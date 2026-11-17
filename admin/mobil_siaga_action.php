<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/../includes/mobil_siaga_helpers.php';
require_once __DIR__ . '/../includes/mobil_siaga_storage.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Metode permintaan tidak diizinkan.');
}

$flashKey = 'admin_mobil_siaga_flash';
$action = $_POST['action'] ?? '';
$allowedSections = [
    'save_profile' => 'profil-kendaraan',
    'save_facility' => 'fasilitas-kendaraan',
    'save_staff' => 'data-petugas',
    'save_schedule' => 'jadwal-petugas',
    'set_vehicle_status' => 'status-operasional',
    'approve_application' => 'pengajuan',
    'transition_application' => 'pengajuan',
    'update_usage_estimate' => 'riwayat-penggunaan',
];
$section = is_string($action) && isset($allowedSections[$action]) ? $allowedSections[$action] : 'profil-kendaraan';
$newPhoto = null;
$oldPhoto = null;

$uploadPhoto = static function (string $field): ?string {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK
        || !isset($file['tmp_name'], $file['size'])
        || !is_string($file['tmp_name'])
        || !is_uploaded_file($file['tmp_name'])
        || !is_int($file['size'])
        || $file['size'] < 1
        || $file['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Unggahan foto tidak valid atau melebihi 5 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $image = getimagesize($file['tmp_name']);
    if (!is_string($mime) || !isset($extensions[$mime]) || $image === false
        || ($image['mime'] ?? '') !== $mime
        || (int) $image[0] < 1 || (int) $image[1] < 1
        || (int) $image[0] > 6000 || (int) $image[1] > 6000
        || (int) $image[0] * (int) $image[1] > 30000000) {
        throw new InvalidArgumentException('Foto harus berupa JPG, PNG, atau WebP dengan dimensi yang valid (maksimal 6.000 x 6.000 piksel).');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $path = mobil_siaga_storage_ensure_directory() . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        throw new RuntimeException('Foto tidak dapat disimpan.');
    }

    return $fileName;
};

try {
    if (!is_string($action) || !isset($allowedSections[$action])) {
        throw new InvalidArgumentException('Aksi Mobil Siaga tidak dikenali.');
    }
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!is_string($csrfToken) || !hash_equals(admin_csrf_token(), $csrfToken)) {
        throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.');
    }

    $idFromPost = static function (string $key): ?int {
        $raw = $_POST[$key] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        $validated = is_string($raw)
            ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        if ($validated === false) {
            throw new InvalidArgumentException('ID data tidak valid.');
        }
        return (int) $validated;
    };

    switch ($action) {
        case 'save_profile':
            $current = mobil_siaga_get_profile($koneksi);
            $oldPhoto = is_array($current) && is_string($current['foto_file'] ?? null) ? $current['foto_file'] : null;
            $newPhoto = $uploadPhoto('foto_kendaraan');
            $input = [
                'nama_kendaraan' => $_POST['nama_kendaraan'] ?? null,
                'deskripsi' => $_POST['deskripsi'] ?? null,
                'kapasitas_penumpang' => $_POST['kapasitas_penumpang'] ?? null,
                'fungsi_layanan' => $_POST['fungsi_layanan'] ?? null,
                'cara_penggunaan' => $_POST['cara_penggunaan'] ?? null,
                'ketentuan_penggunaan' => $_POST['ketentuan_penggunaan'] ?? null,
                'nama_kontak_darurat' => $_POST['nama_kontak_darurat'] ?? null,
                'kontak_darurat' => $_POST['kontak_darurat'] ?? null,
            ];
            if ($newPhoto !== null) {
                $input['foto_file'] = $newPhoto;
            }
            mobil_siaga_save_profile($koneksi, $input);
            $newPhoto = null;
            if ($oldPhoto !== null && isset($input['foto_file']) && $oldPhoto !== $input['foto_file']) {
                try {
                    mobil_siaga_storage_delete_file($oldPhoto);
                } catch (Throwable $exception) {
                    error_log('Previous Mobil Siaga vehicle photo cleanup failed: ' . $exception->getMessage());
                }
            }
            $message = 'Profil kendaraan berhasil disimpan.';
            break;

        case 'save_facility':
            $facilityId = $idFromPost('facility_id');
            mobil_siaga_save_facility($koneksi, [
                'nama_fasilitas' => $_POST['nama_fasilitas'] ?? null,
                'keterangan' => $_POST['keterangan'] ?? null,
                'urutan' => $_POST['urutan'] ?? '0',
                'aktif' => $_POST['aktif'] ?? '1',
            ], $facilityId);
            $message = 'Data fasilitas berhasil disimpan.';
            break;

        case 'save_staff':
            $staffId = $idFromPost('staff_id');
            $current = null;
            if ($staffId !== null) {
                foreach (mobil_siaga_list_staff($koneksi, false) as $person) {
                    if ((int) ($person['id'] ?? 0) === $staffId) {
                        $current = $person;
                        break;
                    }
                }
                if ($current === null) {
                    throw new InvalidArgumentException('Petugas Mobil Siaga tidak ditemukan.');
                }
            }
            $oldPhoto = is_array($current) && is_string($current['foto_file'] ?? null) ? $current['foto_file'] : null;
            $newPhoto = $uploadPhoto('foto_petugas');
            $input = [
                'nama_petugas' => $_POST['nama_petugas'] ?? null,
                'jenis_petugas' => $_POST['jenis_petugas'] ?? null,
                'jabatan' => $_POST['jabatan'] ?? null,
                'no_hp' => $_POST['no_hp'] ?? null,
                'foto_file' => $newPhoto ?? $oldPhoto,
                'status_tugas' => $_POST['status_tugas'] ?? null,
                'aktif' => $_POST['aktif'] ?? null,
            ];
            mobil_siaga_save_staff($koneksi, $input, $staffId);
            $newPhoto = null;
            if ($oldPhoto !== null && isset($input['foto_file']) && $oldPhoto !== $input['foto_file']) {
                try {
                    mobil_siaga_storage_delete_file($oldPhoto);
                } catch (Throwable $exception) {
                    error_log('Previous Mobil Siaga staff photo cleanup failed: ' . $exception->getMessage());
                }
            }
            $message = 'Data petugas berhasil disimpan.';
            break;

        case 'save_schedule':
            $scheduleId = $idFromPost('schedule_id');
            mobil_siaga_save_schedule($koneksi, [
                'petugas_id' => $_POST['petugas_id'] ?? null,
                'tanggal' => $_POST['tanggal'] ?? null,
                'jam_mulai' => $_POST['jam_mulai'] ?? null,
                'jam_selesai' => $_POST['jam_selesai'] ?? null,
                'catatan' => $_POST['catatan'] ?? null,
            ], $scheduleId);
            $message = 'Jadwal petugas berhasil disimpan.';
            break;

        case 'set_vehicle_status':
            $status = $_POST['status'] ?? '';
            if (!is_string($status)) {
                throw new InvalidArgumentException('Status kendaraan tidak valid.');
            }
            $changed = mobil_siaga_set_vehicle_status(
                $koneksi,
                $status,
                is_string($_POST['catatan'] ?? null) ? $_POST['catatan'] : null,
                (string) $_SESSION['admin_username']
            );
            $message = $changed
                ? 'Status operasional kendaraan berhasil diperbarui.'
                : 'Status kendaraan sudah sama; tidak ada perubahan yang dilakukan.';
            break;

        case 'approve_application':
            $applicationId = $idFromPost('application_id');
            if ($applicationId === null) {
                throw new InvalidArgumentException('ID pengajuan tidak valid.');
            }
            $changed = mobil_siaga_review_application(
                $koneksi,
                $applicationId,
                'disetujui',
                (string) $_SESSION['admin_username'],
                is_string($_POST['pesan'] ?? null) ? $_POST['pesan'] : null
            );
            $message = $changed
                ? 'Permohonan disetujui. Status dan riwayat diperbarui tanpa membuat jadwal atau catatan penggunaan.'
                : 'Permohonan sudah berstatus disetujui; tidak ada perubahan.';
            break;

        case 'transition_application':
            $applicationId = $idFromPost('application_id');
            $newStatus = $_POST['status'] ?? '';
            if ($applicationId === null || !is_string($newStatus)) {
                throw new InvalidArgumentException('Data perubahan status pengajuan tidak valid.');
            }
            if ($newStatus === 'ditolak') {
                $changed = mobil_siaga_review_application(
                    $koneksi,
                    $applicationId,
                    'ditolak',
                    (string) $_SESSION['admin_username'],
                    is_string($_POST['pesan'] ?? null) ? $_POST['pesan'] : null
                );
                $message = $changed
                    ? 'Permohonan ditolak dan riwayat diperbarui.'
                    : 'Permohonan sudah berstatus ditolak; tidak ada perubahan.';
            } else {
                mobil_siaga_transition_application(
                    $koneksi,
                    $applicationId,
                    $newStatus,
                    (string) $_SESSION['admin_username'],
                    is_string($_POST['pesan'] ?? null) ? $_POST['pesan'] : null
                );
                $message = 'Status permohonan berhasil diperbarui.';
            }
            break;

        case 'update_usage_estimate':
            $usageId = $idFromPost('usage_id');
            $estimatedEnd = $_POST['estimated_end'] ?? '';
            if ($usageId === null || !is_string($estimatedEnd)) {
                throw new InvalidArgumentException('Data estimasi selesai tidak valid.');
            }
            $estimatedEnd = str_replace('T', ' ', $estimatedEnd);
            if (preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}\z/', $estimatedEnd) === 1) {
                $estimatedEnd .= ':00';
            }
            mobil_siaga_update_usage_estimate(
                $koneksi,
                $usageId,
                $estimatedEnd,
                (string) $_SESSION['admin_username']
            );
            $message = 'Estimasi selesai berhasil diperbarui.';
            break;
    }

    admin_module_flash($flashKey, 'success', $message);
} catch (InvalidArgumentException $exception) {
    if ($newPhoto !== null) {
        try {
            mobil_siaga_storage_delete_file($newPhoto);
        } catch (Throwable $cleanupException) {
            error_log('Rejected Mobil Siaga photo cleanup failed: ' . $cleanupException->getMessage());
        }
    }
    admin_module_flash($flashKey, 'error', $exception->getMessage());
} catch (Throwable $exception) {
    if ($newPhoto !== null) {
        try {
            mobil_siaga_storage_delete_file($newPhoto);
        } catch (Throwable $cleanupException) {
            error_log('Failed Mobil Siaga photo cleanup failed: ' . $cleanupException->getMessage());
        }
    }
    error_log('Admin Mobil Siaga action failed: ' . $exception->getMessage());
    admin_module_flash($flashKey, 'error', 'Perubahan belum dapat disimpan. Periksa data atau coba kembali.');
}

header('Location: mobil_siaga.php#' . $section, true, 303);
exit;
