<?php
require_once __DIR__ . '/config.php';

$semester = (int)($_GET['semester'] ?? 0);
if ($semester < 1 || $semester > 8) {
    header('Location: semesters.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT subject,
        SUM(type = 'note') AS note_count,
        SUM(type = 'solution') AS solution_count,
        SUM(type = 'question_paper') AS question_count,
        COUNT(*) AS post_count
     FROM posts
     WHERE semester = ? AND type IN ('note', 'solution', 'question_paper')
     GROUP BY subject
     ORDER BY subject ASC"
);
$stmt->execute([$semester]);
$subjects = $stmt->fetchAll();

$pageTitle = 'Semester ' . $semester . ' — Subjects';
$metaDescription = 'List of subjects and their available resources';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$canonicalUrl = $protocol . $_SERVER['HTTP_HOST'] . '/subjects.php?semester=' . $semester;
require __DIR__ . '/includes/header.php';
?>

<p class="breadcrumb"><a href="semesters">Semesters</a> &rsaquo; Semester <?= $semester ?></p>

<h1>Semester <?= $semester ?></h1>

<?php if (empty($subjects)): ?>
    <p class="empty-state">No subjects posted yet for this semester.</p>
<?php else: ?>
    <div class="subject-grid">
    <?php foreach ($subjects as $row): ?>
        <a href="subject.php?semester=<?= $semester ?>&subject=<?= urlencode($row['subject']) ?>" class="subject-card">
            <span class="subject-card-title"><?= htmlspecialchars($row['subject']) ?></span>
            <span class="subject-card-breakdown">
                <span class="mini-badge mini-badge-note"><?= (int)$row['note_count'] ?> Notes</span>
                <span class="mini-badge mini-badge-solution"><?= (int)$row['solution_count'] ?> Solutions</span>
                <span class="mini-badge mini-badge-question"><?= (int)$row['question_count'] ?> Papers</span>
            </span>
            <span class="subject-card-browse">Browse &rarr;</span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
