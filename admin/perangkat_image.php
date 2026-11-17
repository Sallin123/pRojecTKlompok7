<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../perangkat_storage.php';
require_once __DIR__ . '/module_helpers.php';

$rawId = $_GET['id'] ?? '';
if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
    http_response_code(404);
    exit;
}

try {
    $id = (int) $rawId;
    $statement = admin_module_execute($koneksi, 'SELECT foto FROM perangkat_desa WHERE id = ?', 'i', [$id]);
    $result = $statement->get_result();
    $record = $result ? $result->fetch_assoc() : null;
    $statement->close();
    if (!$record || !is_string($record['foto']) || $record['foto'] === '') {
        http_response_code(404);
        exit;
    }

    $fileName = $record['foto'];
    $path = perangkat_storage_file_path($fileName);
    if (!is_file($path) || !is_readable($path)) {
        http_response_code(404);
        exit;
    }

    $fileSize = filesize($path);
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $allowedTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($fileSize === false || !isset($allowedTypes[$extension]) || $mimeType !== $allowedTypes[$extension]) {
        error_log('Perangkat image rejected due to invalid stored MIME type.');
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $mimeType);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Length: ' . (string) $fileSize);
    readfile($path);
} catch (Throwable $exception) {
    error_log('Perangkat image could not be served: ' . $exception->getMessage());
    http_response_code(404);
}
