<?php
require_once __DIR__ . '/masyarakat_auth.php';
require_once __DIR__ . '/koneksi.php';

function bansos_page_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$programSummaries = [];
$fallbackProgramSummaries = [
    ['nama_program' => 'BPJS PBI Ketenagakerjaan', 'jumlah_penerima' => 67],
    ['nama_program' => 'PKH', 'jumlah_penerima' => 41],
    ['nama_program' => 'BPNT', 'jumlah_penerima' => 35],
    ['nama_program' => 'BLT 2024', 'jumlah_penerima' => 0],
    ['nama_program' => 'PSTN', 'jumlah_penerima' => 0],
];
$checkMessage = '';
$checkMessageType = '';
$checkResults = [];
$checkMatched = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!masyarakat_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $checkMessage = 'Permintaan tidak dapat diverifikasi. Muat ulang halaman lalu coba kembali.';
        $checkMessageType = 'error';
    } elseif (!$koneksi instanceof mysqli) {
        $checkMessage = 'Layanan cek bansos belum tersedia. Silakan hubungi kantor desa.';
        $checkMessageType = 'error';
    } else {
        $nik = $_POST['nik'] ?? '';
        $tanggalLahir = $_POST['tanggal_lahir'] ?? '';
        $nik = is_string($nik) ? trim($nik) : '';
        $tanggalLahir = is_string($tanggalLahir) ? trim($tanggalLahir) : '';

        $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $tanggalLahir);
        if (preg_match('/\A[0-9]{16}\z/', $nik) !== 1
            || !$birthDate
            || $birthDate->format('Y-m-d') !== $tanggalLahir
            || $birthDate > new DateTimeImmutable('today')) {
            $checkMessage = 'Masukkan NIK 16 digit dan tanggal lahir yang valid.';
            $checkMessageType = 'error';
        } else {
            $now = time();
            $attempts = $_SESSION['bansos_check_attempts'] ?? [];
            if (!is_array($attempts)) {
                $attempts = [];
            }
            $attempts = array_values(array_filter(
                $attempts,
                static fn ($attempt): bool => is_int($attempt) && $attempt > $now - 600
            ));

            if (count($attempts) >= 5) {
                $checkMessage = 'Batas pengecekan tercapai. Silakan coba kembali 10 menit lagi.';
                $checkMessageType = 'error';
            } else {
                $attempts[] = $now;
                $_SESSION['bansos_check_attempts'] = $attempts;

                try {
                    $identityStatement = $koneksi->prepare(
                        "SELECT id FROM penduduk WHERE nik = ? AND tanggal_lahir = ? AND status_data = 'aktif' LIMIT 1"
                    );
                    if (!$identityStatement) {
                        throw new RuntimeException('Identity check could not be prepared.');
                    }
                    $identityStatement->bind_param('ss', $nik, $tanggalLahir);
                    if (!$identityStatement->execute()) {
                        throw new RuntimeException('Identity check could not be executed.');
                    }
                    $identityResult = $identityStatement->get_result();
                    $identity = $identityResult ? $identityResult->fetch_assoc() : null;
                    $identityStatement->close();

                    if ($identity === null) {
                        $checkMessage = 'Data tidak cocok atau belum tercatat. Pastikan NIK dan tanggal lahir sesuai data yang tercatat di kantor desa.';
                        $checkMessageType = 'error';
                    } else {
                        $checkMatched = true;
                        $benefitStatement = $koneksi->prepare(
                            "SELECT bpr.nama_program, bp.nama_periode, bpn.status_penerima, bpn.tanggal_penyaluran
                            FROM bansos_penerima bpn
                            JOIN bansos_periode bp ON bp.id = bpn.periode_id
                            JOIN bansos_program bpr ON bpr.id = bp.program_id
                            WHERE bpn.penduduk_id = ?
                                AND bpn.status_penerima <> 'dibatalkan'
                                AND bp.status_periode = 'aktif'
                                AND bpr.status_program = 'aktif'
                            ORDER BY bp.tanggal_mulai DESC, bp.id DESC, bpr.nama_program ASC"
                        );
                        if (!$benefitStatement) {
                            throw new RuntimeException('Benefit check could not be prepared.');
                        }
                        $residentId = (int) $identity['id'];
                        $benefitStatement->bind_param('i', $residentId);
                        if (!$benefitStatement->execute()) {
                            throw new RuntimeException('Benefit check could not be executed.');
                        }
                        $benefitResult = $benefitStatement->get_result();
                        if ($benefitResult) {
                            while ($benefit = $benefitResult->fetch_assoc()) {
                                $checkResults[] = $benefit;
                            }
                        }
                        $benefitStatement->close();
                        $checkMessage = $checkResults === []
                            ? 'Data terverifikasi. Belum ada catatan penerima bansos pada periode aktif.'
                            : 'Data terverifikasi. Berikut status bansos pada periode aktif:';
                        $checkMessageType = 'success';
                    }
                } catch (Throwable $exception) {
                    error_log('Public bansos check failed: ' . $exception->getMessage());
                    $checkMessage = 'Pengecekan belum dapat dilakukan. Silakan coba lagi nanti atau hubungi kantor desa.';
                    $checkMessageType = 'error';
                    $checkResults = [];
                    $checkMatched = false;
                }
            }
        }
    }
}

if ($koneksi instanceof mysqli) {
    try {
        $summaryStatement = $koneksi->prepare(
            "SELECT p.nama_program,
                COUNT(DISTINCT CASE WHEN bp.id IS NOT NULL AND bpn.status_penerima <> 'dibatalkan' THEN bpn.penduduk_id END) AS jumlah_penerima
            FROM bansos_program p
            LEFT JOIN bansos_periode bp ON bp.program_id = p.id AND bp.status_periode = 'aktif'
            LEFT JOIN bansos_penerima bpn ON bpn.periode_id = bp.id
            WHERE p.status_program = 'aktif'
            GROUP BY p.id, p.nama_program
            ORDER BY p.nama_program ASC"
        );
        if (!$summaryStatement || !$summaryStatement->execute()) {
            throw new RuntimeException('Public bansos summary could not be loaded.');
        }
        $summaryResult = $summaryStatement->get_result();
        if ($summaryResult) {
            while ($program = $summaryResult->fetch_assoc()) {
                $programSummaries[] = $program;
            }
        }
        $summaryStatement->close();
    } catch (Throwable $exception) {
        error_log('Public bansos summary failed: ' . $exception->getMessage());
        $programSummaries = [];
    }
}

if ($programSummaries === []) {
    $programSummaries = $fallbackProgramSummaries;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informasi Bansos - Portal Resmi Desa Digital</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/public-notice.css?v=20261026" rel="stylesheet">
    <link href="assets/css/public-nav.css?v=20261117-desktop-nav" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

    <?php require __DIR__ . '/includes/public_notice.php'; ?>

    <!-- Header / Navbar -->
    <header class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-20">
            <a href="index.php" class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-amber-500 rounded-lg flex items-center justify-center font-bold text-gray-900 text-xl shadow-inner">D</div>
                <div>
                    <h1 class="font-extrabold text-xl tracking-tight text-gray-900 leading-none">DESA <span class="text-amber-500">DIGITAL</span></h1>
                    <p class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">Portal Resmi Layanan Desa</p>
                </div>
            </a>

            <?php require __DIR__ . '/includes/public_nav.php'; ?>

            <a href="login.php" class="hidden lg:inline-flex bg-blue-900 hover:bg-blue-800 text-white font-bold text-xs px-5 py-2.5 rounded-full transition shadow-md">
                LOGIN ADMIN
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-10 sm:px-6 lg:px-8">
        <section aria-labelledby="bansos-summary-title">
            <div class="mx-auto mb-9 max-w-2xl text-center">
                <span class="text-xs font-extrabold uppercase tracking-widest text-amber-500">Transparansi Penyaluran</span>
                <h2 id="bansos-summary-title" class="mt-2 text-2xl font-black text-gray-900 sm:text-3xl">Jumlah Penerima Bansos</h2>
                <p class="mt-2 text-sm leading-6 text-gray-500">Rekap penerima bantuan sosial di Desa Pemayahan.</p>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <?php foreach ($programSummaries as $program): ?>
                    <article class="flex min-h-36 items-center gap-6 rounded-lg border border-gray-100 bg-white px-5 py-6 shadow-md sm:px-8">
                        <div class="w-28 shrink-0 text-center">
                            <p class="text-5xl font-black leading-none text-black"><?php echo number_format((int) $program['jumlah_penerima'], 0, ',', '.'); ?></p>
                            <p class="mt-2 text-sm font-medium text-gray-800">Penduduk</p>
                        </div>
                        <div class="min-w-0 border-l border-gray-100 pl-5 text-gray-600">
                            <p class="text-sm font-semibold">mendapatkan bantuan</p>
                            <h3 class="mt-1 break-words text-base font-extrabold"><?php echo bansos_page_escape((string) $program['nama_program']); ?></h3>
                            </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section aria-labelledby="bansos-check-title" class="mt-10 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:p-8">
            <div class="mb-6 max-w-2xl">
                <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Layanan mandiri</p>
                <h2 id="bansos-check-title" class="mt-1 text-2xl font-black tracking-tight text-gray-900">Cek Penerima Bansos</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">Masukkan NIK dan tanggal lahir sesuai data yang tercatat di kantor desa. Hasil hanya menampilkan program dan status, tanpa nama atau alamat.</p>
            </div>

            <?php if ($checkMessage !== ''): ?>
                <div role="<?php echo $checkMessageType === 'success' ? 'status' : 'alert'; ?>" class="mb-5 rounded-lg border px-4 py-3 text-sm font-semibold <?php echo $checkMessageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900'; ?>">
                    <?php echo bansos_page_escape($checkMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($checkMatched && $checkResults !== []): ?>
                <div class="mb-6 divide-y divide-gray-100 rounded-lg border border-gray-200">
                    <?php foreach ($checkResults as $result): ?>
                        <?php
                        $statusLabel = $result['status_penerima'] === 'disalurkan' ? 'Sudah disalurkan' : 'Terdaftar';
                        $statusClass = $result['status_penerima'] === 'disalurkan'
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-amber-100 text-amber-900';
                        ?>
                        <div class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="font-bold text-gray-900"><?php echo bansos_page_escape((string) $result['nama_program']); ?></h3>
                                <p class="mt-1 text-sm text-gray-500"><?php echo bansos_page_escape((string) $result['nama_periode']); ?></p>
                            </div>
                            <span class="w-fit rounded-full px-3 py-1 text-xs font-extrabold <?php echo $statusClass; ?>">
                                <?php echo bansos_page_escape($statusLabel); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="bansos.php#bansos-check-title" class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <input type="hidden" name="csrf_token" value="<?php echo bansos_page_escape(masyarakat_csrf_token()); ?>">
                <div>
                    <label for="bansos-nik" class="mb-2 block text-sm font-bold text-gray-800">NIK</label>
                    <input id="bansos-nik" name="nik" type="text" inputmode="numeric" autocomplete="off" pattern="[0-9]{16}" maxlength="16" required placeholder="16 digit NIK" class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/10">
                </div>
                <div>
                    <label for="bansos-tanggal-lahir" class="mb-2 block text-sm font-bold text-gray-800">Tanggal lahir</label>
                    <input id="bansos-tanggal-lahir" name="tanggal_lahir" type="date" required class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/10">
                </div>
                <button type="submit" class="min-h-11 rounded-lg bg-amber-500 px-5 py-3 text-sm font-extrabold text-gray-950 transition hover:bg-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-500/30">Cek Status</button>
            </form>
        </section>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>

</body>
</html>