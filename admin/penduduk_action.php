<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';

function penduduk_action_redirect(string $target = 'penduduk.php'): void
{
    header('Location: ' . $target);
    exit;
}

function penduduk_action_flash(string $type, string $message, array $oldInput = []): void
{
    $_SESSION['penduduk_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    if ($oldInput !== []) {
        $_SESSION['penduduk_old_input'] = $oldInput;
    }
}

function penduduk_action_bind(mysqli_stmt $statement, string $types, array &$values): bool
{
    $references = [];
    foreach ($values as &$value) {
        $references[] = &$value;
    }
    unset($value);

    return call_user_func_array([$statement, 'bind_param'], array_merge([$types], $references));
}

function penduduk_action_execute(mysqli $connection, string $query, string $types, array $values): mysqli_stmt
{
    $statement = $connection->prepare($query);
    if (!$statement || !penduduk_action_bind($statement, $types, $values) || !$statement->execute()) {
        if ($statement instanceof mysqli_stmt) {
            $statement->close();
        }

        throw new RuntimeException('Query modul penduduk gagal dijalankan.');
    }

    return $statement;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Aksi Data Penduduk hanya menerima metode POST.');
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (!is_string($submittedToken) || !hash_equals(admin_csrf_token(), $submittedToken)) {
    penduduk_action_flash('error', 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
    penduduk_action_redirect();
}

$action = $_POST['action'] ?? '';
if (!is_string($action) || !in_array($action, ['create', 'update', 'archive', 'restore'], true)) {
    penduduk_action_flash('error', 'Aksi Data Penduduk tidak valid.');
    penduduk_action_redirect();
}

$rawId = $_POST['id'] ?? '';
$id = null;
if ($action !== 'create') {
    if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
        penduduk_action_flash('error', 'ID data penduduk tidak valid.');
        penduduk_action_redirect();
    }
    $id = (int) $rawId;
}

$nama = '';
$alamat = '';
$nik = '';
$tanggalLahir = '';
if ($action === 'create' || $action === 'update') {
    $rawNama = $_POST['nama_lengkap'] ?? '';
    $rawAlamat = $_POST['alamat'] ?? '';
    $rawNik = $_POST['nik'] ?? '';
    $rawTanggalLahir = $_POST['tanggal_lahir'] ?? '';

    if (!is_string($rawNama) || !is_string($rawAlamat) || !is_string($rawNik) || !is_string($rawTanggalLahir)) {
        penduduk_action_flash('error', 'Data formulir tidak valid.');
        penduduk_action_redirect(
            $action === 'create' ? 'penduduk.php?form=tambah' : 'penduduk.php?form=edit&id=' . $id
        );
    }

    $nama = trim($rawNama);
    $alamat = trim($rawAlamat);
    $nik = trim($rawNik);
    $tanggalLahir = trim($rawTanggalLahir);
    $oldInput = [
        'nama_lengkap' => $nama,
        'alamat' => $alamat,
        'nik' => $nik,
        'tanggal_lahir' => $tanggalLahir,
    ];

    if (preg_match('/\A.{1,120}\z/us', $nama) !== 1) {
        penduduk_action_flash('error', 'Nama wajib diisi dan maksimal 120 karakter.', $oldInput);
        penduduk_action_redirect(
            $action === 'create' ? 'penduduk.php?form=tambah' : 'penduduk.php?form=edit&id=' . $id
        );
    }

    if (preg_match('/\A.{0,1000}\z/us', $alamat) !== 1) {
        penduduk_action_flash('error', 'Alamat maksimal 1.000 karakter.', $oldInput);
        penduduk_action_redirect(
            $action === 'create' ? 'penduduk.php?form=tambah' : 'penduduk.php?form=edit&id=' . $id
        );
    }

    if ($nik !== '' && preg_match('/\A[0-9]{16}\z/', $nik) !== 1) {
        penduduk_action_flash('error', 'NIK harus terdiri dari 16 digit angka.', $oldInput);
        penduduk_action_redirect(
            $action === 'create' ? 'penduduk.php?form=tambah' : 'penduduk.php?form=edit&id=' . $id
        );
    }

    if ($tanggalLahir !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $tanggalLahir);
        if (!$date || $date->format('Y-m-d') !== $tanggalLahir || $date > new DateTimeImmutable('today')) {
            penduduk_action_flash('error', 'Tanggal lahir tidak valid.', $oldInput);
            penduduk_action_redirect(
                $action === 'create' ? 'penduduk.php?form=tambah' : 'penduduk.php?form=edit&id=' . $id
            );
        }
    }
}

try {
    if ($action === 'create') {
        $saved = false;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $kodeInternal = bin2hex(random_bytes(16));
            try {
                $statement = penduduk_action_execute(
                    $koneksi,
                    'INSERT INTO penduduk (kode_internal, nama_lengkap, nik, tanggal_lahir, alamat) VALUES (?, ?, ?, ?, ?)',
                    'sssss',
                    [$kodeInternal, $nama, $nik === '' ? null : $nik, $tanggalLahir === '' ? null : $tanggalLahir, $alamat === '' ? null : $alamat]
                );
                $statement->close();
                $saved = true;
                break;
            } catch (mysqli_sql_exception $exception) {
                if ((int) $exception->getCode() !== 1062 || $attempt === 2) {
                    throw $exception;
                }
            }
        }

        if (!$saved) {
            throw new RuntimeException('Kode internal tidak dapat dibuat.');
        }

        penduduk_action_flash('success', 'Data penduduk berhasil ditambahkan.');
    } elseif ($action === 'update') {
        $statement = penduduk_action_execute(
            $koneksi,
            'UPDATE penduduk SET nama_lengkap = ?, nik = ?, tanggal_lahir = ?, alamat = ? WHERE id = ?',
            'ssssi',
            [$nama, $nik === '' ? null : $nik, $tanggalLahir === '' ? null : $tanggalLahir, $alamat === '' ? null : $alamat, $id]
        );
        $affectedRows = $statement->affected_rows;
        $statement->close();

        if ($affectedRows === 0) {
            $check = penduduk_action_execute($koneksi, 'SELECT id FROM penduduk WHERE id = ?', 'i', [$id]);
            $exists = $check->fetch();
            $check->close();
            if (!$exists) {
                penduduk_action_flash('error', 'Data penduduk tidak ditemukan.');
                penduduk_action_redirect();
            }
        }

        penduduk_action_flash('success', 'Data penduduk berhasil diperbarui.');
    } else {
        $newStatus = $action === 'archive' ? 'arsip' : 'aktif';
        $statement = penduduk_action_execute(
            $koneksi,
            'UPDATE penduduk SET status_data = ? WHERE id = ?',
            'si',
            [$newStatus, $id]
        );
        $affectedRows = $statement->affected_rows;
        $statement->close();

        if ($affectedRows === 0) {
            $check = penduduk_action_execute($koneksi, 'SELECT id FROM penduduk WHERE id = ?', 'i', [$id]);
            $exists = $check->fetch();
            $check->close();
            if (!$exists) {
                penduduk_action_flash('error', 'Data penduduk tidak ditemukan.');
                penduduk_action_redirect();
            }
        }

        penduduk_action_flash(
            'success',
            $action === 'archive' ? 'Data penduduk berhasil diarsipkan.' : 'Data penduduk berhasil diaktifkan kembali.'
        );
    }

    penduduk_action_redirect();
} catch (mysqli_sql_exception $exception) {
    error_log('Admin penduduk action failed: ' . $exception->getMessage());
    $message = (int) $exception->getCode() === 1146
        ? 'Tabel penduduk belum tersedia. Modul belum dapat digunakan sampai tabel disiapkan.'
        : 'Perubahan data penduduk gagal disimpan. Silakan coba lagi.';
    $oldInput = isset($nama, $alamat, $nik, $tanggalLahir) && ($action === 'create' || $action === 'update')
        ? ['nama_lengkap' => $nama, 'alamat' => $alamat, 'nik' => $nik, 'tanggal_lahir' => $tanggalLahir]
        : [];
    if ((int) $exception->getCode() === 1062 && ($action === 'create' || $action === 'update')) {
        penduduk_action_flash('error', 'NIK tersebut sudah digunakan pada data penduduk lain.', $oldInput);
        penduduk_action_redirect(
            $action === 'create' ? 'penduduk.php?form=tambah' : 'penduduk.php?form=edit&id=' . $id
        );
    }
    penduduk_action_flash('error', $message, $oldInput);
    penduduk_action_redirect(
        $action === 'create' ? 'penduduk.php?form=tambah' : ($action === 'update' ? 'penduduk.php?form=edit&id=' . $id : 'penduduk.php')
    );
} catch (Throwable $exception) {
    error_log('Admin penduduk action failed: ' . $exception->getMessage());
    penduduk_action_flash('error', 'Perubahan data penduduk gagal disimpan. Silakan coba lagi.');
    penduduk_action_redirect();
}
