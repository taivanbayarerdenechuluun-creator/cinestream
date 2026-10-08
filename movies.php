<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Movies';

$db = getDB();

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET['search'] ?? ''));
$genreId = (int) ($_GET['genre'] ?? 0);
$year = (int) ($_GET['year'] ?? 0);

$premium = $_GET['premium'] ?? 'all';
$sort = $_GET['sort'] ?? 'latest';

$page = max(1, (int) ($_GET['page'] ?? 1));

$perPage = 12;


/*
|--------------------------------------------------------------------------
| Validate filters
|--------------------------------------------------------------------------
*/

$allowedPremium = ['all', 'premium', 'free'];

if (!in_array($premium, $allowedPremium, true)) {
    $premium = 'all';
}

$allowedSorts = [
    'latest',
    'rating',
    'title',
    'oldest'
];

if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'latest';
}


/*
|--------------------------------------------------------------------------
| Get Genres
|--------------------------------------------------------------------------
*/

$genreStmt = $db->query(
    'SELECT
        id,
        name
     FROM genres
     ORDER BY name ASC'
);

$genres = $genreStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Get Years
|--------------------------------------------------------------------------
*/

$yearStmt = $db->query(
    'SELECT DISTINCT
        release_year
     FROM movies
     ORDER BY release_year DESC'
);

$years = $yearStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Build WHERE
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] = '
        (
            m.title LIKE :search_title
            OR m.description LIKE :search_description
            OR m.director LIKE :search_director
            OR m.cast_data LIKE :search_cast
        )
    ';

    $params[':search_title'] = '%' . $search . '%';
    $params[':search_description'] = '%' . $search . '%';
    $params[':search_director'] = '%' . $search . '%';
    $params[':search_cast'] = '%' . $search . '%';
}


/*
|--------------------------------------------------------------------------
| Genre
|--------------------------------------------------------------------------
*/

if ($genreId > 0) {

    $where[] = 'mg.genre_id = :genre_id';

    $params[':genre_id'] = $genreId;
}


/*
|--------------------------------------------------------------------------
| Year
|--------------------------------------------------------------------------
*/

if ($year > 0) {

    $where[] = 'm.release_year = :release_year';

    $params[':release_year'] = $year;
}


/*
|--------------------------------------------------------------------------
| Premium / Free
|--------------------------------------------------------------------------
*/

if ($premium === 'premium') {

    $where[] = 'm.is_premium = 1';

}

if ($premium === 'free') {

    $where[] = 'm.is_premium = 0';

}


/*
|--------------------------------------------------------------------------
| Build WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSql = '';

if (!empty($where)) {

    $whereSql = ' WHERE ' . implode(' AND ', $where);

}


/*
|--------------------------------------------------------------------------
| Genre JOIN
|--------------------------------------------------------------------------
*/

$genreJoin = '';

if ($genreId > 0) {

    $genreJoin = '
        INNER JOIN movie_genres mg
            ON mg.movie_id = m.id
    ';

}


/*
|--------------------------------------------------------------------------
| Count Total Movies
|--------------------------------------------------------------------------
*/

$countSql = '
    SELECT COUNT(DISTINCT m.id)
    FROM movies m
    ' . $genreJoin . '
    ' . $whereSql;

$countStmt = $db->prepare($countSql);
$countStmt->execute($params);

$totalMovies = (int) $countStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$totalPages = max(
    1,
    (int) ceil($totalMovies / $perPage)
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;


/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

$orderSql = match ($sort) {

    'rating' =>
        'm.rating DESC, m.title ASC',

    'title' =>
        'm.title ASC',

    'oldest' =>
        'm.release_year ASC, m.title ASC',

    default =>
        'm.created_at DESC, m.id DESC'
};


/*
|--------------------------------------------------------------------------
| Get Movies
|--------------------------------------------------------------------------
|
| LIMIT / OFFSET are safely converted to integers and inserted
| into SQL instead of using named placeholders.
|--------------------------------------------------------------------------
*/

$sql = '
    SELECT DISTINCT
        m.id,
        m.title,
        m.description,
        m.release_year,
        m.duration,
        m.poster,
        m.rating,
        m.is_premium,
        m.created_at
    FROM movies m
    ' . $genreJoin . '
    ' . $whereSql . '
    ORDER BY ' . $orderSql . '
    LIMIT ' . (int) $perPage . '
    OFFSET ' . (int) $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);

$movies = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Build pagination URL
|--------------------------------------------------------------------------
*/

function moviesPageUrl(
    int $pageNumber,
    string $search,
    int $genreId,
    int $year,
    string $premium,
    string $sort
): string {

    $query = [
        'page' => $pageNumber
    ];

    if ($search !== '') {
        $query['search'] = $search;
    }

    if ($genreId > 0) {
        $query['genre'] = $genreId;
    }

    if ($year > 0) {
        $query['year'] = $year;
    }

    if ($premium !== 'all') {
        $query['premium'] = $premium;
    }

    if ($sort !== 'latest') {
        $query['sort'] = $sort;
    }

    return BASE_URL . '/movies.php?' . http_build_query($query);
}


require_once __DIR__ . '/includes/header.php';
?>

<style>

    /* =========================================================
       MOVIES PAGE
    ========================================================= */

    .movies-page {
        min-height: 100vh;
        background: #0b0b0f;
        color: #fff;
        padding: 40px 0 80px;
    }

    .movies-container {
        width: min(1400px, calc(100% - 80px));
        margin: 0 auto;
    }


    /* =========================================================
       PAGE HEADER
    ========================================================= */

    .movies-header {
        margin-bottom: 30px;
    }

    .movies-title {
        margin: 0;
        font-size: clamp(32px, 4vw, 48px);
        font-weight: 900;
        letter-spacing: -1px;
    }

    .movies-subtitle {
        margin: 10px 0 0;
        color: #8d8d96;
        font-size: 15px;
    }


    /* =========================================================
       FILTER BOX
    ========================================================= */

    .movies-filters {
        padding: 20px;
        margin-bottom: 28px;
        border-radius: 14px;
        background: #141419;
        border: 1px solid rgba(255,255,255,0.06);
    }

    .filter-row {
        display: grid;
        grid-template-columns:
            minmax(220px, 2fr)
            repeat(4, minmax(130px, 1fr))
            auto
            auto;

        gap: 12px;
        align-items: center;
    }

    .search-box {
        position: relative;
    }

    .search-box input,
    .filter-select {
        width: 100%;
        height: 46px;
        padding: 0 14px;
        border-radius: 8px;
        border: 1px solid #2a2a32;
        background: #0f0f14;
        color: #fff;
        outline: none;
        font-size: 14px;
        box-sizing: border-box;
    }

    .search-box input:focus,
    .filter-select:focus {
        border-color: #555;
    }

    .filter-select {
        cursor: pointer;
    }

    .filter-btn {
        height: 46px;
        padding: 0 18px;
        border: none;
        border-radius: 8px;
        background: #fff;
        color: #111;
        font-weight: 800;
        cursor: pointer;
        transition: 0.2s ease;
        white-space: nowrap;
    }

    .filter-btn:hover {
        background: #e8e8e8;
        transform: translateY(-1px);
    }

    .clear-btn {
        height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 18px;
        border-radius: 8px;
        border: 1px solid #2d2d35;
        color: #ccc;
        text-decoration: none;
        font-size: 14px;
        font-weight: 700;
        white-space: nowrap;
        transition: 0.2s ease;
    }

    .clear-btn:hover {
        background: #222229;
        color: #fff;
    }


    /* =========================================================
       ACTIVE FILTERS
    ========================================================= */

    .active-filters {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-top: 15px;
    }

    .filter-label {
        color: #777;
        font-size: 12px;
    }

    .filter-tag {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 10px;
        border-radius: 999px;
        background: #222229;
        color: #ddd;
        font-size: 12px;
        font-weight: 700;
    }


    /* =========================================================
       RESULT BAR
    ========================================================= */

    .result-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        margin-bottom: 20px;
    }

    .result-count {
        color: #85858e;
        font-size: 14px;
    }

    .result-count strong {
        color: #fff;
    }


    /* =========================================================
       MOVIE GRID
    ========================================================= */

    .movies-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 18px;
    }

    .movie-card {
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
        box-shadow: 0 18px 40px rgba(0,0,0,0.45);
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
        background: rgba(0,0,0,0.5);
        opacity: 0;
        transition: opacity 0.25s ease;
    }

    .movie-card:hover .movie-overlay {
        opacity: 1;
    }

    .play-button {
        width: 52px;
        height: 52px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #fff;
        color: #111;
        font-size: 20px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.4);
    }


    /* =========================================================
       BADGES
    ========================================================= */

    .movie-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        z-index: 3;
        padding: 5px 9px;
        border-radius: 6px;
        background: rgba(0,0,0,0.78);
        color: #fff;
        font-size: 10px;
        font-weight: 900;
    }

    .movie-badge.premium {
        background: #f5c518;
        color: #111;
    }


    /* =========================================================
       MOVIE INFO
    ========================================================= */

    .movie-info {
        padding: 13px;
    }

    .movie-title {
        display: block;
        margin-bottom: 8px;
        color: #fff;
        text-decoration: none;
        font-size: 14px;
        font-weight: 800;
        line-height: 1.3;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .movie-title:hover {
        color: #f5c518;
    }

    .movie-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 5px;
        color: #85858d;
        font-size: 11px;
    }

    .movie-rating {
        color: #f5c518;
        font-weight: 800;
    }


    /* =========================================================
       NO RESULTS
    ========================================================= */

    .no-results {
        padding: 90px 20px;
        text-align: center;
        border-radius: 14px;
        background: #131319;
    }

    .no-results-icon {
        font-size: 60px;
        margin-bottom: 15px;
    }

    .no-results h2 {
        margin: 0 0 10px;
        font-size: 25px;
    }

    .no-results p {
        margin: 0 0 25px;
        color: #85858d;
    }

    .view-all-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        padding: 0 20px;
        border-radius: 8px;
        background: #fff;
        color: #111;
        text-decoration: none;
        font-weight: 800;
    }


    /* =========================================================
       PAGINATION
    ========================================================= */

    .pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        flex-wrap: wrap;
        margin-top: 45px;
    }

    .page-link {
        min-width: 40px;
        height: 40px;
        padding: 0 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: 1px solid #2a2a32;
        background: #141419;
        color: #bbb;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
        box-sizing: border-box;
    }

    .page-link:hover {
        background: #24242c;
        color: #fff;
    }

    .page-link.active {
        background: #fff;
        color: #111;
        border-color: #fff;
    }

    .page-link.disabled {
        opacity: 0.35;
        pointer-events: none;
    }

    .page-dots {
        color: #666;
        padding: 0 3px;
    }


    /* =========================================================
       RESPONSIVE
    ========================================================= */

    @media (max-width: 1250px) {

        .movies-grid {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }

        .filter-row {
            grid-template-columns:
                2fr
                repeat(3, 1fr);
        }

    }

    @media (max-width: 1000px) {

        .movies-container {
            width: min(100% - 40px, 900px);
        }

        .movies-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .filter-row {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .search-box {
            grid-column: 1 / -1;
        }

    }

    @media (max-width: 700px) {

        .movies-page {
            padding-top: 25px;
        }

        .movies-container {
            width: calc(100% - 28px);
        }

        .movies-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .filter-row {
            grid-template-columns: 1fr;
        }

        .search-box {
            grid-column: auto;
        }

        .filter-btn,
        .clear-btn {
            width: 100%;
        }

        .result-bar {
            align-items: flex-start;
            flex-direction: column;
        }

    }

    @media (max-width: 500px) {

        .movies-title {
            font-size: 32px;
        }

        .movies-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .movie-info {
            padding: 10px;
        }

        .movie-title {
            font-size: 13px;
        }

        .movie-meta {
            font-size: 10px;
        }

        .movie-badge {
            top: 7px;
            left: 7px;
            padding: 4px 7px;
            font-size: 9px;
        }

    }

</style>


<div class="movies-page">

    <div class="movies-container">


        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->

        <header class="movies-header">

            <h1 class="movies-title">
                🎬 Browse Movies
            </h1>

            <p class="movies-subtitle">
                Discover your next favorite movie.
            </p>

        </header>


        <!-- =====================================================
             FILTERS
        ====================================================== -->

        <form
            method="GET"
            action="<?= BASE_URL ?>/movies.php"
            class="movies-filters"
        >

            <div class="filter-row">


                <!-- Search -->

                <div class="search-box">

                    <input
                        type="text"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="🔎 Search movies..."
                    >

                </div>


                <!-- Genre -->

                <select
                    name="genre"
                    class="filter-select"
                >

                    <option value="0">
                        All Genres
                    </option>

                    <?php foreach ($genres as $genre): ?>

                        <option
                            value="<?= (int) $genre['id'] ?>"
                            <?= $genreId === (int) $genre['id'] ? 'selected' : '' ?>
                        >
                            <?= e($genre['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>


                <!-- Year -->

                <select
                    name="year"
                    class="filter-select"
                >

                    <option value="0">
                        All Years
                    </option>

                    <?php foreach ($years as $yearItem): ?>

                        <option
                            value="<?= (int) $yearItem['release_year'] ?>"
                            <?= $year === (int) $yearItem['release_year'] ? 'selected' : '' ?>
                        >
                            <?= (int) $yearItem['release_year'] ?>
                        </option>

                    <?php endforeach; ?>

                </select>


                <!-- Premium -->

                <select
                    name="premium"
                    class="filter-select"
                >

                    <option
                        value="all"
                        <?= $premium === 'all' ? 'selected' : '' ?>
                    >
                        All Movies
                    </option>

                    <option
                        value="premium"
                        <?= $premium === 'premium' ? 'selected' : '' ?>
                    >
                        👑 Premium
                    </option>

                    <option
                        value="free"
                        <?= $premium === 'free' ? 'selected' : '' ?>
                    >
                        Free
                    </option>

                </select>


                <!-- Sort -->

                <select
                    name="sort"
                    class="filter-select"
                >

                    <option
                        value="latest"
                        <?= $sort === 'latest' ? 'selected' : '' ?>
                    >
                        Latest
                    </option>

                    <option
                        value="rating"
                        <?= $sort === 'rating' ? 'selected' : '' ?>
                    >
                        ⭐ Highest Rated
                    </option>

                    <option
                        value="title"
                        <?= $sort === 'title' ? 'selected' : '' ?>
                    >
                        A-Z
                    </option>

                    <option
                        value="oldest"
                        <?= $sort === 'oldest' ? 'selected' : '' ?>
                    >
                        Oldest
                    </option>

                </select>


                <!-- Search button -->

                <button
                    type="submit"
                    class="filter-btn"
                >
                    Search
                </button>


                <!-- Clear -->

                <a
                    href="<?= BASE_URL ?>/movies.php"
                    class="clear-btn"
                >
                    Clear
                </a>

            </div>


            <!-- Active filters -->

            <?php if (
                $search !== '' ||
                $genreId > 0 ||
                $year > 0 ||
                $premium !== 'all' ||
                $sort !== 'latest'
            ): ?>

                <div class="active-filters">

                    <span class="filter-label">
                        Active:
                    </span>

                    <?php if ($search !== ''): ?>

                        <span class="filter-tag">
                            🔎 <?= e($search) ?>
                        </span>

                    <?php endif; ?>


                    <?php if ($genreId > 0): ?>

                        <?php
                        $selectedGenreName = 'Genre';

                        foreach ($genres as $genre) {
                            if ((int) $genre['id'] === $genreId) {
                                $selectedGenreName = $genre['name'];
                                break;
                            }
                        }
                        ?>

                        <span class="filter-tag">
                            🎭 <?= e($selectedGenreName) ?>
                        </span>

                    <?php endif; ?>


                    <?php if ($year > 0): ?>

                        <span class="filter-tag">
                            📅 <?= $year ?>
                        </span>

                    <?php endif; ?>


                    <?php if ($premium === 'premium'): ?>

                        <span class="filter-tag">
                            👑 Premium
                        </span>

                    <?php elseif ($premium === 'free'): ?>

                        <span class="filter-tag">
                            Free
                        </span>

                    <?php endif; ?>


                    <?php if ($sort !== 'latest'): ?>

                        <span class="filter-tag">

                            <?php if ($sort === 'rating'): ?>
                                ⭐ Highest Rated
                            <?php elseif ($sort === 'title'): ?>
                                A-Z
                            <?php elseif ($sort === 'oldest'): ?>
                                Oldest
                            <?php endif; ?>

                        </span>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </form>


        <!-- =====================================================
             RESULT BAR
        ====================================================== -->

        <div class="result-bar">

            <div class="result-count">

                <strong>
                    <?= $totalMovies ?>
                </strong>

                movie<?= $totalMovies !== 1 ? 's' : '' ?> found

            </div>

            <?php if ($totalPages > 1): ?>

                <div class="result-count">

                    Page
                    <strong><?= $page ?></strong>
                    of
                    <strong><?= $totalPages ?></strong>

                </div>

            <?php endif; ?>

        </div>


        <!-- =====================================================
             MOVIES
        ====================================================== -->

        <?php if (!empty($movies)): ?>

            <div class="movies-grid">

                <?php foreach ($movies as $movie): ?>

                    <article class="movie-card">

                        <a
                            href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                            style="text-decoration:none;"
                        >

                            <div class="movie-poster">


                                <!-- Premium Badge -->

                                <?php if ((int) $movie['is_premium'] === 1): ?>

                                    <div class="movie-badge premium">
                                        👑 Premium
                                    </div>

                                <?php endif; ?>


                                <!-- Poster -->

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
                                            background:#202027;
                                        "
                                    >
                                        No Poster
                                    </div>

                                <?php endif; ?>


                                <!-- Hover Overlay -->

                                <div class="movie-overlay">

                                    <div class="play-button">
                                        ▶
                                    </div>

                                </div>

                            </div>


                            <!-- Movie info -->

                            <div class="movie-info">

                                <div class="movie-title">

                                    <?= e($movie['title']) ?>

                                </div>


                                <div class="movie-meta">

                                    <span class="movie-rating">
                                        ⭐ <?= number_format(
                                            (float) $movie['rating'],
                                            1
                                        ) ?>
                                    </span>

                                    <span>
                                        <?= (int) $movie['release_year'] ?>
                                    </span>

                                    <span>
                                        <?= (int) $movie['duration'] ?>m
                                    </span>

                                </div>

                            </div>

                        </a>

                    </article>

                <?php endforeach; ?>

            </div>


            <!-- =================================================
                 PAGINATION
            ================================================== -->

            <?php if ($totalPages > 1): ?>

                <nav class="pagination" aria-label="Movie pagination">


                    <!-- Previous -->

                    <?php if ($page > 1): ?>

                        <a
                            href="<?= e(
                                moviesPageUrl(
                                    $page - 1,
                                    $search,
                                    $genreId,
                                    $year,
                                    $premium,
                                    $sort
                                )
                            ) ?>"
                            class="page-link"
                        >
                            ←
                        </a>

                    <?php else: ?>

                        <span class="page-link disabled">
                            ←
                        </span>

                    <?php endif; ?>


                    <?php

                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);

                    ?>


                    <!-- First page -->

                    <?php if ($startPage > 1): ?>

                        <a
                            href="<?= e(
                                moviesPageUrl(
                                    1,
                                    $search,
                                    $genreId,
                                    $year,
                                    $premium,
                                    $sort
                                )
                            ) ?>"
                            class="page-link"
                        >
                            1
                        </a>

                        <?php if ($startPage > 2): ?>

                            <span class="page-dots">
                                ...
                            </span>

                        <?php endif; ?>

                    <?php endif; ?>


                    <!-- Page numbers -->

                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>

                        <a
                            href="<?= e(
                                moviesPageUrl(
                                    $i,
                                    $search,
                                    $genreId,
                                    $year,
                                    $premium,
                                    $sort
                                )
                            ) ?>"
                            class="page-link <?= $i === $page ? 'active' : '' ?>"
                        >
                            <?= $i ?>
                        </a>

                    <?php endfor; ?>


                    <!-- Last page -->

                    <?php if ($endPage < $totalPages): ?>

                        <?php if ($endPage < $totalPages - 1): ?>

                            <span class="page-dots">
                                ...
                            </span>

                        <?php endif; ?>

                        <a
                            href="<?= e(
                                moviesPageUrl(
                                    $totalPages,
                                    $search,
                                    $genreId,
                                    $year,
                                    $premium,
                                    $sort
                                )
                            ) ?>"
                            class="page-link"
                        >
                            <?= $totalPages ?>
                        </a>

                    <?php endif; ?>


                    <!-- Next -->

                    <?php if ($page < $totalPages): ?>

                        <a
                            href="<?= e(
                                moviesPageUrl(
                                    $page + 1,
                                    $search,
                                    $genreId,
                                    $year,
                                    $premium,
                                    $sort
                                )
                            ) ?>"
                            class="page-link"
                        >
                            →
                        </a>

                    <?php else: ?>

                        <span class="page-link disabled">
                            →
                        </span>

                    <?php endif; ?>

                </nav>

            <?php endif; ?>


        <?php else: ?>


            <!-- =================================================
                 NO RESULTS
            ================================================== -->

            <div class="no-results">

                <div class="no-results-icon">
                    🎬
                </div>

                <h2>
                    No movies found
                </h2>

                <p>
                    Try changing your search or filters.
                </p>

                <a
                    href="<?= BASE_URL ?>/movies.php"
                    class="view-all-btn"
                >
                    View All Movies
                </a>

            </div>

        <?php endif; ?>


    </div>

</div>


<?php
require_once __DIR__ . '/includes/footer.php';
?>