<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$baseUrl = $protocol . $_SERVER['HTTP_HOST'];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    <!-- Static pages -->
    <url>
        <loc><?= htmlspecialchars($baseUrl) ?>/index</loc>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($baseUrl) ?>/semesters</loc>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($baseUrl) ?>/blog</loc>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($baseUrl) ?>/about</loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($baseUrl) ?>/contact</loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($baseUrl) ?>/privacy</loc>
        <changefreq>yearly</changefreq>
        <priority>0.1</priority>
    </url>

    <!-- Semester pages -->
    <?php for ($i = 1; $i <= 8; $i++): ?>
    <url>
        <loc><?= htmlspecialchars($baseUrl) ?>/subjects.php?semester=<?= $i ?></loc>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
    <?php endfor; ?>

    
    <!-- Subject pages: one per distinct semester+subject combination that has content -->
    <?php
    $subjectCombos = $pdo->query(
        "SELECT DISTINCT semester, subject FROM posts 
         WHERE type IN ('note', 'solution', 'question_paper') AND semester IS NOT NULL AND subject IS NOT NULL"
    )->fetchAll();
    foreach ($subjectCombos as $combo):
        $url = $baseUrl . '/subject.php?semester=' . (int)$combo['semester'] . '&subject=' . urlencode($combo['subject']);
    ?>
    <url>
        <loc><?= htmlspecialchars($url) ?></loc>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    <?php endforeach; ?>

    <!-- Every individual post -->
    <?php
    $posts = $pdo->query("SELECT slug, updated_at FROM posts ORDER BY updated_at DESC")->fetchAll();
    foreach ($posts as $post):
    ?>
    <url>
        <loc><?= htmlspecialchars($baseUrl) ?>/post.php?slug=<?= urlencode($post['slug']) ?></loc>
        <lastmod><?= date('c', strtotime($post['updated_at'])) ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php endforeach; ?>

</urlset>