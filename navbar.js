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
                    if (!link.classList.contains('contact-btn')) {
                        link.classList.add('active');
                    }
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
                    left: 50%;
                    transform: translateX(-50%);
                    width: 97%;
                    height: 45px;
                    background-color: white;
                    z-index: 999;
                    pointer-events: none; /* Allow clicks through backdrop */
                }

                .floating-navbar {
                    position: fixed;
                    top: 20px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 97%; /* User defined */
                    height: 90px; /* User defined */
                    background: linear-gradient(to right, #F5F7F6 5%, transparent 5%), linear-gradient(135deg, #1d0a3f 0%, #4b1c9b 100%);
                    border-radius: 50px;
                    display: flex;
                    align-items: center;
                    padding: 0;
                    z-index: 1000;
                    border: none;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
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
                    left: 25px; /* Moved right */
                    top: 55%; /* Moved down */
                    transform: translateY(-50%);
                    height: 85%; /* Increased size */
                    width: auto;
                }

                li {
                    position: relative;
                    height: 100%;
                    display: flex;
                    align-items: center;
                    flex-shrink: 0;
                }

                .navbar-links {
                    margin-left: auto;
                    display: flex;
                    gap: 15px;
                    padding-right: 80px; /* Increased to avoid overlap with absolute toggle */
                    list-style: none;
                    margin-top: 0;
                    margin-bottom: 0;
                    flex-wrap: nowrap;
                    height: 100%;
                    align-items: center;
                    /* Priority+ Flex Behavior */
                    /* overflow: hidden; Removed to fix dropdown overlap */
                    flex: 1;
                    justify-content: flex-end;
                }

                .navbar-links a, .mobile-links a {
                    color: white;
                    text-decoration: none;
                    font-size: 17px;
                    font-weight: 400;
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

                /* Active link styling (Desktop & Tablet) */
                 /* We don't restrict this to min-width: 1419px anymore because we want it for the "visible" links in tablet mode too */
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

                /* Desktop/Tablet-Visible Dropdown behaviors */
                 /* Applied when the item is in the .navbar-links container */
                .navbar-links .dropdown-menu {
                    position: absolute;
                    top: 80px;
                    left: 50%;
                    transform: translateX(-50%) translateY(20px);
                    background: rgba(255, 255, 255, 0.95);
                }

                .navbar-links .dropdown-menu::before {
                    content: '';
                    position: absolute;
                    top: -6px;
                    left: 50%;
                    transform: translateX(-50%);
                    border-left: 6px solid transparent;
                    border-right: 6px solid transparent;
                    border-bottom: 6px solid rgba(255, 255, 255, 0.95);
                }

                .navbar-links li:hover .dropdown-menu,
                .navbar-links li.active .dropdown-menu {
                    opacity: 1;
                    visibility: visible;
                    transform: translateX(-50%) translateY(0);
                    top: 60px;
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
                    font-size: 15px; 
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

                /* Button Styles (Visible & Mobile) */
                .navbar-links a.contact-btn, .mobile-links a.contact-btn {
                    background-color: white;
                    color: #1d0a3f;
                    padding: 12px 30px;
                    border-radius: 50px;
                    font-weight: 600;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
                    transition: transform 0.3s ease;
                    height: auto;
                    line-height: normal;
                    text-transform: capitalize;
                    letter-spacing: normal;
                    position: relative;
                    overflow: hidden;
                    z-index: 1;
                }

                .navbar-links a.contact-btn::before, .mobile-links a.contact-btn::before {
                    content: "";
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: #C9A227;
                    z-index: -1;
                    transition: clip-path 0.4s ease-out;
                    clip-path: circle(0% at 0 50%);
                }

                .navbar-links a.contact-btn:hover::before, .mobile-links a.contact-btn:hover::before {
                    clip-path: circle(150% at 0 50%);
                }

                .navbar-links a.contact-btn:hover, .mobile-links a.contact-btn:hover {
                    opacity: 1;
                    transform: translateY(-2px);
                    box-shadow: 0 6px 15px rgba(0,0,0,0.3);
                }

                /* Mobile toggle button (hamburger) */
                .mobile-toggle {
                    display: none; /* Controlled by JS */
                    flex-direction: column;
                    gap: 5px;
                    cursor: pointer;
                    padding: 10px;
                    /* Fixed Positioning Logic */
                    position: absolute;
                    right: 20px;
                    top: 50%;
                    transform: translateY(-50%);
                    /* margin-left: auto; REMOVED */
                    /* margin-right: 20px; REMOVED */
                    z-index: 1001;
                    /* flex-shrink: 0; REMOVED (not needed for absolute) */
                }

                .mobile-toggle span {
                    display: block;
                    width: 25px;
                    height: 3px;
                    background-color: white;
                    border-radius: 3px;
                    transition: all 0.3s ease;
                }

                /* Mobile Overflow Menu Container */
                .mobile-links {
                    position: absolute;
                    top: 100%;
                    left: 0;
                    width: 100%;
                    background: #1d0a3f;
                    flex-direction: column;
                    padding: 20px 0 40px 0;
                    height: auto;
                    max-height: calc(100vh - 90px);
                    overflow-y: auto;
                    border-radius: 0 0 20px 20px;
                    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
                    opacity: 0;
                    visibility: hidden;
                    transform: translateY(-10px);
                    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
                    display: flex;
                    z-index: 999;
                    list-style: none;
                }

                .mobile-links.active {
                    opacity: 1;
                    visibility: visible;
                    transform: translateY(0);
                }

                .mobile-links li {
                    width: 100%;
                    height: auto;
                    flex-direction: column;
                    align-items: flex-start;
                }
                
                .mobile-links > li > a {
                    width: 100%;
                    padding: 15px 30px;
                    box-sizing: border-box;
                    justify-content: flex-start;
                }

                /* Fix Contact Button in Mobile Menu */
                .mobile-links a.contact-btn {
                    width: fit-content !important;
                    margin-left: 30px;
                    margin-bottom: 20px;
                    display: inline-flex;
                    justify-content: center;
                }

                /* Submenus in Overflow/Mobile */
                .mobile-links .dropdown-menu {
                    position: static;
                    transform: none;
                    opacity: 1;
                    visibility: visible;
                    display: block;
                    max-height: 0;
                    overflow: hidden;
                    width: 100%;
                    background: transparent;
                    box-shadow: none;
                    border-radius: 0;
                    padding: 0;
                    transition: max-height 0.4s ease;
                }

                .mobile-links .dropdown-menu::before { content: none; }
                
                .mobile-links li.active .dropdown-menu {
                    max-height: 500px;
                }

                .mobile-links .dropdown-menu li a {
                    color: rgba(255, 255, 255, 0.8);
                    padding: 12px 30px 12px 60px;
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

                /* Conference title displayed only on mobile headers */
                .mobile-title {
                    display: none;
                    color: white;
                    font-family: 'Outfit', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                    font-size: 20px;
                    font-weight: 500;
                    margin-left: 20px;
                    white-space: nowrap;
                    text-decoration: none;
                }

                /* Logo container specifically for mobile view */
                .logo-container-mobile {
                    display: none;
                    height: 100%;
                    /* background removed */
                    padding: 0;
                    align-items: center;
                    position: relative;
                    z-index: 2;
                }

                .logo-mobile-base {
                    height: 100%;
                    width: auto;
                    object-fit: cover;
                }

                .logo-mobile-overlay {
                    position: absolute;
                    z-index: 10;
                    height: 85%;
                    width: auto;
                    left: 25px;
                    top: 55%;
                    transform: translateY(-50%);
                }

                /* 
                    STRICT Mobile Override (< 768px) 
                   Forces everything into the overflow/hamburger 
                */
                @media (max-width: 768px) {
                    .navbar-logo { display: none; }
                    .mobile-title { 
                        display: block;
                        margin-left: auto; /* Push to right */
                        padding-right: 70px; /* Space for absolute toggle */
                    }
                    .mobile-toggle { 
                        display: flex !important; 
                        /* Keep absolute positioning via base class */
                    }
                    
                     .logo-container-mobile {
                        display: flex;
                    }
                     /* .logo-container-mobile img.rajagiri-mobile REMOVED - styles are now in base class */
                     
                     .floating-navbar {
                        width: 100% !important;
                        border-radius: 0 !important;
                         background: #1d0a3f;
                        top: 0px;
                     }
                     .navbar-links {
                         display: none; /* Hide visible strip entirely */
                     }
                }
            </style>

            <div class="navbar-backdrop"></div>
            <nav class="floating-navbar">
                <div class="logo-container-mobile">
                    <!-- Mobile View: Replicated Desktop Logo Structure -->
                    <img src="${logoSrc}" alt="Conference Logo" class="logo-mobile-base">
                    <a href="https://rajagiri.edu/" target="_blank">
                        <!-- Use class logo-mobile-overlay instead of rajagiri-mobile -->
                        <img src="assets/rajagiri_logo.png" alt="Rajagiri" class="logo-mobile-overlay">
                    </a>
                </div>

                <a href="index.html" class="mobile-title">2027 ICSWHMH</a>

                <div class="navbar-logo">
                    <!-- Base Logo -->
                    <img src="${logoSrc}" alt="Conference Logo" class="logo-base">
                    <!-- Overlays -->
                    <a href="https://rajagiri.edu/" target="_blank">
                        <img src="assets/rajagiri_logo.png" alt="Rajagiri" class="logo-overlay rajagiri">
                    </a>
                </div>

                <!-- Visible Links (Priority+) -->
                <ul class="navbar-links" id="navbar-links">
                    <li><a href="index.html">2027 ICSWHMH</a></li>
                    <li>
                        <a href="program.html">Program</a>
                        <ul class="dropdown-menu">
                            <li><a href="topics.html">Conference Topics</a></li>
                            <li><a href="ministerialopening.html">Ministerial Opening</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Speakers</a>
                        <ul class="dropdown-menu">
                            <li><a href="speaker.html">Speakers list</a></li>
                            <li><a href="committee.html">Committee</a></li>
                        </ul>
                    </li>
                    <li><a href="registration.html">Registration</a></li>
                    <li><a href="abstract.html">Abstract Submission</a></li>
                    <li>
                        <a href="#">Host city</a>
                        <ul class="dropdown-menu">
                            <li><a href="hotels.html">Hotels</a></li>
                            <li><a href="attractions.html">Attractions</a></li>
                        </ul>
                    </li>
                    <li><a href="contact-us.html" class="contact-btn">Contact Us</a></li>
                </ul>

                <div class="mobile-toggle" id="mobile-toggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <!-- Overflow Links -->
                <ul class="mobile-links" id="mobile-links">
                    <!-- Javascript will move items here -->
                </ul>
            </nav>
        `;

        this.initPriorityPlus();
    }

    initPriorityPlus() {
        const navbar = this.shadowRoot.querySelector('.floating-navbar');
        const visibleLinks = this.shadowRoot.getElementById('navbar-links');
        const overflowLinks = this.shadowRoot.getElementById('mobile-links');
        const toggle = this.shadowRoot.getElementById('mobile-toggle');
        const logo = this.shadowRoot.querySelector('.navbar-logo');

        // 0. Initialize indices for strict ordering
        Array.from(visibleLinks.children).forEach((li, index) => {
            li.dataset.index = index;
        });

        let isToggling = false; // Debounce/Throttle flag

        // 1. Toggle Button Logic
        toggle.addEventListener('click', () => {
            toggle.classList.toggle('active');
            overflowLinks.classList.toggle('active');
        });

        // 2. Overflow Menu Dropdown Logic (Delegation)
        overflowLinks.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (link && link.parentElement.querySelector('.dropdown-menu')) {
                e.preventDefault();
                // Close other active keys in overflow
                Array.from(overflowLinks.children).forEach(li => {
                    if (li !== link.parentElement) li.classList.remove('active');
                });
                link.parentElement.classList.toggle('active');
            }
        });

        // Helper: Move all items to visible and sort them
        const resetToVisible = () => {
            while (overflowLinks.firstElementChild) {
                visibleLinks.appendChild(overflowLinks.firstElementChild);
            }
            const items = Array.from(visibleLinks.children);
            items.sort((a, b) => parseInt(a.dataset.index) - parseInt(b.dataset.index));
            items.forEach(item => visibleLinks.appendChild(item));
        };

        // 3. Main Priority+ Logic
        const checkPriorityPlus = () => {
            if (isToggling) return;
            isToggling = true;

            const width = window.innerWidth;

            // --- SCENARIO A: DESKTOP (> 1418px) ---
            // STRICTLY keep everything in visible links. No hamburger.
            if (width > 1418) {
                // Move everything back to visible
                while (overflowLinks.firstElementChild) {
                    // Prepend to maintain order if we moved from end? 
                    // Actually, let's just move everything back and let the DOM order sort itself out if we are careful.
                    // If we move items strictly from right-to-left into overflow, we should append them back.
                    // But we used prepend logic in mobile. Let's be safe: 
                    // We need to restore original order.
                    // Simplified: Just move everything to visible.
                    visibleLinks.appendChild(overflowLinks.firstElementChild);
                }

                // Sort by data-index if we wanted strict ordering, but simplistic append works if we empty overflow cleanly.
                // We need to ensure we don't mess up order. 
                // Strategy: always move from overflow -> visible, then check space.

                toggle.style.display = 'none';
                overflowLinks.classList.remove('active');
                toggle.classList.remove('active');
                isToggling = false;
                return;
            }

            // --- SCENARIO B: STRICT MOBILE (<= 768px) ---
            if (width <= 768) {
                resetToVisible();
                while (visibleLinks.firstElementChild) {
                    overflowLinks.appendChild(visibleLinks.firstElementChild);
                }
                toggle.style.display = 'flex';
                isToggling = false;
                return;
            }

            // --- SCENARIO C: TABLET / SMALL DESKTOP (768px < w <= 1418px) ---
            // Dynamic Priority+ behavior.

            // 1. Reset Phase: Move everything to visible and sort
            resetToVisible();

            toggle.style.display = 'none';
            overflowLinks.classList.remove('active');
            toggle.classList.remove('active');

            // 2. Measure and Move Phase
            const navbarWidth = navbar.clientWidth;
            const logoWidth = logo.getBoundingClientRect().width;
            const toggleReservedWidth = 50; // Width of toggle button
            const padding = 60; // Safety padding

            // Available space for links
            const availableSpace = navbarWidth - logoWidth - toggleReservedWidth - padding;

            let currentUsedWidth = 0;
            const visibleChildren = Array.from(visibleLinks.children);
            let overflowStarted = false;

            for (let i = 0; i < visibleChildren.length; i++) {
                const item = visibleChildren[i];
                const itemWidth = item.offsetWidth + 15; // Width + Gap

                if (!overflowStarted) {
                    currentUsedWidth += itemWidth;
                    // If this item pushes us over the limit
                    if (currentUsedWidth > availableSpace) {
                        overflowStarted = true;
                        // Move this item and all subsequent to overflow
                        overflowLinks.appendChild(item);
                    }
                } else {
                    // Already overflowing, just move it
                    overflowLinks.appendChild(item);
                }
            }

            // 3. Show toggle if we have items in overflow
            if (overflowLinks.children.length > 0) {
                toggle.style.display = 'flex';
            }

            isToggling = false;
        };

        // Resize Observer
        const ro = new ResizeObserver(() => {
            // Use requestAnimationFrame for smooth UI updates
            requestAnimationFrame(checkPriorityPlus);
        });
        ro.observe(navbar);
        ro.observe(document.body);

        // Initial check
        setTimeout(checkPriorityPlus, 50);
        // Image load check
        const logos = this.shadowRoot.querySelectorAll('img');
        logos.forEach(img => img.onload = checkPriorityPlus);
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
