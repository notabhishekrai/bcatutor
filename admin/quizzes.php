<?php
require_once __DIR__ . '/../config.php';
requireLogin();

// Handle delete (POST + CSRF protected, not a plain GET link)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrfCheck();
    $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_id']]);
    header('Location: quizzes.php');
    exit;
}

$stmt = $pdo->query("
    SELECT quizzes.id, quizzes.title, quizzes.slug, quizzes.created_at,
           admins.username AS author,
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_questions.quiz_id = quizzes.id) AS question_count
    FROM quizzes
    LEFT JOIN admins ON quizzes.created_by = admins.id
    ORDER BY quizzes.created_at DESC
");
$quizzes = $stmt->fetchAll();

$pageTitle = 'Manage Quizzes';
require __DIR__ . '/../includes/header.php';
?>

<h1>Manage Quizzes</h1>
<p><a href="quiz-create.php" class="button">+ New Quiz</a></p>

<div class="table-scroll">
    <table class="admin-table">
        <thead>
            <tr><th>Title</th><th>Questions</th><th>Author</th><th>Date</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($quizzes as $quiz): ?>
                <tr>
                    <td><?= htmlspecialchars($quiz['title']) ?></td>
                    <td><?= (int)$quiz['question_count'] ?></td>
                    <td><?= htmlspecialchars($quiz['author'] ?? 'Unknown') ?></td>
                    <td><?= date('M j, Y', strtotime($quiz['created_at'])) ?></td>
                    <td>
                        <a href="/take-quiz.php?slug=<?= urlencode($quiz['slug']) ?>">View</a>
                        <a href="quiz-edit.php?id=<?= $quiz['id'] ?>">Edit</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this quiz and all its questions permanently?');">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="delete_id" value="<?= $quiz['id'] ?>">
                            <button type="submit" class="link-button">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($quizzes)): ?>
                <tr><td colspan="5" class="empty-state">No quizzes yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
