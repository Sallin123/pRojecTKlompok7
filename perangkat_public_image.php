<?php
require_once __DIR__ . '/perangkat_storage.php';
require_once __DIR__ . '/koneksi.php';

$rawId = $_GET['id'] ?? '';
if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
    http_response_code(404);
    exit;
}

try {
    $id = (int) $rawId;
    $statement = $koneksi->prepare('SELECT foto FROM perangkat_desa WHERE id = ? AND status_data = \'aktif\'');
    if (!$statement) {
        throw new RuntimeException('Public perangkat image query could not be prepared.');
    }
    if (!$statement->bind_param('i', $id) || !$statement->execute()) {
        if ($statement instanceof mysqli_stmt) {
            $statement->close();
        }
        throw new RuntimeException('Public perangkat image query failed.');
    }

    $statement->bind_result($fileName);
    $found = $statement->fetch();
    $statement->close();
    if ($found !== true || !is_string($fileName) || $fileName === '') {
        http_response_code(404);
        exit;
    }

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
        error_log('Public perangkat image rejected due to invalid stored MIME type.');
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $mimeType);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    header('Content-Length: ' . (string) $fileSize);
    readfile($path);
} catch (Throwable $exception) {
    error_log('Public perangkat image could not be served: ' . $exception->getMessage());
    http_response_code(404);
}