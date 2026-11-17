<?php

function galeri_storage_directory(): string
{
    return dirname(__DIR__, 2)
        . DIRECTORY_SEPARATOR . 'private_uploads'
        . DIRECTORY_SEPARATOR . 'pRojecTKlompok7'
        . DIRECTORY_SEPARATOR . 'galeri';
}

function galeri_storage_ensure_directory(): string
{
    $directory = galeri_storage_directory();
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Direktori penyimpanan Galeri tidak tersedia.');
    }

    return galeri_storage_validate_directory($directory);
}

function galeri_storage_validate_directory(string $directory): string
{
    $realDirectory = realpath($directory);
    if ($realDirectory === false) {
        return $directory;
    }

    return $realDirectory;
}

function galeri_storage_file_path(string $fileName): string
{
    if (!preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $fileName)) {
        throw new InvalidArgumentException('Nama file Galeri tidak valid.');
    }

    $directory = galeri_storage_validate_directory(galeri_storage_directory());
    $path = $directory . DIRECTORY_SEPARATOR . $fileName;

    if (is_file($path)) {
        $realPath = realpath($path);
        if ($realPath !== false) {
            return $realPath;
        }
    }

    return $path;
}

function galeri_storage_delete_file(string $fileName): bool
{
    $path = galeri_storage_file_path($fileName);
    return !is_file($path) || unlink($path);
}
