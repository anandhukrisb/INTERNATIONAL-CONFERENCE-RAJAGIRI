<?php
$reg_id = $_GET['reg_id'] ?? '';
$fetched_user = null;
$reg_status = '';
$txn_id = '';

if ($reg_id !== '') {
    try {
        require_once __DIR__ . '/backend/db.php';
        $stmt = $pdo->prepare("SELECT * FROM user_registrations WHERE registration_id = :reg_id LIMIT 1");
        $stmt->execute([':reg_id' => $reg_id]);
        $fetched_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fetched_user) {
            if (($fetched_user['payment_status'] ?? '') === 'Completed') {
                $reg_status = 'success';
                $txn_id = $fetched_user['transaction_id'] ?? '';
            }
        }
    } catch (Exception $e) {
        // Ignore DB error
    }
}

if (!$fetched_user) {
    echo "Invalid Registration ID or Registration not found.";
    exit;
}

$rawCost = $fetched_user['package'];
$baseAmount = (float)($fetched_user['base_amount'] ?? 0);
$formattedAmount = ($baseAmount == (int)$baseAmount) ? number_format($baseAmount) : number_format($baseAmount, 2);
$currency = (strpos(strtolower($fetched_user['country_category']), 'india') !== false || strtolower($fetched_user['country_category']) === 'national') ? 'INR' : 'USD';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration - ICSWHMH 2027</title>
    <link rel="icon" type="image/x-icon"
        href="https://res.cloudinary.com/dswfp5fwx/image/upload/v1778131826/Favicon-192_hdltam.ico">

    <script src="navbar.js" defer></script>
    <script src="footer.js" defer></script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Outfit:wght@400;600;800&display=swap"
        rel="stylesheet">

    <style>
        /* ================= GLOBAL STYLES ================= */
        :root {
            --primary-purple: #1D0A3F;
            --accent-gold: #C9A227;
            --bg-light: #FDFBF7;
            --text-dark: #2D3436;
            --text-muted: #555;
            --white: #ffffff;
            --shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            overflow-x: hidden;
            width: 100%;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            font-family: 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            overflow-x: hidden;
        }

        .main-container {
            max-width: 95%;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* ================= TITLE SECTION ================= */
        .title-section {
            margin-top: 150px;
            margin-bottom: 60px;
        }

        #pageTitle {
            font-family: 'Outfit', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-purple);
            text-transform: uppercase;
            position: relative;
            display: inline-block;
        }

        #pageTitle::after {
            content: "";
            display: block;
            width: 80px;
            height: 4px;
            background-color: var(--accent-gold);
            margin-top: 10px;
            border-radius: 2px;
        }

        /* ================= CONTENT SECTIONS ================= */
        .intro-text {
            font-size: 1.15rem;
            margin-bottom: 40px;
            color: var(--text-muted);
            max-width: 100%;
            text-align: justify;
        }

        .section-header {
            font-family: 'Outfit', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 1.8rem;
            color: var(--primary-purple);
            margin-bottom: 25px;
            font-weight: 600;
        }

        /* ================= INCLUSIONS GRID ================= */
        .inclusions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }

        .inclusion-card {
            background: #1D0A3F;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            border-left: 6px solid var(--accent-gold);
            border-top: none;
        }

        .inclusion-card h3 {
            font-family: 'Outfit', sans-serif;
            color: var(--white);
            margin-bottom: 20px;
            font-size: 1.4rem;
            font-weight: 700;
        }

        .inclusion-list {
            list-style: none;
        }

        .inclusion-list li {
            position: relative;
            padding-left: 25px;
            margin-bottom: 12px;
            font-size: 1rem;
            color: #F0F0F0;
        }

        .inclusion-list li::before {
            content: "\F272";
            /* Bootstrap Icon Check */
            font-family: "bootstrap-icons";
            position: absolute;
            left: 0;
            color: var(--accent-gold);
            font-weight: bold;
        }

        /* ================= PRICING TABLE ================= */
        .pricing-section {
            margin-bottom: 60px;
            overflow-x: auto;
        }

        .pricing-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .pricing-table th {
            background-color: var(--primary-purple);
            color: var(--white);
            text-align: left;
            padding: 18px 25px;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .pricing-table td {
            padding: 18px 25px;
            border-bottom: 1px solid #eee;
            color: var(--text-muted);
        }

        .pricing-table tr:last-child td {
            border-bottom: none;
        }

        .pricing-table tr:nth-child(even) {
            background-color: #f9f9fb;
        }

        .price-tag {
            font-weight: 700;
            color: var(--primary-purple);
        }

        /* ================= INFO SECTIONS (ACCORDION STYLE) ================= */
        .info-section {
            margin-bottom: 80px;
        }

        .info-strip {
            margin-bottom: 25px;
            background: var(--white);
            border: 1px solid #ddd;
            border-radius: 10px;
            /* Slight rounding like in image */
            overflow: hidden;
        }

        .info-header {
            background-color: var(--primary-purple);
            color: var(--white);
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
        }

        .info-header .icon {
            font-size: 1.2rem;
            font-weight: bold;
        }

        .info-content {
            padding: 0 25px;
            color: var(--text-muted);
            font-size: 1rem;
            max-height: 0;
            /* Changed to 0 by default for consistency with JS-driven open */
            opacity: 0;
            transition: max-height 0.3s ease-out, opacity 0.3s ease, padding 0.3s ease;
            overflow: hidden;
            text-align: justify;
        }

        .info-strip.expanded .info-content {
            max-height: 1000px;
            /* Fallback */
            opacity: 1;
            padding-top: 0;
            padding-bottom: 0;
        }

        .info-content-inner {
            padding: 25px 0;
        }

        .info-content p {
            margin-bottom: 15px;
        }

        .info-content p:last-child {
            margin-bottom: 0;
        }

        /* Cancellation Table */
        .cancel-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .cancel-table th,
        .cancel-table td {
            border: 1px solid #333;
            padding: 12px 18px;
            text-align: left;
        }

        .cancel-table th {
            background-color: #fff;
            color: #000;
            font-weight: 700;
        }

        .cancel-table tr td:first-child {
            width: 60%;
        }

        .btn-register {
            display: inline-block;
            background-color: var(--accent-gold);
            color: #1D0A3F;
            padding: 15px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            text-transform: capitalize;
            letter-spacing: normal;
            transition: transform 0.3s ease;
            margin-top: 30px;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn-register::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #1D0A3F;
            z-index: -1;
            transition: clip-path 0.4s ease-out;
            clip-path: circle(0% at 0 50%);
        }

        .btn-register:hover::before {
            clip-path: circle(150% at 0 50%);
        }

        .btn-register:hover {
            transform: translateY(-3px);
            color: var(--accent-gold);
            box-shadow: 0 10px 20px rgba(29, 10, 63, 0.2);
        }



        /* ================= BOOTSTRAP ICONS FALLBACK ================= */
        @font-face {
            font-family: "bootstrap-icons";
            src: url("https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/fonts/bootstrap-icons.woff2?24ad65009a63aa365fd148d0a1b9b395") format("woff2");
        }

        /* ================= MOBILE RESPONSIVENESS ================= */
        @media (max-width: 768px) {
            * {
                max-width: 100%;
            }

            .main-container {
                padding: 0 15px;
                width: 100%;
                max-width: 100vw;
                box-sizing: border-box;
            }

            .title-section {
                margin-top: 120px;
                text-align: center;
                width: 100%;
            }

            #pageTitle {
                font-size: 2rem;
            }

            #pageTitle::after {
                margin: 10px auto;
            }

            .intro-text {
                font-size: 1rem;
                text-align: left;
                width: 100%;
                max-width: 100%;
            }

            .section-header {
                font-size: 1.5rem;
            }

            .inclusions-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                width: 100%;
            }

            .inclusion-card {
                padding: 25px;
                width: 100%;
                box-sizing: border-box;
            }

            .pricing-section {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                width: 100%;
                margin-left: -15px;
                margin-right: -15px;
                padding: 0 15px;
            }

            .pricing-table {
                min-width: 600px;
            }

            .pricing-table th,
            .pricing-table td {
                padding: 12px 15px;
                font-size: 0.85rem;
            }

            .cancel-table {
                min-width: 100%;
                font-size: 0.85rem;
            }

            .cancel-table th,
            .cancel-table td {
                padding: 10px 12px;
            }

            .info-section {
                width: 100%;
            }

            .info-strip {
                width: 100%;
                box-sizing: border-box;
            }

            .info-header {
                font-size: 1rem;
                padding: 12px 20px;
            }

            .info-content {
                padding: 0 20px;
            }

            .info-content-inner {
                padding: 20px 0;
            }

            .btn-register {
                padding: 14px 35px;
                font-size: 0.9rem;
            }
        }

        /* ================= FEE GUIDELINES STYLES ================= */
        .fee-guidelines-section {
            margin: 0 auto 50px auto;
            max-width: 1050px;
            background: var(--white);
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            border-top: 5px solid var(--accent-gold);
        }

        .fee-toggle-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .fee-toggle-btn {
            background-color: #f0f0f0;
            color: var(--text-dark);
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .fee-toggle-btn.active {
            background-color: var(--primary-purple);
            color: var(--white);
        }

        .fee-tab-content {
            display: none;
        }

        .fee-tab-content.active {
            display: block;
            animation: fadeIn 0.4s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fee-table-title {
            font-family: 'Outfit', sans-serif;
            color: var(--primary-purple);
            font-size: 1.3rem;
            margin-top: 30px;
            margin-bottom: 15px;
        }

        .fee-table-title:first-child {
            margin-top: 0;
        }

        /* Modern Pricing Cards */
        .pricing-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .pricing-card {
            background: var(--white);
            border-radius: 12px;
            padding: 30px 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            border: 1px solid #eaeaea;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
        }

        .pricing-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--primary-purple);
        }

        .pricing-card.accent::before {
            background: var(--accent-gold);
        }

        .pricing-card-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .pricing-category {
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--primary-purple);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .pricing-main-price {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1;
        }

        .pricing-main-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 6px;
            font-weight: 500;
        }

        .pricing-features {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }

        .pricing-features li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed #eaeaea;
            font-size: 0.95rem;
            color: var(--text-muted);
        }

        .pricing-features li:last-child {
            border-bottom: none;
        }

        .pricing-feature-label {
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pricing-feature-label::before {
            content: "✓";
            color: var(--accent-gold);
            font-weight: bold;
            font-size: 1.2rem;
        }

        .pricing-feature-value {
            font-weight: 700;
            color: var(--text-dark);
        }

        .fee-filter-controls {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            background: #f9f9fb;
            padding: 20px;
            border-radius: 8px;
        }

        .filtered-fee-result {
            background: #f0f4f8;
            padding: 25px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-purple);
        }

        .result-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #fff;
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        }

        .result-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1.1rem;
        }

        .result-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-purple);
        }

        @media (max-width: 768px) {
            .fee-filter-controls {
                flex-direction: column;
            }

            .result-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .fee-toggle-btn {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
        }

        /* ================= FORM STYLES ================= */
        .registration-form-section {
            margin-bottom: 80px;
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            border-top: 5px solid var(--accent-gold);
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary-purple);
            font-size: 0.95rem;
        }

        .form-control {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            background-color: #f9f9fb;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(29, 10, 63, 0.1);
            background-color: #fff;
        }

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%231D0A3F' class='bi bi-chevron-down' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 12px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary-purple);
        }

        .checkbox-label {
            font-size: 0.95rem;
            color: var(--text-dark);
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 15px;
            }

            .registration-form-section {
                padding: 25px;
            }
        }

        /* ================= STICKY SIDEBAR BUTTON ================= */
        .sticky-sidebar-buttons {
            position: fixed;
            top: 170px;
            right: 0;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .sidebar-btn {
            display: block;
            background-color: #C9A227;
            color: #1D0A3F;
            padding: 12px 20px;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            border-radius: 8px 0 0 8px;
            box-shadow: -2px 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            font-size: 0.95rem;
            min-width: 140px;
            position: relative;
            overflow: hidden;
            z-index: 1;
            cursor: pointer;
        }

        .sidebar-btn::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #1D0A3F;
            z-index: -1;
            transition: clip-path 0.3s ease-out;
            clip-path: circle(0% at 0 50%);
        }

        .sidebar-btn:hover::before {
            clip-path: circle(150% at 0 50%);
        }

        .sidebar-btn:hover {
            transform: translateX(-5px);
            color: #FFFFFF;
        }

        @media (max-width: 768px) {
            .sticky-sidebar-buttons {
                top: auto;
                bottom: 20px;
                right: 20px;
            }

            .sidebar-btn {
                border-radius: 8px;
                min-width: 120px;
                font-size: 0.85rem;
            }
        }

        /* ================= WIZARD & PROGRESS BAR ================= */
        .wizard-progress {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin: 0 auto 50px auto;
            max-width: 600px;
        }

        .wizard-progress::before {
            content: "";
            background-color: #E2E8F0;
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            height: 4px;
            width: 100%;
            z-index: 1;
        }

        .progress-line {
            background-color: var(--accent-gold);
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            height: 4px;
            width: 0%;
            /* Dynamic based on steps */
            z-index: 2;
            transition: width 0.4s ease;
        }

        .progress-step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #fff;
            border: 3px solid #E2E8F0;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 3;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            color: #718096;
            transition: all 0.4s ease;
            position: relative;
            background-clip: padding-box;
        }

        .progress-step.active {
            border-color: var(--primary-purple);
            color: var(--primary-purple);
            background-color: #fff;
            box-shadow: 0 0 15px rgba(29, 10, 63, 0.2);
        }

        .progress-step.completed {
            border-color: var(--accent-gold);
            background-color: var(--accent-gold);
            color: #1D0A3F;
        }

        .progress-step::after {
            content: attr(data-label);
            position: absolute;
            top: 48px;
            font-size: 0.8rem;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            white-space: nowrap;
            color: #718096;
            transition: color 0.4s ease;
        }

        .progress-step.active::after {
            color: var(--primary-purple);
        }

        .progress-step.completed::after {
            color: var(--accent-gold);
        }

        /* ================= STEP CONTAINERS ================= */
        .step-container {
            display: none;
            animation: slideIn 0.5s ease forwards;
        }

        .step-container.active {
            display: block;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(15px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ================= VERIFICATION BOX ================= */
        .verification-box {
            background: linear-gradient(135deg, rgba(29, 10, 63, 0.02) 0%, rgba(201, 162, 39, 0.05) 100%);
            border: 1px dashed var(--accent-gold);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }

        .verification-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }

        .btn-verify {
            background-color: var(--primary-purple);
            color: var(--white);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 130px;
        }

        .btn-verify:hover {
            background-color: var(--accent-gold);
            color: #1D0A3F;
            transform: translateY(-2px);
        }

        .btn-verify:disabled {
            background-color: #CBD5E0;
            color: #718096;
            cursor: not-allowed;
            transform: none;
        }

        .verification-status {
            margin-top: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            display: none;
            align-items: center;
            gap: 8px;
        }

        .verification-status.success {
            color: #2F855A;
            display: flex;
        }

        .verification-status.error {
            color: #C53030;
            display: flex;
        }

        .verified-badge {
            background-color: #C6F6D5;
            color: #22543D;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .locked-form-overlay {
            opacity: 0.4;
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .unlocked-form {
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        /* ================= REVIEW RECEIPT & INVOICE ================= */
        .invoice-card {
            background-color: #fff;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.02);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .invoice-header {
            background: var(--primary-purple);
            color: var(--white);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .invoice-header h4 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: 0.5px;
        }

        .invoice-body {
            padding: 30px;
        }

        .receipt-section-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            color: var(--primary-purple);
            border-bottom: 2px solid #F7FAFC;
            padding-bottom: 8px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .receipt-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px 30px;
            margin-bottom: 30px;
        }

        .receipt-item {
            display: flex;
            flex-direction: column;
        }

        .receipt-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 2px;
        }

        .receipt-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .invoice-table th,
        .invoice-table td {
            padding: 12px 15px;
            text-align: left;
        }

        .invoice-table th {
            background-color: #F8FAFC;
            color: var(--primary-purple);
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: 0.9rem;
            border-bottom: 1px solid #E2E8F0;
        }

        .invoice-table td {
            border-bottom: 1px solid #EDF2F7;
            font-size: 0.95rem;
        }

        .invoice-table tr.total-row td {
            border-bottom: none;
            border-top: 2px solid var(--primary-purple);
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary-purple);
        }

        .invoice-table tr.gst-row td {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .btn-container {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-back {
            background-color: #EDF2F7;
            color: var(--text-dark);
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background-color: #E2E8F0;
            transform: translateY(-2px);
        }

        /* ================= SUCCESS CELEBRATION ================= */
        .success-celebration {
            text-align: center;
            padding: 40px 20px;
        }

        .success-icon-wrap {
            width: 80px;
            height: 80px;
            background-color: #C6F6D5;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 30px auto;
            color: #22543D;
            font-size: 2.5rem;
            animation: popCheck 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }

        @keyframes popCheck {
            0% {
                transform: scale(0);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .success-celebration h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.8rem;
            color: var(--primary-purple);
            margin-bottom: 15px;
        }

        .success-celebration p {
            color: var(--text-muted);
            margin-bottom: 30px;
            font-size: 1.05rem;
        }

        .receipt-summary-box {
            background-color: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            max-width: 500px;
            margin: 0 auto 40px auto;
            padding: 25px;
            text-align: left;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #E2E8F0;
            font-size: 0.95rem;
        }

        .receipt-row:last-child {
            border-bottom: none;
            font-weight: 700;
            color: var(--primary-purple);
            font-size: 1.05rem;
        }

        .receipt-row span:first-child {
            color: var(--text-muted);
        }

        .receipt-row span:last-child {
            font-weight: 600;
        }

        /* ================= PAYMENT SIMULATION MODAL ================= */

        .gateway-loader {
            width: 60px;
            height: 60px;
            border: 4px solid #E2E8F0;
            border-top: 4px solid var(--primary-purple);
            border-radius: 50%;
            margin: 0 auto 25px auto;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }



        /* ================= FAILURE STATUS DISPLAY ================= */
        .payment-error-box {
            background-color: #FFF5F5;
            border: 1px solid #FEB2B2;
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 25px;
            display: none;
            align-items: flex-start;
            gap: 12px;
            text-align: left;
            animation: shake 0.4s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        .payment-error-box.active {
            display: flex;
        }
    </style>

</head>

<body>

    <floating-navbar></floating-navbar>

    <!-- Sticky Sidebar Buttons -->
    <div class="sticky-sidebar-buttons">
        <a href="view_transaction.php" class="sidebar-btn">Check Details</a>
    </div>

    <main class="main-container">

        <div class="title-section">
            <h2 id="pageTitle">REGISTRATION</h2>
        </div>

        <!-- Fee Guidelines Section -->
        <section class="fee-guidelines-section" style="display: none !important;">
            <h2 class="section-header" style="text-align: center; margin-bottom: 30px;">FEE GUIDELINES</h2>
            <div class="fee-toggle-container">
                <button class="fee-toggle-btn active" id="btn-view-all" onclick="showFeeTab('all')">View All
                    Fees</button>
                <button class="fee-toggle-btn" id="btn-filter" onclick="showFeeTab('filter')">Find My Fee</button>
            </div>

            <!-- View All Tab -->
            <div id="fee-view-all" class="fee-tab-content active">

                <h3 class="fee-table-title"
                    style="text-align: center; margin-bottom: 30px; font-size: 1.6rem; font-weight: 700;">General
                    Registration</h3>
                <div class="pricing-cards-container">
                    <!-- Developed -->
                    <div class="pricing-card">
                        <div class="pricing-card-header">
                            <div class="pricing-category">Developed Countries</div>
                            <div class="pricing-main-price">350 USD</div>
                            <div class="pricing-main-label">Early Bird Registration</div>
                        </div>
                        <ul class="pricing-features">
                            <li>
                                <span class="pricing-feature-label">Regular</span>
                                <span class="pricing-feature-value">400 USD</span>
                            </li>
                            <li>
                                <span class="pricing-feature-label">Spot Registration</span>
                                <span class="pricing-feature-value">500 USD</span>
                            </li>
                            <li>
                                <span class="pricing-feature-label">Online (Recorded/Live)</span>
                                <span class="pricing-feature-value">250 USD</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Developing -->
                    <div class="pricing-card accent">
                        <div class="pricing-card-header">
                            <div class="pricing-category">Developing Countries</div>
                            <div class="pricing-main-price">220 USD</div>
                            <div class="pricing-main-label">Early Bird Registration</div>
                        </div>
                        <ul class="pricing-features">
                            <li>
                                <span class="pricing-feature-label">Regular</span>
                                <span class="pricing-feature-value">250 USD</span>
                            </li>
                            <li>
                                <span class="pricing-feature-label">Spot Registration</span>
                                <span class="pricing-feature-value">300 USD</span>
                            </li>
                            <li>
                                <span class="pricing-feature-label">Online (Recorded/Live)</span>
                                <span class="pricing-feature-value">175 USD</span>
                            </li>
                        </ul>
                    </div>

                    <!-- National -->
                    <div class="pricing-card">
                        <div class="pricing-card-header">
                            <div class="pricing-category">National (India)</div>
                            <div class="pricing-main-price">3,000 INR</div>
                            <div class="pricing-main-label">Early Bird Registration</div>
                        </div>
                        <ul class="pricing-features">
                            <li>
                                <span class="pricing-feature-label">Regular</span>
                                <span class="pricing-feature-value">3,500 INR</span>
                            </li>
                            <li>
                                <span class="pricing-feature-label">Spot Registration</span>
                                <span class="pricing-feature-value">4,500 INR</span>
                            </li>
                            <li>
                                <span class="pricing-feature-label">Online (Recorded/Live)</span>
                                <span class="pricing-feature-value">2,000 INR</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <h3 class="fee-table-title"
                    style="text-align: center; margin-top: 40px; margin-bottom: 30px; font-size: 1.6rem; font-weight: 700;">
                    PhD Scholars <span
                        style="font-size: 1.05rem; color: var(--text-muted); font-weight: 500; display: block; margin-top: 5px;">(One-day
                        Colloquium)</span></h3>
                <div class="pricing-cards-container">
                    <!-- Developed -->
                    <div class="pricing-card">
                        <div class="pricing-card-header">
                            <div class="pricing-category">Developed Countries</div>
                            <div class="pricing-main-price">150 USD</div>
                            <div class="pricing-main-label">In-Person (Offline)</div>
                        </div>
                        <ul class="pricing-features">
                            <li>
                                <span class="pricing-feature-label">Online (Recorded)</span>
                                <span class="pricing-feature-value">100 USD</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Developing -->
                    <div class="pricing-card accent">
                        <div class="pricing-card-header">
                            <div class="pricing-category">Developing Countries</div>
                            <div class="pricing-main-price">100 USD</div>
                            <div class="pricing-main-label">In-Person (Offline)</div>
                        </div>
                        <ul class="pricing-features">
                            <li>
                                <span class="pricing-feature-label">Online (Recorded)</span>
                                <span class="pricing-feature-value">75 USD</span>
                            </li>
                        </ul>
                    </div>

                    <!-- National -->
                    <div class="pricing-card">
                        <div class="pricing-card-header">
                            <div class="pricing-category">National (India)</div>
                            <div class="pricing-main-price">1,200 INR</div>
                            <div class="pricing-main-label">In-Person (Offline)</div>
                        </div>
                        <ul class="pricing-features">
                            <li>
                                <span class="pricing-feature-label">Online (Recorded)</span>
                                <span class="pricing-feature-value">600 INR</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <h3 class="fee-table-title"
                    style="text-align: center; margin-top: 40px; margin-bottom: 30px; font-size: 1.6rem; font-weight: 700;">
                    Students <span
                        style="font-size: 1.05rem; color: var(--text-muted); font-weight: 500; display: block; margin-top: 5px;">(One-day
                        International Summit)</span></h3>
                <div class="pricing-cards-container">
                    <!-- Developed -->
                    <div class="pricing-card">
                        <div class="pricing-card-header">
                            <div class="pricing-category">Developed Countries</div>
                            <div class="pricing-main-price">100 USD</div>
                            <div class="pricing-main-label">In-Person (Offline)</div>
                        </div>
                        <ul class="pricing-features">
                            <li>
                                <span class="pricing-feature-label">Online (Recorded)</span>
                                <span class="pricing-feature-value">50 USD</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Developing -->
                    <div class="pricing-card accent">
                        <div class="pricing-card-header">
                            <div class="pricing-category">Developing Countries</div>
                            <div class="pricing-main-price">75 USD</div>
                            <div class="pricing-main-label">In-Person (Offline)</div>
                        </div>
                        <ul class="pricing-features">
                            <li>
                                <span class="pricing-feature-label">Online (Recorded)</span>
                                <span class="pricing-feature-value">40 USD</span>
                            </li>
                        </ul>
                    </div>

                    <!-- National -->
                    <div class="pricing-card">
                        <div class="pricing-card-header">
                            <div class="pricing-category">National (India)</div>
                            <div class="pricing-main-price">1,000 INR</div>
                            <div class="pricing-main-label">In-Person (Offline)</div>
                        </div>
                        <ul class="pricing-features">
                            <li>
                                <span class="pricing-feature-label">Online (Recorded)</span>
                                <span class="pricing-feature-value">500 INR</span>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>

            <!-- Filter Tab -->
            <div id="fee-view-filter" class="fee-tab-content">
                <div class="fee-filter-controls">
                    <div class="form-group">
                        <label class="form-label" for="filter-participant">Participant Type</label>
                        <select id="filter-participant" class="form-control" onchange="updateFeeFilter()">
                            <option value="general">General Registration</option>
                            <option value="phd">PhD Scholar</option>
                            <option value="student">Student</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="filter-category">Country Category</label>
                        <select id="filter-category" class="form-control" onchange="updateFeeFilter()">
                            <option value="developed">Developed Countries</option>
                            <option value="developing">Developing Countries</option>
                            <option value="national">National (India)</option>
                        </select>
                    </div>
                </div>
                <div id="filtered-fee-result" class="filtered-fee-result">
                    <!-- Results populated by JS -->
                </div>
            </div>
        </section>

        <!--
        <section class="intro-section">
            <p class="intro-text">
                Welcome to the registration page for the 11th International Conference for Social Work in Health and
                Mental Health. All persons intending to attend the Conference must complete an online Registration Form.
            </p>
            <p class="intro-text">
                Please read the below registration information before you complete the Registration Form. Should you
                experience any difficulties please contact the Conference Managers at
                <a href="mailto:icswhmh2027@rajagiri.edu"
                    style="color: var(--accent-gold); text-decoration: none;">icswhmh2027@rajagiri.edu</a>.
            </p>
        </section>
        -->

        <!-- Pricing Section -->
        <!-- Pricing Section -->
        <!--
        <section class="pricing-section">
            <h2 class="section-header">REGISTRATION FEES</h2>
            <p class="intro-text">
                Registration for the conference will be available on the official conference website.
            </p>

            <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Type of Participant</th>
                            <th rowspan="2">Country</th>
                            <th colspan="3" style="text-align: center;">Registration Type</th>
                            <th rowspan="2">Spot</th>
                        </tr>
                        <tr>
                            <th style="text-align: center;">Regular</th>
                            <th style="text-align: center;">Early Bird</th>
                            <th style="text-align: center;">Day Registration**</th>
                        </tr>
                    </thead>
                    <tbody>
                        
                        <tr>
                            <td rowspan="3" style="font-weight: 600; vertical-align: middle;">Academics / Field
                                Practitioners</td>
                            <td>From Developed Countries</td>
                            <td class="price-tag">400 USD</td>
                            <td class="price-tag">350 USD</td>
                            <td class="price-tag">150 USD</td>
                            <td class="price-tag">500 USD</td>
                        </tr>
                        
                        <tr>
                            <td>From Developing Countries</td>
                            <td class="price-tag">250 USD</td>
                            <td class="price-tag">220 USD</td>
                            <td class="price-tag">150 USD</td>
                            <td class="price-tag">300 USD</td>
                        </tr>
                        
                        <tr>
                            <td>National Participants</td>
                            <td class="price-tag">3500 INR</td>
                            <td class="price-tag">3000 INR</td>
                            <td class="price-tag">1500 INR</td>
                            <td class="price-tag">4500 INR</td>
                        </tr>

                        
                        <tr>
                            <td rowspan="3" style="font-weight: 600; vertical-align: middle;">Students / PhD Scholars
                            </td>
                            <td>From Developed Countries</td>
                            <td class="price-tag">300 USD</td>
                            <td class="price-tag">275 USD</td>
                            <td class="price-tag">150 USD</td>
                            <td class="price-tag">350 USD</td>
                        </tr>
                        
                        <tr>
                            <td>From Developing Countries</td>
                            <td class="price-tag">200 USD</td>
                            <td class="price-tag">175 USD</td>
                            <td class="price-tag">150 USD</td>
                            <td class="price-tag">250 USD</td>
                        </tr>
                        
                        <tr>
                            <td>National Participants</td>
                            <td class="price-tag">2500 INR</td>
                            <td class="price-tag">2000 INR</td>
                            <td class="price-tag">1500 INR</td>
                            <td class="price-tag">3000 INR</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            
            <div
                style="margin-top: 30px; padding: 25px; background: #fff9e6; border-left: 4px solid var(--accent-gold); border-radius: 8px;">
                <p style="margin-bottom: 15px; font-size: 0.95rem; line-height: 1.6; color: var(--text-dark);">
                    <strong>Notes:</strong>
                </p>
                <ul style="margin-left: 20px; color: var(--text-muted); font-size: 0.95rem; line-height: 1.8;">
                    <li style="margin-bottom: 10px;">All rates are excluding GST. <strong>18% GST rates are
                            applicable.</strong></li>
                    <li style="margin-bottom: 10px;"><strong>*Full conference registration fees</strong> include access
                        to all conference sessions, conference kits, networking events, coffee breaks, lunches,
                        conference dinners, and electronic proceedings.</li>
                    <li><strong>**Day registration fees</strong> include access to conference sessions, coffee breaks,
                        and lunch for any single day.</li>
                </ul>
            </div>
        </section>
        -->


        <!-- What's Included Section -->
        <!-- What's Included Section -->
        <!--
        <section class="info-section" style="margin-top: 60px; margin-bottom: 60px;">
            <h2 class="section-header">WHAT'S INCLUDED</h2>
            <div class="inclusions-grid">
                <div class="inclusion-card">
                    <h3>Full Conference Registration*</h3>
                    <ul class="inclusion-list">
                        <li>Access to all conference sessions</li>
                        <li>Conference kit</li>
                        <li>Networking events</li>
                        <li>Daily coffee breaks</li>
                        <li>Conference lunches</li>
                        <li>Conference dinners</li>
                        <li>Electronic proceedings</li>
                    </ul>
                </div>
                <div class="inclusion-card">
                    <h3>Day Registration**</h3>
                    <ul class="inclusion-list">
                        <li>Access to conference sessions (single day)</li>
                        <li>Coffee breaks</li>
                        <li>Lunch for the selected day</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        -->
        <section class="registration-form-section">
            <!-- Wizard Progress Indicator Removed for Simplicity -->

            <!-- LOADING STATE -->
            <div id="view-loading" class="step-container <?= ($reg_status !== '') ? 'active' : '' ?>">
                <div class="success-celebration" style="border-top-color: var(--accent-gold);">
                    <div style="display: flex; justify-content: center; margin-bottom: 20px;">
                        <div class="gateway-loader" style="width: 50px; height: 50px; display: block; margin: 0;"></div>
                    </div>
                    <h3 style="color: var(--primary-purple);">Processing...</h3>
                    <p>Please wait while we retrieve your transaction data.</p>
                </div>
            </div>

            <!-- STEP 1: REGISTRATION -->
            <div id="view-review" class="step-container <?= ($reg_status === '') ? 'active' : '' ?>">
                <h2 class="section-header" style="text-align: center; margin-bottom: 35px;">REVIEW REGISTRATION & FEES
                </h2>

                <!-- Payment Error Display -->
                <div id="paymentErrorBox" class="payment-error-box">
                    <span style="font-size: 1.2rem; line-height: 1;">⚠</span>
                    <div>
                        <strong style="display: block; margin-bottom: 4px;">Payment Failed</strong>
                        <span id="paymentErrorMessage">Your simulated transaction was declined. Please try again.</span>
                    </div>
                </div>

                <div style="background-color: #FFF3CD; border-left: 4px solid #D69E2E; padding: 12px 15px; margin-bottom: 20px; border-radius: 4px;">
                    <p style="margin: 0; color: #975A16; font-size: 0.9rem; display: flex; align-items: flex-start; gap: 8px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-top: 2px; flex-shrink: 0;"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>
                        <span><strong>Important:</strong> Please safely copy and save your Registration ID (<?= htmlspecialchars($reg_id) ?>) for future reference and to check your payment status later.</span>
                    </p>
                </div>

                <div class="invoice-card">
                    <div class="invoice-header">
                        <h4>REGISTRATION SUMMARY</h4>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div id="invoiceReference" style="font-weight: 600; font-size: 0.9rem; opacity: 0.9;">Ref: <?= htmlspecialchars($reg_id) ?></div>
                            <button type="button" onclick="copyRegId('<?= htmlspecialchars($reg_id, ENT_QUOTES) ?>')" style="background: none; border: none; cursor: pointer; color: white; opacity: 0.8; padding: 0; display: flex; align-items: center; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'" title="Copy Registration ID">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1v-1z"/><path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5h3zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3z"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="invoice-body">
                        <!-- Demographics Review -->
                        <h5 class="receipt-section-title">Registrant Metadata</h5>
                        <div class="receipt-grid">
                            <div class="receipt-item">
                                <span class="receipt-label">Full Name</span>
                                <span class="receipt-value" id="reviewName"><?= htmlspecialchars(trim(($fetched_user['first_name'] ?? '') . ' ' . ($fetched_user['middle_name'] ?? '') . ' ' . ($fetched_user['last_name'] ?? ''))) ?></span>
                            </div>
                            <div class="receipt-item">
                                <span class="receipt-label">Email Address</span>
                                <span class="receipt-value" id="reviewEmail"><?= htmlspecialchars($fetched_user['email'] ?? '') ?></span>
                            </div>
                            <div class="receipt-item">
                                <span class="receipt-label">Institution / Organization</span>
                                <span class="receipt-value" id="reviewOrganization"><?= htmlspecialchars($fetched_user['organization'] ?? '') ?></span>
                            </div>
                            <div class="receipt-item">
                                <span class="receipt-label">Phone Number</span>
                                <span class="receipt-value" id="reviewPhone"><?= htmlspecialchars($fetched_user['phone'] ?? '') ?></span>
                            </div>
                            <div class="receipt-item">
                                <span class="receipt-label">Participant Type</span>
                                <span class="receipt-value" id="reviewType"><?= htmlspecialchars($fetched_user['participant_category'] ?? '') ?></span>
                            </div>
                            <div class="receipt-item">
                                <span class="receipt-label">Country Category</span>
                                <span class="receipt-value" id="reviewCountry"><?= htmlspecialchars($fetched_user['country_category'] ?? '') ?></span>
                            </div>
                            <div class="receipt-item" style="grid-column: span 2;">
                                <span class="receipt-label">Selected Requirement</span>
                                <span class="receipt-value" id="reviewRequirement"><?= htmlspecialchars($fetched_user['package'] ?? '') ?></span>
                            </div>
                        </div>

                        <!-- Price Calculations -->
                        <h5 class="receipt-section-title">Itemized Fee Invoice</h5>
                        <table class="invoice-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th style="text-align: right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td id="invoiceItemDesc"><?= htmlspecialchars($fetched_user["participant_category"]) ?> Package: <?= htmlspecialchars($fetched_user["package"]) ?> Access</td>
                                    <td style="text-align: right; font-weight: 600;" id="invoiceBasePrice"><?= htmlspecialchars($formattedAmount) ?> <?= htmlspecialchars($currency) ?></td>
                                </tr>
                                <tr class="gst-row" style="display: none !important;">
                                    <td>Goods & Services Tax (GST) @ 18%</td>
                                    <td style="text-align: right;" id="invoiceGstPrice">0 <?= htmlspecialchars($currency) ?></td>
                                </tr>
                                <tr class="total-row">
                                    <td>Total Amount Due</td>
                                    <td style="text-align: right;" id="invoiceTotalPrice"><?= htmlspecialchars($formattedAmount) ?> <?= htmlspecialchars($currency) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="btn-container">
                    <button type="button" class="btn-register" style="margin-top: 0;" onclick="completePayment()">Pay
                        Now & Confirm</button>
                </div>
            </div>

            <!-- STEP 3: CONFIRMATION SUCCESS -->
            <div id="view-success" class="step-container">
                <div class="success-celebration">
                    <div class="success-icon-wrap">
                        ✓
                    </div>
                    <h3>Registration Completed Successfully!</h3>
                    <p>Your payment has been simulated and processed. Welcome to the conference!</p>

                    <div class="receipt-summary-box">
                        <div class="receipt-row">
                            <span>Transaction Reference</span>
                            <span id="finalTxnId"><?= htmlspecialchars($txn_id ?: 'Pending') ?></span>
                        </div>
                        <div class="receipt-row">
                            <span>Registrant</span>
                            <span id="finalName"><?= htmlspecialchars(trim(($fetched_user['first_name'] ?? '') . ' ' . ($fetched_user['middle_name'] ?? '') . ' ' . ($fetched_user['last_name'] ?? ''))) ?></span>
                        </div>
                        <div class="receipt-row">
                            <span>Registration Category</span>
                            <span id="finalType"><?= htmlspecialchars($fetched_user['participant_category'] ?? '') ?></span>
                        </div>
                        <div class="receipt-row">
                            <span>Country / Tier</span>
                            <span id="finalCountry"><?= htmlspecialchars($fetched_user['country_category'] ?? '') ?></span>
                        </div>
                        <div class="receipt-row">
                            <span>Paid Package</span>
                            <span id="finalRequirement"><?= htmlspecialchars($fetched_user['package'] ?? '') ?></span>
                        </div>
                        <div class="receipt-row">
                            <span>Total Paid</span>
                            <span id="finalPricePaid"><?= htmlspecialchars($formattedAmount) ?> <?= htmlspecialchars($currency) ?></span>
                        </div>
                    </div>

                    <a href="index.html" class="btn-register" style="margin-top: 0;">Return to Home</a>
                </div>
            </div>

            <!-- STEP 4: FAILURE STATE -->
            <div id="view-failed" class="step-container">
                <div class="success-celebration" style="border-top-color: #D32F2F;">
                    <div class="success-icon-wrap" style="background-color: #ffebee; color: #D32F2F;">
                        ✕
                    </div>
                    <h3 style="color: #D32F2F;">Payment Failed</h3>
                    <p>Your payment was declined or cancelled. Please try again.</p>

                    <a href="/view_transaction.php" class="btn-register" style="margin-top: 20px; background-color: #D32F2F;">Try Again</a>
                </div>
            </div>
        </section>

        <!--
        <section class="info-section">
            <h2 class="section-header">MORE INFORMATION:</h2>

            
            <div class="info-strip">
                <div class="info-header">
                    <span>Registration Confirmation</span>
                    <span class="icon">+</span>
                </div>
                <div class="info-content">
                    <div class="info-content-inner">
                        <p>Your completed registration and successful payment will be acknowledged via email. Your
                            registration will only be processed if payment has been received.</p>
                    </div>
                </div>
            </div>

            
            <div class="info-strip">
                <div class="info-header">
                    <span>Payment Options</span>
                    <span class="icon">+</span>
                </div>
                <div class="info-content">
                    <div class="info-content-inner">
                        <p>Please note all online registrations require immediate payment by credit card. Accepted
                            credit
                            cards are MasterCard, Visa and American Express. Please note all transactions by credit card
                            will appear on your statement as payment to ‘Forum Group Events'.</p>
                    </div>
                </div>
            </div>

            
            <div class="info-strip">
                <div class="info-header">
                    <span>Cancellation/Postponement</span>
                    <span class="icon">+</span>
                </div>
                <div class="info-content">
                    <div class="info-content-inner">
                        <p>All online bookings are non-cancellable, and any online Registration Fees paid are
                            non-refundable.</p>
                        <p>In-person registration cancellations will not be accepted unless made in writing to the
                            Conference Organisers at <a href="mailto:aasw@forumgroupevents.com.au"
                                style="color: #00779B; text-decoration: none;">aasw@forumgroupevents.com.au</a>.</p>

                        <table class="cancel-table">
                            <thead>
                                <tr>
                                    <th>Conditions</th>
                                    <th>Charges Applicable</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>On or before 30 June 2024</td>
                                    <td>100% refund less 10% administration fee</td>
                                </tr>
                                <tr>
                                    <td>On or before 4 November 2024</td>
                                    <td>50% refund less 10% administration fee</td>
                                </tr>
                                <tr>
                                    <td>After 4 November 2024</td>
                                    <td>No refund available</td>
                                </tr>
                                <tr>
                                    <td>No refund will be paid following failure to attend without notice</td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>

                        <p>In the unlikely event that we have to cancel the event, if, for example, delegate numbers are
                            too low to run the event, we will give you as much notice as possible, and you will be given
                            the option of a refund or transfer to an alternative event.</p>
                        <p>We are not liable to you for any non-attendance for any reason. If you are unable to attend
                            the event, event presentations will be available for on-demand viewing after the Conference.
                            Please consult the Event website or contact us for further details.</p>
                    </div>
                </div>
            </div>
        </section>
        -->
    
    </main>

    <main-footer></main-footer>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        // Fix back button cache issue
        window.addEventListener("pageshow", function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        const spinnerSvg = '<svg style="animation: spin 1s linear infinite; margin-right: 8px; width: 18px; height: 18px; display: inline-block; vertical-align: text-bottom;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity: 0.25;"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" style="opacity: 0.75;"></path></svg>';

        // Copy to clipboard helper
        function copyRegId(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert("Registration ID copied to clipboard!");
            });
        }

        // Global state for wizard data
        let wizardState = {
            registrationId: <?= json_encode($fetched_user['registration_id'] ?? '') ?>,
            firstName: <?= json_encode($fetched_user['first_name'] ?? '') ?>,
            middleName: <?= json_encode($fetched_user['middle_name'] ?? '') ?>,
            lastName: <?= json_encode($fetched_user['last_name'] ?? '') ?>,
            participantType: <?= json_encode($fetched_user['participant_category'] ?? '') ?>,
            countryCategory: <?= json_encode($fetched_user['country_category'] ?? '') ?>,
            requirementLabel: <?= json_encode($fetched_user['package'] ?? '') ?>,
            totalPrice: <?= json_encode((float)($fetched_user['base_amount'] ?? 0)) ?>,
            currency: (<?= json_encode($fetched_user['country_category'] ?? '') ?> === 'National (India)' || <?= json_encode($fetched_user['country_category'] ?? '') ?> === 'national') ? 'INR' : 'USD'
        };

        // Navigate between views
        function showView(viewId) {
            document.querySelectorAll('.step-container').forEach(container => {
                container.classList.remove('active');
            });
            document.getElementById(viewId).classList.add('active');
            document.querySelector('.registration-form-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Trigger Razorpay Payment Flow natively
        async function completePayment() {
            const phpRegId = '<?= htmlspecialchars($reg_id, ENT_QUOTES) ?>';
            const btn = document.querySelector('.btn-register');
            let originalBtnText = '';
            
            if (!phpRegId) {
                alert('Registration ID not found. Please try registering again.');
                return;
            }

            try {
                // Disable button
                if (btn) {
                    originalBtnText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = spinnerSvg + 'Processing...';
                }
                
                // 1. Create order
                const createRes = await fetch('razorpay/create_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ registration_id: phpRegId })
                });
                
                const orderData = await createRes.json();
                
                if (!orderData.success) {
                    throw new Error(orderData.error || 'Failed to create order');
                }

                // 2. Open Razorpay Checkout
                const options = {
                    key: orderData.key,
                    amount: orderData.amount,
                    currency: orderData.currency,
                    name: 'Checkout',
                    description: 'Payment',
                    order_id: orderData.order_id,
                    prefill: {
                        name: orderData.name,
                        email: orderData.email,
                        contact: orderData.phone
                    },
                    theme: {
                        color: '#0b74de'
                    },
                    modal: {
                        ondismiss: async function() {
                            if (btn) {
                                btn.disabled = false;
                                btn.innerHTML = originalBtnText;
                            }
                            try {
                                await fetch('razorpay/log_failure.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        registration_id: phpRegId,
                                        razorpay_order_id: orderData.order_id,
                                        razorpay_payment_id: '',
                                        error_description: 'User cancelled payment'
                                    })
                                });
                            } catch (e) {
                                console.error('Failed to log payment cancellation:', e);
                            }
                            // The user just closed the window, we don't necessarily want to force them to the fail screen
                            // so they can try again if they want, but if you want to show failure:
                            // handleGatewayResponseFinish('Failed', '');
                        }
                    },
                    handler: async function (response) {
                        // Show loading screen immediately while verifying payment
                        showView('view-loading');
                        
                        // 3. Verify Payment
                        try {
                            const verifyRes = await fetch('razorpay/verify_ajax.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    registration_id: phpRegId,
                                    razorpay_payment_id: response.razorpay_payment_id,
                                    razorpay_order_id: response.razorpay_order_id,
                                    razorpay_signature: response.razorpay_signature
                                })
                            });
                            
                            const verifyData = await verifyRes.json();
                            
                            // Artificial delay to show processing loader
                            setTimeout(() => {
                                if (verifyData.success) {
                                    handleGatewayResponseFinish('Success', response.razorpay_payment_id);
                                } else {
                                    alert(verifyData.error || 'Payment verification failed');
                                    handleGatewayResponseFinish('Failed', '');
                                }
                            }, 2500);
                        } catch (err) {
                            alert('An error occurred during verification.');
                            handleGatewayResponseFinish('Failed', '');
                        }
                    }
                };

                const rzp = new window.Razorpay(options);
                
                rzp.on('payment.failed', async function (response){
                    try {
                        await fetch('razorpay/log_failure.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                registration_id: phpRegId,
                                razorpay_order_id: response.error.metadata ? response.error.metadata.order_id : orderData.order_id,
                                razorpay_payment_id: response.error.metadata ? response.error.metadata.payment_id : '',
                                error_description: response.error.description
                            })
                        });
                    } catch (e) {
                        console.error('Failed to log payment failure:', e);
                    }
                    alert('Payment failed: ' + response.error.description);
                    handleGatewayResponseFinish('Failed', '');
                });
                
                rzp.open();

            } catch (err) {
                alert(err.message);
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalBtnText;
                }
            }
        }


        // Final UI cleanup based on payment outcome
        function handleGatewayResponseFinish(status, txnId) {
            const errorBox = document.getElementById('paymentErrorBox');

            if (status === 'Success') {
                wizardState.transactionId = txnId;

                // Populate Step 3 Success Receipt
                document.getElementById('finalTxnId').textContent = txnId;
                const fullName = wizardState.middleName ? `${wizardState.firstName} ${wizardState.middleName} ${wizardState.lastName}` : `${wizardState.firstName} ${wizardState.lastName}`;
                document.getElementById('finalName').textContent = fullName;
                document.getElementById('finalType').textContent = wizardState.participantType;
                document.getElementById('finalCountry').textContent = wizardState.countryCategory;
                document.getElementById('finalRequirement').textContent = wizardState.requirementLabel;

                const formatCost = (val) => {
                    if (typeof val === 'number') {
                        return val.toLocaleString('en-US') + ' ' + wizardState.currency;
                    }
                    // Fallback if it's already a string from PHP
                    return val + ' ' + wizardState.currency;
                };
                document.getElementById('finalPricePaid').textContent = formatCost(wizardState.totalPrice);

                // Hide any prior failure error box
                errorBox.classList.remove('active');

                // Smoothly move to Step 3
                showView('view-success');
            } else {
                // Show failure UI
                showView('view-failed');
            }
        }
    </script>
    
    <?php if ($fetched_user && $reg_status === 'success'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                handleGatewayResponseFinish('Success', <?= json_encode($txn_id) ?>);
            }, 800);
        });
    </script>
    <?php elseif ($fetched_user && $reg_status === 'failed'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                showView('view-failed');
            }, 800);
        });
    </script>
    <?php endif; ?>


</body>

</html>