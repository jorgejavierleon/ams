<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>

        <script>
            (function () {
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @vite('resources/css/app.css')
    </head>
    <body class="flex min-h-screen items-center justify-center bg-background font-sans antialiased">
        <div class="mx-4 w-full max-w-sm rounded-lg border border-border bg-card p-8 text-center shadow-sm">
            @if ($expired)
                <h1 class="text-lg font-semibold text-card-foreground">{{ __('ui.dt.reports.export.expired_heading') }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">{{ __('ui.dt.reports.export.expired_body') }}</p>
            @else
                <h1 class="text-lg font-semibold text-card-foreground">{{ __('ui.dt.reports.export.ready_heading') }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">{{ __('ui.dt.reports.export.ready_body') }}</p>

                <a
                    href="{{ route('dt.reports.exports.download', ['reportExport' => $reportExport->id]) }}"
                    class="mt-6 inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    {{ __('ui.dt.reports.export.download') }}
                </a>
            @endif
        </div>
    </body>
</html>
