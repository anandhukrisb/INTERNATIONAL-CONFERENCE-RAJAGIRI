<?php
/**
 * Vortex Admin – Login Page
 * Split Hero Layout: Brand Showcase (Left) + Curvy Navy Card (Right)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in
if (!empty($_SESSION['vortex_admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../classes/Database.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        try {
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, username, password_hash, full_name, email, role, avatar_color, is_active FROM admin WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && (int)$admin['is_active'] === 1 && password_verify($password, $admin['password_hash'])) {
                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);

                $_SESSION['vortex_admin_id']           = $admin['id'];
                $_SESSION['vortex_admin_username']      = $admin['username'];
                $_SESSION['vortex_admin_fullname']      = $admin['full_name'];
                $_SESSION['vortex_admin_email']         = $admin['email'] ?? '';
                $_SESSION['vortex_admin_role']          = $admin['role'] ?? 'Super Administrator';
                $_SESSION['vortex_admin_avatar_color']  = $admin['avatar_color'] ?? '#1a73e8';

                // Update last_login
                $db->prepare("UPDATE admin SET last_login = NOW() WHERE id = :id")->execute([':id' => $admin['id']]);

                header('Location: dashboard.php');
                exit;
            } else {
                sleep(1);
                $error = 'Invalid username or password.';
            }
        } catch (Throwable $e) {
            $error = 'A system error occurred. Please try again.';
        }
    }
}
$loggedOut = isset($_GET['logged_out']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rpay Admin – Login</title>
    <link rel="icon" type="image/png" href="assets/images/rpay-icon.png">
    
    <!-- Google Fonts: Outfit, Ubuntu (Logo Typography), and JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,700;1,800;1,900&family=Ubuntu:ital,wght@1,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
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
            --bg-page: #f1f5fb;
            --card-navy: #0A1938;
            --card-blue: #3165EC;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --accent-purple: #133989;
            --accent-cyan: #06b6d4;
            --accent-emerald: #10b981;
            --accent-rose: #f43f5e;
            
            --hero-heading: #0A1938;
            --hero-subtext: #475569;
            --hero-accent-gradient: linear-gradient(135deg, #1d4ed8 0%, #3165EC 50%, #06b6d4 100%);
            --pill-bg: rgba(255, 255, 255, 0.9);
            --pill-border: rgba(49, 101, 236, 0.25);
            --pill-text: #1e40af;
            --feature-text: #334155;
            --check-bg: rgba(49, 101, 236, 0.12);
            --check-color: #2563eb;
            --hero-btn-bg: #ffffff;
            --hero-btn-text: #0A1938;
            --hero-btn-border: #cbd5e1;
            --hero-btn-hover: #f8fafc;
            
            --input-bg: rgba(0, 0, 0, 0.22);
            --input-border: rgba(255, 255, 255, 0.14);
            --input-text: #ffffff;
            --input-placeholder: rgba(255, 255, 255, 0.45);
            --input-focus: #60a5fa;
            
            --btn-bg: #ffffff;
            --btn-text: #0A1938;
            --btn-hover: #f8fafc;
            
            --toggle-bg: #ffffff;
            --toggle-border: #cbd5e1;
            --toggle-icon: #5f6368;
            
            --bg-radial-1: rgba(200, 220, 255, 0.65);
            --bg-radial-2: rgba(220, 230, 255, 0.65);
        }

        [data-theme="dark"] {
            --bg-canvas: #202124;
            --bg-card: #303134;
            --bg-page: #090e1a;
            --card-navy: #0A1938;
            --card-blue: #8ab4f8;
            --text-dark: #e8eaed;
            --text-muted: #9aa0a6;
            --accent-purple: #133989;
            --accent-cyan: #38bdf8;
            --accent-emerald: #34d399;
            --accent-rose: #fb7185;
            
            --hero-heading: #ffffff;
            --hero-subtext: #94a3b8;
            --hero-accent-gradient: linear-gradient(135deg, #60a5fa 0%, #38bdf8 50%, #818cf8 100%);
            --pill-bg: rgba(49, 101, 236, 0.15);
            --pill-border: rgba(96, 165, 250, 0.3);
            --pill-text: #93c5fd;
            --feature-text: #cbd5e1;
            --check-bg: rgba(56, 189, 248, 0.15);
            --check-color: #38bdf8;
            --hero-btn-bg: #1e293b;
            --hero-btn-text: #ffffff;
            --hero-btn-border: #334155;
            --hero-btn-hover: #273549;
            
            --input-bg: rgba(0, 0, 0, 0.35);
            --input-border: rgba(255, 255, 255, 0.16);
            --input-text: #ffffff;
            --input-placeholder: rgba(255, 255, 255, 0.4);
            --input-focus: #8ab4f8;
            
            --btn-bg: #ffffff;
            --btn-text: #0A1938;
            --btn-hover: #f1f5f9;
            
            --toggle-bg: #1e293b;
            --toggle-border: #334155;
            --toggle-icon: #e8eaed;
            
            --bg-radial-1: rgba(49, 101, 236, 0.18);
            --bg-radial-2: rgba(19, 57, 137, 0.22);
        }

        body {
            background-color: var(--bg-page);
            background-image: 
                radial-gradient(circle at 10% 20%, var(--bg-radial-1) 0%, transparent 45%),
                radial-gradient(circle at 90% 80%, var(--bg-radial-2) 0%, transparent 45%);
            color: var(--text-dark);
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: background-color 0.25s ease;
            overflow-x: hidden;
        }

        /* Floating Theme Toggle in top-right corner */
        .login-theme-toggle {
            position: fixed;
            top: 24px;
            right: 28px;
            z-index: 100;
        }

        .btn-theme-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background-color: var(--toggle-bg);
            border: 1px solid var(--toggle-border);
            color: var(--toggle-icon);
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            padding: 0;
        }

        .btn-theme-toggle:hover {
            transform: scale(1.08);
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
        }

        :root:not([data-theme="dark"]) .theme-icon-sun { display: none !important; }
        :root:not([data-theme="dark"]) .theme-icon-moon { display: flex !important; align-items: center; justify-content: center; color: #5f6368; }

        [data-theme="dark"] .theme-icon-sun { display: flex !important; align-items: center; justify-content: center; color: #fdd663; }
        [data-theme="dark"] .theme-icon-moon { display: none !important; }

        /* Two-Column Split Container */
        .login-split-container {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            align-items: center;
            max-width: 1260px;
            width: 100%;
            margin: 0 auto;
            padding: 3rem 2.5rem;
            gap: 4.5rem;
            min-height: calc(100vh - 4rem);
            z-index: 10;
        }

        /* Left Side: Brand Showcase */
        .brand-showcase {
            display: flex;
            flex-direction: column;
            gap: 1.6rem;
            max-width: 600px;
            animation: fadeInHero 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes fadeInHero {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .brand-kicker-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 7px 16px 7px 10px;
            border-radius: 9999px;
            background: var(--pill-bg);
            border: 1px solid var(--pill-border);
            color: var(--pill-text);
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            width: fit-content;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(8px);
        }

        .brand-kicker-icon {
            width: 22px;
            height: 22px;
            object-fit: contain;
        }

        /* Primary Text in Logo Font */
        .brand-primary-text {
            font-family: 'Ubuntu', 'Outfit', sans-serif;
            font-style: italic;
            font-weight: 700;
            font-size: clamp(2.6rem, 4.2vw, 3.75rem);
            line-height: 1.12;
            letter-spacing: -0.03em;
            color: var(--hero-heading);
            margin: 0;
        }

        .gradient-accent {
            background: var(--hero-accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }

        /* Secondary Text */
        .brand-secondary-text {
            font-size: 1.14rem;
            line-height: 1.72;
            color: var(--hero-subtext);
            margin: 0;
            font-weight: 400;
        }

        /* Feature Checklist */
        .feature-checklist {
            display: flex;
            flex-direction: column;
            gap: 0.95rem;
            margin-top: 0.35rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.98rem;
            font-weight: 500;
            color: var(--feature-text);
        }

        .feature-check-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--check-bg);
            color: var(--check-color);
            flex-shrink: 0;
        }

        /* Brand CTA row */
        .brand-cta-row {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 0.6rem;
        }

        .btn-about-hero {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: 12px;
            background: var(--hero-btn-bg);
            color: var(--hero-btn-text);
            border: 1px solid var(--hero-btn-border);
            font-weight: 600;
            font-size: 0.92rem;
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .btn-about-hero:hover {
            background: var(--hero-btn-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .btn-rlabz-hero {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            border-radius: 12px;
            color: var(--hero-subtext);
            text-decoration: none;
            font-size: 0.92rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-rlabz-hero strong {
            color: var(--card-blue);
            font-weight: 800;
        }

        .btn-rlabz-hero:hover {
            color: var(--text-dark);
        }

        /* Right Side: Login Card Wrapper */
        .login-card-wrapper {
            display: flex;
            justify-content: flex-end;
            width: 100%;
        }

        /* Curvy Checkout-Style Card - Wider & Taller */
        .curvy-card {
            width: 100%;
            max-width: 510px;
            min-height: 560px;
            background-color: var(--card-navy);
            border-radius: 26px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 28px 60px rgba(10, 25, 56, 0.36);
            padding: 3.25rem 2.75rem 2.75rem 2.75rem;
            color: #ffffff;
            z-index: 10;
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: cardSpringIn 1.1s cubic-bezier(0.25, 1.3, 0.5, 1) forwards;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        @keyframes cardSpringIn {
            0% {
                transform: translateY(-50px) scale(0.97);
                opacity: 0;
            }
            100% {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        /* SVG Curvy Wave Background Layers */
        .card-bg-shape {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
            background-size: cover;
            background-position: center;
            transform: translateY(-100%);
            opacity: 0;
        }

        .shape-1 {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 500 800' preserveAspectRatio='none'%3E%3Cpath d='M500,0 L0,0 L0,300 C150,550 350,150 500,400 Z' fill='%230A1938' opacity='0.8'/%3E%3C/svg%3E");
            animation: slideDownShape 1.2s cubic-bezier(0.22, 1, 0.36, 1) 0.25s forwards;
        }

        .shape-2 {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 500 800' preserveAspectRatio='none'%3E%3Cpath d='M500,0 L0,0 L0,200 C200,400 300,50 500,250 Z' fill='%23133989' opacity='0.9'/%3E%3C/svg%3E");
            animation: slideDownShape 1.3s cubic-bezier(0.22, 1, 0.36, 1) 0.45s forwards;
        }

        @keyframes slideDownShape {
            0% {
                transform: translateY(-100%) scaleY(1.2);
                opacity: 0;
            }
            100% {
                transform: translateY(0) scaleY(1);
                opacity: 1;
            }
        }

        .card-content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
            height: 100%;
            justify-content: space-between;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-brand img {
            height: 40px;
            width: auto;
            object-fit: contain;
        }

        .admin-badge {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(6px);
            border-radius: 12px;
            padding: 0.35rem 0.8rem;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #93c5fd;
            border: 1px solid rgba(255, 255, 255, 0.14);
        }

        .headline-section {
            margin-top: 0.25rem;
        }

        .headline-title {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.01em;
            margin-bottom: 0;
        }

        /* Alerts */
        .alert-error {
            background: rgba(244, 63, 94, 0.15);
            border: 1px solid rgba(244, 63, 94, 0.35);
            color: #fca5a5;
            border-radius: 14px;
            padding: 12px 16px;
            font-size: 0.92rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.35);
            color: #86efac;
            border-radius: 14px;
            padding: 12px 16px;
            font-size: 0.92rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Glassmorphic Form Fields */
        .form-fields {
            display: flex;
            flex-direction: column;
            gap: 1.3rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #ffffff;
            letter-spacing: 0.01em;
        }

        .form-group input {
            padding: 15px 18px;
            font-size: 1.02rem;
            font-family: 'Outfit', sans-serif;
            border: 1px solid var(--input-border);
            border-radius: 14px;
            color: var(--input-text);
            background: var(--input-bg);
            backdrop-filter: blur(8px);
            transition: all 0.2s ease;
        }

        .form-group input::placeholder {
            color: var(--input-placeholder);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.28);
            background: rgba(0, 0, 0, 0.35);
        }

        /* Shiny Sweep Action Button */
        .btn-login {
            width: 100%;
            background: var(--btn-bg);
            color: var(--btn-text);
            border: none;
            border-radius: 14px;
            padding: 16px 20px;
            font-family: 'Outfit', sans-serif;
            font-size: 1.14rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.25s ease;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.22);
            margin-top: 0.6rem;
            position: relative;
            overflow: hidden;
        }

        .btn-login::after {
            content: '';
            position: absolute;
            top: 0;
            left: -150%;
            width: 80%;
            height: 100%;
            background: linear-gradient(120deg, transparent, rgba(49, 101, 236, 0.55), transparent);
            transform: skewX(-25deg);
            animation: shinySweep 2.2s ease-in-out 1s forwards;
            pointer-events: none;
        }

        @keyframes shinySweep {
            0% { left: -150%; }
            100% { left: 200%; }
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.3);
            background: var(--btn-hover);
        }

        /* Inside-Card Footer Note */
        .card-footer-note {
            text-align: center;
            font-size: 0.9rem;
            color: #94a3b8;
            margin-top: 0.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            user-select: none;
        }

        .card-footer-note a {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .card-footer-note a.rlabz-card-link strong {
            font-weight: 800;
            color: #60a5fa;
            letter-spacing: 0.3px;
            transition: color 0.15s ease;
        }

        .card-footer-note a.rlabz-card-link:hover strong {
            color: #93c5fd;
            text-decoration: underline;
        }

        .card-footer-note a.about-rpay-link {
            font-weight: 600;
            color: #cbd5e1;
        }

        .card-footer-note a.about-rpay-link:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        .card-footer-note .sub-sep {
            opacity: 0.45;
            font-size: 0.8rem;
        }

        @keyframes btn-spinner-anim {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .vortex-btn-spinner {
            display: inline-block;
            width: 1.1em;
            height: 1.1em;
            margin-right: 8px;
            vertical-align: middle;
            animation: btn-spinner-anim 1s linear infinite;
        }
        .btn-loading-state {
            opacity: 0.85;
            cursor: not-allowed !important;
            pointer-events: none;
        }

        /* Responsive Layout Breakpoints */
        @media (max-width: 1080px) {
            .login-split-container {
                grid-template-columns: 1fr;
                gap: 3.5rem;
                padding: 4.5rem 1.5rem 2.5rem 1.5rem;
                min-height: auto;
            }
            
            .brand-showcase {
                max-width: 620px;
                margin: 0 auto;
                text-align: center;
                align-items: center;
            }
            
            .brand-kicker-pill {
                margin: 0 auto;
            }
            
            .feature-checklist {
                align-items: flex-start;
                text-align: left;
                max-width: 500px;
                margin: 0 auto;
            }
            
            .brand-cta-row {
                justify-content: center;
            }
            
            .login-card-wrapper {
                justify-content: center;
            }
        }

        @media (max-width: 580px) {
            .curvy-card {
                padding: 2.5rem 1.75rem 2rem 1.75rem;
                border-radius: 22px;
                min-height: auto;
            }
            
            .brand-primary-text {
                font-size: 2.25rem;
            }

            .brand-cta-row {
                flex-direction: column;
                width: 100%;
            }

            .btn-about-hero {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <!-- Floating Theme Toggle in top-right corner -->
    <div class="login-theme-toggle">
        <button type="button" id="themeToggleBtn" class="btn-theme-toggle" title="Switch Theme" aria-label="Toggle Theme">
            <span class="theme-icon-sun">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
            </span>
            <span class="theme-icon-moon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </span>
        </button>
    </div>

    <!-- Two-Column Split Layout Container -->
    <div class="login-split-container">
        
        <!-- Left Side: App Brand & Capability Showcase -->
        <div class="brand-showcase">
            <div class="brand-kicker-pill">
                <img src="assets/images/rpay-icon.png" alt="Rpay Icon" class="brand-kicker-icon">
                <span>Rpay Administration Console</span>
            </div>

            <!-- Primary Headline matching logo font aesthetics -->
            <h1 class="brand-primary-text">
                Unified Payments.<br>
                <span class="gradient-accent">Simple &amp; Secure.</span>
            </h1>

            <!-- Secondary Descriptive Text -->
            <p class="brand-secondary-text">
                A secure, centralized platform to monitor transactions, verify payments, and manage institutional finances across Rajagiri.
            </p>

            <!-- Value Highlights -->
            <div class="feature-checklist">
                <div class="feature-item">
                    <div class="feature-check-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                    <span>Real-time transaction tracking &amp; verification</span>
                </div>
                <div class="feature-item">
                    <div class="feature-check-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                    <span>Secure role-based access &amp; automated audit logs</span>
                </div>
                <div class="feature-item">
                    <div class="feature-check-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                    <span>Instant financial reports &amp; settlement reconciliation</span>
                </div>
            </div>

            <!-- Showcase Action Row -->
            <div class="brand-cta-row">
                <a href="about.php" class="btn-about-hero">
                    <span>Learn More About Rpay &rarr;</span>
                </a>
                <a href="https://rlabz.in" target="_blank" rel="noopener noreferrer" class="btn-rlabz-hero">
                    <span>Engineered by <strong>RLabz</strong></span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                </a>
            </div>
        </div>

        <!-- Right Side: Login Card Wrapper -->
        <div class="login-card-wrapper">
            <!-- Curvy Card with Layered Waves -->
            <div class="curvy-card">
                <div class="card-bg-shape shape-1"></div>
                <div class="card-bg-shape shape-2"></div>

                <div class="card-content">
                    <div class="card-header">
                        <div class="card-brand">
                            <img src="assets/images/rpay-logo-white-text.png" alt="Rpay Logo">
                        </div>
                        <div class="admin-badge">Admin Console</div>
                    </div>

                    <div class="headline-section">
                        <h1 class="headline-title">Welcome</h1>
                    </div>

                    <?php if ($loggedOut): ?>
                        <div class="alert-success">✓ You have been signed out successfully.</div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="login.php" autocomplete="off" class="form-fields">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                   placeholder="Enter admin username"
                                   autofocus required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password"
                                   placeholder="Enter your password"
                                   required>
                        </div>

                        <button type="submit" class="btn-login">
                            <span>Sign In to Console &rarr;</span>
                        </button>
                    </form>

                    <div class="card-footer-note">
                        <span>Made with <a href="https://rlabz.in" target="_blank" rel="noopener noreferrer" class="rlabz-card-link"><strong>RLabz</strong></a></span>
                        <span class="sub-sep" aria-hidden="true">&bull;</span>
                        <a href="about.php" class="about-rpay-link">About Rpay</a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Form Loading Spinner
        document.addEventListener('submit', function(e) {
            if (e.target.tagName === 'FORM') {
                const form = e.target;
                const submitBtn = form.querySelector('button[type="submit"]:focus') || form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    if (submitBtn.dataset.submitting) return;
                    submitBtn.dataset.submitting = 'true';
                    submitBtn.classList.add('btn-loading-state');
                    
                    const spinnerSvg = `<svg class="vortex-btn-spinner" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-dasharray="80" stroke-dashoffset="60"></circle></svg>`;
                    submitBtn.innerHTML = `<span style="display: inline-flex; align-items: center; justify-content: center;">${spinnerSvg}<span>Signing in...</span></span>`;
                }
            }
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
