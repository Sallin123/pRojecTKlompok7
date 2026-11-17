<?php
require_once __DIR__ . '/masyarakat_auth.php';
masyarakat_require_authentication('mobil_siaga.php#permohonan');
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/includes/mobil_siaga_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Metode permintaan tidak diizinkan.');
}

if (!masyarakat_csrf_is_valid($_POST['csrf_token'] ?? null)) {
    $_SESSION['mobil_siaga_form_error'] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.';
    header('Location: mobil_siaga.php#permohonan', true, 303);
    exit;
}

try {
    $useDate = $_POST['tanggal_penggunaan'] ?? null;
    if (!is_string($useDate) || $useDate < date('Y-m-d')) {
        throw new InvalidArgumentException('Tanggal penggunaan tidak boleh sebelum hari ini.');
    }

    $applicationId = mobil_siaga_create_application($koneksi, [
        'nama_pemohon' => $_POST['nama_pemohon'] ?? null,
        'no_hp_pemohon' => $_POST['no_hp_pemohon'] ?? null,
        'alamat_pemohon' => $_POST['alamat_pemohon'] ?? null,
        'tujuan_penggunaan' => $_POST['tujuan_penggunaan'] ?? null,
        'tanggal_penggunaan' => $_POST['tanggal_penggunaan'] ?? null,
        'jam_mulai' => $_POST['jam_mulai'] ?? null,
        'jam_selesai' => null,
        'jumlah_penumpang' => $_POST['jumlah_penumpang'] ?? 1,
        'catatan' => $_POST['catatan'] ?? null,
    ]);
    $_SESSION['mobil_siaga_application_created'] = true;
    $application = mobil_siaga_get_application($koneksi, $applicationId);
    if ($application === null || !isset($application['kode_pengajuan'])) {
        throw new RuntimeException('Pengajuan tersimpan tetapi kode pelacakan tidak dapat dibaca.');
    }

    $_SESSION['mobil_siaga_application_code'] = (string) $application['kode_pengajuan'];
} catch (InvalidArgumentException $exception) {
    $_SESSION['mobil_siaga_form_error'] = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('Public Mobil Siaga application submission failed: ' . $exception->getMessage());
    $_SESSION['mobil_siaga_form_error'] = !empty($_SESSION['mobil_siaga_application_created'])
        ? 'Pengajuan sudah tersimpan, tetapi kode pelacakan belum dapat ditampilkan. Jangan kirim ulang; hubungi kontak resmi untuk bantuan.'
        : 'Pengajuan belum dapat dikirim. Silakan coba kembali atau hubungi kontak resmi.';
}
unset($_SESSION['mobil_siaga_application_created']);

header('Location: mobil_siaga.php#permohonan', true, 303);
exit;
