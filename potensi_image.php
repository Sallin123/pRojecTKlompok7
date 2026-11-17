<?php
require_once __DIR__ . '/potensi_storage.php';
require_once __DIR__ . '/koneksi.php';

$adminPreview = isset($_GET['preview']) && $_GET['preview'] === 'admin';
if ($adminPreview) {
    require_once __DIR__ . '/admin/auth.php';
    admin_require_authentication();
}

$rawId = $_GET['id'] ?? '';
if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
    http_response_code(404);
    exit;
}

try {
    $statement = $koneksi->prepare('SELECT nama_file FROM potensi_desa WHERE id = ?');
    if (!$statement) {
        throw new RuntimeException('Potensi image query could not be prepared.');
    }
    $id = (int) $rawId;
    $statement->bind_param('i', $id);
    if (!$statement->execute()) {
        $statement->close();
        throw new RuntimeException('Potensi image query could not be executed.');
    }
    $result = $statement->get_result();
    $record = $result ? $result->fetch_assoc() : null;
    $statement->close();

    if ($record === null || !is_string($record['nama_file'] ?? null) || $record['nama_file'] === '') {
        http_response_code(404);
        exit;
    }

    $fileName = (string) $record['nama_file'];
    $path = potensi_storage_file_path($fileName);
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }
    $fileSize = filesize($path);
    if (!is_readable($path) || $fileSize === false) {
        http_response_code(404);
        exit;
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $allowedTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!isset($allowedTypes[$extension]) || $mimeType !== $allowedTypes[$extension]) {
        error_log('Potensi image rejected due to invalid stored MIME type.');
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $mimeType);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: ' . ($adminPreview ? 'private, no-store' : 'public, max-age=300'));
    header('Content-Length: ' . (string) $fileSize);
    readfile($path);
} catch (Throwable $exception) {
    error_log('Potensi image could not be served: ' . $exception->getMessage());
    http_response_code(404);
}