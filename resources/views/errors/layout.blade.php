@php
    /*
     * Self-contained error layout: inline CSS, no Vite, no Livewire, no DB.
     * Renders cleanly even during a failed deploy, broken manifest, or DB outage.
     */
    $appName = config('app.name');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? __('Something went wrong') }} · {{ $appName }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta name="theme-color" content="#0a0a0a">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100%; }
        body {
            background: #0a0a0a;
            color: #fafafa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, "Instrument Sans", sans-serif;
            line-height: 1.5;
        }
        .wrap {
            min-height: 100%;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
        }
        header.brand {
            display: flex;
            justify-content: center;
            padding: 0.5rem 0 2rem;
        }
        header.brand a {
            color: #f59e0b;
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: -0.02em;
            text-decoration: none;
        }
        main.content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            max-width: 32rem;
            width: 100%;
            text-align: center;
        }
        .code {
            font-family: ui-monospace, "SF Mono", Menlo, monospace;
            font-size: 0.75rem;
            letter-spacing: 0.2em;
            color: #f59e0b;
            text-transform: uppercase;
            margin: 0;
        }
        h1 {
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            font-weight: 700;
            line-height: 1.1;
            margin: 0.5rem 0 1rem;
            color: #fafafa;
            letter-spacing: -0.02em;
        }
        p.lede { color: #a3a3a3; margin: 0 0 1.75rem; font-size: 1.0625rem; }
        .actions { display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center; }
        a.btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.125rem;
            border-radius: 0.625rem;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            transition: background-color 120ms, border-color 120ms, color 120ms;
        }
        a.btn-primary { background: #f59e0b; color: #0a0a0a; }
        a.btn-primary:hover { background: #fbbf24; }
        a.btn-ghost { border: 1px solid #404040; color: #fafafa; }
        a.btn-ghost:hover { border-color: #737373; background: #1a1a1a; }
        footer.legal {
            color: #525252;
            font-size: 0.75rem;
            text-align: center;
            padding-top: 2rem;
        }
        @media (forced-colors: active) {
            a.btn-primary { background: Highlight; color: HighlightText; }
            a.btn-ghost { border-color: CanvasText; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="brand">
            <a href="{{ url('/') }}">{{ $appName }}</a>
        </header>
        <main class="content">
            <div class="card">
                @yield('error-body')
            </div>
        </main>
        <footer class="legal">
            &copy; {{ date('Y') }} {{ $appName }}
        </footer>
    </div>
</body>
</html>
