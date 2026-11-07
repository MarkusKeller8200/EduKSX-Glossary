<!DOCTYPE html>
<html lang="de-CH" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="content-language" content="de-CH">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="EduKSX-Glossary – Begriffe und Abkürzungen für Schweizer Volksschulen, Schulgemeinden und Gemeinden.">

        {{-- Systemvorgabe hell/dunkel sofort anwenden (wie im Intranet) --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';
                if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        {{-- Hintergrund passend zu resources/css/app.css (CI: surface hell/dunkel) --}}
        <style>
            html { background-color: #ffffff; }
            html.dark { background-color: #151b24; }
        </style>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <meta name="theme-color" content="#ff8a00">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'EduKSX-Glossary') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
