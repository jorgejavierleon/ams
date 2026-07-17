<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28mm 22mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #1a1a1a;
        }
        h1 { font-size: 20px; margin: 0 0 12px; }
        h2 { font-size: 16px; margin: 18px 0 8px; }
        h3 { font-size: 14px; margin: 16px 0 6px; }
        p { margin: 0 0 10px; text-align: justify; }
        ul, ol { margin: 0 0 10px; padding-left: 20px; }
        li { margin-bottom: 4px; }
        blockquote {
            margin: 0 0 10px;
            padding-left: 12px;
            border-left: 3px solid #cbd5e1;
            color: #475569;
        }
        strong { font-weight: bold; }
        em { font-style: italic; }
    </style>
</head>
<body>
    {!! $body !!}
</body>
</html>
