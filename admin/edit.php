<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$syllabus = require __DIR__ . '/../includes/syllabus.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$id]);
$post = $stmt->fetch();


if (!$post) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? 'blog';
    $content = $_POST['content'] ?? '';
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $currentSlug = $_POST['slug'] ?? $post['slug'];
    
    $slug = slugify(trim($_POST['slug'] ?? ''));
    if ($slug === '') {
        $slug = slugify($title);
    }

    if ($type === 'blog') {
        $semester = null;
        $subject = null;
    } else {
        $semester = (int)($_POST['semester'] ?? 1);
        $subject = trim($_POST['subject'] ?? '');
    }

    $validSubjectsForSemester = $syllabus[$semester] ?? [];

    if ($title === '' || isContentEmpty($content)) {
        $error = 'Title and content are required.';
    } elseif ($type !== 'blog' && !in_array($subject, $validSubjectsForSemester)) {
        $error = 'Please select a valid subject for the chosen semester.';
    } elseif ($slug === '') {
    $error = 'Could not generate a valid slug — please enter one manually.';
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE slug = ? AND id != ?");
        $check->execute([$slug, $id]);
        if ($check->fetchColumn() > 0) {
        $error = 'That URL slug is already in use — please choose a different one.';
        } else {
        $content = sanitizeHtml($content);

        $stmt = $pdo->prepare("UPDATE posts SET title = ?, slug = ?, type = ?, semester = ?, subject = ?, content = ?, sort_order = ?, meta_description = ? WHERE id = ?");
        $stmt->execute([$title, $slug, $type, $semester, $subject, $content, $sortOrder, $metaDescription, $id]);

        header('Location: dashboard.php');
        exit;
    }
}
}
$pageTitle = 'Edit Post';
$wideLayout = true;
require __DIR__ . '/../includes/header.php';

// Use posted values if this is a redisplay after a validation error,
// otherwise fall back to what's saved in the database.
$currentTitle = $_POST['title'] ?? $post['title'];
$currentType = $_POST['type'] ?? $post['type'];
$currentSemester = $_POST['semester'] ?? $post['semester'];
$currentSubject = $_POST['subject'] ?? $post['subject'];
$currentContent = $_POST['content'] ?? $post['content'];
$currentSortOrder = $_POST['sort_order'] ?? $post['sort_order'];
$currentSlug = $_POST['slug'] ?? $post['slug'];
$currentMetaDescription = $_POST['meta_description'] ?? $post['meta_description'];
?>

<h1>Edit Post</h1>

<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.6/quill.min.js"></script>

<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <label>Title
        <input type="text" name="title" required value="<?= htmlspecialchars($currentTitle) ?>">
    </label>
    
    <label>URL Slug (editing this changes the post's public URL — old links will break)
        <input type="text" name="slug" id="slug-input" value="<?= htmlspecialchars($currentSlug) ?>" pattern="[a-z0-9\-]+" title="Lowercase letters, numbers, and hyphens only">
        <span class="field-hint">yourdomain.com/post.php?slug=<span id="slug-preview"></span></span>
    </label>
    
    <label>Meta Description (for search engines — under 160 characters)
        <textarea name="meta_description" rows="2" maxlength="160"><?= htmlspecialchars($currentMetaDescription) ?></textarea>
    </label>

    <label>Order (for Notes — controls unit sequence; leave as 0 if not applicable)
        <input type="number" name="sort_order" value="<?= htmlspecialchars($currentSortOrder) ?>" min="0">
    </label>
    
    <label>Type
        <select name="type" id="type-select">
            <?php foreach (['blog', 'note', 'solution', 'question_paper'] as $t): ?>
                <option value="<?= $t ?>" <?= $currentType === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <div id="semester-subject-fields">
        <label>Semester
            <select name="semester" id="semester-select">
                <?php for ($i = 1; $i <= 8; $i++): ?>
                    <option value="<?= $i ?>" <?= (int)$currentSemester === $i ? 'selected' : '' ?>>Semester <?= $i ?></option>
                <?php endfor; ?>
            </select>
        </label>
        <label>Subject
            <select name="subject" id="subject-select"></select>
        </label>
    </div>

    <div class="admin-field">
        <span class="admin-field-label">Content</span>
        <div id="editor" style="background:#fff; min-height:300px; max-height:500px; overflow-y:auto;"></div>
        <textarea name="content" id="content-input" style="display:none;"><?= htmlspecialchars($currentContent ?? '') ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit">Save Changes</button>
        <a href="dashboard.php" class="button-secondary" id="cancel-link">Cancel</a>
    </div>
</form>

<!--quill integration-->
<script>
    var Size = Quill.import('attributors/style/size');
    Size.whitelist = ['10px', '12px', '13px', '14px', '16px', '18px', '20px', '24px', '28px', '32px', '36px'];
    Quill.register(Size, true);
    var quill = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [2, 3, 4, 5, 6, false] }],
                [{ size: Size.whitelist }],
                ['bold', 'italic', 'underline', 'strike'],
                ['blockquote', 'code-block'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link', 'image'],
                ['clean']
            ]
        }
    });
    
    
    
    // slug
    var slugInput = document.getElementById('slug-input');
    var slugPreview = document.getElementById('slug-preview');
    slugInput.addEventListener('input', function () {
    slugPreview.textContent = slugInput.value;
    });
    slugPreview.textContent = slugInput.value;
    
    // meta
    
    var metaField = document.querySelector('textarea[name="meta_description"]');
if (metaField) {
    var counter = document.createElement('span');
    counter.style.fontSize = '0.78rem';
    counter.style.color = 'var(--muted)';
    metaField.insertAdjacentElement('afterend', counter);

    function updateCounter() {
        counter.textContent = metaField.value.length + ' / 160 characters';
    }
    metaField.addEventListener('input', updateCounter);
    updateCounter();
}
    
    document.getElementById('cancel-link').addEventListener('click', function (e) {
    var titleField = document.querySelector('input[name="title"]');
    var hasTitle = titleField.value.trim() !== '';
    var hasContent = quill.getText().trim() !== '';

    if (hasTitle || hasContent) {
        var confirmed = confirm('You have unsaved changes. Discard them and go back?');
        if (!confirmed) {
            e.preventDefault();
        }
    }
});
    
    (function () {
    var toolbar = quill.getModule('toolbar').container;

    var titles = {
        bold: 'Bold',
        italic: 'Italic',
        underline: 'Underline',
        strike: 'Strikethrough',
        blockquote: 'Blockquote',
        'code-block': 'Code Block',
        link: 'Insert Link',
        image: 'Insert Image',
        clean: 'Clear Formatting'
    };

    Object.keys(titles).forEach(function (key) {
        var el = toolbar.querySelector('.ql-' + key);
        if (el) el.setAttribute('title', titles[key]);
    });

    // The two list buttons share the same class, differentiated by a "value" attribute
    toolbar.querySelectorAll('.ql-list').forEach(function (btn) {
        var value = btn.getAttribute('value');
        btn.setAttribute('title', value === 'ordered' ? 'Numbered List' : 'Bullet List');
    });

    // The heading picker is a <select>, not a button
    var headerSelect = toolbar.querySelector('.ql-header');
    if (headerSelect) headerSelect.setAttribute('title', 'Heading Style');

    var sizeSelect = toolbar.querySelector('.ql-size');
    if (sizeSelect) sizeSelect.setAttribute('title', 'Font Size');
})();

    // Custom image handler
    quill.getModule('toolbar').addHandler('image', function () {
    var input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/jpeg,image/png,image/gif,image/webp');
    input.click();

    input.onchange = function () {
        var file = input.files[0];
        if (!file) return;

        var range = quill.getSelection(true);
        var formData = new FormData();
        formData.append('image', file);
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

        fetch('upload-image.php', { method: 'POST', body: formData })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.url) {
                    quill.insertEmbed(range.index, 'image', data.url);

                    // Prompt for alt text right after inserting — describe what
                    // the image actually shows, e.g. "ER diagram for library database"
                    var altText = prompt('Describe this image for accessibility and search (alt text):') || '';
                    var leafResult = quill.getLeaf(range.index);
                    var leaf = leafResult[0];
                    if (leaf && leaf.domNode) {
                        leaf.domNode.setAttribute('alt', altText.trim());
                    }
                } else {
                    alert(data.error || 'Upload failed.');
                }
            })
            .catch(function () {
                alert('Upload failed — check your connection and try again.');
            });
    };
});


    // Pre-fill the editor with existing content (edit mode, or a
    // validation error redisplay on the create form)
    var existingContent = document.getElementById('content-input').value;
    if (existingContent) {
        quill.root.innerHTML = existingContent;
    }

    // Copy Quill's HTML into the hidden textarea right before submit
    document.querySelector('form.admin-form').addEventListener('submit', function () {
        document.getElementById('content-input').value = quill.root.innerHTML;
    });
</script>

<script>
const syllabus = <?= json_encode($syllabus) ?>;
const savedSubject = <?= json_encode($currentSubject) ?>;
const typeSelect = document.getElementById('type-select');
const semesterSelect = document.getElementById('semester-select');
const subjectSelect = document.getElementById('subject-select');
const fieldsWrap = document.getElementById('semester-subject-fields');

function populateSubjects() {
    const subjects = syllabus[semesterSelect.value] || [];
    subjectSelect.innerHTML = subjects
        .map(s => `<option value="${s}" ${s === savedSubject ? 'selected' : ''}>${s}</option>`)
        .join('');
}

function toggleFields() {
    fieldsWrap.style.display = typeSelect.value === 'blog' ? 'none' : 'block';
}

typeSelect.addEventListener('change', toggleFields);
semesterSelect.addEventListener('change', populateSubjects);

populateSubjects();
toggleFields();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
