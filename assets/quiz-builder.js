// Shared by admin/quiz-create.php and admin/quiz-edit.php — everything that
// doesn't depend on PHP-rendered data (the question builder itself, the math
// toolbar, and image upload wiring). Each page's own inline <script> block
// handles only what's specific to it: initial question data, slug behavior,
// and the cancel-link confirmation message.

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
    // Must be safe inside a quoted HTML attribute (value="...", src="...")
    // as well as plain text — textContent->innerHTML round-tripping only
    // escapes &/</>, not quotes, which isn't enough for attribute context.
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
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

// Matrix type dropdown — same insert-at-cursor behavior as the math-btn buttons,
// just with more template choices than would fit as individual buttons.
var matrixSelect = document.getElementById('matrix-type-select');
if (matrixSelect) {
    matrixSelect.addEventListener('change', function () {
        insertAtCursor(lastFocusedField, matrixSelect.value, '');
        matrixSelect.selectedIndex = 0;
    });
}
