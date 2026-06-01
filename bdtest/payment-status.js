/**
 * ================================================================
 * BILLDESK PAYMENT STATUS — JavaScript
 * Shared by response.php and retrieve.php
 * ================================================================
 *
 * Handles:
 *   • Card entrance animation (Intersection Observer)
 *   • Keyboard trap and ESC support inside the status card
 *   • Ripple effect on buttons
 *   • Auto-countdown (inline script in response.php feeds this)
 *   • Focus management on page load
 * ================================================================
 */

(function () {
    'use strict';

    // ── DOM refs ───────────────────────────────────────────────
    var card    = document.querySelector('.status-card');
    var buttons = document.querySelectorAll('.btn');
    var body    = document.body;
    var status  = body.dataset.status || 'error';

    // ── 1. Focus the card heading on load ──────────────────────
    // Helps screen-reader users understand what happened
    // immediately without requiring them to navigate.
    window.addEventListener('load', function () {
        var title = document.getElementById('status-title');
        if (title) {
            title.setAttribute('tabindex', '-1');
            title.focus();
        }
    });

    // ── 2. Keyboard trap inside the card ──────────────────────
    // When the card is the only interactive region on the page,
    // trapping focus inside it prevents accidental navigation
    // to browser chrome via Tab.
    if (card) {
        card.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab') return;

            var focusable = Array.from(
                card.querySelectorAll(
                    'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
                )
            );

            if (focusable.length === 0) return;

            var first = focusable[0];
            var last  = focusable[focusable.length - 1];

            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });
    }

    // ── 3. Ripple effect on buttons ────────────────────────────
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var rect   = btn.getBoundingClientRect();
            var ripple = document.createElement('span');

            ripple.style.cssText = [
                'position:absolute',
                'border-radius:50%',
                'background:rgba(255,255,255,0.35)',
                'width:10px',
                'height:10px',
                'transform:scale(0)',
                'animation:ripple 500ms ease-out forwards',
                'left:' + (e.clientX - rect.left - 5) + 'px',
                'top:'  + (e.clientY - rect.top  - 5) + 'px',
                'pointer-events:none',
                'z-index:1',
            ].join(';');

            btn.appendChild(ripple);
            setTimeout(function () { ripple.remove(); }, 600);
        });
    });

    // Inject ripple keyframe once
    if (!document.getElementById('bd-ripple-style')) {
        var style       = document.createElement('style');
        style.id        = 'bd-ripple-style';
        style.textContent = '@keyframes ripple{to{transform:scale(30);opacity:0}}';
        document.head.appendChild(style);
    }

    // ── 4. Pending auto-refresh offer ─────────────────────────
    // If status is "pending", offer the user an auto-recheck
    // after 60 seconds with a dismissible toast.
    if (status === 'pending') {

        var orderid = (function () {
            var m = window.location.search.match(/[?&]orderid=([^&]+)/);
            return m ? decodeURIComponent(m[1]) : '';
        }());

        if (orderid) {
            setTimeout(function () {
                showToast(
                    'Still waiting? You can check your payment status again.',
                    'Check Now',
                    function () {
                        window.location.href = 'retrieve.php?orderid=' + encodeURIComponent(orderid);
                    }
                );
            }, 60000); // 60 seconds
        }
    }

    // ── 5. Toast notification helper ──────────────────────────
    function showToast(message, actionLabel, actionFn) {

        var toast = document.createElement('div');
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');
        toast.setAttribute('aria-atomic', 'true');

        toast.style.cssText = [
            'position:fixed',
            'bottom:24px',
            'left:50%',
            'transform:translateX(-50%) translateY(80px)',
            'background:#1D0A3F',
            'color:#fff',
            'font-family:Inter,sans-serif',
            'font-size:0.875rem',
            'padding:14px 20px',
            'border-radius:12px',
            'box-shadow:0 8px 32px rgba(29,10,63,0.3)',
            'display:flex',
            'align-items:center',
            'gap:16px',
            'max-width:90vw',
            'z-index:9999',
            'transition:transform 300ms cubic-bezier(0.16,1,0.3,1)',
        ].join(';');

        var msg = document.createElement('span');
        msg.textContent = message;

        var btn = document.createElement('button');
        btn.textContent = actionLabel;
        btn.style.cssText = [
            'background:#C9A227',
            'color:#1D0A3F',
            'border:none',
            'border-radius:99px',
            'padding:6px 14px',
            'font-family:Outfit,sans-serif',
            'font-weight:700',
            'font-size:0.8rem',
            'cursor:pointer',
            'white-space:nowrap',
            'flex-shrink:0',
        ].join(';');

        btn.addEventListener('click', function () {
            dismissToast(toast);
            if (typeof actionFn === 'function') actionFn();
        });

        var close = document.createElement('button');
        close.setAttribute('aria-label', 'Dismiss');
        close.textContent = '×';
        close.style.cssText = [
            'background:transparent',
            'border:none',
            'color:rgba(255,255,255,0.6)',
            'font-size:1.2rem',
            'cursor:pointer',
            'padding:0 4px',
        ].join(';');
        close.addEventListener('click', function () { dismissToast(toast); });

        toast.appendChild(msg);
        toast.appendChild(btn);
        toast.appendChild(close);
        document.body.appendChild(toast);

        // Slide in
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                toast.style.transform = 'translateX(-50%) translateY(0)';
            });
        });

        // Auto-dismiss after 10 seconds
        setTimeout(function () { dismissToast(toast); }, 10000);
    }

    function dismissToast(toast) {
        if (!toast.parentNode) return;
        toast.style.transform = 'translateX(-50%) translateY(80px)';
        setTimeout(function () { toast.remove(); }, 350);
    }

}());
