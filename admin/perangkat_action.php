<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/../perangkat_storage.php';

function perangkat_redirect(string $target = 'profil.php?section=struktur#struktur'): void
{
    header('Location: ' . $target);
    exit;
}

function perangkat_form_redirect(string $action, ?int $id): void
{
    $parameters = ['section' => 'struktur'];
    if ($action === 'create' || $action === 'update') {
        $parameters['perangkat_form'] = $action === 'create' ? 'tambah' : 'edit';
        if ($id !== null) {
            $parameters['perangkat_id'] = $id;
        }
    }

    $query = $parameters === [] ? '' : '?' . http_build_query($parameters);
    header('Location: profil.php' . $query . '#struktur');
    exit;
}

function perangkat_record_exists(mysqli $connection, int $id): bool
{
    $statement = admin_module_execute($connection, 'SELECT id FROM perangkat_desa WHERE id = ?', 'i', [$id]);
    $foundId = null;
    if (!$statement->bind_result($foundId)) {
        $statement->close();
        throw new RuntimeException('Hasil pemeriksaan data perangkat tidak dapat dibaca.');
    }

    $fetchResult = $statement->fetch();
    $statement->close();
    if ($fetchResult === false) {
        throw new RuntimeException('Hasil pemeriksaan data perangkat gagal dibaca.');
    }

    return $fetchResult === true && $foundId !== null;
}

function perangkat_upload_photo(): ?string
{
    if (!isset($_FILES['foto']) || !is_array($_FILES['foto'])) {
        return null;
    }

    $file = $_FILES['foto'];
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($error)) {
        throw new InvalidArgumentException('Status unggahan foto tidak valid.');
    }
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        $message = $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
            ? 'Ukuran foto melebihi batas unggah.'
            : 'Foto gagal diunggah. Pilih file lain dan coba kembali.';
        throw new InvalidArgumentException($message);
    }

    $temporaryPath = $file['tmp_name'] ?? '';
    if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('File unggahan tidak valid.');
    }

    $size = filesize($temporaryPath);
    if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran foto maksimal 5 MB.');
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $imageInfo = @getimagesize($temporaryPath);
    $mimeExtensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mimeType)
        || !isset($mimeExtensions[$mimeType])
        || !is_array($imageInfo)
        || ($imageInfo['mime'] ?? '') !== $mimeType) {
        throw new InvalidArgumentException('Isi file harus berupa gambar JPEG, PNG, atau WebP yang valid.');
    }

    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);
    if ($width < 1 || $height < 1 || $width * $height > 40000000) {
        throw new InvalidArgumentException('Dimensi foto tidak valid atau terlalu besar.');
    }

    $directory = perangkat_storage_ensure_directory();
    $fileName = bin2hex(random_bytes(16)) . '.' . $mimeExtensions[$mimeType];
    $destination = $directory . DIRECTORY_SEPARATOR . $fileName;
    if (file_exists($destination) || !move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('Foto perangkat gagal disimpan.');
    }

    return $fileName;
}

function perangkat_cleanup_photo_if_unused(mysqli $connection, ?string $fileName): bool
{
    if ($fileName === null || $fileName === '') {
        return true;
    }

    $usage = admin_module_execute($connection, 'SELECT COUNT(*) FROM perangkat_desa WHERE foto = ?', 's', [$fileName]);
    $count = null;
    if (!$usage->bind_result($count)) {
        $usage->close();
        throw new RuntimeException('Jumlah penggunaan foto perangkat tidak dapat dibaca.');
    }
    $fetchResult = $usage->fetch();
    $usage->close();
    if ($fetchResult === false) {
        throw new RuntimeException('Jumlah penggunaan foto perangkat gagal dibaca.');
    }

    return (int) $count !== 0 || perangkat_storage_delete_file($fileName);
}

function perangkat_cleanup_temporary_photo(?string $fileName): bool
{
    if ($fileName === null) {
        return true;
    }

    try {
        $deleted = perangkat_storage_delete_file($fileName);
        if (!$deleted) {
            error_log('Temporary Perangkat photo could not be removed.');
        }
        return $deleted;
    } catch (Throwable $exception) {
        error_log('Temporary Perangkat photo cleanup failed: ' . $exception->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Aksi perangkat desa hanya menerima metode POST.');
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !hash_equals(admin_csrf_token(), $token)) {
    admin_module_flash('perangkat_flash', 'error', 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
    perangkat_redirect();
}

$action = $_POST['action'] ?? '';
if (!is_string($action) || !in_array($action, ['create', 'update', 'archive', 'restore'], true)) {
    admin_module_flash('perangkat_flash', 'error', 'Aksi perangkat desa tidak valid.');
    perangkat_redirect();
}

$rawId = $_POST['id'] ?? '';
$id = null;
if ($action !== 'create') {
    if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
        admin_module_flash('perangkat_flash', 'error', 'ID perangkat desa tidak valid.');
        perangkat_redirect();
    }
    $id = (int) $rawId;
}

$storedNewFile = null;
try {
    $missingTables = admin_module_missing_tables($koneksi, ['perangkat_desa']);
    if ($missingTables !== []) {
        admin_module_flash('perangkat_flash', 'error', 'Tabel perangkat_desa belum tersedia. Jalankan SQL modul setelah membuat backup database.');
        perangkat_redirect();
    }

    if ($action === 'create' || $action === 'update') {
        $rawName = $_POST['nama_lengkap'] ?? '';
        $rawPosition = $_POST['jabatan'] ?? '';
        $rawInfo = $_POST['informasi'] ?? '';
        $rawOrder = $_POST['urutan'] ?? '0';
        if (!is_string($rawName) || !is_string($rawPosition) || !is_string($rawInfo) || !is_string($rawOrder)) {
            throw new InvalidArgumentException('Data formulir tidak valid.');
        }

        $name = trim($rawName);
        $position = trim($rawPosition);
        $info = trim($rawInfo);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120) {
            throw new InvalidArgumentException('Nama wajib diisi dan maksimal 120 karakter.');
        }
        if ($position === '' || mb_strlen($position, 'UTF-8') > 120) {
            throw new InvalidArgumentException('Jabatan wajib diisi dan maksimal 120 karakter.');
        }
        if (mb_strlen($info, 'UTF-8') > 2000) {
            throw new InvalidArgumentException('Informasi maksimal 2.000 karakter.');
        }
        if (!ctype_digit($rawOrder) || (int) $rawOrder > 65535) {
            throw new InvalidArgumentException('Urutan harus berupa angka antara 0 dan 65.535.');
        }
        $order = (int) $rawOrder;

        if ($action === 'create') {
            $storedNewFile = perangkat_upload_photo();
            $statement = admin_module_execute(
                $koneksi,
                'INSERT INTO perangkat_desa (nama_lengkap, jabatan, informasi, foto, urutan) VALUES (?, ?, ?, ?, ?)',
                'ssssi',
                [$name, $position, $info === '' ? null : $info, $storedNewFile, $order]
            );
            $storedNewFile = null;
            $statement->close();
            admin_module_flash('perangkat_flash', 'success', 'Data perangkat desa berhasil ditambahkan.');
        } else {
            $lookup = admin_module_execute($koneksi, 'SELECT foto FROM perangkat_desa WHERE id = ?', 'i', [$id]);
            $record = $lookup->get_result()->fetch_assoc();
            $lookup->close();
            if (!$record) {
                admin_module_flash('perangkat_flash', 'error', 'Data perangkat desa tidak ditemukan.');
                perangkat_redirect();
            }
            $oldFile = is_string($record['foto']) ? $record['foto'] : null;
            $storedNewFile = perangkat_upload_photo();
            $newFileName = $storedNewFile;

            if ($storedNewFile !== null) {
                $statement = admin_module_execute(
                    $koneksi,
                    'UPDATE perangkat_desa SET nama_lengkap = ?, jabatan = ?, informasi = ?, foto = ?, urutan = ? WHERE id = ?',
                    'ssssii',
                    [$name, $position, $info === '' ? null : $info, $storedNewFile, $order, $id]
                );
                $storedNewFile = null;
            } else {
                $statement = admin_module_execute(
                    $koneksi,
                    'UPDATE perangkat_desa SET nama_lengkap = ?, jabatan = ?, informasi = ?, urutan = ? WHERE id = ?',
                    'sssii',
                    [$name, $position, $info === '' ? null : $info, $order, $id]
                );
            }
            $affectedRows = $statement->affected_rows;
            $statement->close();
            if ($affectedRows === 0 && !perangkat_record_exists($koneksi, $id)) {
                throw new RuntimeException('Data perangkat desa tidak ditemukan saat perubahan disimpan.');
            }

            if ($newFileName !== null) {
                try {
                    if (!perangkat_cleanup_photo_if_unused($koneksi, $oldFile)) {
                        throw new RuntimeException('Foto lama tidak dapat dibersihkan.');
                    }
                } catch (Throwable $exception) {
                    error_log('Replaced Perangkat photo cleanup failed: ' . $exception->getMessage());
                    admin_module_flash('perangkat_flash', 'error', 'Data diperbarui, tetapi foto lama perlu dibersihkan oleh administrator.');
                    perangkat_redirect();
                }
            }
            admin_module_flash('perangkat_flash', 'success', 'Data perangkat desa berhasil diperbarui.');
        }
    } else {
        $status = $action === 'archive' ? 'arsip' : 'aktif';
        $statement = admin_module_execute(
            $koneksi,
            'UPDATE perangkat_desa SET status_data = ? WHERE id = ?',
            'si',
            [$status, $id]
        );
        $affectedRows = $statement->affected_rows;
        $statement->close();
        if ($affectedRows === 0 && !perangkat_record_exists($koneksi, $id)) {
            admin_module_flash('perangkat_flash', 'error', 'Data perangkat desa tidak ditemukan.');
            perangkat_redirect();
        }
        admin_module_flash(
            'perangkat_flash',
            'success',
            $action === 'archive' ? 'Data perangkat desa diarsipkan.' : 'Data perangkat desa diaktifkan kembali.'
        );
    }

    perangkat_redirect();
} catch (InvalidArgumentException $exception) {
    $temporaryPhotoCleaned = perangkat_cleanup_temporary_photo($storedNewFile);
    if ($action === 'create' || $action === 'update') {
        admin_module_store_old_input('perangkat_old_input', $_POST, [
            'nama_lengkap' => 120,
            'jabatan' => 120,
            'informasi' => 2000,
            'urutan' => 5,
        ]);
    }
    $message = $exception->getMessage();
    if (!$temporaryPhotoCleaned) {
        $message .= ' File sementara perlu dibersihkan oleh administrator.';
    }
    admin_module_flash('perangkat_flash', 'error', $message);
    perangkat_form_redirect($action, $id);
} catch (mysqli_sql_exception $exception) {
    $temporaryPhotoCleaned = perangkat_cleanup_temporary_photo($storedNewFile);
    error_log('Admin perangkat action failed: ' . $exception->getMessage());
    if ($action === 'create' || $action === 'update') {
        admin_module_store_old_input('perangkat_old_input', $_POST, [
            'nama_lengkap' => 120,
            'jabatan' => 120,
            'informasi' => 2000,
            'urutan' => 5,
        ]);
    }
    $message = 'Perubahan data perangkat desa gagal disimpan. Silakan periksa tabel dan coba lagi.';
    if (!$temporaryPhotoCleaned) {
        $message .= ' File foto sementara perlu dibersihkan oleh administrator.';
    }
    admin_module_flash('perangkat_flash', 'error', $message);
    perangkat_form_redirect($action, $id);
} catch (Throwable $exception) {
    $temporaryPhotoCleaned = perangkat_cleanup_temporary_photo($storedNewFile);
    error_log('Admin perangkat action failed: ' . $exception->getMessage());
    if ($action === 'create' || $action === 'update') {
        admin_module_store_old_input('perangkat_old_input', $_POST, [
            'nama_lengkap' => 120,
            'jabatan' => 120,
            'informasi' => 2000,
            'urutan' => 5,
        ]);
    }
    $message = 'Perubahan data perangkat desa gagal disimpan. Silakan coba lagi.';
    if (!$temporaryPhotoCleaned) {
        $message .= ' File foto sementara perlu dibersihkan oleh administrator.';
    }
    admin_module_flash('perangkat_flash', 'error', $message);
    perangkat_form_redirect($action, $id);
}
