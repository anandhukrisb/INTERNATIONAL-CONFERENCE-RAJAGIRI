<?php
// Handle redirect back from Razorpay
$reg_status = $_GET['status'] ?? '';
$reg_id = $_GET['reg_id'] ?? '';
$txn_id = $_GET['txn_id'] ?? '';

$fetched_user = null;
if ($reg_id !== '' && ($reg_status === 'success' || $reg_status === 'failed')) {
    try {
        require_once __DIR__ . '/backend/db.php';
        $stmt = $pdo->prepare("SELECT * FROM user_registrations WHERE registration_id = :reg_id LIMIT 1");
        $stmt->execute([':reg_id' => $reg_id]);
        $fetched_user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Ignore DB error, it will just load normally
    }
}
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
            border: 2px solid #A0AEC0;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            background-color: #f9f9fb;
            box-sizing: border-box;
        }

        .form-control::placeholder {
            color: #CBD5E0;
            opacity: 1; /* Firefox sets opacity < 1 by default */
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(29, 10, 63, 0.1);
            background-color: #fff;
        }

        #firstName, #middleName, #lastName {
            text-transform: capitalize;
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

            <!-- STEP 1: REGISTRATION -->
            <div id="step-1" class="step-container active">

                <!-- Initial Email Check Block -->
                <div id="initialEmailCheckContainer" class="verification-box" style="margin-bottom: 25px;">
                    <h4
                        style="font-family: 'Outfit', sans-serif; color: var(--primary-purple); margin-bottom: 12px; font-weight: 700;">
                        Start Registration</h4>
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 15px;">Please enter your email address to begin. We will check your abstract submission status.</p>
                    <div class="verification-row">
                        <div class="form-group" style="margin-bottom: 0; flex: 1;">
                            <label for="checkEmail" class="form-label">Email Address</label>
                            <input type="email" id="checkEmail" class="form-control"
                                placeholder="you@example.com">
                        </div>
                        <button type="button" class="btn-verify" id="btnCheckEmail"
                            onclick="checkInitialEmail()">Proceed</button>
                    </div>
                    <div id="initialCheckStatus" class="verification-status"></div>
                    
                    <!-- OTP Verification Container -->
                    <div id="otpContainer" style="display: none; margin-top: 15px; border-top: 1px dashed var(--accent-gold); padding-top: 15px;">
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 15px;">
                            Please enter the 6-digit OTP sent to your email.
                        </p>
                        
                        <div style="background-color: #EBF8FF; border-left: 4px solid #3182CE; padding: 12px 15px; margin-bottom: 20px; border-radius: 4px;">
                            <p style="margin: 0; color: #2B6CB0; font-size: 0.85rem; display: flex; align-items: flex-start; gap: 8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-top: 2px; flex-shrink: 0;"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>
                                <span><strong>Didn't receive the email?</strong><br>Kindly check your spam folder, or verify that the email address you entered is correct.</span>
                            </p>
                        </div>
                        <div class="verification-row">
                            <div class="form-group" style="margin-bottom: 0; flex: 1;">
                                <input type="text" id="otpInput" class="form-control" placeholder="XXXXXX" maxlength="6">
                            </div>
                            <button type="button" class="btn-verify" id="btnVerifyOTP" onclick="submitOTP()" style="background-color: var(--accent-gold); color: #1D0A3F;">Verify OTP</button>
                        </div>
                        <div id="otpStatus" class="verification-status"></div>
                        <div id="resendOtpContainer" style="margin-top: 10px; font-size: 0.85rem; text-align: right;">
                            <a href="#" onclick="resendOTP(); return false;" style="color: var(--primary-purple); font-weight: 600; text-decoration: underline;">Resend OTP</a>
                        </div>
                    </div>
                </div>

                <!-- Remaining Registration Form (Conditionally overlay locked until verified) -->
                <form id="registrationForm" onsubmit="submitStep1(event)" class="locked-form-overlay">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" id="firstName" name="firstName" class="form-control" placeholder="Alan"
                                required>
                        </div>
                        <div class="form-group">
                            <label for="middleName" class="form-label">Middle Name</label>
                            <input type="text" id="middleName" name="middleName" class="form-control" placeholder="J.">
                        </div>
                        <div class="form-group">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" id="lastName" name="lastName" class="form-control" placeholder="Wake"
                                required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="organization" class="form-label">Organization / Institution</label>
                            <input type="text" id="organization" name="organization" class="form-control"
                                placeholder="University / Institution Name" required>
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control"
                                placeholder="alan.wake@example.com" required>
                        </div>
                        <div class="form-group">
                            <label for="dob" class="form-label">Date of Birth</label>
                            <input type="date" id="dob" name="dob" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder="+91 98765 43210"
                                required>
                        </div>
                        <div class="form-group">
                            <label for="participantType" class="form-label">Participant Category (Who are you?)</label>
                            <select id="participantType" name="participantType" class="form-control"
                                onchange="updateRequirementsOptions()" required>
                                <option value="" disabled selected>Select Category</option>
                                <option value="general">Academics / Field Practitioners (General)</option>
                                <option value="phd">PhD Scholars</option>
                                <option value="student">Students</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="country" class="form-label">Country (Where are you from?)</label>
                            <select id="country" name="country" class="form-control"
                                onchange="handleCountryChange()" required>
                                <option value="" disabled selected>Select Country</option>
                                <option value="Afghanistan">Afghanistan</option>
                                <option value="Albania">Albania</option>
                                <option value="Algeria">Algeria</option>
                                <option value="Andorra">Andorra</option>
                                <option value="Angola">Angola</option>
                                <option value="Antigua and Barbuda">Antigua and Barbuda</option>
                                <option value="Argentina">Argentina</option>
                                <option value="Armenia">Armenia</option>
                                <option value="Australia">Australia</option>
                                <option value="Austria">Austria</option>
                                <option value="Azerbaijan">Azerbaijan</option>
                                <option value="Bahamas">Bahamas</option>
                                <option value="Bahrain">Bahrain</option>
                                <option value="Bangladesh">Bangladesh</option>
                                <option value="Barbados">Barbados</option>
                                <option value="Belarus">Belarus</option>
                                <option value="Belgium">Belgium</option>
                                <option value="Belize">Belize</option>
                                <option value="Benin">Benin</option>
                                <option value="Bhutan">Bhutan</option>
                                <option value="Bolivia">Bolivia</option>
                                <option value="Bosnia and Herzegovina">Bosnia and Herzegovina</option>
                                <option value="Botswana">Botswana</option>
                                <option value="Brazil">Brazil</option>
                                <option value="Brunei">Brunei</option>
                                <option value="Bulgaria">Bulgaria</option>
                                <option value="Burkina Faso">Burkina Faso</option>
                                <option value="Burundi">Burundi</option>
                                <option value="Cabo Verde">Cabo Verde</option>
                                <option value="Cambodia">Cambodia</option>
                                <option value="Cameroon">Cameroon</option>
                                <option value="Canada">Canada</option>
                                <option value="Central African Republic">Central African Republic</option>
                                <option value="Chad">Chad</option>
                                <option value="Chile">Chile</option>
                                <option value="China">China</option>
                                <option value="Colombia">Colombia</option>
                                <option value="Comoros">Comoros</option>
                                <option value="Congo">Congo</option>
                                <option value="Costa Rica">Costa Rica</option>
                                <option value="Croatia">Croatia</option>
                                <option value="Cuba">Cuba</option>
                                <option value="Cyprus">Cyprus</option>
                                <option value="Czech Republic">Czech Republic</option>
                                <option value="Democratic Republic of the Congo">Democratic Republic of the Congo</option>
                                <option value="Denmark">Denmark</option>
                                <option value="Djibouti">Djibouti</option>
                                <option value="Dominica">Dominica</option>
                                <option value="Dominican Republic">Dominican Republic</option>
                                <option value="Ecuador">Ecuador</option>
                                <option value="Egypt">Egypt</option>
                                <option value="El Salvador">El Salvador</option>
                                <option value="Equatorial Guinea">Equatorial Guinea</option>
                                <option value="Eritrea">Eritrea</option>
                                <option value="Estonia">Estonia</option>
                                <option value="Eswatini">Eswatini</option>
                                <option value="Ethiopia">Ethiopia</option>
                                <option value="Fiji">Fiji</option>
                                <option value="Finland">Finland</option>
                                <option value="France">France</option>
                                <option value="Gabon">Gabon</option>
                                <option value="Gambia">Gambia</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Germany">Germany</option>
                                <option value="Ghana">Ghana</option>
                                <option value="Greece">Greece</option>
                                <option value="Grenada">Grenada</option>
                                <option value="Guatemala">Guatemala</option>
                                <option value="Guinea">Guinea</option>
                                <option value="Guyana">Guyana</option>
                                <option value="Haiti">Haiti</option>
                                <option value="Honduras">Honduras</option>
                                <option value="Hungary">Hungary</option>
                                <option value="Iceland">Iceland</option>
                                <option value="India">India</option>
                                <option value="Indonesia">Indonesia</option>
                                <option value="Iran">Iran</option>
                                <option value="Iraq">Iraq</option>
                                <option value="Ireland">Ireland</option>
                                <option value="Israel">Israel</option>
                                <option value="Italy">Italy</option>
                                <option value="Jamaica">Jamaica</option>
                                <option value="Japan">Japan</option>
                                <option value="Jordan">Jordan</option>
                                <option value="Kazakhstan">Kazakhstan</option>
                                <option value="Kenya">Kenya</option>
                                <option value="Kiribati">Kiribati</option>
                                <option value="Kuwait">Kuwait</option>
                                <option value="Kyrgyzstan">Kyrgyzstan</option>
                                <option value="Laos">Laos</option>
                                <option value="Latvia">Latvia</option>
                                <option value="Lebanon">Lebanon</option>
                                <option value="Lesotho">Lesotho</option>
                                <option value="Liberia">Liberia</option>
                                <option value="Libya">Libya</option>
                                <option value="Liechtenstein">Liechtenstein</option>
                                <option value="Lithuania">Lithuania</option>
                                <option value="Luxembourg">Luxembourg</option>
                                <option value="Madagascar">Madagascar</option>
                                <option value="Malawi">Malawi</option>
                                <option value="Malaysia">Malaysia</option>
                                <option value="Maldives">Maldives</option>
                                <option value="Mali">Mali</option>
                                <option value="Malta">Malta</option>
                                <option value="Marshall Islands">Marshall Islands</option>
                                <option value="Mauritania">Mauritania</option>
                                <option value="Mauritius">Mauritius</option>
                                <option value="Mexico">Mexico</option>
                                <option value="Micronesia">Micronesia</option>
                                <option value="Moldova">Moldova</option>
                                <option value="Monaco">Monaco</option>
                                <option value="Mongolia">Mongolia</option>
                                <option value="Montenegro">Montenegro</option>
                                <option value="Morocco">Morocco</option>
                                <option value="Mozambique">Mozambique</option>
                                <option value="Myanmar">Myanmar</option>
                                <option value="Namibia">Namibia</option>
                                <option value="Nauru">Nauru</option>
                                <option value="Nepal">Nepal</option>
                                <option value="Netherlands">Netherlands</option>
                                <option value="New Zealand">New Zealand</option>
                                <option value="Nicaragua">Nicaragua</option>
                                <option value="Niger">Niger</option>
                                <option value="Nigeria">Nigeria</option>
                                <option value="North Korea">North Korea</option>
                                <option value="North Macedonia">North Macedonia</option>
                                <option value="Norway">Norway</option>
                                <option value="Oman">Oman</option>
                                <option value="Pakistan">Pakistan</option>
                                <option value="Palau">Palau</option>
                                <option value="Palestine">Palestine</option>
                                <option value="Panama">Panama</option>
                                <option value="Papua New Guinea">Papua New Guinea</option>
                                <option value="Paraguay">Paraguay</option>
                                <option value="Peru">Peru</option>
                                <option value="Philippines">Philippines</option>
                                <option value="Poland">Poland</option>
                                <option value="Portugal">Portugal</option>
                                <option value="Qatar">Qatar</option>
                                <option value="Romania">Romania</option>
                                <option value="Russia">Russia</option>
                                <option value="Rwanda">Rwanda</option>
                                <option value="Saint Kitts and Nevis">Saint Kitts and Nevis</option>
                                <option value="Saint Lucia">Saint Lucia</option>
                                <option value="Samoa">Samoa</option>
                                <option value="San Marino">San Marino</option>
                                <option value="Sao Tome and Principe">Sao Tome and Principe</option>
                                <option value="Saudi Arabia">Saudi Arabia</option>
                                <option value="Senegal">Senegal</option>
                                <option value="Serbia">Serbia</option>
                                <option value="Seychelles">Seychelles</option>
                                <option value="Sierra Leone">Sierra Leone</option>
                                <option value="Singapore">Singapore</option>
                                <option value="Slovakia">Slovakia</option>
                                <option value="Slovenia">Slovenia</option>
                                <option value="Solomon Islands">Solomon Islands</option>
                                <option value="Somalia">Somalia</option>
                                <option value="South Africa">South Africa</option>
                                <option value="South Korea">South Korea</option>
                                <option value="South Sudan">South Sudan</option>
                                <option value="Spain">Spain</option>
                                <option value="Sri Lanka">Sri Lanka</option>
                                <option value="Sudan">Sudan</option>
                                <option value="Suriname">Suriname</option>
                                <option value="Sweden">Sweden</option>
                                <option value="Switzerland">Switzerland</option>
                                <option value="Syria">Syria</option>
                                <option value="Taiwan">Taiwan</option>
                                <option value="Tajikistan">Tajikistan</option>
                                <option value="Tanzania">Tanzania</option>
                                <option value="Thailand">Thailand</option>
                                <option value="Timor-Leste">Timor-Leste</option>
                                <option value="Togo">Togo</option>
                                <option value="Tonga">Tonga</option>
                                <option value="Trinidad and Tobago">Trinidad and Tobago</option>
                                <option value="Tunisia">Tunisia</option>
                                <option value="Turkey">Turkey</option>
                                <option value="Turkmenistan">Turkmenistan</option>
                                <option value="Tuvalu">Tuvalu</option>
                                <option value="Uganda">Uganda</option>
                                <option value="Ukraine">Ukraine</option>
                                <option value="United Arab Emirates">United Arab Emirates</option>
                                <option value="United Kingdom">United Kingdom</option>
                                <option value="United States">United States</option>
                                <option value="Uruguay">Uruguay</option>
                                <option value="Uzbekistan">Uzbekistan</option>
                                <option value="Vanuatu">Vanuatu</option>
                                <option value="Vatican City">Vatican City</option>
                                <option value="Venezuela">Venezuela</option>
                                <option value="Vietnam">Vietnam</option>
                                <option value="Yemen">Yemen</option>
                                <option value="Zambia">Zambia</option>
                                <option value="Zimbabwe">Zimbabwe</option>
                            </select>
                            <input type="hidden" id="countryCategory" name="countryCategory" value="">
                        </div>
                        <div class="form-group">
                            <label for="requirement" class="form-label">Required Package (What requirement do you
                                need?)</label>
                            <select id="requirement" name="requirement" class="form-control" required>
                                <option value="" disabled selected>Select Package</option>
                                <!-- Populated dynamically based on participant category -->
                            </select>
                        </div>
                    </div>


                    <div class="checkbox-group" style="margin-bottom: 30px;">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms" class="checkbox-label">I agree to the <a href="payment-policy.html"
                                target="_blank" style="color: var(--primary-purple); font-weight: 600;">Terms &
                                Conditions</a> and Payment Policy.</label>
                    </div>

                    <div style="text-align: center;">
                        <button type="submit" class="btn-register">Proceed to Payment Details</button>
                    </div>
                </form>
            </div>

            <!-- STEP 2: REVIEW & PAYMENT -->
            
    </main>

    <main-footer></main-footer>

    <script>
        function toggleAccordion(icon) {
            const strip = icon.closest('.info-strip');
            const content = strip.querySelector('.info-content');
            const isExpanded = strip.classList.contains('expanded');

            if (isExpanded) {
                // Close
                content.style.maxHeight = content.scrollHeight + "px";
                content.offsetHeight; // force reflow
                content.style.maxHeight = "0";
                strip.classList.remove('expanded');
                icon.textContent = "+";
            } else {
                // Open
                strip.classList.add('expanded');
                content.style.maxHeight = content.scrollHeight + "px";
                icon.textContent = "−";

                content.addEventListener("transitionend", function handler() {
                    if (strip.classList.contains("expanded")) {
                        content.style.maxHeight = "none";
                    }
                    content.removeEventListener("transitionend", handler);
                });
            }
        }

        document.querySelectorAll('.info-header').forEach(header => {
            header.addEventListener('click', () => toggleAccordion(header));

            // Set initial state for expanded sections
            const strip = header.parentElement;
            if (strip.classList.contains('expanded')) {
                const content = strip.querySelector('.info-content');
                content.style.maxHeight = 'none';
                content.style.opacity = '1';
            }
        });
    </script>

    <script>
        const feeData = {
            general: {
                developed: [
                    { label: "Early Bird", value: "350 USD" },
                    { label: "Regular", value: "400 USD" },
                    { label: "Spot Registration", value: "500 USD" },
                    { label: "Online (Recorded/Live Stream main)", value: "250 USD" }
                ],
                developing: [
                    { label: "Early Bird", value: "220 USD" },
                    { label: "Regular", value: "250 USD" },
                    { label: "Spot Registration", value: "300 USD" },
                    { label: "Online (Recorded/Live Stream main)", value: "175 USD" }
                ],
                national: [
                    { label: "Early Bird", value: "3,000 INR" },
                    { label: "Regular", value: "3,500 INR" },
                    { label: "Spot Registration", value: "4,500 INR" },
                    { label: "Online (Recorded/Live Stream main)", value: "2,000 INR" }
                ]
            },
            phd: {
                developed: [
                    { label: "In-Person (Offline)", value: "150 USD" },
                    { label: "Online (Recorded)", value: "100 USD" }
                ],
                developing: [
                    { label: "In-Person (Offline)", value: "100 USD" },
                    { label: "Online (Recorded)", value: "75 USD" }
                ],
                national: [
                    { label: "In-Person (Offline)", value: "1,200 INR" },
                    { label: "Online (Recorded)", value: "600 INR" }
                ]
            },
            student: {
                developed: [
                    { label: "In-Person (Offline)", value: "100 USD" },
                    { label: "Online (Recorded)", value: "50 USD" }
                ],
                developing: [
                    { label: "In-Person (Offline)", value: "75 USD" },
                    { label: "Online (Recorded)", value: "40 USD" }
                ],
                national: [
                    // { label: "In-Person (Offline)", value: "1,000 INR" },
                    // { label: "Online (Recorded)", value: "500 INR" }
                    { label: "In-Person (Offline)", value: "1 INR" },
                    { label: "Online (Recorded)", value: "1 INR" }
                ]
            }
        };

        function showFeeTab(tab) {
            document.querySelectorAll('.fee-toggle-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.fee-tab-content').forEach(content => content.classList.remove('active'));

            if (tab === 'all') {
                document.getElementById('btn-view-all').classList.add('active');
                document.getElementById('fee-view-all').classList.add('active');
            } else {
                document.getElementById('btn-filter').classList.add('active');
                document.getElementById('fee-view-filter').classList.add('active');
                updateFeeFilter();
            }
        }

        function updateFeeFilter() {
            const participant = document.getElementById('filter-participant').value;
            const category = document.getElementById('filter-category').value;
            const resultContainer = document.getElementById('filtered-fee-result');

            const data = feeData[participant][category];
            let html = `<h4 style="margin-bottom: 15px; color: var(--primary-purple); font-family: 'Outfit', sans-serif;">Your Applicable Fees</h4>`;

            data.forEach(item => {
                html += `
                    <div class="result-card">
                        <div class="result-label">${item.label}</div>
                        <div class="result-value">${item.value}</div>
                    </div>
                `;
            });

            resultContainer.innerHTML = html;
        }

        // Initialize filter on load if needed
        document.addEventListener('DOMContentLoaded', () => {
            updateFeeFilter();
        });
    </script>

    <!-- Multi-Step Wizard and Price Calculations Logic -->
    <script>
        // Fix back button cache issue
        window.addEventListener("pageshow", function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        const spinnerSvg = '<svg style="animation: spin 1s linear infinite; margin-right: 8px; width: 18px; height: 18px; display: inline-block; vertical-align: text-bottom;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity: 0.25;"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" style="opacity: 0.75;"></path></svg>';

        // Global state for wizard data
        let wizardState = {
            abstractSubmitted: '',
            isVerified: false,
            firstName: '',
            middleName: '',
            lastName: '',
            email: '',
            organization: '',
            phone: '',
            dob: '',
            participantType: '',
            country: '',
            countryCategory: '',
            requirementIndex: -1,
            requirementLabel: '',
            requirementCostText: '',
            currency: 'USD',
            basePrice: 0,
            gstPrice: 0,
            totalPrice: 0,
            transactionId: ''
        };

        const DEVELOPED_COUNTRIES = [
            "Andorra", "Australia", "Austria", "Belgium", "Canada", "Cyprus", 
            "Czech Republic", "Denmark", "Estonia", "Finland", "France", 
            "Germany", "Greece", "Hong Kong", "Iceland", "Ireland", "Israel", 
            "Italy", "Japan", "Latvia", "Liechtenstein", "Lithuania", 
            "Luxembourg", "Malta", "Monaco", "Netherlands", "New Zealand", 
            "Norway", "Portugal", "San Marino", "Singapore", "Slovakia", 
            "Slovenia", "South Korea", "Spain", "Sweden", "Switzerland", 
            "Taiwan", "United Kingdom", "United States", "Vatican City"
        ];

        // Handles Country selection to determine tier (Developed, Developing, National)
        function handleCountryChange() {
            const countrySelect = document.getElementById('country');
            const countryVal = countrySelect.value;
            const cCatInput = document.getElementById('countryCategory');

            if (countryVal === 'India') {
                cCatInput.value = 'national';
            } else if (DEVELOPED_COUNTRIES.includes(countryVal)) {
                cCatInput.value = 'developed';
            } else {
                cCatInput.value = 'developing';
            }

            // Trigger dynamic fee options update
            updateRequirementsOptions();
        }

        // Initial Email Verification
        async function checkInitialEmail() {
            const emailVal = document.getElementById('checkEmail').value.trim().toLowerCase();
            const statusDiv = document.getElementById('initialCheckStatus');
            const btn = document.getElementById('btnCheckEmail');
            const form = document.getElementById('registrationForm');

            if (!emailVal) {
                statusDiv.innerHTML = '<span style="color: #C53030;">⚠ Please enter an email address.</span>';
                statusDiv.className = 'verification-status error';
                statusDiv.style.display = 'flex';
                return;
            }

            btn.disabled = true;
            const originalBtnText = btn.innerHTML;
            btn.innerHTML = spinnerSvg + 'Checking...';
            statusDiv.innerHTML = 'Checking database...';
            statusDiv.className = 'verification-status';
            statusDiv.style.display = 'flex';
            statusDiv.style.color = 'var(--text-muted)';

            try {
                const res = await fetch(`backend/check_email.php?email=${encodeURIComponent(emailVal)}`);
                const data = await res.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Server error occurred while checking email.');
                }
                
                if (data.is_registered) {
                    const statusText = data.payment_status || 'Pending';
                    
                    let statusColor = '#D69E2E'; // Default (e.g., Pending -> Orange)
                    let buttonText = 'Check Details / Pay Again &rarr;';
                    
                    if (statusText === 'Completed') {
                        statusColor = '#2F855A'; // Green
                        buttonText = 'Check Registration Details &rarr;';
                    } else if (statusText === 'Failed' || statusText.toLowerCase() === 'not completed') {
                        statusColor = '#C53030'; // Red
                    }
                    
                    statusDiv.innerHTML = `
                        <div style="display: flex; flex-direction: column; gap: 10px; width: 100%;">
                            <div style="color: #C53030; font-weight: 600;">
                                ⚠ This email is already registered.
                            </div>
                            <div style="background: #F7FAFC; padding: 15px; border-radius: 8px; border: 1px solid #E2E8F0; text-align: left;">
                                <div style="margin-bottom: 8px; color: #4A5568;">Payment Status: <strong style="color: ${statusColor}; text-transform: uppercase;">${statusText}</strong></div>
                                <div style="font-size: 0.9rem; color: #718096; margin-bottom: 15px;">You can view your transaction history or complete pending payments from the Check Status page.</div>
                                <a href="view_transaction.php" style="display: inline-block; background-color: var(--primary-purple); color: white; padding: 10px 20px; border-radius: 50px; text-decoration: none; font-size: 0.9rem; font-family: 'Outfit', sans-serif; font-weight: 600; text-align: center; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='var(--accent-gold)'; this.style.color='var(--primary-purple)';" onmouseout="this.style.backgroundColor='var(--primary-purple)'; this.style.color='white';">${buttonText}</a>
                            </div>
                        </div>
                    `;
                    statusDiv.className = 'verification-status'; 
                    statusDiv.style.display = 'block';
                    
                    btn.disabled = false;
                    btn.innerHTML = 'Check Another';
                    form.classList.add('locked-form-overlay');
                    form.classList.remove('unlocked-form');
                    return;
                }

                // If not registered, trigger OTP send
                statusDiv.innerHTML = 'Sending OTP to your email...';
                const otpRes = await fetch('backend/send_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: emailVal })
                });
                const otpData = await otpRes.json();

                if (!otpData.success) {
                    throw new Error(otpData.error || 'Failed to send OTP.');
                }

                // Show OTP input container
                wizardState.pendingEmail = emailVal;
                wizardState.pendingAbstract = data.has_abstract ? 'yes' : 'no';
                
                document.getElementById('checkEmail').readOnly = true;
                btn.style.display = 'none'; // Hide proceed button
                
                statusDiv.innerHTML = `
                    <span class="verified-badge" style="background-color: #EBF8FF; color: #2B6CB0;">✓ OTP Sent Successfully</span>
                `;
                statusDiv.className = 'verification-status success';
                statusDiv.style.display = 'block';
                
                document.getElementById('otpContainer').style.display = 'block';
                btn.innerHTML = 'Verified';

            } catch (error) {
                statusDiv.innerHTML = '<span style="color: #C53030;">⚠ Error checking email. Please try again later.</span>';
                statusDiv.className = 'verification-status error';
                btn.disabled = false;
                btn.innerHTML = originalBtnText;
                console.error(error);
            }
        }

        async function submitOTP() {
            const otpVal = document.getElementById('otpInput').value.trim();
            const otpStatusDiv = document.getElementById('otpStatus');
            const btnVerifyOTP = document.getElementById('btnVerifyOTP');
            
            if (!otpVal || otpVal.length < 6) {
                otpStatusDiv.innerHTML = '<span style="color: #C53030;">⚠ Please enter a valid 6-digit OTP.</span>';
                otpStatusDiv.className = 'verification-status error';
                otpStatusDiv.style.display = 'flex';
                return;
            }

            const originalBtnText = btnVerifyOTP.innerHTML;
            btnVerifyOTP.disabled = true;
            btnVerifyOTP.innerHTML = spinnerSvg + 'Verifying...';
            otpStatusDiv.style.display = 'none';

            try {
                const res = await fetch('backend/verify_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: wizardState.pendingEmail, otp: otpVal })
                });
                const data = await res.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Invalid OTP.');
                }

                // OTP Verified! Unlock form
                wizardState.isVerified = true;
                wizardState.email = wizardState.pendingEmail;
                wizardState.abstractSubmitted = wizardState.pendingAbstract;

                otpStatusDiv.innerHTML = `
                    <span class="verified-badge">✓ Email Verified</span>
                    <span style="color: #2F855A; margin-left: 10px;">You can now proceed.</span>
                `;
                otpStatusDiv.className = 'verification-status success';
                otpStatusDiv.style.color = '#2F855A';
                otpStatusDiv.style.display = 'block';

                btnVerifyOTP.innerHTML = 'Verified';
                btnVerifyOTP.style.backgroundColor = '#CBD5E0';
                btnVerifyOTP.style.color = '#718096';
                document.getElementById('otpInput').readOnly = true;
                document.getElementById('resendOtpContainer').style.display = 'none';

                // Populate main form email and make readonly
                const mainEmail = document.getElementById('email');
                mainEmail.value = wizardState.email;
                mainEmail.readOnly = true;

                // Unlock registration form
                const form = document.getElementById('registrationForm');
                form.classList.remove('locked-form-overlay');
                form.classList.add('unlocked-form');
                
            } catch (error) {
                btnVerifyOTP.disabled = false;
                btnVerifyOTP.innerHTML = originalBtnText;
                otpStatusDiv.innerHTML = `<span style="color: #C53030;">⚠ ${error.message}</span>`;
                otpStatusDiv.className = 'verification-status error';
                otpStatusDiv.style.display = 'flex';
            }
        }

        async function resendOTP() {
            const otpStatusDiv = document.getElementById('otpStatus');
            otpStatusDiv.innerHTML = 'Resending OTP...';
            otpStatusDiv.className = 'verification-status';
            otpStatusDiv.style.display = 'block';
            otpStatusDiv.style.color = 'var(--text-muted)';
            
            try {
                const otpRes = await fetch('backend/send_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: wizardState.pendingEmail })
                });
                const otpData = await otpRes.json();

                if (!otpData.success) {
                    throw new Error(otpData.error || 'Failed to resend OTP.');
                }
                otpStatusDiv.innerHTML = `<span style="color: #2B6CB0;">✓ A new OTP was sent to your email.</span>`;
            } catch (error) {
                otpStatusDiv.innerHTML = `<span style="color: #C53030;">⚠ ${error.message}</span>`;
                otpStatusDiv.className = 'verification-status error';
            }
        }

        // Dynamically updates package options dropdown based on Who they are & Where they are fromType
        function updateRequirementsOptions() {
            const pType = document.getElementById('participantType').value;
            const cCat = document.getElementById('countryCategory').value;
            const reqSelect = document.getElementById('requirement');

            // Clear options
            reqSelect.innerHTML = '<option value="" disabled selected>Select Package</option>';

            if (!pType || !cCat) return;

            // Fetch from the pre-existing feeData object from registration.html
            const data = feeData[pType][cCat];

            if (data && data.length > 0) {
                data.forEach((item, index) => {
                    const opt = document.createElement('option');
                    opt.value = index;
                    opt.textContent = `${item.label} (${item.value})`;
                    reqSelect.appendChild(opt);
                });
            }
        }

        // Navigate between steps
        function goToStep(stepNumber) {
            // Hide all steps
            document.querySelectorAll('.step-container').forEach(container => {
                container.classList.remove('active');
            });

            // Show active step
            document.getElementById(`step-${stepNumber}`).classList.add('active');

            // Update progress track
            const line = document.getElementById('progressLine');
            const steps = document.querySelectorAll('.progress-step');

            if (line) {
                steps.forEach(s => {
                    const sNum = parseInt(s.dataset.step);
                    if (sNum < stepNumber) {
                        s.classList.add('completed');
                        s.classList.remove('active');
                    } else if (sNum === stepNumber) {
                        s.classList.add('active');
                        s.classList.remove('completed');
                    } else {
                        s.classList.remove('active', 'completed');
                    }
                });
                // Slide progress bar width
                line.style.width = ((stepNumber - 1) * 50) + '%';
            }

            // Scroll smoothly to form section top
            document.querySelector('.registration-form-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Submission for Step 1
        async function submitStep1(event) {
            event.preventDefault();

            // Read abstract submitted status from global state
            const status = wizardState.abstractSubmitted || 'no';

            const capitalize = (s) => s.replace(/\b\w/g, c => c.toUpperCase());
            const firstName = capitalize(document.getElementById('firstName').value.trim());
            const middleName = capitalize(document.getElementById('middleName').value.trim());
            const lastName = capitalize(document.getElementById('lastName').value.trim());
            const email = document.getElementById('email').value.trim();
            const organization = document.getElementById('organization').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const dob = document.getElementById('dob').value.trim();
            const pTypeSelect = document.getElementById('participantType');
            const countrySelect = document.getElementById('country');
            const cCatInput = document.getElementById('countryCategory');
            const reqSelect = document.getElementById('requirement');

            const pType = pTypeSelect.value;
            const countryVal = countrySelect.value;
            const cCat = cCatInput.value;
            const reqIdx = parseInt(reqSelect.value);

            if (!firstName || !lastName || !email || !organization || !phone || !dob || !pType || !countryVal || !cCat || isNaN(reqIdx)) {
                alert('Please fill out all required fields before proceeding.');
                return;
            }

            // Show checking status on submit button
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = spinnerSvg + 'Checking...';

            // (Email check removed from here since it happens before unlocking the form)
            
            // Restore button text before the rest of the flow modifies it again
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;

            // Pre-existing feeData retrieval
            const selectedPackageData = feeData[pType][cCat][reqIdx];

            // Save state
            wizardState.firstName = firstName;
            wizardState.middleName = middleName;
            wizardState.lastName = lastName;
            wizardState.email = email;
            wizardState.organization = organization;
            wizardState.phone = phone;
            wizardState.dob = dob;
            wizardState.participantType = pTypeSelect.options[pTypeSelect.selectedIndex].text;
            wizardState.country = countryVal;
            
            // Format countryCategory readable string
            wizardState.countryCategory = cCat === 'developed' ? 'Developed Countries' : (cCat === 'national' ? 'National (India)' : 'Developing Countries');
            
            wizardState.requirementIndex = reqIdx;
            wizardState.requirementLabel = selectedPackageData.label;
            wizardState.requirementCostText = selectedPackageData.value;

            // Price analysis
            const rawCost = selectedPackageData.value; // e.g. "350 USD" or "3,000 INR"
            const numPart = rawCost.replace(/[^0-9]/g, '');
            const baseAmount = parseInt(numPart);
            const isINR = rawCost.includes('INR');

            wizardState.currency = isINR ? 'INR' : 'USD';
            wizardState.basePrice = baseAmount;

            // Taxes removed — only show and save the real rate
            wizardState.gstPrice = 0;
            wizardState.totalPrice = baseAmount;

            // Show saving status on submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = spinnerSvg + 'Saving details...';

            // Hide any previous payment error (element moved to step 2)
            // document.getElementById('paymentErrorBox').classList.remove('active');

            // Construct payload for database
            const payload = {
                firstName: wizardState.firstName,
                middleName: wizardState.middleName,
                lastName: wizardState.lastName,
                email: wizardState.email,
                organization: wizardState.organization,
                phone: wizardState.phone,
                dob: wizardState.dob,
                participantType: wizardState.participantType,
                country: wizardState.country,
                countryCategory: wizardState.countryCategory,
                requiredPackage: `${wizardState.requirementLabel} (${rawCost})`,
                abstractSubmitted: status,
                abstractEmail: status === 'yes' ? document.getElementById('verifyEmail').value.trim() : '',
                baseAmount: wizardState.basePrice,
                paymentStatus: 'Not Completed'
            };

            // Call backend endpoint to save registrant details to the database with status 'Not Completed'
            fetch('save_registration.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(async response => {
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    throw new Error('Server returned non-JSON error: ' + response.status);
                }
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Server returned an error status: ' + response.status);
                }
                return data;
            })
            .then(data => {
                if (data.registration_id) {
                    window.location.href = 'process_payment.php?reg_id=' + data.registration_id;
                } else {
                    throw new Error('Failed to save registration details.');
                }
            })
            .catch(error => {
                console.error('Registration Error:', error.message);
                alert('An error occurred while saving your registration. Please try again later.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        }

        // Proceed to Step 2 after ID is retrieved or generated
        function proceedToStep2(registrationId, rawCost) {
            wizardState.registrationId = registrationId;

            // Populate Step 2 Review Fields
            const fullName = wizardState.middleName ? `${wizardState.firstName} ${wizardState.middleName} ${wizardState.lastName}` : `${wizardState.firstName} ${wizardState.lastName}`;
            document.getElementById('reviewName').textContent = fullName;
            document.getElementById('reviewEmail').textContent = wizardState.email;
            document.getElementById('reviewOrganization').textContent = wizardState.organization;
            document.getElementById('reviewPhone').textContent = wizardState.phone;
            document.getElementById('reviewType').textContent = wizardState.participantType;
            document.getElementById('reviewCountry').textContent = wizardState.countryCategory;
            document.getElementById('reviewRequirement').textContent = `${wizardState.requirementLabel} (${rawCost})`;

            // Populate Invoice Reference with registration ID
            document.getElementById('invoiceReference').textContent = 'Ref: ' + registrationId;

            // Invoice table populating
            const desc = `${wizardState.participantType} Package: ${wizardState.requirementLabel} Access`;
            document.getElementById('invoiceItemDesc').textContent = desc;

            const formatCost = (val) => {
                return val.toLocaleString('en-US') + ' ' + wizardState.currency;
            };

            document.getElementById('invoiceBasePrice').textContent = formatCost(wizardState.basePrice);
            document.getElementById('invoiceGstPrice').textContent = formatCost(wizardState.gstPrice);
            document.getElementById('invoiceTotalPrice').textContent = formatCost(wizardState.totalPrice);

            // Navigate to Step 2
            goToStep(2);
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
                goToStep(3);
            } else {
                // Show failure UI
                goToStep(4);
            }
        }
    </script>
    
    <?php if ($fetched_user && $reg_status === 'success'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Reconstruct minimal wizardState for Step 3 rendering
            wizardState.registrationId = <?= json_encode($fetched_user['registration_id']) ?>;
            wizardState.firstName = <?= json_encode($fetched_user['first_name']) ?>;
            wizardState.middleName = <?= json_encode($fetched_user['middle_name']) ?>;
            wizardState.lastName = <?= json_encode($fetched_user['last_name']) ?>;
            wizardState.participantType = <?= json_encode($fetched_user['participant_category']) ?>;
            wizardState.countryCategory = <?= json_encode($fetched_user['country_category']) ?>;
            wizardState.requirementLabel = <?= json_encode($fetched_user['package']) ?>;
            wizardState.totalPrice = <?= json_encode($fetched_user['base_amount']) ?>;
            
            // Determine currency
            const cCat = wizardState.countryCategory;
            wizardState.currency = (cCat === 'National (India)' || cCat === 'national') ? 'INR' : 'USD';

            handleGatewayResponseFinish('Success', <?= json_encode($txn_id) ?>);
        });
    </script>
    <?php elseif ($fetched_user && $reg_status === 'failed'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            goToStep(4);
        });
    </script>
    <?php endif; ?>


</body>

</html>