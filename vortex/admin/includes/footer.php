        </main>
        </div>
    </div>

    <!-- Create Event Modal (Global) -->
    <div class="modal-overlay" id="createEventModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-box">
            <div class="modal-header">
                <h2 id="modalTitle">Create New Event</h2>
                <button class="modal-close" id="closeEventModal" title="Close">&times;</button>
            </div>

            <form action="<?= $adminBase ?? '' ?>events/index.php" method="POST">
                <input type="hidden" name="action" value="create_event">

                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: span 2;">
                            <label for="modal_event_name">Event Name</label>
                            <input type="text" id="modal_event_name" name="event_name" class="form-control"
                                   placeholder="e.g. International AI Summit 2026" required>
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label for="modal_department">Department / Host</label>
                            <input type="text" id="modal_department" name="department" class="form-control"
                                   placeholder="e.g. Computer Science Dept" required>
                        </div>
                        <div class="form-group">
                            <label for="modal_start_date">Start Date</label>
                            <input type="date" id="modal_start_date" name="start_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="modal_end_date">End Date</label>
                            <input type="date" id="modal_end_date" name="end_date" class="form-control" required>
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label for="modal_webhook_url">Webhook Delivery URL (Optional)</label>
                            <input type="url" id="modal_webhook_url" name="webhook_url" class="form-control"
                                   placeholder="e.g. https://dyuti.in/webhook.php">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="cancelEventModal">Cancel</button>
                    <button type="submit" class="btn-submit-event">
                        Create Event
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEventModal(e) {
        if(e) e.preventDefault();
        const modal = document.getElementById('createEventModal');
        if(modal) { modal.classList.add('open'); document.body.style.overflow = 'hidden'; }
    }
    
    function closeEventModal(e) {
        if(e) e.preventDefault();
        const modal = document.getElementById('createEventModal');
        if(modal) { modal.classList.remove('open'); document.body.style.overflow = ''; }
    }

    (function () {
        const modal = document.getElementById('createEventModal');
        const closeBtn = document.getElementById('closeEventModal');
        const cancelBtn = document.getElementById('cancelEventModal');

        if(closeBtn) closeBtn.addEventListener('click', closeEventModal);
        if(cancelBtn) cancelBtn.addEventListener('click', closeEventModal);

        // Close on overlay click
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeEventModal();
            });
        }

        // Close on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && modal.classList.contains('open')) closeEventModal();
        });

        // Auto-open modal if there's a validation error returned (i.e. create_event was the last action)
        <?php if (isset($flashError) && $flashError && str_contains($flashError ?? '', 'event')): ?>
        openEventModal();
        <?php endif; ?>
    }());
    </script>

    <script>
        // Universal copy helper supporting text string or element reference with visual button feedback
        function copyToClipboard(target, btnElement) {
            let textToCopy = '';
            if (typeof target === 'string') {
                textToCopy = target;
            } else if (typeof target === 'object' && target !== null) {
                textToCopy = target.innerText || target.textContent || '';
            } else if (document.getElementById(target)) {
                const el = document.getElementById(target);
                textToCopy = el.innerText || el.textContent || '';
            }

            if (!textToCopy) return;

            function showSuccessFeedback() {
                if (!btnElement) return;
                const origText = btnElement.getAttribute('data-orig-text') || btnElement.innerHTML;
                if (!btnElement.getAttribute('data-orig-text')) {
                    btnElement.setAttribute('data-orig-text', origText);
                }
                btnElement.classList.add('copied');
                btnElement.innerHTML = '✓ Copied!';
                setTimeout(() => {
                    btnElement.innerHTML = btnElement.getAttribute('data-orig-text');
                    btnElement.classList.remove('copied');
                }, 1800);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(showSuccessFeedback).catch(err => {
                    fallbackCopy(textToCopy, showSuccessFeedback);
                });
            } else {
                fallbackCopy(textToCopy, showSuccessFeedback);
            }
        }

        function fallbackCopy(text, callback) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                if (callback) callback();
            } catch (e) {
                console.error('Fallback copy failed', e);
            }
            document.body.removeChild(textarea);
        }
    </script>
    <style>
        @keyframes btn-spinner-anim {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .vortex-btn-spinner {
            display: inline-block;
            width: 1em;
            height: 1em;
            margin-right: 8px;
            vertical-align: middle;
            animation: btn-spinner-anim 1s linear infinite;
        }
        .btn-loading-state {
            opacity: 0.8;
            cursor: not-allowed !important;
            pointer-events: none;
        }
    </style>
    <script>
        document.addEventListener('submit', function(e) {
            if (e.target.tagName === 'FORM') {
                const form = e.target;
                const submitBtn = form.querySelector('button[type="submit"]:focus') || form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    if (submitBtn.dataset.submitting) return;
                    submitBtn.dataset.submitting = 'true';
                    submitBtn.classList.add('btn-loading-state');
                    
                    const spinnerSvg = `<svg class="vortex-btn-spinner" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-dasharray="80" stroke-dashoffset="60"></circle></svg>`;
                    
                    submitBtn.innerHTML = `<span style="display: inline-flex; align-items: center; justify-content: center;">${spinnerSvg}<span>${submitBtn.innerHTML}</span></span>`;
                }
            }
        });
    </script>

    <!-- Omnisearch & Keyboard Shortcuts Controller -->
    <script>
    (function() {
        const searchInput = document.getElementById('topbarSearchInput');
        const dropdown = document.getElementById('topbarSearchDropdown');
        const adminBase = '<?= $adminBase ?? '' ?>';
        let debounceTimer = null;
        let selectedIndex = -1;

        // 1. Global Keyboard Shortcuts (/ and Ctrl+K / Cmd+K)
        document.addEventListener('keydown', function(e) {
            // Ignore if active element is an input, textarea, or contenteditable
            const isTyping = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) || document.activeElement?.isContentEditable;
            
            if (e.key === '/' && !isTyping) {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            } else if (e.key === 'Escape') {
                if (dropdown) dropdown.style.display = 'none';
                if (searchInput && document.activeElement === searchInput) {
                    searchInput.blur();
                }
            }
        });

        if (!searchInput || !dropdown) return;

        // 2. Debounced Autocomplete
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const q = this.value.trim();

            if (q.length < 2) {
                dropdown.style.display = 'none';
                dropdown.innerHTML = '';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`${adminBase}search_ajax.php?q=${encodeURIComponent(q)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success || data.total_matches === 0) {
                            dropdown.innerHTML = `
                                <div style="padding: 16px; text-align: center; color: #94a3b8; font-size: 0.85rem;">
                                    No direct matches found for "<strong>${escapeHtml(q)}</strong>".<br>
                                    <span style="font-size: 0.78rem;">Press Enter to search entire archive.</span>
                                </div>
                            `;
                            dropdown.style.display = 'block';
                            return;
                        }

                        let html = '';

                        // Transactions
                        if (data.transactions && data.transactions.length > 0) {
                            html += `
                                <div class="search-dropdown-section">
                                    <div class="search-dropdown-header">
                                        <span>💳 Transactions</span>
                                        <span style="font-size: 0.7rem; font-weight: 600;">${data.transactions.length}</span>
                                    </div>
                            `;
                            data.transactions.forEach(tx => {
                                const statusColor = tx.status === 'SUCCESS' ? '#16a34a' : (tx.status === 'FAILED' ? '#dc2626' : '#d97706');
                                html += `
                                    <a href="${adminBase}${tx.url}" class="search-dropdown-item">
                                        <div>
                                            <div style="font-weight: 600; font-family: monospace; font-size: 0.82rem;">${escapeHtml(tx.vortex_id)}</div>
                                            <div style="font-size: 0.75rem; color: #64748b;">${escapeHtml(tx.customer_email)} &bull; ${escapeHtml(tx.event_name)}</div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-weight: 700; font-size: 0.85rem;">${escapeHtml(tx.amount_fmt)}</div>
                                            <span style="font-size: 0.68rem; font-weight: 700; color: ${statusColor};">${escapeHtml(tx.status)}</span>
                                        </div>
                                    </a>
                                `;
                            });
                            html += `</div>`;
                        }

                        // Clients
                        if (data.clients && data.clients.length > 0) {
                            html += `
                                <div class="search-dropdown-section">
                                    <div class="search-dropdown-header">
                                        <span>🔌 Merchants</span>
                                        <span style="font-size: 0.7rem; font-weight: 600;">${data.clients.length}</span>
                                    </div>
                            `;
                            data.clients.forEach(c => {
                                html += `
                                    <a href="${adminBase}${c.url}" class="search-dropdown-item">
                                        <div>
                                            <div style="font-weight: 600; font-size: 0.85rem;">${escapeHtml(c.client_name)}</div>
                                            <div style="font-size: 0.75rem; color: #64748b;">${escapeHtml(c.email)}</div>
                                        </div>
                                        <span class="badge ${c.is_active ? 'badge-success' : 'badge-danger'}" style="font-size: 0.7rem;">
                                            ${c.is_active ? 'Active' : 'Disabled'}
                                        </span>
                                    </a>
                                `;
                            });
                            html += `</div>`;
                        }

                        // Events
                        if (data.events && data.events.length > 0) {
                            html += `
                                <div class="search-dropdown-section">
                                    <div class="search-dropdown-header">
                                        <span>🎟️ Events</span>
                                        <span style="font-size: 0.7rem; font-weight: 600;">${data.events.length}</span>
                                    </div>
                            `;
                            data.events.forEach(ev => {
                                html += `
                                    <a href="${adminBase}${ev.url}" class="search-dropdown-item">
                                        <div>
                                            <div style="font-weight: 600; font-size: 0.85rem;">${escapeHtml(ev.event_name)}</div>
                                            <div style="font-size: 0.75rem; color: #64748b;">${escapeHtml(ev.department)}</div>
                                        </div>
                                        <span class="badge ${ev.is_active ? 'badge-success' : 'badge-danger'}" style="font-size: 0.7rem;">
                                            ${ev.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </a>
                                `;
                            });
                            html += `</div>`;
                        }

                        // Footer
                        html += `
                            <div class="search-dropdown-footer" onclick="document.getElementById('topbarSearchForm').submit();">
                                View all ${data.total_matches} results for "${escapeHtml(q)}" &rarr;
                            </div>
                        `;

                        dropdown.innerHTML = html;
                        dropdown.style.display = 'block';
                        selectedIndex = -1;
                    })
                    .catch(err => {
                        console.error('Search error', err);
                    });
            }, 220);
        });

        // 3. Arrow Navigation in Dropdown
        searchInput.addEventListener('keydown', function(e) {
            const items = dropdown.querySelectorAll('.search-dropdown-item');
            if (dropdown.style.display !== 'block' || items.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = (selectedIndex + 1) % items.length;
                updateSelection(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = (selectedIndex - 1 + items.length) % items.length;
                updateSelection(items);
            } else if (e.key === 'Enter' && selectedIndex >= 0 && items[selectedIndex]) {
                e.preventDefault();
                items[selectedIndex].click();
            }
        });

        function updateSelection(items) {
            items.forEach((item, idx) => {
                if (idx === selectedIndex) {
                    item.classList.add('selected');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('selected');
                }
            });
        }

        // 4. Click Outside to Close
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    })();
    </script>

    <!-- Google Dark Mode Theme Controller -->
    <script>
    (function() {
        const toggleBtn = document.getElementById('themeToggleBtn');
        if (!toggleBtn) return;

        function updateToggleState(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            toggleBtn.setAttribute('title', theme === 'dark' ? 'Switch to Light mode (Google Drive Light)' : 'Switch to Dark mode (Google Dark)');
            toggleBtn.setAttribute('aria-label', theme === 'dark' ? 'Switch to Light mode' : 'Switch to Dark mode');
        }

        // Sync initial state
        const currentTheme = document.documentElement.getAttribute('data-theme') || localStorage.getItem('vortex_theme') || 'light';
        updateToggleState(currentTheme);

        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            const next = current === 'dark' ? 'light' : 'dark';

            localStorage.setItem('vortex_theme', next);
            document.cookie = "vortex_theme=" + next + "; path=/; max-age=31536000; SameSite=Lax";
            updateToggleState(next);

            window.dispatchEvent(new CustomEvent('vortexThemeChanged', { detail: { theme: next } }));
        });
    })();
    </script>
    <!-- In-App Notification Center Controller -->
    <script>
    (function() {
        const bellBtn = document.getElementById('notifBellBtn');
        const dropdown = document.getElementById('notifDropdown');
        const badge = document.getElementById('notifBadge');
        const sidebarBadge = document.getElementById('sidebarNotifBadge');
        const dropdownBadge = document.getElementById('notifDropdownBadge');
        const itemsContainer = document.getElementById('notifItemsContainer');
        const markAllReadBtn = document.getElementById('notifMarkAllReadBtn');

        if (!bellBtn || !dropdown) return;

        // Determine base path dynamically
        const currentPath = window.location.pathname;
        const inSubdir = currentPath.includes('/admin/payments/') || currentPath.includes('/admin/events/') || currentPath.includes('/admin/api_clients/');
        const notifApiUrl = (inSubdir ? '../' : '') + 'notifications_ajax.php';
        const adminBase = inSubdir ? '../' : '';

        function updateBadges(count) {
            const num = parseInt(count, 10) || 0;
            const text = num > 99 ? '99+' : String(num);

            if (badge) {
                if (num > 0) {
                    badge.textContent = text;
                    badge.style.display = 'inline-flex';
                } else {
                    badge.style.display = 'none';
                }
            }
            if (sidebarBadge) {
                if (num > 0) {
                    sidebarBadge.textContent = text;
                    sidebarBadge.style.display = 'inline-block';
                } else {
                    sidebarBadge.style.display = 'none';
                }
            }
            if (dropdownBadge) {
                dropdownBadge.textContent = num + ' unread';
            }
        }

        function renderNotifications(items) {
            if (!itemsContainer) return;
            if (!items || items.length === 0) {
                itemsContainer.innerHTML = `
                    <div style="text-align: center; padding: 36px 20px; color: var(--text-gray);">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.5; margin-bottom: 8px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <div style="font-size: 0.88rem; font-weight: 600; color: var(--text-dark);">No unread notifications</div>
                        <div style="font-size: 0.78rem; margin-top: 2px;">Gateway alerts will appear here</div>
                    </div>
                `;
                return;
            }

            let html = '';
            items.forEach(item => {
                const isUnread = item.is_read === 0;
                let iconSvg = '';
                let colorBg = 'rgba(26, 115, 232, 0.12)';
                let colorText = '#1a73e8';

                if (item.type === 'failed_webhook') {
                    colorBg = 'rgba(234, 67, 53, 0.14)';
                    colorText = '#ea4335';
                    iconSvg = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path><line x1="2" y1="2" x2="22" y2="22"></line></svg>`;
                } else if (item.type === 'new_dispute') {
                    colorBg = 'rgba(251, 188, 4, 0.18)';
                    colorText = '#b06000';
                    iconSvg = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>`;
                } else if (item.type === 'high_value_transaction') {
                    colorBg = 'rgba(52, 168, 83, 0.16)';
                    colorText = '#188038';
                    iconSvg = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>`;
                } else {
                    iconSvg = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`;
                }

                const targetLink = item.link ? (adminBase + item.link.replace(/^\.\.\//, '')) : (adminBase + 'notifications.php');

                html += `
                    <div class="notif-dropdown-item ${isUnread ? 'unread' : 'read'}" data-id="${item.id}" data-link="${targetLink}">
                        <div class="notif-item-icon" style="background: ${colorBg}; color: ${colorText};">
                            ${iconSvg}
                        </div>
                        <div class="notif-item-content">
                            <div class="notif-item-title">${escapeHtml(item.title)}</div>
                            <div class="notif-item-msg">${escapeHtml(item.message)}</div>
                            <div class="notif-item-time">${escapeHtml(item.time_ago)}</div>
                        </div>
                        ${isUnread ? '<div class="notif-unread-dot"></div>' : ''}
                    </div>
                `;
            });
            itemsContainer.innerHTML = html;

            // Item click handler
            itemsContainer.querySelectorAll('.notif-dropdown-item').forEach(itemEl => {
                itemEl.addEventListener('click', function(e) {
                    const id = this.getAttribute('data-id');
                    const link = this.getAttribute('data-link');

                    // Fire mark as read in background
                    fetch(notifApiUrl + '?action=mark_read&id=' + id, { method: 'POST' })
                        .catch(() => {});

                    if (link) {
                        window.location.href = link;
                    }
                });
            });
        }

        function loadNotificationsSummary(forceSync = false) {
            const url = notifApiUrl + '?action=get_summary' + (forceSync ? '&sync=1' : '');
            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        updateBadges(data.unread_count);
                        renderNotifications(data.items);
                    }
                })
                .catch(err => {
                    if (itemsContainer) {
                        itemsContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-gray); font-size: 0.8rem;">Unable to load alerts</div>';
                    }
                });
        }

        // Toggle Dropdown
        bellBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const userDropdown = document.getElementById('userProfileDropdown');
            if (userDropdown) {
                userDropdown.style.display = 'none';
                const userBtn = document.getElementById('userProfileDropdownBtn');
                if (userBtn) userBtn.setAttribute('aria-expanded', 'false');
                const userWrap = userBtn?.closest('.topbar-user-wrapper');
                if (userWrap) userWrap.classList.remove('open');
            }

            const isOpen = dropdown.style.display === 'block';
            if (isOpen) {
                dropdown.style.display = 'none';
                bellBtn.setAttribute('aria-expanded', 'false');
            } else {
                dropdown.style.display = 'block';
                bellBtn.setAttribute('aria-expanded', 'true');
                loadNotificationsSummary(false);
            }
        });

        // Close on Click Outside
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !bellBtn.contains(e.target)) {
                dropdown.style.display = 'none';
                bellBtn.setAttribute('aria-expanded', 'false');
            }
        });

        // Mark All Read
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                fetch(notifApiUrl + '?action=mark_all_read', { method: 'POST' })
                    .then(res => res.json())
                    .then(data => {
                        updateBadges(0);
                        loadNotificationsSummary(false);
                    })
                    .catch(() => {});
            });
        }

        // Initial fetch on page load
        loadNotificationsSummary(true);

        // Background poll every 30 seconds
        setInterval(function() {
            fetch(notifApiUrl + '?action=poll')
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        updateBadges(data.unread_count);
                    }
                })
                .catch(() => {});
        }, 30000);

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    })();
    </script>

    <!-- Admin User Profile Dropdown Controller -->
    <script>
    (function() {
        const profileBtn = document.getElementById('userProfileDropdownBtn');
        const dropdown = document.getElementById('userProfileDropdown');
        if (!profileBtn || !dropdown) return;

        const wrapper = profileBtn.closest('.topbar-user-wrapper');
        const notifDropdown = document.getElementById('notifDropdown');
        const notifBellBtn = document.getElementById('notifBellBtn');

        function closeProfileDropdown() {
            dropdown.style.display = 'none';
            profileBtn.setAttribute('aria-expanded', 'false');
            if (wrapper) wrapper.classList.remove('open');
        }

        function openProfileDropdown() {
            // Close notification dropdown if open
            if (notifDropdown) {
                notifDropdown.style.display = 'none';
                if (notifBellBtn) notifBellBtn.setAttribute('aria-expanded', 'false');
            }
            dropdown.style.display = 'block';
            profileBtn.setAttribute('aria-expanded', 'true');
            if (wrapper) wrapper.classList.add('open');
        }

        profileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const isOpen = dropdown.style.display === 'block';
            if (isOpen) {
                closeProfileDropdown();
            } else {
                openProfileDropdown();
            }
        });

        // Close on Click Outside
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !profileBtn.contains(e.target)) {
                closeProfileDropdown();
            }
        });

        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProfileDropdown();
            }
        });
    })();
    </script>
</body>
</html>

