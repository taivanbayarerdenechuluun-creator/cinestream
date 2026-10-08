<?php
declare(strict_types=1);

/**
 * test_connection.php  -  TEMPORARY Phase 1 test page.
 * Open: http://localhost/movie-streaming/test_connection.php
 * DELETE this file when Phase 1 is confirmed working.
 */

// Only allow access from your own computer
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/includes/auth.php';

$expectedTables = [
    'users', 'genres', 'movies', 'movie_genres', 'watchlist',
    'watch_history', 'ratings', 'comments', 'subscriptions', 'payments',
];

$checks = [];   // [label, ok(bool), details]
$pdo    = null;

// 1. Database connection (shows the real error because this is local-only)
try {
    $pdo = connectDatabase();
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    $checks[] = ['Database connection', true, 'Connected to "' . DB_NAME . '" (server version ' . $version . ')'];
} catch (PDOException $ex) {
    $checks[] = ['Database connection', false, $ex->getMessage()
        . ' - Is MySQL started in XAMPP and did you import database.sql?'];
}

$tableRows = [];
$movies    = [];

if ($pdo) {
    // 2. Tables exist + row counts
    try {
        $existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $missing  = array_diff($expectedTables, $existing);

        foreach ($expectedTables as $table) {
            if (in_array($table, $existing, true)) {
                // $table comes from our own fixed list above, never from user input
                $count = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
                $tableRows[$table] = (int) $count;
            }
        }

        $checks[] = [
            'Tables created',
            empty($missing),
            empty($missing) ? 'All ' . count($expectedTables) . ' tables found.'
                            : 'Missing tables: ' . implode(', ', $missing),
        ];
    } catch (PDOException $ex) {
        $checks[] = ['Tables created', false, $ex->getMessage()];
    }

    // 3. Sample query using a prepared statement (movies + genres)
    try {
        $stmt = $pdo->prepare(
            'SELECT m.id, m.title, m.release_year, m.rating, m.is_premium,
                    GROUP_CONCAT(g.name ORDER BY g.name SEPARATOR ", ") AS genres
             FROM movies m
             LEFT JOIN movie_genres mg ON mg.movie_id = m.id
             LEFT JOIN genres g ON g.id = mg.genre_id
             GROUP BY m.id
             ORDER BY m.id
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', 20, PDO::PARAM_INT);
        $stmt->execute();
        $movies = $stmt->fetchAll();
        $checks[] = ['Demo movies', count($movies) > 0, count($movies) . ' movies loaded with their genres.'];
    } catch (PDOException $ex) {
        $checks[] = ['Demo movies', false, $ex->getMessage()];
    }
}

// 4. Password hashing
$hash = password_hash('test-password', PASSWORD_DEFAULT);
$checks[] = [
    'password_hash / password_verify',
    password_verify('test-password', $hash) && !password_verify('wrong', $hash),
    'Hashing and verification work.',
];

// 5. Session
$checks[] = [
    'Secure session',
    session_status() === PHP_SESSION_ACTIVE,
    'Session "' . session_name() . '" is active. Logged in: ' . (isLoggedIn() ? 'yes' : 'no (expected for now)'),
];

$allOk = !in_array(false, array_column($checks, 1), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Phase 1 Test</title>
    <style>
        body { font-family: Arial, sans-serif; background:#141414; color:#eee; max-width:900px; margin:30px auto; padding:0 15px; }
        h1, h2 { color:#e50914; }
        .box { padding:12px 16px; margin:8px 0; border-radius:6px; background:#222; border-left:6px solid #888; }
        .ok  { border-color:#2ecc71; }
        .bad { border-color:#e74c3c; }
        table { border-collapse:collapse; width:100%; margin-top:10px; }
        th, td { border:1px solid #444; padding:8px; text-align:left; }
        th { background:#2a2a2a; }
        .banner { padding:15px; border-radius:6px; font-weight:bold; margin:15px 0; }
        .banner.ok { background:#1e5631; } .banner.bad { background:#7a1f1f; }
    </style>
</head>
<body>
    <h1>Phase 1 - Connection Test</h1>

    <div class="banner <?= $allOk ? 'ok' : 'bad' ?>">
        <?= $allOk ? 'ALL CHECKS PASSED - Phase 1 is working.' : 'SOME CHECKS FAILED - see details below.' ?>
    </div>

    <?php foreach ($checks as [$label, $ok, $details]): ?>
        <div class="box <?= $ok ? 'ok' : 'bad' ?>">
            <strong><?= $ok ? 'PASS' : 'FAIL' ?> - <?= e($label) ?></strong><br>
            <?= e($details) ?>
        </div>
    <?php endforeach; ?>

    <?php if ($tableRows): ?>
        <h2>Tables and row counts</h2>
        <table>
            <tr><th>Table</th><th>Rows</th></tr>
            <?php foreach ($tableRows as $table => $count): ?>
                <tr><td><?= e($table) ?></td><td><?= e($count) ?></td></tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php if ($movies): ?>
        <h2>Demo movies</h2>
        <table>
            <tr><th>ID</th><th>Title</th><th>Year</th><th>Rating</th><th>Premium</th><th>Genres</th></tr>
            <?php foreach ($movies as $m): ?>
                <tr>
                    <td><?= e($m['id']) ?></td>
                    <td><?= e($m['title']) ?></td>
                    <td><?= e($m['release_year']) ?></td>
                    <td><?= e($m['rating']) ?></td>
                    <td><?= $m['is_premium'] ? 'Yes' : 'No' ?></td>
                    <td><?= e($m['genres']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <p style="margin-top:30px;color:#aaa;">Next step: open <code>setup_demo_users.php</code> once, then delete both test files.</p>
</body>
</html>
