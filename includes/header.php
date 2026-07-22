<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>BCA TUTOR</title>
    <?php if (!empty($metaDescription)): ?>
        <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <?php endif; ?>
    <?php
    // Canonical URL — pages can override by setting $canonicalUrl before including this file;
    // otherwise it defaults to the current page's own URL.
    if (empty($canonicalUrl)) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $canonicalUrl = $protocol . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
    }
    ?>  
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/favicon-192x192.png">
    <link rel="shortcut icon" href="/assets/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
    <?php if (!empty($schemaData)): ?>
        <script type="application/ld+json"><?= json_encode($schemaData) ?></script>
    <?php endif; ?>
    <?php if (!empty($ogTitle) || !empty($ogDescription) || !empty($ogImage)): ?>
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? 'BCA TUTOR') ?>">
    <?php if (!empty($ogDescription)): ?>
        <meta property="og:description" content="<?= htmlspecialchars($ogDescription) ?>">
    <?php endif; ?>
    <?php if (!empty($ogImage)): ?>
        <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
    <?php endif; ?>
    <?php if (!empty($ogUrl)): ?>
        <meta property="og:url" content="<?= htmlspecialchars($ogUrl) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?= !empty($ogImage) ? 'summary_large_image' : 'summary' ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="site-header">
        <a href="/" class="logo">
            <span class="logo-mark"></span>
            BCA TUTOR
        </a>
        <button class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="siteNav" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav id="siteNav" class="site-nav">
            <a href="/semesters" class="nav-tab nav-tab--notes">Browse Semesters</a>
            <a href="/quizzes" class="nav-tab nav-tab--quiz">BCA Entrance</a>
            <a href="/blog" class="nav-tab nav-tab--blog">Blog</a>
            <?php if (isLoggedIn()): ?>
                <a href="/admin/dashboard.php" class="nav-link">Dashboard</a>
                <a href="/admin/quizzes.php" class="nav-link">Manage Quizzes</a>
                <a href="/admin/logout.php" class="nav-link">Logout</a>
            <?php endif; ?>
        </nav>
    </header>
    <script>
        (function () {
            var toggle = document.getElementById('navToggle');
            var nav = document.getElementById('siteNav');
            toggle.addEventListener('click', function () {
                var isOpen = nav.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                toggle.classList.toggle('is-active', isOpen);
            });
        })();
    </script>
    <main class="site-main<?= !empty($fullBleed) ? ' site-main--full' : (!empty($wideLayout) ? ' site-main--wide' : '') ?>">
