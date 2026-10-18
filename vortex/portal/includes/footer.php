        </div><!-- /.portal-body -->
    </main><!-- /.portal-main -->
</div><!-- /.portal-layout -->

<!-- Toast Alert -->
<div id="portalToast" class="portal-toast"></div>

<script>
function showToast(message, type = 'info') {
    const toast = document.getElementById('portalToast');
    if (!toast) return;
    toast.innerText = message;
    toast.style.display = 'block';
    if (type === 'error') {
        toast.style.background = '#991b1b';
    } else if (type === 'success') {
        toast.style.background = '#166534';
    } else {
        toast.style.background = '#0f172a';
    }
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3200);
}

function copyToClipboard(text, btnElement) {
    if (!navigator.clipboard) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    } else {
        navigator.clipboard.writeText(text);
    }
    
    if (btnElement) {
        const origText = btnElement.innerText;
        btnElement.innerText = '✓ Copied';
        setTimeout(() => {
            btnElement.innerText = origText;
        }, 1800);
    }
    showToast('Copied to clipboard!', 'success');
}
</script>

</body>
</html>
