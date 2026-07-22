<?php
require_once __DIR__ . '/config.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Honeypot: a field hidden from real users via CSS, but bots often fill in anyway
    $honeypot = trim($_POST['website'] ?? '');

    if ($honeypot !== '') {
        // Silently pretend success to the bot, but don't actually send anything
        $success = true;
    } elseif ($name === '' || $email === '' || $message === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        require __DIR__ . '/lib/PHPMailer/Exception.php';
        require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
        require __DIR__ . '/lib/PHPMailer/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // Mail credentials live in config.php (git-ignored) — see MAIL_* constants there.
            $mail->isSMTP();

            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom(MAIL_USERNAME, 'BCA TUTOR Contact Form');
            $mail->addAddress(MAIL_TO);
            $mail->addReplyTo($email, $name);
        
            $mail->Subject = 'BCA TUTOR contact form: ' . $name;
            $mail->Body = "Name: $name\nEmail: $email\n\nMessage:\n$message";
        
            $mail->send();
            $success = true;
        } 
        
         catch (Exception $e) {
            $error = 'Something went wrong sending your message. Please try emailing directly instead.';
        }
    }
 }

$pageTitle = 'Contact';
$metaDescription = 'Contact information and forms';
require __DIR__ . '/includes/header.php';
?>

<h1>Contact</h1>

<?php if ($success): ?>
    <p class="success">Thanks — your message has been sent. We'll get back to you soon.</p>
<?php else: ?>
    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" class="admin-form">
        <div style="position:absolute; left:-9999px;">
            <label>Leave this empty
                <input type="text" name="website" tabindex="-1" autocomplete="off">
            </label>
        </div>

        <label>Name
            <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </label>
        <label>Email
            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </label>
        <label>Message
            <textarea name="message" rows="6" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
        </label>
        <button type="submit">Send Message</button>
    </form>

    <p>Or email directly: <a href="mailto:contact@bcatutor.com">contact@bcatutor.com</a></p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>