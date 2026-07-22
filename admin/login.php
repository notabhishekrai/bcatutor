<?php
require_once __DIR__ . '/../config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SESSION['login_attempts'] >= 5) {
    $error = 'Too many failed attempts. Please wait a few minutes and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['login_attempts'] = 0;
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        header('Location: dashboard.php');
        exit;
    } else {
        $_SESSION['login_attempts']++;
        $error = 'Invalid username or password.';
    }
}

$pageTitle = 'Admin Login';
require __DIR__ . '/../includes/header.php';
?>

<h1>Admin Login</h1>

<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <label>Username
        <input type="text" name="username" required autofocus>
    </label>
    <label>Password
        <input type="password" name="password" required>
    </label>
    <button type="submit">Log In</button>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
