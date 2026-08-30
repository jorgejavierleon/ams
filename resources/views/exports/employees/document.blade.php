{{--
    Full-document shell wrapping the employee master's table fragment for the
    Excel (PhpSpreadsheet) writer, which accepts a complete HTML page with a
    stylesheet. Kept as its own copy of exports/payroll/document.blade.php
    (itself a copy of exports/dt/document.blade.php) rather than shared, so a
    payroll or DT report shell can change without risking this one.
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

        .numeric {
            text-align: right;
            white-space: nowrap;
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
