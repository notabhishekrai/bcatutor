<?php
require_once __DIR__ . '/../config.php';

// POST + CSRF only — a plain GET-triggered logout can be forced cross-site
// (e.g. <img src="/admin/logout.php">) by a page the admin merely visits.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

csrfCheck();
session_destroy();
header('Location: login.php');
exit;
