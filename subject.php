<?php
require_once __DIR__ . '/config.php';

$semester = (int)($_GET['semester'] ?? 0);
$subject = trim($_GET['subject'] ?? '');

if ($semester < 1 || $semester > 8 || $subject === '') {
    header('Location: semesters.php');
    exit;
}

$notesStmt = $pdo->prepare(
    "SELECT id, title, slug, created_at FROM posts 
     WHERE semester = ? AND subject = ? AND type = 'note' 
     ORDER BY sort_order ASC, created_at ASC"
);
$notesStmt->execute([$semester, $subject]);
$notes = $notesStmt->fetchAll();

$solutionsStmt = $pdo->prepare(
    "SELECT id, title, slug, created_at FROM posts 
     WHERE semester = ? AND subject = ? AND type = 'solution' 
     ORDER BY created_at DESC"
);
$solutionsStmt->execute([$semester, $subject]);
$solutions = $solutionsStmt->fetchAll();

$questionPapersStmt = $pdo->prepare(
    "SELECT id, title, slug, created_at FROM posts 
     WHERE semester = ? AND subject = ? AND type = 'question_paper' 
     ORDER BY created_at DESC"
);
$questionPapersStmt->execute([$semester, $subject]);
$questionPapers = $questionPapersStmt->fetchAll();

$pageTitle = htmlspecialchars($subject) . ' — Semester ' . $semester;
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$canonicalUrl = $protocol . $_SERVER['HTTP_HOST'] . '/subject.php?semester=' . $semester . '&subject=' . urlencode($subject);
require __DIR__ . '/includes/header.php';
?>

<p class="breadcrumb">
    <a href="semesters">Semesters</a> &rsaquo;
    <a href="subjects.php?semester=<?= $semester ?>">Semester <?= $semester ?></a> &rsaquo;
    <?= htmlspecialchars($subject) ?>
</p>

<h1><?= htmlspecialchars($subject) ?></h1>
<p class="subject-meta">Semester <?= $semester ?></p>

<div class="tab-toggle" role="tablist">
    <button type="button" class="tab-btn is-active" data-tab="notes" role="tab" aria-selected="true">
        Notes <span class="tab-count"><?= count($notes) ?></span>
    </button>
    <button type="button" class="tab-btn" data-tab="solutions" role="tab" aria-selected="false">
        Solution Papers <span class="tab-count"><?= count($solutions) ?></span>
    </button>
    <button type="button" class="tab-btn" data-tab="questionpapers" role="tab" aria-selected="false">
        Question Papers <span class="tab-count"><?= count($questionPapers) ?></span>
    </button>
</div>

<div class="tab-panel" id="panel-notes">
    <?php if (empty($notes)): ?>
        <p class="empty-state">No notes posted yet for this subject.</p>
    <?php else: ?>
        <div class="post-list">
            <?php foreach ($notes as $post): ?>
                <article class="post-card">
                    <span class="badge badge-note">note</span>
                    <h3><a href="post.php?slug=<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h3>
                    <time><?= date('F j, Y', strtotime($post['created_at'])) ?></time>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="tab-panel" id="panel-solutions" hidden>
    <?php if (empty($solutions)): ?>
        <p class="empty-state">No solution papers posted yet for this subject.</p>
    <?php else: ?>
        <div class="post-list">
            <?php foreach ($solutions as $post): ?>
                <article class="post-card">
                    <span class="badge badge-solution">solution</span>
                    <h3><a href="post.php?slug=<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h3>
                    <time><?= date('F j, Y', strtotime($post['created_at'])) ?></time>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="tab-panel" id="panel-questionpapers" hidden>
    <?php if (empty($questionPapers)): ?>
        <p class="empty-state">No question papers posted yet for this subject.</p>
    <?php else: ?>
        <div class="post-list">
            <?php foreach ($questionPapers as $post): ?>
                <article class="post-card">
                    <span class="badge badge-question">question paper</span>
                    <h3><a href="post.php?slug=<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h3>
                    <time><?= date('F j, Y', strtotime($post['created_at'])) ?></time>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
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
        });
    });
</script>

<?php if (isLoggedIn()): ?>
    <p><a href="admin/create.php">+ Add a note or solution for this subject</a></p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
