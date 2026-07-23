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
$total = count($questions);

// Prefill the nickname field for a returning visitor without creating a
// cookie/player row just for viewing the page — that only happens on submit.
$existingNickname = '';
$existingPlayerId = getPlayerId(false);
if ($existingPlayerId) {
    $nickStmt = $pdo->prepare("SELECT nickname FROM players WHERE player_id = ?");
    $nickStmt->execute([$existingPlayerId]);
    $existingNickname = $nickStmt->fetchColumn() ?: '';
}

$submitted = false;
$score = 0;
$results = [];
$leaderboardStatus = null;

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

    // Optional leaderboard submission — no login, just a nickname tied to a
    // cookie-based player ID. Score/total come from the server-side check
    // above, never from client input, so there's nothing to trust here.
    //
    // Strip control chars and Unicode "Format" chars (zero-width spaces,
    // bidi overrides/isolates, BOM, etc.) before anything else — left in,
    // they'd render invisibly or reorder text, letting a nickname visually
    // spoof another entry on the public leaderboard. preg_replace with /u
    // returns null on invalid UTF-8 input, which we treat as "no nickname".
    $nickname = preg_replace('/[\p{Cc}\p{Cf}]/u', '', $_POST['nickname'] ?? '');
    $nickname = $nickname === null ? '' : trim(preg_replace('/\s+/u', ' ', $nickname));

    if ($nickname !== '' && $total > 0) {
        $nickname = mb_substr($nickname, 0, 40);

        // Rate limit: one leaderboard-affecting submission per IP every 5
        // seconds. This is keyed off the IP, not the player_id cookie —
        // a scripted flood simply won't send that cookie, so the cookie
        // alone can't stop it from minting endless fake players/scores.
        $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
        $rateStmt = $pdo->prepare("SELECT last_submitted_at FROM leaderboard_rate_limit WHERE ip_hash = ?");
        $rateStmt->execute([$ipHash]);
        $lastSubmittedAt = $rateStmt->fetchColumn();

        if ($lastSubmittedAt !== false && (time() - strtotime($lastSubmittedAt)) < 5) {
            $leaderboardStatus = 'rate_limited';
        } else {
            $pdo->prepare("INSERT INTO leaderboard_rate_limit (ip_hash, last_submitted_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_submitted_at = NOW()")
                ->execute([$ipHash]);

            $storedPercent = round(($score / $total) * 100, 2);
            $playerId = getPlayerId(true);

            $pdo->prepare("INSERT INTO players (player_id, nickname) VALUES (?, ?) ON DUPLICATE KEY UPDATE nickname = ?")
                ->execute([$playerId, $nickname, $nickname]);

            // Single atomic upsert instead of SELECT-then-INSERT/UPDATE — the
            // old check-then-act version raced under a double-submit (two
            // requests both seeing "no existing row" and both trying to
            // INSERT) and could throw on the UNIQUE KEY(quiz_id, player_id)
            // constraint. This keeps the best (highest percent) score in one
            // atomic statement, so there's nothing to race.
            $stmt = $pdo->prepare("
                INSERT INTO quiz_leaderboard (quiz_id, player_id, score, total, percent)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    score = IF(VALUES(percent) > percent, VALUES(score), score),
                    total = IF(VALUES(percent) > percent, VALUES(total), total),
                    percent = IF(VALUES(percent) > percent, VALUES(percent), percent)
            ");
            $stmt->execute([$quiz['id'], $playerId, $score, $total, $storedPercent]);

            // MySQL/MariaDB report affected-rows as 1 for a fresh insert, 2 for
            // an update that changed a value, and 0 if ON DUPLICATE KEY UPDATE
            // left every column as it was — a reliable "did this improve?" signal
            // without a separate SELECT.
            $leaderboardStatus = $stmt->rowCount() > 0 ? 'saved' : 'not_improved';
        }
    }
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

    <?php $percent = $total > 0 ? round(($score / $total) * 100) : 0; ?>

    <div class="quiz-result-summary">
        <div class="quiz-result-score"><?= $score ?> / <?= $total ?></div>
        <div class="quiz-result-label"><?= $percent ?>% correct</div>
        <?php if ($leaderboardStatus === 'saved'): ?>
            <p class="field-hint no-print">Saved to the leaderboard as "<?= htmlspecialchars($nickname) ?>".</p>
        <?php elseif ($leaderboardStatus === 'not_improved'): ?>
            <p class="field-hint no-print">Your previous best on this quiz is still higher — leaderboard unchanged.</p>
        <?php elseif ($leaderboardStatus === 'rate_limited'): ?>
            <p class="field-hint no-print">Please wait a few seconds before submitting to the leaderboard again.</p>
        <?php endif; ?>
        <div class="quiz-result-actions no-print">
            <button type="button" id="print-results-btn" class="button">Print / Save as PDF</button>
            <a href="take-quiz.php?slug=<?= urlencode($quiz['slug']) ?>" class="button-secondary">Retake Quiz</a>
            <a href="leaderboard.php?slug=<?= urlencode($quiz['slug']) ?>" class="button-secondary">View Leaderboard</a>
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

        <label class="quiz-nickname-field">Nickname for the leaderboard (optional — leave blank to skip)
            <input type="text" name="nickname" maxlength="40" placeholder="e.g. Alex" value="<?= htmlspecialchars($existingNickname) ?>">
        </label>

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
