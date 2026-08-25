<?php

use App\Services\Reports\ReportWriter;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

uses(TestCase::class);

/**
 * A minimal well-formed HTML table fragment, standing in for what a report's
 * Blade view would render. Contains acentos, an eñe, and a RUT so CSV
 * encoding/delimiter behaviour can be exercised (AC #4, #5).
 */
function sampleReportHtml(): string
{
    return <<<'HTML'
        <table>
            <tr><th>Nombre</th><th>RUT</th><th>Función</th></tr>
            <tr><td>Peña, José</td><td>11.111.111-1</td><td>Diseño; Producción</td></tr>
        </table>
        HTML;
}

function streamedResponseContent(Response $response): string
{
    return TestResponse::fromBaseResponse($response)->streamedContent();
}

function spreadsheetFromXlsxResponse(Response $response): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, streamedResponseContent($response));

    try {
        return (new XlsxReader)->load($path);
    } finally {
        unlink($path);
    }
}

test('excel renders the report fragment into a single-sheet xlsx workbook', function () {
    $response = (new ReportWriter)->excel(sampleReportHtml(), 'reporte');

    expect($response->headers->get('content-type'))
        ->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $spreadsheet = spreadsheetFromXlsxResponse($response);
    $sheet = $spreadsheet->getActiveSheet();

    expect($sheet->getCell('A1')->getValue())->toBe('Nombre')
        ->and($sheet->getCell('A2')->getValue())->toBe('Peña, José')
        ->and($sheet->getCell('B2')->getValue())->toBe('11.111.111-1');
});

test('excelSheets builds one named sheet per fragment, in order', function () {
    $writer = new ReportWriter;

    $response = $writer->excelSheets([
        'Altas' => '<table><tr><td>Alta uno</td></tr></table>',
        'Bajas' => '<table><tr><td>Baja uno</td></tr></table>',
        'Vacaciones' => '<table><tr><td>Vacación uno</td></tr></table>',
    ], 'movimientos');

    $spreadsheet = spreadsheetFromXlsxResponse($response);

    expect($spreadsheet->getSheetCount())->toBe(3)
        ->and($spreadsheet->getSheet(0)->getTitle())->toBe('Altas')
        ->and($spreadsheet->getSheet(0)->getCell('A1')->getValue())->toBe('Alta uno')
        ->and($spreadsheet->getSheet(1)->getTitle())->toBe('Bajas')
        ->and($spreadsheet->getSheet(1)->getCell('A1')->getValue())->toBe('Baja uno')
        ->and($spreadsheet->getSheet(2)->getTitle())->toBe('Vacaciones')
        ->and($spreadsheet->getSheet(2)->getCell('A1')->getValue())->toBe('Vacación uno');
});

test('csv defaults to a comma delimiter with a UTF-8 BOM', function () {
    $response = (new ReportWriter)->csv(sampleReportHtml(), 'reporte');

    expect($response->headers->get('content-type'))->toBe('text/csv; charset=UTF-8');

    $content = streamedResponseContent($response);

    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($content)->toContain("\xEF\xBB\xBF\"Nombre\",\"RUT\",\"Función\"")
        ->and($content)->toContain('"Peña, José","11.111.111-1","Diseño; Producción"');
});

test('csv delimiter is selectable, e.g. semicolon for Chilean Excel', function () {
    $response = (new ReportWriter)->csv(sampleReportHtml(), 'reporte', delimiter: ';');

    $content = streamedResponseContent($response);

    expect($content)->toContain('"Nombre";"RUT";"Función"')
        ->and($content)->toContain('"Peña, José";"11.111.111-1";"Diseño; Producción"');
});

test('pdf renders the html document into a downloadable pdf', function () {
    $response = (new ReportWriter)->pdf('<html><body><h1>Reporte</h1></body></html>', 'reporte');

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

test('word renders the report fragment into a downloadable docx', function () {
    $response = (new ReportWriter)->word(sampleReportHtml(), 'reporte');

    expect($response->headers->get('content-type'))
        ->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    // A .docx file is a zip archive; its first bytes carry the zip signature.
    expect(substr(streamedResponseContent($response), 0, 2))->toBe('PK');
});

test('excel and csv carry the same report content for the same fragment', function () {
    $writer = new ReportWriter;
    $html = sampleReportHtml();

    $excel = spreadsheetFromXlsxResponse($writer->excel($html, 'reporte'))->getActiveSheet();
    $csv = streamedResponseContent($writer->csv($html, 'reporte'));

    expect($excel->getCell('A2')->getValue())->toBe('Peña, José')
        ->and($excel->getCell('B2')->getValue())->toBe('11.111.111-1')
        ->and($csv)->toContain('Peña, José')
        ->and($csv)->toContain('11.111.111-1');
});
