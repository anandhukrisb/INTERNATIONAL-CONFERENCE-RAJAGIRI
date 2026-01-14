class FloatingNavbar extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this.toggleMobileMenu = this.toggleMobileMenu.bind(this);
        this.hasRendered = false; // Prevent re-rendering during view transitions
    }

    connectedCallback() {
        // Only render once to prevent flickering during view transitions
        if (!this.hasRendered) {
            this.render();
            this.hasRendered = true;
        }
        // Always update active link highlighting
        this.highlightActiveLink();
    }

    highlightActiveLink() {
        const currentPath = window.location.pathname;
        // Handle root path / or /index.html
        let pageName = currentPath.split('/').pop();
        if (pageName === '' || pageName === undefined) pageName = 'index.html';

        const allLinks = this.shadowRoot.querySelectorAll('a');
        allLinks.forEach(l => l.classList.remove('active'));

        allLinks.forEach(link => {
            const href = link.getAttribute('href');
            // Check if href matches pageName
            // We use simple string match assuming relative links like 'index.html', 'program.html'
            if (href === pageName) {
                // If it is a top-level link (direct child of .navbar-links > li)
                // Structure: .navbar-links > li > a
                const parentLi = link.closest('li');
                if (parentLi && parentLi.parentElement && parentLi.parentElement.classList.contains('navbar-links')) {
                    // It is a top level link, just mark it
                    link.classList.add('active');
                } else if (link.closest('.dropdown-menu')) {
                    // It is inside a dropdown
                    const topLevelLi = link.closest('.navbar-links > li');
                    if (topLevelLi) {
                        const topLink = topLevelLi.querySelector(':scope > a');
                        if (topLink) topLink.classList.add('active');
                    }
                }
            }
        });
    }



    render() {
        const logoSrc = this.getAttribute('logo-src') || 'assets/logo.png';

        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: block;
                }
                
                .navbar-backdrop {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 110px;
                    background-color: #FDFBF7;
                    z-index: 999;
                }


                .floating-navbar {
                    position: fixed;
                    top: 10px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 97%; /* User defined */
                    height: 90px; /* User defined */
                    // background-color: #007720;
                    background: linear-gradient(to right, #F5F7F6 5%, transparent 5%), linear-gradient(135deg, #1d0a3f 0%, #4b1c9b 100%);
                    border-radius: 50px;
                    display: flex;
                    align-items: center;
                    padding: 0;
                    // box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                    z-index: 1000;
                    /* overflow: hidden; Removed to allow dropdowns to show */
                    border: none;

                }



                .navbar-logo {
                    height: 100%;
                    position: relative;
                    display: flex;
                    align-items: center;
                    padding-left: 0;
                }

                /* Base Logo (Conference) */
                .logo-base {
                    height: 100%;
                    width: auto;
                    object-fit: cover;
                    border-top-left-radius: 50px;
                    border-bottom-left-radius: 50px;
                    transition: border-radius 0.6s ease-in-out;
                    position: relative;
                    z-index: 1;
                }


                
                /* Overlay Logos */
                .logo-overlay {
                    position: absolute;
                    z-index: 10;
                    height: 50%; /* Smaller than base */
                    width: auto;
                    object-fit: contain;
                    top: 50%;
                    transform: translateY(-50%);
                }

                .logo-overlay.rajagiri {
                    left: 4px;
                    top:45px;
                    height: 80%;
                    width: auto;
                }

                .logo-overlay.rcss {
                    top: 55px;
                    left: 80px;
                    height: 60%;
                    width: auto;
                }

                li {
                    position: relative;
                    height: 100%;
                    display: flex;
                    align-items: center;
                }

                .navbar-links {
                    margin-left: auto;
                    display: flex;
                    gap: 15px;
                    padding-right: 60px;
                    list-style: none;
                    margin-top: 0;
                    margin-bottom: 0;
                    flex-wrap: nowrap;
                    height: 100%;
                    align-items: center;
                }

                .navbar-links a {
                    color: white;
                    text-decoration: none;
                    font-size: 16px;
                    font-weight: 500;
                    transition: opacity 0.3s;
                    font-family: 'Outfit', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                    white-space: nowrap;
                    display: flex;
                    align-items: center;
                    height: 100%;
                    padding: 0 5px;
                }

                .navbar-links a:hover {
                    opacity: 0.8;
                }

                /* Active link styling (Desktop only) */
                @media (min-width: 1025px) {
                    .navbar-links a.active {
                        color: #d4af37; /* Gold Text */
                        position: relative;
                    }
                    
                    .navbar-links a.active::after {
                        content: '';
                        position: absolute;
                        bottom: 20px; /* Adjust based on valid clickable area height */
                        left: 5px;
                        width: calc(100% - 10px); /* Match padding */
                        height: 3px;
                        background-color: #d4af37;
                        border-radius: 2px;
                    }
                }

                /* Dropdown Styles */
                .dropdown-menu {
                    backdrop-filter: blur(10px);
                    min-width: 300px;
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    padding: 10px 0;
                    list-style: none;
                    opacity: 0;
                    visibility: hidden;
                    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
                    z-index: 100;
                }

                /* Desktop-only dropdown behaviors */
                @media (min-width: 1025px) {
                    .dropdown-menu {
                        position: absolute;
                        top: 80px;
                        left: 50%;
                        transform: translateX(-50%) translateY(20px);
                        background: rgba(255, 255, 255, 0.95);
                    }

                    /* Triangle/Arrow */
                    .dropdown-menu::before {
                        content: '';
                        position: absolute;
                        top: -6px;
                        left: 50%;
                        transform: translateX(-50%);
                        border-left: 6px solid transparent;
                        border-right: 6px solid transparent;
                        border-bottom: 6px solid rgba(255, 255, 255, 0.95);
                    }

                    /* Show Dropdown on Hover or Active class */
                    li:hover .dropdown-menu,
                    li.active .dropdown-menu {
                        opacity: 1;
                        visibility: visible;
                        transform: translateX(-50%) translateY(0);
                        top: 60px;
                    }
                }

                .dropdown-menu li {
                    display: block;
                    height: auto;
                    margin: 0;
                }

                .dropdown-menu li a {
                    color: #1d0a3f;
                    padding: 10px 20px;
                    display: block;
                    width: 100%;
                    box-sizing: border-box; 
                    height: auto;
                    border-radius: 0;
                    font-size: 13px;
                    white-space: nowrap; 
                    text-align: left;
                    position: relative;
                    z-index: 1;
                    overflow: hidden;
                }

                .dropdown-menu li a::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 0%;
                    height: 100%;
                    background-color: rgba(29, 10, 63, 0.1);
                    transition: width 0.3s ease;
                    z-index: -1;
                }

                .dropdown-menu li a:hover::before {
                    width: 100%;
                }

                .dropdown-menu li a:hover {
                    color: #4b1c9b;
                    opacity: 1;
                }

                .navbar-links > li:last-child {
                    margin-left: 10px;
                }

                .navbar-links a.contact-btn {
                    background-color: white;
                    color: #1d0a3f;
                    padding: 10px 25px;
                    border-radius: 25px;
                    font-weight: 600;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
                    transition: all 0.3s ease;
                    height: 20px;
                }

                .navbar-links a.contact-btn:hover {
                    opacity: 1;
                    transform: translateY(-2px);
                    box-shadow: 0 6px 15px rgba(0,0,0,0.3);
                    background-color: #f0f0f0;
                }

                /* Mobile toggle button (hamburger) container */
                .mobile-toggle {
                    display: none;
                    flex-direction: column;
                    gap: 5px;
                    cursor: pointer;
                    padding: 10px;
                    margin-left: auto;
                    margin-right: 20px;
                    z-index: 1001;
                }

                .mobile-toggle span {
                    display: block;
                    width: 25px;
                    height: 3px;
                    background-color: white;
                    border-radius: 3px;
                    transition: all 0.3s ease;
                }

                /* Conference title displayed only on mobile headers */
                .mobile-title {
                    display: none;
                    color: white;
                    font-family: 'Outfit', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                    font-size: 20px;
                    font-weight: 500;
                    margin-left: 20px;
                    white-space: nowrap;
                }

                /* Logo container specifically for mobile view */
                .logo-container-mobile {
                    display: none;
                    height: 100%;
                    background: url('${logoSrc}') no-repeat center center;
                    background-size: cover;
                    border-top-left-radius: 50px;
                    border-bottom-left-radius: 50px;
                    padding: 0 20px 0 10px;
                    align-items: center;
                    position: relative;
                    z-index: 2;
                }
                


                .logo-container-mobile img.rajagiri-mobile {
                    height: 70%;
                    width: auto;
                }

                .logo-container-mobile img.rcss-mobile {
                    height: 50%;
                    width: auto;
                    margin-left: -22px;
                    margin-top: 20px;
                }

                /* Media query for mobile and tablet devices */
                @media (max-width: 1024px) {
                    .floating-navbar {
                        width: 100% !important;
                        left: 0 !important;
                        transform: none !important;
                        top: 0;
                        border-radius: 0 !important;
                        background: #1d0a3f;
                        transition: background 0.4s ease; /* Simple transition */
                    }

                    .navbar-links {
                        display: flex;
                        position: absolute;
                        top: 100%;
                        left: 0 !important;
                        width: 100% !important;
                        background: #1d0a3f;
                        flex-direction: column;
                        padding: 20px 0;
                        height: auto;
                        border-radius: 0 0 20px 20px;
                        box-shadow: 0 10px 20px rgba(0,0,0,0.2);
                        /* Smooth transition setup */
                        opacity: 0;
                        visibility: hidden;
                        transform: translateY(-10px);
                        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
                        pointer-events: none;
                    }

                    .navbar-links.mobile-active {
                        opacity: 1;
                        visibility: visible;
                        transform: translateY(0);
                        pointer-events: auto;
                    }

                    .navbar-links li {
                        width: 100%;
                        height: auto;
                        flex-direction: column;
                        align-items: flex-start;
                    }

                    .navbar-links a {
                        width: 100%;
                        padding: 15px 30px;
                        box-sizing: border-box;
                    }

                    .mobile-toggle {
                        display: flex;
                    }

                    .mobile-title {
                        display: block;
                    }

                    .logo-container-mobile {
                        display: flex;
                        border-radius: 0 !important;
                    }

                    .navbar-logo {
                        display: none;
                    }

                    .dropdown-menu {
                        position: static;
                        transform: none;
                        opacity: 1;
                        visibility: visible;
                        display: block; /* Always block but height 0 for transition */
                        max-height: 0;
                        overflow: hidden;
                        width: 100%;
                        background: transparent; /* Fix: removed gray background */
                        box-shadow: none;
                        border-radius: 0;
                        padding: 0;
                        transition: max-height 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
                    }

                    .dropdown-menu::before,
                    .dropdown-menu::after {
                        display: none !important;
                        content: none !important;
                    }

                    li.active .dropdown-menu {
                        max-height: 500px; /* Sufficient height for dropdown */
                    }

                    .dropdown-menu li a {
                        color: rgba(255, 255, 255, 0.8);
                        padding: 12px 30px 12px 60px; /* Indented for submenu feel */
                        font-size: 13px;
                    }

                    .dropdown-menu li a:hover {
                        color: white;
                        background: rgba(255, 255, 255, 0.1);
                    }

                    .navbar-links a.contact-btn {
                        margin: 10px 30px;
                        width: auto;
                        display: inline-block;
                    }
                    
                    /* Hamburger Animation */
                    .mobile-toggle.active span:nth-child(1) {
                        transform: translateY(8px) rotate(45deg);
                    }
                    .mobile-toggle.active span:nth-child(2) {
                        opacity: 0;
                    }
                    .mobile-toggle.active span:nth-child(3) {
                        transform: translateY(-8px) rotate(-45deg);
                    }
                }
            </style>

            <div class="navbar-backdrop"></div>
            <nav class="floating-navbar">
                <div class="logo-container-mobile">
                    <img src="assets/rajagiri_logo.png" alt="Rajagiri" class="rajagiri-mobile">
                    <img src="assets/rcss_logo.png" alt="RCSS" class="rcss-mobile">
                </div>

                <div class="mobile-title">ICSWHMH 27</div>

                <div class="mobile-toggle" id="mobile-toggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <div class="navbar-logo">
                    <!-- Base Logo -->
                    <img src="${logoSrc}" alt="Conference Logo" class="logo-base">
                    
                    <!-- Overlays -->
                    <img src="assets/rajagiri_logo.png" alt="Rajagiri" class="logo-overlay rajagiri">
                    <img src="assets/rcss_logo.png" alt="RCSS" class="logo-overlay rcss">
                </div>
                <ul class="navbar-links">
                    <li>
                        <a href="index.html">2027 ICSWHMH</a>
                    </li>
                    <li>
                        <a href="program.html">Program</a>
                        <ul class="dropdown-menu">
                            <li><a href="program.html">Events</a></li>
                            <li><a href="topics.html">Conference topics</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Speakers</a>
                        <ul class="dropdown-menu">
                            <li><a href="speaker.html">Speakers list</a></li>
                            <li><a href="ministerialopening.html">Ministerrial opening</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Registration</a>
                        <ul class="dropdown-menu">
                            <li><a href="#">Student</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Abstracts</a>
                        <ul class="dropdown-menu">
                            <li><a href="#">Guidelines</a></li>
                        </ul>
                    </li>

                    <li>
                        <a href="#">Social Functions</a>
                        <ul class="dropdown-menu">
                            <li><a href="#">Tours</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Sponsorships & Exhibitions</a>
                         <ul class="dropdown-menu">
                            <li><a href="#">Floor Plan</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Host city</a>
                        <ul class="dropdown-menu">
                            <li><a href="#">Travel</a></li>
                        </ul>
                    </li>
                    <li><a href="#" class="contact-btn">Contact Us</a></li>
                </ul>
            </nav>
        `;

        /* Handle opening/closing of the mobile navigation menu */
        // Mobile toggle logic
        const mobileToggle = this.shadowRoot.getElementById('mobile-toggle');
        const navbarLinks = this.shadowRoot.querySelector('.navbar-links');

        if (mobileToggle) {
            mobileToggle.addEventListener('click', () => {
                mobileToggle.classList.toggle('active');
                navbarLinks.classList.toggle('mobile-active');
            });
        }

        /* Ensure sub-menus work on mobile via click events */
        // Add click event listeners for mobile/click support
        const navItems = this.shadowRoot.querySelectorAll('.navbar-links > li');
        navItems.forEach(item => {
            const link = item.querySelector('a');
            if (link && item.querySelector('.dropdown-menu')) {
                link.addEventListener('click', (e) => {
                    // Only prevent default on mobile or if it's a dropdown toggle
                    if (window.innerWidth <= 1024) {
                        e.preventDefault();
                        // Close others
                        navItems.forEach(other => {
                            if (other !== item) other.classList.remove('active');
                        });
                        item.classList.toggle('active');
                    }
                });
            }
        });
    }

    toggleMobileMenu() {
        const mobileToggle = this.shadowRoot.getElementById('mobile-toggle');
        const navbarLinks = this.shadowRoot.querySelector('.navbar-links');
        if (mobileToggle && navbarLinks) {
            mobileToggle.classList.toggle('active');
            navbarLinks.classList.toggle('mobile-active');
        }
    }
}

customElements.define('floating-navbar', FloatingNavbar);
