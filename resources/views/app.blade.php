<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'light') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "light" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }

            /*
              First-load splash for slow connections: shown while #app is still empty (JS downloading),
              hidden automatically once Vue renders. Inline CSS + SVG only — no extra downloads.
            */
            .boot-splash {
                position: fixed;
                inset: 0;
                z-index: 50;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 1rem;
                color: #0a0a0a;
                font: 500 0.875rem/1.4 ui-sans-serif, system-ui, sans-serif;
                animation: boot-fade-in 0.2s 0.3s both; /* only appears if loading takes > 300ms */
            }
            html.dark .boot-splash { color: #fafafa; }
            #app:not(:empty) ~ .boot-splash { display: none; }
            .boot-splash__icon { width: 3rem; height: 3rem; padding: 0.625rem; border-radius: 0.75rem; background: #dc2626; color: #fff; }
            .boot-splash__track { width: 8rem; height: 0.25rem; overflow: hidden; border-radius: 9999px; background: rgb(127 127 127 / 0.25); }
            .boot-splash__bar { width: 40%; height: 100%; border-radius: inherit; background: #dc2626; animation: boot-slide 1.1s ease-in-out infinite; }
            @keyframes boot-fade-in { from { opacity: 0; } to { opacity: 1; } }
            @keyframes boot-slide { from { transform: translateX(-100%); } to { transform: translateX(250%); } }
            @media (prefers-reduced-motion: reduce) {
                .boot-splash__bar { animation: none; width: 100%; opacity: 0.6; }
            }
        </style>

        <link rel="icon" href="/favicon.ico?v=clean-logo-2" sizes="any">
        <link rel="icon" href="/favicon.svg?v=clean-logo-2" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=clean-logo-2">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />

        <div class="boot-splash" role="status">
            <x-icon name="dumbbell" class="boot-splash__icon" />
            <div class="boot-splash__track"><div class="boot-splash__bar"></div></div>
            <span class="sr-only">Loading</span>
        </div>
    </body>
</html>
