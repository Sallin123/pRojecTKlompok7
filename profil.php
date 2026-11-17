<?php
require_once __DIR__ . '/masyarakat_auth.php';
$profileValues = [
    'visi' => '"Mewujudkan Desa Digital yang Mandiri, Sejahtera, Transparan, serta Unggul dalam Pelayanan Publik dan Kebudayaan."',
    'misi' => "Meningkatkan kualitas pelayanan administrasi secara digital.\nMendorong transparansi pengelolaan anggaran keuangan desa.\nMengembangkan potensi ekonomi dan pemberdayaan warga lokal.\nMewujudkan lingkungan desa yang tertib, aman, dan berbudaya.",
    'sejarah' => 'Desa ini didirikan sebagai kawasan pemukiman dan pertanian mandiri. Seiring berkembangnya teknologi dan zaman, desa terus bertransformasi menjadi desa berbasis tata kelola digital untuk memudahkan seluruh warga dalam mendapatkan pelayanan publik yang transparan dan cepat.',
];
$activePerangkat = [];

if (!defined('DB_CONNECTION_OPTIONAL')) {
    define('DB_CONNECTION_OPTIONAL', true);
}
require_once __DIR__ . '/koneksi.php';
if ($koneksi instanceof mysqli) {
    try {
        $profileStatement = $koneksi->prepare('SELECT visi, misi, sejarah FROM profil_desa WHERE id = 1');
        if (!$profileStatement || !$profileStatement->execute()) {
            throw new RuntimeException('Public profile query failed.');
        }

        $profileResult = $profileStatement->get_result();
        $storedProfile = $profileResult ? $profileResult->fetch_assoc() : null;
        $profileStatement->close();
        if (is_array($storedProfile)) {
            foreach (array_keys($profileValues) as $field) {
                if (is_string($storedProfile[$field]) && trim($storedProfile[$field]) !== '') {
                    $profileValues[$field] = $storedProfile[$field];
                }
            }
        }
    } catch (Throwable $exception) {
        error_log('Public profile content could not be loaded: ' . $exception->getMessage());
    }

    try {
        $officialsStatement = $koneksi->prepare(
            'SELECT id, nama_lengkap, jabatan, informasi, foto FROM perangkat_desa WHERE status_data = \'aktif\' ORDER BY urutan, id'
        );
        if (!$officialsStatement || !$officialsStatement->execute()) {
            throw new RuntimeException('Public officials query failed.');
        }

        $officialsResult = $officialsStatement->get_result();
        if ($officialsResult) {
            while ($official = $officialsResult->fetch_assoc()) {
                $activePerangkat[] = $official;
            }
        }
        $officialsStatement->close();
    } catch (Throwable $exception) {
        error_log('Public officials could not be loaded: ' . $exception->getMessage());
    }
}
$profileMissionItems = array_values(array_filter(
    array_map('trim', preg_split('/\r\n|\r|\n/', $profileValues['misi']) ?: []),
    static function (string $item): bool {
        return $item !== '';
    }
));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Desa - Portal Resmi Desa Digital</title>
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

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto px-4 py-12 space-y-12">
        
        <!-- Visi & Misi -->
        <div class="bg-white rounded-2xl p-6 md:p-10 shadow-sm border border-gray-100">
            <div class="text-center max-w-2xl mx-auto mb-8">
                <span class="text-amber-500 text-xs font-extrabold uppercase tracking-widest">VISI & MISI</span>
                <h2 class="text-2xl md:text-3xl font-black text-gray-900 mt-1">Pedoman Pembangunan Desa</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="bg-amber-50/50 border border-amber-100 p-6 rounded-2xl">
                    <h3 class="font-extrabold text-lg text-amber-900 mb-3">Visi Desa</h3>
                    <p class="text-gray-700 text-sm leading-relaxed font-medium">
                        <?php echo nl2br(htmlspecialchars($profileValues['visi'], ENT_QUOTES, 'UTF-8')); ?>
                    </p>
                </div>

                <div class="bg-blue-50/50 border border-blue-100 p-6 rounded-2xl">
                    <h3 class="font-extrabold text-lg text-blue-900 mb-3">Misi Desa</h3>
                    <ul class="text-gray-700 text-sm space-y-2 list-disc list-inside">
                        <?php foreach ($profileMissionItems as $mission): ?>
                            <li><?php echo htmlspecialchars($mission, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Sejarah Singkat -->
        <div class="bg-white rounded-2xl p-6 md:p-10 shadow-sm border border-gray-100">
            <div class="mb-6">
                <span class="text-amber-500 text-xs font-extrabold uppercase tracking-widest">SEJARAH DESA</span>
                <h2 class="text-2xl font-black text-gray-900 mt-1">Sekilas Tentang Desa Kami</h2>
            </div>
            <p class="text-gray-600 text-sm leading-relaxed mb-4">
                <?php echo nl2br(htmlspecialchars($profileValues['sejarah'], ENT_QUOTES, 'UTF-8')); ?>
            </p>
        </div>

        <!-- Struktur Organisasi / Pejabat Desa -->
        <div id="struktur-perangkat-desa" class="scroll-mt-24 bg-white rounded-2xl p-6 md:p-10 shadow-sm border border-gray-100">
            <div class="text-center mb-8">
                <span class="text-amber-500 text-xs font-extrabold uppercase tracking-widest">APARATUR DESA</span>
                <h2 class="text-2xl md:text-3xl font-black text-gray-900 mt-1">Struktur Perangkat Desa</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if ($activePerangkat === []): ?>
                    <p class="col-span-full py-6 text-center text-sm text-gray-500">Data perangkat desa belum tersedia.</p>
                <?php else: ?>
                    <?php foreach ($activePerangkat as $official): ?>
                        <article class="bg-gray-50 p-6 rounded-2xl text-center border border-gray-200">
                            <div class="relative mx-auto mb-4 aspect-[3/4] h-[200px] w-[150px] overflow-hidden rounded-[14px] bg-amber-500/20 text-amber-700 flex items-center justify-center text-3xl font-bold">
                                <span aria-hidden="true">👤</span>
                                <?php if (is_string($official['foto'] ?? null) && $official['foto'] !== ''): ?>
                                    <img src="perangkat_public_image.php?id=<?php echo (int) $official['id']; ?>" alt="Foto <?php echo htmlspecialchars((string) $official['nama_lengkap'], ENT_QUOTES, 'UTF-8'); ?>" onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover object-center">
                                <?php endif; ?>
                            </div>
                            <h3 class="font-extrabold text-gray-900 text-base"><?php echo htmlspecialchars((string) $official['nama_lengkap'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="text-xs text-amber-600 font-bold uppercase mt-1"><?php echo htmlspecialchars((string) $official['jabatan'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php if (is_string($official['informasi'] ?? null) && trim($official['informasi']) !== ''): ?>
                                <p class="mt-3 text-sm leading-relaxed text-gray-600 whitespace-pre-line"><?php echo htmlspecialchars($official['informasi'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>

</body>
</html>