    <!-- Footer -->
    <footer class="text-center py-4 text-muted small mt-5">
        <div class="container">
            DNS Author Manager &copy; <?= date('Y') ?>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        /**
         * Robust Copy to Clipboard function
         * @param {string} text - The text to copy
         * @param {HTMLElement} btn - The button element for feedback
         */
        function copyToClipboard(text, btn) {
            if (!text) return;
            const originalText = btn ? btn.innerHTML : null;

            function showSuccess() {
                if (!btn) return;
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
                btn.classList.add('btn-success', 'text-white');
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.classList.remove('btn-success', 'text-white');
                }, 2000);
            }

            // Try modern API first (requires HTTPS)
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(showSuccess).catch(fallback);
            } else {
                fallback();
            }

            function fallback() {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.left = "-9999px";
                textArea.style.top = "0";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    const successful = document.execCommand('copy');
                    if (successful) showSuccess();
                } catch (err) {}
                document.body.removeChild(textArea);
            }
        }
    </script>
</body>
</html>