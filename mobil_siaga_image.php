<?php
require_once __DIR__ . '/includes/mobil_siaga_storage.php';
require_once __DIR__ . '/koneksi.php';

$type = $_GET['type'] ?? '';
$rawId = $_GET['id'] ?? '';
$id = is_string($rawId)
    ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if (!is_string($type) || !in_array($type, ['vehicle', 'staff'], true)
    || $id === false) {
    http_response_code(404);
    exit;
}

try {
    $id = (int) $id;
    if ($type === 'vehicle') {
        $statement = $koneksi->prepare('SELECT foto_file FROM mobil_siaga_profil WHERE id = ? LIMIT 1');
    } else {
        $statement = $koneksi->prepare('SELECT foto_file FROM mobil_siaga_petugas WHERE id = ? AND aktif = 1 LIMIT 1');
    }
    if (!$statement) {
        throw new RuntimeException('Mobil Siaga image query could not be prepared.');
    }
    if (!$statement->bind_param('i', $id) || !$statement->execute()) {
        $statement->close();
        throw new RuntimeException('Mobil Siaga image query failed.');
    }
    $statement->bind_result($fileName);
    $found = $statement->fetch();
    $statement->close();
    if ($found !== true || !is_string($fileName) || $fileName === '') {
        http_response_code(404);
        exit;
    }

    $path = mobil_siaga_storage_file_path($fileName);
    if (!is_file($path) || !is_readable($path)) {
        http_response_code(404);
        exit;
    }
    $fileSize = filesize($path);
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $allowedTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($fileSize === false || !isset($allowedTypes[$extension]) || $mimeType !== $allowedTypes[$extension]) {
        error_log('Mobil Siaga image rejected due to invalid stored MIME type.');
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $mimeType);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    header('Content-Length: ' . (string) $fileSize);
    readfile($path);
} catch (Throwable $exception) {
    error_log('Mobil Siaga image could not be served: ' . $exception->getMessage());
    http_response_code(404);
}
