<?php
/**
 * about.php – Rpay public overview page
 * Structure: Hero · What is Rpay · Why Rpay · Key Capabilities · Contact · Meet the Team
 * Fonts: Ubuntu (primary / brand headings) + Outfit (body & UI)
 * Engineered by RLabz (https://rlabz.in)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedInAdmin    = !empty($_SESSION['vortex_admin_id']);
$isLoggedInMerchant = !empty($_SESSION['vortex_client_id']);

/* ------------------------------------------------------------
   EDIT HERE: contact form recipient + team details
   Each card shows one person. The photo is placed on the side
   opposite to the name/designation and overflows the card.
   ------------------------------------------------------------ */
$contactTo = 'rlabzresearch@rajagiri.edu';   // where "Contact Rlabz" messages are sent

$team = [
    [
        'role'        => 'System Architect',
        'ghost'       => 'Architect',
        'photo'       => 'assets/images/team/shiju.png',
        'photo_shape' => 'rounded-square',
        'name'        => 'Dr. Shiju Thomas M.Y',
        'title'       => 'Associate Professor, Dept. of Computer Science',
        'email'       => 'shijuthomas@rajagiri.edu',
        'link'        => 'https://rajagiri.edu/faculty-profile-details/dr-shiju-thomas-m-y',
        'link_label'  => 'Faculty Profile',
    ],
    [
        'role'        => 'Developer',
        'ghost'       => 'Developer',
        'photo'       => 'assets/images/team/anandhu.png',
        'photo_shape' => 'rounded-square',
        'name'        => 'Anandhu Krishna B',
        'title'       => '',
        'github'      => 'https://github.com/anandhukrisb',
        'linkedin'    => 'https://www.linkedin.com/in/anandhu-krishna-286b65354',
    ],
    [
        'role'        => 'Developer',
        'ghost'       => 'Developer',
        'photo'       => 'assets/images/team/febin.png',
        'photo_shape' => 'rounded-square',
        'name'        => 'Febin Sunny',
        'title'       => '',
        'github'      => 'https://github.com/febiins',
        'linkedin'    => 'https://www.linkedin.com/in/febinsunny27',
    ],
    [
        'role'        => 'Hosting Support',
        'ghost'       => 'Hosting',
        'photo'       => 'assets/images/team/lakshmi.png',
        'photo_shape' => 'rounded-square',
        'name'        => 'Lakshmi Kanth L R',
        'title'       => 'Senior Systems Analyst',
    ],
];

/* ------------------------------------------------------------
   Contact form handler
   Dispatches inquiries to $contactTo (rlabzresearch@rajagiri.edu)
   via PHPMailer (SMTP) with native mail() fallback.
   ------------------------------------------------------------ */
$formStatus = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $name    = trim(strip_tags($_POST['name'] ?? ''));
    $email   = trim($_POST['email'] ?? '');
    $org     = trim(strip_tags($_POST['org'] ?? ''));
    $message = trim(strip_tags($_POST['message'] ?? ''));
    $honey   = $_POST['website'] ?? '';   // honeypot: bots fill this

    if ($honey !== '') {
        $formStatus = 'ok'; // silently drop bots
    } elseif ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formStatus = 'invalid';
    } else {
        $subject = 'RLabz Contact Inquiry: ' . mb_substr($name, 0, 60);

        // Load environment credentials from vortex/.env or root .env
        $envVars = [];
        $loadEnvFile = function ($path) use (&$envVars) {
            if (file_exists($path) && is_readable($path)) {
                $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '#') === 0 || strpos($line, ';') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($k, $v) = explode('=', $line, 2);
                        $k = trim($k);
                        $v = trim(trim($v), "\"'\t\r\n");
                        if (!isset($envVars[$k]) || $envVars[$k] === '') {
                            $envVars[$k] = $v;
                        }
                    }
                }
            }
        };

        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
            if (class_exists('Dotenv\\Dotenv') && file_exists(__DIR__ . '/.env')) {
                try {
                    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
                    $dotenv->safeLoad();
                } catch (\Throwable $e) {}
            }
        }
        $loadEnvFile(__DIR__ . '/.env');
        $loadEnvFile(__DIR__ . '/../.env');

        $smtpHost = $_ENV['SMTP_HOST'] ?? $envVars['SMTP_HOST'] ?? 'smtp.gmail.com';
        $smtpPort = (int)($_ENV['SMTP_PORT'] ?? $envVars['SMTP_PORT'] ?? 587);
        $smtpUser = $_ENV['SMTP_USERNAME'] ?? $envVars['SMTP_USERNAME'] ?? $_ENV['SMTP_USER'] ?? $envVars['SMTP_USER'] ?? '';
        $smtpPass = $_ENV['SMTP_PASSWORD'] ?? $envVars['SMTP_PASSWORD'] ?? $_ENV['SMTP_PASS'] ?? $envVars['SMTP_PASS'] ?? '';
        $smtpFrom = $_ENV['SMTP_FROM_EMAIL'] ?? $envVars['SMTP_FROM_EMAIL'] ?? ($smtpUser ?: 'no-reply@rajagiri.edu');
        $fromName = $_ENV['SMTP_FROM_NAME'] ?? $envVars['SMTP_FROM_NAME'] ?? 'RLabz Contact Form';

        $safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeEmail   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeOrg     = !empty($org) ? htmlspecialchars($org, ENT_QUOTES, 'UTF-8') : 'Not specified';
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $dateTime    = date('d M Y, h:i A T');

        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>RLabz Contact Inquiry</title>
        </head>
        <body style='margin: 0; padding: 24px; background-color: #0b1528; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #1e293b;'>
            <table role='presentation' width='100%' cellspacing='0' cellpadding='0'>
                <tr>
                    <td align='center'>
                        <table role='presentation' width='100%' style='max-width: 600px; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.15);'>
                            <tr><td style='height: 5px; background: linear-gradient(90deg, #1a73e8 0%, #00d2ff 100%);'></td></tr>
                            <tr>
                                <td style='padding: 28px 32px 20px; border-bottom: 1px solid #f1f5f9; background: #fafbfc;'>
                                    <div style='font-size: 11px; font-weight: 800; color: #1a73e8; letter-spacing: 1px; text-transform: uppercase;'>RLabz &middot; Rpay Portal</div>
                                    <h1 style='font-size: 22px; font-weight: 800; margin: 6px 0 0; color: #0a1938;'>New Contact Inquiry</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 24px 32px 12px;'>
                                    <table width='100%' cellspacing='0' cellpadding='0' style='border-collapse: collapse;'>
                                        <tr>
                                            <td style='padding: 8px 0; font-size: 13px; color: #64748b; font-weight: 600; width: 30%;'>Sender Name</td>
                                            <td style='padding: 8px 0; font-size: 14px; color: #0a1938; font-weight: 700;'>{$safeName}</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 8px 0; font-size: 13px; color: #64748b; font-weight: 600;'>Email Address</td>
                                            <td style='padding: 8px 0; font-size: 14px; color: #1a73e8; font-weight: 600;'>
                                                <a href='mailto:{$safeEmail}' style='color: #1a73e8; text-decoration: none;'>{$safeEmail}</a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 8px 0; font-size: 13px; color: #64748b; font-weight: 600;'>Organization</td>
                                            <td style='padding: 8px 0; font-size: 14px; color: #0a1938;'>{$safeOrg}</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 8px 0; font-size: 13px; color: #64748b; font-weight: 600;'>Date &amp; Time</td>
                                            <td style='padding: 8px 0; font-size: 13px; color: #64748b;'>{$dateTime}</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 32px 28px;'>
                                    <div style='font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;'>Message</div>
                                    <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #1a73e8; border-radius: 8px; padding: 18px 20px; font-size: 14px; line-height: 1.6; color: #1e293b;'>{$safeMessage}</div>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 0 32px 28px;' align='center'>
                                    <a href='mailto:{$safeEmail}?subject=Re:%20RLabz%20Contact%20Inquiry' style='display: inline-block; background: #1a73e8; color: #ffffff; font-size: 13px; font-weight: 700; padding: 10px 22px; text-decoration: none; border-radius: 8px;'>
                                        Reply to {$safeName} &rarr;
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td style='background: #f8fafc; padding: 16px 32px; border-top: 1px solid #f1f5f9; font-size: 11px; color: #94a3b8; text-align: center;'>
                                    Inquiry received via Rpay contact form on Rajagiri College of Social Sciences portal.<br>
                                    Direct replies to this message will reach <strong>{$safeEmail}</strong>.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>";

        $plainBody = "RLabz Contact Inquiry\n"
                   . "=====================\n\n"
                   . "Name: $name\n"
                   . "Email: $email\n"
                   . "Organization: " . ($org ?: 'Not specified') . "\n"
                   . "Date: $dateTime\n\n"
                   . "Message:\n"
                   . "---------------------\n"
                   . "$message\n\n"
                   . "---------------------\n"
                   . "Reply directly to this email to contact $name ($email).\n";

        $sent = false;
        $phpMailerPath = __DIR__ . '/../backend/PHPMailer/src/';

        if (!empty($smtpUser) && !empty($smtpPass) && file_exists($phpMailerPath . 'PHPMailer.php')) {
            try {
                require_once $phpMailerPath . 'Exception.php';
                require_once $phpMailerPath . 'PHPMailer.php';
                require_once $phpMailerPath . 'SMTP.php';

                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $smtpHost;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtpUser;
                $mail->Password   = $smtpPass;
                $mail->SMTPSecure = ($smtpPort === 465)
                    ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $smtpPort;
                $mail->CharSet    = 'UTF-8';
                $mail->Timeout    = 10;

                $mail->setFrom($smtpFrom, $fromName);
                $mail->addReplyTo($email, $name);
                $mail->addAddress($contactTo, 'RLabz Research');

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $htmlBody;
                $mail->AltBody = $plainBody;

                $mail->send();
                $sent = true;
            } catch (\Throwable $e) {
                error_log("PHPMailer contact error: " . $e->getMessage());
            }
        }

        if (!$sent) {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . mb_encode_mimeheader($fromName, 'UTF-8') . " <" . ($smtpFrom ?: ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))) . ">\r\n";
            $headers .= "Reply-To: " . mb_encode_mimeheader($name, 'UTF-8') . " <" . str_replace(["\r", "\n"], '', $email) . ">\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

            $sent = @mail($contactTo, $subject, $htmlBody, $headers);
        }

        $formStatus = $sent ? 'ok' : 'error';
    }
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rpay | Unified Payment Platform</title>
    <meta name="description" content="Rpay is a comprehensive web-based payment management system built for Rajagiri College of Social Sciences (RCSS).">
    <link rel="icon" type="image/png" href="assets/images/rpay-icon.png">

    <!-- Google Fonts: Ubuntu (Primary Brand Typography), Outfit, and JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,600;1,700;1,800&family=Ubuntu:ital,wght@0,400;0,500;0,700;1,700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <script>
        (function () {
            var saved = localStorage.getItem('vortex_theme');
            var dark = saved === 'dark' || (!saved && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        })();
    </script>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root, [data-theme="light"] {
            --font-primary: 'Ubuntu', 'Outfit', sans-serif;
            --font-body: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --bg: #ffffff;
            --bg-soft: #f4f6fa;
            --card: #ffffff;
            --border: #e3e8ef;
            --heading: #0A1938;
            --body: #475569;
            --muted: #64748b;
            --primary: #1a73e8; /* Vortex Brand Blue */
            --primary-hover: #1557b0;
            --primary-light: #e8f0fe;
            --primary-glow: rgba(26, 115, 232, 0.18);
            --nav-bg: rgba(255, 255, 255, 0.92);
            --red: var(--primary);
            --red-dark: var(--primary-hover);
        }
        [data-theme="dark"] {
            --bg: #090e1a;
            --bg-soft: #0e1626;
            --card: #131d31;
            --border: #1e293b;
            --heading: #f8fafc;
            --body: #cbd5e1;
            --muted: #94a3b8;
            --primary: #38bdf8; /* Vortex Blue in Dark mode */
            --primary-hover: #60a5fa;
            --primary-light: rgba(56, 189, 248, 0.12);
            --primary-glow: rgba(56, 189, 248, 0.22);
            --nav-bg: rgba(9, 14, 26, 0.92);
            --red: var(--primary);
            --red-dark: var(--primary-hover);
        }

        html { scroll-behavior: smooth; scroll-padding-top: 80px; }
        body {
            font-family: var(--font-body, 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
            background: var(--bg);
            color: var(--body);
            line-height: 1.65;
            transition: background .2s, color .2s;
        }
        h1, h2, h3 { 
            font-family: var(--font-primary, 'Ubuntu', 'Outfit', sans-serif); 
            color: var(--heading); 
            letter-spacing: -0.02em; 
        }
        a { text-decoration: none; color: inherit; }
        .container { width: 80%; max-width: 1440px; margin: 0 auto; padding: 0 16px; }

        /* Nav */
        .nav { position: sticky; top: 0; z-index: 50; background: var(--nav-bg); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border); }
        .nav-inner { height: 68px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .nav-left { display: flex; align-items: center; gap: 32px; }
        .nav-logo { display: inline-flex; align-items: center; text-decoration: none; }
        .nav-logo img { height: 34px; width: auto; object-fit: contain; }
        .nav-logo .logo-light { display: block; }
        .nav-logo .logo-dark { display: none; }
        [data-theme="dark"] .nav-logo .logo-light { display: none !important; }
        [data-theme="dark"] .nav-logo .logo-dark { display: block !important; }
        .nav-links { display: flex; gap: 6px; list-style: none; }
        .nav-links a { font-size: .95rem; font-weight: 600; color: var(--heading); padding: 8px 16px; border-radius: 999px; transition: all .2s; }
        .nav-links a:hover { background: var(--primary-light); color: var(--primary); }
        .nav-right { display: flex; align-items: center; gap: 12px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 24px; border-radius: 999px; font-family: 'Outfit', sans-serif; font-size: .95rem; font-weight: 600; border: 1.5px solid transparent; cursor: pointer; transition: all .2s; white-space: nowrap; }
        .btn-primary, .btn-red { background: var(--primary); color: #fff; box-shadow: 0 4px 14px rgba(26,115,232,.25); }
        .btn-primary:hover, .btn-red:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 6px 18px rgba(26,115,232,.35); }
        .btn-ghost { border-color: var(--border); color: var(--heading); background: transparent; }
        .btn-ghost:hover { border-color: var(--primary); color: var(--primary); }
        .theme-btn { width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--border); background: var(--card); color: var(--heading); cursor: pointer; font-size: 1rem; display: inline-flex; align-items: center; justify-content: center; transition: all .2s; }
        .theme-btn:hover { border-color: var(--primary); color: var(--primary); transform: scale(1.05); }
        .theme-btn .icon-sun { display: none; }
        .theme-btn .icon-moon { display: flex; align-items: center; justify-content: center; }
        [data-theme="dark"] .theme-btn .icon-sun { display: flex; align-items: center; justify-content: center; }
        [data-theme="dark"] .theme-btn .icon-moon { display: none; }
        .mobile-toggle { display: none; width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--border); background: var(--card); color: var(--heading); cursor: pointer; align-items: center; justify-content: center; transition: all .2s; }
        .mobile-toggle:hover { border-color: var(--primary); color: var(--primary); }
        .nav-mobile-menu { display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--nav-bg); backdrop-filter: blur(16px); border-bottom: 1px solid var(--border); padding: 16px 24px 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .nav-mobile-menu.open { display: block; }
        .nav-mobile-links { display: flex; flex-direction: column; gap: 8px; list-style: none; margin-bottom: 16px; }
        .nav-mobile-links a { display: block; padding: 10px 14px; font-size: 1rem; font-weight: 600; color: var(--heading); border-radius: 10px; transition: background .2s, color .2s; }
        .nav-mobile-links a:hover { background: var(--primary-light); color: var(--primary); }
        .nav-mobile-actions { display: flex; flex-direction: column; gap: 10px; padding-top: 12px; border-top: 1px solid var(--border); }

        /* Shared */
        section { padding: 90px 0; }
        .kicker {
            display: inline-block;
            font-family: var(--font-primary, 'Ubuntu', 'Outfit', sans-serif);
            font-size: clamp(1.25rem, 2.2vw, 1.55rem);
            font-weight: 700;
            letter-spacing: .01em;
            color: var(--primary);
            margin-bottom: 14px;
            text-transform: none;
        }
        .section-title { 
            font-family: var(--font-primary, 'Ubuntu', 'Outfit', sans-serif); 
            font-size: clamp(2.1rem, 3.8vw, 3rem); 
            font-weight: 700; 
            line-height: 1.15; 
            margin-bottom: 14px; 
        }
        .section-desc { max-width: 860px; font-size: 1.1rem; color: var(--muted); }
        .prose { max-width: 960px; display: flex; flex-direction: column; gap: 18px; font-size: 1.1rem; color: var(--muted); }

        /* Hero */
        #hero { padding: 110px 0 90px; text-align: left; }
        #hero .tag { display: inline-block; font-size: .88rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--primary); margin-bottom: 18px; }
        #hero h1 { 
            font-family: var(--font-primary, 'Ubuntu', 'Outfit', sans-serif); 
            font-style: italic; 
            font-weight: 700; 
            font-size: clamp(2.8rem, 5.5vw, 4.8rem); 
            line-height: 1.08; 
            letter-spacing: -0.03em; 
            margin-bottom: 22px; 
            text-align: left;
        }
        #hero h1 span { color: var(--primary); }
        #hero p { max-width: 860px; font-size: 1.2rem; color: var(--muted); margin-bottom: 32px; text-align: left; }
        .cta-row { display: flex; gap: 14px; flex-wrap: wrap; justify-content: flex-start; }

        /* What is Rpay */
        #what-is-rpay { background: var(--bg-soft); text-align: left; }
        #what-is-rpay .kicker { margin: 0 0 12px; }
        #what-is-rpay .section-title { margin: 0 0 16px; text-align: left; }
        #what-is-rpay .prose { max-width: 960px; margin: 0; text-align: left; }

        /* Why Rpay */
        #why-rpay { text-align: left; }
        #why-rpay .kicker { margin: 0 0 12px; }
        #why-rpay .section-title { margin: 0 0 16px; text-align: left; }
        #why-rpay .prose { max-width: 960px; margin: 0 0 40px; text-align: left; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; width: 100%; margin-top: 40px; text-align: left; }
        .plain-card { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 34px 30px; transition: transform .2s, border-color .2s; }
        .plain-card:hover { transform: translateY(-2px); border-color: var(--primary); }
        .plain-card .num { font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.05rem; color: var(--primary); margin-bottom: 14px; }
        .plain-card h3 { font-size: 1.35rem; font-weight: 700; margin-bottom: 10px; }
        .plain-card p { font-size: 1rem; color: var(--muted); line-height: 1.6; }

        /* Capabilities */
        #key-capabilities { background: var(--bg-soft); text-align: left; }
        #key-capabilities .kicker { margin: 0 0 12px; }
        #key-capabilities .section-title { margin: 0 0 16px; text-align: left; }
        #key-capabilities .section-desc { max-width: 860px; margin: 0 0 40px; text-align: left; }
        .cap-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; width: 100%; margin-top: 40px; text-align: left; }
        .cap-item { display: flex; gap: 16px; align-items: stretch; padding: 24px 22px; border: 1px solid var(--border); border-radius: 18px; background: var(--card); transition: transform .2s, border-color .2s; }
        .cap-item:hover { transform: translateY(-2px); border-color: var(--primary); }
        .cap-bar { width: 4px; border-radius: 3px; background: var(--primary); flex-shrink: 0; }
        .cap-item h3 { font-size: 1.05rem; font-weight: 700; line-height: 1.35; }

        /* Contact */
        .contact-wrap { display: grid; grid-template-columns: 1fr 1.3fr; gap: 56px; align-items: start; }
        .contact-form { background: var(--card); border: 1px solid var(--border); border-radius: 22px; padding: 36px; display: flex; flex-direction: column; gap: 18px; }
        .contact-form label { font-size: .88rem; font-weight: 600; color: var(--heading); display: block; margin-bottom: 6px; }
        .contact-form input, .contact-form textarea {
            width: 100%; padding: 13px 16px; border-radius: 12px; border: 1px solid var(--border);
            background: var(--bg); color: var(--heading); font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: .98rem;
        }
        .contact-form input:focus, .contact-form textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
        .contact-form textarea { min-height: 130px; resize: vertical; }
        .hp { position: absolute; left: -9999px; }
        .notice { padding: 12px 16px; border-radius: 12px; font-size: .95rem; font-weight: 500; }
        .notice.ok { background: rgba(16,185,129,.12); color: #059669; }
        .notice.bad { background: rgba(244,63,94,.1); color: #f43f5e; }

        /* Team (Vortex deep blue / navy template) */
        #team {
            padding: 92px 0 0;
            background:
                radial-gradient(ellipse at 15% 0%, rgba(26,115,232,.55) 0%, rgba(26,115,232,0) 60%),
                radial-gradient(circle at 85% 25%, rgba(56,189,248,.18) 0%, transparent 45%),
                linear-gradient(180deg, #0A1938 0%, #0c1833 45%, #050a14 100%);
            color: #fff; overflow: hidden;
        }
        .team-head { text-align: left; margin-bottom: 110px; }
        .team-head h2 { 
            font-family: var(--font-primary, 'Ubuntu', 'Outfit', sans-serif); 
            color: #fff; 
            font-size: clamp(2.2rem, 4.8vw, 3.8rem); 
            font-weight: 700; 
            line-height: 1.15; 
        }
        .team-head h2 em { font-style: italic; color: #38bdf8; }
        .team-head p { margin-top: 14px; font-size: 1.18rem; color: rgba(255,255,255,.82); }

        /* Gap is large so each photo can rise above its card without touching the card above */
        .team-stack { display: flex; flex-direction: column; gap: 120px; width: 100%; max-width: 100%; margin: 0 auto; }

        .team-card {
            --photo-h: 340px;            /* photo is taller than the card */
            position: relative;
            background: linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%);
            border-radius: 22px;
            min-height: 230px;           /* card height < photo height, so the photo pops out the top */
            display: flex; align-items: center;
            padding: 42px 58px;
            box-shadow: 0 16px 40px rgba(0,0,0,.35);
        }
        /* Text on the left, photo on the right */
        .team-card.left  { justify-content: flex-start; text-align: left;  padding-right: 400px; }
        /* Text on the right, photo on the left */
        .team-card.right { justify-content: flex-end;   text-align: right; padding-left: 400px; }

        /* Watermark word lives in its own clipped layer so the photo can overflow the card */
        .ghost-clip { position: absolute; inset: 0; overflow: hidden; border-radius: 22px; pointer-events: none; }
        .ghost { 
            position: absolute; 
            top: 50%; left: 50%;
            transform: translate(-50%, -50%); 
            font-family: var(--font-primary, 'Ubuntu', 'Outfit', sans-serif); 
            font-weight: 700; 
            font-style: italic; 
            font-size: 10rem; 
            line-height: 1; 
            letter-spacing: -.04em; 
            color: rgba(10,25,56,.05); 
            white-space: nowrap; 
            user-select: none; 
        }

        /* Photo: bottom-aligned to the card, taller than the card, on the side opposite the text */
        .team-photo {
            position: absolute;
            bottom: 0;
            height: var(--photo-h);
            width: auto;
            max-width: 340px;
            object-fit: contain;
            object-position: center bottom;
            z-index: 2;
            filter: drop-shadow(0 12px 22px rgba(0,0,0,.28));
            pointer-events: none;
        }
        .team-card.left  .team-photo { right: 48px; }
        .team-card.right .team-photo { left: 48px; }

        /* Rounded square photo style (85% height of container) */
        .team-photo.photo-rounded-square {
            height: 85%;
            width: auto;
            max-width: none;
            aspect-ratio: 1 / 1;
            border-radius: 20px;
            object-fit: cover;
            object-position: center top;
            bottom: auto;
            top: 50%;
            transform: translateY(-50%);
            box-shadow: 0 10px 28px rgba(10, 25, 56, 0.16), 0 0 0 3.5px #ffffff;
            background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
            filter: none;
            transition: transform .25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow .25s ease;
        }
        .team-card.left  .team-photo.photo-rounded-square { right: 58px; }
        .team-card.right .team-photo.photo-rounded-square { left: 58px; }

        .team-card:hover .team-photo.photo-rounded-square {
            transform: translateY(calc(-50% - 3px));
            box-shadow: 0 16px 36px rgba(10, 25, 56, 0.24), 0 0 0 3.5px var(--primary);
        }

        .team-text { position: relative; z-index: 1; display: flex; gap: 20px; align-items: stretch; max-width: 640px; }
        .team-card.right .team-text { flex-direction: row-reverse; }
        .team-bar { width: 4px; background: var(--primary); flex-shrink: 0; border-radius: 2px; }
        .team-text h3 { color: #0A1938; font-size: 1.85rem; font-weight: 700; line-height: 1.25; margin-bottom: 12px; }
        .person .pname { font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: #0A1938; }
        .person .ptitle { font-size: .98rem; font-weight: 500; color: #475569; }
        .person .pmeta { margin-top: 4px; font-size: .92rem; }
        .person .pmeta a { color: #1a73e8; font-weight: 600; }
        .person .pmeta a:hover { text-decoration: underline; }
        .person .sep { color: #94a3b8; margin: 0 6px; }

        .social-links {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 14px;
        }
        .team-card.left .social-links { justify-content: flex-start; }
        .team-card.right .social-links { justify-content: flex-end; }
        .social-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #ffffff;
            color: #475569;
            border: 1px solid rgba(10, 25, 56, 0.12);
            box-shadow: 0 2px 6px rgba(10, 25, 56, 0.06);
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
        }
        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(10, 25, 56, 0.14);
        }
        .social-btn.github:hover {
            background: #24292e;
            color: #ffffff;
            border-color: #24292e;
        }
        .social-btn.linkedin:hover {
            background: #0077b5;
            color: #ffffff;
            border-color: #0077b5;
        }
        .social-btn svg {
            display: block;
            width: 18px;
            height: 18px;
            fill: currentColor;
            transition: transform 0.2s ease;
        }
        .social-btn:hover svg {
            transform: scale(1.1);
        }

        .curve-band { margin-top: 68px; background: #ffffff; border-top: 7px solid var(--primary); border-radius: 50% 50% 0 0 / 80px 80px 0 0; padding: 60px 24px 64px; text-align: center; }
        .curve-band .line { 
            font-family: var(--font-primary, 'Ubuntu', 'Outfit', sans-serif); 
            font-size: 1.35rem; 
            font-weight: 700; 
            color: #0A1938; 
            line-height: 1.6; 
        }
        .pill { display: inline-flex; align-items: center; margin-top: 28px; background: #0A1938; color: #fff; border-radius: 999px; overflow: hidden; font-family: 'Outfit', sans-serif; font-weight: 600; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .pill .p1 { padding: 14px 24px; }
        .pill .p2 { padding: 14px 24px; background: #fff; color: #0A1938; margin: 4px 0; border-radius: 999px; }
        .pill .p3 { padding: 14px 28px 14px 24px; color: #38bdf8; }

        [data-theme="dark"] .team-card { background: linear-gradient(180deg, #131d31 0%, #0e1626 100%); border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 16px 40px rgba(0,0,0,.5); }
        [data-theme="dark"] .team-photo.photo-rounded-square {
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.55), 0 0 0 3.5px rgba(255, 255, 255, 0.12);
        }
        [data-theme="dark"] .team-card:hover .team-photo.photo-rounded-square {
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.7), 0 0 0 3.5px var(--primary);
        }
        [data-theme="dark"] .team-text h3 { color: #f8fafc; }
        [data-theme="dark"] .person .pname { color: #f8fafc; }
        [data-theme="dark"] .person .ptitle { color: #94a3b8; }
        [data-theme="dark"] .person .pmeta a { color: #38bdf8; }
        [data-theme="dark"] .social-btn {
            background: #1a2536;
            color: #cbd5e1;
            border-color: rgba(255, 255, 255, 0.12);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
        }
        [data-theme="dark"] .social-btn.github:hover {
            background: #ffffff;
            color: #0f172a;
            border-color: #ffffff;
        }
        [data-theme="dark"] .social-btn.linkedin:hover {
            background: #0077b5;
            color: #ffffff;
            border-color: #0077b5;
        }
        [data-theme="dark"] .ghost { color: rgba(255,255,255,0.03); }
        [data-theme="dark"] .curve-band { background: #090e1a; border-top: 7px solid var(--primary); }
        [data-theme="dark"] .curve-band .line { color: #f8fafc; }
        [data-theme="dark"] .pill { background: #131d31; border-color: rgba(255,255,255,0.18); }
        [data-theme="dark"] .pill .p2 { background: #1e293b; color: #f8fafc; }

        footer { background: #050a14; color: rgba(255,255,255,.65); padding: 28px 0; font-size: .9rem; }
        footer .container { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        footer a { color: #fff; font-weight: 600; }

        @media (max-width: 1100px) {
            .team-card { --photo-h: 310px; }
            .team-card.left  { padding-right: 330px; }
            .team-card.right { padding-left: 330px; }
            .team-photo { max-width: 290px; }
            .team-card.left  .team-photo.photo-rounded-square { right: 38px; }
            .team-card.right .team-photo.photo-rounded-square { left: 38px; }
        }

        @media (max-width: 960px) {
            .container { width: 90%; padding: 0 12px; }
            .nav-links { display: none; }
            .mobile-toggle { display: inline-flex; }
            .grid-3 { grid-template-columns: 1fr; }
            .cap-grid { grid-template-columns: repeat(2, 1fr); }
            .contact-wrap { grid-template-columns: 1fr; gap: 32px; }

            /* Mobile: photo sits on top of the card and rises above it, text below */
            .team-stack { gap: 100px; }
            .team-card, .team-card.left, .team-card.right {
                --photo-h: 250px;
                flex-direction: column;
                align-items: flex-start;
                justify-content: flex-start;
                text-align: left;
                min-height: 0;
                padding: 0 26px 30px;
            }
            .team-photo, .team-card.left .team-photo, .team-card.right .team-photo {
                position: relative;
                left: auto; right: auto; bottom: auto;
                margin-top: -90px;
                margin-bottom: 18px;
                align-self: center;
                max-width: 80%;
                height: var(--photo-h);
            }
            .team-photo.photo-rounded-square,
            .team-card.left .team-photo.photo-rounded-square,
            .team-card.right .team-photo.photo-rounded-square {
                position: relative;
                top: auto;
                left: auto;
                right: auto;
                bottom: auto;
                transform: none;
                height: 160px;
                width: 160px;
                aspect-ratio: 1 / 1;
                margin-top: -60px;
                margin-bottom: 16px;
                border-radius: 18px;
                align-self: center;
            }
            .team-card:hover .team-photo.photo-rounded-square {
                transform: translateY(-3px);
            }
            .team-card.right .team-text { flex-direction: row; }
            .team-card.right .social-links { justify-content: flex-start; }
            .team-text h3 { font-size: 1.55rem; }
            .ghost { font-size: 6.5rem; }
            .pill { flex-direction: column; border-radius: 28px; }
            .pill .p2 { margin: 0; }
        }
        @media (max-width: 640px) {
            .container { width: 92%; padding: 0 8px; }
            .nav-inner { height: 60px; }
            .nav-right .btn { padding: 8px 16px; font-size: 0.88rem; }
            .nav-right .btn-ghost { display: none; }
        }
        @media (max-width: 520px) {
            .container { width: 94%; }
            .cap-grid { grid-template-columns: 1fr; }
        }    </style>
</head>
<body>

<!-- NAV -->
<header class="nav">
    <div class="container nav-inner">
        <div class="nav-left">
            <a href="about.php" class="nav-logo" aria-label="Rpay Homepage">
                <img src="assets/images/rpay-logo.png" alt="Rpay Logo" class="logo-light">
                <img src="assets/images/rpay-logo-white-text.png" alt="Rpay Logo" class="logo-dark">
            </a>
            <ul class="nav-links">
                <li><a href="#what-is-rpay">What is Rpay</a></li>
                <li><a href="#why-rpay">Why Rpay</a></li>
                <li><a href="#key-capabilities">Key Capabilities</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="#team">Team</a></li>
            </ul>
        </div>
        <div class="nav-right">
            <button type="button" id="themeToggleBtn" class="theme-btn" aria-label="Toggle theme" title="Toggle theme">
                <span class="icon-moon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                </span>
                <span class="icon-sun" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                </span>
            </button>
            <?php if ($isLoggedInAdmin): ?>
                <a href="admin/dashboard.php" class="btn btn-primary">Admin Console</a>
            <?php elseif ($isLoggedInMerchant): ?>
                <a href="portal/dashboard.php" class="btn btn-primary">Merchant Portal</a>
            <?php else: ?>
                <a href="admin/login.php" class="btn btn-ghost">Admin Log In</a>
                <a href="portal/login.php" class="btn btn-primary">Merchant Sign In</a>
            <?php endif; ?>
            <button type="button" id="mobileMenuBtn" class="mobile-toggle" aria-label="Toggle navigation menu" aria-expanded="false" title="Menu">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
        </div>
    </div>
    <div id="mobileNavMenu" class="nav-mobile-menu">
        <ul class="nav-mobile-links">
            <li><a href="#what-is-rpay">What is Rpay</a></li>
            <li><a href="#why-rpay">Why Rpay</a></li>
            <li><a href="#key-capabilities">Key Capabilities</a></li>
            <li><a href="#contact">Contact</a></li>
            <li><a href="#team">Team</a></li>
        </ul>
        <div class="nav-mobile-actions">
            <?php if ($isLoggedInAdmin): ?>
                <a href="admin/dashboard.php" class="btn btn-primary">Admin Console</a>
            <?php elseif ($isLoggedInMerchant): ?>
                <a href="portal/dashboard.php" class="btn btn-primary">Merchant Portal</a>
            <?php else: ?>
                <a href="admin/login.php" class="btn btn-ghost">Admin Log In</a>
                <a href="portal/login.php" class="btn btn-primary">Merchant Sign In</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- HERO -->
<section id="hero">
    <div class="container">
        <span class="tag">Rpay &middot; Unified Payment Platform</span>
        <h1>Powering Smarter<br><span>Payment Management</span></h1>
        <p>
            Rpay is a secure, role-based digital platform that streamlines the complete lifecycle of
            online payments &mdash; from checkout and transaction verification to settlement reports.
        </p>
        <div class="cta-row">
            <?php if ($isLoggedInAdmin): ?>
                <a href="admin/dashboard.php" class="btn btn-primary">Admin Console</a>
            <?php elseif ($isLoggedInMerchant): ?>
                <a href="portal/dashboard.php" class="btn btn-primary">Merchant Portal</a>
            <?php else: ?>
                <a href="portal/login.php" class="btn btn-primary">Merchant Sign In</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- WHAT IS RPAY -->
<section id="what-is-rpay">
    <div class="container">
        <span class="kicker">What is Rpay?</span>
        <h2 class="section-title">One platform for every payment.</h2>
        <div class="prose">
            <p>
                Rpay &mdash; Rajagiri Payment Platform &mdash; is a comprehensive web-based payment management system built for Rajagiri College of Social Sciences (RCSS). It automates and digitises the end-to-end payment collection process, replacing manual workflows with a transparent, auditable, and efficient platform.
            </p>
            <p>
                The system brings every phase of payment administration under one secure roof: from generating checkout sessions and tracking department events, to verifying transaction signatures, managing refunds, and producing official settlement reports &mdash; all through a role-appropriate dashboard.
            </p>
        </div>
    </div>
</section>

<!-- WHY RPAY -->
<section id="why-rpay">
    <div class="container">
        <span class="kicker">Why Rpay?</span>
        <div class="prose">
            <p>
                Traditional institutional payment processes rely heavily on manual ledger entries, fragmented receipt tracking, and delayed reconciliation &mdash; creating opportunities for delays, errors, and a lack of visibility. Rpay was conceived to address these pain points directly.
            </p>
            <p>
                By bringing the entire payment and settlement workflow online, Rpay reduces operational delays, minimises manual intervention, and provides real-time visibility to authorised stakeholders &mdash; while maintaining the robust security and audit integrity that financial transactions demand.
            </p>
        </div>

        <div class="grid-3">
            <div class="plain-card">
                <div class="num">01</div>
                <h3>Instant Verification</h3>
                <p>Cryptographic signature verification ensures transactions are authenticated and confirmed in real time.</p>
            </div>
            <div class="plain-card">
                <div class="num">02</div>
                <h3>Unified Checkout</h3>
                <p>Campus applications connect via secure API credentials and standard checkout sessions with zero manual friction.</p>
            </div>
            <div class="plain-card">
                <div class="num">03</div>
                <h3>Secure and auditable</h3>
                <p>Signed requests, role-based access, and a clear record of every transaction.</p>
            </div>
        </div>
    </div>
</section>

<!-- KEY CAPABILITIES -->
<section id="key-capabilities">
    <div class="container">
        <span class="kicker">Key Capabilities</span>
        <h2 class="section-title">The complete payment lifecycle.</h2>
        <p class="section-desc">The platform covers it through focused functional modules:</p>

        <div class="cap-grid">
            <?php
            $caps = [
                'Dynamic Checkout Sessions',
                'Cryptographic Verification',
                'Unified Checkout',
                'Merchant Portal',
                'API Keys & Webhooks',
                'Role-Based Access Control',
                'Secure Data Handling',
                'Reports & Reconciliation',
            ];
            foreach ($caps as $c): ?>
                <div class="cap-item"><span class="cap-bar"></span><h3><?= e($c) ?></h3></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CONTACT -->
<section id="contact">
    <div class="container contact-wrap">
        <div>
            <span class="kicker">Get in touch</span>
            <h2 class="section-title">Contact RLabz</h2>
            <p class="section-desc">
                Have a question, want to integrate your application, or need support? Send us a message.
            </p>
        </div>

        <form class="contact-form" method="post" action="about.php#contact">
            <input type="hidden" name="contact_submit" value="1">
            <?php if ($formStatus === 'ok'): ?>
                <div class="notice ok">Thanks! Your message has been sent to RLabz.</div>
            <?php elseif ($formStatus === 'invalid'): ?>
                <div class="notice bad">Please fill in your name, a valid email, and a message.</div>
            <?php elseif ($formStatus === 'error'): ?>
                <div class="notice bad">Sorry, the message could not be sent. Please try again later.</div>
            <?php endif; ?>

            <div>
                <label for="name">Your Name</label>
                <input type="text" id="name" name="name" required maxlength="100" value="<?= $formStatus === 'invalid' ? e($_POST['name'] ?? '') : '' ?>" placeholder="e.g. Febin Sunny">
            </div>
            <div>
                <label for="email">Your Email</label>
                <input type="email" id="email" name="email" required maxlength="150" value="<?= $formStatus === 'invalid' ? e($_POST['email'] ?? '') : '' ?>" placeholder="e.g. anandhukrishna@rajagiri.edu">
            </div>
            <div>
                <label for="org">Company / Organization (Optional)</label>
                <input type="text" id="org" name="org" maxlength="150" value="<?= $formStatus === 'invalid' ? e($_POST['org'] ?? '') : '' ?>" placeholder="e.g. Rajagiri College">
            </div>
            <div>
                <label for="message">Message</label>
                <textarea id="message" name="message" required maxlength="3000" placeholder="Type your message here..."><?= $formStatus === 'invalid' ? e($_POST['message'] ?? '') : '' ?></textarea>
            </div>
            <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            <button type="submit" class="btn btn-primary">Send Message</button>
        </form>
    </div>
</section>

<!-- MEET THE TEAM -->
<section id="team">
    <div class="container">
        <div class="team-head">
            <h2>Meet the <em>Team</em></h2>
            <p>The people behind Rpay</p>
        </div>

        <div class="team-stack">
            <?php foreach ($team as $i => $p):
                // Even cards: text left / photo right. Odd cards: text right / photo left.
                $side = ($i % 2 === 0) ? 'left' : 'right';
                $isRounded = (!empty($p['photo_shape']) && $p['photo_shape'] === 'rounded-square');
            ?>
                <div class="team-card <?= $side ?>">
                    <div class="ghost-clip"><span class="ghost"><?= e($p['ghost']) ?></span></div>

                    <img class="team-photo <?= $isRounded ? 'photo-rounded-square' : '' ?>" src="<?= e($p['photo']) ?>" alt="<?= e($p['name']) ?>" loading="eager">

                    <div class="team-text">
                        <span class="team-bar"></span>
                        <div>
                            <h3><?= e($p['role']) ?></h3>
                            <div class="person">
                                <div class="pname"><?= e($p['name']) ?></div>
                                <?php if (!empty($p['title'])): ?>
                                    <div class="ptitle"><?= e($p['title']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($p['email']) || !empty($p['link'])): ?>
                                    <div class="pmeta">
                                        <?php if (!empty($p['email'])): ?>
                                            <a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a>
                                        <?php endif; ?>
                                        <?php if (!empty($p['email']) && !empty($p['link'])): ?><span class="sep">&middot;</span><?php endif; ?>
                                        <?php if (!empty($p['link'])): ?>
                                            <a href="<?= e($p['link']) ?>" target="_blank" rel="noopener noreferrer"><?= e($p['link_label'] ?? 'Profile') ?></a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($p['github']) || !empty($p['linkedin'])): ?>
                                    <div class="social-links">
                                        <?php if (!empty($p['github'])): ?>
                                            <a href="<?= e($p['github']) ?>" target="_blank" rel="noopener noreferrer" class="social-btn github" aria-label="GitHub Profile" title="GitHub">
                                                <svg width="18" height="18" viewBox="0 0 24 24"><path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($p['linkedin'])): ?>
                                            <a href="<?= e($p['linkedin']) ?>" target="_blank" rel="noopener noreferrer" class="social-btn linkedin" aria-label="LinkedIn Profile" title="LinkedIn">
                                                <svg width="18" height="18" viewBox="0 0 24 24"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 10.9v8.37H9.2V10.9H6.46M7.83 6.64a1.64 1.64 0 0 0-1.66 1.63 1.63 1.63 0 0 0 1.66 1.64 1.63 1.63 0 0 0 1.64-1.64 1.64 1.64 0 0 0-1.64-1.63Z"/></svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<footer>
    <div class="container">
        <div>Made with &hearts; by <a href="https://rlabz.in" target="_blank" rel="noopener noreferrer">RLabz</a></div>
        <div>&copy; <?= date('Y') ?> RLabz. All rights reserved.</div>
    </div>
</footer>

<script>
    (function () {
        var btn = document.getElementById('themeToggleBtn');
        if (btn) {
            btn.addEventListener('click', function () {
                var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('vortex_theme', next);
                document.cookie = 'vortex_theme=' + next + '; path=/; max-age=31536000; SameSite=Lax';
            });
        }

        var mobileBtn = document.getElementById('mobileMenuBtn');
        var mobileMenu = document.getElementById('mobileNavMenu');
        if (mobileBtn && mobileMenu) {
            mobileBtn.addEventListener('click', function () {
                var isOpen = mobileMenu.classList.toggle('open');
                mobileBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            // Close mobile menu when clicking any nav link
            mobileMenu.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    mobileMenu.classList.remove('open');
                    mobileBtn.setAttribute('aria-expanded', 'false');
                });
            });

            // Close menu when clicking outside
            document.addEventListener('click', function (e) {
                if (!mobileMenu.contains(e.target) && !mobileBtn.contains(e.target) && mobileMenu.classList.contains('open')) {
                    mobileMenu.classList.remove('open');
                    mobileBtn.setAttribute('aria-expanded', 'false');
                }
            });
        }

        var contactForm = document.querySelector('.contact-form');
        if (contactForm) {
            contactForm.addEventListener('submit', function () {
                var submitBtn = contactForm.querySelector('button[type="submit"]');
                if (submitBtn) {
                    setTimeout(function () {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Sending...';
                    }, 50);
                }
            });
        }
    })();
</script>
</body>
</html>