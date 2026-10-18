<?php
/**
 * ------------------------------------------------------------
 * about.php – Rpay Overview & Platform Showcase
 * Aesthetic: PayPal Open Design System (https://www.paypal.com/in/home)
 * ------------------------------------------------------------
 * Engineered by RLabz (https://rlabz.in)
 * ------------------------------------------------------------
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedInAdmin = !empty($_SESSION['vortex_admin_id']);
$isLoggedInMerchant = !empty($_SESSION['vortex_client_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rpay Open – One Platform for All Your Payment Needs | RLabz</title>
    <meta name="description" content="Discover Rpay (PayVortex), the unified payment orchestration platform engineered by RLabz. Resilient multi-rail routing, instant auto-failover, zero-friction checkout, and enterprise cryptographic security.">
    <link rel="icon" type="image/png" href="assets/images/rpay-icon.png">
    
    <!-- Google Fonts: Outfit (Headings), Ubuntu (Logo Wordmark), Inter (Body), JetBrains Mono (Code) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:ital,wght@0,400;0,500;0,600;0,700;0,800;1,700;1,800&family=Ubuntu:ital,wght@1,700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Immediate Flash-Free Theme Initializer -->
    <script>
        (function() {
            var saved = localStorage.getItem('vortex_theme');
            if (saved === 'dark' || (!saved && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root, [data-theme="light"] {
            --bg-page: #f9fbfe;
            --bg-canvas: #ffffff;
            --bg-card: #ffffff;
            --bg-card-subtle: #f1f5fa;
            --border-card: #e2e8f0;
            --border-subtle: #edf2f7;
            --text-heading: #0A1938;
            --text-body: #334155;
            --text-muted: #64748b;
            
            --paypal-navy: #0A1938;
            --paypal-deep-blue: #003087;
            --paypal-bright-blue: #0070e0;
            --paypal-cyan: #06b6d4;
            --paypal-gold: #ffc439;
            --accent-green: #10b981;
            
            --hero-bg-gradient: radial-gradient(circle at 50% -20%, rgba(0, 112, 224, 0.12) 0%, rgba(249, 251, 254, 0) 70%);
            --nav-bg: rgba(255, 255, 255, 0.88);
            --nav-border: #e2e8f0;
            --pill-active-bg: #0A1938;
            --pill-active-text: #ffffff;
            --pill-inactive-bg: #f1f5fa;
            --pill-inactive-text: #475569;
            
            --card-shadow: 0 4px 20px -2px rgba(10, 25, 56, 0.06), 0 2px 6px -1px rgba(0, 0, 0, 0.02);
            --card-hover-shadow: 0 20px 40px -4px rgba(0, 48, 135, 0.12), 0 4px 12px -2px rgba(0, 0, 0, 0.04);
            --code-bg: #0A1938;
            --code-text: #e2e8f0;
            --toggle-bg: #ffffff;
            --toggle-border: #cbd5e1;
            --toggle-icon: #5f6368;
        }

        [data-theme="dark"] {
            --bg-page: #070d18;
            --bg-canvas: #0c1424;
            --bg-card: #0e192e;
            --bg-card-subtle: #14223d;
            --border-card: #1e3052;
            --border-subtle: #172642;
            --text-heading: #f8fafc;
            --text-body: #cbd5e1;
            --text-muted: #94a3b8;
            
            --paypal-navy: #0A1938;
            --paypal-deep-blue: #1e40af;
            --paypal-bright-blue: #60a5fa;
            --paypal-cyan: #38bdf8;
            --paypal-gold: #facc15;
            --accent-green: #34d399;
            
            --hero-bg-gradient: radial-gradient(circle at 50% -20%, rgba(96, 165, 250, 0.15) 0%, rgba(7, 13, 24, 0) 70%);
            --nav-bg: rgba(12, 20, 36, 0.92);
            --nav-border: #1e3052;
            --pill-active-bg: #38bdf8;
            --pill-active-text: #070d18;
            --pill-inactive-bg: #14223d;
            --pill-inactive-text: #94a3b8;
            
            --card-shadow: 0 6px 24px -2px rgba(0, 0, 0, 0.5);
            --card-hover-shadow: 0 20px 40px -4px rgba(0, 0, 0, 0.7), 0 0 1px 1px rgba(96, 165, 250, 0.3);
            --code-bg: #050a12;
            --code-text: #e2e8f0;
            --toggle-bg: #14223d;
            --toggle-border: #1e3052;
            --toggle-icon: #e8eaed;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-body);
            line-height: 1.6;
            font-size: 1rem;
            overflow-x: hidden;
            transition: background-color 0.25s ease, color 0.25s ease;
        }

        h1, h2, h3, h4, .brand-font {
            font-family: 'Outfit', 'Inter', sans-serif;
            color: var(--text-heading);
            letter-spacing: -0.02em;
        }

        /* PayPal Wordmark Typography */
        .logo-wordmark-font {
            font-family: 'Ubuntu', 'Outfit', sans-serif;
            font-style: italic;
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        a {
            color: var(--paypal-bright-blue);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .container {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ----------------------------------------------------
           1. PayPal-Style Sticky Minimalist Header
           ---------------------------------------------------- */
        .header-nav {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--nav-bg);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--nav-border);
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }

        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 74px;
        }

        .nav-brand-group {
            display: flex;
            align-items: center;
            gap: 36px;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .nav-brand img {
            height: 38px;
            width: auto;
            object-fit: contain;
        }

        .rpay-logo-light { display: block; }
        .rpay-logo-dark { display: none; }
        [data-theme="dark"] .rpay-logo-light { display: none !important; }
        [data-theme="dark"] .rpay-logo-dark { display: block !important; }

        /* Segment Links: Personal, Business, Developer, Partners */
        .nav-segments {
            display: flex;
            align-items: center;
            gap: 22px;
            list-style: none;
        }

        .nav-segments a {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-heading);
            padding: 8px 12px;
            border-radius: 20px;
            transition: all 0.15s ease;
        }

        .nav-segments a:hover, .nav-segments a.active {
            color: var(--paypal-bright-blue);
            background: var(--bg-card-subtle);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* PayPal Pill Buttons */
        .btn-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 9999px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-pill-ghost {
            background: transparent;
            color: var(--text-heading);
            border: 1.5px solid var(--border-card);
        }

        .btn-pill-ghost:hover {
            border-color: var(--paypal-bright-blue);
            color: var(--paypal-bright-blue);
            background: var(--bg-card-subtle);
        }

        .btn-pill-primary {
            background: var(--paypal-bright-blue);
            color: #ffffff;
            border: 1.5px solid transparent;
            box-shadow: 0 4px 14px rgba(0, 112, 224, 0.25);
        }

        .btn-pill-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 112, 224, 0.38);
            filter: brightness(1.05);
        }

        .btn-pill-navy {
            background: var(--paypal-navy);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .btn-pill-navy:hover {
            background: #132448;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(10, 25, 56, 0.3);
        }

        .btn-theme-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background-color: var(--toggle-bg);
            border: 1px solid var(--toggle-border);
            color: var(--toggle-icon);
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 0;
            flex-shrink: 0;
        }

        .btn-theme-toggle:hover {
            transform: scale(1.08);
            border-color: var(--paypal-bright-blue);
        }

        :root:not([data-theme="dark"]) .theme-icon-sun { display: none !important; }
        :root:not([data-theme="dark"]) .theme-icon-moon { display: flex !important; }
        [data-theme="dark"] .theme-icon-sun { display: flex !important; color: #facc15; }
        [data-theme="dark"] .theme-icon-moon { display: none !important; }

        /* ----------------------------------------------------
           2. PayPal Open Hero Section
           ---------------------------------------------------- */
        .hero-section {
            background: var(--hero-bg-gradient);
            padding: 70px 0 60px;
            position: relative;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            align-items: center;
            gap: 50px;
        }

        .hero-left {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .hero-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 6px 16px 6px 10px;
            border-radius: 9999px;
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            color: var(--paypal-bright-blue);
            font-size: 0.84rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            width: fit-content;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }

        .badge-pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--accent-green);
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulseGlow 1.8s infinite;
        }

        @keyframes pulseGlow {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .hero-headline {
            font-size: clamp(2.8rem, 4.4vw, 4rem);
            font-weight: 800;
            line-height: 1.08;
            letter-spacing: -0.03em;
            color: var(--text-heading);
            margin: 0;
        }

        .gradient-span {
            background: linear-gradient(135deg, var(--paypal-deep-blue) 0%, var(--paypal-bright-blue) 50%, var(--paypal-cyan) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }

        [data-theme="dark"] .gradient-span {
            background: linear-gradient(135deg, #ffffff 0%, var(--paypal-bright-blue) 50%, var(--paypal-cyan) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subhead {
            font-size: 1.18rem;
            line-height: 1.68;
            color: var(--text-muted);
            max-width: 580px;
        }

        .hero-cta-group {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .hero-trust-note {
            display: flex;
            align-items: center;
            gap: 18px;
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-top: 10px;
        }

        .hero-trust-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ----------------------------------------------------
           Hero Right: Interactive Live Payment Terminal Mock
           ---------------------------------------------------- */
        .terminal-card-container {
            background: #0A1938;
            border-radius: 24px;
            padding: 30px;
            color: #ffffff;
            box-shadow: 0 25px 60px rgba(10, 25, 56, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.12);
            position: relative;
            overflow: hidden;
            animation: cardFloat 6s ease-in-out infinite alternate;
        }

        @keyframes cardFloat {
            0% { transform: translateY(0); }
            100% { transform: translateY(-8px); }
        }

        .terminal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 18px;
            margin-bottom: 20px;
        }

        .terminal-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .terminal-brand img {
            height: 28px;
            width: auto;
        }

        .terminal-status-chip {
            background: rgba(16, 185, 129, 0.16);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.35);
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.74rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .terminal-amount-box {
            text-align: center;
            padding: 16px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 16px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .terminal-amount-box .label {
            font-size: 0.82rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .terminal-amount-box .amount {
            font-size: 2.3rem;
            font-weight: 800;
            color: #ffffff;
            font-family: 'JetBrains Mono', monospace;
            margin: 4px 0;
        }

        /* Active Rails Switcher */
        .rail-track-box {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .rail-track-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: #93c5fd;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: flex;
            justify-content: space-between;
        }

        .rail-items-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .rail-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.88rem;
        }

        .rail-item.active-rail {
            background: rgba(0, 112, 224, 0.25);
            border-color: #60a5fa;
        }

        .rail-name {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        .rail-latency {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.78rem;
            color: #34d399;
        }

        /* ----------------------------------------------------
           3. PayPal India Flagship "Get Paid. Get Growing. Get Ahead."
           ---------------------------------------------------- */
        .section-padding {
            padding: 90px 0;
        }

        .section-header-center {
            text-align: center;
            max-width: 800px;
            margin: 0 auto 56px;
        }

        .section-kicker {
            font-size: 0.84rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--paypal-bright-blue);
            margin-bottom: 12px;
            display: inline-block;
        }

        .section-title {
            font-size: clamp(2.2rem, 3.2vw, 3rem);
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 16px;
        }

        .section-desc {
            font-size: 1.12rem;
            color: var(--text-muted);
            line-height: 1.7;
        }

        /* 3-Column Pillar Cards */
        .pillar-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .pillar-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 20px;
            padding: 38px 30px;
            box-shadow: var(--card-shadow);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .pillar-card:hover {
            transform: translateY(-6px);
            border-color: var(--paypal-bright-blue);
            box-shadow: var(--card-hover-shadow);
        }

        .pillar-icon-box {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            background: var(--bg-card-subtle);
            border: 1px solid var(--border-subtle);
            color: var(--paypal-bright-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }

        .pillar-card h3 {
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -0.01em;
        }

        .pillar-card p {
            font-size: 0.98rem;
            color: var(--text-muted);
            line-height: 1.68;
            margin-bottom: 20px;
            flex-grow: 1;
        }

        .pillar-link {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--paypal-bright-blue);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .pillar-link:hover {
            gap: 10px;
        }

        /* ----------------------------------------------------
           4. Interactive Solutions & Capabilities Tab Strip (PayPal Open Style)
           ---------------------------------------------------- */
        .tabs-nav-bar {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 24px;
            border-radius: 9999px;
            border: 1px solid var(--border-card);
            background: var(--bg-card);
            color: var(--text-body);
            font-size: 0.94rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .tab-btn:hover {
            border-color: var(--paypal-bright-blue);
            color: var(--paypal-bright-blue);
        }

        .tab-btn.active {
            background: var(--pill-active-bg);
            color: var(--pill-active-text);
            border-color: var(--pill-active-bg);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
        }

        .tab-panel {
            display: none;
            animation: fadeInTab 0.35s ease;
        }

        .tab-panel.active {
            display: block;
        }

        @keyframes fadeInTab {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .solution-card-split {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 24px;
            padding: 48px;
            box-shadow: var(--card-shadow);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            align-items: center;
        }

        .solution-bullets {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin: 24px 0;
        }

        .solution-bullet-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.98rem;
            color: var(--text-body);
        }

        .solution-check-badge {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.15);
            color: var(--accent-green);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .solution-visual-box {
            background: var(--bg-card-subtle);
            border: 1px solid var(--border-subtle);
            border-radius: 18px;
            padding: 30px;
        }

        /* ----------------------------------------------------
           5. Stats Banner (Uptime, Volume, SLA, Rails)
           ---------------------------------------------------- */
        .stats-strip-section {
            background: var(--bg-canvas);
            border-top: 1px solid var(--border-card);
            border-bottom: 1px solid var(--border-card);
            padding: 48px 0;
        }

        .stats-grid-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            text-align: center;
        }

        .stat-item h4 {
            font-size: 2.6rem;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }

        .stat-item p {
            font-size: 0.92rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* ----------------------------------------------------
           6. Developer API Code Playground (PayPal Developer section)
           ---------------------------------------------------- */
        .dev-section-grid {
            display: grid;
            grid-template-columns: 1fr 1.15fr;
            gap: 48px;
            align-items: center;
        }

        .code-box-wrapper {
            background: var(--code-bg);
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid var(--border-card);
            box-shadow: var(--card-shadow);
        }

        .code-box-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            background: rgba(255, 255, 255, 0.04);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .code-tabs {
            display: flex;
            gap: 8px;
        }

        .code-tab-btn {
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 0.84rem;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .code-tab-btn.active, .code-tab-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.1);
        }

        .code-snippet-pre {
            padding: 24px;
            color: var(--code-text);
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.88rem;
            line-height: 1.68;
            overflow-x: auto;
            margin: 0;
        }

        .code-token-verb { color: #f472b6; font-weight: 700; }
        .code-token-str { color: #86efac; }
        .code-token-num { color: #facc15; }
        .code-token-key { color: #93c5fd; }
        .code-token-comment { color: #64748b; }

        /* ----------------------------------------------------
           7. RLabz Company Spotlight
           ---------------------------------------------------- */
        .rlabz-banner {
            background: linear-gradient(135deg, rgba(0, 112, 224, 0.08) 0%, rgba(6, 182, 212, 0.08) 100%);
            border: 1px solid var(--border-card);
            border-radius: 24px;
            padding: 48px;
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 40px;
            align-items: center;
            box-shadow: var(--card-shadow);
        }

        [data-theme="dark"] .rlabz-banner {
            background: linear-gradient(135deg, rgba(96, 165, 250, 0.1) 0%, rgba(56, 189, 248, 0.05) 100%);
        }

        .rlabz-badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .rlabz-badge {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--text-heading);
        }

        /* ----------------------------------------------------
           8. PayPal Action Banner (Bottom Callout)
           ---------------------------------------------------- */
        .action-banner-section {
            padding: 60px 0 90px;
        }

        .action-banner-card {
            background: #0A1938;
            border-radius: 26px;
            padding: 60px 48px;
            text-align: center;
            color: #ffffff;
            box-shadow: 0 28px 60px rgba(10, 25, 56, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .action-banner-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: 50%;
            transform: translateX(-50%);
            width: 800px;
            height: 300px;
            background: radial-gradient(ellipse at center, rgba(0, 112, 224, 0.25) 0%, transparent 70%);
            pointer-events: none;
        }

        .action-banner-title {
            font-size: clamp(2.2rem, 3.5vw, 3rem);
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 16px;
        }

        .action-banner-sub {
            font-size: 1.15rem;
            color: #cbd5e1;
            max-width: 640px;
            margin: 0 auto 32px;
            line-height: 1.65;
        }

        .action-banner-buttons {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        /* ----------------------------------------------------
           9. PayPal-Style Structured Footer
           ---------------------------------------------------- */
        .footer-section {
            background: var(--bg-canvas);
            border-top: 1px solid var(--border-card);
            padding: 60px 0 32px;
        }

        .footer-top-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 48px;
        }

        .footer-brand p {
            font-size: 0.92rem;
            color: var(--text-muted);
            margin: 16px 0 20px;
            line-height: 1.65;
            max-width: 320px;
        }

        .footer-column h5 {
            font-size: 0.88rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-heading);
            margin-bottom: 18px;
        }

        .footer-column ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-column a {
            font-size: 0.9rem;
            color: var(--text-muted);
            transition: color 0.15s ease;
        }

        .footer-column a:hover {
            color: var(--paypal-bright-blue);
        }

        .footer-bottom-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid var(--border-subtle);
            padding-top: 28px;
            flex-wrap: wrap;
            gap: 16px;
            font-size: 0.88rem;
            color: var(--text-muted);
        }

        .footer-bottom-bar strong {
            color: var(--text-heading);
        }

        .country-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            background: var(--bg-card-subtle);
            border: 1px solid var(--border-subtle);
            color: var(--text-heading);
            font-weight: 600;
            font-size: 0.84rem;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .hero-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            .pillar-grid {
                grid-template-columns: 1fr;
            }
            .solution-card-split {
                grid-template-columns: 1fr;
            }
            .dev-section-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .footer-top-row {
                grid-template-columns: 1fr 1fr;
            }
            .nav-segments {
                display: none; /* Collapses for mobile */
            }
            .rlabz-banner {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .hero-headline {
                font-size: 2.4rem;
            }
            .stats-grid-row {
                grid-template-columns: 1fr;
            }
            .footer-top-row {
                grid-template-columns: 1fr;
            }
            .solution-card-split {
                padding: 28px 20px;
            }
            .action-banner-card {
                padding: 40px 24px;
            }
        }
    </style>
</head>
<body>

    <!-- 1. PayPal-Style Sticky Minimalist Navigation -->
    <header class="header-nav">
        <div class="container nav-inner">
            <div class="nav-brand-group">
                <a href="about.php" class="nav-brand">
                    <img src="assets/images/rpay-logo.png" alt="Rpay Logo" class="rpay-logo-light">
                    <img src="assets/images/rpay-logo-white-text.png" alt="Rpay Logo" class="rpay-logo-dark">
                </a>

                <!-- Segment Links -->
                <ul class="nav-segments">
                    <li><a href="#solutions" class="active">Platform</a></li>
                    <li><a href="#features">Capabilities</a></li>
                    <li><a href="#developers">Developers</a></li>
                    <li><a href="#rlabz">RLabz</a></li>
                </ul>
            </div>

            <div class="nav-actions">
                <!-- Theme Switcher -->
                <button type="button" id="themeToggleBtn" class="btn-theme-toggle" title="Switch Theme" aria-label="Toggle Theme">
                    <span class="theme-icon-sun">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                    </span>
                    <span class="theme-icon-moon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                    </span>
                </button>

                <!-- Auth / Portal CTAs -->
                <?php if ($isLoggedInAdmin): ?>
                    <a href="admin/dashboard.php" class="btn-pill btn-pill-primary">Admin Console &rarr;</a>
                <?php elseif ($isLoggedInMerchant): ?>
                    <a href="portal/dashboard.php" class="btn-pill btn-pill-primary">Merchant Portal &rarr;</a>
                <?php else: ?>
                    <a href="admin/login.php" class="btn-pill btn-pill-ghost">Admin Log In</a>
                    <a href="portal/login.php" class="btn-pill btn-pill-primary">Merchant Sign In</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- 2. Hero Section (PayPal Open Inspired) -->
    <section class="hero-section">
        <div class="container hero-grid">
            <div class="hero-left">
                <div class="hero-badge-pill">
                    <span class="badge-pulse-dot"></span>
                    <span>Rpay Open &bull; Unified Payment Platform</span>
                </div>

                <h1 class="hero-headline">
                    One platform for all your <span class="gradient-span">payment needs.</span>
                </h1>

                <p class="hero-subhead">
                    Get paid. Get growing. Get ahead. Rpay connects enterprise platforms, modern merchants, and developers with sub-second multi-rail routing, intelligent auto-failover, and zero-friction checkout.
                </p>

                <div class="hero-cta-group">
                    <a href="portal/login.php" class="btn-pill btn-pill-primary" style="padding: 13px 28px; font-size: 1rem;">
                        <span>Open Merchant Account &rarr;</span>
                    </a>
                    <a href="#developers" class="btn-pill btn-pill-ghost" style="padding: 13px 24px; font-size: 1rem;">
                        <span>Explore Developer APIs</span>
                    </a>
                </div>

                <div class="hero-trust-note">
                    <div class="hero-trust-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span>99.99% Uptime SLA</span>
                    </div>
                    <div class="hero-trust-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span>Sub-second Multi-Rail Routing</span>
                    </div>
                    <div class="hero-trust-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span>PCI-DSS Ready</span>
                    </div>
                </div>
            </div>

            <!-- Hero Right: Interactive Live Payment Terminal Mock -->
            <div class="hero-right">
                <div class="terminal-card-container">
                    <div class="terminal-header">
                        <div class="terminal-brand">
                            <img src="assets/images/rpay-logo-white-text.png" alt="Rpay Terminal">
                        </div>
                        <div class="terminal-status-chip">
                            <span class="badge-pulse-dot"></span>
                            <span>Smart Router Active</span>
                        </div>
                    </div>

                    <div class="terminal-amount-box">
                        <div class="label">Simulated Order Payment</div>
                        <div class="amount">₹ 4,999.00</div>
                        <div style="font-size: 0.8rem; color: #93c5fd;">ID: txn_vtx_984a0c88e</div>
                    </div>

                    <div class="rail-track-box">
                        <div class="rail-track-title">
                            <span>Dynamic Multi-Rail Status</span>
                            <span>Auto-Failover ON</span>
                        </div>
                        <div class="rail-items-list">
                            <div class="rail-item active-rail">
                                <div class="rail-name">
                                    <span>⚡ Rail 1: Razorpay Direct</span>
                                </div>
                                <div class="rail-latency">● 180ms [OPTIMAL]</div>
                            </div>
                            <div class="rail-item">
                                <div class="rail-name">
                                    <span>🔄 Rail 2: Cashfree Standby</span>
                                </div>
                                <div class="rail-latency" style="color: #94a3b8;">Ready (Failover)</div>
                            </div>
                            <div class="rail-item">
                                <div class="rail-name">
                                    <span>🌐 Rail 3: Stripe Global</span>
                                </div>
                                <div class="rail-latency" style="color: #94a3b8;">Cross-Border Ready</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. PayPal-Style "Get Paid. Get Growing. Get Ahead." Pillar Cards -->
    <section id="features" class="section-padding">
        <div class="container">
            <div class="section-header-center">
                <span class="section-kicker">Built to Power Your Business</span>
                <h2 class="section-title">One platform. Infinite payment possibilities.</h2>
                <p class="section-desc">
                    We connect enterprises, merchants, and developer platforms with streamlined solutions engineered for reliable, resilient, global transactions.
                </p>
            </div>

            <div class="pillar-grid">
                <!-- Pillar 1: Get Paid -->
                <div class="pillar-card">
                    <div class="pillar-icon-box">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    </div>
                    <h3>Get Paid</h3>
                    <p>
                        Offer customers fast, flexible payment methods. Accept UPI, credit/debit cards, netbanking, digital wallets, and cross-border currencies through a single, beautifully optimized checkout experience.
                    </p>
                    <a href="checkout.php" class="pillar-link">
                        <span>Preview Curvy Checkout</span> &rarr;
                    </a>
                </div>

                <!-- Pillar 2: Get Growing -->
                <div class="pillar-card">
                    <div class="pillar-icon-box">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                    </div>
                    <h3>Get Growing</h3>
                    <p>
                        Accelerate revenue across borders with intelligent multi-rail routing. When one gateway experiences latency or degradation, Rpay instantly shifts transactions without customer disruption.
                    </p>
                    <a href="portal/login.php" class="pillar-link">
                        <span>Merchant Growth Tools</span> &rarr;
                    </a>
                </div>

                <!-- Pillar 3: Get Ahead -->
                <div class="pillar-card">
                    <div class="pillar-icon-box">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    </div>
                    <h3>Get Ahead</h3>
                    <p>
                        Stay compliant and protected with tokenized card vaults, HMAC signature authentication, real-time transaction telemetry, instant ledger exports, and automated webhook retries.
                    </p>
                    <a href="#developers" class="pillar-link">
                        <span>Security Specifications</span> &rarr;
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. Interactive Capabilities Tab Strip (PayPal Open Style) -->
    <section id="solutions" class="section-padding" style="background: var(--bg-canvas);">
        <div class="container">
            <div class="section-header-center">
                <span class="section-kicker">End-to-End Orchestration</span>
                <h2 class="section-title">Designed for merchants. Built for developers.</h2>
                <p class="section-desc">
                    Choose your focus area to see how Rpay transforms checkout conversion, gateway resilience, and operational clarity.
                </p>
            </div>

            <!-- Tab Navigation -->
            <div class="tabs-nav-bar">
                <button type="button" class="tab-btn active" data-tab="tab-checkout">Optimized Checkout</button>
                <button type="button" class="tab-btn" data-tab="tab-routing">Smart Gateway Routing</button>
                <button type="button" class="tab-btn" data-tab="tab-security">Security & Token Vault</button>
                <button type="button" class="tab-btn" data-tab="tab-merchant">Merchant Command Center</button>
            </div>

            <!-- Tab 1: Optimized Checkout -->
            <div id="tab-checkout" class="tab-panel active">
                <div class="solution-card-split">
                    <div>
                        <span class="section-kicker">Checkout Experience</span>
                        <h3 style="font-size: 2.1rem; font-weight: 800; margin-bottom: 14px;">Curvy, frictionless, and high-converting.</h3>
                        <p style="color: var(--text-muted); line-height: 1.7;">
                            Modeled after modern high-end fintech interfaces, Rpay's checkout delivers a drop-in experience that feels native on any device:
                        </p>
                        <ul class="solution-bullets">
                            <li class="solution-bullet-item">
                                <span class="solution-check-badge">&#10003;</span>
                                <span>UPI Deep-links, QR codes, and intent-flow for Google Pay, PhonePe, and Paytm.</span>
                            </li>
                            <li class="solution-bullet-item">
                                <span class="solution-check-badge">&#10003;</span>
                                <span>International & domestic card processing with dynamic CVV verification.</span>
                            </li>
                            <li class="solution-bullet-item">
                                <span class="solution-check-badge">&#10003;</span>
                                <span>Zero redirect drop-offs — tokenized asynchronous payment confirmation.</span>
                            </li>
                        </ul>
                        <a href="checkout.php" class="btn-pill btn-pill-primary">Launch Live Checkout Demo &rarr;</a>
                    </div>
                    <div class="solution-visual-box">
                        <div style="background: #0A1938; border-radius: 16px; padding: 24px; color: #ffffff;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                <strong style="font-size: 1.1rem;">Checkout Flow Status</strong>
                                <span style="background: #10b981; color: #ffffff; padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">LIVE</span>
                            </div>
                            <p style="color: #94a3b8; font-size: 0.88rem; margin-bottom: 14px;">Instant customer session validation with HMAC-SHA256 token verification.</p>
                            <div style="background: rgba(255, 255, 255, 0.05); padding: 12px; border-radius: 8px; font-family: monospace; font-size: 0.8rem; color: #60a5fa;">
                                POST /api/v1/payments/create<br>
                                &rarr; 200 OK { token: "ca8372...", expires: 900 }
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Smart Routing -->
            <div id="tab-routing" class="tab-panel">
                <div class="solution-card-split">
                    <div>
                        <span class="section-kicker">Multi-Provider Engine</span>
                        <h3 style="font-size: 2.1rem; font-weight: 800; margin-bottom: 14px;">Intelligent failover with sub-second rerouting.</h3>
                        <p style="color: var(--text-muted); line-height: 1.7;">
                            Never lose a customer to upstream payment gateway downtime. Rpay monitors gateway latencies in real time and automatically diverts traffic:
                        </p>
                        <ul class="solution-bullets">
                            <li class="solution-bullet-item">
                                <span class="solution-check-badge">&#10003;</span>
                                <span>Unified abstraction layer across Razorpay, Cashfree, Stripe, and PayPal.</span>
                            </li>
                            <li class="solution-bullet-item">
                                <span class="solution-check-badge">&#10003;</span>
                                <span>Zero-downtime automated failover with automatic retry mechanisms.</span>
                            </li>
                            <li class="solution-bullet-item">
                                <span class="solution-check-badge">&#10003;</span>
                                <span>Configurable routing weights per merchant and transaction value.</span>
                            </li>
                        </ul>
                        <a href="admin/login.php" class="btn-pill btn-pill-navy">Inspect Admin Routing Console &rarr;</a>
                    </div>
                    <div class="solution-visual-box">
                        <div style="background: #0A1938; border-radius: 16px; padding: 24px; color: #ffffff;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                                <span style="font-weight: 700;">Dynamic Routing Telemetry</span>
                                <span style="color: #38bdf8; font-size: 0.82rem; font-family: monospace;">240ms mean</span>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: #cbd5e1;">
                                    <span>Primary: Razorpay</span>
                                    <span style="color: #34d399;">99.98% SUCCESS</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: #cbd5e1;">
                                    <span>Standby: Cashfree</span>
                                    <span style="color: #93c5fd;">HOT STANDBY</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: #cbd5e1;">
                                    <span>Global: Stripe</span>
                                    <span style="color: #93c5fd;">READY</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Security & Token Vault -->
            <div id="tab-security" class="tab-panel">
                <div class="solution-card-split">
                    <div>
                        <span class="section-kicker">Enterprise Security</span>
                        <h3 style="font-size: 2.1rem; font-weight: 800; margin-bottom: 14px;">Bank-grade encryption & PCI-DSS readiness.</h3>
                        <p style="color: var(--text-muted); line-height: 1.7;">
                            Engineered with zero-trust architectural isolation. Sensitive customer payment data is cryptographically protected at every stage:
                        </p>
                        <ul class="solution-bullets">
                            <li class="solution-bullet-item">
                                <span class="solution-check-badge">&#10003;</span>
                                <span>AES-256 encrypted token vault with ephemeral session tokens.</span>
                            </li>
                            <li class="solution-bullet-item">
                                <span class="solution-check-badge">&#10003;</span>
                                <span>HMAC-SHA256 signature verification on all merchant API and webhook payloads.</span>
                            </li>
                            <li class="solution-bullet-item">
                                <span class="solution-check-badge">&#10003;</span>
                                <span>Multi-tenant isolation — zero cross-merchant data leakage.</span>
                            </li>
                        </ul>
                        <a href="about.php" class="btn-pill btn-pill-primary">Read Architecture Specs &rarr;</a>
                    </div>
                    <div class="solution-visual-box">
                        <div style="background: #0A1938; border-radius: 16px; padding: 24px; color: #ffffff;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; color: #34d399;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                <strong>Cryptographic Isolation Level 1</strong>
                            </div>
                            <p style="font-size: 0.85rem; color: #94a3b8;">All webhook calls are cryptographically signed with merchant secrets to guarantee payload integrity.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Merchant Command Center -->
            <div id="tab-merchant" class="tab-panel">
                <div class="solution-card-split">
                    <div>
                        <span class="section-kicker">Merchant Control</span>
                        <h3 style="font-size: 2.1rem; font-weight: 800; margin-bottom: 14px;">Live feeds, settlements, and instant webhooks.</h3>
                        <p style="color: var(--text-muted); line-height: 1.7;">
                            Merchants get comprehensive operational power through the dedicated client portal:
                        </p>
                        <ul class="solution-bullets">
                            <li class="solution-bullet-item">
                                <span class="solution-check-badge">&#10003;</span>
                                <span>Live transaction telemetry with immediate status filters (Paid, Failed, Pending).</span>
                            </li>
                            <li class="solution-bullet-item">
                                <span class="solution-check-badge">&#10003;</span>
                                <span>One-click API key & secret generation with sandbox/live switching.</span>
                            </li>
                            <li class="solution-bullet-item">
                                <span class="solution-check-badge">&#10003;</span>
                                <span>Automated reconciliation ledger export for accounting teams.</span>
                            </li>
                        </ul>
                        <a href="portal/login.php" class="btn-pill btn-pill-primary">Sign In to Merchant Portal &rarr;</a>
                    </div>
                    <div class="solution-visual-box">
                        <div style="background: #0A1938; border-radius: 16px; padding: 24px; color: #ffffff;">
                            <div style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 8px;">SAMPLE MERCHANT RECORD</div>
                            <div style="font-size: 1.25rem; font-weight: 800; margin-bottom: 12px;">Merchant: Org Global Inc.</div>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 700;">Live Key Active</span>
                                <span style="background: rgba(96, 165, 250, 0.2); color: #93c5fd; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 700;">Webhooks Verified</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- 5. Global Metrics & SLA Strip -->
    <section class="stats-strip-section">
        <div class="container">
            <div class="stats-grid-row">
                <div class="stat-item">
                    <h4>99.99%</h4>
                    <p>High-Availability Uptime SLA</p>
                </div>
                <div class="stat-item">
                    <h4>&lt; 350ms</h4>
                    <p>Mean Multi-Rail Routing Latency</p>
                </div>
                <div class="stat-item">
                    <h4>4+ Rails</h4>
                    <p>Active Payment Gateways Supported</p>
                </div>
                <div class="stat-item">
                    <h4>256-bit</h4>
                    <p>Cryptographic Token Security</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. Developer Section & Interactive REST Code Playground -->
    <section id="developers" class="section-padding">
        <div class="container dev-section-grid">
            <div>
                <span class="section-kicker">Developer First</span>
                <h2 class="section-title">Clean REST APIs.<br>Instant drop-in SDKs.</h2>
                <p class="section-desc">
                    Integrating Rpay takes less than 10 lines of code. Generate tokens, dispatch payment requests, and receive authenticated webhook notifications seamlessly.
                </p>

                <div style="display: flex; flex-direction: column; gap: 14px; margin: 24px 0;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: var(--accent-green); font-weight: 700;">&bull;</span>
                        <span>Idempotent RESTful JSON endpoints with semantic status codes</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: var(--accent-green); font-weight: 700;">&bull;</span>
                        <span>Automated webhook retry policy with exponential backoff</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: var(--accent-green); font-weight: 700;">&bull;</span>
                        <span>Isolated sandbox environment for testing complete lifecycle</span>
                    </div>
                </div>

                <a href="portal/login.php" class="btn-pill btn-pill-primary">Get Your API Credentials &rarr;</a>
            </div>

            <!-- Code Playground Box -->
            <div class="code-box-wrapper">
                <div class="code-box-header">
                    <div class="code-tabs">
                        <button type="button" class="code-tab-btn active" data-lang="curl">cURL</button>
                        <button type="button" class="code-tab-btn" data-lang="php">PHP</button>
                        <button type="button" class="code-tab-btn" data-lang="node">Node.js</button>
                        <button type="button" class="code-tab-btn" data-lang="python">Python</button>
                    </div>
                    <span style="font-size: 0.76rem; color: #94a3b8; font-family: monospace;">POST /api/v1/payments/create</span>
                </div>

                <!-- cURL Snippet -->
                <pre id="snippet-curl" class="code-snippet-pre"><code><span class="code-token-verb">curl</span> -X POST https://api.rpay.com/v1/payments/create \
  -H <span class="code-token-str">"X-API-KEY: vtx_live_94ce1b3505..."</span> \
  -H <span class="code-token-str">"X-API-SECRET: vtx_sec_77e92a10..."</span> \
  -H <span class="code-token-str">"Content-Type: application/json"</span> \
  -d '{
    <span class="code-token-key">"amount"</span>: <span class="code-token-num">4999.00</span>,
    <span class="code-token-key">"currency"</span>: <span class="code-token-str">"INR"</span>,
    <span class="code-token-key">"order_id"</span>: <span class="code-token-str">"ORD_2026_9081"</span>,
    <span class="code-token-key">"customer_email"</span>: <span class="code-token-str">"alex@domain.com"</span>,
    <span class="code-token-key">"preferred_rail"</span>: <span class="code-token-str">"auto"</span>
  }'</code></pre>

                <!-- PHP Snippet -->
                <pre id="snippet-php" class="code-snippet-pre" style="display: none;"><code><span class="code-token-comment">// Initialize Rpay Gateway Client</span>
<span class="code-token-key">$rpay</span> = <span class="code-token-verb">new</span> Rpay\Client(<span class="code-token-str">'vtx_live_94ce1b3505...'</span>, <span class="code-token-str">'vtx_sec_77e92a10...'</span>);

<span class="code-token-key">$session</span> = <span class="code-token-key">$rpay</span>->payments->create([
    <span class="code-token-str">'amount'</span>         => <span class="code-token-num">4999.00</span>,
    <span class="code-token-str">'currency'</span>       => <span class="code-token-str">'INR'</span>,
    <span class="code-token-str">'order_id'</span>       => <span class="code-token-str">'ORD_2026_9081'</span>,
    <span class="code-token-str">'customer_email'</span> => <span class="code-token-str">'alex@domain.com'</span>,
    <span class="code-token-str">'redirect_url'</span>   => <span class="code-token-str">'https://merchant.com/callback'</span>
]);

<span class="code-token-comment">// Redirect customer to curvy checkout URL</span>
header(<span class="code-token-str">'Location: '</span> . <span class="code-token-key">$session</span>->checkout_url);</code></pre>

                <!-- Node Snippet -->
                <pre id="snippet-node" class="code-snippet-pre" style="display: none;"><code><span class="code-token-verb">const</span> { RpayClient } = require(<span class="code-token-str">'@rlabz/rpay-sdk'</span>);

<span class="code-token-verb">const</span> rpay = <span class="code-token-verb">new</span> RpayClient({
  apiKey: <span class="code-token-str">'vtx_live_94ce1b3505...'</span>,
  apiSecret: <span class="code-token-str">'vtx_sec_77e92a10...'</span>
});

<span class="code-token-verb">const</span> session = <span class="code-token-verb">await</span> rpay.payments.create({
  amount: <span class="code-token-num">4999.00</span>,
  currency: <span class="code-token-str">'INR'</span>,
  orderId: <span class="code-token-str">'ORD_2026_9081'</span>,
  customerEmail: <span class="code-token-str">'alex@domain.com'</span>
});

console.log(<span class="code-token-str">'Checkout Token:'</span>, session.token);</code></pre>

                <!-- Python Snippet -->
                <pre id="snippet-python" class="code-snippet-pre" style="display: none;"><code><span class="code-token-verb">from</span> rpay <span class="code-token-verb">import</span> RpayClient

client = RpayClient(api_key=<span class="code-token-str">'vtx_live_94ce1b3505...'</span>, api_secret=<span class="code-token-str">'vtx_sec_77e92a10...'</span>)

session = client.payments.create(
    amount=<span class="code-token-num">4999.00</span>,
    currency=<span class="code-token-str">'INR'</span>,
    order_id=<span class="code-token-str">'ORD_2026_9081'</span>,
    customer_email=<span class="code-token-str">'alex@domain.com'</span>
)

print(f<span class="code-token-str">"Checkout URL: {session.checkout_url}"</span>)</code></pre>
            </div>
        </div>
    </section>

    <!-- 7. RLabz Company Spotlight -->
    <section id="rlabz" class="section-padding" style="background: var(--bg-canvas);">
        <div class="container">
            <div class="rlabz-banner">
                <div>
                    <span class="section-kicker">Engineered with Precision</span>
                    <h3 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 14px;">Built by RLabz.</h3>
                    <p style="color: var(--text-body); line-height: 1.7; font-size: 1.05rem;">
                        RLabz is an advanced technology engineering firm specializing in high-concurrency transaction architectures, cloud-native enterprise software, and resilient fintech orchestration. Rpay was engineered from the ground up to solve multi-rail latency, drop-offs, and gateway downtime.
                    </p>
                    <div class="rlabz-badge-list">
                        <span class="rlabz-badge">⚡ Sub-Second Orchestration</span>
                        <span class="rlabz-badge">🛡️ Zero-Trust Security</span>
                        <span class="rlabz-badge">🌐 Global Multi-Rail</span>
                        <span class="rlabz-badge">📊 Deep Telemetry</span>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 14px; align-items: flex-start;">
                    <a href="https://rlabz.in" target="_blank" rel="noopener noreferrer" class="btn-pill btn-pill-primary" style="padding: 13px 28px;">
                        <span>Visit Official RLabz Website &rarr;</span>
                    </a>
                    <span style="font-size: 0.86rem; color: var(--text-muted);">Explore enterprise engineering solutions at rlabz.in</span>
                </div>
            </div>
        </div>
    </section>

    <!-- 8. PayPal-Style Bottom Action Banner -->
    <section class="action-banner-section">
        <div class="container">
            <div class="action-banner-card">
                <h2 class="action-banner-title">Ready to orchestrate your payments?</h2>
                <p class="action-banner-sub">
                    Join forward-thinking businesses and developer teams using Rpay for resilient multi-rail routing, higher authorization rates, and frictionless checkout.
                </p>
                <div class="action-banner-buttons">
                    <a href="portal/login.php" class="btn-pill btn-pill-primary" style="padding: 14px 30px; font-size: 1.05rem;">
                        <span>Merchant Portal Sign In &rarr;</span>
                    </a>
                    <a href="admin/login.php" class="btn-pill btn-pill-ghost" style="padding: 14px 28px; font-size: 1.05rem; color: #ffffff; border-color: rgba(255, 255, 255, 0.3);">
                        <span>Admin Console</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- 9. PayPal-Style Structured Footer -->
    <footer class="footer-section">
        <div class="container">
            <div class="footer-top-row">
                <div class="footer-brand">
                    <img src="assets/images/rpay-logo.png" alt="Rpay Logo" class="rpay-logo-light" style="height: 36px; width: auto;">
                    <img src="assets/images/rpay-logo-white-text.png" alt="Rpay Logo" class="rpay-logo-dark" style="height: 36px; width: auto;">
                    <p>
                        A unified payment gateway orchestration platform engineered by RLabz for high-throughput, fault-tolerant transaction processing.
                    </p>
                    <span class="country-pill">
                        <span>🇮🇳</span>
                        <span>India (EN)</span>
                    </span>
                </div>

                <div class="footer-column">
                    <h5>Platform</h5>
                    <ul>
                        <li><a href="checkout.php">Curvy Checkout</a></li>
                        <li><a href="#features">Smart Routing</a></li>
                        <li><a href="#features">Auto-Failover</a></li>
                        <li><a href="#solutions">Token Vault</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h5>Portals</h5>
                    <ul>
                        <li><a href="admin/login.php">Admin Console</a></li>
                        <li><a href="portal/login.php">Merchant Portal</a></li>
                        <li><a href="portal/login.php">API Key Manager</a></li>
                        <li><a href="about.php">Architecture Docs</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h5>Engineered By</h5>
                    <ul>
                        <li><a href="https://rlabz.in" target="_blank" rel="noopener noreferrer"><strong>RLabz Official</strong></a></li>
                        <li><a href="https://rlabz.in" target="_blank" rel="noopener noreferrer">Fintech Solutions</a></li>
                        <li><a href="https://rlabz.in" target="_blank" rel="noopener noreferrer">Cloud Architecture</a></li>
                        <li><a href="https://rlabz.in" target="_blank" rel="noopener noreferrer">Security Engineering</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom-bar">
                <div>
                    &copy; <?= date('Y') ?> <strong>Rpay</strong> (PayVortex Orchestration Engine). Engineered by <a href="https://rlabz.in" target="_blank" rel="noopener noreferrer"><strong>RLabz</strong></a>.
                </div>
                <div style="display: flex; gap: 20px;">
                    <a href="about.php">Privacy Policy</a>
                    <a href="about.php">Terms of Service</a>
                    <a href="about.php">PCI-DSS Status</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Interactive Scripts -->
    <script>
        // Tab Switcher for Solutions
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetTabId = this.dataset.tab;
                
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
                
                this.classList.add('active');
                const targetPanel = document.getElementById(targetTabId);
                if (targetPanel) {
                    targetPanel.classList.add('active');
                }
            });
        });

        // Code Snippet Switcher
        document.querySelectorAll('.code-tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const lang = this.dataset.lang;
                
                document.querySelectorAll('.code-tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.code-snippet-pre').forEach(p => p.style.display = 'none');
                
                this.classList.add('active');
                const snippet = document.getElementById('snippet-' + lang);
                if (snippet) {
                    snippet.style.display = 'block';
                }
            });
        });

        // Dark/Light Mode Theme Controller
        (function() {
            const toggleBtn = document.getElementById('themeToggleBtn');
            if (!toggleBtn) return;

            function updateToggleState(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                toggleBtn.setAttribute('title', theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode');
                toggleBtn.setAttribute('aria-label', theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode');
            }

            const currentTheme = document.documentElement.getAttribute('data-theme') || localStorage.getItem('vortex_theme') || 'light';
            updateToggleState(currentTheme);

            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const current = document.documentElement.getAttribute('data-theme') || 'light';
                const next = current === 'dark' ? 'light' : 'dark';

                localStorage.setItem('vortex_theme', next);
                document.cookie = "vortex_theme=" + next + "; path=/; max-age=31536000; SameSite=Lax";
                updateToggleState(next);
            });
        })();
    </script>
</body>
</html>
