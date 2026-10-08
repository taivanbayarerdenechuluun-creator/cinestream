<?php
declare(strict_types=1);

/**
 * config/database.php
 * Secure PDO connection for the Movie Streaming project (XAMPP defaults).
 *
 * This file lives on the server only. It prints nothing, so credentials are
 * never sent to the browser. Other PHP files use it like this:
 *
 *     require_once __DIR__ . '/config/database.php';
 *     $pdo = getDB();
 */

const DB_HOST    = 'localhost';
const DB_NAME    = 'movie_streaming';
const DB_USER    = 'root';
const DB_PASS    = '';          // XAMPP default: empty password
const DB_CHARSET = 'utf8mb4';

/**
 * Creates a new PDO connection. Throws PDOException on failure.
 * (Used directly by test_connection.php to show the real error locally.)
 */
function connectDatabase(): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
    ];

    return new PDO($dsn, DB_USER, DB_PASS, $options);
}

/**
 * Returns one shared PDO connection per request.
 * On failure it logs the real error to the PHP error log and shows
 * only a generic message to visitors (no credentials or details leaked).
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = connectDatabase();
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Sorry, the service is temporarily unavailable. Please try again later.');
    }

    return $pdo;
}
