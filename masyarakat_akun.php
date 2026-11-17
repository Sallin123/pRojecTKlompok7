<?php
require_once __DIR__ . '/masyarakat_auth.php';
require_once __DIR__ . '/surat_draft.php';
require_once __DIR__ . '/koneksi.php';

function masyarakat_akun_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if (!masyarakat_is_authenticated()) {
    header('Location: masyarakat_login.php');
    exit;
}

$errorMessage = '';
$account = null;
try {
    $account = masyarakat_current_account($koneksi);
} catch (Throwable $exception) {
    error_log('Community account status lookup failed: ' . $exception->getMessage());
    $errorMessage = 'Status akun tidak dapat dimuat saat ini.';
}

if ($account === null && $errorMessage === '') {
    header('Location: masyarakat_login.php');
    exit;
}

$statusMessages = [
    'pending' => 'Akun menunggu persetujuan admin. Pengajuan surat belum tersedia.',
    'approved' => 'Akun telah disetujui. Anda dapat mengajukan surat dan melihat riwayat permohonan.',
    'rejected' => 'Akun belum disetujui. Hubungi kantor desa untuk informasi lebih lanjut.',
];
$draft = surat_draft_get();
$csrfToken = masyarakat_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Akun Masyarakat - Desa Pemayahan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="index.php" class="font-extrabold tracking-tight">DESA <span class="text-amber-600">PEMAYAHAN</span></a>
            <form method="POST" action="masyarakat_logout.php">
                <input type="hidden" name="csrf_token" value="<?php echo masyarakat_akun_escape($csrfToken); ?>">
                <button type="submit" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Keluar</button>
            </form>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Akun Masyarakat</p>
            <h1 class="mt-2 text-2xl font-black text-slate-950">Status akun</h1>
            <?php if ($errorMessage !== ''): ?>
                <div role="alert" class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800"><?php echo masyarakat_akun_escape($errorMessage); ?></div>
            <?php elseif ($account !== null): ?>
                <p class="mt-4 text-sm text-slate-600">Masuk sebagai <strong><?php echo masyarakat_akun_escape($account['email']); ?></strong></p>
                <div role="status" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950"><?php echo masyarakat_akun_escape($statusMessages[$account['status']] ?? 'Status akun tidak dikenal. Hubungi admin.'); ?></div>
                <?php if ($draft !== null && $account['status'] === 'approved'): ?>
                    <a href="surat.php?confirm=1" class="mt-5 inline-flex rounded-xl bg-amber-500 px-4 py-3 text-xs font-extrabold text-slate-950 hover:bg-amber-400">Lanjutkan draft permohonan surat</a>
                <?php elseif ($draft !== null && $account['status'] === 'pending'): ?>
                    <p class="mt-4 text-xs text-slate-600">Draft pengajuan tersimpan sementara selama 24 jam. Setelah persetujuan, masuk kembali untuk melanjutkan.</p>
                <?php endif; ?>
                <?php if ($account['status'] === 'approved'): ?>
                    <a href="riwayat_surat.php" class="mt-5 inline-flex rounded-xl border border-slate-200 px-4 py-3 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Riwayat permohonan surat</a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="index.php" class="mt-6 inline-flex rounded-xl bg-slate-950 px-4 py-3 text-xs font-extrabold text-white hover:bg-blue-950">Kembali ke website</a>
        </section>
    </main>
</body>
</html>
