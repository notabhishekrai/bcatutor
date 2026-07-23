<?php
require_once __DIR__ . '/../config.php';
requireLogin();

// Handle delete (POST + CSRF protected, not a plain GET link)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post_id'])) {
    csrfCheck();
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_post_id']]);
    header('Location: dashboard.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quiz_id'])) {
    csrfCheck();
    $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_quiz_id']]);
    header('Location: dashboard.php?tab=quizzes');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_leaderboard_id'])) {
    csrfCheck();
    $stmt = $pdo->prepare("DELETE FROM quiz_leaderboard WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_leaderboard_id']]);
    header('Location: dashboard.php?tab=leaderboard');
    exit;
}

$filterType = $_GET['type'] ?? '';
$filterAuthor = $_GET['author'] ?? '';
$filterSubject = $_GET['subject'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');

$conditions = [];
$params = [];

if ($filterType !== '') {
    $conditions[] = 'posts.type = ?';
    $params[] = $filterType;
}
if ($filterAuthor !== '') {
    $conditions[] = 'posts.created_by = ?';
    $params[] = $filterAuthor;
}
if ($filterSubject !== '' && $filterType !== 'blog') {
    $conditions[] = 'posts.subject = ?';
    $params[] = $filterSubject;
}
if ($searchQuery !== '') {
    $conditions[] = 'posts.title LIKE ?';
    $params[] = '%' . $searchQuery . '%';
}
$sql = "
    SELECT posts.id, posts.title, posts.type, posts.semester, posts.subject, posts.created_at, admins.username AS author
    FROM posts
    LEFT JOIN admins ON posts.created_by = admins.id
";
if ($conditions) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY posts.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// For the author dropdown — every admin who has ever posted
$authors = $pdo->query("SELECT id, username FROM admins ORDER BY username ASC")->fetchAll();

// For the subject dropdown — only subjects that actually have notes/solutions/question papers
$subjects = $pdo->query("SELECT DISTINCT subject FROM posts WHERE subject IS NOT NULL ORDER BY subject ASC")->fetchAll(PDO::FETCH_COLUMN);

$quizzesStmt = $pdo->query("
    SELECT quizzes.id, quizzes.title, quizzes.slug, quizzes.created_at,
           admins.username AS author,
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_questions.quiz_id = quizzes.id) AS question_count
    FROM quizzes
    LEFT JOIN admins ON quizzes.created_by = admins.id
    ORDER BY quizzes.created_at DESC
");
$quizzes = $quizzesStmt->fetchAll();

$leaderboardStmt = $pdo->query("
    SELECT quiz_leaderboard.id, quizzes.title AS quiz_title, players.nickname,
           quiz_leaderboard.score, quiz_leaderboard.total, quiz_leaderboard.percent, quiz_leaderboard.updated_at
    FROM quiz_leaderboard
    JOIN quizzes ON quizzes.id = quiz_leaderboard.quiz_id
    JOIN players ON players.player_id = quiz_leaderboard.player_id
    ORDER BY quizzes.title ASC, quiz_leaderboard.percent DESC
");
$leaderboardEntries = $leaderboardStmt->fetchAll();

$activeTab = in_array($_GET['tab'] ?? '', ['quizzes', 'leaderboard'], true) ? $_GET['tab'] : 'posts';

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h1>Dashboard</h1>

<div class="tab-toggle" role="tablist">
    <button type="button" class="tab-btn <?= $activeTab === 'posts' ? 'is-active' : '' ?>" data-tab="posts" role="tab" aria-selected="<?= $activeTab === 'posts' ? 'true' : 'false' ?>">
        Posts <span class="tab-count"><?= count($posts) ?></span>
    </button>
    <button type="button" class="tab-btn <?= $activeTab === 'quizzes' ? 'is-active' : '' ?>" data-tab="quizzes" role="tab" aria-selected="<?= $activeTab === 'quizzes' ? 'true' : 'false' ?>">
        Quizzes <span class="tab-count"><?= count($quizzes) ?></span>
    </button>
    <button type="button" class="tab-btn <?= $activeTab === 'leaderboard' ? 'is-active' : '' ?>" data-tab="leaderboard" role="tab" aria-selected="<?= $activeTab === 'leaderboard' ? 'true' : 'false' ?>">
        Leaderboard <span class="tab-count"><?= count($leaderboardEntries) ?></span>
    </button>
</div>

<div class="tab-panel" id="panel-posts" <?= $activeTab === 'posts' ? '' : 'hidden' ?>>
    <p><a href="create.php" class="button">+ New Post</a></p>

    <form method="GET" class="filter-bar">
        <input type="hidden" name="tab" value="posts">
        <input type="text" name="q" placeholder="Search by title..." value="<?= htmlspecialchars($searchQuery) ?>" class="filter-search">
        <select name="type" onchange="this.form.submit()">
            <option value="">All Types</option>
            <?php foreach (['blog', 'note', 'solution', 'question_paper'] as $t): ?>
                <option value="<?= $t ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="author" onchange="this.form.submit()">
            <option value="">All Authors</option>
            <?php foreach ($authors as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $filterAuthor == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['username']) ?></option>
            <?php endforeach; ?>
        </select>

        <?php if ($filterType !== 'blog'): ?>
            <select name="subject" onchange="this.form.submit()">
                <option value="">All Subjects</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $filterSubject === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <?php if ($filterType !== '' || $filterAuthor !== '' || $filterSubject !== '' || $searchQuery !== ''): ?>
            <a href="dashboard.php" class="button-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr><th>Title</th><th>Type</th><th>Semester / Subject</th><th>Author</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td><?= htmlspecialchars($post['title']) ?></td>
                        <td><?= htmlspecialchars($post['type']) ?></td>
                        <td>
                            <?= $post['semester'] ? 'Sem ' . (int)$post['semester'] . ' — ' . htmlspecialchars($post['subject']) : '—' ?>
                        </td>
                        <td><?= htmlspecialchars($post['author'] ?? 'Unknown') ?></td>
                        <td><?= date('M j, Y', strtotime($post['created_at'])) ?></td>
                        <td>
                            <a href="edit.php?id=<?= $post['id'] ?>">Edit</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this post permanently?');">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="delete_post_id" value="<?= $post['id'] ?>">
                                <button type="submit" class="link-button">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($posts)): ?>
                    <tr><td colspan="6" class="empty-state">No posts yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="tab-panel" id="panel-quizzes" <?= $activeTab === 'quizzes' ? '' : 'hidden' ?>>
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
                                <input type="hidden" name="delete_quiz_id" value="<?= $quiz['id'] ?>">
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
</div>

<div class="tab-panel" id="panel-leaderboard" <?= $activeTab === 'leaderboard' ? '' : 'hidden' ?>>
    <p class="field-hint">Anyone can submit any nickname here since there's no login — use Delete to remove an offensive nickname or an obviously fake score.</p>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr><th>Quiz</th><th>Nickname</th><th>Score</th><th>%</th><th>Last Updated</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($leaderboardEntries as $entry): ?>
                    <tr>
                        <td><?= htmlspecialchars($entry['quiz_title']) ?></td>
                        <td><?= htmlspecialchars($entry['nickname']) ?></td>
                        <td><?= (int)$entry['score'] ?> / <?= (int)$entry['total'] ?></td>
                        <td><?= round((float)$entry['percent']) ?>%</td>
                        <td><?= date('M j, Y', strtotime($entry['updated_at'])) ?></td>
                        <td>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Remove this leaderboard entry?');">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="delete_leaderboard_id" value="<?= $entry['id'] ?>">
                                <button type="submit" class="link-button">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($leaderboardEntries)): ?>
                    <tr><td colspan="6" class="empty-state">No leaderboard entries yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.tab-btn').forEach(function (b) {
                b.classList.remove('is-active');
                b.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('.tab-panel').forEach(function (p) {
                p.hidden = true;
            });
            btn.classList.add('is-active');
            btn.setAttribute('aria-selected', 'true');
            document.getElementById('panel-' + btn.dataset.tab).hidden = false;

            // Keep the URL in sync so a reload or shared link lands on the right tab
            var url = new URL(window.location);
            url.searchParams.set('tab', btn.dataset.tab);
            history.replaceState(null, '', url);
        });
    });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
