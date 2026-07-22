<?php
// ====== EDIT THESE 4 LINES with your Hostinger database details ======
// Find these in hPanel > Databases > MySQL Databases
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name_here');
define('DB_USER', 'your_database_username_here');
define('DB_PASS', 'your_database_password_here');
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
    die("Database connection failed. Check config.php details. (" . $e->getMessage() . ")");
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
