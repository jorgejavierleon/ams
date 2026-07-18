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

        .signatures {
            margin-top: 28px;
            padding-top: 12px;
            border-top: 2px solid #1a1a1a;
        }
        .signatures h2 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 12px;
        }
        .signature {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 8px 10px;
            margin-bottom: 8px;
        }
        .signature .name { font-weight: bold; }
        .signature .meta { font-size: 10px; color: #475569; }
        .signature .hash {
            font-size: 8px;
            color: #64748b;
            word-break: break-all;
        }
    </style>
</head>
<body>
    {!! $body !!}

    <div class="signatures">
        <h2>{{ __('ui.documents.pdf.signatures_heading') }}</h2>

        @foreach ($signatures as $signature)
            <div class="signature">
                <div class="name">{{ $signature['name'] }} <span class="meta">— {{ $signature['type'] }}</span></div>
                <div class="meta">
                    {{ __('ui.documents.pdf.rut') }}: {{ $signature['rut'] }} &nbsp;·&nbsp;
                    {{ __('ui.documents.pdf.email') }}: {{ $signature['email'] }}
                </div>
                <div class="meta">{{ __('ui.documents.pdf.signed_at') }}: {{ $signature['signed_at'] }}</div>
                <div class="hash">{{ __('ui.documents.pdf.hash') }}: {{ $signature['hash'] }}</div>
            </div>
        @endforeach
    </div>
</body>
</html>
