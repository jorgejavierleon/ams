<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Reader\Html as HtmlSpreadsheetReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html as WordHtml;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders an HTML table fragment into any downloadable report format —
 * Excel, CSV, PDF or Word — using only the packages the project already
 * ships (phpspreadsheet, phpword, dompdf). Every writer consumes the same
 * fragment a report renders once, so every format stays identical to the
 * others and to the on-screen table.
 *
 * Shared by DtReportExporter (the five Resolución 38 reports) and payroll
 * report exports.
 */
class ReportWriter
{
    private const DEFAULT_FONT = 'Arial';

    private const DEFAULT_FONT_SIZE = 8;

    /**
     * Render an HTML table fragment into a single-sheet .xlsx workbook.
     */
    public function excel(string $html, string $filename): StreamedResponse
    {
        return $this->streamXlsx((new HtmlSpreadsheetReader)->loadFromString($html), $filename);
    }

    /**
     * Render several HTML table fragments into one .xlsx workbook, one named
     * sheet per fragment, in the given order.
     *
     * @param  array<string, string>  $sheets  sheet title => HTML table fragment
     */
    public function excelSheets(array $sheets, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;

        $index = 0;
        foreach ($sheets as $title => $html) {
            (new HtmlSpreadsheetReader)->setSheetIndex($index)->loadFromString($html, $spreadsheet);
            $spreadsheet->getSheet($index)->setTitle($title);
            $index++;
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $this->streamXlsx($spreadsheet, $filename);
    }

    /**
     * Render an HTML table fragment into a UTF-8 CSV with a BOM, so Excel
     * does not mangle acentos and eñes, and a configurable delimiter,
     * because Excel under a Chilean regional setting expects semicolons.
     */
    public function csv(string $html, string $filename, string $delimiter = ','): StreamedResponse
    {
        $spreadsheet = (new HtmlSpreadsheetReader)->loadFromString($html);

        $writer = new CsvWriter($spreadsheet);
        $writer->setUseBOM(true);
        $writer->setDelimiter($delimiter);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            "{$filename}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * Render a full HTML document into a landscape letter PDF.
     */
    public function pdf(string $html, string $filename): Response
    {
        return Pdf::loadHTML($html)
            ->setPaper('letter', 'landscape')
            ->download("{$filename}.pdf");
    }

    /**
     * Render an HTML table fragment into a .docx document. PhpWord's HTML
     * reader parses with a strict XML parser, so it must be fed a
     * well-formed fragment (no document shell).
     */
    public function word(string $fragment, string $filename): StreamedResponse
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName(self::DEFAULT_FONT);
        $phpWord->setDefaultFontSize(self::DEFAULT_FONT_SIZE);

        $section = $phpWord->addSection(['orientation' => 'landscape']);
        WordHtml::addHtml($section, $fragment, false, false);

        $writer = WordIOFactory::createWriter($phpWord, 'Word2007');

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            "{$filename}.docx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }

    private function streamXlsx(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $spreadsheet->getDefaultStyle()->getFont()->setName(self::DEFAULT_FONT)->setSize(self::DEFAULT_FONT_SIZE);

        $writer = new XlsxWriter($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            "{$filename}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
