<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Хэрэв аль хэдийн нэвтэрсэн бол Home руу явуулна
if (isLoggedIn()) {
    redirect('/index.php');
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF шалгах
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Хүсэлт буруу байна. Хуудсаа refresh хийгээд дахин оролдоно уу.';
    }

    // Form утгууд
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    // Нэр шалгах
    if ($name === '') {
        $errors[] = 'Нэрээ оруулна уу.';
    } elseif (mb_strlen($name) < 2) {
        $errors[] = 'Нэр хамгийн багадаа 2 тэмдэгт байх ёстой.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Нэр 100 тэмдэгтээс их байж болохгүй.';
    }

    // Email шалгах
    if ($email === '') {
        $errors[] = 'Email хаягаа оруулна уу.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Зөв email хаяг оруулна уу.';
    }

    // Password шалгах
    if ($password === '') {
        $errors[] = 'Нууц үгээ оруулна уу.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Нууц үг хамгийн багадаа 8 тэмдэгт байх ёстой.';
    } elseif (strlen($password) > 255) {
        $errors[] = 'Нууц үг хэт урт байна.';
    }

    // Password confirmation
    if ($confirmPassword === '') {
        $errors[] = 'Нууц үгээ давтаж оруулна уу.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Нууц үгнүүд таарахгүй байна.';
    }

    // Алдаа байхгүй бол database шалгана
    if (empty($errors)) {

        $db = getDB();

        // Email өмнө нь бүртгэгдсэн эсэх
        $stmt = $db->prepare(
            'SELECT id FROM users WHERE email = :email LIMIT 1'
        );

        $stmt->execute([
            ':email' => $email
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Энэ email аль хэдийн бүртгэлтэй байна.';
        }
    }

    // Бүх шалгалт амжилттай бол хэрэглэгч үүсгэнэ
    if (empty($errors)) {

        $passwordHash = hashPassword($password);

        $stmt = getDB()->prepare(
            'INSERT INTO users 
            (name, email, password, role, subscription_status)
            VALUES 
            (:name, :email, :password, :role, :subscription_status)'
        );

        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $passwordHash,
            ':role' => 'user',
            ':subscription_status' => 'none'
        ]);

        // Амжилттай бүртгүүлсний дараа login руу шилжинэ
        redirect('/login.php?registered=1');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account - CineStream</title>

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

        .register-container {
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

        .register-card {
            background: rgba(20, 20, 20, 0.96);
            border: 1px solid #2d2d2d;
            border-radius: 16px;
            padding: 35px;

            box-shadow:
                0 25px 60px rgba(0, 0, 0, 0.55);
        }

        .register-card h1 {
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

            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus {
            border-color: #e50914;
            box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.12);
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

        .register-button {
            width: 100%;
            border: none;
            border-radius: 8px;

            padding: 14px;

            background: #e50914;
            color: white;

            font-size: 16px;
            font-weight: 700;

            cursor: pointer;

            transition: background 0.2s, transform 0.1s;
        }

        .register-button:hover {
            background: #f6121d;
        }

        .register-button:active {
            transform: scale(0.99);
        }

        .login-link {
            text-align: center;
            margin-top: 24px;

            color: #999999;
            font-size: 14px;
        }

        .login-link a {
            color: #ffffff;
            font-weight: 600;
            text-decoration: none;
        }

        .login-link a:hover {
            color: #e50914;
        }

        .password-note {
            color: #777777;
            font-size: 12px;
            margin-top: 6px;
        }

        @media (max-width: 500px) {
            body {
                padding: 15px;
            }

            .register-card {
                padding: 25px 20px;
            }

            .logo a {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>

<div class="register-container">

    <div class="logo">
        <a href="index.php">
            Cine<span>Stream</span>
        </a>
    </div>

    <div class="register-card">

        <h1>Create account</h1>

        <p class="subtitle">
            Join CineStream and start watching.
        </p>

        <?php if (!empty($errors)): ?>

            <div class="error-box">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

        <?php endif; ?>

        <form method="POST" action="register.php">

            <?= csrfField() ?>

            <div class="form-group">
                <label for="name">Full name</label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?= e($name) ?>"
                    placeholder="Enter your name"
                    maxlength="100"
                    autocomplete="name"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email</label>

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
                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Create a password"
                    minlength="8"
                    maxlength="255"
                    autocomplete="new-password"
                    required
                >

                <div class="password-note">
                    Password must contain at least 8 characters.
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm password</label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Repeat your password"
                    minlength="8"
                    maxlength="255"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button
                type="submit"
                class="register-button"
            >
                Create Account
            </button>

        </form>

        <div class="login-link">
            Already have an account?
            <a href="login.php">Sign in</a>
        </div>

    </div>

</div>

</body>
</html>