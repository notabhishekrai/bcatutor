<?php
require_once __DIR__ . '/config.php';

$stmt = $pdo->query("
    SELECT quizzes.id, quizzes.title, quizzes.slug, quizzes.description,
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_questions.quiz_id = quizzes.id) AS question_count
    FROM quizzes
    ORDER BY quizzes.created_at DESC
");
$quizzes = $stmt->fetchAll();

$pageTitle = 'Quizzes';
$metaDescription = 'Test yourself with multiple-choice quizzes covering BCA subjects.';
require __DIR__ . '/includes/header.php';
?>

<h1>Quizzes</h1>

<p class="field-hint"><a href="leaderboard.php">&#127942; View global leaderboard &rarr;</a></p>

<?php if (empty($quizzes)): ?>
    <p class="empty-state">No quizzes yet — check back soon.</p>
<?php else: ?>
    <div class="quiz-grid">
        <?php foreach ($quizzes as $quiz): ?>
            <div class="quiz-card">
                <a href="take-quiz.php?slug=<?= urlencode($quiz['slug']) ?>" class="quiz-card-link">
                    <span class="quiz-card-title"><?= htmlspecialchars($quiz['title']) ?></span>
                    <?php if (!empty($quiz['description'])): ?>
                        <p class="quiz-card-desc"><?= htmlspecialchars($quiz['description']) ?></p>
                    <?php endif; ?>
                    <span class="quiz-card-meta"><?= (int)$quiz['question_count'] ?> question<?= $quiz['question_count'] !== 1 ? 's' : '' ?></span>
                </a>
                <div class="quiz-card-footer">
                    <a href="take-quiz.php?slug=<?= urlencode($quiz['slug']) ?>" class="quiz-card-start">Start Quiz &rarr;</a>
                    <a href="leaderboard.php?slug=<?= urlencode($quiz['slug']) ?>" class="quiz-card-leaderboard">&#127942; Leaderboard</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
