<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Logout зөвхөн POST хүсэлтээр хийгдэнэ
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('405 Method Not Allowed');
}

// CSRF шалгах
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('403 Forbidden - Invalid CSRF token.');
}

// Session-ийг устгах
logout();

// Home руу буцаах
redirect('/index.php');