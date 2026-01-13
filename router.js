
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
        // We assume the content is inside <main id="app-content"> or we take body content excluding nav/footer
        // Based on plan, we are wrapping content in <main id="app-content">
        const newContent = doc.querySelector('#app-content');
        const currentContent = document.querySelector('#app-content');

        if (newContent && currentContent) {
            // Fade out
            currentContent.style.opacity = '0';
            currentContent.style.transition = 'opacity 0.2s ease';

            setTimeout(() => {
                currentContent.innerHTML = newContent.innerHTML;
                document.title = doc.title;

                // Re-run scripts if necessary? 
                // Mostly static content, but if there are card flips we might need to re-attach listeners 
                // In this simple site, inline handlers like onclick="flipCard()" usually work 
                // but if they rely on DOMContentLoaded they won't trigger. 
                // Currently they are inline onclicks, so they should work fine.

                // Scroll to top
                if (scrollTop) window.scrollTo(0, 0);

                // Fade in
                currentContent.style.opacity = '1';

                // Update Navbar Active State
                const navbar = document.querySelector('floating-navbar');
                if (navbar && navbar.highlightActiveLink) {
                    navbar.highlightActiveLink();
                }
            }, 200);

        } else {
            console.error('Could not find #app-content in target or source page');
            // Fallback to full reload if structure is missing
            window.location.reload();
        }

    } catch (err) {
        console.error('Navigation error:', err);
    }
}
