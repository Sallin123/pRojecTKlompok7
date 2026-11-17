<?php

function potensi_storage_directory(): string
{
    return dirname(__DIR__, 2)
        . DIRECTORY_SEPARATOR . 'private_uploads'
        . DIRECTORY_SEPARATOR . 'pRojecTKlompok7'
        . DIRECTORY_SEPARATOR . 'potensi';
}

function potensi_storage_ensure_directory(): string
{
    $directory = potensi_storage_directory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Direktori penyimpanan Potensi tidak tersedia.');
    }

    return potensi_storage_validate_directory($directory);
}

function potensi_storage_validate_directory(string $directory): string
{
    $realDirectory = realpath($directory);
    if ($realDirectory === false) {
        throw new RuntimeException('Direktori penyimpanan Potensi tidak dapat diverifikasi.');
    }

    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if ($documentRoot !== false) {
        $normalizedDirectory = strtolower(str_replace('\\', '/', $realDirectory));
        $normalizedDocumentRoot = rtrim(strtolower(str_replace('\\', '/', $documentRoot)), '/') . '/';
        if (strpos($normalizedDirectory . '/', $normalizedDocumentRoot) === 0) {
            throw new RuntimeException('Direktori penyimpanan Potensi harus berada di luar web root.');
        }
    }

    return $realDirectory;
}

function potensi_storage_file_path(string $fileName): string
{
    if (!preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $fileName)) {
        throw new InvalidArgumentException('Nama file Potensi tidak valid.');
    }

    $directory = potensi_storage_validate_directory(potensi_storage_directory());
    $path = $directory . DIRECTORY_SEPARATOR . $fileName;
    if (is_file($path)) {
        $realPath = realpath($path);
        if ($realPath === false || dirname($realPath) !== $directory) {
            throw new RuntimeException('Lokasi file Potensi tidak dapat diverifikasi.');
        }

        return $realPath;
    }

    return $path;
}

function potensi_storage_delete_file(string $fileName): bool
{
    $path = potensi_storage_file_path($fileName);
    return !is_file($path) || unlink($path);
}