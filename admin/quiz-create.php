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
        // Only ever trust images that came from our own uploader
        if ($image !== '' && strpos($image, '/uploads/') !== 0) {
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
            <button type="button" class="math-btn" data-before="$\begin{bmatrix} a & b \\ c & d \end{bmatrix}$" data-after="">Matrix</button>
            <button type="button" class="math-btn" data-before="$\sum_{i=1}^{n}$" data-after="">&sum; Summation</button>
            <button type="button" class="math-btn" id="math-wrap-btn">$...$ Equation</button>
        </div>
        <div id="questions-wrap"></div>
        <button type="button" id="add-question-btn" class="button-secondary">+ Add Question</button>
    </div>

    <div class="form-actions">
        <button type="submit">Publish Quiz</button>
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
        <div class="math-preview" data-preview="text"></div>
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
        <div class="math-preview" data-preview="options"></div>
        <p class="field-hint">Select the radio button next to the correct option. Wrap math in $...$, e.g. $\\frac{1}{2}$</p>
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

    // Live math preview — question text and the 4 options each get re-rendered on input
    var textField = block.querySelector('textarea[name^="question_text"]');
    var textPreview = block.querySelector('[data-preview="text"]');
    var optionsPreview = block.querySelector('[data-preview="options"]');
    var optionFields = {
        a: block.querySelector('input[name^="option_a"]'),
        b: block.querySelector('input[name^="option_b"]'),
        c: block.querySelector('input[name^="option_c"]'),
        d: block.querySelector('input[name^="option_d"]')
    };

    function renderMathPreview(el, text) {
        el.textContent = text;
        if (text.trim() && window.renderMathInElement) {
            renderMathInElement(el, {
                delimiters: [
                    { left: '$$', right: '$$', display: true },
                    { left: '$', right: '$', display: false }
                ],
                throwOnError: false
            });
        }
    }

    function updateTextPreview() { renderMathPreview(textPreview, textField.value); }

    function updateOptionsPreview() {
        optionsPreview.innerHTML = '';
        ['a', 'b', 'c', 'd'].forEach(function (letter) {
            var val = optionFields[letter].value;
            if (!val.trim()) return;
            var line = document.createElement('div');
            line.className = 'math-preview-line';
            optionsPreview.appendChild(line);
            renderMathPreview(line, letter.toUpperCase() + '. ' + val);
        });
    }

    textField.addEventListener('input', updateTextPreview);
    ['a', 'b', 'c', 'd'].forEach(function (letter) {
        optionFields[letter].addEventListener('input', updateOptionsPreview);
    });
    updateTextPreview();
    updateOptionsPreview();

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

// Math toolbar — inserts a LaTeX snippet into whichever question/option field was last focused
let lastFocusedField = null;
document.addEventListener('focusin', function (e) {
    if (e.target.matches('.quiz-question-builder textarea, .quiz-question-builder input[type="text"]')) {
        lastFocusedField = e.target;
    }
});

function insertAtCursor(field, before, after) {
    if (!field) {
        alert('Click into a question or option field first, then choose a symbol to insert.');
        return;
    }
    var start = field.selectionStart;
    var end = field.selectionEnd;
    var value = field.value;
    var selected = value.slice(start, end);
    field.value = value.slice(0, start) + before + selected + after + value.slice(end);
    var cursorPos = start + before.length + selected.length + after.length;
    field.focus();
    field.setSelectionRange(cursorPos, cursorPos);
    field.dispatchEvent(new Event('input', { bubbles: true }));
}

document.querySelectorAll('.math-btn[data-before]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        insertAtCursor(lastFocusedField, btn.dataset.before, btn.dataset.after || '');
    });
});

document.getElementById('math-wrap-btn').addEventListener('click', function () {
    insertAtCursor(lastFocusedField, '$', '$');
});

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
