        </main>
        </div>
    </div>

    <!-- Create Event Modal (Global) -->
    <div class="modal-overlay" id="createEventModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-box">
            <div class="modal-header">
                <h2 id="modalTitle">🎟️ Create New Event</h2>
                <button class="modal-close" id="closeEventModal" title="Close">&times;</button>
            </div>

            <form action="<?= $adminBase ?? '' ?>dashboard.php" method="POST">
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
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="cancelEventModal">Cancel</button>
                    <button type="submit" class="btn-submit-event">
                        <span>✚</span> Create Event
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
</body>
</html>
