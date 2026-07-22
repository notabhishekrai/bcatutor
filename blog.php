<?php
require_once __DIR__ . '/config.php';

$stmt = $pdo->query("SELECT id, title, slug, created_at FROM posts WHERE type = 'blog' ORDER BY created_at DESC");
$posts = $stmt->fetchAll();

$pageTitle = 'Blog';
$metaDescription = 'Blogs about information technology relevant to Nepal';
require __DIR__ . '/includes/header.php';
?>

<h1>Blog</h1>

<?php if (empty($posts)): ?>
    <p class="empty-state">No blog posts yet.</p>
<?php else: ?>
    <div class="post-list">
        <?php foreach ($posts as $post): ?>
            <article class="post-card">
                <span class="badge badge-blog">blog</span>
                <h2><a href="post.php?slug=<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
                <time><?= date('F j, Y', strtotime($post['created_at'])) ?></time>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
