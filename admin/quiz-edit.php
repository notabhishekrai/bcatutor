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
        // Only ever trust images that came from our own uploader
        if ($image !== '' && strpos($image, '/uploads/') !== 0) {
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
        <div id="questions-wrap"></div>
        <button type="button" id="add-question-btn" class="button-secondary">+ Add Question</button>
    </div>

    <div class="form-actions">
        <button type="submit">Save Changes</button>
        <a href="dashboard.php?tab=quizzes" class="button-secondary" id="cancel-link">Cancel</a>
    </div>
</form>

<script>
let questionIndex = 0;
const wrap = document.getElementById('questions-wrap');

function addQuestionBlock(data) {
    data = data || {};
    const idx = questionIndex++;
    const block = document.createElement('div');
    block.className = 'quiz-question-builder';
    block.innerHTML = `
        <div class="quiz-question-builder-header">
            <span>Question ${idx + 1}</span>
            <button type="button" class="link-button remove-question-btn">Remove</button>
        </div>
        <label>Question text
            <textarea name="question_text[${idx}]" rows="2" required>${data.text ? escapeHtml(data.text) : ''}</textarea>
        </label>
        <div class="quiz-image-field">
            <input type="hidden" name="question_image[${idx}]" value="${data.image ? escapeHtml(data.image) : ''}">
            <div class="quiz-image-preview" style="${data.image ? '' : 'display:none;'}">
                <img src="${data.image ? escapeHtml(data.image) : ''}" alt="">
                <button type="button" class="link-button remove-image-btn">Remove image</button>
            </div>
            <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="question-image-input" style="${data.image ? 'display:none;' : ''}">
        </div>
        <div class="quiz-option-row">
            <span class="quiz-option-row-label">A</span>
            <input type="radio" name="correct_option[${idx}]" value="a" ${data.correct === 'a' ? 'checked' : ''} required>
            <input type="text" name="option_a[${idx}]" value="${data.a ? escapeHtml(data.a) : ''}" placeholder="Option A">
        </div>
        <div class="quiz-option-row">
            <span class="quiz-option-row-label">B</span>
            <input type="radio" name="correct_option[${idx}]" value="b" ${data.correct === 'b' ? 'checked' : ''}>
            <input type="text" name="option_b[${idx}]" value="${data.b ? escapeHtml(data.b) : ''}" placeholder="Option B">
        </div>
        <div class="quiz-option-row">
            <span class="quiz-option-row-label">C</span>
            <input type="radio" name="correct_option[${idx}]" value="c" ${data.correct === 'c' ? 'checked' : ''}>
            <input type="text" name="option_c[${idx}]" value="${data.c ? escapeHtml(data.c) : ''}" placeholder="Option C">
        </div>
        <div class="quiz-option-row">
            <span class="quiz-option-row-label">D</span>
            <input type="radio" name="correct_option[${idx}]" value="d" ${data.correct === 'd' ? 'checked' : ''}>
            <input type="text" name="option_d[${idx}]" value="${data.d ? escapeHtml(data.d) : ''}" placeholder="Option D">
        </div>
        <p class="field-hint">Select the radio button next to the correct option.</p>
    `;
    block.querySelector('.remove-question-btn').addEventListener('click', function () {
        block.remove();
        renumberQuestions();
    });

    var hiddenImage = block.querySelector('input[name="question_image[' + idx + ']"]');
    var imagePreview = block.querySelector('.quiz-image-preview');
    var previewImg = imagePreview.querySelector('img');
    var imageInput = block.querySelector('.question-image-input');

    imageInput.addEventListener('change', function () {
        var file = imageInput.files[0];
        if (!file) return;

        var formData = new FormData();
        formData.append('image', file);
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

        fetch('upload-image.php', { method: 'POST', body: formData })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.url) {
                    hiddenImage.value = data.url;
                    previewImg.src = data.url;
                    imagePreview.style.display = '';
                    imageInput.style.display = 'none';
                } else {
                    alert(data.error || 'Upload failed.');
                }
            })
            .catch(function () {
                alert('Upload failed — check your connection and try again.');
            });
    });

    block.querySelector('.remove-image-btn').addEventListener('click', function () {
        hiddenImage.value = '';
        previewImg.src = '';
        imagePreview.style.display = 'none';
        imageInput.style.display = '';
        imageInput.value = '';
    });

    wrap.appendChild(block);
}

function renumberQuestions() {
    wrap.querySelectorAll('.quiz-question-builder-header span').forEach(function (el, i) {
        el.textContent = 'Question ' + (i + 1);
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

document.getElementById('add-question-btn').addEventListener('click', function () {
    addQuestionBlock();
});

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
