{{--
    Full-document shell wrapping a report's table fragment for the PDF (dompdf)
    and Excel (PhpSpreadsheet) writers, which accept a complete HTML page with a
    stylesheet. The Word writer consumes the bare fragment instead, since its
    HTML reader requires well-formed XML. The maximum font is Arial 8 (Art. 28 e)
    and the fixed-layout tables fit the page without manual configuration
    (Art. 28 d).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * {
            font-family: Arial, sans-serif;
            font-size: 8pt;
        }

        body {
            margin: 0;
        }

        h1 {
            font-size: 10pt;
            margin: 0 0 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #000000;
            padding: 2px 4px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }

        thead th {
            background-color: #e5e7eb;
        }

        .block-header th {
            background-color: #f3f4f6;
            font-weight: bold;
        }

        .total-row td {
            background-color: #f3f4f6;
            font-weight: bold;
        }

        .grand-total-row td {
            background-color: #d1d5db;
            font-weight: bold;
        }

        .legend {
            text-align: center;
            font-style: italic;
        }
    </style>
</head>
<body>
    {!! $content !!}
</body>
</html>
