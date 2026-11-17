<?php
require_once __DIR__ . '/berita_storage.php';
require_once __DIR__ . '/koneksi.php';

$rawId = $_GET['id'] ?? '';
if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
    http_response_code(404);
    exit;
}

try {
    $statement = $koneksi->prepare('SELECT gambar FROM berita WHERE id = ?');
    if (!$statement) {
        throw new RuntimeException('Berita image query could not be prepared.');
    }

    $id = (int) $rawId;
    $statement->bind_param('i', $id);
    if (!$statement->execute()) {
        $statement->close();
        throw new RuntimeException('Berita image query could not be executed.');
    }

    $result = $statement->get_result();
    $record = $result ? $result->fetch_assoc() : null;
    $statement->close();
    if (!$record || !is_string($record['gambar']) || $record['gambar'] === '') {
        http_response_code(404);
        exit;
    }

    $path = berita_storage_file_path($record['gambar']);
    if (!is_file($path) || !is_readable($path)) {
        http_response_code(404);
        exit;
    }

    $fileSize = filesize($path);
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $allowedTypes = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    $extension = strtolower(pathinfo($record['gambar'], PATHINFO_EXTENSION));
    if ($fileSize === false || !isset($allowedTypes[$extension]) || $mimeType !== $allowedTypes[$extension]) {
        error_log('Berita image rejected due to invalid stored MIME type.');
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $mimeType);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=300');
    header('Content-Length: ' . (string) $fileSize);
    readfile($path);
} catch (Throwable $exception) {
    error_log('Berita image could not be served: ' . $exception->getMessage());
    http_response_code(404);
}
