<?php
/**
 * preview_process_payment.php
 * Standalone Client Demo & Preview Mode
 * ZERO Database Connections - Safe for testing and client review.
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Review Preview (Demo Mode) - ICSWHMH 2027</title>
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

        /* Demo Mode Top Banner */
        .demo-mode-banner {
            background: linear-gradient(90deg, #1D0A3F, #311368);
            color: #C9A227;
            padding: 12px 20px;
            text-align: center;
            font-size: 0.95rem;
            font-weight: 600;
            border-bottom: 2px solid #C9A227;
            position: sticky;
            top: 0;
            z-index: 99999;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .demo-mode-badge {
            background: #C9A227;
            color: #1D0A3F;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .demo-link-btn {
            background: rgba(201, 162, 39, 0.2);
            color: #fff;
            border: 1px solid #C9A227;
            padding: 4px 12px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.2s ease;
        }

        .demo-link-btn:hover {
            background: #C9A227;
            color: #1D0A3F;
        }

        /* ================= TITLE SECTION ================= */
        .title-section {
            margin-top: 140px;
            margin-bottom: 40px;
        }

        #pageTitle {
            font-family: 'Outfit', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 2.2rem;
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

        /* ================= WIZARD PROGRESS BAR ================= */
        .wizard-progress {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 600px;
            margin: 0 auto 50px auto;
            position: relative;
        }

        .progress-line-track {
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            height: 4px;
            width: 100%;
            background-color: #E0E0E0;
            z-index: 1;
        }

        .progress-line-fill {
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            height: 4px;
            width: 50%;
            background-color: var(--accent-gold);
            z-index: 2;
            transition: width 0.4s ease;
        }

        .progress-step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--white);
            border: 3px solid #E0E0E0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #999;
            z-index: 3;
            transition: all 0.3s ease;
            position: relative;
            font-size: 0.95rem;
        }

        .progress-step.completed {
            border-color: var(--accent-gold);
            background-color: var(--accent-gold);
            color: var(--white);
        }

        .progress-step.active {
            border-color: var(--primary-purple);
            color: var(--primary-purple);
            transform: scale(1.1);
            box-shadow: 0 0 10px rgba(29, 10, 63, 0.2);
        }

        .progress-step::after {
            content: attr(data-label);
            position: absolute;
            top: 48px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.8rem;
            font-weight: 600;
            color: #888;
            white-space: nowrap;
        }

        .progress-step.active::after {
            color: var(--primary-purple);
            font-weight: 700;
        }

        .progress-step.completed::after {
            color: var(--accent-gold);
        }

        /* ================= STEP CONTAINERS ================= */
        .step-container {
            display: none;
            animation: fadeIn 0.4s ease forwards;
        }

        .step-container.active {
            display: block;
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

        /* ================= REVIEW RECEIPT & INVOICE ================= */
        .invoice-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            max-width: 900px;
            margin: 0 auto 40px auto;
            border: 1px solid #eaeaea;
            overflow: hidden;
        }

        .invoice-header {
            background: linear-gradient(135deg, var(--primary-purple), #2d1060);
            color: #fff;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 4px solid var(--accent-gold);
            flex-wrap: wrap;
            gap: 15px;
        }

        .invoice-header h4 {
            margin: 0;
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            letter-spacing: 0.5px;
            color: #fff;
        }

        .invoice-header .ref-code {
            font-size: 0.95rem;
            opacity: 0.95;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.15);
            padding: 6px 14px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .invoice-body {
            padding: 35px 40px;
        }

        .receipt-section-title {
            font-size: 1.15rem;
            color: var(--primary-purple);
            font-weight: 700;
            margin-bottom: 20px;
            margin-top: 10px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .receipt-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px 24px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .receipt-grid {
                grid-template-columns: 1fr;
            }
            .invoice-body {
                padding: 25px 20px;
            }
        }

        .receipt-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .receipt-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #777;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .receipt-value {
            font-size: 1.02rem;
            font-weight: 600;
            color: #2D3436;
        }

        .badge-passport {
            background-color: #e3f2fd;
            color: #0d47a1;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-family: monospace;
            font-weight: 700;
            display: inline-block;
            margin-top: 3px;
        }

        .badge-pill {
            background: #f0f4f8;
            color: var(--primary-purple);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            width: fit-content;
        }

        /* Invoice Table */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 20px;
        }

        .invoice-table th {
            text-align: left;
            padding: 12px 16px;
            background-color: #f8f9fa;
            color: #555;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            border-bottom: 2px solid #e9ecef;
        }

        .invoice-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f3f5;
            color: #333;
            font-size: 0.95rem;
        }

        .invoice-table tr.total-row td {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-purple);
            border-top: 2px solid #dee2e6;
            border-bottom: none;
            background-color: #fafbfc;
        }

        .btn-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn-register {
            background: linear-gradient(135deg, var(--primary-purple), #311368);
            color: var(--white);
            border: none;
            padding: 16px 40px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(29, 10, 63, 0.2);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(29, 10, 63, 0.3);
            background: linear-gradient(135deg, #2a0f5a, #421a8a);
        }

        .btn-secondary {
            background: #ffffff;
            color: var(--primary-purple);
            border: 2px solid var(--primary-purple);
            padding: 14px 30px;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: #f4effd;
        }

        /* Success Celebration */
        .success-celebration {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            max-width: 800px;
            margin: 0 auto 40px auto;
            border-top: 6px solid #28a745;
            padding: 45px 40px;
            text-align: center;
        }

        .success-icon-wrap {
            width: 80px;
            height: 80px;
            background-color: #e8f5e9;
            color: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 20px auto;
        }

        .success-celebration h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.8rem;
            color: var(--primary-purple);
            margin-bottom: 10px;
        }

        .receipt-summary-box {
            background-color: #f8f9fa;
            border: 1px solid #eaeaea;
            border-radius: 8px;
            padding: 20px 25px;
            margin: 25px 0 35px 0;
            text-align: left;
        }

        .receipt-summary-box .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.95rem;
        }

        .receipt-summary-box .receipt-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary-purple);
            padding-top: 12px;
        }

        /* Modal Overlay for Payment Simulation */
        .simulation-modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 6, 33, 0.75);
            backdrop-filter: blur(4px);
            z-index: 999999;
            align-items: center;
            justify-content: center;
        }

        .simulation-modal {
            background: #fff;
            border-radius: 16px;
            width: 90%;
            max-width: 480px;
            padding: 35px 30px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            animation: popUp 0.3s ease;
        }

        @keyframes popUp {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .spinner-ring {
            width: 60px;
            height: 60px;
            border: 6px solid #e0d5f5;
            border-top: 6px solid var(--accent-gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body>

    <!-- Top Preview Banner -->
    <div class="demo-mode-banner">
        <span class="demo-mode-badge">🔬 PREVIEW MODE</span>
        <span>Zero Database Connection &bull; Payment is simulated safely for testing.</span>
        <a href="preview_registration.php" class="demo-link-btn">← Back to Form Preview</a>
    </div>

    <!-- Navigation Header -->
    <header>
        <div id="navbar"></div>
    </header>

    <main class="main-container">
        <!-- Title Section -->
        <div class="title-section">
            <h1 id="pageTitle">PREVIEW: REGISTRATION & PAYMENT</h1>
        </div>

        <!-- Wizard Progress Bar -->
        <div class="wizard-progress">
            <div class="progress-line-track"></div>
            <div class="progress-line-fill" id="progressFill"></div>
            <div class="progress-step completed" data-label="Form Filled">✓</div>
            <div class="progress-step active" id="step2Indicator" data-label="Review & Fees">2</div>
            <div class="progress-step" id="step3Indicator" data-label="Confirmation">3</div>
        </div>

        <!-- STEP 1: REVIEW & ITEMISED FEES -->
        <div id="view-review" class="step-container active">
            <h2 style="font-family: 'Outfit', sans-serif; text-align: center; margin-bottom: 30px; color: var(--primary-purple); font-size: 1.7rem;">
                REVIEW REGISTRANT DETAILS & FEES
            </h2>

            <div class="invoice-card">
                <div class="invoice-header">
                    <div>
                        <h4>ICSWHMH 2027 Registration Preview</h4>
                        <div style="font-size: 0.85rem; color: #f0f0f0; margin-top: 4px;">Rajagiri College of Social Sciences, Kalamassery, Kochi</div>
                    </div>
                    <div>
                        <span class="ref-code" id="lblRegId">
                            <span style="color: var(--accent-gold); font-weight: 700;">REF:</span>
                            <span id="reviewRegId">ICSW-PREVIEW-DEMO</span>
                        </span>
                    </div>
                </div>

                <div class="invoice-body">
                    <!-- Registrant Details -->
                    <div class="receipt-section-title">
                        <span>1. Registrant Information</span>
                        <span class="badge-pill" id="lblParticipantType">Academician / Researcher</span>
                    </div>
                    
                    <div class="receipt-grid">
                        <div class="receipt-item">
                            <span class="receipt-label">Full Name & Designation</span>
                            <span class="receipt-value" id="reviewFullName">Dr. Jane Doe</span>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-label">Email Address</span>
                            <span class="receipt-value" id="reviewEmail">jane.doe@example.com</span>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-label">Institution / Organization</span>
                            <span class="receipt-value" id="reviewOrg">Rajagiri College of Social Sciences</span>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-label">Phone Number</span>
                            <span class="receipt-value" id="reviewPhone">+91 9876543210</span>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-label">Country & Category</span>
                            <span class="receipt-value" id="reviewCountry">India (National)</span>
                        </div>
                        <div class="receipt-item" id="reviewPassportRow" style="display: none;">
                            <span class="receipt-label">Passport Number (Non-Indian)</span>
                            <span class="receipt-value">
                                <span class="badge-passport" id="reviewPassport">N/A</span>
                            </span>
                        </div>
                        <div class="receipt-item" style="grid-column: span 2;">
                            <span class="receipt-label">Address for Communication</span>
                            <span class="receipt-value" id="reviewAddress" style="font-weight: 500; font-size: 0.95rem; white-space: pre-line;">-</span>
                        </div>
                    </div>

                    <!-- Participation Details -->
                    <div class="receipt-section-title">
                        <span>2. Participation Details</span>
                    </div>

                    <div class="receipt-grid">
                        <div class="receipt-item">
                            <span class="receipt-label">Mode of Presentation</span>
                            <span class="receipt-value" id="reviewMode">In Person Presentation</span>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-label">Type of Presentation</span>
                            <span class="receipt-value" id="reviewType">Paper Presentation</span>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-label">Food Preference</span>
                            <span class="receipt-value" id="reviewFood">Veg</span>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-label">Chosen Registration Package</span>
                            <span class="receipt-value" id="reviewPackage" style="color: var(--primary-purple);">Non-Residential</span>
                        </div>
                    </div>

                    <!-- Price Calculations -->
                    <div class="receipt-section-title">
                        <span>3. Itemized Fee Invoice</span>
                    </div>

                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th style="text-align: right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td id="invoiceItemDesc">Registration Package Access Fee</td>
                                <td style="text-align: right; font-weight: 600;" id="invoiceBasePrice">₹ 5,000 INR</td>
                            </tr>
                            <tr class="total-row">
                                <td>Total Amount Due (Demo)</td>
                                <td style="text-align: right;" id="invoiceTotalPrice">₹ 5,000 INR</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="btn-container">
                <a href="preview_registration.php" class="btn-secondary">
                    ← Edit Form Details
                </a>
                <button type="button" class="btn-register" id="btnPayPreview" onclick="simulatePaymentFlow()">
                    Pay Now & Confirm (Demo Simulation) →
                </button>
            </div>
        </div>

        <!-- STEP 2: CONFIRMATION SUCCESS (REVEALED ON SIMULATION) -->
        <div id="view-success" class="step-container">
            <div class="success-celebration">
                <div class="success-icon-wrap">
                    ✓
                </div>
                <h3>Registration Completed Successfully!</h3>
                <p style="color: #666; font-size: 1.05rem;">
                    Your test payment has been simulated successfully with zero database impact.
                </p>

                <div class="receipt-summary-box">
                    <div class="receipt-row">
                        <span>Demo Transaction ID</span>
                        <span id="finalTxnId" style="font-family: monospace; font-weight: 700; color: #28a745;">TXN-DEMO-2027-SUCCESS</span>
                    </div>
                    <div class="receipt-row">
                        <span>Registration Reference</span>
                        <span id="finalRegId" style="font-weight: 700;">ICSW-PREVIEW-DEMO</span>
                    </div>
                    <div class="receipt-row">
                        <span>Registrant Name</span>
                        <span id="finalName">Dr. Jane Doe</span>
                    </div>
                    <div class="receipt-row">
                        <span>Participant Category</span>
                        <span id="finalCategory">Academician / Researcher</span>
                    </div>
                    <div class="receipt-row">
                        <span>Participation Mode</span>
                        <span id="finalMode">In Person Presentation</span>
                    </div>
                    <div class="receipt-row">
                        <span>Presentation Type</span>
                        <span id="finalTypePres">Paper Presentation</span>
                    </div>
                    <div class="receipt-row">
                        <span>Food Preference</span>
                        <span id="finalFood">Veg</span>
                    </div>
                    <div class="receipt-row">
                        <span>Package Paid</span>
                        <span id="finalPackage">Non-Residential</span>
                    </div>
                    <div class="receipt-row">
                        <span>Total Paid (Simulated)</span>
                        <span id="finalTotalPaid" style="color: #28a745;">₹ 5,000 INR</span>
                    </div>
                </div>

                <div class="btn-container" style="margin-top: 10px;">
                    <a href="preview_registration.php" class="btn-register" style="background: var(--accent-gold); color: #1D0A3F;">
                        ↻ Test Another Preview Registration
                    </a>
                    <a href="index.html" class="btn-secondary">
                        Return to Conference Home
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Payment Simulation Modal -->
    <div class="simulation-modal-backdrop" id="simModal">
        <div class="simulation-modal">
            <div class="spinner-ring"></div>
            <h3 id="simModalTitle" style="color: var(--primary-purple); font-family: 'Outfit', sans-serif; margin-bottom: 8px;">
                Simulating Payment Gateway...
            </h3>
            <p id="simModalMsg" style="color: #666; font-size: 0.95rem; margin-bottom: 15px;">
                Connecting to simulated bank switch (Zero charges).
            </p>
            <div style="background: #f4effd; padding: 10px; border-radius: 6px; font-size: 0.82rem; color: var(--primary-purple); font-weight: 600;">
                🔒 Safe Demo Mode &bull; No credit card or database calls made
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div id="footer"></div>
    </footer>

    <script>
        // Default Sample Demo Data if visited directly without previous form submission
        const defaultSampleData = {
            registrationId: 'ICSW-PREVIEW-' + Math.floor(100000 + Math.random() * 900000),
            designation: 'Dr. / Researcher',
            firstName: 'Sarah',
            middleName: 'M.',
            lastName: 'Williams',
            fullName: 'Dr. / Researcher Sarah M. Williams',
            email: 'sarah.williams@example.edu',
            organization: 'Oxford University / Global Health Institute',
            phone: '+44 7911 123456',
            participantType: 'Faculty / Academician / Researcher',
            country: 'United Kingdom',
            countryCategory: 'Developed Countries',
            passportNumber: 'GB98234110A',
            communicationAddress: 'Department of Social Sciences, Wellington Square, Oxford OX1 2JD, UK',
            modeOfPresentation: 'in person presentation',
            typeOfPresentation: 'Paper presentation',
            foodPreference: 'Veg',
            package: 'Non-Residential Access ($300 USD)',
            baseAmount: 300,
            currency: 'USD',
            createdDate: new Date().toLocaleDateString('en-GB')
        };

        // Load preview registration state from sessionStorage
        let regData = null;
        try {
            const stored = sessionStorage.getItem('icsw_preview_registration');
            if (stored) {
                regData = JSON.parse(stored);
            }
        } catch (e) {
            console.warn('Error reading sessionStorage:', e);
        }

        if (!regData) {
            regData = defaultSampleData;
        }

        // Populate Review UI
        document.addEventListener('DOMContentLoaded', () => {
            // Reference ID
            document.getElementById('reviewRegId').textContent = regData.registrationId || 'ICSW-PREVIEW-2027';
            
            // Registrant
            const desig = regData.designation ? `${regData.designation} ` : '';
            const fullName = regData.fullName || `${desig}${regData.firstName || ''} ${regData.lastName || ''}`.trim();
            document.getElementById('reviewFullName').textContent = fullName;
            document.getElementById('reviewEmail').textContent = regData.email || '-';
            document.getElementById('reviewOrg').textContent = regData.organization || '-';
            document.getElementById('reviewPhone').textContent = regData.phone || '-';
            document.getElementById('lblParticipantType').textContent = regData.participantType || 'Delegate';

            // Country & Passport
            const isNonIndian = regData.country && regData.country.toLowerCase() !== 'india';
            document.getElementById('reviewCountry').textContent = `${regData.country || 'N/A'} (${regData.countryCategory || 'Tier'})`;
            
            if (isNonIndian && regData.passportNumber) {
                document.getElementById('reviewPassportRow').style.display = 'flex';
                document.getElementById('reviewPassport').textContent = regData.passportNumber;
            } else {
                document.getElementById('reviewPassportRow').style.display = 'none';
            }

            // Address
            document.getElementById('reviewAddress').textContent = regData.communicationAddress || 'Not Provided';

            // Participation Details
            document.getElementById('reviewMode').textContent = regData.modeOfPresentation || 'In Person';
            document.getElementById('reviewType').textContent = regData.typeOfPresentation || 'Paper Presentation';
            document.getElementById('reviewFood').textContent = regData.foodPreference || 'Veg';
            document.getElementById('reviewPackage').textContent = regData.package || 'Standard Package';

            // Pricing
            const symbol = regData.currency === 'INR' ? '₹ ' : '$ ';
            const formattedPrice = `${symbol}${Number(regData.baseAmount || 0).toLocaleString()} ${regData.currency}`;
            document.getElementById('invoiceItemDesc').textContent = `${regData.participantType || 'Registration'} - ${regData.package || 'Access'}`;
            document.getElementById('invoiceBasePrice').textContent = formattedPrice;
            document.getElementById('invoiceTotalPrice').textContent = formattedPrice;
        });

        // Simulate Payment Process Flow
        function simulatePaymentFlow() {
            const modal = document.getElementById('simModal');
            const modalTitle = document.getElementById('simModalTitle');
            const modalMsg = document.getElementById('simModalMsg');

            modal.style.display = 'flex';

            // Step 1: Handshake
            setTimeout(() => {
                modalTitle.textContent = 'Verifying Transaction Credentials...';
                modalMsg.textContent = 'Simulating secure 3D Secure / OTP authorization...';
            }, 1000);

            // Step 2: Confirmation
            setTimeout(() => {
                modalTitle.textContent = 'Payment Authorized Successfully!';
                modalMsg.textContent = 'Generating preview invoice and receipt...';
            }, 2000);

            // Step 3: Switch view to success
            setTimeout(() => {
                modal.style.display = 'none';

                // Update Progress bar to Step 3
                document.getElementById('progressFill').style.width = '100%';
                document.getElementById('step2Indicator').classList.remove('active');
                document.getElementById('step2Indicator').classList.add('completed');
                document.getElementById('step3Indicator').classList.add('active', 'completed');

                // Populate Step 3 details
                const demoTxn = 'VTX-DEMO-' + Math.floor(10000000 + Math.random() * 90000000);
                document.getElementById('finalTxnId').textContent = demoTxn;
                document.getElementById('finalRegId').textContent = regData.registrationId || 'ICSW-PREVIEW-2027';
                document.getElementById('finalName').textContent = document.getElementById('reviewFullName').textContent;
                document.getElementById('finalCategory').textContent = regData.participantType || 'Delegate';
                document.getElementById('finalMode').textContent = regData.modeOfPresentation || 'In Person';
                document.getElementById('finalTypePres').textContent = regData.typeOfPresentation || 'Paper Presentation';
                document.getElementById('finalFood').textContent = regData.foodPreference || 'Veg';
                document.getElementById('finalPackage').textContent = regData.package || 'Standard';

                const symbol = regData.currency === 'INR' ? '₹ ' : '$ ';
                document.getElementById('finalTotalPaid').textContent = `${symbol}${Number(regData.baseAmount || 0).toLocaleString()} ${regData.currency}`;

                // Hide Step 1 Review, Show Step 2 Success
                document.getElementById('view-review').classList.remove('active');
                document.getElementById('view-success').classList.add('active');

                window.scrollTo({ top: 100, behavior: 'smooth' });
            }, 2800);
        }
    </script>
</body>

</html>
