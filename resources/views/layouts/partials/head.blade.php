<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'MyApptimatic')</title>
@if(!empty($portalBranding['favicon_url']))
    <link rel="icon" href="{{ $portalBranding['favicon_url'] }}">
@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
    :root {
        --ink: #0f172a;
        --muted: #64748b;
        --surface: rgba(255, 255, 255, 0.88);
        --surface-strong: #ffffff;
        --border: rgba(15, 23, 42, 0.08);
        --accent: #0d9488;
        --accent-strong: #14b8a6;
        --accent-soft: rgba(20, 184, 166, 0.15);
        --warning: #f59e0b;
        --warning-soft: rgba(245, 158, 11, 0.16);
        --sidebar-bg: #0f172a;
        --sidebar-text: #e2e8f0;
        --sidebar-muted: #94a3b8;
    }

    body {
        font-family: "Manrope", "Segoe UI", sans-serif;
        color: var(--ink);
    }

    html {
        scroll-behavior: smooth;
    }

    h1, h2, h3, h4, h5, h6 {
        font-family: "Space Grotesk", "Segoe UI", sans-serif;
    }

    body.bg-guest {
        background: radial-gradient(circle at top, rgba(20, 184, 166, 0.16), transparent 55%),
            linear-gradient(135deg, #f8fafc 0%, #eef2f7 60%, #e9eff7 100%);
    }

    body.bg-dashboard {
        background: radial-gradient(circle at 15% 15%, rgba(20, 184, 166, 0.2), transparent 55%),
            radial-gradient(circle at 85% 10%, rgba(59, 130, 246, 0.12), transparent 45%),
            linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
    }

    .card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        backdrop-filter: blur(8px);
    }

    .card-muted {
        background: rgba(255, 255, 255, 0.74);
        border: 1px solid var(--border);
        border-radius: 16px;
    }

    .section-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.35em;
        color: var(--muted);
    }

    .sidebar {
        background: var(--sidebar-bg);
        color: var(--sidebar-text);
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.65rem 0.9rem;
        border-radius: 14px;
        color: var(--sidebar-muted);
        transition: all 0.2s ease;
    }

    .nav-link:hover {
        color: var(--sidebar-text);
        background: rgba(148, 163, 184, 0.12);
    }

    .nav-link-active {
        background: rgba(20, 184, 166, 0.16);
        color: #5eead4;
    }

    .pill {
        border-radius: 999px;
        padding: 0.15rem 0.65rem;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.18em;
        background: var(--accent-soft);
        color: var(--accent);
    }

    .fade-in {
        animation: fade-in 0.35s ease-out both;
    }

    .stagger > * {
        animation: rise-in 0.45s ease-out both;
    }

    .stagger > *:nth-child(1) { animation-delay: 0.05s; }
    .stagger > *:nth-child(2) { animation-delay: 0.1s; }
    .stagger > *:nth-child(3) { animation-delay: 0.15s; }
    .stagger > *:nth-child(4) { animation-delay: 0.2s; }
    .stagger > *:nth-child(5) { animation-delay: 0.25s; }
    .stagger > *:nth-child(6) { animation-delay: 0.3s; }

    @keyframes fade-in {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes rise-in {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
