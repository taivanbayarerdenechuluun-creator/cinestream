<?php
declare(strict_types=1);

/**
 * includes/tmdb.php
 * TMDB (The Movie Database) API Service and Integration
 */

const TMDB_API_TOKEN = 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiI4NzZiMzEwNzJlZDg5ODcwMzQxM2Y0NzkyYzZjZTdjYyIsIm5iZiI6MTczODAyNjY5NS44NCwic3ViIjoiNjc5ODJlYzc3MDJmNDkyZjQ3OGY2OGUwIiwic2NvcGVzIjpbImFwaV9yZWFkIl0sInZlcnNpb24iOjF9.k4OF9yGrhA2gZ4VKCH7KLnNBB2LIf1Quo9c3lGF6toE';
const TMDB_API_BASE_URL = 'https://api.themoviedb.org/3';
const TMDB_IMAGE_POSTER_URL = 'https://image.tmdb.org/t/p/w500';
const TMDB_IMAGE_BACKDROP_URL = 'https://image.tmdb.org/t/p/original';
const TMDB_IMAGE_PROFILE_URL = 'https://image.tmdb.org/t/p/w185';

/**
 * Make an HTTP GET request to TMDB API using cURL.
 */
function tmdbApiRequest(string $endpoint, array $queryParams = []): ?array
{
    $url = TMDB_API_BASE_URL . '/' . ltrim($endpoint, '/');
    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . TMDB_API_TOKEN,
            'Accept: application/json',
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode < 200 || $httpCode >= 300) {
        error_log("TMDB API Error [{$httpCode}]: {$error} for {$url}");
        return null;
    }

    $decoded = json_decode((string) $response, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Fetch full movie details including credits and videos by TMDB ID.
 */
function tmdbGetMovie(int $tmdbId): ?array
{
    return tmdbApiRequest("movie/{$tmdbId}", [
        'append_to_response' => 'credits,videos'
    ]);
}

/**
 * Search movies on TMDB by title query.
 */
function tmdbSearchMovies(string $query, int $page = 1): ?array
{
    return tmdbApiRequest('search/movie', [
        'query' => $query,
        'page' => $page,
        'include_adult' => 'false'
    ]);
}

/**
 * Fetch popular movies from TMDB.
 */
function tmdbGetPopular(int $page = 1): ?array
{
    return tmdbApiRequest('movie/popular', [
        'page' => $page
    ]);
}

/**
 * Fetch top rated movies from TMDB.
 */
function tmdbGetTopRated(int $page = 1): ?array
{
    return tmdbApiRequest('movie/top_rated', [
        'page' => $page
    ]);
}

/**
 * Parse raw TMDB movie payload into structured, cleaned application array.
 */
function tmdbParseMovieData(array $raw): array
{
    $tmdbId = (int) ($raw['id'] ?? 0);
    $title = trim((string) ($raw['title'] ?? ''));
    $description = trim((string) ($raw['overview'] ?? ''));
    $tagline = trim((string) ($raw['tagline'] ?? ''));

    $releaseDate = !empty($raw['release_date']) ? (string) $raw['release_date'] : null;
    $releaseYear = 0;
    if ($releaseDate && preg_match('/^(\d{4})/', $releaseDate, $m)) {
        $releaseYear = (int) $m[1];
    }

    $duration = (int) ($raw['runtime'] ?? 0);
    if ($duration <= 0) {
        $duration = 120; // fallback default
    }

    $poster = !empty($raw['poster_path'])
        ? TMDB_IMAGE_POSTER_URL . $raw['poster_path']
        : null;

    $backdrop = !empty($raw['backdrop_path'])
        ? TMDB_IMAGE_BACKDROP_URL . $raw['backdrop_path']
        : null;

    $budget = (int) ($raw['budget'] ?? 0);
    $revenue = (int) ($raw['revenue'] ?? 0);
    $rating = isset($raw['vote_average']) ? round((float) $raw['vote_average'], 1) : 0.0;

    // Director
    $director = '';
    if (!empty($raw['credits']['crew'])) {
        foreach ($raw['credits']['crew'] as $crew) {
            if (($crew['job'] ?? '') === 'Director') {
                $director = (string) $crew['name'];
                break;
            }
        }
    }

    // Trailer
    $trailerKey = '';
    $trailerUrl = '';
    if (!empty($raw['videos']['results'])) {
        foreach ($raw['videos']['results'] as $vid) {
            if (($vid['site'] ?? '') === 'YouTube' && ($vid['type'] ?? '') === 'Trailer') {
                $trailerKey = (string) $vid['key'];
                $trailerUrl = 'https://www.youtube.com/watch?v=' . $trailerKey;
                break;
            }
        }
        // Fallback to Teaser if no Trailer
        if ($trailerKey === '') {
            foreach ($raw['videos']['results'] as $vid) {
                if (($vid['site'] ?? '') === 'YouTube') {
                    $trailerKey = (string) $vid['key'];
                    $trailerUrl = 'https://www.youtube.com/watch?v=' . $trailerKey;
                    break;
                }
            }
        }
    }

    // Cast (top 15)
    $cast = [];
    if (!empty($raw['credits']['cast'])) {
        $topCast = array_slice($raw['credits']['cast'], 0, 16);
        foreach ($topCast as $actor) {
            $profileUrl = !empty($actor['profile_path'])
                ? TMDB_IMAGE_PROFILE_URL . $actor['profile_path']
                : null;

            $cast[] = [
                'id' => (int) ($actor['id'] ?? 0),
                'name' => (string) ($actor['name'] ?? ''),
                'character' => (string) ($actor['character'] ?? ''),
                'profile' => $profileUrl
            ];
        }
    }

    // Genres
    $genres = [];
    if (!empty($raw['genres'])) {
        foreach ($raw['genres'] as $g) {
            if (!empty($g['name'])) {
                $genres[] = (string) $g['name'];
            }
        }
    }

    return [
        'tmdb_id' => $tmdbId,
        'title' => $title,
        'description' => $description,
        'tagline' => $tagline,
        'release_year' => $releaseYear,
        'release_date' => $releaseDate,
        'duration' => $duration,
        'poster' => $poster,
        'backdrop' => $backdrop,
        'budget' => $budget,
        'revenue' => $revenue,
        'director' => $director,
        'rating' => $rating,
        'trailer_key' => $trailerKey,
        'trailer_url' => $trailerUrl,
        'cast' => $cast,
        'cast_data' => !empty($cast) ? json_encode($cast, JSON_UNESCAPED_UNICODE) : null,
        'genres' => $genres,
        'video_url' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
    ];
}

/**
 * Save or update parsed TMDB movie data in the MySQL database.
 */
function tmdbSaveMovieToDb(PDO $db, array $movieData, ?int $targetMovieId = null, bool $isPremium = false): int
{
    $tmdbId = $movieData['tmdb_id'] ?: null;
    $existingId = $targetMovieId;

    if (!$existingId && $tmdbId) {
        $stmt = $db->prepare('SELECT id FROM movies WHERE tmdb_id = :tmdb_id LIMIT 1');
        $stmt->execute([':tmdb_id' => $tmdbId]);
        $existingId = $stmt->fetchColumn() ?: null;
    }

    if (!$existingId) {
        $stmt = $db->prepare('SELECT id FROM movies WHERE title = :title LIMIT 1');
        $stmt->execute([':title' => $movieData['title']]);
        $existingId = $stmt->fetchColumn() ?: null;
    }

    $castJson = is_string($movieData['cast_data'] ?? null)
        ? $movieData['cast_data']
        : (!empty($movieData['cast']) ? json_encode($movieData['cast'], JSON_UNESCAPED_UNICODE) : null);

    if ($existingId) {
        $updateSql = 'UPDATE movies SET
            tmdb_id = COALESCE(:tmdb_id, tmdb_id),
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
            trailer_key = :trailer_key,
            trailer_url = :trailer_url,
            cast_data = :cast_data,
            rating = :rating
            WHERE id = :id';

        $stmt = $db->prepare($updateSql);
        $stmt->execute([
            ':tmdb_id' => $tmdbId,
            ':title' => $movieData['title'],
            ':description' => $movieData['description'],
            ':tagline' => $movieData['tagline'] ?: null,
            ':release_year' => $movieData['release_year'],
            ':release_date' => $movieData['release_date'] ?: null,
            ':duration' => $movieData['duration'],
            ':budget' => $movieData['budget'] ?? 0,
            ':revenue' => $movieData['revenue'] ?? 0,
            ':poster' => $movieData['poster'] ?: null,
            ':backdrop' => $movieData['backdrop'] ?: null,
            ':director' => $movieData['director'] ?: null,
            ':trailer_key' => $movieData['trailer_key'] ?: null,
            ':trailer_url' => $movieData['trailer_url'] ?: null,
            ':cast_data' => $castJson,
            ':rating' => $movieData['rating'],
            ':id' => $existingId
        ]);

        $finalMovieId = (int) $existingId;
    } else {
        $insertSql = 'INSERT INTO movies (
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
        ) VALUES (
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
            :trailer_key,
            :trailer_url,
            :video_url,
            :cast_data,
            :rating,
            :is_premium
        )';

        $stmt = $db->prepare($insertSql);
        $stmt->execute([
            ':tmdb_id' => $tmdbId,
            ':title' => $movieData['title'],
            ':description' => $movieData['description'],
            ':tagline' => $movieData['tagline'] ?: null,
            ':release_year' => $movieData['release_year'],
            ':release_date' => $movieData['release_date'] ?: null,
            ':duration' => $movieData['duration'],
            ':budget' => $movieData['budget'] ?? 0,
            ':revenue' => $movieData['revenue'] ?? 0,
            ':poster' => $movieData['poster'] ?: null,
            ':backdrop' => $movieData['backdrop'] ?: null,
            ':director' => $movieData['director'] ?: null,
            ':trailer_key' => $movieData['trailer_key'] ?: null,
            ':trailer_url' => $movieData['trailer_url'] ?: null,
            ':video_url' => $movieData['video_url'] ?? 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
            ':cast_data' => $castJson,
            ':rating' => $movieData['rating'],
            ':is_premium' => $isPremium ? 1 : 0
        ]);

        $finalMovieId = (int) $db->lastInsertId();
    }

    // Sync genres
    if (!empty($movieData['genres'])) {
        foreach ($movieData['genres'] as $genreName) {
            $genreName = trim((string) $genreName);
            if ($genreName === '') continue;

            // Ensure genre exists in genres table
            $genreStmt = $db->prepare('SELECT id FROM genres WHERE name = :name LIMIT 1');
            $genreStmt->execute([':name' => $genreName]);
            $genreId = $genreStmt->fetchColumn();

            if (!$genreId) {
                $insertGenre = $db->prepare('INSERT INTO genres (name) VALUES (:name)');
                $insertGenre->execute([':name' => $genreName]);
                $genreId = $db->lastInsertId();
            }

            // Link in movie_genres
            $linkStmt = $db->prepare('INSERT IGNORE INTO movie_genres (movie_id, genre_id) VALUES (:movie_id, :genre_id)');
            $linkStmt->execute([
                ':movie_id' => $finalMovieId,
                ':genre_id' => (int) $genreId
            ]);
        }
    }

    return $finalMovieId;
}

/**
 * Ensure the given movie record array has TMDB details populated.
 * If details or cast_data are missing and TMDB ID or title is available,
 * fetch from TMDB, update DB, and enrich the array in-place.
 */
function ensureMovieTmdbData(PDO $db, array &$movie): void
{
    $needsEnrich = empty($movie['cast_data']) || empty($movie['backdrop']) || empty($movie['director']) || empty($movie['trailer_key']);

    if (!$needsEnrich) {
        return;
    }

    $tmdbDetails = null;

    if (!empty($movie['tmdb_id'])) {
        $tmdbDetails = tmdbGetMovie((int) $movie['tmdb_id']);
    } elseif (!empty($movie['title'])) {
        // Search by title
        $searchRes = tmdbSearchMovies($movie['title']);
        if (!empty($searchRes['results'][0]['id'])) {
            $tmdbDetails = tmdbGetMovie((int) $searchRes['results'][0]['id']);
        }
    }

    if ($tmdbDetails && !empty($tmdbDetails['id'])) {
        $parsed = tmdbParseMovieData($tmdbDetails);
        tmdbSaveMovieToDb($db, $parsed, (int) $movie['id'], (int) ($movie['is_premium'] ?? 0) === 1);

        // Update passed array with fresh values
        foreach ($parsed as $key => $val) {
            if ($key !== 'id') {
                $movie[$key] = $val;
            }
        }
    }
}
