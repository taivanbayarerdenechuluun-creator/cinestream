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


<section class="section" style="padding-top:20px;">

    <!-- =====================================================
         BACKDROP BANNER
    ====================================================== -->
    <?php if (!empty($movie['backdrop'])): ?>
        <div
            style="
                position:relative;
                width:100%;
                height:clamp(220px, 35vw, 420px);
                margin-top:-20px;
                margin-bottom:35px;
                background:
                    linear-gradient(180deg, rgba(8,8,8,0.1) 0%, rgba(8,8,8,0.7) 70%, #080808 100%),
                    linear-gradient(90deg, rgba(8,8,8,0.85) 0%, rgba(8,8,8,0.2) 50%, rgba(8,8,8,0.85) 100%),
                    url('<?= e($movie['backdrop']) ?>') center/cover no-repeat;
                border-bottom:1px solid #222;
            "
        >
            <div
                class="container"
                style="
                    height:100%;
                    display:flex;
                    align-items:flex-end;
                    padding-bottom:25px;
                "
            >
                <?php if (!empty($movie['tagline'])): ?>
                    <div
                        style="
                            background:rgba(0,0,0,0.65);
                            backdrop-filter:blur(8px);
                            border:1px solid rgba(229,9,20,0.4);
                            color:#fff;
                            font-style:italic;
                            padding:8px 16px;
                            border-radius:20px;
                            font-size:14px;
                            letter-spacing:0.3px;
                        "
                    >
                        ✨ &ldquo;<?= e($movie['tagline']) ?>&rdquo;
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

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
                margin-bottom:25px;
                font-size:14px;
                transition:color 0.2s;
            "
            onmouseover="this.style.color='#fff'"
            onmouseout="this.style.color='#aaa'"
        >
            ← Back to Movies
        </a>


        <!-- =====================================================
             MOVIE DETAILS
        ====================================================== -->
        <div
            style="
                display:grid;
                grid-template-columns:minmax(250px,320px) 1fr;
                gap:45px;
                align-items:start;
            "
        >

            <!-- POSTER COLUMN -->
            <div>
                <div class="movie-poster" style="box-shadow:0 15px 35px rgba(0,0,0,0.7);border:1px solid #262626;border-radius:12px;overflow:hidden;position:relative;">

                    <?php if (!empty($movie['poster'])): ?>
                        <img
                            src="<?= e($movie['poster']) ?>"
                            alt="<?= e($movie['title']) ?>"
                            style="width:100%;display:block;"
                        >
                    <?php else: ?>
                        <div
                            style="
                                width:100%;
                                aspect-ratio:2/3;
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                color:#777;
                                background:#151515;
                            "
                        >
                            No Poster
                        </div>
                    <?php endif; ?>

                    <?php if ((int) $movie['is_premium'] === 1): ?>
                        <div class="movie-badge" style="position:absolute;top:12px;right:12px;background:#f5c518;color:#111;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:700;">
                            👑 Premium
                        </div>
                    <?php endif; ?>
                </div>

                <!-- QUICK INFO PILLS -->
                <div
                    style="
                        margin-top:18px;
                        display:flex;
                        flex-direction:column;
                        gap:10px;
                        background:#141414;
                        border:1px solid #242424;
                        border-radius:12px;
                        padding:16px;
                    "
                >
                    <?php if (!empty($movie['director'])): ?>
                        <div style="font-size:13px;color:#aaa;">
                            <span style="color:#666;">Director:</span>
                            <strong style="color:#fff;display:block;margin-top:2px;">
                                <?= e($movie['director']) ?>
                            </strong>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($movie['release_date'])): ?>
                        <div style="font-size:13px;color:#aaa;">
                            <span style="color:#666;">Release Date:</span>
                            <span style="color:#ddd;display:block;margin-top:2px;">
                                <?= !empty($releaseDateFormatted) ? e($releaseDateFormatted) : e($movie['release_date']) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div style="font-size:13px;color:#aaa;">
                        <span style="color:#666;">Runtime:</span>
                        <span style="color:#ddd;display:block;margin-top:2px;">
                            <?= e($durationDisplay) ?> (<?= (int) $movie['duration'] ?> mins)
                        </span>
                    </div>

                    <?php if (!empty($movie['tmdb_id'])): ?>
                        <div style="font-size:12px;color:#666;padding-top:6px;border-top:1px solid #222;display:flex;justify-content:space-between;align-items:center;">
                            <span>TMDB ID: #<?= (int) $movie['tmdb_id'] ?></span>
                            <span style="background:#032541;color:#01b4e4;padding:2px 6px;border-radius:4px;font-weight:700;font-size:10px;">TMDB</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>


            <!-- INFORMATION COLUMN -->
            <div>

                <h1
                    style="
                        font-size:clamp(30px,4.5vw,50px);
                        margin:0 0 12px;
                        line-height:1.15;
                        letter-spacing:-0.5px;
                    "
                >
                    <?= e($movie['title']) ?>
                </h1>

                <!-- TAGLINE -->
                <?php if (!empty($movie['tagline'])): ?>
                    <p
                        style="
                            color:#e50914;
                            font-size:17px;
                            font-style:italic;
                            margin:0 0 18px;
                            font-weight:500;
                        "
                    >
                        &ldquo;<?= e($movie['tagline']) ?>&rdquo;
                    </p>
                <?php endif; ?>


                <!-- META BAR -->
                <div
                    style="
                        display:flex;
                        align-items:center;
                        gap:14px;
                        margin-bottom:22px;
                        flex-wrap:wrap;
                    "
                >
                    <!-- Rating -->
                    <span
                        style="
                            color:#f5c518;
                            font-size:20px;
                            font-weight:700;
                            display:inline-flex;
                            align-items:center;
                            gap:4px;
                        "
                    >
                        ★ <?= number_format((float) $movie['rating'], 1) ?>
                    </span>

                    <span style="color:#777;font-size:14px;">
                        (<?= $ratingCount ?> үнэлгээ)
                    </span>

                    <span style="color:#444;">•</span>

                    <!-- Release Date -->
                    <span style="color:#ccc;font-size:14px;display:inline-flex;align-items:center;gap:5px;">
                        📅 <?= !empty($releaseDateFormatted) ? e($releaseDateFormatted) : (int) $movie['release_year'] ?>
                    </span>

                    <span style="color:#444;">•</span>

                    <!-- Duration -->
                    <span style="color:#ccc;font-size:14px;display:inline-flex;align-items:center;gap:5px;">
                        ⏱️ <?= e($durationDisplay) ?>
                    </span>

                    <!-- Premium/Free -->
                    <?php if ((int) $movie['is_premium'] === 1): ?>
                        <span
                            style="
                                background:#f5c518;
                                color:#111;
                                padding:4px 10px;
                                border-radius:6px;
                                font-size:12px;
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
                                padding:4px 10px;
                                border-radius:6px;
                                font-size:12px;
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
                                    background:rgba(255,255,255,0.03);
                                    padding:5px 12px;
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
                        font-size:19px;
                        margin-bottom:10px;
                        color:#eee;
                    "
                >
                    Киноны тухай / Overview
                </h2>

                <p
                    style="
                        color:#aaa;
                        line-height:1.8;
                        max-width:780px;
                        margin-bottom:28px;
                        font-size:15px;
                    "
                >
                    <?= nl2br(e($movie['description'])) ?>
                </p>


                <!-- =================================================
                     FINANCIALS & INCOME / BOX OFFICE
                ================================================== -->
                <?php if (!empty($movie['revenue']) || !empty($movie['budget']) || !empty($movie['director'])): ?>
                    <div
                        style="
                            display:grid;
                            grid-template-columns:repeat(auto-fit, minmax(170px, 1fr));
                            gap:16px;
                            margin-bottom:30px;
                            padding:20px;
                            background:#141414;
                            border:1px solid #262626;
                            border-radius:14px;
                            max-width:780px;
                        "
                    >
                        <!-- Income / Revenue -->
                        <?php if (!empty($movie['revenue'])): ?>
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <span style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">💰 Box Office / Income</span>
                                <span style="font-size:19px;font-weight:700;color:#4ade80;">
                                    $<?= number_format((float) $movie['revenue']) ?>
                                </span>
                                <span style="font-size:11px;color:#666;">
                                    Дэлхий даяарх орлого
                                </span>
                            </div>
                        <?php endif; ?>

                        <!-- Budget -->
                        <?php if (!empty($movie['budget'])): ?>
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <span style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">💼 Төсөв / Budget</span>
                                <span style="font-size:19px;font-weight:700;color:#f3f4f6;">
                                    $<?= number_format((float) $movie['budget']) ?>
                                </span>
                                <span style="font-size:11px;color:#666;">
                                    Бүтээсэн нийт зардал
                                </span>
                            </div>
                        <?php endif; ?>

                        <!-- Net Profit / Return -->
                        <?php if (!empty($movie['revenue']) && !empty($movie['budget'])): ?>
                            <?php $netIncome = (float) $movie['revenue'] - (float) $movie['budget']; ?>
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <span style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">📈 Цэвэр ашиг / Net Return</span>
                                <span style="font-size:19px;font-weight:700;color:<?= $netIncome >= 0 ? '#4ade80' : '#f87171' ?>;">
                                    <?= $netIncome >= 0 ? '+$' . number_format($netIncome) : '-$' . number_format(abs($netIncome)) ?>
                                </span>
                                <span style="font-size:11px;color:#666;">
                                    <?= $netIncome >= 0 ? 'Box Office Hit' : 'Ашиггүй' ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>


                <!-- =================================================
                     ACTION BUTTONS
                ================================================== -->
                <div
                    style="
                        display:flex;
                        gap:12px;
                        flex-wrap:wrap;
                        align-items:center;
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


                    <!-- WATCH TRAILER -->
                    <?php if (!empty($ytTrailerKey)): ?>
                        <button
                            type="button"
                            onclick="openTrailerModal()"
                            class="btn btn-secondary"
                            style="cursor:pointer;"
                        >
                            🎬 Watch Trailer
                        </button>
                    <?php elseif (!empty($movie['trailer_url'])): ?>
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
                                style="margin:0;"
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
                                style="margin:0;"
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
                     RATING WIDGET
                ================================================== -->
                <div
                    style="
                        margin-top:35px;
                        padding:22px;
                        background:#151515;
                        border:1px solid #292929;
                        border-radius:16px;
                        max-width:650px;
                    "
                >
                    <h3 style="margin:0 0 8px;font-size:17px;">
                        ⭐ Rate this movie
                    </h3>

                    <?php if ($currentUser): ?>
                        <p
                            style="
                                color:#888;
                                margin:0 0 16px;
                                font-size:14px;
                            "
                        >
                            <?php if ($userRating > 0): ?>
                                Your rating: <strong style="color:#f5c518;"><?= $userRating ?>/5</strong>
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
                                            padding:8px 14px;
                                            cursor:pointer;
                                            font-size:18px;
                                            transition:transform 0.1s, border-color 0.2s;
                                        "
                                        title="<?= $i ?> star"
                                    >
                                        ★
                                    </button>
                                <?php endfor; ?>
                            </div>
                        </form>
                    <?php else: ?>
                        <p style="color:#888;margin:0;font-size:14px;">
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
             TOP CAST / ЖҮЖИГЧИД
        ====================================================== -->
        <?php if (!empty($castList)): ?>
            <div style="margin-top:65px;">

                <div
                    style="
                        display:flex;
                        align-items:center;
                        justify-content:space-between;
                        margin-bottom:22px;
                        gap:15px;
                        flex-wrap:wrap;
                    "
                >
                    <h2 class="section-title" style="margin:0;">
                        👥 Top Cast / Кинонд тоглосон жүжигчид
                    </h2>

                    <span style="color:#777;font-size:14px;">
                        <?= count($castList) ?> жүжигчин
                    </span>
                </div>

                <div
                    style="
                        display:grid;
                        grid-template-columns:repeat(auto-fill, minmax(130px, 1fr));
                        gap:16px;
                    "
                >
                    <?php foreach ($castList as $actor): ?>
                        <div
                            style="
                                background:#141414;
                                border:1px solid #262626;
                                border-radius:12px;
                                padding:14px 10px;
                                text-align:center;
                                transition:transform 0.2s, border-color 0.2s, box-shadow 0.2s;
                            "
                            onmouseover="this.style.borderColor='#e50914';this.style.transform='translateY(-4px)';this.style.boxShadow='0 10px 25px rgba(0,0,0,0.6)'"
                            onmouseout="this.style.borderColor='#262626';this.style.transform='none';this.style.boxShadow='none'"
                        >
                            <div
                                style="
                                    width:76px;
                                    height:76px;
                                    border-radius:50%;
                                    overflow:hidden;
                                    margin:0 auto 10px;
                                    background:#202020;
                                    border:2px solid #333;
                                "
                            >
                                <?php if (!empty($actor['profile'])): ?>
                                    <img
                                        src="<?= e($actor['profile']) ?>"
                                        alt="<?= e($actor['name']) ?>"
                                        loading="lazy"
                                        style="width:100%;height:100%;object-fit:cover;"
                                    >
                                <?php else: ?>
                                    <div
                                        style="
                                            width:100%;
                                            height:100%;
                                            display:flex;
                                            align-items:center;
                                            justify-content:center;
                                            color:#666;
                                            font-size:26px;
                                        "
                                    >
                                        👤
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div
                                style="
                                    font-weight:700;
                                    font-size:13px;
                                    color:#fff;
                                    margin-bottom:3px;
                                    white-space:nowrap;
                                    overflow:hidden;
                                    text-overflow:ellipsis;
                                "
                                title="<?= e($actor['name']) ?>"
                            >
                                <?= e($actor['name']) ?>
                            </div>

                            <div
                                style="
                                    font-size:11px;
                                    color:#888;
                                    white-space:nowrap;
                                    overflow:hidden;
                                    text-overflow:ellipsis;
                                "
                                title="<?= e($actor['character']) ?>"
                            >
                                <?= e($actor['character'] ?: 'Cast') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
        <?php endif; ?>


        <!-- =====================================================
             TRAILER SECTION
        ====================================================== -->
        <?php if (!empty($ytTrailerKey) || !empty($movie['trailer_url'])): ?>
            <div id="trailer-section" style="margin-top:65px;">

                <h2 class="section-title">
                    🎬 Official Trailer
                </h2>

                <div
                    style="
                        margin-top:22px;
                        max-width:960px;
                        position:relative;
                        border-radius:14px;
                        overflow:hidden;
                        border:1px solid #292929;
                        background:#000;
                    "
                >
                    <?php if (!empty($ytTrailerKey)): ?>
                        <div style="position:relative;padding-bottom:56.25%;height:0;">
                            <iframe
                                src="https://www.youtube-nocookie.com/embed/<?= e($ytTrailerKey) ?>?rel=0"
                                title="<?= e($movie['title']) ?> Trailer"
                                style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                            ></iframe>
                        </div>
                    <?php else: ?>
                        <video
                            controls
                            preload="metadata"
                            style="width:100%;display:block;background:#000;"
                        >
                            <source
                                src="<?= e($movie['trailer_url']) ?>"
                                type="video/mp4"
                            >
                            Your browser does not support video playback.
                        </video>
                    <?php endif; ?>
                </div>

            </div>
        <?php endif; ?>


        <!-- =====================================================
             TRAILER MODAL
        ====================================================== -->
        <?php if (!empty($ytTrailerKey)): ?>
            <div
                id="trailerModal"
                style="
                    display:none;
                    position:fixed;
                    inset:0;
                    z-index:9999;
                    background:rgba(0,0,0,0.88);
                    backdrop-filter:blur(8px);
                    align-items:center;
                    justify-content:center;
                    padding:20px;
                "
                onclick="if(event.target === this) closeTrailerModal()"
            >
                <div
                    style="
                        position:relative;
                        width:100%;
                        max-width:920px;
                        background:#111;
                        border:1px solid #333;
                        border-radius:14px;
                        overflow:hidden;
                        box-shadow:0 25px 60px rgba(0,0,0,0.9);
                    "
                >
                    <div
                        style="
                            display:flex;
                            align-items:center;
                            justify-content:space-between;
                            padding:14px 20px;
                            border-bottom:1px solid #222;
                        "
                    >
                        <div style="font-weight:700;font-size:15px;color:#fff;">
                            🎬 <?= e($movie['title']) ?> - Official Trailer
                        </div>

                        <button
                            type="button"
                            onclick="closeTrailerModal()"
                            style="
                                background:none;
                                border:none;
                                color:#aaa;
                                font-size:26px;
                                cursor:pointer;
                                line-height:1;
                                padding:0 6px;
                            "
                            onmouseover="this.style.color='#fff'"
                            onmouseout="this.style.color='#aaa'"
                        >
                            &times;
                        </button>
                    </div>

                    <div style="position:relative;padding-bottom:56.25%;height:0;">
                        <iframe
                            id="trailerModalIframe"
                            src=""
                            style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </div>
                </div>
            </div>

            <script>
            function openTrailerModal() {
                var modal = document.getElementById('trailerModal');
                var iframe = document.getElementById('trailerModalIframe');
                if (modal && iframe) {
                    iframe.src = 'https://www.youtube-nocookie.com/embed/<?= e($ytTrailerKey) ?>?autoplay=1&rel=0';
                    modal.style.display = 'flex';
                }
            }
            function closeTrailerModal() {
                var modal = document.getElementById('trailerModal');
                var iframe = document.getElementById('trailerModalIframe');
                if (modal && iframe) {
                    iframe.src = '';
                    modal.style.display = 'none';
                }
            }
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeTrailerModal();
                }
            });
            </script>
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