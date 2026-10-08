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