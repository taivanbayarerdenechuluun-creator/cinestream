<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

requireLogin();

$db = getDB();
$currentUser = getCurrentUser();

if (!$currentUser) {
    redirect('/login.php');
}


/*
|--------------------------------------------------------------------------
| REMOVE HISTORY ITEM
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('403 Forbidden - Invalid CSRF token.');
    }

    $historyId = (int) ($_POST['history_id'] ?? 0);

    if ($historyId > 0) {

        $deleteStmt = $db->prepare(
            'DELETE FROM watch_history
             WHERE id = :id
               AND user_id = :user_id'
        );

        $deleteStmt->execute([
            ':id' => $historyId,
            ':user_id' => (int) $currentUser['id']
        ]);
    }

    redirect('/history.php');
}


/*
|--------------------------------------------------------------------------
| GET WATCH HISTORY
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare(
    'SELECT
        wh.id,
        wh.movie_id,
        wh.progress,
        wh.watched_at,
        m.title,
        m.description,
        m.release_year,
        m.duration,
        m.poster,
        m.rating,
        m.is_premium
     FROM watch_history wh
     INNER JOIN movies m
        ON m.id = wh.movie_id
     WHERE wh.user_id = :user_id
     ORDER BY wh.watched_at DESC'
);

$stmt->execute([
    ':user_id' => (int) $currentUser['id']
]);

$history = $stmt->fetchAll();


$pageTitle = 'Watch History';

require_once __DIR__ . '/includes/header.php';
?>


<section class="section">

    <div class="container">

        <!-- =====================================================
             HEADER
        ====================================================== -->

        <div class="section-header">

            <div>

                <h1 class="section-title">
                    Watch History
                </h1>

                <p style="color:#888;margin-top:8px;">
                    Movies you have recently watched
                </p>

            </div>

        </div>


        <?php if (!empty($history)): ?>

            <div class="movie-grid">

                <?php foreach ($history as $item): ?>

                    <?php

                    $progress = (float) ($item['progress'] ?? 0);

                    if ($progress < 0) {
                        $progress = 0;
                    }

                    if ($progress > 100) {
                        $progress = 100;
                    }

                    $movieId = (int) $item['movie_id'];

                    ?>

                    <div
                        class="movie-card"
                        style="position:relative;"
                    >


                        <!-- =================================================
                             POSTER
                        ================================================== -->

                        <a
                            href="<?= BASE_URL ?>/movie.php?id=<?= $movieId ?>"
                            style="
                                text-decoration:none;
                                color:inherit;
                                display:block;
                            "
                        >

                            <div class="movie-poster">

                                <?php if (!empty($item['poster'])): ?>

                                    <img
                                        src="<?= e($item['poster']) ?>"
                                        alt="<?= e($item['title']) ?>"
                                    >

                                <?php else: ?>

                                    <div
                                        style="
                                            width:100%;
                                            height:100%;
                                            display:flex;
                                            align-items:center;
                                            justify-content:center;
                                            color:#777;
                                        "
                                    >
                                        No Poster
                                    </div>

                                <?php endif; ?>


                                <?php if ((int) $item['is_premium'] === 1): ?>

                                    <div class="movie-badge">
                                        Premium
                                    </div>

                                <?php endif; ?>


                                <!-- PLAY OVERLAY -->

                                <div
                                    class="movie-overlay"
                                    style="
                                        pointer-events:none;
                                    "
                                >

                                    <span
                                        style="
                                            width:55px;
                                            height:55px;
                                            border-radius:50%;
                                            background:#e50914;
                                            display:flex;
                                            align-items:center;
                                            justify-content:center;
                                            font-size:22px;
                                        "
                                    >
                                        ▶
                                    </span>

                                </div>

                            </div>

                        </a>


                        <!-- =================================================
                             MOVIE INFO
                        ================================================== -->

                        <div class="movie-info">

                            <a
                                href="<?= BASE_URL ?>/movie.php?id=<?= $movieId ?>"
                                style="
                                    text-decoration:none;
                                    color:inherit;
                                "
                            >

                                <h3 class="movie-title">
                                    <?= e($item['title']) ?>
                                </h3>

                            </a>


                            <!-- META -->

                            <div class="movie-meta">

                                <span>
                                    <?= (int) $item['release_year'] ?>
                                </span>

                                <span>
                                    <?= (int) $item['duration'] ?> min
                                </span>

                                <span class="movie-rating">
                                    ★
                                    <?= number_format(
                                        (float) $item['rating'],
                                        1
                                    ) ?>
                                </span>

                            </div>


                            <!-- =================================================
                                 PROGRESS
                            ================================================== -->

                            <div style="margin-top:15px;">

                                <div
                                    style="
                                        display:flex;
                                        justify-content:space-between;
                                        color:#777;
                                        font-size:12px;
                                        margin-bottom:6px;
                                    "
                                >

                                    <span>
                                        Watched
                                    </span>

                                    <span>
                                        <?= number_format($progress, 0) ?>%
                                    </span>

                                </div>


                                <div
                                    style="
                                        width:100%;
                                        height:5px;
                                        background:#2a2a2a;
                                        border-radius:10px;
                                        overflow:hidden;
                                    "
                                >

                                    <div
                                        style="
                                            width:<?= $progress ?>%;
                                            height:100%;
                                            background:#e50914;
                                            border-radius:10px;
                                        "
                                    ></div>

                                </div>

                            </div>


                            <!-- =================================================
                                 BUTTONS
                            ================================================== -->

                            <div
                                style="
                                    display:flex;
                                    gap:8px;
                                    margin-top:15px;
                                    flex-wrap:wrap;
                                    position:relative;
                                    z-index:20;
                                "
                            >

                                <!-- CONTINUE -->

                                <a
                                    href="<?= BASE_URL ?>/watch.php?movie_id=<?= $movieId ?>"
                                    class="btn btn-primary"
                                    style="
                                        flex:1;
                                        position:relative;
                                        z-index:30;
                                        cursor:pointer;
                                        display:inline-flex;
                                        align-items:center;
                                        justify-content:center;
                                    "
                                >
                                    ▶ Continue
                                </a>


                                <!-- REMOVE -->

                                <form
                                    method="POST"
                                    action="<?= BASE_URL ?>/history.php"
                                    style="
                                        position:relative;
                                        z-index:30;
                                        margin:0;
                                    "
                                    onsubmit="
                                        return confirm(
                                            'Remove this movie from your watch history?'
                                        );
                                    "
                                >

                                    <?= csrfField() ?>

                                    <input
                                        type="hidden"
                                        name="history_id"
                                        value="<?= (int) $item['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-secondary"
                                        title="Remove"
                                    >
                                        🗑
                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <!-- =====================================================
                 EMPTY STATE
            ====================================================== -->

            <div
                style="
                    text-align:center;
                    padding:80px 20px;
                    background:#151515;
                    border:1px solid #292929;
                    border-radius:18px;
                "
            >

                <div style="font-size:60px;">
                    🎬
                </div>

                <h2 style="margin:20px 0 10px;">
                    No Watch History
                </h2>

                <p style="color:#777;margin-bottom:25px;">
                    Movies you watch will appear here.
                </p>

                <a
                    href="<?= BASE_URL ?>/movies.php"
                    class="btn btn-primary"
                >
                    Browse Movies
                </a>

            </div>

        <?php endif; ?>

    </div>

</section>


<?php
require_once __DIR__ . '/includes/footer.php';
?>