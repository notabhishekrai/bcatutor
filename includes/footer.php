</main>
    <footer class="site-footer">
        <div class="footer-holes" aria-hidden="true"></div>
        <p class="footer-links">
            <a href="/about">About</a>
            <a href="/contact">Contact</a>
            <a href="/privacy">Privacy Policy</a>
        </p>
        <p class="footer-meta">&copy; <?= date('Y') ?> BCA TUTOR. All rights reserved.</p>
    </footer>

    <button type="button" id="scrollTopBtn" class="scroll-top-btn no-print" aria-label="Scroll to top">&uarr;</button>
    <script>
        (function () {
            var btn = document.getElementById('scrollTopBtn');
            window.addEventListener('scroll', function () {
                btn.classList.toggle('is-visible', window.scrollY > 300);
            });
            btn.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        })();
    </script>
</body>
</html>