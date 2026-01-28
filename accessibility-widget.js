class AccessibilityWidget extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this.isOpen = false;

        // Initialize state from localStorage or default
        this.state = JSON.parse(localStorage.getItem('accessibilityState')) || {
            textSize: 1, // 0: normal, 1: large, 2: extra large
            contrast: 'normal', // normal, high, invert
            links: false,
            grayscale: false,
            cursor: false,
            readableFont: false
        };
    }

    connectedCallback() {
        this.render();
        this.applySettings(); // Apply saved settings on load
        this.setupEventListeners();
    }

    applySettings() {
        const doc = document.documentElement;

        // Text Size
        doc.classList.remove('access-text-large', 'access-text-xlarge');
        if (this.state.textSize === 1) doc.classList.add('access-text-large');
        if (this.state.textSize === 2) doc.classList.add('access-text-xlarge');

        // Contrast
        doc.classList.remove('access-high-contrast', 'access-invert');
        if (this.state.contrast === 'high') doc.classList.add('access-high-contrast');
        if (this.state.contrast === 'invert') doc.classList.add('access-invert');

        // Other toggles
        this.state.grayscale ? doc.classList.add('access-grayscale') : doc.classList.remove('access-grayscale');
        this.state.links ? doc.classList.add('access-links') : doc.classList.remove('access-links');
        this.state.cursor ? doc.classList.add('access-cursor') : doc.classList.remove('access-cursor');
        this.state.readableFont ? doc.classList.add('access-font') : doc.classList.remove('access-font');

        // Save to local storage
        localStorage.setItem('accessibilityState', JSON.stringify(this.state));

        // Update Shadow DOM components
        this.updateShadowComponents();
    }

    updateShadowComponents() {
        const components = [
            document.querySelector('floating-navbar'),
            document.querySelector('main-footer')
        ];

        components.forEach(comp => {
            if (comp && comp.shadowRoot) {
                // Remove existing style if any
                const existingStyle = comp.shadowRoot.getElementById('accessibility-shadow-style');
                if (existingStyle) existingStyle.remove();

                // Add new style
                const style = document.createElement('style');
                style.id = 'accessibility-shadow-style';
                style.textContent = this.getShadowStyles();
                comp.shadowRoot.appendChild(style);
            }
        });
    }

    getShadowStyles() {
        let styles = '';

        // Text Size
        if (this.state.textSize === 1) {
            styles += `
                :host { font-size: 110% !important; }
                a, p, span, h3, li { font-size: 1.1em !important; line-height: 1.4 !important; }
                .navbar-links a, .mobile-links a { font-size: 1.1em !important; }
            `;
        }
        if (this.state.textSize === 2) {
            styles += `
                :host { font-size: 125% !important; }
                a, p, span, h3, li { font-size: 1.25em !important; line-height: 1.4 !important; }
                .navbar-links a, .mobile-links a { font-size: 1.25em !important; }
            `;
        }

        // Grayscale (apply to host to cover everything)
        if (this.state.grayscale) styles += ':host { filter: grayscale(100%) !important; }';

        // High Contrast
        if (this.state.contrast === 'high') {
            styles += `
                :host { 
                    filter: contrast(125%); 
                    background-color: #000 !important;
                    color: #fff !important;
                }
                * {
                    background-color: #000 !important;
                    color: #ff0 !important;
                    border-color: #fff !important;
                }
                a { color: #0ff !important; text-decoration: underline !important; }
                div, nav, ul, li { background-color: #000 !important; }
                img, svg { filter: grayscale(100%) contrast(120%); }
                .navbar-logo, .footer-logo { filter: grayscale(100%) contrast(120%); }
                .floating-navbar, .footer-container { background: #000 !important; }
            `;
        }

        // Invert
        if (this.state.contrast === 'invert') {
            styles += 'img, video { filter: invert(100%) !important; }';
        }

        // Links
        if (this.state.links) {
            styles += 'a { text-decoration: underline !important; font-weight: bold !important; color: #d63384 !important; }';
        }

        // Readable Font
        if (this.state.readableFont) {
            styles += '* { font-family: Arial, Helvetica, sans-serif !important; letter-spacing: 0.05em !important; word-spacing: 0.1em !important; }';
        }

        return styles;
    }

    getStyles() {
        return `
            <style>
                :host {
                    position: fixed;
                    bottom: 120px; /* Above the scroll button (which is bottom: 40px + 60px height + 20px gap) */
                    right: 40px;
                    z-index: 10000;
                    font-family: 'Inter', sans-serif;
                }

                /* Floating Action Button */
                .fab {
                    width: 50px;
                    height: 50px;
                    border-radius: 50%;
                    background-color: #1D0A3F;
                    color: #fff;
                    border: 2px solid #C9A227;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
                    transition: transform 0.3s ease;
                }

                .fab:hover {
                    transform: scale(1.1);
                }

                .fab svg {
                    width: 28px;
                    height: 28px;
                    fill: currentColor;
                }

                /* Panel */
                .panel {
                    position: absolute;
                    bottom: 70px;
                    right: 0;
                    width: 300px;
                    background: #fff;
                    border-radius: 12px;
                    padding: 20px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    display: none;
                    flex-direction: column;
                    gap: 15px;
                    border: 1px solid #eee;
                    opacity: 0;
                    transform: translateY(20px);
                    transition: opacity 0.3s ease, transform 0.3s ease;
                }

                .panel.open {
                    display: flex;
                    opacity: 1;
                    transform: translateY(0);
                }

                h3 {
                    margin: 0 0 10px 0;
                    font-size: 1.1rem;
                    color: #1D0A3F;
                    border-bottom: 2px solid #C9A227;
                    padding-bottom: 5px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .reset-btn {
                    font-size: 0.8rem;
                    color: #d63384;
                    background: none;
                    border: none;
                    cursor: pointer;
                    text-decoration: underline;
                }

                .control-group {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .label {
                    font-size: 0.9rem;
                    color: #333;
                    font-weight: 500;
                }

                /* Toggles/Buttons */
                .btn-group {
                    display: flex;
                    gap: 5px;
                }

                button.option-btn {
                    padding: 6px 12px;
                    border: 1px solid #ccc;
                    background: #f8f9fa;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 0.85rem;
                    transition: all 0.2s;
                }

                button.option-btn.active {
                    background: #1D0A3F;
                    color: white;
                    border-color: #1D0A3F;
                }

                /* Toggle Switch */
                .switch {
                    position: relative;
                    display: inline-block;
                    width: 46px;
                    height: 24px;
                }

                .switch input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }

                .slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #ccc;
                    transition: .4s;
                    border-radius: 24px;
                }

                .slider:before {
                    position: absolute;
                    content: "";
                    height: 16px;
                    width: 16px;
                    left: 4px;
                    bottom: 4px;
                    background-color: white;
                    transition: .4s;
                    border-radius: 50%;
                }

                input:checked + .slider {
                    background-color: #1D0A3F;
                }

                input:checked + .slider:before {
                    transform: translateX(22px);
                }

            </style>
        `;
    }

    getGlobalStyles() {
        // These are injected into the document head to affect the page
        return `
            <style id="accessibility-global-styles">
                /* Font Size */
                html.access-text-large { font-size: 110% !important; }
                html.access-text-xlarge { font-size: 125% !important; }

                /* Grayscale */
                html.access-grayscale { filter: grayscale(100%) !important; }

                /* High Contrast */
                html.access-high-contrast { 
                    filter: contrast(125%); 
                    background-color: #000 !important;
                    color: #fff !important;
                }
                html.access-high-contrast * {
                    background-color: #000 !important;
                    color: #ff0 !important; /* Yellow text */
                    border-color: #fff !important;
                }
                html.access-high-contrast img { filter: grayscale(100%) contrast(120%); }

                /* Invert */
                html.access-invert { filter: invert(100%) !important; }
                html.access-invert img, html.access-invert video { filter: invert(100%) !important; }

                /* Links */
                html.access-links a { 
                    text-decoration: underline !important;
                    font-weight: bold !important;
                    color: #d63384 !important; /* High vis pink/magenta */
                }

                /* Cursor */
                html.access-cursor * { cursor: crosshair !important; }

                /* Readable Font */
                html.access-font * { font-family: Arial, Helvetica, sans-serif !important; letter-spacing: 0.05em !important; word-spacing: 0.1em !important; }
            </style>
        `;
    }

    render() {
        // Inject global styles if not present
        if (!document.getElementById('accessibility-global-styles')) {
            document.head.insertAdjacentHTML('beforeend', this.getGlobalStyles());
        }

        const personIcon = `
            <svg viewBox="0 0 24 24">
                <circle cx="12" cy="7" r="4" />
                <path d="M12 13c-5 0-9 3-9 8h18c0-5-4-8-9-8z" />
            </svg>
        `;

        this.shadowRoot.innerHTML = `
            ${this.getStyles()}
            <button class="fab" aria-label="Accessibility Settings">
                ${personIcon}
            </button>
            <div class="panel">
                <h3>Accessibility <button class="reset-btn">Reset</button></h3>
                
                <div class="control-group">
                    <span class="label">Text Size</span>
                    <div class="btn-group">
                        <button class="option-btn ${this.state.textSize === 0 ? 'active' : ''}" data-action="text-0">A</button>
                        <button class="option-btn ${this.state.textSize === 1 ? 'active' : ''}" data-action="text-1">A+</button>
                        <button class="option-btn ${this.state.textSize === 2 ? 'active' : ''}" data-action="text-2">A++</button>
                    </div>
                </div>

                <div class="control-group">
                    <span class="label">Grayscale</span>
                    <label class="switch">
                        <input type="checkbox" ${this.state.grayscale ? 'checked' : ''} data-toggle="grayscale">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="control-group">
                    <span class="label">Highlight Links</span>
                    <label class="switch">
                        <input type="checkbox" ${this.state.links ? 'checked' : ''} data-toggle="links">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="control-group">
                    <span class="label">Readable Font</span>
                    <label class="switch">
                        <input type="checkbox" ${this.state.readableFont ? 'checked' : ''} data-toggle="readableFont">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="control-group">
                    <span class="label">Contrast</span>
                    <div class="btn-group">
                        <button class="option-btn ${this.state.contrast === 'normal' ? 'active' : ''}" data-contrast="normal">Norm</button>
                        <button class="option-btn ${this.state.contrast === 'high' ? 'active' : ''}" data-contrast="high">High</button>
                        <button class="option-btn ${this.state.contrast === 'invert' ? 'active' : ''}" data-contrast="invert">Inv</button>
                    </div>
                </div>
            </div>
        `;
    }

    setupEventListeners() {
        const fab = this.shadowRoot.querySelector('.fab');
        const panel = this.shadowRoot.querySelector('.panel');
        const resetBtn = this.shadowRoot.querySelector('.reset-btn');

        // Toggle Panel
        fab.addEventListener('click', () => {
            this.isOpen = !this.isOpen;
            panel.classList.toggle('open', this.isOpen);
            fab.setAttribute('aria-expanded', this.isOpen);
        });

        // Close when clicking outside (on document)
        document.addEventListener('click', (e) => {
            if (this.isOpen && !this.contains(e.target)) {
                this.isOpen = false;
                panel.classList.remove('open');
            }
        });

        // Text Size Buttons
        this.shadowRoot.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                if (action === 'text-0') this.state.textSize = 0;
                if (action === 'text-1') this.state.textSize = 1;
                if (action === 'text-2') this.state.textSize = 2;
                this.updateUI();
                this.applySettings();
            });
        });

        // Contrast Buttons
        this.shadowRoot.querySelectorAll('[data-contrast]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.state.contrast = e.target.dataset.contrast;
                this.updateUI();
                this.applySettings();
            });
        });

        // Toggles
        this.shadowRoot.querySelectorAll('input[type="checkbox"]').forEach(input => {
            input.addEventListener('change', (e) => {
                const toggle = e.target.dataset.toggle;
                this.state[toggle] = e.target.checked;
                this.applySettings();
            });
        });

        // Reset
        resetBtn.addEventListener('click', () => {
            this.state = {
                textSize: 0,
                contrast: 'normal',
                links: false,
                grayscale: false,
                cursor: false,
                readableFont: false
            };
            this.updateUI();
            this.applySettings();
        });
    }

    updateUI() {
        // Update Text Buttons
        this.shadowRoot.querySelectorAll('[data-action]').forEach(btn => {
            const action = btn.dataset.action;
            const level = parseInt(action.split('-')[1]);
            btn.classList.toggle('active', this.state.textSize === level);
        });

        // Update Contrast Buttons
        this.shadowRoot.querySelectorAll('[data-contrast]').forEach(btn => {
            btn.classList.toggle('active', this.state.contrast === btn.dataset.contrast);
        });

        // Update Checkboxes
        this.shadowRoot.querySelector('[data-toggle="grayscale"]').checked = this.state.grayscale;
        this.shadowRoot.querySelector('[data-toggle="links"]').checked = this.state.links;
        this.shadowRoot.querySelector('[data-toggle="readableFont"]').checked = this.state.readableFont;
    }
}

customElements.define('accessibility-widget', AccessibilityWidget);
