<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/../potensi_storage.php';

function potensi_action_redirect(string $form = '', ?int $id = null): void
{
    $parameters = [];
    if ($form !== '') {
        $parameters['form'] = $form;
    }
    if ($id !== null) {
        $parameters['id'] = $id;
    }
    header('Location: potensi.php' . ($parameters === [] ? '' : '?' . http_build_query($parameters)));
    exit;
}

function potensi_action_text(string $field, int $limit, bool $required = false): string
{
    $value = $_POST[$field] ?? '';
    if (!is_string($value)) {
        throw new InvalidArgumentException('Data formulir Potensi tidak valid.');
    }
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $limit) {
        throw new InvalidArgumentException($required
            ? 'Kolom wajib diisi dan maksimal ' . $limit . ' karakter.'
            : 'Kolom maksimal ' . $limit . ' karakter.');
    }

    return $value;
}

function potensi_action_upload(): ?string
{
    if (!isset($_FILES['gambar']) || !is_array($_FILES['gambar'])) {
        return null;
    }

    $file = $_FILES['gambar'];
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($error) || $error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
            ? 'Ukuran gambar melebihi batas unggah.'
            : 'Gambar gagal diunggah. Pilih file lain dan coba kembali.');
    }

    $temporaryPath = $file['tmp_name'] ?? '';
    $originalName = $file['name'] ?? '';
    if (!is_string($temporaryPath) || !is_string($originalName) || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('File gambar tidak valid.');
    }

    $size = filesize($temporaryPath);
    if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran gambar maksimal 5 MB.');
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $extensions = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'];
    $mimeTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    if (!isset($extensions[$extension])) {
        throw new InvalidArgumentException('Format gambar yang diizinkan hanya JPEG, PNG, dan WebP.');
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $imageInfo = @getimagesize($temporaryPath);
    $storedExtension = $extensions[$extension];
    if ($mimeType !== $mimeTypes[$storedExtension]
        || !is_array($imageInfo)
        || ($imageInfo['mime'] ?? '') !== $mimeTypes[$storedExtension]) {
        throw new InvalidArgumentException('Isi file tidak sesuai dengan format gambar yang diizinkan.');
    }
    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);
    if ($width < 1 || $height < 1 || $width * $height > 40000000) {
        throw new InvalidArgumentException('Dimensi gambar tidak valid atau terlalu besar.');
    }

    $directory = potensi_storage_ensure_directory();
    $fileName = bin2hex(random_bytes(16)) . '.' . $storedExtension;
    if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $fileName)) {
        throw new RuntimeException('Gambar gagal disimpan.');
    }

    return $fileName;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Aksi Potensi hanya menerima metode POST.');
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !hash_equals(admin_csrf_token(), $token)) {
    admin_module_flash('potensi_flash', 'error', 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
    potensi_action_redirect();
}

$action = $_POST['action'] ?? '';
if (!is_string($action) || !in_array($action, ['create', 'update', 'delete'], true)) {
    admin_module_flash('potensi_flash', 'error', 'Aksi Potensi tidak valid.');
    potensi_action_redirect();
}

$rawId = $_POST['id'] ?? '';
$id = null;
if ($action !== 'create') {
    if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
        admin_module_flash('potensi_flash', 'error', 'ID potensi tidak valid.');
        potensi_action_redirect();
    }
    $id = (int) $rawId;
}

$storedNewFile = null;
try {
    if (admin_module_missing_tables($koneksi, ['potensi_desa']) !== []) {
        admin_module_flash('potensi_flash', 'error', 'Tabel Potensi Desa belum tersedia. Jalankan SQL migrasi terlebih dahulu.');
        potensi_action_redirect();
    }
    $priceColumn = admin_module_execute(
        $koneksi,
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        'ss',
        ['potensi_desa', 'harga']
    );
    $priceColumn->bind_result($priceColumnCount);
    $priceColumn->fetch();
    $priceColumn->close();
    if ((int) $priceColumnCount !== 1) {
        admin_module_flash('potensi_flash', 'error', 'Kolom harga belum tersedia. Jalankan database/potensi_harga.sql satu kali pada database db_desa_pemulihan.');
        potensi_action_redirect();
    }

    if ($action === 'delete') {
        $lookup = admin_module_execute($koneksi, 'SELECT nama_file FROM potensi_desa WHERE id = ?', 'i', [$id]);
        $record = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if (!$record) {
            admin_module_flash('potensi_flash', 'error', 'Data potensi tidak ditemukan.');
            potensi_action_redirect();
        }

        $delete = admin_module_execute($koneksi, 'DELETE FROM potensi_desa WHERE id = ?', 'i', [$id]);
        $deleted = $delete->affected_rows === 1;
        $delete->close();
        if (!$deleted) {
            throw new RuntimeException('Data Potensi gagal dihapus dari database.');
        }

        if (is_string($record['nama_file'] ?? null) && $record['nama_file'] !== '') {
            try {
                if (!potensi_storage_delete_file((string) $record['nama_file'])) {
                    throw new RuntimeException('File Potensi lama tidak dapat dibersihkan.');
                }
            } catch (Throwable $exception) {
                error_log('Potensi image cleanup failed after row removal: ' . $exception->getMessage());
                admin_module_flash('potensi_flash', 'error', 'Data potensi dihapus, tetapi file gambar perlu dibersihkan administrator.');
                potensi_action_redirect();
            }
        }

        admin_module_flash('potensi_flash', 'success', 'Data potensi berhasil dihapus.');
        potensi_action_redirect();
    }

    $category = potensi_action_text('kategori', 100, true);
    $title = potensi_action_text('judul', 150, true);
    $price = potensi_action_text('harga', 15);
    if ($price !== '' && (!ctype_digit($price) || (float) $price > 999999999999999)) {
        throw new InvalidArgumentException('Harga harus berupa angka bulat antara 0 dan 999.999.999.999.999.');
    }
    $description = potensi_action_text('deskripsi', 5000);
    $storedNewFile = potensi_action_upload();

    if ($action === 'create') {
        $insert = admin_module_execute(
            $koneksi,
            'INSERT INTO potensi_desa (kategori, judul, harga, deskripsi, nama_file) VALUES (?, ?, ?, ?, ?)',
            'sssss',
            [$category, $title, $price === '' ? null : $price, $description === '' ? null : $description, $storedNewFile]
        );
        $insert->close();
        $storedNewFile = null;
        admin_module_flash('potensi_flash', 'success', 'Data potensi berhasil ditambahkan.');
        potensi_action_redirect();
    }

    $lookup = admin_module_execute($koneksi, 'SELECT nama_file FROM potensi_desa WHERE id = ?', 'i', [$id]);
    $record = $lookup->get_result()->fetch_assoc();
    $lookup->close();
    if (!$record) {
        throw new InvalidArgumentException('Data potensi tidak ditemukan.');
    }

    $oldFile = is_string($record['nama_file'] ?? null) ? $record['nama_file'] : '';
    if ($storedNewFile !== null) {
        $update = admin_module_execute(
            $koneksi,
            'UPDATE potensi_desa SET kategori = ?, judul = ?, harga = ?, deskripsi = ?, nama_file = ? WHERE id = ?',
            'sssssi',
            [$category, $title, $price === '' ? null : $price, $description === '' ? null : $description, $storedNewFile, $id]
        );
    } else {
        $update = admin_module_execute(
            $koneksi,
            'UPDATE potensi_desa SET kategori = ?, judul = ?, harga = ?, deskripsi = ? WHERE id = ?',
            'ssssi',
            [$category, $title, $price === '' ? null : $price, $description === '' ? null : $description, $id]
        );
    }
    $update->close();

    if ($storedNewFile !== null) {
        $storedNewFile = null;
        if ($oldFile !== '') {
            try {
                if (!potensi_storage_delete_file($oldFile)) {
                    throw new RuntimeException('File Potensi lama tidak dapat dibersihkan.');
                }
            } catch (Throwable $exception) {
                error_log('Old Potensi image cleanup failed after update: ' . $exception->getMessage());
                admin_module_flash('potensi_flash', 'error', 'Data diperbarui, tetapi file gambar lama perlu dibersihkan administrator.');
                potensi_action_redirect();
            }
        }
    }
    admin_module_flash('potensi_flash', 'success', 'Data potensi berhasil diperbarui.');
    potensi_action_redirect();
} catch (InvalidArgumentException $exception) {
    if ($storedNewFile !== null) {
        try {
            potensi_storage_delete_file($storedNewFile);
        } catch (Throwable $cleanupException) {
            error_log('Temporary Potensi image cleanup failed: ' . $cleanupException->getMessage());
        }
    }
    if ($action === 'create' || $action === 'update') {
        admin_module_store_old_input('potensi_old_input', $_POST, [
            'kategori' => 100,
            'judul' => 150,
            'harga' => 15,
            'deskripsi' => 5000,
        ]);
    }
    admin_module_flash('potensi_flash', 'error', $exception->getMessage());
    potensi_action_redirect($action === 'create' ? 'tambah' : ($action === 'update' ? 'edit' : ''), $id);
} catch (Throwable $exception) {
    if ($storedNewFile !== null) {
        try {
            potensi_storage_delete_file($storedNewFile);
        } catch (Throwable $cleanupException) {
            error_log('Temporary Potensi image cleanup failed: ' . $cleanupException->getMessage());
        }
    }
    error_log('Admin Potensi action failed: ' . $exception->getMessage());
    admin_module_flash('potensi_flash', 'error', 'Perubahan Potensi gagal diproses. Silakan coba kembali.');
    potensi_action_redirect($action === 'create' ? 'tambah' : ($action === 'update' ? 'edit' : ''), $id);
}