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
                }

                * {
                    box-sizing: border-box;
                }


                ul {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                }

                .footer-container {
                    position: relative;
                    background-color: #1d0a3f;
                    color: white;
                    min-height: 290px; /* Reduced height */
                    overflow: visible;
                    display: flex;
                    flex-direction: column;
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

                /* Social Icons Styling */
                .social-icons {
                    position: absolute;
                    left: 50px;
                    bottom: 75px;
                    display: flex;
                    gap: 10px;
                    z-index: 10;
                }

                .social-icon {
                    width: 40px;
                    height: 40px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 50%;
                    color: white;
                    font-size: 1.2rem;
                    cursor: pointer;
                    transition: transform 0.3s;
                }

                .social-icon:hover {
                    transform: translateY(-3px);
                }

                .icon-linkedin { background-color: #0077b5; }
                .icon-facebook { background-color: #1877f2; }
                .icon-instagram { 
                    background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); 
                }

                .social-svg {
                    width: 20px;
                    height: 20px;
                    fill: white;
                }

                /* Content Grid (Right Side) */
                .content-section {
                    margin-left: 580px; /* Shifted slightly right from 480px */
                    padding: 35px 50px 20px;
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
                    margin-bottom: 20px;
                    color: #C9A227; /* Gold color for headers */
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }

                .footer-col ul li {
                    margin-bottom: 12px;
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
                        width: 100%;
                        overflow-x: hidden;
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

                    .social-icons {
                        position: relative;
                        left: auto;
                        bottom: auto;
                        justify-content: center;
                        margin-top: 20px;
                        width: 100%;
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
                <div class="social-icons">
                     <a href="https://www.linkedin.com/school/rajagiri-college-of-social-sciences-autonomous/posts/?feedView=all" target="_blank" class="social-icon icon-linkedin">
                        <svg class="social-svg" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                    </a>
                    <a href="https://www.facebook.com/Rajagiri-College-of-Social-Sciences-Autonomous-1376623805998150/?fref=ts" target="_blank" class="social-icon icon-facebook">
                        <svg class="social-svg" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                    </a>
                    <a href="https://www.instagram.com/rajagiri.official/" target="_blank" class="social-icon icon-instagram">
                        <svg class="social-svg" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
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
                    <span class="copyright-text">Copyright © 2025 Rajagiri. All Rights Reserved.</span>
                </div>
            </div>
        `;
    }
}

customElements.define('main-footer', MainFooter);