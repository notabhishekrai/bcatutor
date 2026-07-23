<?php
require_once __DIR__ . '/config.php';

$slug = $_GET['slug'] ?? '';
$quiz = null;

if ($slug !== '') {
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE slug = ?");
    $stmt->execute([$slug]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        http_response_code(404);
        $pageTitle = 'Leaderboard Not Found';
        require __DIR__ . '/includes/header.php';
        ?>
        <div style="text-align:center; padding: 40px 0;">
            <p style="font-family:'IBM Plex Mono', monospace; font-size:0.85rem; color:var(--muted); letter-spacing:0.06em;">ERROR 404</p>
            <h1>This quiz doesn't exist</h1>
            <div class="home-links" style="justify-content:center;">
                <a href="/quizzes" class="button">Browse Quizzes</a>
            </div>
        </div>
        <?php
        require __DIR__ . '/includes/footer.php';
        exit;
    }
}

// Only reads an existing cookie (if any) to highlight "you" in the table —
// never creates one just for viewing the leaderboard.
$playerId = getPlayerId(false);

if ($quiz) {
    $stmt = $pdo->prepare("
        SELECT players.player_id, players.nickname, quiz_leaderboard.score, quiz_leaderboard.total, quiz_leaderboard.percent
        FROM quiz_leaderboard
        JOIN players ON players.player_id = quiz_leaderboard.player_id
        WHERE quiz_leaderboard.quiz_id = ?
        ORDER BY quiz_leaderboard.percent DESC, quiz_leaderboard.updated_at ASC
        LIMIT 50
    ");
    $stmt->execute([$quiz['id']]);
    $rows = $stmt->fetchAll();

    $pageTitle = $quiz['title'] . ' Leaderboard';
} else {
    $stmt = $pdo->query("
        SELECT players.player_id, players.nickname,
               SUM(quiz_leaderboard.percent) AS total_points,
               COUNT(*) AS quizzes_taken
        FROM quiz_leaderboard
        JOIN players ON players.player_id = quiz_leaderboard.player_id
        GROUP BY quiz_leaderboard.player_id
        ORDER BY total_points DESC, quizzes_taken DESC
        LIMIT 50
    ");
    $rows = $stmt->fetchAll();

    $pageTitle = 'Global Leaderboard';
}

$medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];

require __DIR__ . '/includes/header.php';
?>

<p class="breadcrumb">
    <a href="quizzes">Quizzes</a> &rsaquo;
    <?php if ($quiz): ?>
        <a href="take-quiz.php?slug=<?= urlencode($quiz['slug']) ?>"><?= htmlspecialchars($quiz['title']) ?></a> &rsaquo; Leaderboard
    <?php else: ?>
        Global Leaderboard
    <?php endif; ?>
</p>

<h1><?= $quiz ? htmlspecialchars($quiz['title']) . ' — Leaderboard' : 'Global Leaderboard' ?></h1>

<?php if (!$quiz): ?>
    <p class="subject-meta">Ranked by total points — your best score (as a %) on every quiz you've taken, added up.</p>
<?php endif; ?>

<?php if (empty($rows)): ?>
    <p class="empty-state">No scores yet — be the first to <?= $quiz ? 'top this quiz' : 'take a quiz' ?>!</p>
<?php else: ?>
    <div class="table-scroll">
        <table class="admin-table leaderboard-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Nickname</th>
                    <?php if ($quiz): ?>
                        <th>Score</th>
                        <th>%</th>
                    <?php else: ?>
                        <th>Quizzes Taken</th>
                        <th>Total Points</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $row): ?>
                    <?php $rank = $i + 1; ?>
                    <tr class="<?= ($playerId && $row['player_id'] === $playerId) ? 'leaderboard-row-you' : '' ?>">
                        <td><?= $medals[$rank] ?? $rank ?></td>
                        <td>
                            <?= htmlspecialchars($row['nickname']) ?>
                            <?php if ($playerId && $row['player_id'] === $playerId): ?>
                                <span class="leaderboard-you-tag">You</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($quiz): ?>
                            <td><?= (int)$row['score'] ?> / <?= (int)$row['total'] ?></td>
                            <td><?= round((float)$row['percent']) ?>%</td>
                        <?php else: ?>
                            <td><?= (int)$row['quizzes_taken'] ?></td>
                            <td><?= round((float)$row['total_points']) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<p class="field-hint" style="margin-top:16px;">
    <?php if ($quiz): ?>
        <a href="leaderboard.php">View global leaderboard &rarr;</a>
    <?php else: ?>
        <a href="quizzes">Browse quizzes &rarr;</a>
    <?php endif; ?>
</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
