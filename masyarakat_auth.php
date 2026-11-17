<?php

$masyarakatSessionName = 'PEMAYAHAN_MASYARAKAT';
if (session_status() === PHP_SESSION_ACTIVE && session_name() !== $masyarakatSessionName) {
    throw new RuntimeException('Community authentication cannot share an active administrator session.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower($_SERVER['HTTPS']) !== 'off';
    session_name($masyarakatSessionName);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function masyarakat_csrf_token(): string
{
    if (!isset($_SESSION['masyarakat_csrf_token']) || !is_string($_SESSION['masyarakat_csrf_token'])) {
        $_SESSION['masyarakat_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['masyarakat_csrf_token'];
}

function masyarakat_csrf_is_valid($token): bool
{
    return is_string($token)
        && isset($_SESSION['masyarakat_csrf_token'])
        && is_string($_SESSION['masyarakat_csrf_token'])
        && hash_equals($_SESSION['masyarakat_csrf_token'], $token);
}

function masyarakat_is_authenticated(): bool
{
    return isset($_SESSION['masyarakat_account_id'])
        && is_int($_SESSION['masyarakat_account_id'])
        && $_SESSION['masyarakat_account_id'] > 0;
}

function masyarakat_safe_return_path($candidate): ?string
{
    if (!is_string($candidate) || $candidate === '' || preg_match('/[\r\n]/', $candidate) === 1) {
        return null;
    }

    $parts = parse_url($candidate);
    if (!is_array($parts) || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }

    $allowedPages = [
        'index.php',
        'profil.php',
        'berita.php',
        'potensi.php',
        'galeri.php',
        'bansos.php',
        'surat.php',
        'aduan.php',
        'mobil_siaga.php',
        'mobil_siaga_lacak.php',
        'posyandu.php',
        'riwayat_surat.php',
    ];
    $page = basename($parts['path'] ?? '');
    if (!in_array($page, $allowedPages, true)) {
        return null;
    }

    $returnPath = $page;
    $query = $parts['query'] ?? '';
    if ($query !== '' && strlen($query) <= 1000) {
        $returnPath .= '?' . $query;
    }

    $fragment = $parts['fragment'] ?? '';
    if ($fragment !== '' && preg_match('/\A[A-Za-z0-9._~-]{1,100}\z/', $fragment) === 1) {
        $returnPath .= '#' . $fragment;
    }

    return $returnPath;
}

function masyarakat_require_authentication(?string $returnTo = null): void
{
    if (masyarakat_is_authenticated()) {
        return;
    }

    $requestedPath = $returnTo ?? ($_SERVER['REQUEST_URI'] ?? '');
    $safeReturnPath = masyarakat_safe_return_path($requestedPath) ?? 'index.php';
    header('Location: masyarakat_login.php?next=' . rawurlencode($safeReturnPath));
    exit;
}

function masyarakat_establish_session(int $accountId): void
{
    session_regenerate_id(true);
    $_SESSION['masyarakat_account_id'] = $accountId;
    unset($_SESSION['masyarakat_csrf_token']);
    masyarakat_csrf_token();
}

function masyarakat_current_account(mysqli $connection): ?array
{
    if (!masyarakat_is_authenticated()) {
        return null;
    }

    $statement = $connection->prepare(
        'SELECT id, email, status FROM akun_masyarakat WHERE id = ? LIMIT 1'
    );
    if (!$statement) {
        throw new RuntimeException('Account lookup could not be prepared.');
    }

    $accountId = $_SESSION['masyarakat_account_id'];
    if (!$statement->bind_param('i', $accountId) || !$statement->execute()) {
        $statement->close();
        throw new RuntimeException('Account lookup could not be executed.');
    }

    if (!$statement->bind_result($id, $email, $status)) {
        $statement->close();
        throw new RuntimeException('Account lookup results could not be bound.');
    }

    $found = $statement->fetch();
    $statement->close();
    if ($found !== true) {
        unset($_SESSION['masyarakat_account_id']);
        return null;
    }

    return [
        'id' => (int) $id,
        'email' => (string) $email,
        'status' => (string) $status,
    ];
}

function masyarakat_is_approved(mysqli $connection): bool
{
    $account = masyarakat_current_account($connection);

    return $account !== null && $account['status'] === 'approved';
}
