<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

requireLogin();

$db = getDB();
$currentUser = getCurrentUser();

if (!$currentUser) {
    redirect('/login.php');
}

$userId = (int) $currentUser['id'];


/*
|--------------------------------------------------------------------------
| GET MOVIE ID
|--------------------------------------------------------------------------
*/

$movieId = 0;

if (isset($_GET['id'])) {
    $movieId = (int) $_GET['id'];
} elseif (isset($_GET['movie_id'])) {
    $movieId = (int) $_GET['movie_id'];
} elseif (isset($_GET['movie'])) {
    $movieId = (int) $_GET['movie'];
}

if ($movieId <= 0) {
    http_response_code(404);
    exit('Movie not found.');
}


/*
|--------------------------------------------------------------------------
| GET MOVIE
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare(
    'SELECT
        id,
        title,
        description,
        release_year,
        duration,
        poster,
        video_url,
        rating,
        is_premium
     FROM movies
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute([
    ':id' => $movieId
]);

$movie = $stmt->fetch();

if (!$movie) {
    http_response_code(404);
    exit('Movie not found.');
}


/*
|--------------------------------------------------------------------------
| PREMIUM ACCESS CHECK
|--------------------------------------------------------------------------
*/

$isPremiumMovie = (int) $movie['is_premium'] === 1;

$hasPremiumAccess =
    $currentUser['subscription_status'] === 'active'
    && !empty($currentUser['subscription_expires_at'])
    && strtotime($currentUser['subscription_expires_at']) > time();


if ($isPremiumMovie && !$hasPremiumAccess) {
    http_response_code(403);
    exit('This movie requires an active Premium subscription.');
}


/*
|--------------------------------------------------------------------------
| GET WATCH HISTORY
|--------------------------------------------------------------------------
*/

$historyStmt = $db->prepare(
    'SELECT
        id,
        progress,
        position_seconds
     FROM watch_history
     WHERE user_id = :user_id
       AND movie_id = :movie_id
     LIMIT 1'
);

$historyStmt->execute([
    ':user_id' => $userId,
    ':movie_id' => $movieId
]);

$history = $historyStmt->fetch();


/*
|--------------------------------------------------------------------------
| CREATE WATCH HISTORY IF NOT EXISTS
|--------------------------------------------------------------------------
*/

if ($history) {

    $historyId = (int) $history['id'];

    $savedProgress = (float) ($history['progress'] ?? 0);

    $savedPosition = (float) ($history['position_seconds'] ?? 0);

} else {

    $insertHistory = $db->prepare(
        'INSERT INTO watch_history
            (
                user_id,
                movie_id,
                progress,
                position_seconds,
                watched_at
            )
         VALUES
            (
                :user_id,
                :movie_id,
                0,
                0,
                CURRENT_TIMESTAMP
            )'
    );

    $insertHistory->execute([
        ':user_id' => $userId,
        ':movie_id' => $movieId
    ]);

    $historyId = (int) $db->lastInsertId();

    $savedProgress = 0;
    $savedPosition = 0;
}


/*
|--------------------------------------------------------------------------
| SAVE VIDEO POSITION - AJAX
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json; charset=utf-8');


    /*
    | CSRF
    */

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token.'
        ]);

        exit;
    }


    /*
    | GET CURRENT VIDEO TIME
    */

    $currentTime = (float) ($_POST['current_time'] ?? 0);

    $videoDuration = (float) ($_POST['duration'] ?? 0);


    /*
    | Safety
    */

    if ($currentTime < 0) {
        $currentTime = 0;
    }

    if ($videoDuration <= 0) {

        echo json_encode([
            'success' => false,
            'message' => 'Invalid video duration.'
        ]);

        exit;
    }


    /*
    | Don't allow time beyond video
    */

    if ($currentTime > $videoDuration) {
        $currentTime = $videoDuration;
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULATE PROGRESS %
    |--------------------------------------------------------------------------
    */

    $progress = ($currentTime / $videoDuration) * 100;

    if ($progress < 0) {
        $progress = 0;
    }

    if ($progress > 100) {
        $progress = 100;
    }


    /*
    | Consider movie completed at 95%
    */

    if ($progress >= 95) {
        $progress = 100;
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE EXACT POSITION
    |--------------------------------------------------------------------------
    */

    $saveStmt = $db->prepare(
        'UPDATE watch_history
         SET
            progress = :progress,
            position_seconds = :position_seconds,
            watched_at = CURRENT_TIMESTAMP
         WHERE id = :history_id
           AND user_id = :user_id
           AND movie_id = :movie_id'
    );

    $saveStmt->execute([
        ':progress' => round($progress, 2),
        ':position_seconds' => round($currentTime, 2),
        ':history_id' => $historyId,
        ':user_id' => $userId,
        ':movie_id' => $movieId
    ]);


    echo json_encode([
        'success' => true,
        'progress' => round($progress, 2),
        'position_seconds' => round($currentTime, 2)
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$pageTitle = 'Watching - ' . $movie['title'];

require_once __DIR__ . '/includes/header.php';
?>


<section
    style="
        padding:30px 0 60px;
        background:#080808;
        min-height:calc(100vh - 100px);
    "
>

    <div class="container">


        <!-- BACK -->

        <div style="margin-bottom:20px;">

            <a
                href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                style="
                    color:#aaa;
                    text-decoration:none;
                "
            >
                ← Back to Movie
            </a>

        </div>


        <!-- VIDEO -->

        <div
            style="
                width:100%;
                max-width:1200px;
                margin:0 auto;
            "
        >

            <video
                id="moviePlayer"
                controls
                playsinline
                preload="metadata"
                poster="<?= e((string) ($movie['poster'] ?? '')) ?>"
                style="
                    width:100%;
                    display:block;
                    background:#000;
                    border-radius:12px;
                    max-height:75vh;
                "
            >

                <source
                    src="<?= e((string) $movie['video_url']) ?>"
                    type="video/mp4"
                >

                Your browser does not support video playback.

            </video>

        </div>


        <!-- MOVIE INFO -->

        <div
            style="
                max-width:1200px;
                margin:25px auto 0;
            "
        >

            <h1
                style="
                    margin:0 0 10px;
                    font-size:32px;
                "
            >
                <?= e((string) $movie['title']) ?>
            </h1>


            <div
                style="
                    display:flex;
                    gap:18px;
                    flex-wrap:wrap;
                    color:#888;
                "
            >

                <span>
                    <?= (int) $movie['release_year'] ?>
                </span>

                <span>
                    <?= (int) $movie['duration'] ?> min
                </span>

                <span style="color:#f5c518;">
                    ★ <?= number_format((float) $movie['rating'], 1) ?>
                </span>

            </div>


            <p
                style="
                    max-width:850px;
                    color:#999;
                    line-height:1.7;
                    margin-top:18px;
                "
            >
                <?= nl2br(e((string) $movie['description'])) ?>
            </p>


            <!-- RESUME INFO -->

            <?php if ($savedPosition > 0 && $savedProgress < 95): ?>

                <?php
                $resumeHours = floor($savedPosition / 3600);
                $resumeMinutes = floor(($savedPosition % 3600) / 60);
                $resumeSeconds = floor($savedPosition % 60);

                if ($resumeHours > 0) {
                    $resumeTime = sprintf(
                        '%02d:%02d:%02d',
                        $resumeHours,
                        $resumeMinutes,
                        $resumeSeconds
                    );
                } else {
                    $resumeTime = sprintf(
                        '%02d:%02d',
                        $resumeMinutes,
                        $resumeSeconds
                    );
                }
                ?>

                <div
                    style="
                        margin-top:25px;
                        padding:15px 18px;
                        background:#151515;
                        border:1px solid #292929;
                        border-radius:10px;
                        color:#aaa;
                        max-width:500px;
                    "
                >

                    ▶ Continue from

                    <strong style="color:#fff;">
                        <?= e($resumeTime) ?>
                    </strong>

                    <span style="color:#777;">
                        (<?= number_format($savedProgress, 0) ?>%)
                    </span>

                </div>

            <?php elseif ($savedProgress >= 95): ?>

                <div
                    style="
                        margin-top:25px;
                        padding:15px 18px;
                        background:#151515;
                        border:1px solid #292929;
                        border-radius:10px;
                        color:#65d98b;
                        max-width:500px;
                    "
                >

                    ✓ Movie completed

                </div>

            <?php endif; ?>

        </div>

    </div>

</section>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const player = document.getElementById('moviePlayer');

    if (!player) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | SAVED EXACT POSITION
    |--------------------------------------------------------------------------
    */

    const savedPosition =
        <?= json_encode($savedPosition) ?>;


    const csrfToken =
        <?= json_encode(csrfToken()) ?>;


    let positionRestored = false;


    /*
    |--------------------------------------------------------------------------
    | RESTORE EXACT POSITION
    |--------------------------------------------------------------------------
    */

    player.addEventListener('loadedmetadata', function () {

        if (positionRestored) {
            return;
        }

        positionRestored = true;


        if (
            savedPosition > 0 &&
            savedPosition < player.duration
        ) {

            player.currentTime = savedPosition;

        }

    });


    /*
    |--------------------------------------------------------------------------
    | SAVE POSITION
    |--------------------------------------------------------------------------
    */

    let saving = false;


    function saveProgress() {

        if (
            !player.duration ||
            player.duration <= 0
        ) {
            return;
        }


        if (saving) {
            return;
        }

        saving = true;


        const formData = new FormData();


        formData.append(
            'csrf_token',
            csrfToken
        );


        formData.append(
            'current_time',
            String(player.currentTime)
        );


        formData.append(
            'duration',
            String(player.duration)
        );


        fetch(
            '<?= BASE_URL ?>/watch.php?id=<?= (int) $movie['id'] ?>',
            {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                keepalive: true
            }
        )
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {

            if (!data.success) {

                console.warn(
                    'Could not save video position:',
                    data.message
                );

            }

        })
        .catch(function (error) {

            console.warn(
                'Progress save error:',
                error
            );

        })
        .finally(function () {

            saving = false;

        });

    }


    /*
    |--------------------------------------------------------------------------
    | SAVE EVERY 5 SECONDS
    |--------------------------------------------------------------------------
    */

    setInterval(function () {

        if (!player.paused) {
            saveProgress();
        }

    }, 5000);


    /*
    |--------------------------------------------------------------------------
    | SAVE WHEN PAUSED
    |--------------------------------------------------------------------------
    */

    player.addEventListener('pause', function () {

        saveProgress();

    });


    /*
    |--------------------------------------------------------------------------
    | SAVE WHEN VIDEO ENDS
    |--------------------------------------------------------------------------
    */

    player.addEventListener('ended', function () {

        saveProgress();

    });


    /*
    |--------------------------------------------------------------------------
    | SAVE BEFORE LEAVING PAGE
    |--------------------------------------------------------------------------
    */

    window.addEventListener('beforeunload', function () {

        if (
            !player.duration ||
            player.duration <= 0
        ) {
            return;
        }


        const formData = new FormData();


        formData.append(
            'csrf_token',
            csrfToken
        );


        formData.append(
            'current_time',
            String(player.currentTime)
        );


        formData.append(
            'duration',
            String(player.duration)
        );


        navigator.sendBeacon(
            '<?= BASE_URL ?>/watch.php?id=<?= (int) $movie['id'] ?>',
            formData
        );

    });

});

</script>


<?php
require_once __DIR__ . '/includes/footer.php';
?>