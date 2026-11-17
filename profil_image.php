<?php
require_once __DIR__ . '/profil_storage.php';
require_once __DIR__ . '/koneksi.php';

try {
    $statement = $koneksi->prepare('SELECT foto_kepala_desa FROM profil_desa WHERE id = 1');
    if (!$statement || !$statement->execute()) {
        if ($statement instanceof mysqli_stmt) {
            $statement->close();
        }
        throw new RuntimeException('Profile image query failed.');
    }

    $result = $statement->get_result();
    $record = $result ? $result->fetch_assoc() : null;
    $statement->close();
    if (!$record || !is_string($record['foto_kepala_desa']) || $record['foto_kepala_desa'] === '') {
        http_response_code(404);
        exit;
    }

    $fileName = $record['foto_kepala_desa'];
    $path = profil_storage_file_path($fileName);
    if (!is_file($path) || !is_readable($path)) {
        http_response_code(404);
        exit;
    }

    $fileSize = filesize($path);
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $allowedTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($fileSize === false || !isset($allowedTypes[$extension]) || $mimeType !== $allowedTypes[$extension]) {
        error_log('Profile image rejected due to invalid stored MIME type.');
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $mimeType);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=300');
    header('Content-Length: ' . (string) $fileSize);
    readfile($path);
} catch (Throwable $exception) {
    error_log('Profile image could not be served: ' . $exception->getMessage());
    http_response_code(404);
}
