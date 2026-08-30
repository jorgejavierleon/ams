{{--
    Full-document shell wrapping a payroll report's table fragment for the PDF
    (dompdf) and Excel (PhpSpreadsheet) writers, which accept a complete HTML
    page with a stylesheet. Kept as its own copy of exports/dt/document.blade.php
    rather than shared, since the DT shell backs a legal compliance surface
    (KOL-15 AC #3: the five DT reports must keep exporting identically) that
    payroll exports must never risk changing.
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

        .group-header th {
            background-color: #d1d5db;
            text-align: center;
            font-weight: bold;
        }

        .numeric {
            text-align: right;
            white-space: nowrap;
        }

        .total-row td {
            background-color: #f3f4f6;
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
