document.addEventListener('DOMContentLoaded', () => {
    // Scroll handling
    const navbar = document.querySelector('.floating-navbar');

    if (navbar) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }

    // Mobile/Click support for dropdowns
    const navItems = document.querySelectorAll('.navbar-links > li');
    navItems.forEach(item => {
        const link = item.querySelector('a');
        if (link && item.querySelector('.dropdown-menu')) {
            link.addEventListener('click', (e) => {
                // Only prevent default if it's a mobile view or we want click-to-toggle
                // For now, mirroring the original logic
                // e.preventDefault(); // Optional: decided to keep original behavior or not? 
                // Original had: e.preventDefault();

                // Let's check screen width or just toggle
                e.preventDefault();

                // Close others
                navItems.forEach(other => {
                    if (other !== item) other.classList.remove('active');
                });
                item.classList.toggle('active');
            });
        }
    });
});
