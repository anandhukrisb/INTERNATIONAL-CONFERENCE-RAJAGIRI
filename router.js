
document.addEventListener('DOMContentLoaded', () => {
    // Intercept clicks on links
    document.body.addEventListener('click', e => {
        const link = e.target.closest('a');
        if (link && link.href && link.host === window.location.host) {
            // Check if it's a download link or hash link on same page
            const isDownload = link.hasAttribute('download');
            const isHash = link.getAttribute('href').startsWith('#');
            const isTargetBlank = link.target === '_blank';

            if (!isDownload && !isHash && !isTargetBlank) {
                e.preventDefault();
                const url = link.href;
                navigateTo(url);
            }
        }
    });

    // Handle back/forward buttons
    window.addEventListener('popstate', () => {
        loadPage(window.location.href, false);
    });
});

async function navigateTo(url) {
    history.pushState(null, null, url);
    await loadPage(url, true);
}

async function loadPage(url, scrollTop = true) {
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error('Page not found');

        const text = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(text, 'text/html');

        // Extract content from the target page
        const newContent = doc.querySelector('#app-content');
        const currentContent = document.querySelector('#app-content');

        if (newContent && currentContent) {
            // Ensure custom elements are fully loaded before transitioning
            await Promise.all([
                customElements.whenDefined('floating-navbar'),
                customElements.whenDefined('main-footer')
            ]);

            // Check if View Transitions API is supported
            if (document.startViewTransition) {
                // Add marker class to disable page load animations during transition
                document.documentElement.classList.add('active-view-transition');

                // Use View Transitions API for smooth page transitions
                const transition = document.startViewTransition(() => {
                    // Update DOM
                    currentContent.innerHTML = newContent.innerHTML;
                    document.title = doc.title;

                    // Scroll to top if needed
                    if (scrollTop) window.scrollTo(0, 0);

                    // Update Navbar Active State
                    const navbar = document.querySelector('floating-navbar');
                    if (navbar && navbar.highlightActiveLink) {
                        navbar.highlightActiveLink();
                    }
                });

                // Optional: Handle transition completion
                try {
                    await transition.finished;
                } catch (error) {
                    console.error('Transition error:', error);
                } finally {
                    // Remove marker class after transition completes
                    document.documentElement.classList.remove('active-view-transition');
                }
            } else {
                // Fallback for browsers without View Transitions API support
                currentContent.innerHTML = newContent.innerHTML;
                document.title = doc.title;

                if (scrollTop) window.scrollTo(0, 0);

                const navbar = document.querySelector('floating-navbar');
                if (navbar && navbar.highlightActiveLink) {
                    navbar.highlightActiveLink();
                }
            }
        } else {
            console.error('Could not find #app-content in target or source page');
            // Fallback to full reload if structure is missing
            window.location.reload();
        }

    } catch (err) {
        console.error('Navigation error:', err);
    }
}
