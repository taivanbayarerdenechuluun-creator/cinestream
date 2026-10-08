<?php
declare(strict_types=1);

/**
 * includes/auth.php
 * Reusable authentication helpers (PHP sessions + PDO).
 *
 * Usage at the very TOP of any page (before any HTML output):
 *
 *     require_once __DIR__ . '/includes/auth.php';      // from project root
 *     require_once __DIR__ . '/../includes/auth.php';   // from admin/ folder
 */

require_once __DIR__ . '/../config/database.php';

// URL path of the project in the browser (http://localhost/movie-streaming)
const BASE_URL = '/movie-streaming';

// Log users out after 2 hours without activity
const SESSION_IDLE_TIMEOUT = 7200;

/* ---------------------------------------------------------------------
 * Session handling
 * ------------------------------------------------------------------ */

/** Starts a session with secure cookie settings (safe to call many times). */
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');   // reject unknown session IDs
    ini_set('session.use_only_cookies', '1');  // never put session ID in URL
    ini_set('session.use_trans_sid', '0');

    session_name('MOVIESESSID');
    session_set_cookie_params([
        'lifetime' => 0,          // cookie ends when browser closes
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,   // true automatically when using HTTPS
        'httponly' => true,       // JavaScript cannot read the cookie
        'samesite' => 'Lax',      // basic CSRF protection
    ]);

    session_start();

    // Idle timeout
    if (isset($_SESSION['last_activity'])
        && (time() - (int) $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT) {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    // Basic session-hijacking check: browser (User-Agent) must stay the same
    $uaHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (isset($_SESSION['ua_hash']) && !hash_equals($_SESSION['ua_hash'], $uaHash)) {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    $_SESSION['ua_hash']       = $uaHash;
    $_SESSION['last_activity'] = time();
}

/** Redirects to a path inside the project, e.g. redirect('/login.php'). */
function redirect(string $path): never
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/* ---------------------------------------------------------------------
 * Output + CSRF helpers
 * ------------------------------------------------------------------ */

/** Escapes text for safe display in HTML. Use for ALL user-generated content. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Returns the CSRF token for the current session (creates it if needed). */
function csrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to put inside every POST form. */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/** Checks a submitted CSRF token. */
function verifyCsrfToken(?string $token): bool
{
    startSecureSession();
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ---------------------------------------------------------------------
 * Passwords
 * ------------------------------------------------------------------ */

/** Hashes a password for storing in users.password. */
function hashPassword(string $plainPassword): string
{
    return password_hash($plainPassword, PASSWORD_DEFAULT);
}

/* ---------------------------------------------------------------------
 * Login state
 * ------------------------------------------------------------------ */

/** Is somebody logged in? */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
}

/** Logs a user in by ID (call only after the password was verified). */
function loginUser(int $userId): void
{
    startSecureSession();
    session_regenerate_id(true);   // prevents session fixation
    $_SESSION['user_id'] = $userId;
    getCurrentUser(true);          // refresh cached user
}

/**
 * Checks email + password. Returns true and logs the user in on success.
 * Uses prepared statements and password_verify().
 */
function attemptLogin(string $email, string $password): bool
{
    startSecureSession();

    $email = strtolower(trim($email));

    $stmt = getDB()->prepare('SELECT id, password FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password'])) {
        return false;
    }

    // Upgrade the stored hash if PHP's default algorithm/cost has changed
    if (password_needs_rehash($row['password'], PASSWORD_DEFAULT)) {
        $upd = getDB()->prepare('UPDATE users SET password = :hash WHERE id = :id');
        $upd->execute([':hash' => hashPassword($password), ':id' => $row['id']]);
    }

    loginUser((int) $row['id']);
    return true;
}

/**
 * Returns the logged-in user as an array (id, name, email, role,
 * subscription_status, created_at) or null. The password hash is never returned.
 * Pass true to force a fresh read from the database.
 */
function getCurrentUser(bool $refresh = false): ?array
{
    static $cachedUser = null;
    static $loaded     = false;

    if ($refresh) {
        $cachedUser = null;
        $loaded     = false;
    }
    if ($loaded) {
        return $cachedUser;
    }
    $loaded = true;

    if (!isLoggedIn()) {
        return $cachedUser = null;
    }

    $stmt = getDB()->prepare(
        'SELECT id, name, email, role, subscription_status, subscription_expires_at, created_at
         FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // The user was deleted from the database -> end the session
        $_SESSION = [];
        return $cachedUser = null;
    }

    return $cachedUser = $user;
}

/** Sends visitors who are not logged in to the login page. */
function requireLogin(): void
{
    if (!isLoggedIn() || getCurrentUser() === null) {
        redirect('/login.php');
    }
}

/** Is the current user an admin? (role is read fresh from the database) */
function isAdmin(): bool
{
    $user = getCurrentUser();
    return $user !== null && $user['role'] === 'admin';
}

/** Allows admins only. Others get a 403 error page. */
function requireAdmin(): void
{
    requireLogin();

    if (!isAdmin()) {
        http_response_code(403);
        exit('403 Forbidden - Administrators only.');
    }
}

/** Fully logs the user out and destroys the session. */
function logout(): void
{
    startSecureSession();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
    getCurrentUser(true);   // clear cached user
}

// Start the session automatically whenever this file is included.
startSecureSession();
