<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if (!password_verify($current, $admin['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
        $update->execute([$newHash, $_SESSION['admin_id']]);
        $success = true;
    }
}

$pageTitle = 'Change Password';
require __DIR__ . '/../includes/header.php';
?>

<h1>Change Password</h1>

<?php if ($success): ?>
    <p class="success">Password updated successfully.</p>
<?php else: ?>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="POST" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <label>Current Password
            <input type="password" name="current_password" required>
        </label>
        <label>New Password
            <input type="password" name="new_password" required minlength="8">
        </label>
        <label>Confirm New Password
            <input type="password" name="confirm_password" required minlength="8">
        </label>
        <button type="submit">Update Password</button>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>