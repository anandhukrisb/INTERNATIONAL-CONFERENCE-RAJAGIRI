class MainFooter extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this.hasRendered = false; // Prevent re-rendering during view transitions
    }

    connectedCallback() {
        // Only render once to prevent flickering during view transitions
        if (!this.hasRendered) {
            this.render();
            this.hasRendered = true;
        }
    }

    render() {
        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: block;
                    width: 100%;
                    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    --footer-bg: #1d0a3f;
                    --icon-bg: #ffffff;
                    --icon-color: #333333;
                    --icon-hover-color: #ffffff;
                }

                * {
                    box-sizing: border-box;
                    margin: 0;
                    padding: 0;
                }


                ul {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                }

                .footer-container {
                    position: relative;
                    background-color: var(--footer-bg);
                    color: white;
                    height: 280px; /* Fixed height requested */
                    min-height: 280px;
                    overflow: hidden; /* Prevent content overflow */
                    display: flex;
                    flex-direction: column;
                    padding-bottom: 0;
                }

                /* Background Image Layer */
                .footer-bg {
                    position: absolute;
                    top: 0;
                    right: 0;
                    width: 60%;
                    height: 100%;
                    /* background-image: url('assets/about_image.jpg'); Removed as per request */
                    background-size: cover;
                    background-position: center;
                    opacity: 0.15;
                    mask-image: linear-gradient(to right, transparent, black);
                    -webkit-mask-image: linear-gradient(to right, transparent, black);
                    pointer-events: none;
                }

                /* Top Left White Section (Logos) */
                .logo-section {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 450px;
                    height: 160px; /* Slightly smaller */
                    background-image: url('assets/footer_top_shape.png');
                    background-size: 100% 100%;
                    background-repeat: no-repeat;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0px;
                    z-index: 10;
                    padding-right: 40px; 
                }

                .footer-logo {
                    height: 90px;
                    width: auto;
                    object-fit: contain;
                    display: block;
                }

                .footer-logo:first-child {
                    height: 120px;
                    transform: translateY(-15px);
                }

                .footer-logo:last-child {
                    height: 90px;
                    margin-left: 20px;
                }

                /* Bottom Left White Section (Copyright) - Desktop with curved shape */
                .copyright-section {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    width: 550px;
                    height: 60px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding-right: 40px;
                    z-index: 10;
                    color: #000;
                    font-size: 0.85rem;
                    font-weight: 500;
                    text-align: center;
                }

                .footer-shape-bottom {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    object-fit: fill;
                    z-index: -1;
                }

                .copyright-text {
                    position: relative;
                    z-index: 1;
                }

                /* Social Icons Styling - Wrapper with tooltips */
                .social-section {
                    position: absolute;
                    left: 14px;
                    bottom: 65px; /* Adjusted to center in dark stripe */
                    z-index: 20; /* Higher z-index */
                }

                .wrapper {
                    display: inline-flex;
                    list-style: none;
                    padding: 0;
                    margin: 0;
                }

                .wrapper a {
                    color: inherit;
                    text-decoration: none;
                }

                .wrapper .icon {
                    position: relative;
                    background: var(--icon-bg);
                    border-radius: 50%;
                    padding: 0;
                    margin: 5px;
                    width: 42px;
                    height: 42px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    box-shadow: 0 10px 10px rgba(0, 0, 0, 0.1);
                    cursor: pointer;
                    transition: all 0.2s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                    color: var(--icon-color) !important; /* Force dark color */
                }

                .social-svg {
                    width: 18px; /* Slightly smaller for better optical balance */
                    height: 18px;
                    fill: currentColor;
                    transition: fill 0.2s;
                    display: block; /* Removes any line-height issues */
                }

                .wrapper .tooltip {
                    position: absolute;
                    top: 0;
                    font-size: 12px;
                    background: #ffffff;
                    color: #333;
                    padding: 4px 7px;
                    border-radius: 5px;
                    box-shadow: 0 10px 10px rgba(0, 0, 0, 0.1);
                    opacity: 0;
                    pointer-events: none;
                    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                    font-family: 'Outfit', sans-serif;
                    white-space: nowrap;
                    font-weight: 600;
                }

                .wrapper .tooltip::before {
                    position: absolute;
                    content: "";
                    height: 8px;
                    width: 8px;
                    background: #ffffff;
                    bottom: -3px;
                    left: 50%;
                    transform: translate(-50%) rotate(45deg);
                    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                }

                .wrapper .icon:hover .tooltip {
                    top: -45px;
                    opacity: 1;
                    visibility: visible;
                    pointer-events: auto;
                }

                .wrapper .icon:hover span,
                .wrapper .icon:hover .tooltip {
                    text-shadow: 0px -1px 0px rgba(0, 0, 0, 0.1);
                }

                .wrapper .icon:hover {
                    color: var(--icon-hover-color) !important;
                }

                .wrapper .facebook:hover,
                .wrapper .facebook:hover .tooltip,
                .wrapper .facebook:hover .tooltip::before {
                    background: #1877F2;
                    color: #ffffff;
                }

                .wrapper .x:hover,
                .wrapper .x:hover .tooltip,
                .wrapper .x:hover .tooltip::before {
                    background: #000000;
                    color: #ffffff;
                }

                .wrapper .instagram:hover,
                .wrapper .instagram:hover .tooltip,
                .wrapper .instagram:hover .tooltip::before {
                    background: #E4405F;
                    color: #ffffff;
                }

                .wrapper .linkedin:hover,
                .wrapper .linkedin:hover .tooltip,
                .wrapper .linkedin:hover .tooltip::before {
                    background: #0A66C2;
                    color: #ffffff;
                }

                .wrapper .youtube:hover,
                .wrapper .youtube:hover .tooltip,
                .wrapper .youtube:hover .tooltip::before {
                    background: #CD201F;
                    color: #ffffff;
                }

                /* Content Grid (Right Side) */
                .content-section {
                    margin-left: 580px; /* Shifted slightly right from 480px */
                    padding: 25px 50px 20px; /* Reduced top padding */
                    position: relative;
                    z-index: 5;
                    display: grid;
                    grid-template-columns: 1.3fr 0.7fr 1fr; /* Contact, Quick Links, Resources */
                    gap: 20px;
                    align-items: start;
                }

                .footer-col h3 {
                    font-family: 'Outfit', sans-serif;
                    font-size: 1.2rem;
                    margin-bottom: 15px; /* Reduced specific spacing */
                    color: #C9A227; /* Gold color for headers */
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }

                .footer-col ul li {
                    margin-bottom: 8px; /* Reduced spacing */
                }

                .footer-col ul li a {
                    color: #ffffff;
                    text-decoration: none;
                    font-size: 0.95rem;
                    transition: color 0.3s;
                }

                .footer-col ul li a:hover {
                    color: #fff;
                    text-decoration: underline;
                }

                .contact-info p {
                    font-size: 0.95rem;
                    line-height: 1.6;
                    color: rgba(255, 255, 255, 0.85);
                    margin: 0 0 15px 0;
                }


                .contact-link {
                    color: #d4af37;
                    text-decoration: none;
                    transition: color 0.3s;
                }

                .contact-link:hover {
                    text-decoration: underline;
                    color: #d4af37;
                }

                .contact-details a {
                    color: white;
                    text-decoration: none;
                }



                /* Smaller Tablet - 2 columns with Resources spanning (optional intermediate step) */
                /* This breakpoint can be removed if direct jump to mobile is preferred */


                @media (max-width: 1307px) {
                    .footer-container {
                        min-height: auto;
                        height: auto;
                        width: 100%;
                        overflow-x: hidden;
                        padding-bottom: 0;
                    }

                    .logo-section {
                        position: relative;
                        width: 100%;
                        height: 120px;
                        padding: 0;
                        background: none; /* No shape on mobile? or simple bg */
                        background-color: white;
                        border-bottom-left-radius: 30px;
                        border-bottom-right-radius: 30px;
                    }

                    .content-section {
                        margin-left: 0;
                        padding: 40px 20px;
                        grid-template-columns: auto auto;
                        justify-content: center;
                        gap: 30px 60px;
                        text-align: center;
                        width: 100%;
                    }
                    
                    /* Reorder columns: Quick Links and Resources on top, Contact Us below */
                    .col-contact {
                        grid-column: 1 / -1;
                        grid-row: 2;
                    }
                    
                    .col-quick-links {
                        grid-column: 1;
                        grid-row: 1;
                        text-align: left;
                        padding-left: 10px;
                    }

                    .col-resources {
                        grid-column: 2;
                        grid-row: 1;
                        text-align: left;
                        padding-left: 10px;
                    }
                    
                    .footer-col h3 {
                        margin-bottom: 15px;
                    }

                    .social-section {
                        position: relative;
                        left: auto;
                        bottom: auto;
                        display: flex;
                        justify-content: center;
                        margin-top: 20px;
                        width: 100%;
                        padding-bottom: 20px;
                    }

                    .copyright-section {
                        position: relative;
                        width: 100%;
                        height: auto;
                        padding: 20px;
                        bottom: auto;
                        left: 0;
                        right: 0;
                        background-color: white;
                    }
                    
                    .footer-shape-bottom { 
                        display: none; 
                    }
                }
            </style>
            
            <div class="footer-container">
                <div class="footer-bg"></div>

                <!-- Left Top: Logos -->
                <div class="logo-section">
                    <img src="assets/icswhmh_logo_new.png" alt="11th Conference Logo" class="footer-logo">
                    <img src="assets/rajagiri_logo.png" alt="RCSS Logo" class="footer-logo">
                </div>

                <!-- Left Middle: Social Icons -->
                <div class="social-section">
                    <ul class="wrapper">
                        <a href="https://www.facebook.com/Rajagiri-College-of-Social-Sciences-Autonomous-1376623805998150/?fref=ts"
                            target="_blank" rel="noopener noreferrer">
                            <li class="icon facebook">
                                <span class="tooltip">Facebook</span>
                                <span><svg class="social-svg" viewBox="0 0 320 512"><path d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z"/></svg></span>
                            </li>
                        </a>

                        <a href="https://www.instagram.com/rajagiri.official/" target="_blank" rel="noopener noreferrer">
                            <li class="icon instagram">
                                <span class="tooltip">Instagram</span>
                                <span><svg class="social-svg" viewBox="0 0 448 512"><path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.9 0-184.9zm-49.2 273.9c-14.8 14.8-49.1 16.5-123.8 16.5-74.5 0-109-1.7-123.8-16.5-14.8-14.8-16.5-49.1-16.5-123.8 0-74.5 1.7-109 16.5-123.8 14.8-14.8 49.1-16.5 123.8-16.5 74.5 0 109 1.7 123.8 16.5 14.8 14.8 16.5 49.1 16.5 123.8 0 74.5-1.7 109-16.5 123.8z"/></svg></span>
                            </li>
                        </a>
                        <a href="https://www.linkedin.com/school/rajagiri-college-of-social-sciences-autonomous/posts/?feedView=all"
                            target="_blank" rel="noopener noreferrer">
                            <li class="icon linkedin">
                                <span class="tooltip">LinkedIn</span>
                                <span><svg class="social-svg" viewBox="0 0 448 512"><path d="M416 32H31.9C14.3 32 0 46.5 0 64.3v383.4C0 465.5 14.3 480 31.9 480H416c17.6 0 32-14.5 32-32.3V64.3c0-17.8-14.4-32.3-32-32.3zM135.4 416H69V202.2h66.5V416zm-33.2-243c-21.3 0-38.5-17.3-38.5-38.5S80.9 96 102.2 96c21.2 0 38.5 17.3 38.5 38.5 0 21.3-17.2 38.5-38.5 38.5zm282.1 243h-66.4V312c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9V416h-66.4V202.2h63.7v29.2h.9c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9V416z"/></svg></span>
                            </li>
                        </a>
                        <a href="https://www.youtube.com/@rajagiricollegeofsocialsci9476" target="_blank" rel="noopener noreferrer">
                            <li class="icon youtube">
                                <span class="tooltip">Youtube</span>
                                <span><svg class="social-svg" viewBox="0 0 576 512"><path d="M549.655 124.083c-6.281-23.65-24.787-42.276-48.284-48.597C458.781 64 288 64 288 64S117.22 64 74.629 75.486c-23.497 6.322-42.003 24.947-48.284 48.597-11.412 42.867-11.412 132.305-11.412 132.305s0 89.438 11.412 132.305c6.281 23.65 24.787 41.5 48.284 47.821C117.22 448 288 448 288 448s170.78 0 213.371-11.486c23.497-6.321 42.003-24.171 48.284-47.821 11.412-42.867 11.412-132.305 11.412-132.305s0-89.438-11.412-132.305zm-317.51 213.508V175.185l142.739 81.205-142.739 81.201z"/></svg></span>
                            </li>
                        </a>
                    </ul>
                </div>

                <!-- Right Content Area: CSS Grid -->
                <div class="content-section">
                    <!-- Col 1: Contact -->
                    <div class="footer-col col-contact">
                        <h3>Contact Us</h3>
                        <div class="contact-info">
                            <p>Rajagiri College of Social Sciences (Autonomous),<br>
                                Rajagiri P.O, Kalamassery,<br>
                                Cochin - 683104, Kerala, India.</p>
                            
                            <p>
                                Phone: <a href="tel:+914842911111" class="contact-link">+91 484 - 2911111</a> / <a href="tel:+914842911507" class="contact-link">2911507</a><br>
                                Email: <a href="mailto:icswhmh2027@rajagiri.edu" class="contact-link">icswhmh2027@rajagiri.edu</a>
                            </p>
                        </div>
                    </div>

                    <!-- Col 2: Quick Links -->
                    <div class="footer-col col-quick-links">
                        <h3>Quick Links</h3>
                        <ul>
                            <li><a href="abstract.html">Submit Abstract</a></li>
                            <li><a href="program.html">Program</a></li>
                            <li><a href="speaker.html">Speakers</a></li>
                            <li><a href="registration.html">Registration</a></li>
                            <li><a href="hotels.html">Host City</a></li>
                            <li><a href="faq.html">FAQ</a></li>
                        </ul>
                    </div>

                    <!-- Col 3: Resources -->
                    <div class="footer-col col-resources">
                        <h3>Resources</h3>
                        <ul>
                            <li>Privacy Policy</li>
                            <li>Terms & Conditions</li>
                            <li>Code of Conduct</li>
                            <li>Sponsorships</li>
                            <li>Contact Support</li>
                        </ul>
                    </div>
                </div>

                <!-- Left Bottom: Copyright -->
                <div class="copyright-section">
                    <img src="assets/footer_bottom_shape.png" class="footer-shape-bottom" alt="Footer Shape">
                    <span class="copyright-text">Copyright © 2026 Rajagiri. All Rights Reserved.</span>
                </div>
            </div>
        `;
    }
}

customElements.define('main-footer', MainFooter);