<?php
require_once __DIR__ . '/config.php';

$stmt = $pdo->query("SELECT semester, COUNT(*) as post_count FROM posts WHERE type IN ('note', 'solution', 'question_paper') GROUP BY semester");
$counts = [];
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['semester']] = $row['post_count'];
}

$pageTitle = 'Browse by Semester';
$metaDescription = 'List of semesters';
require __DIR__ . '/includes/header.php';
?>

<h1>Browse by Semester</h1>
<div class="semester-grid">
    <?php for ($i = 1; $i <= 8; $i++): ?>
    <?php $count = $counts[$i] ?? 0; ?>
    <a href="subjects.php?semester=<?= $i ?>" class="semester-card">
        <span class="semester-card-num"><?= $i ?></span>
        <span class="semester-card-title">Semester <?= $i ?></span>
        <span class="post-count"><?= $count ?> post<?= $count !== 1 ? 's' : '' ?></span>
        <span class="semester-card-browse">Browse &rarr;</span>
    </a>
<?php endfor; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
