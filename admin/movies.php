<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tmdb.php';

requireAdmin();

$db = getDB();

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| TMDB SEARCH & 1-CLICK IMPORT
|--------------------------------------------------------------------------
*/
$tmdbSearchQuery = trim((string) ($_GET['tmdb_search'] ?? ''));
$tmdbSearchResults = [];
if ($tmdbSearchQuery !== '') {
    $searchResponse = tmdbSearchMovies($tmdbSearchQuery);
    if (!empty($searchResponse['results'])) {
        $tmdbSearchResults = array_slice($searchResponse['results'], 0, 8);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_tmdb'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $importTmdbId = (int) ($_POST['tmdb_id'] ?? 0);
        $importIsPremium = isset($_POST['is_premium']) ? 1 : 0;

        if ($importTmdbId <= 0) {
            $error = 'Please provide a valid TMDB Movie ID.';
        } else {
            try {
                $rawMovie = tmdbGetMovie($importTmdbId);
                if (!$rawMovie) {
                    $error = "Could not fetch details from TMDB for ID {$importTmdbId}. Please check TMDB ID.";
                } else {
                    $parsed = tmdbParseMovieData($rawMovie);
                    $savedId = tmdbSaveMovieToDb($db, $parsed, null, (bool) $importIsPremium);
                    $message = "Successfully imported '{$parsed['title']}' from TMDB! (ID: #{$savedId}, Cast: " . count($parsed['cast']) . ", Revenue: $" . number_format($parsed['revenue']) . ")";
                }
            } catch (Throwable $e) {
                error_log('TMDB Import error: ' . $e->getMessage());
                $error = 'Failed to import from TMDB: ' . $e->getMessage();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| DELETE MOVIE
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_movie'])) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token.';
    } else {

        $movieId = (int) ($_POST['movie_id'] ?? 0);

        if ($movieId <= 0) {
            $error = 'Invalid movie ID.';
        } else {
            try {
                $db->beginTransaction();

                // Delete related data first
                $tables = [
                    'watchlist',
                    'ratings',
                    'comments',
                    'watch_history'
                ];

                foreach ($tables as $table) {
                    $stmt = $db->prepare(
                        "DELETE FROM {$table} WHERE movie_id = :movie_id"
                    );

                    $stmt->execute([
                        ':movie_id' => $movieId
                    ]);
                }

                // Delete movie
                $stmt = $db->prepare(
                    'DELETE FROM movies WHERE id = :id'
                );

                $stmt->execute([
                    ':id' => $movieId
                ]);

                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('Movie not found.');
                }

                $db->commit();

                $message = 'Movie deleted successfully.';

            } catch (Throwable $e) {

                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                error_log('Admin movie delete error: ' . $e->getMessage());

                $error = 'Failed to delete movie.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| ADD / UPDATE MOVIE
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_movie'])) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        $error = 'Invalid security token.';

    } else {

        $movieId = (int) ($_POST['movie_id'] ?? 0);

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tagline = trim($_POST['tagline'] ?? '');
        $releaseYear = (int) ($_POST['release_year'] ?? 0);
        $releaseDate = !empty($_POST['release_date']) ? trim($_POST['release_date']) : null;
        $duration = (int) ($_POST['duration'] ?? 0);
        $budget = (int) ($_POST['budget'] ?? 0);
        $revenue = (int) ($_POST['revenue'] ?? 0);
        $poster = trim($_POST['poster'] ?? '');
        $backdrop = trim($_POST['backdrop'] ?? '');
        $director = trim($_POST['director'] ?? '');
        $trailerUrl = trim($_POST['trailer_url'] ?? '');
        $trailerKey = trim($_POST['trailer_key'] ?? '');
        $videoUrl = trim($_POST['video_url'] ?? '');
        $rating = (float) ($_POST['rating'] ?? 0);
        $tmdbId = !empty($_POST['tmdb_id']) ? (int) $_POST['tmdb_id'] : null;
        $isPremium = isset($_POST['is_premium']) ? 1 : 0;

        if ($title === '') {
            $error = 'Movie title is required.';
        } elseif ($description === '') {
            $error = 'Description is required.';
        } elseif ($releaseYear < 1900 || $releaseYear > 2100) {
            $error = 'Invalid release year.';
        } elseif ($duration <= 0) {
            $error = 'Duration must be greater than 0.';
        } elseif ($videoUrl === '') {
            $error = 'Video URL is required.';
        } elseif ($rating < 0 || $rating > 10) {
            $error = 'Rating must be between 0 and 10.';
        } else {

            try {

                if ($movieId > 0) {

                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $db->prepare(
                        'UPDATE movies
                         SET
                            tmdb_id = :tmdb_id,
                            title = :title,
                            description = :description,
                            tagline = :tagline,
                            release_year = :release_year,
                            release_date = :release_date,
                            duration = :duration,
                            budget = :budget,
                            revenue = :revenue,
                            poster = :poster,
                            backdrop = :backdrop,
                            director = :director,
                            trailer_url = :trailer_url,
                            trailer_key = :trailer_key,
                            video_url = :video_url,
                            rating = :rating,
                            is_premium = :is_premium
                         WHERE id = :id'
                    );

                    $stmt->execute([
                        ':tmdb_id' => $tmdbId,
                        ':title' => $title,
                        ':description' => $description,
                        ':tagline' => $tagline !== '' ? $tagline : null,
                        ':release_year' => $releaseYear,
                        ':release_date' => $releaseDate,
                        ':duration' => $duration,
                        ':budget' => $budget,
                        ':revenue' => $revenue,
                        ':poster' => $poster !== '' ? $poster : null,
                        ':backdrop' => $backdrop !== '' ? $backdrop : null,
                        ':director' => $director !== '' ? $director : null,
                        ':trailer_url' => $trailerUrl !== '' ? $trailerUrl : null,
                        ':trailer_key' => $trailerKey !== '' ? $trailerKey : null,
                        ':video_url' => $videoUrl,
                        ':rating' => $rating,
                        ':is_premium' => $isPremium,
                        ':id' => $movieId
                    ]);

                    $message = 'Movie updated successfully.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | INSERT
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $db->prepare(
                        'INSERT INTO movies
                        (
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
                            trailer_url,
                            trailer_key,
                            video_url,
                            rating,
                            is_premium
                        )
                        VALUES
                        (
                            :tmdb_id,
                            :title,
                            :description,
                            :tagline,
                            :release_year,
                            :release_date,
                            :duration,
                            :budget,
                            :revenue,
                            :poster,
                            :backdrop,
                            :director,
                            :trailer_url,
                            :trailer_key,
                            :video_url,
                            :rating,
                            :is_premium
                        )'
                    );

                    $stmt->execute([
                        ':tmdb_id' => $tmdbId,
                        ':title' => $title,
                        ':description' => $description,
                        ':tagline' => $tagline !== '' ? $tagline : null,
                        ':release_year' => $releaseYear,
                        ':release_date' => $releaseDate,
                        ':duration' => $duration,
                        ':budget' => $budget,
                        ':revenue' => $revenue,
                        ':poster' => $poster !== '' ? $poster : null,
                        ':backdrop' => $backdrop !== '' ? $backdrop : null,
                        ':director' => $director !== '' ? $director : null,
                        ':trailer_url' => $trailerUrl !== '' ? $trailerUrl : null,
                        ':trailer_key' => $trailerKey !== '' ? $trailerKey : null,
                        ':video_url' => $videoUrl,
                        ':rating' => $rating,
                        ':is_premium' => $isPremium
                    ]);

                    $message = 'Movie added successfully.';
                }

            } catch (Throwable $e) {

                error_log('Admin movie save error: ' . $e->getMessage());

                $error = 'Failed to save movie: ' . $e->getMessage();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| EDIT MOVIE
|--------------------------------------------------------------------------
*/

$editMovie = null;

$editId = (int) ($_GET['edit'] ?? 0);

if ($editId > 0) {

    $stmt = $db->prepare(
        'SELECT *
         FROM movies
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $editId
    ]);

    $editMovie = $stmt->fetch();

    if (!$editMovie) {
        $error = 'Movie not found.';
    }
}


/*
|--------------------------------------------------------------------------
| SEARCH / FILTER
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$premiumFilter = $_GET['premium'] ?? 'all';

if (!in_array($premiumFilter, ['all', 'free', 'premium'], true)) {
    $premiumFilter = 'all';
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$perPage = 10;

$page = max(1, (int) ($_GET['page'] ?? 1));

$where = [];
$params = [];


/*
|--------------------------------------------------------------------------
| SEARCH CONDITION
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] = '(title LIKE :search_title OR description LIKE :search_description)';

    $params[':search_title'] = '%' . $search . '%';
    $params[':search_description'] = '%' . $search . '%';
}


/*
|--------------------------------------------------------------------------
| PREMIUM FILTER
|--------------------------------------------------------------------------
*/

if ($premiumFilter === 'premium') {

    $where[] = 'is_premium = 1';

} elseif ($premiumFilter === 'free') {

    $where[] = 'is_premium = 0';
}


$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}


/*
|--------------------------------------------------------------------------
| COUNT MOVIES
|--------------------------------------------------------------------------
*/

$countStmt = $db->prepare(
    "SELECT COUNT(*)
     FROM movies
     {$whereSql}"
);

$countStmt->execute($params);

$totalMovies = (int) $countStmt->fetchColumn();

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
| GET MOVIES
|--------------------------------------------------------------------------
*/

$limit = (int) $perPage;
$offsetValue = (int) $offset;

$sql = "SELECT
            id,
            tmdb_id,
            title,
            release_year,
            release_date,
            duration,
            poster,
            director,
            budget,
            revenue,
            rating,
            is_premium,
            created_at
        FROM movies
        {$whereSql}
        ORDER BY id DESC
        LIMIT {$limit} OFFSET {$offsetValue}";

$stmt = $db->prepare($sql);

$stmt->execute($params);

$movies = $stmt->fetchAll();


require_once __DIR__ . '/../includes/header.php';
?>

<style>
.admin-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.admin-header h1 {
    margin: 0;
}

.admin-nav {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.admin-nav a {
    padding: 10px 16px;
    border-radius: 8px;
    text-decoration: none;
    background: #222;
    color: #fff;
}

.admin-nav a:hover {
    background: #333;
}

.alert {
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
}

.movie-form {
    background: #151515;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 30px;
}

.movie-form h2 {
    margin-top: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group input,
.form-group textarea,
.form-group select {
    padding: 11px;
    border: 1px solid #444;
    border-radius: 7px;
    background: #222;
    color: #fff;
}

.form-group textarea {
    min-height: 110px;
    resize: vertical;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    text-decoration: none;
}

.btn-primary {
    background: #e50914;
    color: #fff;
}

.btn-secondary {
    background: #555;
    color: #fff;
}

.btn-danger {
    background: #dc2626;
    color: #fff;
}

.btn:hover {
    opacity: .85;
}

.form-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

.filter-box {
    background: #151515;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.filter-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-form input,
.filter-form select {
    padding: 10px;
    border-radius: 7px;
    border: 1px solid #444;
    background: #222;
    color: #fff;
}

.movie-table-wrapper {
    overflow-x: auto;
}

.movie-table {
    width: 100%;
    border-collapse: collapse;
    background: #151515;
    border-radius: 12px;
    overflow: hidden;
}

.movie-table th,
.movie-table td {
    padding: 13px;
    border-bottom: 1px solid #333;
    text-align: left;
}

.movie-table th {
    background: #222;
}

.movie-poster {
    width: 55px;
    height: 75px;
    object-fit: cover;
    border-radius: 5px;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.badge-premium {
    background: #f59e0b;
    color: #111;
}

.badge-free {
    background: #374151;
    color: #fff;
}

.actions {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 7px;
    margin-top: 25px;
    flex-wrap: wrap;
}

.pagination a,
.pagination span {
    padding: 9px 13px;
    border-radius: 7px;
    background: #222;
    color: #fff;
    text-decoration: none;
}

.pagination .active {
    background: #e50914;
}

.empty {
    padding: 30px;
    text-align: center;
    background: #151515;
    border-radius: 12px;
}

@media (max-width: 700px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }

    .admin-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .movie-table th:nth-child(4),
    .movie-table td:nth-child(4) {
        display: none;
    }
}
</style>

<div class="admin-container">

    <div class="admin-header">

        <div>
            <h1>🎬 Movie Management</h1>

            <p>
                Manage your movies, Premium content and movie information.
            </p>
        </div>

        <a
            href="<?= BASE_URL ?>/admin/index.php"
            class="btn btn-secondary"
        >
            ← Dashboard
        </a>

    </div>


    <!-- ADMIN NAVIGATION -->

    <div class="admin-nav">

        <a href="<?= BASE_URL ?>/admin/index.php">
            📊 Dashboard
        </a>

        <a href="<?= BASE_URL ?>/admin/movies.php">
            🎬 Movies
        </a>

        <a href="<?= BASE_URL ?>/admin/users.php">
            👥 Users
        </a>

        <a href="<?= BASE_URL ?>/admin/payments.php">
            💳 Payments
        </a>

    </div>


    <!-- MESSAGES -->

    <?php if ($message): ?>

        <div class="alert alert-success">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <!-- =====================================================
         TMDB 1-CLICK IMPORT & SEARCH
    ====================================================== -->
    <div style="background:#151515;border:1px solid #292929;border-radius:14px;padding:22px;margin-bottom:30px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px;margin-bottom:18px;">
            <div>
                <h2 style="font-size:20px;margin:0 0 6px;color:#fff;display:flex;align-items:center;gap:8px;">
                    🌐 TMDB 1-Click Movie Import
                    <span style="background:#032541;color:#01b4e4;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;">API Ready</span>
                </h2>
                <p style="color:#888;font-size:14px;margin:0;">
                    Search any movie on TMDB or enter a TMDB ID to automatically fetch all details (Actors, Runtime, Release Date, Trailer, Poster, Backdrop, Income/Revenue, Budget).
                </p>
            </div>
        </div>

        <!-- TMDB SEARCH FORM -->
        <form method="GET" action="<?= BASE_URL ?>/admin/movies.php" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
            <input
                type="text"
                name="tmdb_search"
                placeholder="Search TMDB by title (e.g. Oppenheimer, Dune, Avatar, Gladiator)..."
                value="<?= e($tmdbSearchQuery) ?>"
                style="flex:1;min-width:260px;padding:11px 16px;border-radius:8px;border:1px solid #333;background:#101010;color:#fff;font-size:14px;"
            >
            <button type="submit" class="btn btn-primary" style="display:flex;align-items:center;gap:6px;">
                🔍 Search TMDB
            </button>
            <?php if ($tmdbSearchQuery !== ''): ?>
                <a href="<?= BASE_URL ?>/admin/movies.php" class="btn btn-secondary">
                    Clear
                </a>
            <?php endif; ?>
        </form>

        <!-- TMDB DIRECT ID IMPORT FORM -->
        <form method="POST" action="<?= BASE_URL ?>/admin/movies.php" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:#101010;padding:12px 16px;border-radius:8px;border:1px solid #222;margin-bottom:<?= !empty($tmdbSearchResults) ? '25px' : '0' ?>;">
            <?= csrfField() ?>
            <input type="hidden" name="import_tmdb" value="1">
            <span style="font-size:13px;color:#aaa;white-space:nowrap;">Direct Import by TMDB ID:</span>
            <input
                type="number"
                name="tmdb_id"
                placeholder="TMDB ID (e.g. 550)"
                required
                style="width:160px;padding:8px 12px;border-radius:6px;border:1px solid #333;background:#181818;color:#fff;font-size:13px;"
            >
            <label style="display:inline-flex;align-items:center;gap:6px;color:#ccc;font-size:13px;cursor:pointer;">
                <input type="checkbox" name="is_premium" value="1">
                👑 Premium
            </label>
            <button type="submit" class="btn btn-secondary" style="background:#2563eb;color:#fff;border:none;">
                📥 Import Movie
            </button>
        </form>

        <!-- TMDB SEARCH RESULTS -->
        <?php if (!empty($tmdbSearchResults)): ?>
            <div style="margin-top:20px;border-top:1px solid #262626;padding-top:20px;">
                <h3 style="font-size:15px;color:#eee;margin:0 0 16px;">
                    Search Results for &ldquo;<?= e($tmdbSearchQuery) ?>&rdquo; (<?= count($tmdbSearchResults) ?> found)
                </h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:14px;">
                    <?php foreach ($tmdbSearchResults as $res): ?>
                        <div style="background:#111;border:1px solid #262626;border-radius:10px;padding:12px;display:flex;gap:12px;align-items:flex-start;">
                            <div style="width:60px;height:90px;flex-shrink:0;border-radius:6px;overflow:hidden;background:#222;">
                                <?php if (!empty($res['poster_path'])): ?>
                                    <img src="https://image.tmdb.org/t/p/w185<?= e($res['poster_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#666;">🎬</div>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;min-width:0;display:flex;flex-direction:column;justify-content:space-between;height:90px;">
                                <div>
                                    <strong style="color:#fff;font-size:13px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= e($res['title']) ?>">
                                        <?= e($res['title']) ?>
                                    </strong>
                                    <span style="color:#888;font-size:12px;">
                                        📅 <?= e(substr($res['release_date'] ?? '', 0, 4)) ?> • ★ <?= number_format((float)($res['vote_average'] ?? 0), 1) ?>
                                    </span>
                                </div>
                                <form method="POST" action="<?= BASE_URL ?>/admin/movies.php" style="margin:0;display:flex;align-items:center;gap:8px;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="import_tmdb" value="1">
                                    <input type="hidden" name="tmdb_id" value="<?= (int) $res['id'] ?>">
                                    <button type="submit" class="btn btn-primary" style="padding:4px 8px;font-size:11px;display:inline-flex;align-items:center;gap:4px;">
                                        📥 Import
                                    </button>
                                    <label style="font-size:11px;color:#aaa;cursor:pointer;">
                                        <input type="checkbox" name="is_premium" value="1"> Prem
                                    </label>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($tmdbSearchQuery !== ''): ?>
            <p style="color:#888;margin:15px 0 0;font-size:14px;">No movies found on TMDB for &ldquo;<?= e($tmdbSearchQuery) ?>&rdquo;.</p>
        <?php endif; ?>
    </div>


    <!-- ADD / EDIT FORM -->
    <div class="movie-form">

        <h2>
            <?= $editMovie ? '✏️ Edit Movie' : '➕ Add New Movie' ?>
        </h2>

        <form method="POST">

            <?= csrfField() ?>

            <input
                type="hidden"
                name="movie_id"
                value="<?= $editMovie ? (int) $editMovie['id'] : 0 ?>"
            >

            <div class="form-grid">

                <div class="form-group">
                    <label>Movie Title</label>
                    <input
                        type="text"
                        name="title"
                        required
                        value="<?= e($editMovie['title'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>TMDB ID (optional)</label>
                    <input
                        type="number"
                        name="tmdb_id"
                        value="<?= e($editMovie['tmdb_id'] ?? '') ?>"
                        placeholder="e.g. 550"
                    >
                </div>

                <div class="form-group">
                    <label>Tagline</label>
                    <input
                        type="text"
                        name="tagline"
                        value="<?= e($editMovie['tagline'] ?? '') ?>"
                        placeholder="e.g. Mischief. Mayhem. Soap."
                    >
                </div>

                <div class="form-group">
                    <label>Director</label>
                    <input
                        type="text"
                        name="director"
                        value="<?= e($editMovie['director'] ?? '') ?>"
                        placeholder="e.g. Christopher Nolan"
                    >
                </div>

                <div class="form-group">
                    <label>Release Year</label>
                    <input
                        type="number"
                        name="release_year"
                        min="1900"
                        max="2100"
                        required
                        value="<?= e($editMovie['release_year'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Release Date</label>
                    <input
                        type="date"
                        name="release_date"
                        value="<?= e($editMovie['release_date'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input
                        type="number"
                        name="duration"
                        min="1"
                        required
                        value="<?= e($editMovie['duration'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Rating (0 - 10)</label>
                    <input
                        type="number"
                        name="rating"
                        min="0"
                        max="10"
                        step="0.1"
                        value="<?= e($editMovie['rating'] ?? '0') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Box Office Income / Revenue (USD)</label>
                    <input
                        type="number"
                        name="revenue"
                        min="0"
                        value="<?= e($editMovie['revenue'] ?? '0') ?>"
                        placeholder="e.g. 100853753"
                    >
                </div>

                <div class="form-group">
                    <label>Production Budget (USD)</label>
                    <input
                        type="number"
                        name="budget"
                        min="0"
                        value="<?= e($editMovie['budget'] ?? '0') ?>"
                        placeholder="e.g. 63000000"
                    >
                </div>

                <div class="form-group full">
                    <label>Description / Storyline</label>
                    <textarea
                        name="description"
                        required
                    ><?= e($editMovie['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Poster Image URL</label>
                    <input
                        type="url"
                        name="poster"
                        value="<?= e($editMovie['poster'] ?? '') ?>"
                        placeholder="https://image.tmdb.org/t/p/w500/..."
                    >
                </div>

                <div class="form-group">
                    <label>Backdrop Banner URL</label>
                    <input
                        type="url"
                        name="backdrop"
                        value="<?= e($editMovie['backdrop'] ?? '') ?>"
                        placeholder="https://image.tmdb.org/t/p/original/..."
                    >
                </div>

                <div class="form-group">
                    <label>Trailer URL</label>
                    <input
                        type="url"
                        name="trailer_url"
                        value="<?= e($editMovie['trailer_url'] ?? '') ?>"
                        placeholder="https://youtube.com/watch?v=..."
                    >
                </div>

                <div class="form-group">
                    <label>YouTube Trailer Key</label>
                    <input
                        type="text"
                        name="trailer_key"
                        value="<?= e($editMovie['trailer_key'] ?? '') ?>"
                        placeholder="e.g. dfeUzm6KF4g"
                    >
                </div>

                <div class="form-group full">
                    <label>Video Stream URL</label>
                    <input
                        type="url"
                        name="video_url"
                        required
                        value="<?= e($editMovie['video_url'] ?? '') ?>"
                        placeholder="https://example.com/movie.mp4"
                    >
                </div>

                <div class="form-group full">
                    <label class="checkbox-group">
                        <input
                            type="checkbox"
                            name="is_premium"
                            value="1"
                            <?= !empty($editMovie['is_premium']) ? 'checked' : '' ?>
                        >
                        👑 Premium Movie
                    </label>
                </div>

            </div>

            <div class="form-actions">
                <button
                    type="submit"
                    name="save_movie"
                    class="btn btn-primary"
                >
                    <?= $editMovie ? '💾 Update Movie' : '➕ Add Movie' ?>
                </button>

                <?php if ($editMovie): ?>
                    <a
                        href="<?= BASE_URL ?>/admin/movies.php"
                        class="btn btn-secondary"
                    >
                        Cancel
                    </a>
                <?php endif; ?>
            </div>

        </form>

    </div>


    <!-- SEARCH / FILTER -->

    <div class="filter-box">

        <h2>🔎 Search & Filter</h2>

        <form
            method="GET"
            class="filter-form"
        >

            <input
                type="text"
                name="search"
                placeholder="Search movie title..."
                value="<?= e($search) ?>"
            >


            <select name="premium">

                <option
                    value="all"
                    <?= $premiumFilter === 'all' ? 'selected' : '' ?>
                >
                    All Movies
                </option>

                <option
                    value="free"
                    <?= $premiumFilter === 'free' ? 'selected' : '' ?>
                >
                    Free Movies
                </option>

                <option
                    value="premium"
                    <?= $premiumFilter === 'premium' ? 'selected' : '' ?>
                >
                    Premium Movies
                </option>

            </select>


            <button
                type="submit"
                class="btn btn-primary"
            >
                🔎 Search
            </button>


            <a
                href="<?= BASE_URL ?>/admin/movies.php"
                class="btn btn-secondary"
            >
                Reset
            </a>

        </form>

    </div>


    <!-- MOVIE LIST -->

    <h2>
        🎞️ Movies
        <small>
            (<?= $totalMovies ?> found)
        </small>
    </h2>


    <?php if (empty($movies)): ?>

        <div class="empty">

            <h3>No movies found.</h3>

            <p>
                Try another search or add a new movie.
            </p>

        </div>

    <?php else: ?>

        <div class="movie-table-wrapper">

            <table class="movie-table">

                <thead>

                    <tr>

                        <th>Poster</th>

                        <th>Movie</th>

                        <th>TMDB ID</th>

                        <th>Director</th>

                        <th>Release</th>

                        <th>Duration</th>

                        <th>Box Office (Income)</th>

                        <th>Rating</th>

                        <th>Type</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($movies as $movie): ?>

                    <tr>

                        <td>

                            <?php if (!empty($movie['poster'])): ?>

                                <img
                                    src="<?= e($movie['poster']) ?>"
                                    alt="<?= e($movie['title']) ?>"
                                    class="movie-poster"
                                >

                            <?php else: ?>

                                <div class="movie-poster">
                                    🎬
                                </div>

                            <?php endif; ?>

                        </td>


                        <td>

                            <strong>
                                <?= e($movie['title']) ?>
                            </strong>

                        </td>


                        <td>
                            <?php if (!empty($movie['tmdb_id'])): ?>
                                <span style="background:#032541;color:#01b4e4;padding:3px 8px;border-radius:4px;font-weight:700;font-size:11px;">
                                    #<?= (int) $movie['tmdb_id'] ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#666;font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>


                        <td>
                            <?= !empty($movie['director']) ? e($movie['director']) : '<span style="color:#666;">—</span>' ?>
                        </td>


                        <td>
                            <?= !empty($movie['release_date']) ? e($movie['release_date']) : (int) $movie['release_year'] ?>
                        </td>


                        <td>
                            <?= (int) $movie['duration'] ?> min
                        </td>


                        <td>
                            <?php if (!empty($movie['revenue'])): ?>
                                <span style="color:#4ade80;font-weight:700;font-size:13px;">
                                    $<?= number_format((float) $movie['revenue']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#666;font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>


                        <td>
                            ⭐ <?= number_format((float) $movie['rating'], 1) ?>
                        </td>


                        <td>

                            <?php if ((int) $movie['is_premium'] === 1): ?>

                                <span class="badge badge-premium">
                                    👑 Premium
                                </span>

                            <?php else: ?>

                                <span class="badge badge-free">
                                    Free
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <div class="actions">

                                <a
                                    href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                                    class="btn btn-secondary"
                                    target="_blank"
                                >
                                    View
                                </a>


                                <a
                                    href="<?= BASE_URL ?>/admin/movies.php?edit=<?= (int) $movie['id'] ?>"
                                    class="btn btn-primary"
                                >
                                    Edit
                                </a>


                                <form
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this movie?');"
                                >

                                    <?= csrfField() ?>

                                    <input
                                        type="hidden"
                                        name="movie_id"
                                        value="<?= (int) $movie['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="delete_movie"
                                        class="btn btn-danger"
                                    >
                                        Delete
                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>


        <!-- PAGINATION -->

        <?php if ($totalPages > 1): ?>

            <div class="pagination">

                <?php if ($page > 1): ?>

                    <a
                        href="?search=<?= urlencode($search) ?>&premium=<?= urlencode($premiumFilter) ?>&page=<?= $page - 1 ?>"
                    >
                        ← Previous
                    </a>

                <?php endif; ?>


                <?php

                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);

                ?>


                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>

                    <?php if ($i === $page): ?>

                        <span class="active">
                            <?= $i ?>
                        </span>

                    <?php else: ?>

                        <a
                            href="?search=<?= urlencode($search) ?>&premium=<?= urlencode($premiumFilter) ?>&page=<?= $i ?>"
                        >
                            <?= $i ?>
                        </a>

                    <?php endif; ?>

                <?php endfor; ?>


                <?php if ($page < $totalPages): ?>

                    <a
                        href="?search=<?= urlencode($search) ?>&premium=<?= urlencode($premiumFilter) ?>&page=<?= $page + 1 ?>"
                    >
                        Next →
                    </a>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>