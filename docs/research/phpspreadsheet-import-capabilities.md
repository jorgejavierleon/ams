# PhpSpreadsheet import capabilities — findings for KOL-94.1

Research scope: `phpoffice/phpspreadsheet` **5.9.0** (confirmed via `composer.lock`, `"name": "phpoffice/phpspreadsheet", "version": "5.9.0"`), focused on `Reader\Xlsx` and `Reader\Csv` as the basis for the planned Employee CSV/Excel bulk import framework (KOL-94).

All vendored-source citations below reference `vendor/phpoffice/phpspreadsheet/...` paths. This worktree does not have `vendor/` installed (not vendored into the worktree checkout), so all source reads and the empirical script in Q4 were run against the app's real `vendor/` at `/home/jj/Work/ams/vendor/phpoffice/phpspreadsheet/`, which `composer.lock` confirms is the same installed 5.9.0. Paths below are given relative to `vendor/phpoffice/phpspreadsheet/` for portability.

---

## 1. Streaming / incremental reading

**Bottom line: `Reader\Csv` genuinely streams row-by-row. `Reader\Xlsx` does not — it always parses the complete worksheet XML into memory first, and `IReadFilter` only skips downstream Cell-object construction, not the parse itself.**

### `IReadFilter` / `setReadFilter()` — what it actually skips

- Interface: `src/PhpSpreadsheet/Reader/IReadFilter.php` — single method `readCell(string $columnAddress, int $row, string $worksheetName = ''): bool`.
- **Xlsx**: In `src/PhpSpreadsheet/Reader/Xlsx.php`, the worksheet's XML part is fully read from the zip and parsed with `simplexml_load_string()` in `loadZip()` (lines 133–147) *before* any filtering happens. This call happens at line 866 (`$xmlSheetNS = $this->loadZip("$dir/$fileWorksheet", $mainNS);`) inside the main sheet-loading routine. Only afterwards, in `loadSheetData()` (line 1903 on), does the code iterate the already-fully-parsed XML tree and check `$this->readFilter->readCell(...)` per cell (line 1934) to decide whether to build a `Cell` object. So: **the entire worksheet XML is decompressed and DOM-parsed into a `SimpleXMLElement` tree regardless of the filter; the filter only prevents `Cell`/style objects being materialized in the `Worksheet` for excluded coordinates.** It saves Cell-object memory, not decompress/parse memory or CPU.
  - The CHANGELOG (`vendor/phpoffice/phpspreadsheet/CHANGELOG.md`, under 1.29.0) records "Memory and speed optimisations for Read Filters with Xlsx Files and Shared Formulae" (PR #3474) — this is an optimization of the post-parse filtering path, not a change to the up-front full-XML-parse behavior confirmed above.
- **Csv**: In `src/PhpSpreadsheet/Reader/Csv.php`, `loadStringOrFile2()` (line 396) reads one line at a time via `fgetcsv()` (wrapped as `self::getCsv()`, line 710) inside a `while (is_array($rowData))` loop (line 434). The read filter (`$this->readFilter->readCell($columnLetter, $currentRow)`, line 444) is checked **per cell as each line is parsed**, before any `Cell` object for that coordinate is created — so for CSV the filter genuinely prevents work on skipped cells (no `setValue()` call, no style lookup), on top of the reader never buffering more than the current line.

### The official "chunk reading" recipe

Confirmed via the maintained sample scripts on the project's GitHub repo (not present in the Composer-installed package, which ships only `src/`): [`samples/Reader/11_Reading_a_workbook_in_chunks_using_a_configurable_read_filter_version_1.php`](https://github.com/PHPOffice/PhpSpreadsheet/blob/master/samples/Reader/11_Reading_a_workbook_in_chunks_using_a_configurable_read_filter_version_1.php). The pattern:

```php
class ChunkReadFilter implements IReadFilter {
    public function __construct(int $startRow, int $chunkSize) { ... }
    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool {
        return ($row == 1) || ($row >= $this->startRow && $row < $this->endRow);
    }
}

$reader = IOFactory::createReader($inputFileType);
$chunkSize = 20;
for ($startRow = 2; $startRow <= 240; $startRow += $chunkSize) {
    $chunkFilter = new ChunkReadFilter($startRow, $chunkSize);
    $reader->setReadFilter($chunkFilter);
    $spreadsheet = $reader->load($inputFileName);   // reloads the WHOLE file every iteration
    // ... process $spreadsheet->getActiveSheet() ...
}
```

**Important nuance for KOL-94.4**: given the Q1 finding above, this recipe calls `$reader->load()` fresh for every chunk — for Xlsx that means **re-decompressing and re-parsing the entire worksheet XML on every loop iteration**, just discarding out-of-range rows each time before returning. It trades CPU (O(chunks × file size) parse work) for peak memory (small `Spreadsheet` object per chunk). It is not a true streaming primitive; it is a repeated-full-parse-with-filtered-materialization pattern. For a queued `ProcessImportRun` job, this pattern would be reasonable for Xlsx only if chunk count stays low (e.g. handful of very large sheets) — for row-level idempotent upserts at scale, a single full parse followed by in-app chunking (see below) is cheaper.

The docs site's general read-filter page (rendered from `docs/topics/reading-and-writing-to-file.md` in the GitHub repo, served at https://phpspreadsheet.readthedocs.io/en/latest/topics/reading-and-writing-to-file/) shows the simpler single-filter form and warns: *"Read Filtering does not renumber cell rows and columns. If you filter to read only rows 100-200, cells that you read will still be numbered A100-A200, not A1-A101."* — relevant if `ProcessImportRun` maps filtered chunk results back into a flat `ImportRow` sequence.

### Genuinely streaming primitives

- **Csv**: True line-by-line streaming via `fgetcsv()` as shown above — no requirement to buffer the whole file, **except** when the input encoding isn't UTF-8: `convertNonUtf8()` (`Csv.php` line 308) does `$entireFile = file_get_contents($filename)` and converts the *whole file* into a `php://memory` stream before re-reading it, because `mb_convert_encoding`-style conversion needs the full byte string. So non-UTF-8 CSVs (Latin-1/CP1252 uploads) lose the streaming benefit at the encoding-conversion step, though the subsequent parse is still line-by-line against the converted in-memory buffer.
- **Xlsx**: No native streaming mode exists in 5.9.0. Every worksheet's XML is parsed as a complete DOM tree (`SimpleXMLElement`) via `loadZip()`/`simplexml_load_string()` before any row/cell filtering. There is no generator-based or SAX/XMLReader-based sheet parser in this reader. `setReadDataOnly(true)` (see Q3) reduces *downstream* object creation (styles, comments, drawings, merges — all gated by `if (!$this->readDataOnly)` checks throughout `Xlsx.php`, e.g. lines 649, 878, 890, 914, 920, 932, 939, 949, 984, 989, 1001, 1019, 1337, 1653, 1826, 1832) but does not change the up-front full-XML-parse.
- Separately, `Worksheet::rangeToArrayYieldRows()` (`src/PhpSpreadsheet/Worksheet/Worksheet.php`, line 3156) is a PHP `Generator` that yields one row array at a time from an **already-fully-loaded-in-memory** `Worksheet`. `toArray()`/`rangeToArray()` (lines 3075, 3379) both consume this generator into a full array. Application code could call `rangeToArrayYieldRows()` directly to iterate rows without materializing the full 2-D array — this helps avoid one extra array copy, but the underlying `Worksheet`'s cell collection is still fully populated in memory by that point (for Xlsx, unavoidably; for Csv, only up to whatever the read filter admitted).

### Practical row-count guidance for a synchronous request

- **Confirmed from this app's own runtime** (not the library): PHP `memory_limit` is `-1` (unlimited) in this app's environment, per `php artisan tinker --execute 'echo ini_get("memory_limit");'` inside the Sail container — verified prior to this research, cited here as told, not independently re-verified by this agent.
- **Confirmed from the library's own docs** (`docs/topics/memory_saving.md`, rendered at https://phpspreadsheet.readthedocs.io/en/latest/topics/memory_saving/): *"PhpSpreadsheet uses an average of about 1k per cell (1.6k on 64-bit PHP) in your worksheets."* This is PhpSpreadsheet's own published per-cell memory figure — the only concrete number found in official docs. It is a `Cell`-object cost, not a raw file-size or XML-parse-overhead figure.
- Reasoned estimate (mine, not sourced) for an Employee import with roughly 15–25 mapped columns per row: at ~1.6 KB/cell × 20 cells/row ≈ 32 KB/row of pure `Cell` memory, so 500 rows (the existing export threshold) ≈ 16 MB, 5,000 rows ≈ 160 MB, 50,000 rows ≈ 1.6 GB — all of which `memory_limit=-1` permits, but the last figure risks pressuring the host's actual physical RAM shared with other PHP-FPM workers, MySQL, etc., especially since (a) this is *only* the Cell-object cost, and (b) for Xlsx there's additional, unmeasured-here SimpleXML DOM overhead on top (DOM/SimpleXML parsing commonly runs several times the raw uncompressed XML size in memory — this multiplier is my own general knowledge, not sourced from PhpSpreadsheet docs, and was not independently benchmarked in this task).
- Since `memory_limit` isn't the binding constraint, the sync/queue threshold should really be driven by **wall-clock time** (HTTP request timeout, UX tolerance for a blocking upload-preview step) rather than memory. No PhpSpreadsheet doc gives a rows/sec throughput number; I did not run a timed benchmark as part of this task (out of scope — no representative sample file was available). Given the existing `config/reports.php` precedent uses 500 rows as the sync/queue line for a comparatively simpler export operation, and that CSV parsing (streaming, line-by-line) is materially cheaper than Xlsx parsing (full-DOM) for the same row count, I'd suggest the preview-step threshold decision explicitly account for format: a higher synchronous ceiling is defensible for CSV than for Xlsx given the different parse cost per Q1 above. This is a recommendation for the threshold-owning ticket to weigh, not a benchmark-backed number.

---

## 2. Header row detection & CSV delimiter/encoding/BOM handling

### Header row: no built-in concept

Grepping both readers for any "header row" handling (excluding unrelated page header/footer and image-header hits) returns nothing — `Reader\Xlsx` and `Reader\Csv` have **no concept of a header row at all**. Row 1 is read like any other data row; whatever column-mapping/"is row 1 a header" decision is needed is entirely application-level (exactly matching what the future `ColumnMapping`/`ImportRow` layer will need to own).

### CSV delimiter detection

`Reader\Csv` does **not** require an explicit delimiter — auto-detection is the default:

- `setDelimiter(?string $delimiter): self` (`Csv.php` line 524) lets the app force one.
- If not set, `checkSeparator()` (line 171) first checks for an explicit Excel-style `sep=,` marker on line 1 of the file.
- Otherwise `inferSeparator()` (line 190) delegates to `src/PhpSpreadsheet/Reader/Csv/Delimiter.php`, which samples up to 1,000 lines (`countPotentialDelimiters()`, line 45–55), counts occurrences of each candidate delimiter (`,`, `;`, tab, `|`, `:`, space, `~` — `POTENTIAL_DELIMITERS`, line 7) per line (ignoring characters inside quoted/enclosed sections), and picks the delimiter with the smallest mean-square deviation across lines (`infer()`, line 69) — i.e. the one that appears the most *consistent* number of times per row, not simply the most frequent character. Falls back to `,` (`getDefaultDelimiter()`) if inference is inconclusive.

### CSV encoding handling

- Default `inputEncoding` is `'UTF-8'` (`Csv.php` line 37).
- `setInputEncoding(string $encoding): self` (line 132) lets the app force one, or pass the sentinel `Csv::GUESS_ENCODING` (`'guess'`, line 18) to trigger heuristic detection.
- Even without calling `setInputEncoding()`, `openFileOrMemory()` (line 286) always runs `guessEncodingBom()` (line 659) first — this checks the first 4 bytes against known BOM signatures for UTF-8/16BE/16LE/32BE/32LE and overrides `inputEncoding` if a BOM is found (so **BOM-based encoding auto-detection is always on**, regardless of what you configure).
- If `inputEncoding === Csv::GUESS_ENCODING` explicitly, `guessEncoding()` (line 672) additionally falls back to `guessEncodingNoBom()` (line 635) — a heuristic that checks for UTF-16/32 line-feed byte patterns without a BOM, or validates the content as well-formed UTF-8 via `preg_match('//u', ...)`, defaulting to `CP1252` (`DEFAULT_FALLBACK_ENCODING`, line 17) if nothing matches.
- If the resolved encoding isn't UTF-8, `convertNonUtf8()` (line 308) reads the **entire file** into memory and converts it with `StringHelper::convertEncoding()` before re-parsing — see the streaming caveat in Q1.
- Practical implication: without any config, a Latin-1/CP1252 CSV with no BOM will be silently treated as UTF-8 (since heuristic guessing only runs when `GUESS_ENCODING` is explicitly requested) — the app should either always pass `Csv::GUESS_ENCODING` or run its own `mb_detect_encoding` pre-check before construting the reader, matching the ticket's suspicion.

### BOM handling

`skipBOM()` (`Csv.php` line 159) explicitly checks for and strips a **UTF-8 BOM** (`UTF8_BOM = "\xEF\xBB\xBF"`, line 19) by reading `UTF8_BOM_LEN + 1` bytes and rewinding if it doesn't match — called from `checkSeparator()`/`inferSeparator()`/`listWorksheetInfo()` before row parsing begins, so a UTF-8 BOM is transparently stripped from the data without app intervention. (UTF-16/32 BOMs are detected for the *encoding-guess* step, per above, but the actual multi-byte BOM stripping for those encodings happens as part of the `convertNonUtf8()` re-encode to UTF-8, not via `skipBOM()`.)

---

## 3. Cell type coercion

### Xlsx date cells: serial number + style, not a DateTime

- Xlsx stores dates internally as the Excel numeric date serial (a float), typed `DataType::TYPE_NUMERIC` — **never** as a native PHP `DateTime` or a pre-formatted string. Whether a numeric cell is a "date" is entirely a function of its **cell style's number-format code**, not the value.
- `Shared\Date::isDateTime(Cell $cell, ...)` (`src/PhpSpreadsheet/Shared/Date.php` line 372) is the canonical check: it reads `$worksheet->getStyle($cell->getCoordinate())->getNumberFormat()` and passes the format code to `isDateTimeFormatCode()` (line 421), which regex-matches the Excel format-code syntax for date/time-indicating characters (`eymdHs`), explicitly excluding `"General"` and scientific-notation formats, and special-casing German/Swiss non-date formats containing `-00000`.
- So to distinguish "a real date cell" from "a numeric value that happens to look like a date," application code must call `Date::isDateTime($cell)` (or use `Cell::getFormattedValue()` / a `NumberFormat` check) per cell, then `Date::excelToDateTimeObject($value)` (line 197) to convert the numeric serial into a PHP `DateTime`.
- **Load-bearing caveat for the import job design**: `setReadDataOnly(true)` — the usual perf/memory-saving flag — skips parsing `styles.xml` into per-cell style objects (`Xlsx.php` line 649, `if (!$this->readDataOnly) { foreach ($xfTags as $xfTag) ... }`, guarding the number-format extraction). With `readDataOnly = true`, cells lose their real number-format code, so `Date::isDateTime()` can no longer reliably tell a date column from a plain numeric column. **If `ProcessImportRun` wants both the read-only performance win and reliable date detection, it cannot use `setReadDataOnly(true)` naively — either skip that flag, or have the column-mapping layer declare date columns explicitly (which the import framework likely needs anyway) rather than relying on Excel's inferred formatting.**
- **CSV has no cell styles at all** — a "date" in a CSV is always just a string; `Date::isDateTime()` will never return true for CSV-sourced cells because there's no number-format to inspect. Date columns in CSV imports must be parsed by the app's own date-format logic (e.g. explicit column mapping declaring "this column is a date, in format X"), with no help from the library.

### Numeric-looking strings

- **Xlsx**: the cell's stored data type is explicit in the XML (`t` attribute per `<c>` element — see `DataType::TYPE_STRING`/`TYPE_STRING2`/etc. switch in `loadSheetData()`, `Xlsx.php` lines 1950+), so numeric vs. string is whatever Excel wrote, not inferred from the text.
- **Csv**: every raw string value is routed through `Cell::setValue()` → the configured `IValueBinder` (default `Cell\DefaultValueBinder`, `src/PhpSpreadsheet/Cell/DefaultValueBinder.php`). `dataTypeForValue()` (line 56) applies a regex (`/^[\+\-]?(\d+\.?\d*|\d*\.?\d+)([Ee][\-\+]?[0-2]?\d{1,3})?$/`, line 107) to decide `TYPE_NUMERIC` vs `TYPE_STRING`, **and** `setValueExplicit()` (`src/PhpSpreadsheet/Cell/Cell.php` line 292) for `TYPE_NUMERIC` actually casts the stored value with `$this->value = 0 + $value;` (line 332) — a real PHP int/float, not a string tagged as numeric. So **by default, `"5"` from a CSV becomes native `int(5)`; `"5.50"` becomes `float(5.5)` (losing the trailing zero unless handled elsewhere)** — this is not opt-in, it's the default reader behavior via the default value binder.
- A leading-zero guard exists: `DefaultValueBinder::dataTypeForValue()` explicitly keeps values like `"05"` as `TYPE_STRING` (lines 108–111: if the value minus a leading `+`/`-` starts with `'0'` and the next char isn't `'.'`, force `TYPE_STRING`) — protecting things like zip codes / leading-zero IDs from being silently cast to `5`.
- A separate, **opt-in** feature, `castFormattedNumberToNumeric(bool $castFormattedNumberToNumeric, bool $preserveNumericFormatting = false)` (`Csv.php` line 345), additionally strips thousands separators from strings like `"1,234.56"` before the normal numeric cast — off by default (`protected bool $castFormattedNumberToNumeric = false;`, line 88).
- Booleans: CSV strings `"true"`/`"false"` (or custom values via `setGetTrue()`/`setGetFalse()`) are auto-converted to PHP `bool` by `convertBoolean()` (line 475) **unless** the configured value binder implements `getBooleanConversion()` and returns true, in which case the raw string is preserved for the binder to interpret itself.

### Empty cells

- Both readers only create a `Cell` object for coordinates that actually get a value written. For **Csv**, `loadStringOrFile2()` explicitly skips writing a cell when `$rowDatum === ''` **unless** `setPreserveNullString(true)` was called (line 444: `if (($rowDatum !== '' || $this->preserveNullString) && $this->readFilter->readCell(...))`) — so by default, an empty CSV field simply never becomes a `Cell` in the worksheet.
- For **Xlsx**, cells with no content are typically simply absent from the sparse worksheet XML (`<row>` only lists `<c>` elements that have content), so they likewise never become `Cell` objects.
- Consuming code determines what "missing" looks like via the `nullValue` parameter (default `null`) on `Worksheet::toArray()`/`rangeToArray()`/`rangeToArrayYieldRows()` (`src/PhpSpreadsheet/Worksheet/Worksheet.php`, e.g. line 3077's docblock: *"Value returned in the array entry if a cell doesn't exist"*) — a genuinely-missing `Cell` produces `$nullValue` (`null` by default) in the output array, indistinguishable from `setPreserveNullString(true)` + an actual empty string unless you pass a non-null sentinel for `$nullValue` and preserve nulls on the CSV side. `calculateFormulas`/`formatData` (also params on `toArray()`/`rangeToArray()`, lines 3078–3079) control whether formula cells are evaluated and whether number formatting is applied to the returned value (e.g., returning `"$1,234.00"` vs `1234.0`) — irrelevant for CSV (no formulas/formats) but relevant if Xlsx imports may contain formula-derived values.

### What the future `ImportRow`/`ColumnMapping` layer must do itself

1. Decide which row is the header (readers have no concept of this).
2. Decide, per mapped column, whether it's a date — and for CSV, parse it manually; for Xlsx, either avoid `setReadDataOnly(true)` and call `Date::isDateTime()`/`excelToDateTimeObject()`, or just declare date columns explicitly in the mapping config instead of trusting Excel's formatting.
3. Decide how to treat leading-zero-looking numeric strings that DID get cast by the CSV binder (e.g. legitimately-numeric-but-zero-padded custom fields the leading-zero guard didn't happen to catch) — validate/re-stringify as needed per field type.
4. Normalize `null`/`''`/missing-cell ambiguity per the `nullValue` behavior above — decide what "blank" means per required-ness of a field.
5. Decide the delimiter/encoding/BOM strategy explicitly rather than relying purely on defaults, per Q2 (e.g., always pass `Csv::GUESS_ENCODING` rather than trusting the UTF-8 default).

---

## 4. Reliable format detection for upload validation

### How `IOFactory::identify()` / `createReaderForFile()` work

`src/PhpSpreadsheet/IOFactory.php`:

- `createReaderForFile()` (line 181) first takes a "lucky guess" from the file extension via `getReaderTypeFromExtension()` (line 196), instantiates that reader, and calls its `canRead($filename)` (line 201) — **only accepting the extension-based guess if `canRead()` independently confirms it**. If that fails, it **iterates every other registered reader type** (line 208) and calls each one's `canRead()` until one returns true (line 212), throwing `Reader\Exception('Unable to identify a reader for this file')` (line 218) if none match. So detection is genuinely content-based with the extension only used as a first-try optimization, not as the source of truth.
- `Xlsx::canRead()` (`Xlsx.php` line 91) actually opens the file with `ZipArchive::open()` (line 100) and verifies it contains a resolvable workbook part (`getWorkbookBaseName()`) — i.e., it structurally validates the ZIP/OOXML container, not just a magic-byte sniff.
- `Csv::canRead()` (`Csv.php` line 594) is comparatively weak: it trusts the `.csv`/`.tsv` extension outright (line 607) without inspecting content, and only falls back to `mime_content_type()` sniffing (against `text/csv`, `text/plain`, etc.) for other extensions (line 612–622). **This means a real XLSX binary saved with a `.csv` extension is accepted by `Csv::canRead()` purely because of the extension**, without any content check.

### Empirical check (first-hand, ad hoc script — not committed)

I wrote and ran a throwaway PHP script (`/tmp/.../scratchpad/format_detect_test.php`, executed via the host's `php` CLI against this app's real `vendor/autoload.php`, then deleted from the temp scratch directory — never added to the repo) that built: (a) a real CSV file saved as `fake.xlsx`, and (b) a real XLSX file (via `IOFactory::createWriter(..., 'Xlsx')`) saved as `fake.csv`, then exercised both `IOFactory` auto-detection and directly-forced mismatched readers. Results:

| Scenario | Result |
|---|---|
| `IOFactory::identify()` on CSV-content-but-`.xlsx`-named file | Correctly identified as `Csv` |
| `IOFactory::identify()` on XLSX-content-but-`.csv`-named file | Correctly identified as `Xlsx` |
| `IOFactory::createReaderForFile()` full pipeline, both mismatches above | Correctly resolved and loaded both files with correct data in both directions |
| `new Xlsx(); $reader->load($csvFileNamedXlsx)` (forcing the wrong reader directly) | `canRead()` returned `false`; `load()` threw `PhpOffice\PhpSpreadsheet\Reader\Exception`: *"Could not find zip member zip:///.../fake.xlsx#_rels\.rels"* — a clear, catchable exception |
| `new Csv(); $reader->load($xlsxFileNamedCsv)` (forcing the wrong reader directly) | `canRead()` returned `true` (extension-trusted); `load()` **did not throw** — it silently parsed the binary ZIP bytes as "CSV rows," producing garbled binary-string cell values with no error |

**Conclusion: `IOFactory`'s auto-detection (`identify()`/`createReaderForFile()`) is reliable for both rename directions and should always be used for upload validation** rather than trusting the uploaded file's claimed extension or MIME type from the browser. Conversely, **never construct `Reader\Csv` directly based on a trusted `.csv` extension** — if a user renames an `.xlsx` to `.csv` (or an unrelated binary file), `Reader\Csv::load()` will not error, it will silently misparse and hand the app garbage rows. The safe pattern for the upload-validation step is: always resolve the reader via `IOFactory::createReaderForFile()`/`identify()`, and treat the resolved class as the source of truth for "what kind of file is this," independent of (and validated against, if desired) the extension the browser/user supplied.

---

## Implications for KOL-94.4 / preview threshold ticket

1. **Upload validation**: use `IOFactory::createReaderForFile()` (or `identify()`) against the actual uploaded file content — never instantiate `Reader\Csv`/`Reader\Xlsx` directly based on client-supplied extension/MIME type. `Xlsx::canRead()` does real ZIP/OOXML structural validation; `Csv::canRead()` is extension-trusting and will not catch a renamed binary file, so relying on `IOFactory`'s full multi-reader fallback (not a single forced reader) is the only reliably safe path.
2. **No header-row help from the library**: `ColumnMapping`/`ImportRow` must own "row 1 is the header," for both formats, unconditionally.
3. **CSV parsing config should not be left at defaults**: explicitly call `Csv::GUESS_ENCODING` (or run the app's own `mb_detect_encoding`) rather than relying on the reader's default `UTF-8` assumption (which only auto-corrects for an explicit BOM); delimiter auto-detection (`inferSeparator()`) is reasonable to trust as-is, or expose `setDelimiter()` as an explicit override in the import wizard for edge cases.
4. **Numeric coercion is already happening for CSV by default** (`"5"` → `int(5)` via `DefaultValueBinder`) — `ImportRow` normalization needs to account for this (e.g., re-stringify before validating a field that's semantically a string, like a phone extension or code that happens to be all-digits and not covered by the leading-zero guard).
5. **Date handling differs sharply by format**: Xlsx can carry real style-based date info (via `Date::isDateTime()`), but **only if `setReadDataOnly(true)` is NOT used** — reading Xlsx with `readDataOnly=true` for performance forfeits reliable date detection. CSV never carries date typing at all — both cases likely mean the column-mapping UI should let the user (or a heuristic) declare "this column is a date, in format X" explicitly rather than depending on library-side inference, which keeps behavior consistent across both file formats anyway.
6. **Chunked/streaming reading for `ProcessImportRun`**:
   - **CSV**: genuinely streams row-by-row (`fgetcsv()` loop) — well suited to processing in application-level batches (e.g., accumulate N `ImportRow`s, upsert, repeat) without needing PhpSpreadsheet's `IReadFilter` chunk trick, and without re-parsing the file. This is the cheap case, and it's cheap regardless of file size (modulo the non-UTF-8-encoding full-file-buffer caveat in Q1).
   - **Xlsx**: no true streaming exists in 5.9.0 — the full worksheet XML is always parsed into memory in one shot inside `load()`. The official "chunk reading" recipe (`IReadFilter` + a loop over row ranges) re-parses the whole file per chunk, so it should **not** be used purely to save CPU/time in a queued job — its only benefit is bounding *peak* `Cell`-object memory per iteration, at the cost of `O(chunks)` full re-parses. Given `memory_limit=-1` and that a queued job has much more generous wall-clock budget than a sync request, **the simplest and likely cheapest approach for `ProcessImportRun` is a single full `load()` of the Xlsx file, then chunk the resulting in-memory `Worksheet` rows in PHP** (e.g. via `rangeToArrayYieldRows()` or a plain loop with `getRowIterator()`) for batched upserts — rather than re-invoking the PhpSpreadsheet reader per chunk.
   - Idempotency/upsert design in `ProcessImportRun` should therefore be driven by row-batch size in application code (independent of PhpSpreadsheet), not by PhpSpreadsheet's `IReadFilter` chunking mechanism, except possibly as a memory-emergency fallback for pathologically large Xlsx files.
7. **Sync/queue threshold**: no primary source gives a rows/sec throughput figure; the only hard number found is PhpSpreadsheet's own ~1.6 KB/cell (64-bit PHP) `Cell`-object memory figure (`docs/topics/memory_saving.md`). With `memory_limit=-1` confirmed in this app, memory is not the constraint — wall-clock time for the synchronous preview request is. Recommend that whoever owns the threshold ticket either runs a real timed benchmark with a representative Employee sample file (not done here — no sample file existed to benchmark against) or, at minimum, sets separate thresholds for CSV vs. Xlsx given their very different parse costs, rather than reusing a single flat number across both formats the way `config/reports.php`'s `queue_threshold` does for a single export path.
