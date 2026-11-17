<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/module_helpers.php';

function admin_akun_masyarakat_redirect(): void
{
    header('Location: akun_masyarakat.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Aksi persetujuan akun hanya menerima metode POST.');
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (!is_string($submittedToken) || !hash_equals(admin_csrf_token(), $submittedToken)) {
    admin_module_flash('akun_masyarakat_flash', 'error', 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
    admin_akun_masyarakat_redirect();
}

$rawId = $_POST['id'] ?? '';
$action = $_POST['action'] ?? '';
if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1
    || !is_string($action) || !in_array($action, ['approve', 'reject'], true)) {
    admin_module_flash('akun_masyarakat_flash', 'error', 'Data keputusan akun tidak valid.');
    admin_akun_masyarakat_redirect();
}
$accountId = (int) $rawId;
$newStatus = $action === 'approve' ? 'approved' : 'rejected';

try {
    $reviewer = admin_module_execute(
        $koneksi,
        'SELECT id FROM admin WHERE username = ? LIMIT 1',
        's',
        [$_SESSION['admin_username']]
    );
    if (!$reviewer->bind_result($reviewerId) || $reviewer->fetch() !== true) {
        $reviewer->close();
        throw new RuntimeException('Authenticated administrator has no matching database record.');
    }
    $reviewer->close();
    $reviewerId = (int) $reviewerId;

    $statement = admin_module_execute(
        $koneksi,
        'UPDATE akun_masyarakat SET status = ?, reviewed_by_admin_id = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = \'pending\'',
        'sii',
        [$newStatus, $reviewerId, $accountId]
    );
    $changed = $statement->affected_rows;
    $statement->close();

    if ($changed !== 1) {
        admin_module_flash('akun_masyarakat_flash', 'error', 'Akun tidak ditemukan atau sudah ditinjau oleh admin lain.');
        admin_akun_masyarakat_redirect();
    }

    admin_module_flash(
        'akun_masyarakat_flash',
        'success',
        $newStatus === 'approved'
            ? 'Akun masyarakat berhasil disetujui.'
            : 'Pendaftaran akun masyarakat ditolak.'
    );
} catch (Throwable $exception) {
    error_log('Admin community account review failed: ' . $exception->getMessage());
    admin_module_flash('akun_masyarakat_flash', 'error', 'Keputusan akun belum dapat disimpan. Silakan coba kembali.');
}

admin_akun_masyarakat_redirect();
