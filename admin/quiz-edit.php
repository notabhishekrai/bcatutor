<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: dashboard.php?tab=quizzes');
    exit;
}

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
        $check = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE slug = ? AND id != ?");
        $check->execute([$slug, $id]);
        if ($check->fetchColumn() > 0) {
            $error = 'That URL slug is already in use — please choose a different one.';
        } else {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE quizzes SET title = ?, slug = ?, description = ? WHERE id = ?");
            $stmt->execute([$title, $slug, $description, $id]);

            // Simplest correct way to sync questions: wipe and reinsert.
            // The FK is ON DELETE CASCADE so this only ever touches quiz_questions.
            $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?")->execute([$id]);

            $qStmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question_text, question_image, option_a, option_b, option_c, option_d, correct_option, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($questions as $order => $q) {
                $qStmt->execute([$id, $q[0], $q[1] !== '' ? $q[1] : null, $q[2], $q[3], $q[4], $q[5], $q[6], $order]);
            }

            $pdo->commit();

            header('Location: dashboard.php?tab=quizzes');
            exit;
        }
    }
}

// Use posted values if this is a redisplay after a validation error,
// otherwise fall back to what's saved in the database.
$currentTitle = $_POST['title'] ?? $quiz['title'];
$currentSlug = $_POST['slug'] ?? $quiz['slug'];
$currentDescription = $_POST['description'] ?? $quiz['description'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $displayQuestions = [];
    foreach (($_POST['question_text'] ?? []) as $idx => $text) {
        $displayQuestions[] = [
            'text' => $text,
            'image' => $_POST['question_image'][$idx] ?? '',
            'a' => $_POST['option_a'][$idx] ?? '',
            'b' => $_POST['option_b'][$idx] ?? '',
            'c' => $_POST['option_c'][$idx] ?? '',
            'd' => $_POST['option_d'][$idx] ?? '',
            'correct' => $_POST['correct_option'][$idx] ?? '',
        ];
    }
} else {
    $qStmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order ASC, id ASC");
    $qStmt->execute([$id]);
    $displayQuestions = array_map(function ($q) {
        return [
            'text' => $q['question_text'],
            'image' => $q['question_image'] ?? '',
            'a' => $q['option_a'],
            'b' => $q['option_b'],
            'c' => $q['option_c'],
            'd' => $q['option_d'],
            'correct' => $q['correct_option'],
        ];
    }, $qStmt->fetchAll());
}

$pageTitle = 'Edit Quiz';
$wideLayout = true;
require __DIR__ . '/../includes/header.php';
?>

<h1>Edit Quiz</h1>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js"></script>

<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" class="admin-form" id="quiz-form">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <label>Title
        <input type="text" name="title" required value="<?= htmlspecialchars($currentTitle) ?>">
    </label>

    <label>URL Slug (editing this changes the quiz's public URL — old links will break)
        <input type="text" name="slug" id="slug-input" value="<?= htmlspecialchars($currentSlug) ?>" pattern="[a-z0-9\-]+" title="Lowercase letters, numbers, and hyphens only">
        <span class="field-hint">yourdomain.com/take-quiz.php?slug=<span id="slug-preview"></span></span>
    </label>

    <label>Description (shown on the quiz listing and before someone starts)
        <textarea name="description" rows="3"><?= htmlspecialchars($currentDescription ?? '') ?></textarea>
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
            <button type="button" class="math-btn" data-before="$\begin{bmatrix} a & b \\ c & d \end{bmatrix}$" data-after="">Matrix</button>
            <button type="button" class="math-btn" data-before="$\sum_{i=1}^{n}$" data-after="">&sum; Summation</button>
        </div>
        <div id="questions-wrap"></div>
        <button type="button" id="add-question-btn" class="button-secondary">+ Add Question</button>
    </div>

    <div class="form-actions">
        <button type="submit">Save Changes</button>
        <a href="dashboard.php?tab=quizzes" class="button-secondary" id="cancel-link">Cancel</a>
    </div>
</form>

<script src="/assets/quiz-builder.js"></script>
<script>
const existingQuestions = <?= json_encode($displayQuestions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
if (existingQuestions.length > 0) {
    existingQuestions.forEach(addQuestionBlock);
} else {
    addQuestionBlock();
}

// slug preview
var slugInput = document.getElementById('slug-input');
var slugPreview = document.getElementById('slug-preview');
slugInput.addEventListener('input', function () {
    slugPreview.textContent = slugInput.value;
});
slugPreview.textContent = slugInput.value;

document.getElementById('cancel-link').addEventListener('click', function (e) {
    var confirmed = confirm('Discard any unsaved changes and go back?');
    if (!confirmed) {
        e.preventDefault();
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
