        </main>
        </div>
    </div>

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
