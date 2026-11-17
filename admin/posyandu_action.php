<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/../galeri_storage.php';

function posyandu_action_redirect(string $anchor = 'profil', array $parameters = []): void
{
    $query = $parameters === [] ? '' : '?' . http_build_query($parameters);
    header('Location: posyandu.php' . $query . '#' . rawurlencode($anchor), true, 303);
    exit;
}

function posyandu_action_text(string $field, int $limit, bool $required = false): string
{
    $value = $_POST[$field] ?? '';
    if (!is_string($value)) {
        throw new InvalidArgumentException('Data formulir Posyandu tidak valid.');
    }

    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $limit) {
        throw new InvalidArgumentException($required
            ? 'Kolom wajib diisi dan maksimal ' . $limit . ' karakter.'
            : 'Kolom maksimal ' . $limit . ' karakter.');
    }

    return $value;
}

function posyandu_action_id(string $field): int
{
    $value = $_POST[$field] ?? '';
    if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
        throw new InvalidArgumentException('ID data Posyandu tidak valid.');
    }

    return (int) $value;
}

function posyandu_action_date(string $value): string
{
    if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1
        || !checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
        throw new InvalidArgumentException('Tanggal kegiatan tidak valid.');
    }

    return $value;
}

function posyandu_action_time(string $field): ?string
{
    $value = $_POST[$field] ?? '';
    if (!is_string($value)) {
        throw new InvalidArgumentException('Waktu kegiatan tidak valid.');
    }
    if ($value === '') {
        return null;
    }
    if (preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $value) !== 1) {
        throw new InvalidArgumentException('Format waktu harus menggunakan jam dan menit yang valid.');
    }

    return $value . ':00';
}

function posyandu_action_upload_photo(): string
{
    if (!isset($_FILES['foto']) || !is_array($_FILES['foto'])) {
        throw new InvalidArgumentException('Pilih foto dokumentasi untuk diunggah.');
    }

    $file = $_FILES['foto'];
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($error) || $error === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException('Pilih foto dokumentasi untuk diunggah.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
            ? 'Ukuran foto melebihi batas unggah.'
            : 'Foto gagal diunggah. Pilih file lain dan coba kembali.');
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

    $storedExtension = $extensions[$extension];
    $expectedMime = $mimeTypes[$storedExtension];
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $imageInfo = @getimagesize($temporaryPath);
    if ($mimeType !== $expectedMime || !is_array($imageInfo) || ($imageInfo['mime'] ?? '') !== $expectedMime) {
        throw new InvalidArgumentException('Isi file tidak sesuai dengan format gambar yang diizinkan.');
    }
    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);
    if ($width < 1 || $height < 1 || $width * $height > 40000000) {
        throw new InvalidArgumentException('Dimensi foto tidak valid atau terlalu besar.');
    }

    $directory = galeri_storage_ensure_directory();
    $fileName = bin2hex(random_bytes(16)) . '.' . $storedExtension;
    if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $fileName)) {
        throw new RuntimeException('Foto dokumentasi gagal disimpan.');
    }

    return $fileName;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Aksi Posyandu hanya menerima metode POST.');
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (!is_string($submittedToken) || !hash_equals(admin_csrf_token(), $submittedToken)) {
    admin_module_flash('posyandu_flash', 'error', 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
    posyandu_action_redirect();
}

$action = $_POST['action'] ?? '';
$allowedActions = [
    'save_profile', 'delete_profile',
    'create_agenda', 'update_agenda', 'delete_agenda',
    'create_kader', 'update_kader', 'delete_kader',
    'create_education', 'update_education', 'delete_education',
    'upload_documentation', 'delete_documentation',
];
if (!is_string($action) || !in_array($action, $allowedActions, true)) {
    admin_module_flash('posyandu_flash', 'error', 'Aksi Posyandu tidak valid.');
    posyandu_action_redirect();
}

$redirectAnchor = 'profil';
$redirectParameters = [];
$newPhotoName = null;

try {
    if (admin_module_missing_tables($koneksi, ['posyandu_profil', 'posyandu_agenda']) !== []) {
        throw new RuntimeException('Struktur database Posyandu belum tersedia.');
    }

    if ($action === 'delete_profile') {
        $statement = admin_module_execute($koneksi, 'DELETE FROM posyandu_profil WHERE id = 1');
        $deleted = $statement->affected_rows === 1;
        $statement->close();
        if (!$deleted) {
            throw new InvalidArgumentException('Profil Posyandu tidak ditemukan.');
        }
        admin_module_flash('posyandu_flash', 'success', 'Profil Posyandu berhasil dihapus.');
        posyandu_action_redirect('profil');
    }

    if ($action === 'save_profile') {
        $name = posyandu_action_text('nama_posyandu', 150, true);
        $description = posyandu_action_text('deskripsi', 5000);
        $location = posyandu_action_text('lokasi', 255);
        $serviceHours = posyandu_action_text('waktu_layanan', 150);
        $statement = admin_module_execute(
            $koneksi,
            'INSERT INTO posyandu_profil (id, nama_posyandu, deskripsi, lokasi, waktu_layanan)
            VALUES (1, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE nama_posyandu = VALUES(nama_posyandu), deskripsi = VALUES(deskripsi),
                lokasi = VALUES(lokasi), waktu_layanan = VALUES(waktu_layanan)',
            'ssss',
            [$name, $description === '' ? null : $description, $location === '' ? null : $location, $serviceHours === '' ? null : $serviceHours]
        );
        $statement->close();
        admin_module_flash('posyandu_flash', 'success', 'Profil Posyandu berhasil diperbarui.');
        posyandu_action_redirect('profil');
    }

    if (in_array($action, ['create_education', 'update_education', 'delete_education'], true)) {
        $redirectAnchor = 'edukasi';
        if (admin_module_missing_tables($koneksi, ['posyandu_edukasi']) !== []) {
            throw new RuntimeException('Tabel edukasi Posyandu belum tersedia. Terapkan migrasi database/posyandu_edukasi.sql terlebih dahulu.');
        }

        if ($action === 'delete_education') {
            $id = posyandu_action_id('id');
            $statement = admin_module_execute($koneksi, 'DELETE FROM posyandu_edukasi WHERE id = ?', 'i', [$id]);
            $deleted = $statement->affected_rows === 1;
            $statement->close();
            if (!$deleted) {
                throw new InvalidArgumentException('Materi edukasi tidak ditemukan.');
            }
            admin_module_flash('posyandu_flash', 'success', 'Materi edukasi berhasil dihapus.');
            posyandu_action_redirect('edukasi');
        }

        $title = posyandu_action_text('judul', 150, true);
        $content = posyandu_action_text('konten', 10000, true);
        $status = $_POST['status_publikasi'] ?? '';
        $publicationDateText = posyandu_action_text('tanggal_publikasi', 10);
        $publicationDate = $publicationDateText === '' ? null : posyandu_action_date($publicationDateText);
        if (!is_string($status) || !in_array($status, ['draft', 'publikasi'], true)) {
            throw new InvalidArgumentException('Status publikasi edukasi tidak valid.');
        }

        if ($action === 'create_education') {
            $statement = admin_module_execute(
                $koneksi,
                'INSERT INTO posyandu_edukasi (judul, konten, status_publikasi, tanggal_publikasi)
                VALUES (?, ?, ?, ?)',
                'ssss',
                [$title, $content, $status, $publicationDate]
            );
            $statement->close();
            admin_module_flash('posyandu_flash', 'success', 'Materi edukasi berhasil ditambahkan.');
            posyandu_action_redirect('edukasi');
        }

        $id = posyandu_action_id('id');
        $lookup = admin_module_execute($koneksi, 'SELECT id FROM posyandu_edukasi WHERE id = ? LIMIT 1', 'i', [$id]);
        $exists = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if (!$exists) {
            throw new InvalidArgumentException('Materi edukasi tidak ditemukan.');
        }
        $statement = admin_module_execute(
            $koneksi,
            'UPDATE posyandu_edukasi
            SET judul = ?, konten = ?, status_publikasi = ?, tanggal_publikasi = ?
            WHERE id = ?',
            'ssssi',
            [$title, $content, $status, $publicationDate, $id]
        );
        $statement->close();
        admin_module_flash('posyandu_flash', 'success', 'Materi edukasi berhasil diperbarui.');
        posyandu_action_redirect('edukasi');
    }

    if (in_array($action, ['create_agenda', 'update_agenda', 'delete_agenda'], true)) {
        $redirectAnchor = 'jadwal';
        $agendaTypes = ['jadwal', 'kegiatan', 'imunisasi'];
        if ($action === 'delete_agenda') {
            $id = posyandu_action_id('id');
            $lookup = admin_module_execute($koneksi, 'SELECT jenis FROM posyandu_agenda WHERE id = ? LIMIT 1', 'i', [$id]);
            $record = $lookup->get_result()->fetch_assoc();
            $lookup->close();
            if (!$record) {
                throw new InvalidArgumentException('Data agenda tidak ditemukan.');
            }
            $redirectAnchor = $record['jenis'] === 'jadwal' ? 'jadwal' : 'kegiatan';
            $statement = admin_module_execute($koneksi, 'DELETE FROM posyandu_agenda WHERE id = ?', 'i', [$id]);
            $deleted = $statement->affected_rows === 1;
            $statement->close();
            if (!$deleted) {
                throw new InvalidArgumentException('Data agenda tidak ditemukan.');
            }
            admin_module_flash('posyandu_flash', 'success', 'Data agenda Posyandu berhasil dihapus.');
            posyandu_action_redirect($redirectAnchor);
        }

        $type = $_POST['jenis'] ?? '';
        $title = posyandu_action_text('judul', 150, true);
        $description = posyandu_action_text('deskripsi', 5000);
        $date = posyandu_action_date(posyandu_action_text('tanggal', 10, true));
        $startTime = posyandu_action_time('waktu_mulai');
        $endTime = posyandu_action_time('waktu_selesai');
        $location = posyandu_action_text('lokasi', 150);
        $status = $_POST['status_publikasi'] ?? '';
        if (!is_string($type) || !in_array($type, $agendaTypes, true)) {
            throw new InvalidArgumentException('Jenis agenda Posyandu tidak valid.');
        }
        if ($startTime !== null && $endTime !== null && $endTime <= $startTime) {
            throw new InvalidArgumentException('Waktu selesai harus setelah waktu mulai.');
        }
        if (!is_string($status) || !in_array($status, ['draft', 'publikasi'], true)) {
            throw new InvalidArgumentException('Status publikasi tidak valid.');
        }
        $redirectAnchor = $type === 'jadwal' ? 'jadwal' : 'kegiatan';

        if ($action === 'create_agenda') {
            $statement = admin_module_execute(
                $koneksi,
                'INSERT INTO posyandu_agenda (jenis, judul, deskripsi, tanggal, waktu_mulai, waktu_selesai, lokasi, status_publikasi)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                'ssssssss',
                [$type, $title, $description === '' ? null : $description, $date, $startTime, $endTime, $location === '' ? null : $location, $status]
            );
            $statement->close();
            admin_module_flash('posyandu_flash', 'success', 'Agenda Posyandu berhasil ditambahkan.');
            posyandu_action_redirect($redirectAnchor);
        }

        $id = posyandu_action_id('id');
        $lookup = admin_module_execute($koneksi, 'SELECT id FROM posyandu_agenda WHERE id = ? LIMIT 1', 'i', [$id]);
        $exists = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if (!$exists) {
            throw new InvalidArgumentException('Data agenda tidak ditemukan.');
        }
        $statement = admin_module_execute(
            $koneksi,
            'UPDATE posyandu_agenda SET jenis = ?, judul = ?, deskripsi = ?, tanggal = ?, waktu_mulai = ?, waktu_selesai = ?, lokasi = ?, status_publikasi = ? WHERE id = ?',
            'ssssssssi',
            [$type, $title, $description === '' ? null : $description, $date, $startTime, $endTime, $location === '' ? null : $location, $status, $id]
        );
        $statement->close();
        admin_module_flash('posyandu_flash', 'success', 'Agenda Posyandu berhasil diperbarui.');
        posyandu_action_redirect($redirectAnchor);
    }

    if (in_array($action, ['create_kader', 'update_kader', 'delete_kader'], true)) {
        $redirectAnchor = 'kader';
        if (admin_module_missing_tables($koneksi, ['posyandu_kader']) !== []) {
            throw new RuntimeException('Tabel kader Posyandu belum tersedia.');
        }
        if ($action === 'delete_kader') {
            $id = posyandu_action_id('id');
            $statement = admin_module_execute($koneksi, 'DELETE FROM posyandu_kader WHERE id = ?', 'i', [$id]);
            $deleted = $statement->affected_rows === 1;
            $statement->close();
            if (!$deleted) {
                throw new InvalidArgumentException('Informasi kader tidak ditemukan.');
            }
            admin_module_flash('posyandu_flash', 'success', 'Informasi kader berhasil dihapus.');
            posyandu_action_redirect('kader');
        }

        $name = posyandu_action_text('nama', 120, true);
        $role = posyandu_action_text('jabatan', 120, true);
        $information = posyandu_action_text('informasi', 2000);
        $rawOrder = $_POST['urutan'] ?? '0';
        $status = $_POST['status_data'] ?? '';
        if (!is_string($rawOrder) || !ctype_digit($rawOrder) || (int) $rawOrder > 65535) {
            throw new InvalidArgumentException('Urutan kader harus berupa angka antara 0 sampai 65535.');
        }
        if (!is_string($status) || !in_array($status, ['aktif', 'arsip'], true)) {
            throw new InvalidArgumentException('Status kader tidak valid.');
        }
        $order = (int) $rawOrder;

        if ($action === 'create_kader') {
            $statement = admin_module_execute(
                $koneksi,
                'INSERT INTO posyandu_kader (nama, jabatan, informasi, urutan, status_data) VALUES (?, ?, ?, ?, ?)',
                'sssis',
                [$name, $role, $information === '' ? null : $information, $order, $status]
            );
            $statement->close();
            admin_module_flash('posyandu_flash', 'success', 'Informasi kader berhasil ditambahkan.');
            posyandu_action_redirect('kader');
        }

        $id = posyandu_action_id('id');
        $lookup = admin_module_execute($koneksi, 'SELECT id FROM posyandu_kader WHERE id = ? LIMIT 1', 'i', [$id]);
        $exists = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if (!$exists) {
            throw new InvalidArgumentException('Informasi kader tidak ditemukan.');
        }
        $statement = admin_module_execute(
            $koneksi,
            'UPDATE posyandu_kader SET nama = ?, jabatan = ?, informasi = ?, urutan = ?, status_data = ? WHERE id = ?',
            'sssisi',
            [$name, $role, $information === '' ? null : $information, $order, $status, $id]
        );
        $statement->close();
        admin_module_flash('posyandu_flash', 'success', 'Informasi kader berhasil diperbarui.');
        posyandu_action_redirect('kader');
    }

    if ($action === 'upload_documentation') {
        $redirectAnchor = 'dokumentasi';
        if (admin_module_missing_tables($koneksi, ['galeri']) !== []) {
            throw new RuntimeException('Tabel Galeri belum tersedia.');
        }
        $title = posyandu_action_text('judul', 150, true);
        $description = posyandu_action_text('keterangan', 5000);
        $date = posyandu_action_date(posyandu_action_text('tanggal_dokumentasi', 10, true));
        $status = $_POST['status_publikasi'] ?? '';
        if (!is_string($status) || !in_array($status, ['draft', 'publikasi'], true)) {
            throw new InvalidArgumentException('Status publikasi dokumentasi tidak valid.');
        }

        $newPhotoName = posyandu_action_upload_photo();
        $adminLookup = admin_module_execute(
            $koneksi,
            'SELECT id FROM admin WHERE username = ? LIMIT 1',
            's',
            [$_SESSION['admin_username']]
        );
        $adminRecord = $adminLookup->get_result()->fetch_assoc();
        $adminLookup->close();
        $adminId = $adminRecord ? (int) $adminRecord['id'] : null;
        $statement = admin_module_execute(
            $koneksi,
            'INSERT INTO galeri (judul, keterangan, kategori, nama_file, tanggal_dokumentasi, status_publikasi, admin_id)
            VALUES (?, ?, \'Posyandu\', ?, ?, ?, ?)',
            'sssssi',
            [$title, $description === '' ? null : $description, $newPhotoName, $date, $status, $adminId]
        );
        $statement->close();
        $newPhotoName = null;
        admin_module_flash('posyandu_flash', 'success', 'Dokumentasi Posyandu berhasil diunggah.');
        posyandu_action_redirect('dokumentasi');
    }

    $redirectAnchor = 'dokumentasi';
    if (admin_module_missing_tables($koneksi, ['galeri']) !== []) {
        throw new RuntimeException('Tabel Galeri belum tersedia.');
    }
    $id = posyandu_action_id('id');
    $lookup = admin_module_execute(
        $koneksi,
        'SELECT nama_file FROM galeri WHERE id = ? AND kategori = \'Posyandu\' LIMIT 1',
        'i',
        [$id]
    );
    $record = $lookup->get_result()->fetch_assoc();
    $lookup->close();
    if (!$record) {
        throw new InvalidArgumentException('Dokumentasi Posyandu tidak ditemukan.');
    }

    $statement = admin_module_execute(
        $koneksi,
        'DELETE FROM galeri WHERE id = ? AND kategori = \'Posyandu\'',
        'i',
        [$id]
    );
    $deleted = $statement->affected_rows === 1;
    $statement->close();
    if (!$deleted) {
        throw new InvalidArgumentException('Dokumentasi Posyandu tidak dapat dihapus.');
    }
    try {
        if (!galeri_storage_delete_file((string) $record['nama_file'])) {
            throw new RuntimeException('File dokumentasi tidak dapat dihapus dari penyimpanan.');
        }
    } catch (Throwable $exception) {
        error_log('Posyandu documentation cleanup failed: ' . $exception->getMessage());
        admin_module_flash('posyandu_flash', 'error', 'Data dokumentasi telah dihapus, tetapi file perlu dibersihkan administrator.');
        posyandu_action_redirect('dokumentasi');
    }
    admin_module_flash('posyandu_flash', 'success', 'Dokumentasi Posyandu berhasil dihapus.');
    posyandu_action_redirect('dokumentasi');
} catch (InvalidArgumentException $exception) {
    if ($newPhotoName !== null) {
        try {
            galeri_storage_delete_file($newPhotoName);
        } catch (Throwable $cleanupException) {
            error_log('Posyandu temporary upload cleanup failed: ' . $cleanupException->getMessage());
        }
    }
    admin_module_flash('posyandu_flash', 'error', $exception->getMessage());
    posyandu_action_redirect($redirectAnchor, $redirectParameters);
} catch (Throwable $exception) {
    if ($newPhotoName !== null) {
        try {
            galeri_storage_delete_file($newPhotoName);
        } catch (Throwable $cleanupException) {
            error_log('Posyandu temporary upload cleanup failed: ' . $cleanupException->getMessage());
        }
    }
    error_log('Admin Posyandu action failed: ' . $exception->getMessage());
    admin_module_flash('posyandu_flash', 'error', 'Perubahan Posyandu gagal disimpan. Silakan coba kembali.');
    posyandu_action_redirect($redirectAnchor, $redirectParameters);
}
