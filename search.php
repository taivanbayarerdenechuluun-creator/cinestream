<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$db = getDB();

$query = trim((string) ($_GET['q'] ?? ''));

$movies = [];

if ($query !== '') {

    $search = '%' . $query . '%';

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
         WHERE
            title LIKE :title
            OR description LIKE :description
            OR director LIKE :director
            OR cast_data LIKE :cast
            OR CAST(release_year AS CHAR) LIKE :year
         ORDER BY
            rating DESC,
            release_year DESC
         LIMIT 50'
    );

    $stmt->execute([
        ':title' => $search,
        ':description' => $search,
        ':director' => $search,
        ':cast' => $search,
        ':year' => $search
    ]);

    $movies = $stmt->fetchAll();
}

$pageTitle = $query !== ''
    ? 'Search: ' . $query
    : 'Search';

require_once __DIR__ . '/includes/header.php';
?>


<section class="section">

    <div class="container">

        <!-- SEARCH HEADER -->

        <div class="section-header">

            <div>

                <h1 class="section-title">
                    Search Movies
                </h1>

                <?php if ($query !== ''): ?>

                    <p style="color:#888;margin-top:8px;">
                        Search results for:
                        <strong style="color:#fff;">
                            <?= e($query) ?>
                        </strong>
                    </p>

                <?php else: ?>

                    <p style="color:#888;margin-top:8px;">
                        Find your favorite movies
                    </p>

                <?php endif; ?>

            </div>

        </div>


        <!-- SEARCH FORM -->

        <form
            method="GET"
            action="<?= BASE_URL ?>/search.php"
            style="
                display:flex;
                gap:10px;
                margin-bottom:35px;
                max-width:700px;
            "
        >

            <input
                type="search"
                name="q"
                value="<?= e($query) ?>"
                placeholder="Search movies..."
                autocomplete="off"
                style="
                    flex:1;
                    min-width:0;
                    padding:14px 18px;
                    background:#181818;
                    border:1px solid #333;
                    border-radius:8px;
                    color:#fff;
                    font-size:16px;
                    outline:none;
                "
            >

            <button
                type="submit"
                class="btn btn-primary"
            >
                🔍 Search
            </button>

        </form>


        <?php if ($query === ''): ?>

            <!-- NO SEARCH YET -->

            <div
                style="
                    text-align:center;
                    padding:70px 20px;
                    background:#151515;
                    border:1px solid #292929;
                    border-radius:18px;
                "
            >

                <div style="font-size:55px;">
                    🔎
                </div>

                <h2 style="margin:20px 0 10px;">
                    Search for a movie
                </h2>

                <p style="color:#777;">
                    Enter a movie title, description or release year.
                </p>

            </div>


        <?php elseif (empty($movies)): ?>

            <!-- NO RESULTS -->

            <div
                style="
                    text-align:center;
                    padding:70px 20px;
                    background:#151515;
                    border:1px solid #292929;
                    border-radius:18px;
                "
            >

                <div style="font-size:55px;">
                    😕
                </div>

                <h2 style="margin:20px 0 10px;">
                    No movies found
                </h2>

                <p style="color:#777;">
                    We couldn't find anything matching
                    <strong style="color:#fff;">
                        <?= e($query) ?>
                    </strong>.
                </p>

                <a
                    href="<?= BASE_URL ?>/movies.php"
                    class="btn btn-primary"
                    style="margin-top:20px;"
                >
                    Browse All Movies
                </a>

            </div>


        <?php else: ?>

            <!-- RESULTS -->

            <p
                style="
                    color:#777;
                    margin-bottom:20px;
                "
            >
                <?= count($movies) ?> movie(s) found
            </p>


            <div class="movie-grid">

                <?php foreach ($movies as $movie): ?>

                    <div class="movie-card">

                        <!-- POSTER -->

                        <a
                            href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                            style="
                                text-decoration:none;
                                color:inherit;
                            "
                        >

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


                                <div class="movie-overlay">

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


                        <!-- INFO -->

                        <div class="movie-info">

                            <a
                                href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                                style="
                                    text-decoration:none;
                                    color:inherit;
                                "
                            >

                                <h3 class="movie-title">
                                    <?= e($movie['title']) ?>
                                </h3>

                            </a>


                            <div class="movie-meta">

                                <span>
                                    <?= (int) $movie['release_year'] ?>
                                </span>

                                <span>
                                    <?= (int) $movie['duration'] ?> min
                                </span>

                                <span class="movie-rating">
                                    ★
                                    <?= number_format(
                                        (float) $movie['rating'],
                                        1
                                    ) ?>
                                </span>

                            </div>


                            <?php if ((int) $movie['is_premium'] === 1): ?>

                                <div
                                    style="
                                        color:#e50914;
                                        font-size:12px;
                                        margin-top:8px;
                                    "
                                >
                                    Premium
                                </div>

                            <?php endif; ?>


                            <a
                                href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>"
                                class="btn btn-primary"
                                style="
                                    width:100%;
                                    margin-top:14px;
                                    text-align:center;
                                    display:block;
                                "
                            >
                                View Movie
                            </a>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</section>


<?php
require_once __DIR__ . '/includes/footer.php';
?>