<?php
require_once __DIR__ . '/masyarakat_auth.php';
masyarakat_require_authentication();
require_once __DIR__ . '/koneksi.php';

function posyandu_public_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$profile = null;
$schedules = [];
$activities = [];
$pageError = false;

try {
    if (!$koneksi instanceof mysqli) {
        throw new RuntimeException('Posyandu database connection is unavailable.');
    }

    $profileStatement = $koneksi->prepare(
        'SELECT nama_posyandu, deskripsi, lokasi, waktu_layanan
        FROM posyandu_profil WHERE id = 1 LIMIT 1'
    );
    if (!$profileStatement || !$profileStatement->execute()) {
        if ($profileStatement instanceof mysqli_stmt) {
            $profileStatement->close();
        }
        throw new RuntimeException('Public Posyandu profile query failed.');
    }
    $profileResult = $profileStatement->get_result();
    $profile = $profileResult ? $profileResult->fetch_assoc() : null;
    $profileStatement->close();

    $agendaStatement = $koneksi->prepare(
        "SELECT id, jenis, judul, deskripsi, tanggal, waktu_mulai, waktu_selesai, lokasi
        FROM posyandu_agenda
        WHERE status_publikasi = 'publikasi'
            AND jenis IN ('jadwal', 'kegiatan', 'imunisasi')
        ORDER BY tanggal ASC, waktu_mulai ASC, id DESC"
    );
    if (!$agendaStatement || !$agendaStatement->execute()) {
        if ($agendaStatement instanceof mysqli_stmt) {
            $agendaStatement->close();
        }
        throw new RuntimeException('Public Posyandu agenda query failed.');
    }
    $agendaResult = $agendaStatement->get_result();
    if ($agendaResult) {
        while ($row = $agendaResult->fetch_assoc()) {
            if ($row['jenis'] === 'jadwal') {
                $schedules[] = $row;
            } else {
                $activities[] = $row;
            }
        }
    }
    $agendaStatement->close();

} catch (Throwable $exception) {
    error_log('Public Posyandu page could not be loaded: ' . $exception->getMessage());
    $pageError = true;
    $profile = null;
    $schedules = [];
    $activities = [];
}

function posyandu_public_date(string $value): string
{
    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date('d/m/Y', $timestamp);
}

function posyandu_public_time(?string $value): string
{
    return $value === null || $value === '' ? '' : substr($value, 0, 5);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posyandu - Portal Resmi Desa Pemayahan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/public-notice.css?v=20261026" rel="stylesheet">
    <link href="assets/css/public-nav.css?v=20261117-desktop-nav" rel="stylesheet">
    <style>body { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gray-50 text-gray-800 antialiased">
    <div class="flex min-h-screen flex-col">
    <?php require __DIR__ . '/includes/public_notice.php'; ?>
    <header class="sticky top-0 z-50 border-b border-gray-100 bg-white shadow-sm">
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="index.php" class="flex items-center space-x-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500 text-xl font-bold text-gray-900 shadow-inner">D</div>
                <div>
                    <h1 class="text-xl font-extrabold leading-none tracking-tight text-gray-900">DESA <span class="text-amber-500">DIGITAL</span></h1>
                    <p class="mt-1 text-[10px] uppercase tracking-widest text-gray-500">Portal Resmi Layanan Desa</p>
                </div>
            </a>
            <?php require __DIR__ . '/includes/public_nav.php'; ?>
            <a href="login.php" class="hidden rounded-full bg-blue-900 px-5 py-2.5 text-xs font-bold text-white shadow-md transition hover:bg-blue-800 lg:inline-flex">LOGIN ADMIN</a>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
        <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 px-6 py-8 text-white shadow-xl sm:px-10 sm:py-10">
            <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-amber-400">Layanan Kesehatan Desa</p>
            <h2 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Posyandu Desa Pemayahan</h2>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">Informasi profil, jadwal pelayanan, dan kegiatan Posyandu untuk masyarakat.</p>
        </section>

        <?php if ($pageError): ?>
            <section role="alert" class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center text-sm leading-6 text-amber-950">
                Informasi Posyandu belum dapat dimuat saat ini. Silakan coba kembali nanti.
            </section>
        <?php else: ?>
            <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-xs font-extrabold uppercase tracking-widest text-amber-700">Profil</p>
                <h3 class="mt-2 text-2xl font-black text-slate-950">
                    <?php echo posyandu_public_escape((string) ($profile['nama_posyandu'] ?? 'Posyandu Desa Pemayahan')); ?>
                </h3>
                <?php if (!empty($profile['deskripsi'])): ?>
                    <p class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-600"><?php echo posyandu_public_escape((string) $profile['deskripsi']); ?></p>
                <?php else: ?>
                    <p class="mt-4 text-sm leading-7 text-slate-600">Informasi profil Posyandu akan tersedia setelah diperbarui oleh pengelola.</p>
                <?php endif; ?>
                <div class="mt-6 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Lokasi</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800"><?php echo posyandu_public_escape((string) ($profile['lokasi'] ?? 'Informasi belum tersedia')); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Waktu pelayanan</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800"><?php echo posyandu_public_escape((string) ($profile['waktu_layanan'] ?? 'Lihat jadwal pelayanan')); ?></p>
                    </div>
                </div>
            </section>

            <section class="mt-10">
                <div class="mb-5">
                    <p class="text-xs font-extrabold uppercase tracking-widest text-amber-700">Layanan</p>
                    <h3 class="mt-1 text-2xl font-black text-slate-950">Jadwal Posyandu</h3>
                </div>
                <?php if ($schedules === []): ?>
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">Belum ada jadwal Posyandu yang dipublikasikan.</div>
                <?php else: ?>
                    <div class="grid gap-4 md:grid-cols-2">
                        <?php foreach ($schedules as $schedule):
                            $startTime = posyandu_public_time($schedule['waktu_mulai'] ?? null);
                            $endTime = posyandu_public_time($schedule['waktu_selesai'] ?? null);
                            $timeLabel = $startTime;
                            if ($endTime !== '') {
                                $timeLabel .= ($timeLabel === '' ? '' : '–') . $endTime;
                            }
                        ?>
                            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-xs font-extrabold uppercase tracking-wider text-amber-700"><?php echo posyandu_public_date((string) $schedule['tanggal']); ?></p>
                                <h4 class="mt-2 text-lg font-extrabold text-slate-950"><?php echo posyandu_public_escape((string) $schedule['judul']); ?></h4>
                                <?php if ($timeLabel !== '' || !empty($schedule['lokasi'])): ?>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">
                                        <?php if ($timeLabel !== ''): ?><?php echo posyandu_public_escape($timeLabel); ?><?php endif; ?>
                                        <?php if (!empty($schedule['lokasi'])): ?><?php echo $timeLabel !== '' ? ' · ' : ''; ?><?php echo posyandu_public_escape((string) $schedule['lokasi']); ?><?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($schedule['deskripsi'])): ?>
                                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600"><?php echo posyandu_public_escape((string) $schedule['deskripsi']); ?></p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="mt-10">
                <div class="mb-5">
                    <p class="text-xs font-extrabold uppercase tracking-widest text-amber-700">Informasi Publik</p>
                    <h3 class="mt-1 text-2xl font-black text-slate-950">Informasi Kegiatan</h3>
                </div>
                <?php if ($activities === []): ?>
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">Belum ada informasi kegiatan yang dipublikasikan.</div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($activities as $activity): ?>
                            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-amber-100 px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider text-amber-900"><?php echo $activity['jenis'] === 'imunisasi' ? 'Informasi Imunisasi' : 'Kegiatan'; ?></span>
                                    <span class="text-xs font-semibold text-slate-500"><?php echo posyandu_public_date((string) $activity['tanggal']); ?></span>
                                </div>
                                <h4 class="mt-3 text-lg font-extrabold text-slate-950"><?php echo posyandu_public_escape((string) $activity['judul']); ?></h4>
                                <?php if (!empty($activity['deskripsi'])): ?>
                                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600"><?php echo posyandu_public_escape((string) $activity['deskripsi']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($activity['lokasi'])): ?>
                                    <p class="mt-3 text-xs font-semibold text-slate-500">Lokasi: <?php echo posyandu_public_escape((string) $activity['lokasi']); ?></p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        <?php endif; ?>
    </main>
    <?php require __DIR__ . '/includes/footer.php'; ?>
    </div>
</body>
</html>
