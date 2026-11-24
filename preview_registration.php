<?php
/**
 * preview_registration.php
 * Standalone Client Demo & Preview Mode
 * ZERO Database Connections - Safe for testing and client review.
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Preview (Demo Mode) - ICSWHMH 2027</title>
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

        /* ================= TITLE SECTION ================= */
        .title-section {
            margin-top: 140px;
            margin-bottom: 40px;
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

        .intro-text {
            font-size: 1.15rem;
            margin-bottom: 30px;
            color: var(--text-muted);
            max-width: 100%;
            text-align: justify;
        }

        /* ================= REGISTRATION FORM SECTION ================= */
        .registration-form-section {
            background: var(--white);
            border-radius: 12px;
            padding: 40px;
            box-shadow: var(--shadow);
            margin-bottom: 80px;
            border: 1px solid #E2E8F0;
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
            color: var(--primary-purple);
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-control {
            padding: 12px 15px;
            border: 2px solid #CBD5E0;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            color: var(--text-dark);
            background-color: #F8FAFC;
            transition: all 0.3s ease;
        }

        .form-control::placeholder {
            color: #A0AEC0;
            opacity: 1;
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
            background-size: 14px;
            padding-right: 40px;
        }

        .btn-register {
            display: inline-block;
            background-color: var(--accent-gold);
            color: #1D0A3F;
            padding: 15px 40px;
            border-radius: 50px;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 2px solid var(--accent-gold);
            cursor: pointer;
            transition: all 0.3s ease;
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
            background-color: var(--primary-purple);
            z-index: -1;
            transition: clip-path 0.4s ease;
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

        /* Checkbox Group */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 25px;
            margin-bottom: 30px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--primary-purple);
            cursor: pointer;
        }

        .checkbox-label {
            font-size: 0.95rem;
            color: var(--text-dark);
            cursor: pointer;
        }

        /* Locked Form State */
        .locked-form-overlay {
            opacity: 0.4;
            pointer-events: none;
            filter: grayscale(80%);
            transition: all 0.4s ease;
            user-select: none;
        }

        .email-verification-box {
            background: #F8FAFC;
            border: 2px solid var(--accent-gold);
            border-radius: 10px;
            padding: 25px 30px;
            margin-bottom: 30px;
        }

        .email-verification-box h4 {
            font-family: 'Outfit', sans-serif;
            color: var(--primary-purple);
            font-size: 1.25rem;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .verification-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            margin-top: 15px;
        }

        .btn-verify {
            background-color: var(--primary-purple);
            color: var(--white);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-verify:hover {
            background-color: var(--accent-gold);
            color: #1D0A3F;
            transform: translateY(-2px);
        }

        .verification-status {
            margin-top: 15px;
            font-size: 0.95rem;
            font-weight: 500;
            display: none;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            border-radius: 6px;
        }

        .verification-status.success {
            background-color: #DEF7EC;
            color: #03543F;
            border: 1px solid #BCF0DA;
            display: flex;
        }

        .verification-status.error {
            background-color: #FDE8E8;
            color: #9B1C1C;
            border: 1px solid #FBD5D5;
            display: flex;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 15px;
            }

            .verification-row {
                flex-direction: column;
                align-items: stretch;
            }

            .registration-form-section {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <!-- Top Demo Banner -->
    <div class="demo-mode-banner">
        <span class="demo-mode-badge">Client Demo</span>
        <span>🔬 PREVIEW MODE (Zero Database Connection) — Enter any email below to instantly unlock all registration fields and test the flow!</span>
    </div>

    <!-- Main Navigation Bar -->
    <main-navbar></main-navbar>

    <main class="main-container">

        <div class="title-section">
            <h1 id="pageTitle">Conference Registration Preview</h1>
            <p class="intro-text" style="margin-top: 20px;">
                Welcome to the interactive preview of the <strong>ICSWHMH 2027</strong> registration system. 
                This sandbox environment allows you to test the complete registration form, dynamic country-based passport popups, designation dropdown, participation modes, and the payment review step without saving records to the database.
            </p>
        </div>

        <section class="registration-form-section">

            <!-- STEP 1: Email Unlock Sandbox -->
            <div class="email-verification-box" id="emailCheckSection">
                <h4>Step 1: Enter Any Email to Unlock Registration</h4>
                <p style="font-size: 0.92rem; color: var(--text-muted); margin-bottom: 0;">
                    In this preview, entering any test email address instantly unlocks all registration fields. No verification email or database check will be executed.
                </p>

                <div class="verification-row">
                    <div class="form-group" style="margin-bottom: 0; flex: 1;">
                        <label for="checkEmail" class="form-label">Email Address <span style="color: red;">*</span></label>
                        <input type="email" id="checkEmail" class="form-control" placeholder="e.g. attendee@example.com or any test email">
                    </div>
                    <button type="button" class="btn-verify" id="btnCheckEmail" onclick="unlockFormPreview()">Unlock Form</button>
                </div>

                <div id="initialCheckStatus" class="verification-status"></div>
            </div>

            <!-- STEP 2: The Main Registration Form (Locked initially) -->
            <p style="color: red; font-size: 0.9em; margin-bottom: 15px;">* Fields are mandatory</p>
            <form id="registrationForm" onsubmit="submitRegistrationPreview(event)" class="locked-form-overlay">

                <!-- Row 1: Designation (Dropdown on left), First Name, Middle Name, Last Name -->
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="designation" class="form-label">Designation <span style="color: red;">*</span></label>
                        <select id="designation" name="designation" class="form-control" onchange="handleDesignationChange()" required>
                            <option value="" disabled selected>Select Designation</option>
                            <option value="Professor">Professor</option>
                            <option value="Associate Professor">Associate Professor</option>
                            <option value="Assistant Professor">Assistant Professor</option>
                            <option value="Dr. / Researcher">Dr. / Researcher</option>
                            <option value="Research Scholar">Research Scholar</option>
                            <option value="Postdoctoral Fellow">Postdoctoral Fellow</option>
                            <option value="Dean / Director / Principal">Dean / Director / Principal</option>
                            <option value="Lecturer / Senior Lecturer">Lecturer / Senior Lecturer</option>
                            <option value="Field Practitioner / Consultant">Field Practitioner / Consultant</option>
                            <option value="Student">Student</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="firstName" class="form-label">First Name <span style="color: red;">*</span></label>
                        <input type="text" id="firstName" name="firstName" class="form-control" placeholder="Alan" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="middleName" class="form-label">Middle Name</label>
                        <input type="text" id="middleName" name="middleName" class="form-control" placeholder="J.">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="lastName" class="form-label">Last Name <span style="color: red;">*</span></label>
                        <input type="text" id="lastName" name="lastName" class="form-control" placeholder="Wake" required>
                    </div>
                </div>

                <!-- Other Designation (Appears dynamically if "Other" is selected) -->
                <div class="form-row" id="otherDesignationGroup" style="display: none; transition: all 0.3s ease;">
                    <div class="form-group" style="max-width: 50%;">
                        <label for="otherDesignation" class="form-label">Please Specify Designation <span style="color: red;">*</span></label>
                        <input type="text" id="otherDesignation" name="otherDesignation" class="form-control" placeholder="Enter your specific designation">
                    </div>
                </div>

                <!-- Row 2: Organization, Email, Date of Birth -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="organization" class="form-label">Organization / Institution <span style="color: red;">*</span></label>
                        <input type="text" id="organization" name="organization" class="form-control" placeholder="University / Institute Name" required>
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address <span style="color: red;">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="alan.wake@example.com" required readonly style="background-color: #EDF2F7; cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label for="dob" class="form-label">Date of Birth <span style="color: red;">*</span></label>
                        <input type="date" id="dob" name="dob" class="form-control" required>
                    </div>
                </div>

                <!-- Row 3: Phone Number, Participant Category -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number <span style="color: red;">*</span></label>
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="+91 98765 43210" required>
                    </div>
                    <div class="form-group">
                        <label for="participantType" class="form-label">Participant Category (Who are you?) <span style="color: red;">*</span></label>
                        <select id="participantType" name="participantType" class="form-control" onchange="updateRequirementsOptions()" required>
                            <option value="" disabled selected>Select Category</option>
                            <option value="general">Academics / Field Practitioners (General)</option>
                            <option value="phd">PhD Scholars</option>
                            <option value="student">Students</option>
                        </select>
                    </div>
                </div>

                <!-- Row 4: Country, Required Package -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="country" class="form-label">Country (Where are you from?) <span style="color: red;">*</span></label>
                        <select id="country" name="country" class="form-control" onchange="handleCountryChange()" required>
                            <option value="" disabled selected>Select Country</option>
                            <option value="India">India</option>
                            <option value="United States">United States</option>
                            <option value="United Kingdom">United Kingdom</option>
                            <option value="Australia">Australia</option>
                            <option value="Canada">Canada</option>
                            <option value="Germany">Germany</option>
                            <option value="New Zealand">New Zealand</option>
                            <option value="Singapore">Singapore</option>
                            <option value="United Arab Emirates">United Arab Emirates</option>
                            <option value="Afghanistan">Afghanistan</option>
                            <option value="Bangladesh">Bangladesh</option>
                            <option value="Brazil">Brazil</option>
                            <option value="France">France</option>
                            <option value="Japan">Japan</option>
                            <option value="Malaysia">Malaysia</option>
                            <option value="Nepal">Nepal</option>
                            <option value="Netherlands">Netherlands</option>
                            <option value="Nigeria">Nigeria</option>
                            <option value="South Africa">South Africa</option>
                            <option value="Sri Lanka">Sri Lanka</option>
                            <option value="Switzerland">Switzerland</option>
                        </select>
                        <input type="hidden" id="countryCategory" name="countryCategory" value="">
                    </div>
                    <div class="form-group">
                        <label for="requirement" class="form-label">Required Package <span style="color: red;">*</span></label>
                        <select id="requirement" name="requirement" class="form-control" required>
                            <option value="" disabled selected>Select Package</option>
                        </select>
                    </div>
                </div>

                <!-- Passport Number: Dynamically revealed when Country is non-Indian -->
                <div class="form-row" id="passportRow" style="display: none; transition: all 0.3s ease;">
                    <div class="form-group" style="flex: 1 1 100%;">
                        <label for="passportNumber" class="form-label">Passport Number</label>
                        <input type="text" id="passportNumber" name="passportNumber" class="form-control" placeholder="Enter valid Passport Number">
                    </div>
                </div>

                <!-- Address for Communication -->
                <div class="form-row">
                    <div class="form-group" style="flex: 1 1 100%;">
                        <label for="communicationAddress" class="form-label">Address for Communication <span style="color: red;">*</span></label>
                        <textarea id="communicationAddress" name="communicationAddress" class="form-control" rows="2" placeholder="Street Address, City, State/Province, Postal Code, Country" required style="resize: vertical; min-height: 70px;"></textarea>
                    </div>
                </div>

                <!-- Participation Details Section -->
                <div style="margin-top: 25px; margin-bottom: 25px; padding-top: 20px; border-top: 2px solid #E2E8F0;">
                    <h3 style="color: var(--primary-purple); font-size: 1.18rem; font-weight: 700; margin-bottom: 18px; display: flex; align-items: center; gap: 8px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-gold);"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7.5" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
                        Participation Details
                    </h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="modeOfPresentation" class="form-label">Mode of Presentation <span style="color: red;">*</span></label>
                            <select id="modeOfPresentation" name="modeOfPresentation" class="form-control" onchange="handleModeChange()" required>
                                <option value="" disabled selected>Select Mode of Presentation</option>
                                <option value="In-person presentation">In-person presentation</option>
                                <option value="Online (virtual presentation) subject to approval">Online (virtual presentation) subject to approval</option>
                                <option value="Attending as a delegate without presenting (in person)">Attending as a delegate without presenting (in person)</option>
                                <option value="Attending as a delegate (online)">Attending as a delegate (online)</option>
                            </select>
                        </div>

                        <div class="form-group" id="typeOfPresentationGroup">
                            <label for="typeOfPresentation" class="form-label">Type of Presentation <span style="color: red;" id="typePresStar">*</span></label>
                            <select id="typeOfPresentation" name="typeOfPresentation" class="form-control" required>
                                <option value="" disabled selected>Select Type of Presentation</option>
                                <option value="Paper presentation">Paper presentation</option>
                                <option value="Panel presentation">Panel presentation</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Pecha Kucha">Pecha Kucha</option>
                                <option value="Student track: seed ideas">Student track: seed ideas</option>
                                <option value="Student track: best student paper">Student track: best student paper</option>
                                <option value="PhD scholar track: best paper">PhD scholar track: best paper</option>
                                <option value="PhD scholar track: best poster">PhD scholar track: best poster</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="max-width: 50%;">
                            <label for="foodPreference" class="form-label">Food Preference <span style="color: red;">*</span></label>
                            <select id="foodPreference" name="foodPreference" class="form-control" required>
                                <option value="" disabled selected>Select Food Preference</option>
                                <option value="Veg">Veg</option>
                                <option value="Non veg">Non veg</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Terms and Conditions -->
                <div class="checkbox-group">
                    <input type="checkbox" id="terms" name="terms" required checked>
                    <label for="terms" class="checkbox-label">I agree to the <a href="payment-policy.html" target="_blank" style="color: var(--primary-purple); font-weight: 600;">Terms & Conditions</a> and Payment Policy.</label>
                </div>

                <!-- Submit Button -->
                <div style="text-align: center;">
                    <button type="submit" class="btn-register" id="btnSubmitPreview">Proceed to Payment Details &rarr;</button>
                </div>
            </form>
        </section>

    </main>

    <main-footer></main-footer>

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
                    { label: "In-Person (Offline)", value: "1,000 INR" },
                    { label: "Online (Recorded)", value: "500 INR" }
                ]
            }
        };

        const DEVELOPED_COUNTRIES = [
            "Australia", "Canada", "France", "Germany", "Japan", 
            "Netherlands", "New Zealand", "Singapore", "Switzerland", 
            "United Kingdom", "United States"
        ];

        // 1. Instant Email Unlock (Zero DB check)
        function unlockFormPreview() {
            const emailInput = document.getElementById('checkEmail');
            const emailVal = emailInput.value.trim().toLowerCase();
            const statusDiv = document.getElementById('initialCheckStatus');
            const form = document.getElementById('registrationForm');
            const mainEmail = document.getElementById('email');

            if (!emailVal || !emailVal.includes('@') || !emailVal.includes('.')) {
                statusDiv.innerHTML = '⚠ Please enter a valid email address to test the form.';
                statusDiv.className = 'verification-status error';
                statusDiv.style.display = 'flex';
                emailInput.focus();
                return;
            }

            // Immediately unlock the form
            statusDiv.innerHTML = `✓ <strong>Preview Mode Active:</strong> Verified for email <em>${emailVal}</em>. Registration form unlocked!`;
            statusDiv.className = 'verification-status success';
            statusDiv.style.display = 'flex';

            form.classList.remove('locked-form-overlay');
            mainEmail.value = emailVal;

            // Optional autofill button for rapid testing
            if (!document.getElementById('quickFillBtn')) {
                const fillBtn = document.createElement('button');
                fillBtn.type = 'button';
                fillBtn.id = 'quickFillBtn';
                fillBtn.style = 'margin-top: 10px; background: none; border: none; color: #2B6CB0; text-decoration: underline; cursor: pointer; font-size: 0.85rem; font-weight: 600;';
                fillBtn.textContent = '⚡ Click to auto-fill sample demo data';
                fillBtn.onclick = autoFillDemoData;
                statusDiv.parentNode.appendChild(fillBtn);
            }
        }

        // Allow Enter key to unlock
        document.getElementById('checkEmail').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                unlockFormPreview();
            }
        });

        // Auto-fill convenience helper for the client review
        function autoFillDemoData() {
            document.getElementById('designation').value = 'Professor';
            document.getElementById('firstName').value = 'Alan';
            document.getElementById('middleName').value = 'J.';
            document.getElementById('lastName').value = 'Wake';
            document.getElementById('organization').value = 'International Institute of Social Research';
            document.getElementById('dob').value = '1985-06-15';
            document.getElementById('phone').value = '+1 415 555 2671';
            document.getElementById('participantType').value = 'general';
            document.getElementById('country').value = 'United States';
            handleCountryChange();
            document.getElementById('passportNumber').value = 'P98765432';
            document.getElementById('communicationAddress').value = '742 Evergreen Terrace, Springfield, OR 97477, United States';
            document.getElementById('modeOfPresentation').value = 'In-person presentation';
            handleModeChange();
            document.getElementById('typeOfPresentation').value = 'Paper presentation';
            document.getElementById('foodPreference').value = 'Veg';
            document.getElementById('requirement').selectedIndex = 1; // Pick second package
        }

        // 2. Designation Custom "Other" Toggle
        function handleDesignationChange() {
            const desig = document.getElementById('designation').value;
            const otherGroup = document.getElementById('otherDesignationGroup');
            const otherInput = document.getElementById('otherDesignation');
            if (otherGroup) {
                if (desig === 'Other') {
                    otherGroup.style.display = 'flex';
                    if (otherInput) {
                        otherInput.required = true;
                        otherInput.focus();
                    }
                } else {
                    otherGroup.style.display = 'none';
                    if (otherInput) {
                        otherInput.required = false;
                        otherInput.value = '';
                    }
                }
            }
        }

        // 3. Country Change Handler (Toggles Passport Popup & Fee Tier)
        function handleCountryChange() {
            const countrySelect = document.getElementById('country');
            const countryVal = countrySelect.value;
            const cCatInput = document.getElementById('countryCategory');
            const passportRow = document.getElementById('passportRow');
            const passportInput = document.getElementById('passportNumber');

            if (countryVal === 'India') {
                cCatInput.value = 'national';
                if (passportRow) passportRow.style.display = 'none';
                if (passportInput) {
                    passportInput.required = false;
                    passportInput.value = '';
                }
            } else {
                if (DEVELOPED_COUNTRIES.includes(countryVal)) {
                    cCatInput.value = 'developed';
                } else {
                    cCatInput.value = 'developing';
                }
                if (passportRow) passportRow.style.display = 'flex';
                if (passportInput) {
                    passportInput.required = true;
                }
            }

            updateRequirementsOptions();
        }

        // 4. Update Requirements Dropdown according to category & tier
        function updateRequirementsOptions() {
            const pType = document.getElementById('participantType').value;
            const cCat = document.getElementById('countryCategory').value;
            const reqSelect = document.getElementById('requirement');

            reqSelect.innerHTML = '<option value="" disabled selected>Select Package</option>';

            if (pType && cCat && feeData[pType] && feeData[pType][cCat]) {
                feeData[pType][cCat].forEach((item, index) => {
                    const opt = document.createElement('option');
                    opt.value = index;
                    opt.textContent = `${item.label} (${item.value})`;
                    reqSelect.appendChild(opt);
                });
            }
        }

        // 5. Mode of Presentation Change Handler
        function handleModeChange() {
            const modeSelect = document.getElementById('modeOfPresentation');
            const typeSelect = document.getElementById('typeOfPresentation');
            const typeStar = document.getElementById('typePresStar');
            const typeGroup = document.getElementById('typeOfPresentationGroup');
            const foodSelect = document.getElementById('foodPreference');

            const mode = modeSelect.value;
            const isDelegateOnly = mode.includes('without presenting') || mode.includes('Attending as a delegate');

            let naOption = typeSelect.querySelector('option[value="Not Applicable (Delegate only)"]');
            if (isDelegateOnly) {
                if (!naOption) {
                    naOption = document.createElement('option');
                    naOption.value = 'Not Applicable (Delegate only)';
                    naOption.textContent = 'Not Applicable (Delegate only)';
                    typeSelect.appendChild(naOption);
                }
                typeSelect.value = 'Not Applicable (Delegate only)';
                typeSelect.required = false;
                if (typeStar) typeStar.style.display = 'none';
                if (typeGroup) typeGroup.style.opacity = '0.7';
            } else {
                if (typeSelect.value === 'Not Applicable (Delegate only)') {
                    typeSelect.value = '';
                }
                typeSelect.required = true;
                if (typeStar) typeStar.style.display = 'inline';
                if (typeGroup) typeGroup.style.opacity = '1';
                if (naOption) {
                    naOption.remove();
                }
            }

            let naFood = foodSelect.querySelector('option[value="Not Applicable (Online)"]');
            if (mode.includes('(online)') || mode.includes('Online (virtual presentation)')) {
                if (!naFood) {
                    naFood = document.createElement('option');
                    naFood.value = 'Not Applicable (Online)';
                    naFood.textContent = 'Not Applicable (Online)';
                    foodSelect.appendChild(naFood);
                }
                foodSelect.value = 'Not Applicable (Online)';
            } else {
                if (foodSelect.value === 'Not Applicable (Online)') {
                    foodSelect.value = '';
                }
                if (naFood) {
                    naFood.remove();
                }
            }
        }

        // 6. Submit Preview Handler (Direct to Payment Process Preview via sessionStorage, Zero DB)
        function submitRegistrationPreview(event) {
            event.preventDefault();

            const capitalize = (s) => s.replace(/\b\w/g, c => c.toUpperCase());
            const desigSelect = document.getElementById('designation');
            let designation = desigSelect ? desigSelect.value.trim() : '';

            if (designation === 'Other') {
                const otherInput = document.getElementById('otherDesignation');
                const customDesig = otherInput ? otherInput.value.trim() : '';
                if (!customDesig) {
                    alert('Please specify your Designation.');
                    if (otherInput) otherInput.focus();
                    return;
                }
                designation = capitalize(customDesig);
            }

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

            const commAddr = document.getElementById('communicationAddress') ? document.getElementById('communicationAddress').value.trim() : '';
            const passportInput = document.getElementById('passportNumber');
            const passportVal = passportInput ? passportInput.value.trim() : '';
            const modeVal = document.getElementById('modeOfPresentation') ? document.getElementById('modeOfPresentation').value : '';
            const typeVal = document.getElementById('typeOfPresentation') ? document.getElementById('typeOfPresentation').value : '';
            const foodVal = document.getElementById('foodPreference') ? document.getElementById('foodPreference').value : '';

            if (!designation) {
                alert('Please select your Designation.');
                if (desigSelect) desigSelect.focus();
                return;
            }

            if (!firstName || !lastName || !email || !organization || !phone || !dob || !pType || !countryVal || !cCat || isNaN(reqIdx)) {
                alert('Please fill out all required fields before proceeding.');
                return;
            }

            if (countryVal !== 'India' && !passportVal) {
                alert('Please enter your Passport Number (Required for non-Indian participants).');
                if (passportInput) passportInput.focus();
                return;
            }

            if (!commAddr) {
                alert('Please enter your Address for Communication.');
                document.getElementById('communicationAddress').focus();
                return;
            }

            if (!modeVal) {
                alert('Please select your Mode of Presentation.');
                document.getElementById('modeOfPresentation').focus();
                return;
            }

            if (!typeVal) {
                alert('Please select your Type of Presentation.');
                document.getElementById('typeOfPresentation').focus();
                return;
            }

            if (!foodVal) {
                alert('Please select your Food Preference.');
                document.getElementById('foodPreference').focus();
                return;
            }

            const selectedPackageData = feeData[pType][cCat][reqIdx];
            const rawCost = selectedPackageData.value;
            const numPart = rawCost.replace(/[^0-9]/g, '');
            const baseAmount = parseInt(numPart);
            const isINR = rawCost.includes('INR');

            // Build clean preview object
            const previewData = {
                registrationId: 'ICSW-PREVIEW-' + Math.floor(100000 + Math.random() * 900000),
                designation: designation,
                firstName: firstName,
                middleName: middleName,
                lastName: lastName,
                fullName: `${designation} ${firstName} ${middleName ? middleName + ' ' : ''}${lastName}`.trim(),
                email: email,
                organization: organization,
                phone: phone,
                dob: dob,
                participantType: pTypeSelect.options[pTypeSelect.selectedIndex].text,
                country: countryVal,
                countryCategory: cCat === 'developed' ? 'Developed Countries' : (cCat === 'national' ? 'National (India)' : 'Developing Countries'),
                passportNumber: passportVal,
                communicationAddress: commAddr,
                modeOfPresentation: modeVal,
                typeOfPresentation: typeVal,
                foodPreference: foodVal,
                package: `${selectedPackageData.label} (${rawCost})`,
                baseAmount: baseAmount,
                currency: isINR ? 'INR' : 'USD',
                createdDate: new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
            };

            // Store in browser sessionStorage (ZERO database dependencies)
            sessionStorage.setItem('icsw_preview_registration', JSON.stringify(previewData));

            // Show feedback button state
            const submitBtn = document.getElementById('btnSubmitPreview');
            submitBtn.textContent = 'Redirecting to Payment Process Preview...';
            submitBtn.disabled = true;

            setTimeout(() => {
                window.location.href = 'preview_process_payment.php';
            }, 500);
        }
    </script>
</body>

</html>
