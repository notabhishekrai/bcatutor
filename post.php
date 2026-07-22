<?php
require_once __DIR__ . '/config.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = ?");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Post Not Found';
    require __DIR__ . '/includes/header.php';
    ?>
    <div style="text-align:center; padding: 40px 0;">
        <p style="font-family:'IBM Plex Mono', monospace; font-size:0.85rem; color:var(--muted); letter-spacing:0.06em;">ERROR 404</p>
        <h1>This post doesn't exist</h1>
        <p style="color:var(--muted); max-width:40ch; margin:0 auto 28px;">
            It may have been removed, or the link might be incorrect.
        </p>
        <div class="home-links" style="justify-content:center;">
            <a href="/" class="button">Back to Home</a>
            <a href="/semesters" class="button">Browse Notes & Solutions</a>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Related posts: same semester + subject, excluding this post itself
$relatedPosts = [];
if ($post['semester'] && $post['subject']) {
    $relatedStmt = $pdo->prepare(
        "SELECT title, slug, type, created_at FROM posts 
         WHERE semester = ? AND subject = ? AND id != ?
         ORDER BY created_at DESC
         LIMIT 5"
    );
    $relatedStmt->execute([$post['semester'], $post['subject'], $post['id']]);
    $relatedPosts = $relatedStmt->fetchAll();
}

$pageTitle = $post['title'];
$wideLayout = true;
$metaDescription = $post['meta_description'];

// ---- Open Graph data for link previews (WhatsApp, Facebook, Telegram, etc.) ----

// Fallback description: if no meta description was set, use a plain-text
// excerpt from the post content instead of leaving the preview blank
$ogDescription = $metaDescription;
if ($ogDescription === '' || $ogDescription === null) {
    $plainText = trim(strip_tags($post['content']));
    $ogDescription = mb_substr($plainText, 0, 160);
}

// Find the first <img> tag in the content, if any, to use as the preview image
$ogImage = null;
if (preg_match('/<img[^>]+src="([^"]+)"/i', $post['content'], $matches)) {
    $imageUrl = $matches[1];
    // Convert a relative URL (e.g. /uploads/abc.webp) into an absolute one,
    // since Open Graph requires a full URL, not a relative path
    if (strpos($imageUrl, 'http') !== 0) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $imageUrl = $protocol . $_SERVER['HTTP_HOST'] . $imageUrl;
    }
    $ogImage = $imageUrl;
}

$ogUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/post.php?slug=' . urlencode($post['slug']);
$canonicalUrl = $ogUrl; // same URL — post.php's canonical form is just its own slug-based URL


// Simple Article structured data (JSON-LD) for search engines
$schemaData = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $post['title'],
    'datePublished' => date('c', strtotime($post['created_at'])),
    'dateModified' => date('c', strtotime($post['updated_at'])),
    'author' => [
        '@type' => 'Organization',
        'name' => 'BCA TUTOR'
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'BCA TUTOR'
    ]
];
if (!empty($ogDescription)) {
    $schemaData['description'] = $ogDescription;
}
if (!empty($ogImage)) {
    $schemaData['image'] = $ogImage;
}

$canonicalUrl = $ogUrl; // same URL — post.php's canonical form is just its own slug-based URL

require __DIR__ . '/includes/header.php';
?>


 <p class="breadcrumb">
    <?php if ($post['semester'] && $post['subject']): ?>
        <a href="semesters">Semesters</a> &rsaquo;
        <a href="subjects.php?semester=<?= $post['semester'] ?>">Semester <?= (int)$post['semester'] ?></a> &rsaquo;
        <a href="subject.php?semester=<?= $post['semester'] ?>&subject=<?= urlencode($post['subject']) ?>"><?= htmlspecialchars($post['subject']) ?></a> &rsaquo;
        <?= htmlspecialchars($post['title']) ?>
        <?php else: ?>
            <a href="blog">Blog</a> &rsaquo;
            <?= htmlspecialchars($post['title']) ?>
        <?php endif; ?>
    </p>
<div class="post-layout">
    
   


    
    <article class="post-full">
        <span class="badge badge-<?= htmlspecialchars($post['type']) ?>"><?= htmlspecialchars($post['type']) ?></span>
        <h1><?= htmlspecialchars($post['title']) ?></h1>
        <?php if ($post['semester'] && $post['subject']): ?>
            <p class="subject-meta">Semester <?= (int)$post['semester'] ?> — <?= htmlspecialchars($post['subject']) ?></p>
        <?php endif; ?>
        <time><?= date('F j, Y', strtotime($post['created_at'])) ?></time>

        <div class="post-content" id="post-content">
            <?= $post['content'] ?>
        </div>

        <?php if (isLoggedIn()): ?>
            <p class="admin-actions">
                <a href="admin/edit.php?id=<?= $post['id'] ?>">Edit this post</a>
            </p>
        <?php endif; ?>
    </article>

    <aside class="toc" id="toc">
        <p class="toc-label">On this page</p>
        <nav id="toc-list"></nav>
    </aside>
</div>

<?php if (!empty($relatedPosts)): ?>
    <section class="related-posts">
        <p class="related-posts-label">More from <?= htmlspecialchars($post['subject']) ?> — Semester <?= (int)$post['semester'] ?></p>
        <div class="post-list">
            <?php foreach ($relatedPosts as $related): ?>
                <article class="post-card">
                    <span class="badge badge-<?= htmlspecialchars($related['type']) ?>"><?= htmlspecialchars(str_replace('_', ' ', $related['type'])) ?></span>
                    <h3><a href="post.php?slug=<?= urlencode($related['slug']) ?>"><?= htmlspecialchars($related['title']) ?></a></h3>
                    <time><?= date('F j, Y', strtotime($related['created_at'])) ?></time>
                </article>
            <?php endforeach; ?>
        </div>
        <a href="subject.php?semester=<?= (int)$post['semester'] ?>&subject=<?= urlencode($post['subject']) ?>" class="related-posts-viewall">
            View all <?= htmlspecialchars($post['subject']) ?> content &rarr;
        </a>
    </section>
<?php endif; ?>

<script>
    (function () {
        var content = document.getElementById('post-content');
        var tocList = document.getElementById('toc-list');
        var toc = document.getElementById('toc');
        var headings = content.querySelectorAll('h2');

        if (headings.length === 0) {
            toc.style.display = 'none';
            return;
        }

        headings.forEach(function (heading, index) {
            if (!heading.id) {
                heading.id = 'section-' + index + '-' + heading.textContent
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-|-$/g, '');
            }
            var link = document.createElement('a');
            link.href = '#' + heading.id;
            link.textContent = heading.textContent;
            tocList.appendChild(link);
        });
    })();
</script>



<?php require __DIR__ . '/includes/footer.php'; ?>
