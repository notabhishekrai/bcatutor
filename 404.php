<?php
require_once __DIR__ . '/config.php';
http_response_code(404);
$pageTitle = 'Page Not Found';
require __DIR__ . '/includes/header.php';
?>

<div style="text-align:center; padding: 40px 0;">
    <p style="font-family:'IBM Plex Mono', monospace; font-size:0.85rem; color:var(--muted); letter-spacing:0.06em;">ERROR 404</p>
    <h1>Page not found</h1>
    <p style="color:var(--muted); max-width:40ch; margin:0 auto 28px;">
        The page you're looking for doesn't exist, or may have been moved.
    </p>
    <div class="home-links" style="justify-content:center;">
        <a href="/index.php" class="button">Back to Home</a>
        <a href="/semesters.php" class="button">Browse Notes & Solutions</a>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>