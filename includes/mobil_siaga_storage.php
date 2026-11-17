<?php

function mobil_siaga_storage_directory(): string
{
    return dirname(__DIR__, 3)
        . DIRECTORY_SEPARATOR . 'private_uploads'
        . DIRECTORY_SEPARATOR . 'pRojecTKlompok7'
        . DIRECTORY_SEPARATOR . 'mobil_siaga';
}

function mobil_siaga_storage_ensure_directory(): string
{
    $directory = mobil_siaga_storage_directory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Direktori penyimpanan foto Mobil Siaga tidak tersedia.');
    }

    return mobil_siaga_storage_validate_directory($directory);
}

function mobil_siaga_storage_validate_directory(string $directory): string
{
    $realDirectory = realpath($directory);
    if ($realDirectory === false) {
        throw new RuntimeException('Direktori foto Mobil Siaga tidak dapat diverifikasi.');
    }

    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if ($documentRoot !== false) {
        $normalizedDirectory = strtolower(str_replace('\\', '/', $realDirectory));
        $normalizedDocumentRoot = rtrim(strtolower(str_replace('\\', '/', $documentRoot)), '/') . '/';
        if (strpos($normalizedDirectory . '/', $normalizedDocumentRoot) === 0) {
            throw new RuntimeException('Foto Mobil Siaga harus disimpan di luar web root.');
        }
    }

    return $realDirectory;
}

function mobil_siaga_storage_file_path(string $fileName): string
{
    if (!preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $fileName)) {
        throw new InvalidArgumentException('Nama file foto Mobil Siaga tidak valid.');
    }

    $directory = mobil_siaga_storage_validate_directory(mobil_siaga_storage_directory());
    $path = $directory . DIRECTORY_SEPARATOR . $fileName;
    if (is_file($path)) {
        $realPath = realpath($path);
        if ($realPath === false || dirname($realPath) !== $directory) {
            throw new RuntimeException('Lokasi foto Mobil Siaga tidak dapat diverifikasi.');
        }

        return $realPath;
    }

    return $path;
}

function mobil_siaga_storage_delete_file(string $fileName): bool
{
    $path = mobil_siaga_storage_file_path($fileName);
    return !is_file($path) || unlink($path);
}
