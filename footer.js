class MainFooter extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
    }

    connectedCallback() {
        this.render();
    }

    render() {
        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: block;
                    width: 100%;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                }

                .footer-container {
                    position: relative;
                    background-color: #1d0a3f;
                    color: white;
                    min-height: 400px;
                    overflow: visible; /* Allow white corners to sit on top */
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
                    background-image: url('assets/about_image.jpg');
                    background-size: cover;
                    background-position: center;
                    opacity: 0.2;
                    mask-image: linear-gradient(to right, transparent, black);
                    -webkit-mask-image: linear-gradient(to right, transparent, black);
                    pointer-events: none;
                }

                /* Top Left White Section (Logos) */
                .logo-section {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 500px; /* Adjusted to fit shape image */
                    height: 180px;
                    background-image: url('assets/footer_top_shape.png');
                    background-size: cover; /* or 100% 100% depending on shape fit */
                    background-repeat: no-repeat;
                    background-position: top left;
                    background-color: transparent; /* Remove solid white */
                    border-radius: 0; /* Remove border radius as shape handles it */
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 30px;
                    z-index: 10;
                    padding-right: 50px; /* Padding to keep content away from curve */
                }

                .footer-logo {
                    height: 110px;
                    width: auto;
                    object-fit: contain;
                }

                /* Bottom Left White Section (Copyright) */
                .copyright-section {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    width: 600px; /* Adjusted to fit shape image */
                    height: 80px;
                    background-color: transparent;
                    border-radius: 0;
                    display: flex;
                    align-items: center; /* Center vertically */
                    justify-content: center; /* Center horizontally */
                    padding-left: 0;
                    padding-right: 50px; /* Slight padding to respect curve if needed, but mostly center */
                    z-index: 10;
                    color: #000;
                    font-size: 1rem; /* Bigger */
                    font-weight: 500;
                    text-align: center;
                }

                .footer-shape-bottom {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    object-fit: cover; /* Maintain aspect ratio */
                    z-index: -1;
                }

                /* Ensure text is above image */
                .copyright-text {
                    position: relative;
                    z-index: 1;
                }

                /* Social Icons Styling */
                .social-icons {
                    position: absolute;
                    left: 50px;
                    bottom: 100px; /* Above copyright section */
                    display: flex;
                    gap: 20px;
                    z-index: 10;
                }

                .social-icon {
                    width: 45px;
                    height: 45px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 50%;
                    color: white;
                    font-size: 1.5rem;
                    cursor: pointer;
                    transition: transform 0.3s;
                }

                .social-icon:hover {
                    transform: translateY(-5px);
                }

                .icon-linkedin { background-color: #0077b5; }
                .icon-facebook { background-color: #1877f2; }
                .icon-instagram { 
                    background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); 
                }

                .social-svg {
                    width: 24px;
                    height: 24px;
                    fill: white;
                }

                /* Contact Content (Right Side) */
                .content-section {
                    margin-left: 500px; /* Clear the left sections */
                    padding: 60px 50px;
                    position: relative;
                    z-index: 5;
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                }

                .contact-info {
                    max-width: 600px;
                }

                .contact-title {
                    font-size: 3rem;
                    font-weight: 400;
                    margin: 0 0 20px 0;
                    color: white;
                }

                .contact-text {
                    font-size: 1.1rem;
                    line-height: 1.6;
                    margin-bottom: 20px;
                    color: rgba(255, 255, 255, 0.9);
                    max-width: 400px;
                }

                .address-block {
                    margin-top: 40px;
                    font-size: 1rem;
                    line-height: 1.8;
                    color: rgba(255, 255, 255, 0.8);
                }

                .address-block strong {
                    color: white;
                    display: block;
                    margin-bottom: 5px;
                }

                .contact-details {
                    margin-top: 20px;
                }
                
                .contact-details a {
                    color: white;
                    text-decoration: none;
                    display: block;
                    margin-bottom: 5px;
                }

                .contact-details a:hover {
                    text-decoration: underline;
                }

                @media (max-width: 900px) {
                    .footer-container {
                        flex-direction: column;
                        min-height: auto;
                    }

                    .logo-section {
                        position: relative;
                        width: 100%;
                        height: auto;
                        padding: 30px 0;
                        border-bottom-right-radius: 0;
                        border-bottom-left-radius: 50px; /* Optional Mobile style */
                        justify-content: center;
                    }

                    .content-section {
                        margin-left: 0;
                        padding: 40px 20px;
                        flex-direction: column;
                    }

                    .social-icons {
                        position: relative;
                        left: 0;
                        bottom: 0;
                        justify-content: center;
                        margin: 20px 0;
                    }

                    .copyright-section {
                        position: relative;
                        width: 100%;
                        height: auto;
                        padding: 20px;
                        border-top-right-radius: 0;
                        text-align: center;
                        justify-content: center;
                    }
                    
                    .footer-bg {
                        width: 100%;
                        opacity: 0.1;
                    }
                }
            </style>

            <div class="footer-container">
                <div class="footer-bg"></div>

                <!-- Left Top: Logos -->
                <div class="logo-section">
                    <img src="assets/conf_logo_10th.png" alt="10th Conference Logo" class="footer-logo">
                    <!-- Assuming RCSS logo filename based on user request context, likely rcss_logo.png or rajagiri_logo.png -->
                    <img src="assets/rajagiri_logo.png" alt="RCSS Logo" class="footer-logo">
                </div>

                <!-- Left Middle: Social Icons -->
                <div class="social-icons">
                    <div class="social-icon icon-linkedin">
                        <svg class="social-svg" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                    </div>
                    <div class="social-icon icon-facebook">
                        <svg class="social-svg" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                    </div>
                    <div class="social-icon icon-instagram">
                        <svg class="social-svg" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </div>
                </div>

                <!-- Right Content Area -->
                <div class="content-section">
                    <div class="contact-info">
                        <h2 class="contact-title">Contact us</h2>
                        <p class="contact-text">Please contact the team at Forum Group with any questions regarding the Conference.</p>
                    </div>

                    <div class="address-block">
                        <strong>Forum Group Events & Marketing</strong>
                        Suite 10/Level 1, 285a Crown St,<br>
                        Surry Hills, NSW, 2010, Australia<br>
                        <div class="contact-details">
                            Phone: <a href="tel:+61286670737" style="display:inline;">+61 2 8667 0737</a><br>
                            Email: <a href="mailto:aasw@forumgroupevents.com.au" style="display:inline;">aasw@forumgroupevents.com.au</a>
                        </div>
                    </div>
                </div>

                <!-- Left Bottom: Copyright -->
                <div class="copyright-section">
                    <img src="assets/footer_bottom_shape.png" class="footer-shape-bottom" alt="Footer Shape">
                    <span class="copyright-text">Copyright © 2025 Rajagiri. All Rights Reserved. Website Designed and Maintained by XXXXXX</span>
                </div>
            </div>
        `;
    }
}

customElements.define('main-footer', MainFooter);
