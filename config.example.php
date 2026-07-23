<?php
// ====== EDIT THESE 4 LINES with your Hostinger database details ======
// Find these in hPanel > Databases > MySQL Databases
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name_here');
define('DB_USER', 'your_database_username_here');
define('DB_PASS', 'your_database_password_here');
// =======================================================================

// ====== Contact form outgoing mail (Hostinger email account) ======
// Find/create this under hPanel > Emails. Keep real values only in
// config.php (git-ignored) — never commit real mail credentials.
define('MAIL_HOST', 'smtp.hostinger.com');
define('MAIL_USERNAME', 'your_mailbox@yourdomain.com');
define('MAIL_PASSWORD', 'your_mailbox_password_here');
define('MAIL_TO', 'your_mailbox@yourdomain.com');
// =======================================================================

date_default_timezone_set('Asia/Kathmandu'); // change if you like

// ---- Security hardening ----
// Session cookie protections: JS can't read it, only sent over HTTPS,
// and not sent along with cross-site requests.
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');

// Don't leak PHP errors (file paths, DB details) to visitors in production.
// If you need to debug an issue, temporarily comment these two lines out,
// reload the page to see the real error, then put them back.
ini_set('display_errors', 0);
error_reporting(0);

session_start();

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    // Never echo $e->getMessage() here — PDO connection errors often include the
    // DB host/username, and this die() runs before display_errors is relevant
    // (it's an explicit message, not a suppressed PHP error/warning).
    die("Something went wrong loading this page. Please try again shortly.");
}

function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function slugify($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

// validate quill
function isContentEmpty($html) {
    // Decode entities like &nbsp; first, so a post that's just
    // whitespace/non-breaking-spaces doesn't count as "real" content
    $text = strip_tags(html_entity_decode($html, ENT_QUOTES, 'UTF-8'));
    $text = str_replace("\xC2\xA0", ' ', $text); // non-breaking space -> regular space
    return trim($text) === '';
}

// ---- Post content sanitization ----
// strip_tags() only removes disallowed tags — it does NOT touch attributes
// on tags it keeps, so `<img src=x onerror=alert(1)>` sails right through
// since <img> is allowed. This walks the DOM after strip_tags and drops
// every attribute except an explicit per-tag allowlist, and blocks
// javascript:/data:/vbscript: URLs on the ones that remain (href/src).
function sanitizeHtml($html) {
    $allowedTags = '<p><h1><h2><h3><h4><strong><em><u><s><ul><ol><li><a><img><br><blockquote><code><pre><span><div><table><thead><tbody><tr><td><th>';
    $html = strip_tags($html, $allowedTags);

    if (trim($html) === '') {
        return $html;
    }

    $allowedAttributes = [
        'a' => ['href'],
        'img' => ['src', 'alt'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(
        '<?xml encoding="utf-8"?><div>' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    foreach ($dom->getElementsByTagName('*') as $node) {
        $tag = strtolower($node->nodeName);
        $keep = $allowedAttributes[$tag] ?? [];

        $toRemove = [];
        foreach ($node->attributes as $attr) {
            $name = strtolower($attr->nodeName);
            if (!in_array($name, $keep, true)) {
                $toRemove[] = $attr->nodeName;
                continue;
            }
            if (in_array($name, ['href', 'src'], true)) {
                // Allowlist, not a blocklist: a scheme blocklist like "reject javascript:"
                // is bypassable with embedded control characters (e.g. "jav\tascript:") that
                // browsers strip when parsing the URL but a naive regex won't catch. Stripping
                // the same tab/newline/CR characters before validating closes that gap.
                $normalizedUrl = preg_replace('/[\t\r\n]/', '', $attr->nodeValue);
                if ($normalizedUrl !== '' && !preg_match('~^(https?://|mailto:|tel:|/|#)~i', trim($normalizedUrl))) {
                    $toRemove[] = $attr->nodeName;
                }
            }
            if (in_array($name, ['colspan', 'rowspan'], true) && !ctype_digit($attr->nodeValue)) {
                $toRemove[] = $attr->nodeName;
            }
        }
        foreach ($toRemove as $name) {
            $node->removeAttribute($name);
        }
    }

    $container = $dom->getElementsByTagName('div')->item(0);
    $result = '';
    foreach ($container->childNodes as $child) {
        $result .= $dom->saveHTML($child);
    }
    return $result;
}

// ---- CSRF protection ----
// Call csrfToken() to print a token into a form's hidden input.
// Call csrfCheck() at the top of any POST handler to verify it matches.
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfCheck() {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('Invalid request (CSRF check failed). Go back and try again.');
    }
}
