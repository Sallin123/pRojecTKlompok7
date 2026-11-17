<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/module_helpers.php';

function bansos_redirect(string $view, array $parameters = []): void
{
    $parameters = ['view' => $view] + $parameters;
    header('Location: bansos.php?' . http_build_query($parameters));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Aksi bansos hanya menerima metode POST.');
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !hash_equals(admin_csrf_token(), $token)) {
    admin_module_flash('bansos_flash', 'error', 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
    bansos_redirect('program');
}

$entity = $_POST['entity'] ?? '';
$action = $_POST['action'] ?? '';
if (!is_string($entity) || !in_array($entity, ['program', 'periode', 'penerima'], true)
    || !is_string($action) || !in_array($action, ['create', 'update', 'archive', 'restore', 'cancel'], true)) {
    admin_module_flash('bansos_flash', 'error', 'Aksi pengelolaan bansos tidak valid.');
    bansos_redirect('program');
}

$view = $entity === 'program' ? 'program' : ($entity === 'periode' ? 'periode' : 'penerima');
$context = [];
$rawContext = $entity === 'periode' ? ($_POST['program_id'] ?? '') : ($entity === 'penerima' ? ($_POST['periode_id'] ?? '') : '');
if ($entity !== 'program') {
    if (!is_string($rawContext) || !ctype_digit($rawContext) || (int) $rawContext < 1) {
        admin_module_flash('bansos_flash', 'error', 'Konteks program atau periode tidak valid.');
        bansos_redirect($view);
    }
    $context[$entity === 'periode' ? 'program_id' : 'periode_id'] = (int) $rawContext;
}

$rawId = $_POST['id'] ?? '';
$id = null;
if ($action !== 'create') {
    if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
        admin_module_flash('bansos_flash', 'error', 'ID data bansos tidak valid.');
        bansos_redirect($view, $context);
    }
    $id = (int) $rawId;
}

try {
    $missing = admin_module_missing_tables($koneksi, ['bansos_program', 'bansos_periode', 'bansos_penerima', 'penduduk']);
    if ($missing !== []) {
        admin_module_flash('bansos_flash', 'error', 'Struktur tabel bansos belum lengkap. Periksa database/dashboard_admin_modules.sql.');
        bansos_redirect($view, $context);
    }

    if ($entity === 'program' && in_array($action, ['create', 'update'], true)) {
        $name = $_POST['nama_program'] ?? '';
        $description = $_POST['deskripsi'] ?? '';
        if (!is_string($name) || !is_string($description)) {
            throw new InvalidArgumentException('Data formulir program tidak valid.');
        }
        $name = trim($name);
        $description = trim($description);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 150) {
            throw new InvalidArgumentException('Nama program wajib diisi dan maksimal 150 karakter.');
        }
        if (mb_strlen($description, 'UTF-8') > 5000) {
            throw new InvalidArgumentException('Deskripsi program maksimal 5.000 karakter.');
        }

        if ($action === 'create') {
            $statement = admin_module_execute(
                $koneksi,
                'INSERT INTO bansos_program (nama_program, deskripsi) VALUES (?, ?)',
                'ss',
                [$name, $description === '' ? null : $description]
            );
            $statement->close();
            admin_module_flash('bansos_flash', 'success', 'Program bantuan berhasil ditambahkan.');
        } else {
            $statement = admin_module_execute(
                $koneksi,
                'UPDATE bansos_program SET nama_program = ?, deskripsi = ? WHERE id = ?',
                'ssi',
                [$name, $description === '' ? null : $description, $id]
            );
            $changed = $statement->affected_rows;
            $statement->close();
            if ($changed === 0) {
                $check = admin_module_execute($koneksi, 'SELECT id FROM bansos_program WHERE id = ?', 'i', [$id]);
                $exists = $check->fetch();
                $check->close();
                if (!$exists) {
                    admin_module_flash('bansos_flash', 'error', 'Program bantuan tidak ditemukan.');
                    bansos_redirect('program');
                }
            }
            admin_module_flash('bansos_flash', 'success', 'Program bantuan berhasil diperbarui.');
        }
        bansos_redirect('program');
    }

    if ($entity === 'periode' && in_array($action, ['create', 'update'], true)) {
        $name = $_POST['nama_periode'] ?? '';
        $start = $_POST['tanggal_mulai'] ?? '';
        $end = $_POST['tanggal_selesai'] ?? '';
        if (!is_string($name) || !is_string($start) || !is_string($end)) {
            throw new InvalidArgumentException('Data formulir periode tidak valid.');
        }
        $name = trim($name);
        $start = trim($start);
        $end = trim($end);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
            throw new InvalidArgumentException('Nama periode wajib diisi dan maksimal 100 karakter.');
        }
        foreach (['tanggal mulai' => $start, 'tanggal selesai' => $end] as $label => $date) {
            if ($date !== '' && (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)))) {
                throw new InvalidArgumentException('Format ' . $label . ' tidak valid.');
            }
        }
        if ($start !== '' && $end !== '' && $start > $end) {
            throw new InvalidArgumentException('Tanggal mulai tidak boleh melewati tanggal selesai.');
        }

        $programCheck = admin_module_execute(
            $koneksi,
            'SELECT id FROM bansos_program WHERE id = ? AND status_program = \'aktif\'',
            'i',
            [$context['program_id']]
        );
        $programExists = $programCheck->fetch();
        $programCheck->close();
        if (!$programExists) {
            throw new InvalidArgumentException('Program tidak ditemukan atau sudah diarsipkan.');
        }

        if ($action === 'create') {
            $statement = admin_module_execute(
                $koneksi,
                'INSERT INTO bansos_periode (program_id, nama_periode, tanggal_mulai, tanggal_selesai) VALUES (?, ?, ?, ?)',
                'isss',
                [$context['program_id'], $name, $start === '' ? null : $start, $end === '' ? null : $end]
            );
            $statement->close();
            admin_module_flash('bansos_flash', 'success', 'Periode bantuan berhasil ditambahkan.');
        } else {
            $statement = admin_module_execute(
                $koneksi,
                'UPDATE bansos_periode SET nama_periode = ?, tanggal_mulai = ?, tanggal_selesai = ? WHERE id = ? AND program_id = ?',
                'sssii',
                [$name, $start === '' ? null : $start, $end === '' ? null : $end, $id, $context['program_id']]
            );
            $changed = $statement->affected_rows;
            $statement->close();
            if ($changed === 0) {
                $check = admin_module_execute(
                    $koneksi,
                    'SELECT id FROM bansos_periode WHERE id = ? AND program_id = ?',
                    'ii',
                    [$id, $context['program_id']]
                );
                $exists = $check->fetch();
                $check->close();
                if (!$exists) {
                    admin_module_flash('bansos_flash', 'error', 'Periode bantuan tidak ditemukan.');
                    bansos_redirect('periode', $context);
                }
            }
            admin_module_flash('bansos_flash', 'success', 'Periode bantuan berhasil diperbarui.');
        }
        bansos_redirect('periode', $context);
    }

    if ($entity === 'penerima' && $action === 'create') {
        $rawResidentId = $_POST['penduduk_id'] ?? '';
        if (!is_string($rawResidentId) || !ctype_digit($rawResidentId) || (int) $rawResidentId < 1) {
            throw new InvalidArgumentException('Penduduk yang dipilih tidak valid.');
        }
        $residentId = (int) $rawResidentId;
        $periodCheck = admin_module_execute(
            $koneksi,
            'SELECT bp.id FROM bansos_periode bp JOIN bansos_program bpr ON bpr.id = bp.program_id WHERE bp.id = ? AND bp.status_periode = \'aktif\' AND bpr.status_program = \'aktif\'',
            'i',
            [$context['periode_id']]
        );
        $periodExists = $periodCheck->fetch();
        $periodCheck->close();
        if (!$periodExists) {
            throw new InvalidArgumentException('Periode bantuan tidak ditemukan atau sudah diarsipkan.');
        }
        $residentCheck = admin_module_execute(
            $koneksi,
            'SELECT id FROM penduduk WHERE id = ? AND status_data = \'aktif\'',
            'i',
            [$residentId]
        );
        $residentExists = $residentCheck->fetch();
        $residentCheck->close();
        if (!$residentExists) {
            throw new InvalidArgumentException('Penduduk tidak ditemukan atau bukan berstatus aktif.');
        }
        $statement = admin_module_execute(
            $koneksi,
            'INSERT INTO bansos_penerima (periode_id, penduduk_id) VALUES (?, ?)',
            'ii',
            [$context['periode_id'], $residentId]
        );
        $statement->close();
        admin_module_flash('bansos_flash', 'success', 'Penerima bantuan berhasil ditambahkan dengan status terdaftar.');
        bansos_redirect('penerima', $context);
    }

    if ($entity === 'penerima' && $action === 'update') {
        $status = $_POST['status_penerima'] ?? '';
        $date = $_POST['tanggal_penyaluran'] ?? '';
        $notes = $_POST['catatan'] ?? '';
        if (!is_string($status) || !in_array($status, ['terdaftar', 'disalurkan', 'dibatalkan'], true)
            || !is_string($date) || !is_string($notes)) {
            throw new InvalidArgumentException('Data status penerima bantuan tidak valid.');
        }
        $date = trim($date);
        $notes = trim($notes);
        if ($status === 'disalurkan') {
            if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)
                || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                throw new InvalidArgumentException('Tanggal penyaluran wajib diisi dengan tanggal yang valid untuk status disalurkan.');
            }
        } else {
            $date = '';
        }
        if (mb_strlen($notes, 'UTF-8') > 3000) {
            throw new InvalidArgumentException('Catatan maksimal 3.000 karakter.');
        }
        $statement = admin_module_execute(
            $koneksi,
            'UPDATE bansos_penerima SET status_penerima = ?, tanggal_penyaluran = ?, catatan = ? WHERE id = ? AND periode_id = ?',
            'sssii',
            [$status, $date === '' ? null : $date, $notes === '' ? null : $notes, $id, $context['periode_id']]
        );
        $changed = $statement->affected_rows;
        $statement->close();
        if ($changed === 0) {
            $check = admin_module_execute(
                $koneksi,
                'SELECT id FROM bansos_penerima WHERE id = ? AND periode_id = ?',
                'ii',
                [$id, $context['periode_id']]
            );
            $exists = $check->fetch();
            $check->close();
            if (!$exists) {
                admin_module_flash('bansos_flash', 'error', 'Data penerima bantuan tidak ditemukan.');
                bansos_redirect('penerima', $context);
            }
        }
        admin_module_flash('bansos_flash', 'success', 'Status penerima bantuan berhasil diperbarui.');
        bansos_redirect('penerima', $context);
    }

    if (in_array($action, ['archive', 'restore', 'cancel'], true)) {
        if ($entity === 'program') {
            $column = 'status_program';
            $table = 'bansos_program';
            $newStatus = $action === 'restore' ? 'aktif' : 'arsip';
        } elseif ($entity === 'periode') {
            $column = 'status_periode';
            $table = 'bansos_periode';
            $newStatus = $action === 'restore' ? 'aktif' : 'arsip';
        } else {
            $column = 'status_penerima';
            $table = 'bansos_penerima';
            $newStatus = $action === 'restore' ? 'terdaftar' : 'dibatalkan';
        }
        if ($entity === 'penerima') {
            $statement = admin_module_execute(
                $koneksi,
                'UPDATE bansos_penerima SET status_penerima = ?, tanggal_penyaluran = NULL WHERE id = ? AND periode_id = ?',
                'sii',
                [$newStatus, $id, $context['periode_id']]
            );
        } else {
            $statement = admin_module_execute(
                $koneksi,
                "UPDATE `$table` SET `$column` = ? WHERE id = ?",
                'si',
                [$newStatus, $id]
            );
        }
        $changed = $statement->affected_rows;
        $statement->close();
        if ($changed === 0) {
            $query = $entity === 'penerima'
                ? 'SELECT id FROM bansos_penerima WHERE id = ? AND periode_id = ?'
                : "SELECT id FROM `$table` WHERE id = ?";
            $types = $entity === 'penerima' ? 'ii' : 'i';
            $values = $entity === 'penerima' ? [$id, $context['periode_id']] : [$id];
            $check = admin_module_execute($koneksi, $query, $types, $values);
            $exists = $check->fetch();
            $check->close();
            if (!$exists) {
                admin_module_flash('bansos_flash', 'error', 'Data bansos tidak ditemukan.');
                bansos_redirect($view, $context);
            }
        }
        $message = $entity === 'penerima'
            ? ($action === 'restore' ? 'Penerima diaktifkan kembali sebagai terdaftar.' : 'Penerima dibatalkan; riwayat tidak dihapus.')
            : ($action === 'restore' ? 'Data berhasil diaktifkan kembali.' : 'Data diarsipkan dan tidak dihapus permanen.');
        admin_module_flash('bansos_flash', 'success', $message);
        bansos_redirect($view, $context);
    }

    throw new InvalidArgumentException('Kombinasi entitas dan aksi bansos tidak valid.');
} catch (InvalidArgumentException $exception) {
    if ($action === 'create' || $action === 'update') {
        $limits = $entity === 'program'
            ? ['nama_program' => 150, 'deskripsi' => 5000]
            : ($entity === 'periode'
                ? ['nama_periode' => 100, 'tanggal_mulai' => 10, 'tanggal_selesai' => 10]
                : ($action === 'create'
                    ? ['penduduk_id' => 10]
                    : ['status_penerima' => 20, 'tanggal_penyaluran' => 10, 'catatan' => 3000]));
        admin_module_store_old_input('bansos_old_input', $_POST, $limits);
    }
    admin_module_flash('bansos_flash', 'error', $exception->getMessage());
    admin_module_form_redirect('bansos.php', $action, $id, ['view' => $view] + $context);
} catch (mysqli_sql_exception $exception) {
    error_log('Admin bansos action failed: ' . $exception->getMessage());
    if ($action === 'create' || $action === 'update') {
        $limits = $entity === 'program'
            ? ['nama_program' => 150, 'deskripsi' => 5000]
            : ($entity === 'periode'
                ? ['nama_periode' => 100, 'tanggal_mulai' => 10, 'tanggal_selesai' => 10]
                : ($action === 'create'
                    ? ['penduduk_id' => 10]
                    : ['status_penerima' => 20, 'tanggal_penyaluran' => 10, 'catatan' => 3000]));
        admin_module_store_old_input('bansos_old_input', $_POST, $limits);
    }
    $message = (int) $exception->getCode() === 1062
        ? 'Data periode atau penerima pada pasangan program/periode tersebut sudah ada.'
        : 'Perubahan data bansos gagal disimpan. Periksa struktur database dan coba lagi.';
    admin_module_flash('bansos_flash', 'error', $message);
    admin_module_form_redirect('bansos.php', $action, $id, ['view' => $view] + $context);
} catch (Throwable $exception) {
    error_log('Admin bansos action failed: ' . $exception->getMessage());
    if ($action === 'create' || $action === 'update') {
        $limits = $entity === 'program'
            ? ['nama_program' => 150, 'deskripsi' => 5000]
            : ($entity === 'periode'
                ? ['nama_periode' => 100, 'tanggal_mulai' => 10, 'tanggal_selesai' => 10]
                : ($action === 'create'
                    ? ['penduduk_id' => 10]
                    : ['status_penerima' => 20, 'tanggal_penyaluran' => 10, 'catatan' => 3000]));
        admin_module_store_old_input('bansos_old_input', $_POST, $limits);
    }
    admin_module_flash('bansos_flash', 'error', 'Perubahan data bansos gagal disimpan. Silakan coba lagi.');
    admin_module_form_redirect('bansos.php', $action, $id, ['view' => $view] + $context);
}
