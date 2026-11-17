<?php
require_once __DIR__ . '/masyarakat_auth.php';
masyarakat_require_authentication();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/includes/mobil_siaga_helpers.php';

function mobil_siaga_tracking_escape($value): string
{
    return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!masyarakat_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Sesi pelacakan tidak valid. Muat ulang halaman lalu coba kembali.';
        unset($_SESSION['mobil_siaga_tracking_code']);
    } else {
        $code = $_POST['kode_pengajuan'] ?? '';
        if (!is_string($code) || preg_match('/\AMS-[A-Fa-f0-9]{12}\z/', trim($code)) !== 1) {
            $error = 'Format kode pengajuan tidak sesuai.';
            unset($_SESSION['mobil_siaga_tracking_code']);
        } else {
            $_SESSION['mobil_siaga_tracking_code'] = strtoupper(trim($code));
            header('Location: mobil_siaga_lacak.php', true, 303);
            exit;
        }
    }
}

$application = null;
$history = [];
$lookupFailed = false;
$code = $_SESSION['mobil_siaga_tracking_code'] ?? '';
if (is_string($code) && $code !== '') {
    try {
        $statement = $koneksi->prepare(
            'SELECT id, kode_pengajuan, tanggal_penggunaan, jam_mulai, tujuan_penggunaan, status
             FROM mobil_siaga_pengajuan WHERE kode_pengajuan = ? LIMIT 1'
        );
        if (!$statement) {
            throw new RuntimeException('Application tracking query could not be prepared.');
        }
        if (!$statement->bind_param('s', $code) || !$statement->execute()) {
            $statement->close();
            throw new RuntimeException('Application tracking query failed.');
        }
        $statement->bind_result($id, $storedCode, $useDate, $requestedTime, $purpose, $status);
        if ($statement->fetch() === true) {
            $application = [
                'id' => (int) $id,
                'kode_pengajuan' => (string) $storedCode,
                'tanggal_penggunaan' => (string) $useDate,
                'jam_mulai' => (string) $requestedTime,
                'tujuan_penggunaan' => (string) $purpose,
                'status' => (string) $status,
            ];
        }
        $statement->close();

        if ($application !== null) {
            $history = mobil_siaga_list_application_history($koneksi, $application['id']);
        }
    } catch (Throwable $exception) {
        error_log('Public Mobil Siaga tracking lookup failed: ' . $exception->getMessage());
        $lookupFailed = true;
    }
}

$csrfToken = masyarakat_csrf_token();
$labels = [
    'menunggu_verifikasi' => 'Menunggu verifikasi',
    'diproses' => 'Diproses',
    'disetujui' => 'Disetujui',
    'ditolak' => 'Ditolak',
    'selesai' => 'Selesai',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lacak Pengajuan Mobil Siaga - Desa Pemayahan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/public-nav.css?v=20261117-desktop-nav" rel="stylesheet">
    <style>body{font-family:'Plus Jakarta Sans',sans-serif}</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
<header class="sticky top-0 z-50 border-b border-slate-100 bg-white shadow-sm">
    <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <a href="index.php" class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500 text-xl font-black text-slate-950">D</div><div><h1 class="text-xl font-extrabold tracking-tight text-slate-900">DESA <span class="text-amber-500">DIGITAL</span></h1><p class="mt-1 text-[10px] uppercase tracking-widest text-slate-500">Portal Resmi Layanan Desa</p></div></a>
        <?php require __DIR__ . '/includes/public_nav.php'; ?>
        <a href="mobil_siaga.php" class="hidden rounded-full bg-slate-950 px-5 py-2.5 text-xs font-bold text-white lg:inline-flex">Mobil Siaga</a>
    </div>
</header>
<main class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:py-14">
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-9">
        <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Layanan Mobil Siaga</p>
        <h2 class="mt-2 text-2xl font-black text-slate-950">Lacak pengajuan</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">Masukkan kode unik pengajuan untuk melihat status dan riwayat perubahannya. Halaman ini tidak memerlukan login.</p>
        <form method="post" class="mt-6 flex flex-col gap-3 sm:flex-row">
            <input type="hidden" name="csrf_token" value="<?php echo mobil_siaga_tracking_escape($csrfToken); ?>">
            <label class="sr-only" for="kode_pengajuan">Kode pengajuan</label>
            <input id="kode_pengajuan" name="kode_pengajuan" value="<?php echo mobil_siaga_tracking_escape($code); ?>" placeholder="Contoh: MS-XXXXXXXXXXXX" required maxlength="15" class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold uppercase tracking-wider">
            <button type="submit" class="rounded-xl bg-amber-500 px-5 py-3 text-sm font-extrabold text-slate-950 hover:bg-amber-400">Periksa status</button>
        </form>
        <?php if ($error !== ''): ?><p role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800"><?php echo mobil_siaga_tracking_escape($error); ?></p><?php endif; ?>
        <?php if ($lookupFailed): ?><p role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">Informasi pengajuan belum dapat diperiksa. Silakan coba kembali nanti.</p>
        <?php elseif (is_string($code) && $code !== ''): ?>
            <?php if ($application === null): ?><p role="status" class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">Pengajuan dengan kode tersebut tidak ditemukan. Pastikan kode dimasukkan dengan benar.</p>
            <?php else: ?>
                <div class="mt-6 rounded-2xl bg-slate-950 p-5 text-white">
                    <p class="text-xs font-bold uppercase tracking-widest text-amber-400">Kode pengajuan</p>
                    <p class="mt-1 text-lg font-black tracking-widest"><?php echo mobil_siaga_tracking_escape($application['kode_pengajuan']); ?></p>
                    <p class="mt-4 text-xs font-bold uppercase tracking-widest text-slate-400">Status saat ini</p>
                    <p class="mt-1 text-xl font-black"><?php echo mobil_siaga_tracking_escape($labels[$application['status']] ?? 'Status belum tersedia'); ?></p>
                    <div class="mt-4 grid gap-2 text-sm text-slate-300 sm:grid-cols-2"><p>Tanggal dimohonkan: <span class="font-bold text-white"><?php echo mobil_siaga_tracking_escape($application['tanggal_penggunaan']); ?></span></p><p>Waktu preferensi: <span class="font-bold text-white"><?php echo mobil_siaga_tracking_escape(substr($application['jam_mulai'], 0, 5)); ?></span></p><p>Tujuan: <span class="font-bold text-white"><?php echo mobil_siaga_tracking_escape($application['tujuan_penggunaan']); ?></span></p></div>
                    <p class="mt-2 text-xs text-slate-400">Tanggal dan waktu di atas adalah permohonan warga, bukan jadwal keberangkatan yang dikonfirmasi.</p>
                </div>
                <div class="mt-7"><h3 class="text-lg font-black text-slate-950">Riwayat status</h3>
                    <?php if ($history === []): ?><p class="mt-3 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">Riwayat status belum tersedia.</p>
                    <?php else: ?><ol class="mt-4 space-y-3"><?php foreach ($history as $item): ?><li class="relative rounded-xl border border-slate-200 p-4"><p class="font-extrabold text-slate-900"><?php echo mobil_siaga_tracking_escape($labels[$item['status'] ?? ''] ?? 'Status diperbarui'); ?></p><p class="mt-1 text-xs text-slate-500"><?php echo mobil_siaga_tracking_escape($item['dibuat_at'] ?? ''); ?></p><?php if (!empty($item['pesan'])): ?><p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600"><?php echo mobil_siaga_tracking_escape($item['pesan']); ?></p><?php endif; ?></li><?php endforeach; ?></ol><?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
<script src="assets/js/public-nav.js?v=20261110"></script>
</body>
</html>
