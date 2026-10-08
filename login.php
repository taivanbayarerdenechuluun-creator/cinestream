<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Аль хэдийн login хийсэн бол Home руу явуулна
if (isLoggedIn()) {
    redirect('/index.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF шалгах
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Хүсэлт буруу байна. Хуудсаа refresh хийгээд дахин оролдоно уу.';
    }

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    // Email шалгах
    if ($email === '') {
        $errors[] = 'Email хаягаа оруулна уу.';
    }

    // Password шалгах
    if ($password === '') {
        $errors[] = 'Нууц үгээ оруулна уу.';
    }

    // Алдаа байхгүй бол login хийх
    if (empty($errors)) {

        if (!attemptLogin($email, $password)) {
            $errors[] = 'Email эсвэл нууц үг буруу байна.';
        } else {
            // Амжилттай login
            redirect('/index.php');
        }
    }
}

$registered = isset($_GET['registered']) && $_GET['registered'] === '1';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign In - CineStream</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;

            background:
                radial-gradient(circle at top left, #1f2937 0%, transparent 35%),
                radial-gradient(circle at bottom right, #111827 0%, transparent 40%),
                #050505;

            color: #ffffff;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 30px;
        }

        .login-container {
            width: 100%;
            max-width: 460px;
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo a {
            color: #ffffff;
            text-decoration: none;

            font-size: 32px;
            font-weight: 800;
            letter-spacing: -1px;
        }

        .logo span {
            color: #e50914;
        }

        .login-card {
            background: rgba(20, 20, 20, 0.96);

            border: 1px solid #2d2d2d;
            border-radius: 16px;

            padding: 35px;

            box-shadow:
                0 25px 60px rgba(0, 0, 0, 0.55);
        }

        .login-card h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #999999;
            margin-bottom: 28px;
            font-size: 15px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;

            margin-bottom: 8px;

            font-size: 14px;
            font-weight: 600;

            color: #dddddd;
        }

        .form-group input {
            width: 100%;

            padding: 13px 14px;

            background: #111111;

            border: 1px solid #3a3a3a;
            border-radius: 8px;

            color: #ffffff;

            font-size: 15px;

            outline: none;

            transition:
                border-color 0.2s,
                box-shadow 0.2s;
        }

        .form-group input:focus {
            border-color: #e50914;

            box-shadow:
                0 0 0 3px rgba(229, 9, 20, 0.12);
        }

        .error-box {
            background: rgba(229, 9, 20, 0.12);

            border: 1px solid rgba(229, 9, 20, 0.5);

            color: #ff8a8a;

            border-radius: 8px;

            padding: 14px;

            margin-bottom: 20px;

            font-size: 14px;
        }

        .error-box ul {
            padding-left: 18px;
        }

        .error-box li {
            margin-bottom: 5px;
        }

        .error-box li:last-child {
            margin-bottom: 0;
        }

        .success-box {
            background: rgba(34, 197, 94, 0.10);

            border: 1px solid rgba(34, 197, 94, 0.35);

            color: #86efac;

            border-radius: 8px;

            padding: 14px;

            margin-bottom: 20px;

            font-size: 14px;
        }

        .login-button {
            width: 100%;

            border: none;
            border-radius: 8px;

            padding: 14px;

            background: #e50914;

            color: white;

            font-size: 16px;
            font-weight: 700;

            cursor: pointer;

            transition:
                background 0.2s,
                transform 0.1s;
        }

        .login-button:hover {
            background: #f6121d;
        }

        .login-button:active {
            transform: scale(0.99);
        }

        .register-link {
            text-align: center;

            margin-top: 24px;

            color: #999999;

            font-size: 14px;
        }

        .register-link a {
            color: #ffffff;

            font-weight: 600;

            text-decoration: none;
        }

        .register-link a:hover {
            color: #e50914;
        }

        @media (max-width: 500px) {

            body {
                padding: 15px;
            }

            .login-card {
                padding: 25px 20px;
            }

            .logo a {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>

<div class="login-container">

    <div class="logo">
        <a href="index.php">
            Cine<span>Stream</span>
        </a>
    </div>

    <div class="login-card">

        <h1>Welcome back</h1>

        <p class="subtitle">
            Sign in to continue watching.
        </p>

        <?php if ($registered): ?>

            <div class="success-box">
                Account created successfully.
                You can now sign in.
            </div>

        <?php endif; ?>

        <?php if (!empty($errors)): ?>

            <div class="error-box">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

        <?php endif; ?>

        <form method="POST" action="login.php">

            <?= csrfField() ?>

            <div class="form-group">

                <label for="email">
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($email) ?>"
                    placeholder="Enter your email"
                    maxlength="255"
                    autocomplete="email"
                    required
                >

            </div>

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

            </div>

            <button
                type="submit"
                class="login-button"
            >
                Sign In
            </button>

        </form>

        <div class="register-link">
            Don't have an account?
            <a href="register.php">Create account</a>
        </div>

    </div>

</div>

</body>
</html>