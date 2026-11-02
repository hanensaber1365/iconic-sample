<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'AudioLab')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/js/app.js'])

    <style>
        :root {
            /* Palette taken from the reference image */
            --ink:        #04090a;   /* deep black background */
            --panel:      #0a1315;   /* dark teal-black surface */
            --panel-2:    #101c1f;
            --steel:      #2a3438;   /* brushed metal grey */
            --line:       rgba(31, 209, 198, .16);
            --teal:       #1fd1c6;   /* main glow / logo teal */
            --teal-soft:  rgba(31, 209, 198, .12);
            --teal-glow:  rgba(31, 209, 198, .35);
            --text:       #ffffff;
            --muted:      #8fa3a6;
            --danger:     #ff6b6b;
            --sidebar-w:  260px;
            --display: 'Sora', 'Segoe UI', sans-serif;
            --body: 'IBM Plex Sans', 'Segoe UI', sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: var(--ink); }

        /* Everything lives inside .al-app so Bootstrap can't override it */
        .al-app {
            min-height: 100vh;
            background:
                radial-gradient(900px 500px at 85% -10%, rgba(31,209,198,.10), transparent 60%),
                radial-gradient(700px 500px at 0% 100%, rgba(31,209,198,.06), transparent 60%),
                var(--ink);
            color: var(--text);
            font-family: var(--body);
            font-size: 16px;
            line-height: 1.6;
        }
        .al-app h1, .al-app h2, .al-app h3, .al-app h4, .al-app h5, .al-app h6 { color: var(--text); margin-top: 0; }
        .al-app p { color: inherit; }
        .al-app a, .al-app a:hover { color: inherit; text-decoration: none; }
        .al-app :focus-visible { outline: 2px solid var(--teal); outline-offset: 3px; }

        /* ---------- Sidebar ---------- */
        .al-sidebar {
            position: fixed; inset: 0 auto 0 0;
            width: var(--sidebar-w);
            background: linear-gradient(180deg, #071012, var(--ink));
            border-right: 1px solid var(--line);
            display: flex; flex-direction: column;
            z-index: 1000;
        }
        .al-brand {
            display: flex; align-items: center; gap: 12px;
            padding: 26px 22px;
            border-bottom: 1px solid var(--line);
            font-family: var(--display);
            font-weight: 700; font-size: 20px; letter-spacing: .01em;
        }
        .al-brand-mark {
            width: 38px; height: 38px; flex: none;
            border-radius: 11px;
            border: 1px solid var(--teal);
            display: grid; place-items: center;
            box-shadow: 0 0 18px var(--teal-glow), inset 0 0 12px var(--teal-soft);
        }
        .al-nav { padding: 18px 14px; flex: 1; }
        .al-app .al-nav a {
            display: flex; align-items: center; gap: 14px;
            padding: 12px 14px; margin-bottom: 4px;
            border-radius: 12px;
            color: var(--muted); font-weight: 500;
            border: 1px solid transparent;
            transition: color .2s, background .2s, border-color .2s;
        }
        .al-app .al-nav a svg { width: 20px; height: 20px; flex: none; stroke: currentColor; fill: none; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
        .al-app .al-nav a:hover { color: var(--text); background: var(--panel-2); }
        .al-app .al-nav a.active {
            color: var(--teal);
            background: var(--teal-soft);
            border-color: var(--line);
            box-shadow: 0 0 22px rgba(31,209,198,.10);
        }
        .al-sidebar-foot {
            margin: 14px; padding: 14px 16px;
            border: 1px solid var(--line); border-radius: 14px;
            background: var(--panel);
            font-size: 13px; color: var(--muted);
        }
        .al-sidebar-foot strong { display: block; color: var(--text); font-weight: 600; }

        /* ---------- Main ---------- */
        .al-main { margin-left: var(--sidebar-w); min-height: 100vh; display: flex; flex-direction: column; }
        .al-topbar {
            height: 76px; padding: 0 34px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid var(--line);
            background: rgba(4, 9, 10, .72);
            backdrop-filter: blur(14px);
            position: sticky; top: 0; z-index: 900;
        }
        .al-topbar h1 { margin: 0; font-family: var(--display); font-size: 18px; font-weight: 600; }
        .al-topbar small { color: var(--muted); }
        .al-status {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 8px 16px; border-radius: 999px;
            border: 1px solid var(--line); background: var(--teal-soft);
            font-size: 13px; font-weight: 500;
        }
        .al-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--teal); box-shadow: 0 0 10px var(--teal); }

        .al-content { flex: 1; padding: 34px; width: 100%; }
        .al-footer { padding: 20px 34px; border-top: 1px solid var(--line); color: var(--muted); font-size: 13px; text-align: center; }

        @media (max-width: 900px) {
            :root { --sidebar-w: 74px; }
            .al-brand { justify-content: center; padding: 22px 0; }
            .al-brand-text, .al-nav a span, .al-sidebar-foot { display: none; }
            .al-app .al-nav a { justify-content: center; padding: 14px 0; }
            .al-topbar, .al-content, .al-footer { padding-left: 18px; padding-right: 18px; }
            .al-topbar small { display: none; }
        }
        @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
    </style>
    @stack('styles')
</head>

<body>
<div class="al-app">

<aside class="al-sidebar">
    <div class="al-brand">
        <div class="al-brand-mark">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1fd1c6" stroke-width="2" stroke-linecap="round">
                <path d="M2 12h3l2-6 4 12 3-9 2 3h6"/>
            </svg>
        </div>
        <span class="al-brand-text">AudioLab</span>
    </div>

    <nav class="al-nav">
        <a href="/" class="{{ request()->is('/') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
            <span>Dashboard</span>
        </a>
        <a href="/results" class="{{ request()->is('results*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/></svg>
            <span>Results</span>
        </a>
        <a href="{{ route('results.export') }}">
            <svg viewBox="0 0 24 24"><path d="M12 4v12M7 11l5 5 5-5M4 20h16"/></svg>
            <span>Export to Excel</span>
        </a>
    </nav>

    <div class="al-sidebar-foot">
        <strong>Band 200 – 4000 Hz</strong>
        Fundamental search range
    </div>
</aside>

<div class="al-main">
    <header class="al-topbar">
        <div>
            <h1>Audio Processing Dashboard</h1>
            <small>Manage and process your audio samples</small>
        </div>
        <span class="al-status"><span class="al-dot"></span> System online</span>
    </header>

    <main class="al-content">
        @yield('content')
    </main>

    <footer class="al-footer">
        © {{ date('Y') }} AudioLab — Audio Processing System
    </footer>
</div>

</div>

@stack('scripts')
</body>
</html>