<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$db = getDB();

$message = '';
$error = '';

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
        $releaseYear = (int) ($_POST['release_year'] ?? 0);
        $duration = (int) ($_POST['duration'] ?? 0);
        $poster = trim($_POST['poster'] ?? '');
        $trailerUrl = trim($_POST['trailer_url'] ?? '');
        $videoUrl = trim($_POST['video_url'] ?? '');
        $rating = (float) ($_POST['rating'] ?? 0);
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
                            title = :title,
                            description = :description,
                            release_year = :release_year,
                            duration = :duration,
                            poster = :poster,
                            trailer_url = :trailer_url,
                            video_url = :video_url,
                            rating = :rating,
                            is_premium = :is_premium
                         WHERE id = :id'
                    );

                    $stmt->execute([
                        ':title' => $title,
                        ':description' => $description,
                        ':release_year' => $releaseYear,
                        ':duration' => $duration,
                        ':poster' => $poster !== '' ? $poster : null,
                        ':trailer_url' => $trailerUrl !== '' ? $trailerUrl : null,
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
                            title,
                            description,
                            release_year,
                            duration,
                            poster,
                            trailer_url,
                            video_url,
                            rating,
                            is_premium
                        )
                        VALUES
                        (
                            :title,
                            :description,
                            :release_year,
                            :duration,
                            :poster,
                            :trailer_url,
                            :video_url,
                            :rating,
                            :is_premium
                        )'
                    );

                    $stmt->execute([
                        ':title' => $title,
                        ':description' => $description,
                        ':release_year' => $releaseYear,
                        ':duration' => $duration,
                        ':poster' => $poster !== '' ? $poster : null,
                        ':trailer_url' => $trailerUrl !== '' ? $trailerUrl : null,
                        ':video_url' => $videoUrl,
                        ':rating' => $rating,
                        ':is_premium' => $isPremium
                    ]);

                    $message = 'Movie added successfully.';
                }

            } catch (Throwable $e) {

                error_log('Admin movie save error: ' . $e->getMessage());

                $error = 'Failed to save movie.';
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
            title,
            release_year,
            duration,
            poster,
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


                <div class="form-group full">

                    <label>Description</label>

                    <textarea
                        name="description"
                        required
                    ><?= e($editMovie['description'] ?? '') ?></textarea>

                </div>


                <div class="form-group full">

                    <label>Poster URL</label>

                    <input
                        type="url"
                        name="poster"
                        value="<?= e($editMovie['poster'] ?? '') ?>"
                        placeholder="https://example.com/poster.jpg"
                    >

                </div>


                <div class="form-group full">

                    <label>Trailer URL</label>

                    <input
                        type="url"
                        name="trailer_url"
                        value="<?= e($editMovie['trailer_url'] ?? '') ?>"
                        placeholder="https://youtube.com/..."
                    >

                </div>


                <div class="form-group full">

                    <label>Video URL</label>

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

                        <th>Year</th>

                        <th>Duration</th>

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
                            <?= (int) $movie['release_year'] ?>
                        </td>


                        <td>
                            <?= (int) $movie['duration'] ?> min
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