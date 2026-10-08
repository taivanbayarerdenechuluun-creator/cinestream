<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Home';
$db = getDB();
$currentUser = getCurrentUser();

/*
|--------------------------------------------------------------------------
| Helper: Build NOT IN placeholders
|--------------------------------------------------------------------------
*/
function buildNotInPlaceholders(array $ids, string $prefix = 'used'): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));

    if (empty($ids)) {
        return [
            'sql' => '',
            'params' => []
        ];
    }

    $placeholders = [];
    $params = [];

    foreach ($ids as $index => $id) {
        $placeholder = ':' . $prefix . '_' . $index;

        $placeholders[] = $placeholder;
        $params[$placeholder] = $id;
    }

    return [
        'sql' => ' AND id NOT IN (' . implode(', ', $placeholders) . ')',
        'params' => $params
    ];
}

/*
|--------------------------------------------------------------------------
| HERO
|--------------------------------------------------------------------------
*/

$stmt = $db->query(
    'SELECT
        id,
        title,
        description,
        tagline,
        release_year,
        duration,
        poster,
        backdrop,
        rating,
        is_premium
     FROM movies
     ORDER BY rating DESC, release_year DESC
     LIMIT 1'
);

$heroMovie = $stmt->fetch();

/*
|--------------------------------------------------------------------------
| USED MOVIE IDS
|--------------------------------------------------------------------------
*/

$usedMovieIds = [];

if ($heroMovie) {
    $usedMovieIds[] = (int) $heroMovie['id'];
}

/*
|--------------------------------------------------------------------------
| TRENDING
|--------------------------------------------------------------------------
|
| Hero movie is excluded.
| Maximum 5 movies.
|--------------------------------------------------------------------------
*/

$notIn = buildNotInPlaceholders($usedMovieIds, 'trending');

$stmt = $db->prepare(
    'SELECT
        id,
        title,
        description,
        release_year,
        duration,
        poster,
        rating,
        is_premium
     FROM movies
     WHERE 1=1'
     . $notIn['sql'] .
    ' ORDER BY rating DESC, release_year DESC
      LIMIT 5'
);

$stmt->execute($notIn['params']);

$featuredMovies = $stmt->fetchAll();

foreach ($featuredMovies as $movie) {
    $usedMovieIds[] = (int) $movie['id'];
}

/*
|--------------------------------------------------------------------------
| RECENTLY ADDED
|--------------------------------------------------------------------------
|
| Maximum 2 movies.
| All previously displayed movies are excluded.
|--------------------------------------------------------------------------
*/

$notIn = buildNotInPlaceholders($usedMovieIds, 'latest');

$stmt = $db->prepare(
    'SELECT
        id,
        title,
        description,
        release_year,
        duration,
        poster,
        rating,
        is_premium
     FROM movies
     WHERE 1=1'
     . $notIn['sql'] .
    ' ORDER BY created_at DESC, id DESC
      LIMIT 2'
);

$stmt->execute($notIn['params']);

$latestMovies = $stmt->fetchAll();

foreach ($latestMovies as $movie) {
    $usedMovieIds[] = (int) $movie['id'];
}

/*
|--------------------------------------------------------------------------
| PREMIUM COLLECTION
|--------------------------------------------------------------------------
|
| Only Premium movies that have NOT already appeared.
|--------------------------------------------------------------------------
*/

$notIn = buildNotInPlaceholders($usedMovieIds, 'premium');

$stmt = $db->prepare(
    'SELECT
        id,
        title,
        description,
        release_year,
        duration,
        poster,
        rating,
        is_premium
     FROM movies
     WHERE is_premium = 1'
     . $notIn['sql'] .
    ' ORDER BY rating DESC, release_year DESC
      LIMIT 6'
);

$stmt->execute($notIn['params']);

$premiumMovies = $stmt->fetchAll();

foreach ($premiumMovies as $movie) {
    $usedMovieIds[] = (int) $movie['id'];
}

/*
|--------------------------------------------------------------------------
| MORE MOVIES
|--------------------------------------------------------------------------
|
| If there are still unused movies, show them here.
| This guarantees that we can display remaining movies
| without duplicates.
|--------------------------------------------------------------------------
*/

$notIn = buildNotInPlaceholders($usedMovieIds, 'more');

$stmt = $db->prepare(
    'SELECT
        id,
        title,
        description,
        release_year,
        duration,
        poster,
        rating,
        is_premium
     FROM movies
     WHERE 1=1'
     . $notIn['sql'] .
    ' ORDER BY rating DESC, release_year DESC
      LIMIT 6'
);

$stmt->execute($notIn['params']);

$moreMovies = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .home-page {
        background: #0b0b0f;
        min-height: 100vh;
        color: #fff;
    }

    /* =========================================================
       HERO
    ========================================================= */

    .hero {
        position: relative;
        min-height: 600px;
        display: flex;
        align-items: center;
        overflow: hidden;
        background:
            linear-gradient(
                90deg,
                rgba(8, 8, 12, 0.98) 0%,
                rgba(8, 8, 12, 0.90) 35%,
                rgba(8, 8, 12, 0.45) 65%,
                rgba(8, 8, 12, 0.85) 100%
            ),
            linear-gradient(
                0deg,
                #0b0b0f 0%,
                transparent 35%
            ),
            url("<?= e(!empty($heroMovie['backdrop']) ? $heroMovie['backdrop'] : ($heroMovie['poster'] ?? '')) ?>");

        background-size: cover;
        background-position: center;
    }

    .hero::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(
            to top,
            #0b0b0f 0%,
            transparent 30%
        );
        pointer-events: none;
    }

    .hero-content {
        position: relative;
        z-index: 2;
        width: 100%;
        max-width: 1400px;
        margin: 0 auto;
        padding: 90px 40px 100px;
    }

    .hero-info {
        max-width: 650px;
    }

    .hero-badges {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 13px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.12);
        font-size: 13px;
        font-weight: 700;
        backdrop-filter: blur(8px);
    }

    .hero-badge.premium {
        background: rgba(245, 197, 24, 0.16);
        border-color: rgba(245, 197, 24, 0.4);
        color: #f5c518;
    }

    .hero-title {
        font-size: clamp(42px, 6vw, 76px);
        line-height: 0.98;
        margin: 0 0 20px;
        font-weight: 900;
        letter-spacing: -2px;
    }

    .hero-description {
        color: #d0d0d5;
        font-size: 17px;
        line-height: 1.7;
        margin: 0 0 28px;
        max-width: 620px;

        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .hero-meta {
        display: flex;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
        color: #d5d5d5;
        margin-bottom: 30px;
        font-size: 14px;
    }

    .hero-rating {
        color: #f5c518;
        font-weight: 800;
    }

    .hero-buttons {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .hero-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 48px;
        padding: 0 22px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 800;
        transition: 0.2s ease;
    }

    .hero-btn.primary {
        background: #fff;
        color: #111;
    }

    .hero-btn.primary:hover {
        transform: translateY(-2px);
        background: #e9e9e9;
    }

    .hero-btn.secondary {
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(8px);
    }

    .hero-btn.secondary:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    /* =========================================================
       CONTENT
    ========================================================= */

    .home-content {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 40px 70px;
    }

    .movie-section {
        margin-top: 48px;
    }

    .section-header {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 18px;
    }

    .section-title {
        margin: 0;
        font-size: 27px;
        font-weight: 900;
        letter-spacing: -0.5px;
    }

    .section-subtitle {
        color: #85858d;
        font-size: 13px;
        margin-top: 5px;
    }

    .section-link {
        color: #aaa;
        text-decoration: none;
        font-size: 14px;
        font-weight: 700;
        white-space: nowrap;
    }

    .section-link:hover {
        color: #fff;
    }

    /* =========================================================
       MOVIE GRID
    ========================================================= */

    .movie-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 18px;
    }

    .movie-card {
        position: relative;
        min-width: 0;
        overflow: hidden;
        border-radius: 12px;
        background: #15151b;
        transition:
            transform 0.25s ease,
            box-shadow 0.25s ease;
    }

    .movie-card:hover {
        transform: translateY(-7px);
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.45);
    }

    .movie-poster {
        position: relative;
        aspect-ratio: 2 / 3;
        overflow: hidden;
        background: #202027;
    }

    .movie-poster img {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: cover;
        transition: transform 0.35s ease;
    }

    .movie-card:hover .movie-poster img {
        transform: scale(1.06);
    }

    .movie-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.48);
        opacity: 0;
        transition: opacity 0.25s ease;
    }

    .movie-card:hover .movie-overlay {
        opacity: 1;
    }

    .play-button {
        width: 54px;
        height: 54px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #fff;
        color: #111;
        font-size: 21px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
    }

    .movie-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        z-index: 2;
        padding: 5px 9px;
        border-radius: 6px;
        background: rgba(0, 0, 0, 0.75);
        color: #fff;
        font-size: 11px;
        font-weight: 800;
    }

    .movie-badge.premium {
        background: #f5c518;
        color: #111;
    }

    .movie-info {
        padding: 13px;
    }

    .movie-title {
        margin: 0 0 7px;
        font-size: 15px;
        line-height: 1.3;
        font-weight: 800;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .movie-title a {
        color: #fff;
        text-decoration: none;
    }

    .movie-title a:hover {
        color: #f5c518;
    }

    .movie-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        color: #8d8d96;
        font-size: 12px;
    }

    .movie-rating {
        color: #f5c518;
        font-weight: 800;
    }

    /* =========================================================
       PREMIUM BANNER
    ========================================================= */

    .premium-banner {
        position: relative;
        overflow: hidden;
        margin-top: 55px;
        padding: 34px 38px;
        border-radius: 18px;
        background:
            radial-gradient(
                circle at 80% 30%,
                rgba(245, 197, 24, 0.25),
                transparent 35%
            ),
            linear-gradient(
                135deg,
                #19160b,
                #241f0c
            );
        border: 1px solid rgba(245, 197, 24, 0.25);
    }

    .premium-banner-content {
        position: relative;
        z-index: 2;
        max-width: 700px;
    }

    .premium-banner h2 {
        margin: 0 0 10px;
        font-size: 30px;
    }

    .premium-banner p {
        margin: 0 0 20px;
        color: #c9c5b5;
        line-height: 1.6;
    }

    .premium-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        padding: 0 20px;
        border-radius: 8px;
        background: #f5c518;
        color: #111;
        text-decoration: none;
        font-weight: 900;
        transition: 0.2s ease;
    }

    .premium-btn:hover {
        transform: translateY(-2px);
        background: #ffd83d;
    }

    /* =========================================================
       EMPTY STATE
    ========================================================= */

    .empty-section {
        padding: 35px;
        border-radius: 12px;
        background: #131319;
        color: #777;
        text-align: center;
    }

    /* =========================================================
       RESPONSIVE
    ========================================================= */

    @media (max-width: 1150px) {
        .movie-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .hero-content,
        .home-content {
            padding-left: 28px;
            padding-right: 28px;
        }
    }

    @media (max-width: 850px) {
        .hero {
            min-height: 560px;
        }

        .hero-content {
            padding-top: 80px;
            padding-bottom: 80px;
        }

        .movie-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .premium-banner {
            padding: 28px;
        }
    }

    @media (max-width: 600px) {
        .hero {
            min-height: 520px;
        }

        .hero-content,
        .home-content {
            padding-left: 16px;
            padding-right: 16px;
        }

        .hero-title {
            font-size: 42px;
            letter-spacing: -1px;
        }

        .hero-description {
            font-size: 15px;
        }

        .movie-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .movie-section {
            margin-top: 38px;
        }

        .section-title {
            font-size: 22px;
        }

        .section-subtitle {
            display: none;
        }

        .premium-banner {
            margin-top: 40px;
            padding: 24px 20px;
        }

        .premium-banner h2 {
            font-size: 25px;
        }
    }

    @media (max-width: 400px) {
        .movie-grid {
            gap: 9px;
        }

        .movie-info {
            padding: 10px;
        }

        .movie-title {
            font-size: 13px;
        }

        .movie-meta {
            font-size: 11px;
        }
    }
</style>

<div class="home-page">

    <?php if ($heroMovie): ?>

        <!-- =====================================================
             HERO
        ====================================================== -->

        <section class="hero">

            <div class="hero-content">

                <div class="hero-info">

                    <div class="hero-badges">

                        <span class="hero-badge">
                            ⭐ Featured
                        </span>

                        <?php if ((int) $heroMovie['is_premium'] === 1): ?>
                            <span class="hero-badge premium">
                                👑 Premium
                            </span>
                        <?php endif; ?>

                    </div>

                    <h1 class="hero-title">
                        <?= e($heroMovie['title']) ?>
                    </h1>

                    <?php if (!empty($heroMovie['tagline'])): ?>
                        <div style="color:#e50914;font-style:italic;font-size:16px;margin:-10px 0 16px;font-weight:600;">
                            &ldquo;<?= e($heroMovie['tagline']) ?>&rdquo;
                        </div>
                    <?php endif; ?>

                    <div class="hero-meta">

                        <span>
                            <?= (int) $heroMovie['release_year'] ?>
                        </span>

                        <span>
                            <?= (int) $heroMovie['duration'] ?> min
                        </span>

                        <span class="hero-rating">
                            ⭐ <?= number_format((float) $heroMovie['rating'], 1) ?>
                        </span>

                    </div>

                    <p class="hero-description">
                        <?= e($heroMovie['description']) ?>
                    </p>

                    <div class="hero-buttons">

                        <?php
                        $heroIsPremium = (int) $heroMovie['is_premium'] === 1;

                        $heroHasPremiumAccess =
                            $currentUser &&
                            $currentUser['subscription_status'] === 'active' &&
                            !empty($currentUser['subscription_expires_at']) &&
                            strtotime($currentUser['subscription_expires_at']) > time();
                        ?>

                        <?php if ($heroIsPremium && !$heroHasPremiumAccess): ?>

                            <a
                                href="<?= BASE_URL ?>/subscription.php"
                                class="hero-btn primary"
                            >
                                👑 Get Premium
                            </a>

                        <?php else: ?>

                            <a
                                href="<?= BASE_URL ?>/watch.php?id=<?= (int) $heroMovie['id'] ?>"
                                class="hero-btn primary"
                            >
                                ▶ Watch Now
                            </a>

                        <?php endif; ?>

                        <a
                            href="<?= BASE_URL ?>/movie.php?id=<?= (int) $heroMovie['id'] ?>"
                            class="hero-btn secondary"
                        >
                            ℹ More Info
                        </a>

                    </div>

                </div>

            </div>

        </section>

    <?php endif; ?>


    <main class="home-content">

        <!-- =====================================================
             TRENDING
        ====================================================== -->

        <?php if (!empty($featuredMovies)): ?>

            <section class="movie-section">

                <div class="section-header">

                    <div>
                        <h2 class="section-title">
                            🔥 Trending Now
                        </h2>

                        <div class="section-subtitle">
                            Popular movies right now
                        </div>
                    </div>

                    <a
                        href="<?= BASE_URL ?>/movies.php"
                        class="section-link"
                    >
                        View All →
                    </a>

                </div>

                <div class="movie-grid">

                    <?php foreach ($featuredMovies as $movie): ?>

                        <article class="movie-card">

                            <a
                                href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                                class="movie-poster"
                            >

                                <?php if ((int) $movie['is_premium'] === 1): ?>

                                    <span class="movie-badge premium">
                                        👑 Premium
                                    </span>

                                <?php endif; ?>

                                <?php if (!empty($movie['poster'])): ?>

                                    <img
                                        src="<?= e($movie['poster']) ?>"
                                        alt="<?= e($movie['title']) ?>"
                                        loading="lazy"
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

                                <div class="movie-overlay">
                                    <div class="play-button">
                                        ▶
                                    </div>
                                </div>

                            </a>

                            <div class="movie-info">

                                <h3 class="movie-title">

                                    <a
                                        href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                                    >
                                        <?= e($movie['title']) ?>
                                    </a>

                                </h3>

                                <div class="movie-meta">

                                    <span>
                                        <?= (int) $movie['release_year'] ?>
                                        ·
                                        <?= (int) $movie['duration'] ?>m
                                    </span>

                                    <span class="movie-rating">
                                        ⭐ <?= number_format((float) $movie['rating'], 1) ?>
                                    </span>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            </section>

        <?php endif; ?>


        <!-- =====================================================
             PREMIUM BANNER
        ====================================================== -->

        <section class="premium-banner">

            <div class="premium-banner-content">

                <h2>
                    👑 Unlock Premium Movies
                </h2>

                <p>
                    Get access to exclusive Premium movies,
                    watch without restrictions and enjoy the full
                    movie collection.
                </p>

                <a
                    href="<?= BASE_URL ?>/subscription.php"
                    class="premium-btn"
                >
                    Get Premium
                </a>

            </div>

        </section>


        <!-- =====================================================
             RECENTLY ADDED
        ====================================================== -->

        <?php if (!empty($latestMovies)): ?>

            <section class="movie-section">

                <div class="section-header">

                    <div>
                        <h2 class="section-title">
                            🆕 Recently Added
                        </h2>

                        <div class="section-subtitle">
                            Fresh movies added to the platform
                        </div>
                    </div>

                    <a
                        href="<?= BASE_URL ?>/movies.php"
                        class="section-link"
                    >
                        View All →
                    </a>

                </div>

                <div class="movie-grid">

                    <?php foreach ($latestMovies as $movie): ?>

                        <article class="movie-card">

                            <a
                                href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                                class="movie-poster"
                            >

                                <?php if ((int) $movie['is_premium'] === 1): ?>

                                    <span class="movie-badge premium">
                                        👑 Premium
                                    </span>

                                <?php endif; ?>

                                <?php if (!empty($movie['poster'])): ?>

                                    <img
                                        src="<?= e($movie['poster']) ?>"
                                        alt="<?= e($movie['title']) ?>"
                                        loading="lazy"
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

                                <div class="movie-overlay">
                                    <div class="play-button">
                                        ▶
                                    </div>
                                </div>

                            </a>

                            <div class="movie-info">

                                <h3 class="movie-title">

                                    <a
                                        href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                                    >
                                        <?= e($movie['title']) ?>
                                    </a>

                                </h3>

                                <div class="movie-meta">

                                    <span>
                                        <?= (int) $movie['release_year'] ?>
                                        ·
                                        <?= (int) $movie['duration'] ?>m
                                    </span>

                                    <span class="movie-rating">
                                        ⭐ <?= number_format((float) $movie['rating'], 1) ?>
                                    </span>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            </section>

        <?php endif; ?>


        <!-- =====================================================
             PREMIUM COLLECTION
        ====================================================== -->

        <?php if (!empty($premiumMovies)): ?>

            <section class="movie-section">

                <div class="section-header">

                    <div>
                        <h2 class="section-title">
                            👑 Premium Collection
                        </h2>

                        <div class="section-subtitle">
                            Exclusive movies for Premium members
                        </div>
                    </div>

                    <a
                        href="<?= BASE_URL ?>/subscription.php"
                        class="section-link"
                    >
                        Get Premium →
                    </a>

                </div>

                <div class="movie-grid">

                    <?php foreach ($premiumMovies as $movie): ?>

                        <article class="movie-card">

                            <a
                                href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                                class="movie-poster"
                            >

                                <span class="movie-badge premium">
                                    👑 Premium
                                </span>

                                <?php if (!empty($movie['poster'])): ?>

                                    <img
                                        src="<?= e($movie['poster']) ?>"
                                        alt="<?= e($movie['title']) ?>"
                                        loading="lazy"
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

                                <div class="movie-overlay">
                                    <div class="play-button">
                                        ▶
                                    </div>
                                </div>

                            </a>

                            <div class="movie-info">

                                <h3 class="movie-title">

                                    <a
                                        href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                                    >
                                        <?= e($movie['title']) ?>
                                    </a>

                                </h3>

                                <div class="movie-meta">

                                    <span>
                                        <?= (int) $movie['release_year'] ?>
                                        ·
                                        <?= (int) $movie['duration'] ?>m
                                    </span>

                                    <span class="movie-rating">
                                        ⭐ <?= number_format((float) $movie['rating'], 1) ?>
                                    </span>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            </section>

        <?php endif; ?>


        <!-- =====================================================
             MORE MOVIES
        ====================================================== -->

        <?php if (!empty($moreMovies)): ?>

            <section class="movie-section">

                <div class="section-header">

                    <div>
                        <h2 class="section-title">
                            🎬 More Movies
                        </h2>

                        <div class="section-subtitle">
                            More movies to discover
                        </div>
                    </div>

                    <a
                        href="<?= BASE_URL ?>/movies.php"
                        class="section-link"
                    >
                        Browse All →
                    </a>

                </div>

                <div class="movie-grid">

                    <?php foreach ($moreMovies as $movie): ?>

                        <article class="movie-card">

                            <a
                                href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                                class="movie-poster"
                            >

                                <?php if ((int) $movie['is_premium'] === 1): ?>

                                    <span class="movie-badge premium">
                                        👑 Premium
                                    </span>

                                <?php endif; ?>

                                <?php if (!empty($movie['poster'])): ?>

                                    <img
                                        src="<?= e($movie['poster']) ?>"
                                        alt="<?= e($movie['title']) ?>"
                                        loading="lazy"
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

                                <div class="movie-overlay">
                                    <div class="play-button">
                                        ▶
                                    </div>
                                </div>

                            </a>

                            <div class="movie-info">

                                <h3 class="movie-title">

                                    <a
                                        href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                                    >
                                        <?= e($movie['title']) ?>
                                    </a>

                                </h3>

                                <div class="movie-meta">

                                    <span>
                                        <?= (int) $movie['release_year'] ?>
                                        ·
                                        <?= (int) $movie['duration'] ?>m
                                    </span>

                                    <span class="movie-rating">
                                        ⭐ <?= number_format((float) $movie['rating'], 1) ?>
                                    </span>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            </section>

        <?php endif; ?>

    </main>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>