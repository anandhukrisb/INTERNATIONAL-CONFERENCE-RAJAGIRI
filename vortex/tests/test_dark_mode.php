<?php
/**
 * ------------------------------------------------------------
 * test_dark_mode.php
 * ------------------------------------------------------------
 * Automated Test Suite for Google-Coloured Dark Mode & Theme Toggle
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';

if (empty($_ENV['TEST_BASE_URL']) && empty(getenv('TEST_BASE_URL'))) {
    $_ENV['TEST_BASE_URL'] = 'http://localhost:8080/vortex';
    putenv('TEST_BASE_URL=http://localhost:8080/vortex');
}

function run_dark_mode_tests()
{
    echo "\n=== Running Google-Coloured Dark Mode & Theme Toggle Tests ===\n";

    $vortexDir = dirname(__DIR__);
    $adminDir = $vortexDir . '/admin';
    $cssPath = $adminDir . '/assets/css/admin.css';
    $headerPath = $adminDir . '/includes/header.php';
    $footerPath = $adminDir . '/includes/footer.php';
    $profilePath = $adminDir . '/profile.php';
    $loginPath = $adminDir . '/login.php';

    // ------------------------------------------------------------
    // 1. Google Dark Mode CSS Design System & Palette Tokens
    // ------------------------------------------------------------
    echo "\n--- 1. Google Dark Mode CSS Tokens & Palette ---\n";

    TestHelper::assertTrue(file_exists($cssPath), "Dark Mode 1.1: admin.css stylesheet exists");
    $cssContent = file_get_contents($cssPath);

    // [data-theme="dark"] selector
    TestHelper::assertTrue(
        str_contains($cssContent, '[data-theme="dark"]'),
        "Dark Mode 1.2: [data-theme=\"dark\"] selector is defined in admin.css"
    );

    // Google Canvas & Surface colors
    TestHelper::assertTrue(
        str_contains($cssContent, '#202124'),
        "Dark Mode 1.3: Google Dark Canvas background (#202124) is defined"
    );
    TestHelper::assertTrue(
        str_contains($cssContent, '#292a2d'),
        "Dark Mode 1.4: Google Dark Surface (#292a2d) is defined for topbar/sidebar"
    );
    TestHelper::assertTrue(
        str_contains($cssContent, '#303134'),
        "Dark Mode 1.5: Google Dark Elevated Card/Modal surface (#303134) is defined"
    );
    TestHelper::assertTrue(
        str_contains($cssContent, '#3c4043'),
        "Dark Mode 1.6: Google Dark Secondary/Border tone (#3c4043) is defined"
    );

    // Google Typography High/Medium Emphasis
    TestHelper::assertTrue(
        str_contains($cssContent, '#e8eaed'),
        "Dark Mode 1.7: Google High-Emphasis White text (#e8eaed) is defined"
    );
    TestHelper::assertTrue(
        str_contains($cssContent, '#9aa0a6'),
        "Dark Mode 1.8: Google Medium-Emphasis Gray text (#9aa0a6) is defined"
    );

    // Google Brand Accent Colors
    TestHelper::assertTrue(
        str_contains($cssContent, '#8ab4f8'),
        "Dark Mode 1.9: Google Dark Blue accent (#8ab4f8) is defined"
    );
    TestHelper::assertTrue(
        str_contains($cssContent, '#81c995'),
        "Dark Mode 1.10: Google Dark Green accent (#81c995) is defined"
    );
    TestHelper::assertTrue(
        str_contains($cssContent, '#f28b82'),
        "Dark Mode 1.11: Google Dark Red accent (#f28b82) is defined"
    );
    TestHelper::assertTrue(
        str_contains($cssContent, '#fdd663'),
        "Dark Mode 1.12: Google Dark Yellow accent (#fdd663) is defined"
    );
    TestHelper::assertTrue(
        str_contains($cssContent, '#c58af9'),
        "Dark Mode 1.13: Google Dark Purple accent (#c58af9) is defined"
    );

    // System Media Query Fallback
    TestHelper::assertTrue(
        str_contains($cssContent, '@media (prefers-color-scheme: dark)'),
        "Dark Mode 1.14: prefers-color-scheme: dark media query fallback is configured"
    );

    // Toggle button styling
    TestHelper::assertTrue(
        str_contains($cssContent, '.btn-theme-toggle') &&
        str_contains($cssContent, '.theme-icon-sun') &&
        str_contains($cssContent, '.theme-icon-moon'),
        "Dark Mode 1.15: Theme toggle button classes and sun/moon icon rules are defined"
    );

    // Scrollbar and Component Dark Overrides
    TestHelper::assertTrue(
        str_contains($cssContent, 'scrollbar-color: #5f6368 #202124') ||
        str_contains($cssContent, 'scrollbar-color: #5f6368 #292a2d'),
        "Dark Mode 1.16: Native dark-themed scrollbars are styled"
    );

    // ------------------------------------------------------------
    // 2. Anti-FOUC & Topbar Toggle Architecture (header.php)
    // ------------------------------------------------------------
    echo "\n--- 2. Anti-FOUC & Topbar Header Controls ---\n";

    TestHelper::assertTrue(file_exists($headerPath), "Dark Mode 2.1: header.php exists");
    $headerContent = file_get_contents($headerPath);

    TestHelper::assertTrue(
        str_contains($headerContent, "localStorage.getItem('vortex_theme')") &&
        str_contains($headerContent, "document.documentElement.setAttribute('data-theme'"),
        "Dark Mode 2.2: Header includes immediate synchronous anti-FOUC script in <head>"
    );

    TestHelper::assertTrue(
        str_contains($headerContent, 'id="themeToggleBtn"') &&
        str_contains($headerContent, 'class="btn-theme-toggle"'),
        "Dark Mode 2.3: Header renders #themeToggleBtn in topbar actions"
    );

    TestHelper::assertTrue(
        str_contains($headerContent, 'class="theme-icon-sun"') &&
        str_contains($headerContent, 'class="theme-icon-moon"'),
        "Dark Mode 2.4: Header toggle button includes both sun and moon SVG icons"
    );

    // ------------------------------------------------------------
    // 3. Theme Controller Script (footer.php)
    // ------------------------------------------------------------
    echo "\n--- 3. Client-Side Theme Controller Script ---\n";

    TestHelper::assertTrue(file_exists($footerPath), "Dark Mode 3.1: footer.php exists");
    $footerContent = file_get_contents($footerPath);

    TestHelper::assertTrue(
        str_contains($footerContent, "document.getElementById('themeToggleBtn')"),
        "Dark Mode 3.2: Footer script attaches to themeToggleBtn"
    );

    TestHelper::assertTrue(
        str_contains($footerContent, "localStorage.setItem('vortex_theme', next)") &&
        str_contains($footerContent, "document.cookie = \"vortex_theme=\""),
        "Dark Mode 3.3: Theme state persists to both localStorage and cookie"
    );

    TestHelper::assertTrue(
        str_contains($footerContent, "vortexThemeChanged"),
        "Dark Mode 3.4: Custom event vortexThemeChanged is dispatched on toggle"
    );

    // ------------------------------------------------------------
    // 4. Admin Profile Appearance & Settings (profile.php)
    // ------------------------------------------------------------
    echo "\n--- 4. Admin Profile Appearance & Settings ---\n";

    TestHelper::assertTrue(file_exists($profilePath), "Dark Mode 4.1: profile.php exists");
    $profileContent = file_get_contents($profilePath);

    TestHelper::assertTrue(
        str_contains($profileContent, 'Appearance & Theme Settings') ||
        str_contains($profileContent, 'Appearance & Theme'),
        "Dark Mode 4.2: Profile settings includes Appearance & Theme section"
    );

    TestHelper::assertTrue(
        str_contains($profileContent, 'themeChoiceLight') &&
        str_contains($profileContent, 'themeChoiceDark') &&
        str_contains($profileContent, 'themeChoiceAuto'),
        "Dark Mode 4.3: Profile page provides Light, Google Dark, and System Auto options"
    );

    TestHelper::assertTrue(
        str_contains($profileContent, 'setAdminTheme(') &&
        str_contains($profileContent, 'syncThemeRadios('),
        "Dark Mode 4.4: Profile page has setAdminTheme and syncThemeRadios JS handlers"
    );

    // ------------------------------------------------------------
    // 5. Admin Login Page Dark Mode (login.php)
    // ------------------------------------------------------------
    echo "\n--- 5. Login Page Dark Mode & Floating Toggle ---\n";

    TestHelper::assertTrue(file_exists($loginPath), "Dark Mode 5.1: login.php exists");
    $loginContent = file_get_contents($loginPath);

    TestHelper::assertTrue(
        str_contains($loginContent, "localStorage.getItem('vortex_theme')"),
        "Dark Mode 5.2: Login page includes immediate anti-FOUC script in <head>"
    );

    TestHelper::assertTrue(
        str_contains($loginContent, 'id="themeToggleBtn"') &&
        str_contains($loginContent, 'login-theme-toggle'),
        "Dark Mode 5.3: Login page renders floating #themeToggleBtn"
    );

    TestHelper::assertTrue(
        str_contains($loginContent, '[data-theme="dark"]') &&
        str_contains($loginContent, '--bg-canvas: #202124;') &&
        str_contains($loginContent, '--bg-card: #303134;'),
        "Dark Mode 5.4: Login page defines Google Dark Mode CSS variables"
    );

    TestHelper::assertTrue(
        str_contains($loginContent, "updateToggleState") &&
        str_contains($loginContent, "localStorage.setItem('vortex_theme'"),
        "Dark Mode 5.5: Login page has theme toggle controller and state persistence"
    );

    // ------------------------------------------------------------
    // 6. Live HTTP Smoke Tests
    // ------------------------------------------------------------
    echo "\n--- 6. Live HTTP Smoke Tests ---\n";

    $loginRes = TestHelper::request('GET', '/admin/login.php');
    TestHelper::assertTrue($loginRes['http_code'] === 200, "Dark Mode 6.1: GET /admin/login.php returns HTTP 200");
    TestHelper::assertTrue(
        str_contains($loginRes['raw'] ?? '', 'id="themeToggleBtn"'),
        "Dark Mode 6.2: Live login page response includes #themeToggleBtn"
    );
    TestHelper::assertTrue(
        str_contains($loginRes['raw'] ?? '', 'vortex_theme'),
        "Dark Mode 6.3: Live login page response contains vortex_theme script"
    );

    // Dashboard redirects to login if unauthenticated
    $dashRes = TestHelper::request('GET', '/admin/dashboard.php');
    TestHelper::assertTrue(
        in_array($dashRes['http_code'], [302, 200]),
        "Dark Mode 6.4: GET /admin/dashboard.php responds correctly (redirects or renders)"
    );

    echo "\n✓ Google-Coloured Dark Mode & Theme Toggle verification complete!\n";
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_dark_mode_tests();
    $counts = TestHelper::getCounts();
    echo "\nTest Summary: " . $counts['passed'] . " passed, " . $counts['failed'] . " failed.\n";
    exit($counts['failed'] > 0 ? 1 : 0);
}
