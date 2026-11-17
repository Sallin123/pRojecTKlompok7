<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_desa_pemulihan";

try {
    $koneksi = mysqli_connect($host, $user, $pass, $db);
    if (!$koneksi) {
        throw new RuntimeException(mysqli_connect_error());
    }

    if (!mysqli_set_charset($koneksi, 'utf8mb4')) {
        throw new RuntimeException(mysqli_error($koneksi));
    }
} catch (Throwable $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    if (defined('DB_CONNECTION_OPTIONAL') && DB_CONNECTION_OPTIONAL === true) {
        $koneksi = null;
        return;
    }

    http_response_code(500);
    exit('Layanan database sedang tidak tersedia. Silakan coba kembali nanti.');
}
?>