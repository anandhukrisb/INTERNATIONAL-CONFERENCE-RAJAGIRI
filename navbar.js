class FloatingNavbar extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this.handleScroll = this.handleScroll.bind(this);
    }

    connectedCallback() {
        this.render();
        window.addEventListener('scroll', this.handleScroll);
    }

    disconnectedCallback() {
        window.removeEventListener('scroll', this.handleScroll);
    }

    handleScroll() {
        const navbar = this.shadowRoot.querySelector('.floating-navbar');
        if (navbar) {
            // Trigger animation earlier to finish before touching banner
            if (window.scrollY > 10) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        }
    }

    render() {
        const logoSrc = this.getAttribute('logo-src') || 'assets/logo.png';

        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: block;
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
                    transition: all 0.4s ease-in-out; /* Faster animation */
                }

                .floating-navbar.scrolled {
                    width: 100%;
                    top: 0;
                    border-radius: 0;
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

                .floating-navbar.scrolled .logo-base {
                    border-top-left-radius: 0;
                    border-bottom-left-radius: 0;
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
                    padding-right: 40px;
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
                    font-size: 14px;
                    font-weight: 500;
                    transition: opacity 0.3s;
                    font-family: sans-serif;
                    white-space: nowrap;
                    display: flex;
                    align-items: center;
                    height: 100%;
                    padding: 0 5px;
                }

                .navbar-links a:hover {
                    opacity: 0.8;
                }

                /* Dropdown Styles */
                .dropdown-menu {
                    position: absolute;
                    top: 80px; /* Slight offset from center */
                    left: 50%;
                    transform: translateX(-50%) translateY(20px);
                    background: rgba(255, 255, 255, 0.95);
                    backdrop-filter: blur(10px);
                    min-width: 300px; /* Increased width */
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    padding: 10px 0;
                    list-style: none;
                    opacity: 0;
                    visibility: hidden;
                    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
                    z-index: 100;
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
                    top: 60px; /* Moves up slightly */
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
                    height: auto; 
                }

                .navbar-links a.contact-btn:hover {
                    opacity: 1;
                    transform: translateY(-2px);
                    box-shadow: 0 6px 15px rgba(0,0,0,0.3);
                    background-color: #f0f0f0;
                }
            </style>

            <nav class="floating-navbar">
                <div class="navbar-logo">
                    <!-- Base Logo -->
                    <img src="${logoSrc}" alt="Conference Logo" class="logo-base">
                    
                    <!-- Overlays -->
                    <img src="assets/rajagiri_logo.png" alt="Rajagiri" class="logo-overlay rajagiri">
                    <img src="assets/rcss_logo.png" alt="RCSS" class="logo-overlay rcss">
                </div>
                <ul class="navbar-links">
                    <li>
                        <a href="home.html">2027 ICSWHMH</a>
                        <ul class="dropdown-menu">
                            <li><a href="home.html">2027 ICSWHMH</a></li>
                            <li><a href="#">History</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="program.html">Program</a>
                        <ul class="dropdown-menu">
                            <li><a href="program.html">Program</a></li>
                            <li><a href="#">Workshops</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Speakers</a>
                        <ul class="dropdown-menu">
                            <li><a href="#">Speakers</a></li>
                            <li><a href="#">Invited</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Registration</a>
                        <ul class="dropdown-menu">
                            <li><a href="#">Registration</a></li>
                            <li><a href="#">Student</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Abstracts</a>
                        <ul class="dropdown-menu">
                            <li><a href="#">Abstracts</a></li>
                            <li><a href="#">Guidelines</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Scholarships</a>
                        <ul class="dropdown-menu">
                            <li><a href="#">Scholarships</a></li>
                            <li><a href="#">Criteria</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Social Functions</a>
                        <ul class="dropdown-menu">
                            <li><a href="#">Social Functions</a></li>
                            <li><a href="#">Tours</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Sponsorships & Exhibitions</a>
                         <ul class="dropdown-menu">
                            <li><a href="#">Sponsorships & Exhibitions</a></li>
                            <li><a href="#">Floor Plan</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Host city</a>
                        <ul class="dropdown-menu">
                            <li><a href="#">Host city</a></li>
                            <li><a href="#">Travel</a></li>
                        </ul>
                    </li>
                    <li><a href="#" class="contact-btn">Contacts</a></li>
                </ul>
            </nav>
        `;

        // Add click event listeners for mobile/click support
        const navItems = this.shadowRoot.querySelectorAll('.navbar-links > li');
        navItems.forEach(item => {
            const link = item.querySelector('a');
            if (link && item.querySelector('.dropdown-menu')) {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    // Close others
                    navItems.forEach(other => {
                        if (other !== item) other.classList.remove('active');
                    });
                    item.classList.toggle('active');
                });
            }
        });
    }
}

customElements.define('floating-navbar', FloatingNavbar);
