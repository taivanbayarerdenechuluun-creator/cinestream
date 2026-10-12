<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser();

$pageTitle = $pageTitle ?? 'CineStream';
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= e($pageTitle) ?> - CineStream</title>

    <script>
        try {
            document.documentElement.dataset.theme = localStorage.getItem('cinestream-theme') === 'light' ? 'light' : 'dark';
        } catch (error) {
            document.documentElement.dataset.theme = 'dark';
        }
    </script>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>/assets/css/style.css"
    >

</head>

<body>

<header class="site-header">

    <div class="navbar">

        <!-- Logo -->
        <a
            href="<?= BASE_URL ?>/index.php"
            class="logo"
        >
            Cine<span>Stream</span>
        </a>


        <!-- Main Navigation -->
        <nav class="main-nav">

            <a
                href="<?= BASE_URL ?>/index.php"
            >
                Home
            </a>

            <a
                href="<?= BASE_URL ?>/movies.php"
            >
                Movies
            </a>

            <a
                href="<?= BASE_URL ?>/search.php"
            >
                Search
            </a>

        </nav>


        <!-- Right Side -->
        <div class="nav-right">

            <button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch color theme">
                <svg class="theme-toggle-icon" aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3.5"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
                <span class="theme-toggle-label">Light</span>
            </button>

            <?php if ($currentUser): ?>

                <!-- Logged in user -->

                <a
                    href="<?= BASE_URL ?>/profile.php"
                    class="profile-link"
                >
                    <?= e($currentUser['name']) ?>
                </a>

                <form
                    method="POST"
                    action="<?= BASE_URL ?>/logout.php"
                    class="logout-form"
                >

                    <?= csrfField() ?>

                    <button
                        type="submit"
                        class="logout-button"
                    >
                        Logout
                    </button>

                </form>

            <?php else: ?>

                <!-- Guest -->

                <a
                    href="<?= BASE_URL ?>/login.php"
                    class="login-link"
                >
                    Sign In
                </a>

                <a
                    href="<?= BASE_URL ?>/register.php"
                    class="register-button"
                >
                    Get Started
                </a>

            <?php endif; ?>

        </div>

    </div>

</header>

<main class="site-main">
