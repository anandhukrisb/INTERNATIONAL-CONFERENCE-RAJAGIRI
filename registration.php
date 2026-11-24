
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- RESPONSIVE: viewport with safe area support for notched devices -->
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Registration - ICSWHMH 2027</title>
    <link rel="icon" type="image/x-icon"
        href="https://res.cloudinary.com/dswfp5fwx/image/upload/v1778131826/Favicon-192_hdltam.ico">

    <script src="navbar.js" defer></script>
    <script src="footer.js" defer></script>

    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Outfit:wght@400;600;800&display=swap"
        rel="stylesheet">

    <!-- RESPONSIVE: Shared responsive CSS replaces all inline styles -->
    <link rel="stylesheet" href="responsive-shared.css">

    <!-- FIX: Registration Action Buttons (Unified Consistent Styling) Styles -->
    <style>
        /* FIX: CSS Custom Properties & shared layout for Registration Action Buttons */
        .registration-buttons-group {
            --btn-bg: var(--accent-gold, #C9A227);
            --btn-text: var(--primary-purple, #1D0A3F);
            --btn-border: var(--accent-gold, #C9A227);
            --btn-shadow: 0 4px 15px rgba(201, 162, 39, 0.25);
            --btn-hover-shadow: 0 6px 20px rgba(201, 162, 39, 0.35);
            --btn-focus-outline: var(--primary-purple, #1D0A3F);

            display: flex;
            flex-direction: column;
            gap: 12px; /* FIX: 12px gap between the two buttons */
            width: 100%;
            margin-top: 25px;
            align-items: center;
        }

        /* FIX: Unified shared styling for both Proceed and Check Details buttons */
        .registration-buttons-group .btn-reg-flow,
        .btn-reg-flow.btn-proceed,
        .btn-reg-flow.btn-check-details {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100% !important;
            max-width: 100% !important;
            min-height: 48px; /* FIX: Touch-friendly (at least 44px touch height) */
            padding: 14px 28px;
            border-radius: 50px;
            font-family: 'Outfit', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: var(--fs-base, 1rem);
            font-weight: 600;
            text-transform: capitalize;
            letter-spacing: normal;
            text-decoration: none;
            text-align: center;
            box-sizing: border-box;
            cursor: pointer;
            margin: 0 !important;
            background-color: var(--btn-bg) !important;
            color: var(--btn-text) !important;
            border: 2px solid var(--btn-border) !important;
            box-shadow: var(--btn-shadow);
            position: relative;
            overflow: hidden;
            transition: transform 0.25s ease, box-shadow 0.25s ease, filter 0.25s ease;
        }

        /* FIX: Suppress inherited ::before circle background animation for unified look */
        .registration-buttons-group .btn-reg-flow::before {
            display: none !important;
            content: none !important;
        }

        /* FIX: Unified hover style - slightly darker gold (brightness 0.94) + subtle lift */
        .registration-buttons-group .btn-reg-flow:hover:not(:disabled),
        .btn-reg-flow.btn-proceed:hover:not(:disabled),
        .btn-reg-flow.btn-check-details:hover {
            filter: brightness(0.94);
            transform: translateY(-2px);
            box-shadow: var(--btn-hover-shadow);
            color: var(--btn-text) !important;
            text-decoration: none;
        }

        /* FIX: Unified active click feedback */
        .registration-buttons-group .btn-reg-flow:active:not(:disabled),
        .btn-reg-flow.btn-proceed:active:not(:disabled),
        .btn-reg-flow.btn-check-details:active {
            transform: translateY(0);
            filter: brightness(0.90);
            box-shadow: var(--btn-shadow);
        }

        /* FIX: High-contrast focus-visible outline against the gold button (7.47:1 contrast ratio) */
        .registration-buttons-group .btn-reg-flow:focus-visible,
        .btn-reg-flow:focus-visible {
            outline: 3px solid var(--btn-focus-outline);
            outline-offset: 3px;
        }

        /* FIX: Proceed button disabled state preserved (Check Details is never disabled) */
        .registration-buttons-group .btn-proceed:disabled,
        .btn-reg-flow.btn-proceed:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
            filter: none !important;
            box-shadow: var(--btn-shadow) !important;
        }
    </style>

</head>

<body>

    <floating-navbar></floating-navbar>

    <!-- FIX: Floating Check Details button removed from body; moved below Proceed button in form -->

    <main class="main-container">

        <div class="title-section">
            <h2 id="pageTitle">REGISTRATION</h2>
        </div>

        
        <section class="fee-guidelines-section" style="display: none !important;">
            <h2 class="section-header" style="text-align: center; margin-bottom: 30px;">FEE GUIDELINES</h2>
            <div class="fee-toggle-container">
                <button class="fee-toggle-btn active" id="btn-view-all" onclick="showFeeTab('all')">View All
                    Fees</button>
                <button class="fee-toggle-btn" id="btn-filter" onclick="showFeeTab('filter')">Find My Fee</button>
            </div>

            
            <div id="fee-view-all" class="fee-tab-content active">

                <h3 class="fee-table-title"
                    style="text-align: center; margin-bottom: 30px; font-size: 1.6rem; font-weight: 700;">General
                    Registration</h3>
                <div class="pricing-cards-container">
                    
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
                    
                </div>
            </div>
        </section>

        

        
        
        


        
        
        
        <section class="registration-form-section">
            

            
            <div id="registration-form-wrapper">

                
                <div id="initialEmailCheckContainer" class="verification-box" style="margin-bottom: 25px;">
                    <h4
                        style="font-family: 'Outfit', sans-serif; color: var(--primary-purple); margin-bottom: 12px; font-weight: 700;">
                        Start Registration</h4>
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 15px;">Please enter your email address to begin. We will check your abstract submission status.</p>
                    <div class="verification-row">
                        <div class="form-group" style="margin-bottom: 0; flex: 1;">
                            <label for="checkEmail" class="form-label">Email Address <span style="color: red;">*</span></label>
                            <input type="email" id="checkEmail" class="form-control"
                                placeholder="you@example.com">
                        </div>
                        <button type="button" class="btn-verify" id="btnCheckEmail"
                            onclick="checkInitialEmail()">Proceed</button>
                    </div>
                    <div id="initialCheckStatus" class="verification-status"></div>
                    
                    
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

                
                <p style="color: red; font-size: 0.9em; margin-bottom: 15px;">* Fields are mandatory</p>
                <form id="registrationForm" onsubmit="submitRegistration(event)" class="locked-form-overlay">

                    <!-- RESPONSIVE: 4-col row on desktop, stacks on mobile via grid -->
                    <div class="form-row form-row--quad">
                        <div class="form-group">
                            <label for="designation" class="form-label">Designation <span style="color: red;">*</span></label>
                            <select id="designation" name="designation" class="form-control" autocomplete="honorific-prefix" onchange="handleDesignationChange()" required>
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
                        <div class="form-group">
                            <label for="firstName" class="form-label">First Name <span style="color: red;">*</span></label>
                            <input type="text" id="firstName" name="firstName" class="form-control" placeholder="Alan"
                                autocomplete="given-name" required>
                        </div>
                        <div class="form-group">
                            <label for="middleName" class="form-label">Middle Name</label>
                            <input type="text" id="middleName" name="middleName" class="form-control" placeholder="J."
                                autocomplete="additional-name">
                        </div>
                        <div class="form-group">
                            <label for="lastName" class="form-label">Last Name <span style="color: red;">*</span></label>
                            <input type="text" id="lastName" name="lastName" class="form-control" placeholder="Wake"
                                autocomplete="family-name" required>
                        </div>
                    </div>

                    <!-- Other Designation (Appears only if "Other" is selected) -->
                    <div class="form-row" id="otherDesignationGroup" style="display: none; transition: all 0.3s ease;">
                        <div class="form-group" style="max-width: 50%;">
                            <label for="otherDesignation" class="form-label">Please Specify Designation <span style="color: red;">*</span></label>
                            <input type="text" id="otherDesignation" name="otherDesignation" class="form-control" placeholder="Enter your designation">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="organization" class="form-label">Organization / Institution <span style="color: red;">*</span></label>
                            <input type="text" id="organization" name="organization" class="form-control"
                                placeholder="University / Institution Name" required>
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address <span style="color: red;">*</span></label>
                            <input type="email" id="email" name="email" class="form-control"
                                placeholder="alan.wake@example.com" required>
                        </div>
                        <div class="form-group">
                            <label for="dob" class="form-label">Date of Birth <span style="color: red;">*</span></label>
                            <input type="date" id="dob" name="dob" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number <span style="color: red;">*</span></label>
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder="+91 98765 43210"
                                required>
                        </div>
                        <div class="form-group">
                            <label for="participantType" class="form-label">Participant Category  <span style="color: red;">*</span></label>
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
                            <label for="country" class="form-label">Country (Where are you from?) <span style="color: red;">*</span></label>
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
                                need?) <span style="color: red;">*</span></label>
                            <select id="requirement" name="requirement" class="form-control" required>
                                <option value="" disabled selected>Select Package</option>
                                
                            </select>
                        </div>
                    </div>

                    <!-- Passport Number (Popped up if nationality is non-Indian) -->
                    <div class="form-row" id="passportRow" style="display: none; transition: all 0.3s ease;">
                        <div class="form-group" style="flex: 1 1 100%;">
                            <label for="passportNumber" class="form-label">Passport Number <span style="color: red;">*</span> <span style="font-size: 0.85rem; font-weight: normal; color: #64748b;">(Required for non-Indian participants)</span></label>
                            <input type="text" id="passportNumber" name="passportNumber" class="form-control" placeholder="Enter your Passport Number">
                        </div>
                    </div>

                    <!-- Address for Communication -->
                    <div class="form-row">
                        <div class="form-group" style="flex: 1 1 100%;">
                            <label for="communicationAddress" class="form-label">Address for Communication <span style="color: red;">*</span></label>
                            <textarea id="communicationAddress" name="communicationAddress" class="form-control" rows="2" placeholder="Street address, City, State/Province, Postal Code, Country" required style="resize: vertical; min-height: 70px;"></textarea>
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


                    <div class="checkbox-group" style="margin-bottom: 30px;">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms" class="checkbox-label">I agree to the <a href="payment-policy.html"
                                target="_blank" style="color: var(--primary-purple); font-weight: 600;">Terms &
                                Conditions</a> and Payment Policy.</label>
                    </div>

                    <!-- FIX: Normal page flow action buttons container with 12px gap -->
                    <div class="registration-buttons-group">
                        <button type="submit" class="btn-register btn-reg-flow btn-proceed">Proceed to Payment Details</button>
                        <a href="view_transaction.php" class="btn-reg-flow btn-check-details" aria-label="Check existing registration details">Check Details</a>
                    </div>
                </form>
            </div>

            
            
    </main>

    <main-footer></main-footer>

    <script>
        function toggleAccordion(icon) {
            const strip = icon.closest('.info-strip');
            const content = strip.querySelector('.info-content');
            const isExpanded = strip.classList.contains('expanded');

            if (isExpanded) {
                
                content.style.maxHeight = content.scrollHeight + "px";
                content.offsetHeight; 
                content.style.maxHeight = "0";
                strip.classList.remove('expanded');
                icon.textContent = "+";
            } else {
                
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
                    { label: "In-Person (Offline)", value: "1,000 INR" },
                    { label: "Online (Recorded)", value: "500 INR" }
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

        
        document.addEventListener('DOMContentLoaded', () => {
            updateFeeFilter();
        });
    </script>

    
    <script>
        
        window.addEventListener("pageshow", function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        const spinnerSvg = '<svg style="animation: spin 1s linear infinite; margin-right: 8px; width: 18px; height: 18px; display: inline-block; vertical-align: text-bottom;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity: 0.25;"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" style="opacity: 0.75;"></path></svg>';

        
        let registrationState = {
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

        function handleModeChange() {
            const modeSelect = document.getElementById('modeOfPresentation');
            const typeSelect = document.getElementById('typeOfPresentation');
            const typeStar = document.getElementById('typePresStar');
            const typeGroup = document.getElementById('typeOfPresentationGroup');
            const foodSelect = document.getElementById('foodPreference');

            const mode = modeSelect.value;
            const isDelegateOnly = mode.includes('without presenting') || mode.includes('Attending as a delegate');

            // Handle presentation type requirement and options
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

            // Handle food preference for online participants
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
                    
                    let statusColor = '#D69E2E'; 
                    let buttonText = 'Check Details / Pay Again &rarr;';
                    
                    if (statusText === 'Completed') {
                        statusColor = '#2F855A'; 
                        buttonText = 'Check Registration Details &rarr;';
                    } else if (statusText === 'Failed' || statusText.toLowerCase() === 'not completed') {
                        statusColor = '#C53030'; 
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

                
                registrationState.pendingEmail = emailVal;
                registrationState.pendingAbstract = data.has_abstract ? 'yes' : 'no';
                
                document.getElementById('checkEmail').readOnly = true;
                btn.style.display = 'none'; 
                
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
                    body: JSON.stringify({ email: registrationState.pendingEmail, otp: otpVal })
                });
                const data = await res.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Invalid OTP.');
                }

                
                registrationState.isVerified = true;
                registrationState.email = registrationState.pendingEmail;
                registrationState.abstractSubmitted = registrationState.pendingAbstract;

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

                
                const mainEmail = document.getElementById('email');
                mainEmail.value = registrationState.email;
                mainEmail.readOnly = true;

                
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
                    body: JSON.stringify({ email: registrationState.pendingEmail })
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

        
        function updateRequirementsOptions() {
            const pType = document.getElementById('participantType').value;
            const cCat = document.getElementById('countryCategory').value;
            const reqSelect = document.getElementById('requirement');

            
            reqSelect.innerHTML = '<option value="" disabled selected>Select Package</option>';

            if (!pType || !cCat) return;

            
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

        

        
        async function submitRegistration(event) {
            event.preventDefault();

            
            const status = registrationState.abstractSubmitted || 'no';

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

            
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = spinnerSvg + 'Checking...';

            
            
            
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;

            
            const selectedPackageData = feeData[pType][cCat][reqIdx];

            
            registrationState.designation = designation;
            registrationState.firstName = firstName;
            registrationState.middleName = middleName;
            registrationState.lastName = lastName;
            registrationState.email = email;
            registrationState.organization = organization;
            registrationState.phone = phone;
            registrationState.dob = dob;
            registrationState.participantType = pTypeSelect.options[pTypeSelect.selectedIndex].text;
            registrationState.country = countryVal;
            registrationState.passportNumber = passportVal;
            registrationState.communicationAddress = commAddr;
            registrationState.modeOfPresentation = modeVal;
            registrationState.typeOfPresentation = typeVal;
            registrationState.foodPreference = foodVal;
            
            registrationState.countryCategory = cCat === 'developed' ? 'Developed Countries' : (cCat === 'national' ? 'National (India)' : 'Developing Countries');
            
            registrationState.requirementIndex = reqIdx;
            registrationState.requirementLabel = selectedPackageData.label;
            registrationState.requirementCostText = selectedPackageData.value;

            
            const rawCost = selectedPackageData.value; 
            const numPart = rawCost.replace(/[^0-9]/g, '');
            const baseAmount = parseInt(numPart);
            const isINR = rawCost.includes('INR');

            registrationState.currency = isINR ? 'INR' : 'USD';
            registrationState.basePrice = baseAmount;

            
            registrationState.gstPrice = 0;
            registrationState.totalPrice = baseAmount;

            
            submitBtn.disabled = true;
            submitBtn.innerHTML = spinnerSvg + 'Saving details...';

            
            const payload = {
                designation: registrationState.designation,
                firstName: registrationState.firstName,
                middleName: registrationState.middleName,
                lastName: registrationState.lastName,
                email: registrationState.email,
                organization: registrationState.organization,
                phone: registrationState.phone,
                communicationAddress: registrationState.communicationAddress,
                dob: registrationState.dob,
                participantType: registrationState.participantType,
                country: registrationState.country,
                passportNumber: registrationState.passportNumber,
                countryCategory: registrationState.countryCategory,
                requiredPackage: `${registrationState.requirementLabel} (${rawCost})`,
                modeOfPresentation: registrationState.modeOfPresentation,
                typeOfPresentation: registrationState.typeOfPresentation,
                foodPreference: registrationState.foodPreference,
                abstractSubmitted: status,
                abstractEmail: status === 'yes' ? document.getElementById('verifyEmail').value.trim() : '',
                baseAmount: registrationState.basePrice,
                paymentStatus: 'Not Completed'
            };

            
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



    </script>
</body>

</html>