<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $slug = slugify(trim($_POST['slug'] ?? ''));
    if ($slug === '') {
        $slug = slugify($title);
    }

    $questionTexts = $_POST['question_text'] ?? [];
    $questionImages = $_POST['question_image'] ?? [];
    $optionA = $_POST['option_a'] ?? [];
    $optionB = $_POST['option_b'] ?? [];
    $optionC = $_POST['option_c'] ?? [];
    $optionD = $_POST['option_d'] ?? [];
    $correctOption = $_POST['correct_option'] ?? [];

    // Build a clean list of valid questions, skipping/rejecting incomplete ones
    $questions = [];
    foreach ($questionTexts as $idx => $text) {
        $text = trim($text);
        $image = trim($questionImages[$idx] ?? '');
        // Only ever trust images that came from our own uploader — upload-image.php
        // always produces exactly this shape, so anything else (including a value
        // tampered with to break out of an HTML attribute) is rejected outright.
        if ($image !== '' && !preg_match('~^/uploads/[a-f0-9]{32}\.webp$~', $image)) {
            $image = '';
        }
        $a = trim($optionA[$idx] ?? '');
        $b = trim($optionB[$idx] ?? '');
        $c = trim($optionC[$idx] ?? '');
        $d = trim($optionD[$idx] ?? '');
        $correct = $correctOption[$idx] ?? '';

        // A completely empty block (never filled in) is silently skipped
        if ($text === '' && $a === '' && $b === '' && $c === '' && $d === '' && $image === '') {
            continue;
        }

        if ($text === '' || $a === '' || $b === '' || $c === '' || $d === '' || !in_array($correct, ['a', 'b', 'c', 'd'], true)) {
            $error = 'Every question needs question text, all 4 options filled in, and a correct answer selected.';
            break;
        }

        $questions[] = [$text, $image, $a, $b, $c, $d, $correct];
    }

    if ($title === '') {
        $error = 'Title is required.';
    } elseif ($slug === '') {
        $error = 'Could not generate a valid slug — please enter one manually.';
    } elseif (empty($questions) && $error === '') {
        $error = 'Add at least one complete question.';
    }

    if ($error === '') {
        $check = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE slug = ?");
        $check->execute([$slug]);
        if ($check->fetchColumn() > 0) {
            $error = 'That URL slug is already in use — please choose a different one.';
        } else {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO quizzes (title, slug, description, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $description, $_SESSION['admin_id']]);
            $quizId = $pdo->lastInsertId();

            $qStmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question_text, question_image, option_a, option_b, option_c, option_d, correct_option, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($questions as $order => $q) {
                $qStmt->execute([$quizId, $q[0], $q[1] !== '' ? $q[1] : null, $q[2], $q[3], $q[4], $q[5], $q[6], $order]);
            }

            $pdo->commit();

            header('Location: dashboard.php?tab=quizzes');
            exit;
        }
    }
}

$pageTitle = 'New Quiz';
$wideLayout = true;
require __DIR__ . '/../includes/header.php';
?>

<h1>New Quiz</h1>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js"></script>

<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" class="admin-form" id="quiz-form">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <label>Title
        <input type="text" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
    </label>

    <label>URL Slug (auto-filled from title — edit if you want a cleaner URL)
        <input type="text" name="slug" id="slug-input" value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>" pattern="[a-z0-9\-]+" title="Lowercase letters, numbers, and hyphens only">
        <span class="field-hint">yourdomain.com/take-quiz.php?slug=<span id="slug-preview"></span></span>
    </label>

    <label>Description (shown on the quiz listing and before someone starts)
        <textarea name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </label>

    <div class="admin-field">
        <span class="admin-field-label">Questions</span>
        <div class="math-toolbar">
            <span class="math-toolbar-label">Insert math (click a question/option field first, then a symbol):</span>
            <button type="button" class="math-btn" data-before="$\frac{a}{b}$" data-after="">Fraction</button>
            <button type="button" class="math-btn" data-before="$x^{2}$" data-after="">Exponent</button>
            <button type="button" class="math-btn" data-before="$a^{n}$" data-after="">Superscript</button>
            <button type="button" class="math-btn" data-before="$x_{1}$" data-after="">Subscript</button>
            <button type="button" class="math-btn" data-before="$\sqrt{x}$" data-after="">&radic; Surd</button>
            <button type="button" class="math-btn" data-before="$\sqrt[n]{x}$" data-after="">&#8319;&radic; Root</button>
            <button type="button" class="math-btn" data-before="$\sum_{i=1}^{n}$" data-after="">&sum; Summation</button>
            <select class="math-select" id="matrix-type-select">
                <option value="" selected disabled>Matrix &#9662;</option>
                <option value="$\begin{bmatrix} a &amp; b \\ c &amp; d \end{bmatrix}$">2&times;2 Matrix</option>
                <option value="$\begin{bmatrix} a &amp; b &amp; c \\ d &amp; e &amp; f \\ g &amp; h &amp; i \end{bmatrix}$">3&times;3 Matrix</option>
                <option value="$\begin{bmatrix} a &amp; b &amp; c \\ d &amp; e &amp; f \end{bmatrix}$">2&times;3 Matrix</option>
                <option value="$\begin{bmatrix} a &amp; b \\ c &amp; d \\ e &amp; f \end{bmatrix}$">3&times;2 Matrix</option>
                <option value="$\begin{bmatrix} a &amp; b &amp; c \end{bmatrix}$">Row Vector (1&times;3)</option>
                <option value="$\begin{bmatrix} a \\ b \\ c \end{bmatrix}$">Column Vector (3&times;1)</option>
                <option value="$\begin{bmatrix} 1 &amp; 0 \\ 0 &amp; 1 \end{bmatrix}$">Identity (2&times;2)</option>
                <option value="$\begin{bmatrix} 1 &amp; 0 &amp; 0 \\ 0 &amp; 1 &amp; 0 \\ 0 &amp; 0 &amp; 1 \end{bmatrix}$">Identity (3&times;3)</option>
            </select>
        </div>
        <div id="questions-wrap"></div>
        <button type="button" id="add-question-btn" class="button-secondary">+ Add Question</button>
    </div>

    <div class="form-actions">
        <button type="submit">Publish Quiz</button>
        <a href="dashboard.php?tab=quizzes" class="button-secondary" id="cancel-link">Cancel</a>
    </div>
</form>

<?php
// Cache-busting: without this, a browser that cached quiz-builder.js before
// a feature was added (e.g. the matrix dropdown) keeps silently running the
// old script — same fix as the style.css versioning in includes/header.php.
$quizBuilderVersion = @filemtime(__DIR__ . '/../assets/quiz-builder.js') ?: time();
?>
<script src="/assets/quiz-builder.js?v=<?= $quizBuilderVersion ?>"></script>
<script>
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['question_text'])): ?>
    // Redisplay whatever was submitted, so a validation error doesn't wipe out the admin's work
    const existingQuestions = <?= json_encode(array_map(function ($idx) {
        return [
            'text' => $_POST['question_text'][$idx] ?? '',
            'image' => $_POST['question_image'][$idx] ?? '',
            'a' => $_POST['option_a'][$idx] ?? '',
            'b' => $_POST['option_b'][$idx] ?? '',
            'c' => $_POST['option_c'][$idx] ?? '',
            'd' => $_POST['option_d'][$idx] ?? '',
            'correct' => $_POST['correct_option'][$idx] ?? '',
        ];
    }, array_keys($_POST['question_text'])), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    existingQuestions.forEach(addQuestionBlock);
<?php else: ?>
    addQuestionBlock();
<?php endif; ?>

// slug preview
var titleInput = document.querySelector('input[name="title"]');
var slugInput = document.getElementById('slug-input');
var slugPreview = document.getElementById('slug-preview');
var slugManuallyEdited = slugInput.value.trim() !== '';

function slugify(text) {
    return text.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}

titleInput.addEventListener('input', function () {
    if (!slugManuallyEdited) {
        slugInput.value = slugify(titleInput.value);
    }
    slugPreview.textContent = slugInput.value;
});

slugInput.addEventListener('input', function () {
    slugManuallyEdited = slugInput.value.trim() !== '';
    slugPreview.textContent = slugInput.value;
});

slugPreview.textContent = slugInput.value;

document.getElementById('cancel-link').addEventListener('click', function (e) {
    var hasTitle = titleInput.value.trim() !== '';
    if (hasTitle) {
        var confirmed = confirm('You have unsaved changes. Discard them and go back?');
        if (!confirmed) {
            e.preventDefault();
        }
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
