<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/../galeri_storage.php';

function galeri_action_redirect(string $form = '', ?int $id = null): void
{
    $parameters = [];
    if ($form !== '') {
        $parameters['form'] = $form;
    }
    if ($id !== null) {
        $parameters['id'] = $id;
    }
    header('Location: galeri.php' . ($parameters === [] ? '' : '?' . http_build_query($parameters)));
    exit;
}

function galeri_action_text(string $field, int $limit, bool $required = false): string
{
    $value = $_POST[$field] ?? '';
    if (!is_string($value)) {
        throw new InvalidArgumentException('Data formulir Galeri tidak valid.');
    }
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $limit) {
        throw new InvalidArgumentException($required
            ? 'Kolom wajib diisi dan tidak boleh melebihi ' . $limit . ' karakter.'
            : 'Kolom tidak boleh melebihi ' . $limit . ' karakter.');
    }

    return $value;
}

function galeri_action_upload(bool $required): ?string
{
    if (!isset($_FILES['foto']) || !is_array($_FILES['foto'])) {
        if ($required) {
            throw new InvalidArgumentException('Pilih foto untuk diunggah.');
        }
        return null;
    }

    $file = $_FILES['foto'];
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($error) || $error === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            throw new InvalidArgumentException('Pilih foto untuk diunggah.');
        }
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        $message = $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
            ? 'Ukuran foto melebihi batas unggah.'
            : 'Foto gagal diunggah. Pilih file lain dan coba kembali.';
        throw new InvalidArgumentException($message);
    }

    $temporaryPath = $file['tmp_name'] ?? '';
    $originalName = $file['name'] ?? '';
    if (!is_string($temporaryPath) || !is_string($originalName) || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('File unggahan tidak valid.');
    }

    $size = filesize($temporaryPath);
    if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran foto maksimal 5 MB.');
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $extensions = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'];
    $mimeTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    if (!isset($extensions[$extension])) {
        throw new InvalidArgumentException('Format foto yang diizinkan hanya JPEG, PNG, dan WebP.');
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
        throw new InvalidArgumentException('Dimensi foto tidak valid atau terlalu besar.');
    }

    $directory = galeri_storage_ensure_directory();
    $fileName = bin2hex(random_bytes(16)) . '.' . $storedExtension;
    $destination = $directory . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('Foto gagal disimpan.');
    }
    return $fileName;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Aksi Galeri hanya menerima metode POST.');
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !hash_equals(admin_csrf_token(), $token)) {
    admin_module_flash('galeri_flash', 'error', 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
    galeri_action_redirect();
}

$action = $_POST['action'] ?? '';
if (!is_string($action) || !in_array($action, ['create', 'update', 'delete'], true)) {
    admin_module_flash('galeri_flash', 'error', 'Aksi Galeri tidak valid.');
    galeri_action_redirect();
}

$rawId = $_POST['id'] ?? '';
$id = null;
if ($action !== 'create') {
    if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
        admin_module_flash('galeri_flash', 'error', 'ID foto tidak valid.');
        galeri_action_redirect();
    }
    $id = (int) $rawId;
}

$storedNewFile = null;
try {
    if (admin_module_missing_tables($koneksi, ['galeri']) !== []) {
        admin_module_flash('galeri_flash', 'error', 'Tabel Galeri belum tersedia. Modul belum dapat menyimpan data.');
        galeri_action_redirect();
    }

    if ($action === 'delete') {
        $lookup = admin_module_execute($koneksi, 'SELECT nama_file FROM galeri WHERE id = ?', 'i', [$id]);
        $record = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if (!$record) {
            admin_module_flash('galeri_flash', 'error', 'Foto Galeri tidak ditemukan.');
            galeri_action_redirect();
        }

        $delete = admin_module_execute($koneksi, 'DELETE FROM galeri WHERE id = ?', 'i', [$id]);
        $deleted = $delete->affected_rows === 1;
        $delete->close();
        if (!$deleted) {
            throw new RuntimeException('Foto Galeri gagal dihapus dari database.');
        }

        try {
            if (!galeri_storage_delete_file((string) $record['nama_file'])) {
                throw new RuntimeException('Foto Galeri tidak dapat dibersihkan dari penyimpanan.');
            }
            admin_module_flash('galeri_flash', 'success', 'Foto Galeri berhasil dihapus.');
        } catch (Throwable $exception) {
            error_log('Galeri photo cleanup failed after row removal: ' . $exception->getMessage());
            admin_module_flash('galeri_flash', 'error', 'Data foto telah dihapus, tetapi file perlu dibersihkan oleh administrator.');
        }
        galeri_action_redirect();
    }

    $title = galeri_action_text('judul', 150, true);
    $description = galeri_action_text('keterangan', 5000);
    $category = galeri_action_text('kategori', 100, true);
    $date = galeri_action_text('tanggal_dokumentasi', 10, true);
    $status = $_POST['status_publikasi'] ?? '';
    if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)
        || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
        throw new InvalidArgumentException('Tanggal dokumentasi tidak valid.');
    }
    if (!is_string($status) || !in_array($status, ['draft', 'publikasi'], true)) {
        throw new InvalidArgumentException('Status publikasi tidak valid.');
    }

    $storedNewFile = galeri_action_upload($action === 'create');
    if ($action === 'create') {
        $adminLookup = admin_module_execute($koneksi, 'SELECT id FROM admin WHERE username = ? LIMIT 1', 's', [$_SESSION['admin_username']]);
        $adminResult = $adminLookup->get_result();
        $adminId = $adminResult ? $adminResult->fetch_assoc() : null;
        $adminLookup->close();
        $adminId = $adminId ? (int) $adminId['id'] : null;

        $insert = admin_module_execute(
            $koneksi,
            'INSERT INTO galeri (judul, keterangan, kategori, nama_file, tanggal_dokumentasi, status_publikasi, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
            'ssssssi',
            [$title, $description === '' ? null : $description, $category, $storedNewFile, $date, $status, $adminId]
        );
        $insert->close();
        $storedNewFile = null;
        admin_module_flash('galeri_flash', 'success', 'Foto berhasil ditambahkan ke Galeri.');
        galeri_action_redirect();
    }

    $lookup = admin_module_execute($koneksi, 'SELECT nama_file FROM galeri WHERE id = ?', 'i', [$id]);
    $record = $lookup->get_result()->fetch_assoc();
    $lookup->close();
    if (!$record) {
        throw new InvalidArgumentException('Foto Galeri tidak ditemukan.');
    }

    $oldFile = (string) $record['nama_file'];
    if ($storedNewFile !== null) {
        $update = admin_module_execute(
            $koneksi,
            'UPDATE galeri SET judul = ?, keterangan = ?, kategori = ?, nama_file = ?, tanggal_dokumentasi = ?, status_publikasi = ? WHERE id = ?',
            'ssssssi',
            [$title, $description === '' ? null : $description, $category, $storedNewFile, $date, $status, $id]
        );
    } else {
        $update = admin_module_execute(
            $koneksi,
            'UPDATE galeri SET judul = ?, keterangan = ?, kategori = ?, tanggal_dokumentasi = ?, status_publikasi = ? WHERE id = ?',
            'sssssi',
            [$title, $description === '' ? null : $description, $category, $date, $status, $id]
        );
    }
    $update->close();

    if ($storedNewFile !== null) {
        $storedNewFile = null;
        try {
            if (!galeri_storage_delete_file($oldFile)) {
                throw new RuntimeException('File Galeri lama tidak dapat dibersihkan.');
            }
        } catch (Throwable $exception) {
            error_log('Old Galeri photo cleanup failed after update: ' . $exception->getMessage());
            admin_module_flash('galeri_flash', 'error', 'Foto diperbarui, tetapi file lama perlu dibersihkan oleh administrator.');
            galeri_action_redirect();
        }
    }
    admin_module_flash('galeri_flash', 'success', 'Informasi foto berhasil diperbarui.');
    galeri_action_redirect();
} catch (InvalidArgumentException $exception) {
    if ($storedNewFile !== null) {
        try {
            galeri_storage_delete_file($storedNewFile);
        } catch (Throwable $cleanupException) {
            error_log('Temporary Galeri photo cleanup failed: ' . $cleanupException->getMessage());
        }
    }
    if ($action === 'create' || $action === 'update') {
        admin_module_store_old_input('galeri_old_input', $_POST, [
            'judul' => 150,
            'keterangan' => 5000,
            'kategori' => 100,
            'tanggal_dokumentasi' => 10,
            'status_publikasi' => 20,
        ]);
    }
    admin_module_flash('galeri_flash', 'error', $exception->getMessage());
    galeri_action_redirect($action === 'create' ? 'tambah' : ($action === 'update' ? 'edit' : ''), $id);
} catch (Throwable $exception) {
    if ($storedNewFile !== null) {
        try {
            galeri_storage_delete_file($storedNewFile);
        } catch (Throwable $cleanupException) {
            error_log('Temporary Galeri photo cleanup failed: ' . $cleanupException->getMessage());
        }
    }
    error_log('Admin Galeri action failed: ' . $exception->getMessage());
    admin_module_flash('galeri_flash', 'error', 'Perubahan Galeri gagal diproses. Silakan coba kembali.');
    galeri_action_redirect($action === 'create' ? 'tambah' : ($action === 'update' ? 'edit' : ''), $id);
}
