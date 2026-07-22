<?php
require_once __DIR__ . '/../config.php';
requireLogin();

// Handle delete (POST + CSRF protected, not a plain GET link)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrfCheck();
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_id']]);
    header('Location: dashboard.php');
    exit;
}

$filterType = $_GET['type'] ?? '';
$filterAuthor = $_GET['author'] ?? '';
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

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h1>Dashboard</h1>
<p><a href="create.php" class="button">+ New Post</a></p>

<form method="GET" class="filter-bar">
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

    <?php if ($filterType !== '' || $filterAuthor !== '' || $searchQuery !== ''): ?>
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
                            <input type="hidden" name="delete_id" value="<?= $post['id'] ?>">
                            <button type="submit" class="link-button">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
