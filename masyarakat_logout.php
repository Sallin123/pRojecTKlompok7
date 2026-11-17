<?php
require_once __DIR__ . '/masyarakat_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Metode permintaan tidak diizinkan.');
}

if (!masyarakat_csrf_is_valid($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    exit('Permintaan logout tidak valid. Muat ulang halaman dan coba lagi.');
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookieParams = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => $cookieParams['secure'],
        'httponly' => $cookieParams['httponly'],
        'samesite' => $cookieParams['samesite'] ?? 'Lax',
    ]);
}

session_destroy();
header('Location: masyarakat_login.php');
exit;
