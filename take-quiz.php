<?php
require_once __DIR__ . '/config.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE slug = ?");
$stmt->execute([$slug]);
$quiz = $stmt->fetch();

if (!$quiz) {
    http_response_code(404);
    $pageTitle = 'Quiz Not Found';
    require __DIR__ . '/includes/header.php';
    ?>
    <div style="text-align:center; padding: 40px 0;">
        <p style="font-family:'IBM Plex Mono', monospace; font-size:0.85rem; color:var(--muted); letter-spacing:0.06em;">ERROR 404</p>
        <h1>This quiz doesn't exist</h1>
        <p style="color:var(--muted); max-width:40ch; margin:0 auto 28px;">
            It may have been removed, or the link might be incorrect.
        </p>
        <div class="home-links" style="justify-content:center;">
            <a href="/" class="button">Back to Home</a>
            <a href="/quizzes" class="button">Browse Quizzes</a>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$qStmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order ASC, id ASC");
$qStmt->execute([$quiz['id']]);
$questions = $qStmt->fetchAll();

$submitted = false;
$score = 0;
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $answers = $_POST['answers'] ?? [];
    foreach ($questions as $q) {
        $selected = $answers[$q['id']] ?? null;
        if (!is_string($selected)) {
            $selected = null;
        }
        $isCorrect = ($selected === $q['correct_option']);
        if ($isCorrect) {
            $score++;
        }
        $results[] = [
            'question' => $q,
            'selected' => $selected,
            'is_correct' => $isCorrect,
        ];
    }
    $submitted = true;
}

$pageTitle = $quiz['title'];
$metaDescription = $quiz['description'] ?: ('Take the ' . $quiz['title'] . ' quiz.');
require __DIR__ . '/includes/header.php';

$optionLabels = ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'];
?>

<p class="breadcrumb">
    <a href="quizzes">Quizzes</a> &rsaquo;
    <?= htmlspecialchars($quiz['title']) ?>
</p>

<h1><?= htmlspecialchars($quiz['title']) ?></h1>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js"></script>

<?php if (!empty($quiz['description']) && !$submitted): ?>
    <p class="subject-meta"><?= htmlspecialchars($quiz['description']) ?></p>
<?php endif; ?>

<?php if (empty($questions)): ?>
    <p class="empty-state">This quiz doesn't have any questions yet.</p>

<?php elseif ($submitted): ?>

    <?php
    $total = count($questions);
    $percent = $total > 0 ? round(($score / $total) * 100) : 0;
    ?>

    <div class="quiz-result-summary">
        <div class="quiz-result-score"><?= $score ?> / <?= $total ?></div>
        <div class="quiz-result-label"><?= $percent ?>% correct</div>
        <div class="quiz-result-actions no-print">
            <button type="button" id="print-results-btn" class="button">Print / Save as PDF</button>
            <a href="take-quiz.php?slug=<?= urlencode($quiz['slug']) ?>" class="button-secondary">Retake Quiz</a>
        </div>
    </div>

    <?php foreach ($results as $i => $r): ?>
        <?php $q = $r['question']; ?>
        <div class="quiz-question">
            <div class="quiz-question-num">Question <?= $i + 1 ?> of <?= $total ?></div>
            <p class="quiz-question-text"><?= htmlspecialchars($q['question_text']) ?></p>
            <?php if (!empty($q['question_image'])): ?>
                <img class="quiz-question-image" src="<?= htmlspecialchars($q['question_image']) ?>" alt="">
            <?php endif; ?>
            <div class="quiz-options">
                <?php foreach (['a', 'b', 'c', 'd'] as $letter): ?>
                    <?php
                    $classes = ['quiz-option'];
                    if ($letter === $q['correct_option']) {
                        $classes[] = 'quiz-option-correct';
                    } elseif ($letter === $r['selected']) {
                        $classes[] = 'quiz-option-incorrect';
                    }
                    ?>
                    <div class="<?= implode(' ', $classes) ?>">
                        <span><strong><?= $optionLabels[$letter] ?>.</strong> <?= htmlspecialchars($q['option_' . $letter]) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        document.getElementById('print-results-btn').addEventListener('click', function () {
            window.print();
        });
    </script>

<?php else: ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <?php foreach ($questions as $i => $q): ?>
            <div class="quiz-question">
                <div class="quiz-question-num">Question <?= $i + 1 ?> of <?= count($questions) ?></div>
                <p class="quiz-question-text"><?= htmlspecialchars($q['question_text']) ?></p>
                <?php if (!empty($q['question_image'])): ?>
                    <img class="quiz-question-image" src="<?= htmlspecialchars($q['question_image']) ?>" alt="">
                <?php endif; ?>
                <div class="quiz-options">
                    <?php foreach (['a', 'b', 'c', 'd'] as $letter): ?>
                        <label class="quiz-option">
                            <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $letter ?>" <?= $letter === 'a' ? 'required' : '' ?>>
                            <span><strong><?= $optionLabels[$letter] ?>.</strong> <?= htmlspecialchars($q['option_' . $letter]) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <button type="submit" class="button">Submit Quiz</button>
        </div>
    </form>

<?php endif; ?>

<script>
    if (window.renderMathInElement) {
        renderMathInElement(document.body, {
            delimiters: [
                { left: '$$', right: '$$', display: true },
                { left: '$', right: '$', display: false }
            ],
            throwOnError: false
        });
    }
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
