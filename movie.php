<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$db = getDB();

$movieId = (int) ($_GET['id'] ?? 0);

if ($movieId <= 0) {
    http_response_code(404);
    exit('Movie not found.');
}


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    requireLogin();

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('403 Forbidden - Invalid CSRF token.');
    }

    $currentUser = getCurrentUser();

    if (!$currentUser) {
        redirect('/login.php');
    }

    $action = $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | ADD WATCHLIST
    |--------------------------------------------------------------------------
    */

    if ($action === 'add_watchlist') {

        $checkStmt = $db->prepare(
            'SELECT id
             FROM watchlist
             WHERE user_id = :user_id
               AND movie_id = :movie_id
             LIMIT 1'
        );

        $checkStmt->execute([
            ':user_id' => (int) $currentUser['id'],
            ':movie_id' => $movieId
        ]);

        if (!$checkStmt->fetch()) {

            $insertStmt = $db->prepare(
                'INSERT INTO watchlist
                    (user_id, movie_id)
                 VALUES
                    (:user_id, :movie_id)'
            );

            $insertStmt->execute([
                ':user_id' => (int) $currentUser['id'],
                ':movie_id' => $movieId
            ]);
        }

        redirect('/movie.php?id=' . $movieId);
    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE WATCHLIST
    |--------------------------------------------------------------------------
    */

    if ($action === 'remove_watchlist') {

        $deleteStmt = $db->prepare(
            'DELETE FROM watchlist
             WHERE user_id = :user_id
               AND movie_id = :movie_id'
        );

        $deleteStmt->execute([
            ':user_id' => (int) $currentUser['id'],
            ':movie_id' => $movieId
        ]);

        redirect('/movie.php?id=' . $movieId);
    }


    /*
    |--------------------------------------------------------------------------
    | RATE MOVIE
    |--------------------------------------------------------------------------
    */

    if ($action === 'rate_movie') {

        $rating = (int) ($_POST['rating'] ?? 0);

        if ($rating < 1 || $rating > 5) {
            http_response_code(400);
            exit('Rating must be between 1 and 5.');
        }


        // Check existing rating
        $checkRatingStmt = $db->prepare(
            'SELECT id
             FROM ratings
             WHERE user_id = :user_id
               AND movie_id = :movie_id
             LIMIT 1'
        );

        $checkRatingStmt->execute([
            ':user_id' => (int) $currentUser['id'],
            ':movie_id' => $movieId
        ]);

        $existingRating = $checkRatingStmt->fetch();


        if ($existingRating) {

            // Update existing rating
            $updateRatingStmt = $db->prepare(
                'UPDATE ratings
                 SET rating = :rating
                 WHERE id = :id'
            );

            $updateRatingStmt->execute([
                ':rating' => $rating,
                ':id' => (int) $existingRating['id']
            ]);

        } else {

            // Create new rating
            $insertRatingStmt = $db->prepare(
                'INSERT INTO ratings
                    (user_id, movie_id, rating)
                 VALUES
                    (:user_id, :movie_id, :rating)'
            );

            $insertRatingStmt->execute([
                ':user_id' => (int) $currentUser['id'],
                ':movie_id' => $movieId,
                ':rating' => $rating
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Update Movie Average Rating
        |--------------------------------------------------------------------------
        */

        $averageStmt = $db->prepare(
            'SELECT AVG(rating)
             FROM ratings
             WHERE movie_id = :movie_id'
        );

        $averageStmt->execute([
            ':movie_id' => $movieId
        ]);

        $averageRating = (float) $averageStmt->fetchColumn();


        $updateMovieStmt = $db->prepare(
            'UPDATE movies
             SET rating = :rating
             WHERE id = :movie_id'
        );

        $updateMovieStmt->execute([
            ':rating' => round($averageRating, 1),
            ':movie_id' => $movieId
        ]);

        redirect('/movie.php?id=' . $movieId);
    }


    /*
    |--------------------------------------------------------------------------
    | ADD COMMENT
    |--------------------------------------------------------------------------
    */

    if ($action === 'add_comment') {

        $commentText = trim($_POST['comment'] ?? '');

        if ($commentText === '') {
            redirect('/movie.php?id=' . $movieId);
        }

        if (mb_strlen($commentText) > 2000) {
            http_response_code(400);
            exit('Comment is too long. Maximum 2000 characters.');
        }


        $commentStmt = $db->prepare(
            'INSERT INTO comments
                (user_id, movie_id, comment)
             VALUES
                (:user_id, :movie_id, :comment)'
        );

        $commentStmt->execute([
            ':user_id' => (int) $currentUser['id'],
            ':movie_id' => $movieId,
            ':comment' => $commentText
        ]);

        redirect('/movie.php?id=' . $movieId);
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE OWN COMMENT
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete_comment') {

        $commentId = (int) ($_POST['comment_id'] ?? 0);

        if ($commentId > 0) {

            $deleteCommentStmt = $db->prepare(
                'DELETE FROM comments
                 WHERE id = :id
                   AND user_id = :user_id
                   AND movie_id = :movie_id'
            );

            $deleteCommentStmt->execute([
                ':id' => $commentId,
                ':user_id' => (int) $currentUser['id'],
                ':movie_id' => $movieId
            ]);
        }

        redirect('/movie.php?id=' . $movieId);
    }
}


/*
|--------------------------------------------------------------------------
| GET MOVIE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/tmdb.php';

$stmt = $db->prepare(
    'SELECT
        id,
        tmdb_id,
        title,
        description,
        tagline,
        release_year,
        release_date,
        duration,
        budget,
        revenue,
        poster,
        backdrop,
        director,
        trailer_key,
        trailer_url,
        video_url,
        cast_data,
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

// Automatically ensure TMDB data is fetched & stored if missing
ensureMovieTmdbData($db, $movie);

// Parse Cast Data
$castList = [];
if (!empty($movie['cast_data'])) {
    $decodedCast = json_decode((string) $movie['cast_data'], true);
    if (is_array($decodedCast)) {
        $castList = $decodedCast;
    }
}

// Format duration
$durationMinutes = (int) ($movie['duration'] ?? 0);
$durationHours = intdiv($durationMinutes, 60);
$durationRemainder = $durationMinutes % 60;
$durationDisplay = $durationHours > 0
    ? ($durationRemainder > 0 ? "{$durationHours}h {$durationRemainder}m" : "{$durationHours}h")
    : "{$durationMinutes}m";

// Format release date
$releaseDateFormatted = '';
if (!empty($movie['release_date'])) {
    $ts = strtotime((string) $movie['release_date']);
    if ($ts) {
        $releaseDateFormatted = date('M j, Y', $ts);
    }
}

// YouTube trailer key
$ytTrailerKey = !empty($movie['trailer_key']) ? (string) $movie['trailer_key'] : null;
if (!$ytTrailerKey && !empty($movie['trailer_url'])) {
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i', $movie['trailer_url'], $matches)) {
        $ytTrailerKey = $matches[1];
    }
}


/*
|--------------------------------------------------------------------------
| GET GENRES
|--------------------------------------------------------------------------
*/

$genreStmt = $db->prepare(
    'SELECT
        g.id,
        g.name
     FROM genres g
     INNER JOIN movie_genres mg
        ON mg.genre_id = g.id
     WHERE mg.movie_id = :movie_id
     ORDER BY g.name ASC'
);

$genreStmt->execute([
    ':movie_id' => $movieId
]);

$movieGenres = $genreStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$currentUser = getCurrentUser();

$isPremiumMovie = (int) $movie['is_premium'] === 1;

$hasPremiumAccess =
    $currentUser &&
    $currentUser['subscription_status'] === 'active'
    && !empty($currentUser['subscription_expires_at'])
    && strtotime($currentUser['subscription_expires_at']) > time();


/*
|--------------------------------------------------------------------------
| CHECK WATCHLIST
|--------------------------------------------------------------------------
*/

$isInWatchlist = false;

if ($currentUser) {

    $watchlistStmt = $db->prepare(
        'SELECT id
         FROM watchlist
         WHERE user_id = :user_id
           AND movie_id = :movie_id
         LIMIT 1'
    );

    $watchlistStmt->execute([
        ':user_id' => (int) $currentUser['id'],
        ':movie_id' => $movieId
    ]);

    $isInWatchlist = (bool) $watchlistStmt->fetch();
}


/*
|--------------------------------------------------------------------------
| GET USER RATING
|--------------------------------------------------------------------------
*/

$userRating = 0;

if ($currentUser) {

    $userRatingStmt = $db->prepare(
        'SELECT rating
         FROM ratings
         WHERE user_id = :user_id
           AND movie_id = :movie_id
         LIMIT 1'
    );

    $userRatingStmt->execute([
        ':user_id' => (int) $currentUser['id'],
        ':movie_id' => $movieId
    ]);

    $savedRating = $userRatingStmt->fetchColumn();

    if ($savedRating !== false) {
        $userRating = (int) $savedRating;
    }
}


/*
|--------------------------------------------------------------------------
| RATING COUNT
|--------------------------------------------------------------------------
*/

$ratingCountStmt = $db->prepare(
    'SELECT COUNT(*)
     FROM ratings
     WHERE movie_id = :movie_id'
);

$ratingCountStmt->execute([
    ':movie_id' => $movieId
]);

$ratingCount = (int) $ratingCountStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| GET COMMENTS
|--------------------------------------------------------------------------
*/

$commentsStmt = $db->prepare(
    'SELECT
        c.id,
        c.user_id,
        c.comment,
        c.created_at,
        u.name
     FROM comments c
     INNER JOIN users u
        ON u.id = c.user_id
     WHERE c.movie_id = :movie_id
     ORDER BY c.created_at DESC'
);

$commentsStmt->execute([
    ':movie_id' => $movieId
]);

$comments = $commentsStmt->fetchAll();


$pageTitle = $movie['title'];

require_once __DIR__ . '/includes/header.php';
?>


<section class="section">

    <div class="container">


        <!-- =====================================================
             BACK
        ====================================================== -->

        <a
            href="<?= BASE_URL ?>/movies.php"
            style="
                display:inline-flex;
                align-items:center;
                gap:8px;
                color:#aaa;
                text-decoration:none;
                margin-bottom:30px;
            "
        >
            ← Back to Movies
        </a>


        <!-- =====================================================
             MOVIE DETAILS
        ====================================================== -->

        <div
            style="
                display:grid;
                grid-template-columns:minmax(250px,350px) 1fr;
                gap:45px;
                align-items:start;
            "
        >


            <!-- POSTER -->

            <div>

                <div class="movie-poster">

                    <?php if (!empty($movie['poster'])): ?>

                        <img
                            src="<?= e($movie['poster']) ?>"
                            alt="<?= e($movie['title']) ?>"
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


                    <?php if ((int) $movie['is_premium'] === 1): ?>

                        <div class="movie-badge">
                            Premium
                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- INFORMATION -->

            <div>

                <h1
                    style="
                        font-size:clamp(32px,5vw,54px);
                        margin:0 0 18px;
                        line-height:1.1;
                    "
                >
                    <?= e($movie['title']) ?>
                </h1>


                <!-- META -->

                <div
                    style="
                        display:flex;
                        align-items:center;
                        gap:20px;
                        margin-bottom:22px;
                        flex-wrap:wrap;
                    "
                >

                    <span
                        style="
                            color:#f5c518;
                            font-size:20px;
                            font-weight:700;
                        "
                    >
                        ★ <?= number_format((float) $movie['rating'], 1) ?>
                    </span>

                    <span style="color:#aaa;">
                        <?= $ratingCount ?> ratings
                    </span>

                    <span style="color:#aaa;">
                        <?= (int) $movie['release_year'] ?>
                    </span>

                    <span style="color:#aaa;">
                        <?= (int) $movie['duration'] ?> minutes
                    </span>


                    <?php if ((int) $movie['is_premium'] === 1): ?>

                        <span
                            style="
                                background:#f5c518;
                                color:#111;
                                padding:5px 10px;
                                border-radius:6px;
                                font-size:13px;
                                font-weight:700;
                            "
                        >
                            PREMIUM
                        </span>

                    <?php else: ?>

                        <span
                            style="
                                background:#263b2d;
                                color:#65d98b;
                                padding:5px 10px;
                                border-radius:6px;
                                font-size:13px;
                                font-weight:700;
                            "
                        >
                            FREE
                        </span>

                    <?php endif; ?>

                </div>


                <!-- GENRES -->

                <?php if (!empty($movieGenres)): ?>

                    <div
                        style="
                            display:flex;
                            gap:8px;
                            flex-wrap:wrap;
                            margin-bottom:25px;
                        "
                    >

                        <?php foreach ($movieGenres as $genre): ?>

                            <span
                                style="
                                    border:1px solid #333;
                                    padding:6px 12px;
                                    border-radius:20px;
                                    color:#bbb;
                                    font-size:13px;
                                "
                            >
                                <?= e($genre['name']) ?>
                            </span>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>


                <!-- DESCRIPTION -->

                <h2
                    style="
                        font-size:20px;
                        margin-bottom:12px;
                    "
                >
                    About this movie
                </h2>


                <p
                    style="
                        color:#aaa;
                        line-height:1.8;
                        max-width:750px;
                        margin-bottom:30px;
                    "
                >
                    <?= nl2br(e($movie['description'])) ?>
                </p>


                <!-- =================================================
                     ACTION BUTTONS
                ================================================== -->

                <div
                    style="
                        display:flex;
                        gap:12px;
                        flex-wrap:wrap;
                    "
                >

                    <!-- WATCH / PREMIUM -->

<?php if ($isPremiumMovie && !$hasPremiumAccess): ?>

    <a
        href="<?= BASE_URL ?>/subscription.php"
        class="btn btn-primary"
    >
        👑 Get Premium
    </a>

<?php else: ?>

    <a
        href="<?= BASE_URL ?>/watch.php?id=<?= (int) $movie['id'] ?>"
        class="btn btn-primary"
    >
        ▶ Watch Now
    </a>

<?php endif; ?>


                    <!-- TRAILER -->

                    <?php if (!empty($movie['trailer_url'])): ?>

                        <a
                            href="<?= e($movie['trailer_url']) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-secondary"
                        >
                            🎬 Watch Trailer
                        </a>

                    <?php endif; ?>


                    <!-- WATCHLIST -->

                    <?php if ($currentUser): ?>

                        <?php if ($isInWatchlist): ?>

                            <form
                                method="POST"
                                action="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                            >

                                <?= csrfField() ?>

                                <input
                                    type="hidden"
                                    name="action"
                                    value="remove_watchlist"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-secondary"
                                >
                                    ❤️ Remove from Watchlist
                                </button>

                            </form>

                        <?php else: ?>

                            <form
                                method="POST"
                                action="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                            >

                                <?= csrfField() ?>

                                <input
                                    type="hidden"
                                    name="action"
                                    value="add_watchlist"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-secondary"
                                >
                                    🤍 Add to Watchlist
                                </button>

                            </form>

                        <?php endif; ?>

                    <?php else: ?>

                        <a
                            href="<?= BASE_URL ?>/login.php"
                            class="btn btn-secondary"
                        >
                            🤍 Sign in to Save
                        </a>

                    <?php endif; ?>

                </div>


                <!-- =================================================
                     RATING
                ================================================== -->

                <div
                    style="
                        margin-top:35px;
                        padding:25px;
                        background:#151515;
                        border:1px solid #292929;
                        border-radius:16px;
                        max-width:650px;
                    "
                >

                    <h3 style="margin:0 0 8px;">
                        ⭐ Rate this movie
                    </h3>


                    <?php if ($currentUser): ?>

                        <p
                            style="
                                color:#888;
                                margin:0 0 18px;
                            "
                        >
                            <?php if ($userRating > 0): ?>

                                Your rating:
                                <?= $userRating ?>/5

                            <?php else: ?>

                                Choose a rating from 1 to 5.

                            <?php endif; ?>
                        </p>


                        <form
                            method="POST"
                            action="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                        >

                            <?= csrfField() ?>

                            <input
                                type="hidden"
                                name="action"
                                value="rate_movie"
                            >


                            <div
                                style="
                                    display:flex;
                                    gap:8px;
                                    flex-wrap:wrap;
                                "
                            >

                                <?php for ($i = 1; $i <= 5; $i++): ?>

                                    <button
                                        type="submit"
                                        name="rating"
                                        value="<?= $i ?>"
                                        style="
                                            border:1px solid <?= $i <= $userRating ? '#f5c518' : '#333' ?>;
                                            background:<?= $i <= $userRating ? 'rgba(245,197,24,.15)' : '#101010' ?>;
                                            color:#f5c518;
                                            border-radius:10px;
                                            padding:10px 14px;
                                            cursor:pointer;
                                            font-size:20px;
                                        "
                                        title="<?= $i ?> star"
                                    >
                                        ★
                                    </button>

                                <?php endfor; ?>

                            </div>

                        </form>

                    <?php else: ?>

                        <p style="color:#888;margin:0;">

                            Please

                            <a
                                href="<?= BASE_URL ?>/login.php"
                                style="color:#e50914;"
                            >
                                sign in
                            </a>

                            to rate this movie.

                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- =====================================================
             TRAILER
        ====================================================== -->

        <?php if (!empty($movie['trailer_url'])): ?>

            <div style="margin-top:70px;">

                <h2 class="section-title">
                    Trailer
                </h2>

                <div
                    style="
                        margin-top:25px;
                        max-width:900px;
                    "
                >

                    <video
                        controls
                        preload="metadata"
                        style="
                            width:100%;
                            display:block;
                            border-radius:14px;
                            background:#000;
                        "
                    >

                        <source
                            src="<?= e($movie['trailer_url']) ?>"
                            type="video/mp4"
                        >

                        Your browser does not support video playback.

                    </video>

                </div>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             COMMENTS
        ====================================================== -->

        <div
            style="
                margin-top:70px;
                max-width:900px;
            "
        >

            <div
                style="
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:15px;
                    margin-bottom:25px;
                    flex-wrap:wrap;
                "
            >

                <h2 class="section-title" style="margin:0;">
                    💬 Comments
                </h2>

                <span style="color:#777;">
                    <?= count($comments) ?> comments
                </span>

            </div>


            <!-- ADD COMMENT -->

            <?php if ($currentUser): ?>

                <form
                    method="POST"
                    action="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                    style="
                        background:#151515;
                        border:1px solid #292929;
                        border-radius:16px;
                        padding:20px;
                        margin-bottom:30px;
                    "
                >

                    <?= csrfField() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="add_comment"
                    >


                    <textarea
                        name="comment"
                        rows="4"
                        maxlength="2000"
                        required
                        placeholder="Write your comment..."
                        style="
                            width:100%;
                            box-sizing:border-box;
                            resize:vertical;
                            background:#101010;
                            color:#fff;
                            border:1px solid #333;
                            border-radius:10px;
                            padding:14px;
                            font-family:inherit;
                            font-size:15px;
                            outline:none;
                        "
                    ></textarea>


                    <div
                        style="
                            display:flex;
                            justify-content:flex-end;
                            margin-top:12px;
                        "
                    >

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Post Comment
                        </button>

                    </div>

                </form>

            <?php else: ?>

                <div
                    style="
                        background:#151515;
                        border:1px solid #292929;
                        border-radius:16px;
                        padding:22px;
                        margin-bottom:30px;
                        color:#888;
                    "
                >

                    Please

                    <a
                        href="<?= BASE_URL ?>/login.php"
                        style="
                            color:#e50914;
                            text-decoration:none;
                        "
                    >
                        sign in
                    </a>

                    to write a comment.

                </div>

            <?php endif; ?>


            <!-- COMMENTS LIST -->

            <?php if (!empty($comments)): ?>

                <div
                    style="
                        display:flex;
                        flex-direction:column;
                        gap:15px;
                    "
                >

                    <?php foreach ($comments as $comment): ?>

                        <div
                            style="
                                background:#151515;
                                border:1px solid #292929;
                                border-radius:16px;
                                padding:20px;
                            "
                        >

                            <div
                                style="
                                    display:flex;
                                    align-items:center;
                                    justify-content:space-between;
                                    gap:15px;
                                    flex-wrap:wrap;
                                "
                            >

                                <div
                                    style="
                                        display:flex;
                                        align-items:center;
                                        gap:12px;
                                    "
                                >

                                    <!-- Avatar -->

                                    <div
                                        style="
                                            width:42px;
                                            height:42px;
                                            border-radius:50%;
                                            background:#e50914;
                                            display:flex;
                                            align-items:center;
                                            justify-content:center;
                                            font-weight:700;
                                            color:#fff;
                                        "
                                    >
                                        <?= e(
                                            strtoupper(
                                                substr(
                                                    $comment['name'],
                                                    0,
                                                    1
                                                )
                                            )
                                        ) ?>
                                    </div>


                                    <div>

                                        <div
                                            style="
                                                font-weight:700;
                                            "
                                        >
                                            <?= e($comment['name']) ?>
                                        </div>

                                        <div
                                            style="
                                                color:#666;
                                                font-size:12px;
                                                margin-top:3px;
                                            "
                                        >
                                            <?= date(
                                                'M j, Y H:i',
                                                strtotime($comment['created_at'])
                                            ) ?>
                                        </div>

                                    </div>

                                </div>


                                <!-- DELETE OWN COMMENT -->

                                <?php if (
                                    $currentUser &&
                                    (int) $currentUser['id'] ===
                                    (int) $comment['user_id']
                                ): ?>

                                    <form
                                        method="POST"
                                        action="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                                    >

                                        <?= csrfField() ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete_comment"
                                        >

                                        <input
                                            type="hidden"
                                            name="comment_id"
                                            value="<?= (int) $comment['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            style="
                                                background:none;
                                                border:none;
                                                color:#888;
                                                cursor:pointer;
                                                font-size:13px;
                                            "
                                            onclick="
                                                return confirm(
                                                    'Delete this comment?'
                                                );
                                            "
                                        >
                                            🗑 Delete
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>


                            <!-- COMMENT TEXT -->

                            <div
                                style="
                                    margin-top:18px;
                                    color:#ccc;
                                    line-height:1.7;
                                    white-space:pre-wrap;
                                    word-break:break-word;
                                "
                            >
                                <?= e($comment['comment']) ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div
                    style="
                        text-align:center;
                        padding:45px 20px;
                        background:#151515;
                        border:1px solid #292929;
                        border-radius:16px;
                        color:#777;
                    "
                >

                    <div style="font-size:45px;">
                        💬
                    </div>

                    <h3 style="color:#aaa;">
                        No comments yet
                    </h3>

                    <p>
                        Be the first to share your thoughts!
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>

</section>


<?php
require_once __DIR__ . '/includes/footer.php';
?>