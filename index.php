<?php
require_once __DIR__ . '/masyarakat_auth.php';

$homeAduanSuccess = $_SESSION['aduan_submission_flash'] ?? null;
$homeAduanError = $_SESSION['aduan_form_error'] ?? null;
unset($_SESSION['aduan_submission_flash'], $_SESSION['aduan_form_error']);
if (is_array($homeAduanSuccess) || is_string($homeAduanError)) {
    header('Cache-Control: no-store, private');
}

$homeProfileName = 'H. Ahmad Subagja, S.STP., M.Si';
$homeProfileSpeech = 'Memimpin pelaksanaan pelayanan administrasi publik, pembangunan infrastruktur desa, pemberdayaan ekonomi masyarakat, serta menjaga ketertiban dan ketenteraman umum demi terwujudnya Desa Pemayahan yang mandiri, maju, dan sejahtera.';
$homeProfileImage = 'Kepala-Desa.jpg';
$homeBannerTitle = 'DESA PEMAYAHAN';
$homeBannerDescription = 'Pusat Informasi, Penegakan Peraturan Desa, Sosialisasi Ketertiban Umum, dan Kanal Pengaduan Terpadu Masyarakat Desa Pemayahan.';
$homeBannerImage = 'desa-pemayahan.jpg';
$homePerangkatItems = [];
$homePotensiItems = [];
$homeGalleryItems = [];
$homeNewsItems = [];
$homeNewsError = false;
$homeGalleryError = false;

function home_public_date(string $date): string
{
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
        return $date;
    }

    $months = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];

    return $dateObject->format('d') . ' ' . $months[(int) $dateObject->format('n')] . ' ' . $dateObject->format('Y');
}

if (!defined('DB_CONNECTION_OPTIONAL')) {
    define('DB_CONNECTION_OPTIONAL', true);
}
require_once __DIR__ . '/koneksi.php';
if ($koneksi instanceof mysqli) {
    try {
        $potensiStatement = $koneksi->prepare(
            'SELECT id, kategori, nama_file FROM potensi_desa ORDER BY id ASC LIMIT 6'
        );
        if (!$potensiStatement || !$potensiStatement->execute()) {
            throw new RuntimeException('Home Potensi query failed.');
        }

        $potensiResult = $potensiStatement->get_result();
        if ($potensiResult) {
            while ($potensiRow = $potensiResult->fetch_assoc()) {
                $homePotensiItems[] = $potensiRow;
            }
        }
        $potensiStatement->close();
    } catch (Throwable $exception) {
        error_log('Home Potensi could not be loaded: ' . $exception->getMessage());
    }

    try {
        $newsStatement = $koneksi->prepare(
            'SELECT id, tanggal, judul, ringkasan, isi, kategori, gambar FROM berita ORDER BY tanggal DESC, id DESC LIMIT 3'
        );
        if (!$newsStatement || !$newsStatement->execute()) {
            if ($newsStatement instanceof mysqli_stmt) {
                $newsStatement->close();
            }
            throw new RuntimeException('Home Berita query failed.');
        }

        $newsResult = $newsStatement->get_result();
        if ($newsResult) {
            while ($newsRow = $newsResult->fetch_assoc()) {
                $homeNewsItems[] = $newsRow;
            }
        }
        $newsStatement->close();
    } catch (Throwable $exception) {
        error_log('Home Berita could not be loaded: ' . $exception->getMessage());
        $homeNewsError = true;
    }

    try {
        $galleryStatement = $koneksi->prepare(
            'SELECT id, judul, keterangan, kategori, tanggal_dokumentasi FROM galeri WHERE status_publikasi = \'publikasi\' ORDER BY tanggal_dokumentasi DESC, id DESC LIMIT 4'
        );
        if (!$galleryStatement || !$galleryStatement->execute()) {
            if ($galleryStatement instanceof mysqli_stmt) {
                $galleryStatement->close();
            }
            throw new RuntimeException('Home Galeri query failed.');
        }

        $galleryResult = $galleryStatement->get_result();
        if ($galleryResult) {
            while ($galleryRow = $galleryResult->fetch_assoc()) {
                $homeGalleryItems[] = $galleryRow;
            }
        }
        $galleryStatement->close();
    } catch (Throwable $exception) {
        error_log('Home Galeri could not be loaded: ' . $exception->getMessage());
        $homeGalleryError = true;
    }

    try {
        $perangkatStatement = $koneksi->prepare(
            'SELECT id, nama_lengkap, jabatan, foto FROM perangkat_desa WHERE status_data = \'aktif\' ORDER BY urutan, id LIMIT 4'
        );
        if (!$perangkatStatement || !$perangkatStatement->execute()) {
            throw new RuntimeException('Home perangkat query failed.');
        }

        $perangkatResult = $perangkatStatement->get_result();
        if ($perangkatResult) {
            while ($perangkatRow = $perangkatResult->fetch_assoc()) {
                $homePerangkatItems[] = $perangkatRow;
            }
        }
        $perangkatStatement->close();
    } catch (Throwable $exception) {
        error_log('Home perangkat could not be loaded: ' . $exception->getMessage());
    }

    try {
        $bannerStatement = $koneksi->prepare('SELECT judul, deskripsi, nama_file, updated_at FROM beranda_banner WHERE id = 1');
        if (!$bannerStatement || !$bannerStatement->execute()) {
            throw new RuntimeException('Home banner query failed.');
        }

        $bannerResult = $bannerStatement->get_result();
        $homeBanner = $bannerResult ? $bannerResult->fetch_assoc() : null;
        $bannerStatement->close();
        if (is_array($homeBanner)) {
            if (is_string($homeBanner['judul']) && trim($homeBanner['judul']) !== '') {
                $homeBannerTitle = $homeBanner['judul'];
            }
            if (is_string($homeBanner['deskripsi']) && trim($homeBanner['deskripsi']) !== '') {
                $homeBannerDescription = $homeBanner['deskripsi'];
            }
            if (is_string($homeBanner['nama_file'] ?? null) && trim($homeBanner['nama_file']) !== '') {
                $bannerVersion = is_string($homeBanner['updated_at'] ?? null)
                    ? $homeBanner['updated_at']
                    : $homeBanner['nama_file'];
                $homeBannerImage = 'beranda_banner_image.php?v=' . rawurlencode($bannerVersion);
            }
        }
    } catch (Throwable $exception) {
        error_log('Home banner content could not be loaded: ' . $exception->getMessage());
    }

    try {
        $profileStatement = $koneksi->prepare('SELECT nama_kepala_desa, sambutan, foto_kepala_desa FROM profil_desa WHERE id = 1');
        if (!$profileStatement || !$profileStatement->execute()) {
            throw new RuntimeException('Home profile query failed.');
        }

        $profileResult = $profileStatement->get_result();
        $homeProfile = $profileResult ? $profileResult->fetch_assoc() : null;
        $profileStatement->close();
        if (is_array($homeProfile)) {
            if (is_string($homeProfile['nama_kepala_desa']) && trim($homeProfile['nama_kepala_desa']) !== '') {
                $homeProfileName = $homeProfile['nama_kepala_desa'];
            }
            if (is_string($homeProfile['sambutan']) && trim($homeProfile['sambutan']) !== '') {
                $homeProfileSpeech = $homeProfile['sambutan'];
            }
            if (is_string($homeProfile['foto_kepala_desa']) && $homeProfile['foto_kepala_desa'] !== '') {
                $homeProfileImage = 'profil_image.php';
            }
        }
    } catch (Throwable $exception) {
        error_log('Home profile content could not be loaded: ' . $exception->getMessage());
    }
} else {
    $homeGalleryError = true;
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Resmi Desa Pemayahan</title>
    
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS CSS (Animate On Scroll) -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Typed.js (Efek Ketik Otomatis) -->
    <script src="https://cdn.jsdelivr.net/npm/typed.js@2.0.12"></script>

    <link href="assets/css/index.css?v=hero-layout-20261021" rel="stylesheet">
    <link href="assets/css/public-notice.css?v=20261026" rel="stylesheet">
    <link href="assets/css/public-nav.css?v=20261117-desktop-nav" rel="stylesheet">
    <style>
        .potential-artwork {
            width: min(100%, 22rem);
            aspect-ratio: 1;
            isolation: isolate;
            border: .5rem solid #fff;
            border-radius: 50%;
            background: #f1f5f9;
            box-shadow: 0 1rem 2.5rem rgba(15, 23, 42, .16);
        }
        .potential-artwork::before {
            position: absolute;
            z-index: 4;
            inset: .8rem;
            border: 1px solid rgba(255, 255, 255, .75);
            border-radius: 50%;
            content: "";
            pointer-events: none;
        }
        .potential-artwork__image {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .potential-artwork__facet {
            position: absolute;
            z-index: 2;
            inset: 0;
            overflow: hidden;
        }
        .potential-artwork__facet img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .potential-artwork__facet--right {
            clip-path: polygon(58% 0, 100% 0, 100% 100%, 76% 100%, 48% 47%);
        }
        .potential-artwork__facet--lower {
            clip-path: polygon(0 59%, 39% 43%, 76% 100%, 0 100%);
        }
        .potential-artwork__facet--right::after,
        .potential-artwork__facet--lower::after {
            position: absolute;
            inset: 0;
            border: .25rem solid rgba(255, 255, 255, .9);
            content: "";
            pointer-events: none;
        }
        .potential-artwork__accent {
            position: absolute;
            z-index: 3;
            inset: 0;
            pointer-events: none;
        }
        .potential-artwork__accent::before {
            position: absolute;
            inset: 0;
            background: rgba(245, 158, 11, .82);
            clip-path: polygon(0 27%, 31% 0, 37% 0, 0 34%);
            content: "";
        }
        .potential-carousel-slide[hidden] {
            display: none;
        }
        @media (max-width: 640px) {
            .potential-artwork {
                width: min(78vw, 19rem);
                border-width: .4rem;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .potential-carousel-slide,
            .potential-carousel-control {
                transition: none !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased overflow-x-hidden">

    <?php require __DIR__ . '/includes/public_notice.php'; ?>

    <!-- MAIN HEADER & NAVIGATION BAR -->
    <header id="main-header" class="z-40">
        <div id="header-container" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-20">
            <a href="index.php" class="flex items-center space-x-3 group">
                <div class="w-10 h-10 bg-amber-500 rounded-lg flex items-center justify-center font-bold text-gray-900 text-xl shadow-inner group-hover:scale-105 transition duration-300">P</div>
                <div>
                    <h1 class="font-extrabold text-xl tracking-tight text-gray-900 leading-none">DESA <span class="text-amber-500">PEMAYAHAN</span></h1>
                    <p class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">Portal Resmi Layanan Desa</p>
                </div>
            </a>

            <div class="hidden xl:block w-1/5">
                <form action="berita.php" method="GET" class="relative">
                    <input type="text" name="q" placeholder="Cari..." class="w-full bg-gray-100 border border-transparent rounded-full py-2 pl-9 pr-4 text-xs focus:outline-none focus:bg-white focus:border-amber-500 transition">
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </form>
            </div>

            <?php require __DIR__ . '/includes/public_nav.php'; ?>

            <a href="login.php" class="hidden lg:inline-flex bg-blue-900 hover:bg-blue-800 text-white font-bold text-xs px-5 py-2.5 rounded-full transition shadow-md hover:shadow-lg hover:-translate-y-0.5 transform duration-200">
                LOGIN ADMIN
            </a>
        </div>
    </header>

    <!-- HERO SECTION (PARALLAX + SLOW ZOOM + FADE-OUT ON SCROLL) -->
    

<section id="hero-section" class="relative bg-gray-900 text-white flex items-center justify-center overflow-hidden">

        
    
<!-- Background Image Parallax -->
<div id="hero-bg"
     class="absolute inset-0 bg-cover bg-center parallax-bg"
    style="background-image: linear-gradient(rgba(15, 23, 42, 0.30), rgba(15, 23, 42, 0.45)), url('<?php echo htmlspecialchars($homeBannerImage, ENT_QUOTES, 'UTF-8'); ?>');">
</div>

        <!-- Hero Content -->
        <div id="hero-content" class="relative z-10 max-w-4xl mx-auto text-center px-4 pt-12 pb-20 parallax-content">
            
            <!-- Badge dengan Live Indicator Dot Kelap-Kelip -->
            <div data-aos="fade-down" data-aos-duration="1000">
                <span class="inline-flex items-center gap-2.5 bg-black/60 border border-amber-500/40 text-amber-400 text-xs font-bold px-4 py-1.5 rounded-full mb-6 backdrop-blur-md uppercase tracking-wider shadow-lg">
                    <span class="w-2.5 h-2.5 bg-amber-400 rounded-full animate-live-dot"></span>
                    PORTAL RESMI DESA PEMAYAHAN
                </span>
            </div>

            <!-- Judul Utama + Efek Ketik -->
            <h1 class="text-3xl md:text-5xl font-black tracking-tight leading-tight mb-4 uppercase min-h-[60px]" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="200">
            <?php echo htmlspecialchars($homeBannerTitle, ENT_QUOTES, 'UTF-8'); ?> <br><span id="typed-text" class="text-amber-400"></span>
            </h1>

            <p class="text-gray-300 text-sm md:text-base max-w-2xl mx-auto mb-8 leading-relaxed" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="400">
                <?php echo nl2br(htmlspecialchars($homeBannerDescription, ENT_QUOTES, 'UTF-8')); ?>
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="600">
                <a href="aduan.php" class="w-full sm:w-auto bg-amber-500 hover:bg-amber-400 text-gray-950 font-extrabold px-8 py-3.5 rounded-xl shadow-lg transition duration-300 text-center text-sm btn-glow">
                    Buat Pengaduan Online
                </a>
                <a href="surat.php" class="w-full sm:w-auto bg-slate-800/80 hover:bg-slate-800 text-white font-bold px-8 py-3.5 rounded-xl border border-slate-600 transition duration-300 text-center text-sm transform hover:-translate-y-1 backdrop-blur-sm">
                    Permohonan Surat Online
                </a>
            </div>
        </div>
    </section>

    
<!-- QUICK MENU CARDS DI BAWAH HERO -->
<section class="max-w-5xl mx-auto px-4 mt-8 md:mt-10 mb-8 relative z-10">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-white p-4 rounded-2xl shadow-xl border border-gray-100">
            <a href="surat.php" class="p-4 rounded-xl hover:bg-amber-50 transition text-center group reveal-on-scroll" style="--stagger-index: 1;">
                <div class="w-10 h-10 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center mx-auto mb-2 font-bold text-lg group-hover:scale-110 group-hover:rotate-6 transition duration-300">📜</div>
                <h3 class="font-bold text-gray-800 text-xs md:text-sm">Surat Online</h3>
            </a>
            <a href="aduan.php" class="p-4 rounded-xl hover:bg-amber-50 transition text-center group reveal-on-scroll" style="--stagger-index: 2;">
                <div class="w-10 h-10 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center mx-auto mb-2 font-bold text-lg group-hover:scale-110 group-hover:rotate-6 transition duration-300">🎯</div>
                <h3 class="font-bold text-gray-800 text-xs md:text-sm">Status Aduan</h3>
            </a>
            <a href="bansos.php" class="p-4 rounded-xl hover:bg-amber-50 transition text-center group reveal-on-scroll" style="--stagger-index: 3;">
                <div class="w-10 h-10 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center mx-auto mb-2 font-bold text-lg group-hover:scale-110 group-hover:rotate-6 transition duration-300">🤝</div>
                <h3 class="font-bold text-gray-800 text-xs md:text-sm">Informasi Bansos</h3>
            </a>
            <a href="potensi.php" class="p-4 rounded-xl hover:bg-amber-50 transition text-center group reveal-on-scroll" style="--stagger-index: 4;">
                <div class="w-10 h-10 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center mx-auto mb-2 font-bold text-lg group-hover:scale-110 group-hover:rotate-6 transition duration-300">🖼️</div>
                <h3 class="font-bold text-gray-800 text-xs md:text-sm">Potensi Desa</h3>
            </a>
        </div>
    </section>

<!-- 1. KEPALA DESA -->
<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-8">
    <div class="bg-white rounded-3xl p-6 md:p-10 shadow-lg border border-gray-100 reveal-on-scroll"
         style="--stagger-index: 1;">

        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-center">

            <!-- FOTO KEPALA DESA -->
            <div class="md:col-span-5">
                <div class="rounded-2xl overflow-hidden bg-gray-100 shadow-md aspect-[4/5] relative group">
                    <img src="<?php echo htmlspecialchars($homeProfileImage, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="Foto Kepala Desa Pemayahan"
                         class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                </div>
            </div>

            <!-- SAMBUTAN KEPALA DESA -->
            <div class="md:col-span-7 space-y-4">

                <span class="inline-block bg-amber-100 text-amber-800 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                    KEPALA DESA PEMAYAHAN
                </span>

                <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">
                    <?php echo htmlspecialchars($homeProfileName, ENT_QUOTES, 'UTF-8'); ?>
                </h2>

                <p class="text-gray-600 text-sm md:text-base leading-relaxed">
                    <?php echo nl2br(htmlspecialchars($homeProfileSpeech, ENT_QUOTES, 'UTF-8')); ?>
                </p>

            </div>

        </div>
    </div>
</section>

    <!-- 2. STRUKTURAL KEPENGURUSAN DESA -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-3xl p-6 md:p-10 shadow-lg border border-gray-100">
            <div class="text-center mb-10 reveal-on-scroll" style="--stagger-index: 1;">
                <span class="text-amber-500 text-xs font-bold uppercase tracking-widest">PEMERINTAHAN DESA PEMAYAHAN</span>
                <h2 class="text-2xl md:text-3xl font-black text-gray-900 mt-1">Struktural Perangkat Desa</h2>
                <p class="text-gray-500 text-xs md:text-sm mt-1">Aparatur Pemerintah Desa Pemayahan yang siap melayani kebutuhan warga.</p>
            </div>

            <div class="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-5 md:gap-6">
                <?php if ($homePerangkatItems === []): ?>
                    <p class="col-span-full py-6 text-center text-sm text-gray-500">Data perangkat desa belum tersedia.</p>
                <?php else: ?>
                    <?php foreach ($homePerangkatItems as $index => $perangkat): ?>
                        <article class="overflow-hidden rounded-2xl border border-gray-100 bg-gray-50 p-3 text-center shadow-sm transition duration-300 hover:-translate-y-1 hover:bg-amber-50/50 hover:shadow-lg sm:p-4 reveal-on-scroll" style="--stagger-index: <?php echo min($index + 1, 8); ?>;">
                            <div class="relative mx-auto mb-4 aspect-[3/4] w-full max-w-[150px] overflow-hidden rounded-[14px] bg-amber-500/20 text-amber-700 flex items-center justify-center text-3xl font-bold">
                                <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-gray-400" aria-hidden="true">Foto perangkat desa</div>
                                <?php if (is_string($perangkat['foto'] ?? null) && $perangkat['foto'] !== ''): ?>
                                    <img src="perangkat_public_image.php?id=<?php echo (int) $perangkat['id']; ?>" alt="Foto <?php echo htmlspecialchars((string) $perangkat['nama_lengkap'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" onerror="this.hidden=true" class="absolute inset-0 h-full w-full object-cover object-center">
                                <?php endif; ?>
                            </div>
                            <h3 class="font-extrabold text-gray-900 text-base"><?php echo htmlspecialchars((string) $perangkat['nama_lengkap'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="text-xs text-amber-600 font-bold uppercase mt-1"><?php echo htmlspecialchars((string) $perangkat['jabatan'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="mt-6 flex justify-center sm:justify-end">
                <a href="profil.php#struktur-perangkat-desa" class="inline-flex min-h-10 items-center gap-2 self-start py-2 text-sm font-extrabold tracking-wide text-slate-900 transition hover:text-amber-700 focus:outline-none focus-visible:rounded focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-4">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V4.75A1.75 1.75 0 0 1 9.75 3h8.5A1.75 1.75 0 0 1 20 4.75v11.5A1.75 1.75 0 0 1 18.25 18H16M5.75 6H14.25A1.75 1.75 0 0 1 16 7.75v12.5A1.75 1.75 0 0 1 14.25 22h-8.5A1.75 1.75 0 0 1 4 20.25V7.75A1.75 1.75 0 0 1 5.75 6ZM7 10h5.5M7 13h5.5M7 16h4" />
                    </svg>
                    LIHAT STRUKTUR LEBIH LENGKAP
                </a>
            </div>
        </div>
    </section>

    <!-- 3. ADMINISTRASI PENDUDUK -->
    <section id="population-statistics" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <noscript>
            <style>
                #population-statistics .reveal-on-scroll {
                    opacity: 1;
                    transform: none;
                }
            </style>
        </noscript>
        <div class="bg-white rounded-3xl p-6 md:p-10 shadow-lg border border-gray-100">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 reveal-on-scroll" style="--stagger-index: 1;">
                <div>
                    <span class="text-amber-500 text-xs font-bold uppercase tracking-widest">DATA DEMOGRAFI</span>
                    <h2 class="text-2xl md:text-3xl font-black text-gray-900 mt-1">Administrasi Penduduk Desa Pemayahan</h2>
                </div>
                <span class="text-xs font-bold text-gray-400 bg-gray-100 px-3 py-1.5 rounded-full">Pembaruan 2026</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
                <div class="min-w-0 min-h-[132px] flex flex-col justify-between bg-gradient-to-br from-amber-500 to-amber-600 text-gray-950 p-4 sm:p-5 rounded-2xl border border-amber-400 shadow-md transform hover:-translate-y-1 transition duration-300 reveal-on-scroll" style="--stagger-index: 1;">
                    <p class="break-words text-[11px] sm:text-xs font-extrabold uppercase leading-tight tracking-wide opacity-80">Total Penduduk</p>
                    <p class="break-words text-2xl sm:text-3xl font-black leading-none tabular-nums">4,852</p>
                    <p class="text-[10px] sm:text-xs font-bold opacity-70">Jiwa Terdaftar</p>
                </div>

                <div class="min-w-0 min-h-[132px] flex flex-col justify-between bg-gray-900 text-white p-4 sm:p-5 rounded-2xl border border-gray-900 shadow-md transform hover:-translate-y-1 transition duration-300 reveal-on-scroll" style="--stagger-index: 2;">
                    <p class="break-words text-[11px] sm:text-xs font-bold text-amber-400 uppercase leading-tight tracking-wide">Kepala Keluarga</p>
                    <p class="break-words text-2xl sm:text-3xl font-black leading-none tabular-nums">1,240</p>
                    <p class="text-[10px] sm:text-xs text-gray-400">KK Terdata</p>
                </div>

                <div class="min-w-0 min-h-[132px] flex flex-col justify-between bg-white border border-gray-200 p-4 sm:p-5 rounded-2xl shadow-md transform hover:-translate-y-1 transition duration-300 reveal-on-scroll" style="--stagger-index: 3;">
                    <p class="break-words text-[11px] sm:text-xs font-bold text-gray-600 uppercase leading-tight tracking-wide">Laki-Laki</p>
                    <p class="break-words text-2xl sm:text-3xl font-black text-gray-900 leading-none tabular-nums">2,460</p>
                    <p class="text-[10px] sm:text-xs text-gray-500">50.7% Jiwa</p>
                </div>

                <div class="min-w-0 min-h-[132px] flex flex-col justify-between bg-white border border-gray-200 p-4 sm:p-5 rounded-2xl shadow-md transform hover:-translate-y-1 transition duration-300 reveal-on-scroll" style="--stagger-index: 4;">
                    <p class="break-words text-[11px] sm:text-xs font-bold text-amber-700 uppercase leading-tight tracking-wide">Perempuan</p>
                    <p class="break-words text-2xl sm:text-3xl font-black text-gray-900 leading-none tabular-nums">2,392</p>
                    <p class="text-[10px] sm:text-xs text-gray-500">49.3% Jiwa</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. INFORMASI BANSOS -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-indigo-900 rounded-3xl p-6 md:p-10 text-white shadow-xl relative overflow-hidden reveal-on-scroll" style="--stagger-index: 1;">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <span class="text-amber-400 text-xs font-bold uppercase tracking-widest">LAYANAN KESEJAHTERAAN WARGA</span>
                    <h2 class="text-2xl md:text-3xl font-black mt-1">Informasi Bantuan Sosial</h2>
                    <p class="text-indigo-200 text-xs md:text-sm mt-2 max-w-xl">Lihat informasi jenis bantuan sosial dan cara memeriksa status penyaluran melalui kanal resmi.</p>
                </div>
                <a href="bansos.php" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-bold text-xs px-6 py-3 rounded-xl transition text-center shrink-0 shadow-lg transform hover:-translate-y-1">
                    Lihat Informasi Bansos →
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8 pt-6 border-t border-indigo-800/80">
                <div class="bg-indigo-950/60 p-4 rounded-2xl border border-indigo-800/50">
                    <p class="text-indigo-300 text-xs font-medium">Bantuan Langsung Tunai Dana Desa</p>
                    <p class="text-sm font-extrabold text-amber-400 mt-2">BLT-DD</p>
                </div>
                <div class="bg-indigo-950/60 p-4 rounded-2xl border border-indigo-800/50">
                    <p class="text-indigo-300 text-xs font-medium">Program Keluarga Harapan</p>
                    <p class="text-sm font-extrabold text-emerald-400 mt-2">PKH</p>
                </div>
                <div class="bg-indigo-950/60 p-4 rounded-2xl border border-indigo-800/50">
                    <p class="text-indigo-300 text-xs font-medium">Bantuan Pangan Non-Tunai</p>
                    <p class="text-sm font-extrabold text-blue-400 mt-2">BPNT</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 5. BERITA DESA -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between mb-8 reveal-on-scroll" style="--stagger-index: 1;">
            <div>
                <span class="text-amber-500 text-xs font-bold uppercase tracking-widest">KABAR PEMAYAHAN</span>
                <h2 class="text-2xl md:text-3xl font-black text-gray-900 mt-0.5">Berita & Kegiatan Terbaru</h2>
            </div>
            <a href="berita.php" class="text-xs font-bold text-indigo-900 hover:text-amber-500 transition">Lihat Semua Berita →</a>
        </div>

        <?php if ($homeNewsError): ?>
            <p role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Berita terbaru belum dapat dimuat saat ini.</p>
        <?php elseif ($homeNewsItems === []): ?>
            <p class="rounded-xl border border-gray-100 bg-white px-4 py-8 text-center text-sm text-gray-500">Belum ada berita yang dipublikasikan.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($homeNewsItems as $newsIndex => $newsItem): ?>
                    <article class="bg-white rounded-2xl overflow-hidden shadow-md border border-gray-100 flex flex-col hover:shadow-xl transition duration-300 reveal-on-scroll" style="--stagger-index: <?php echo $newsIndex + 1; ?>;">
                        <div class="relative h-48 overflow-hidden bg-gray-100">
                            <?php if (!empty($newsItem['gambar'])): ?>
                                <img src="berita_image.php?id=<?php echo (int) $newsItem['id']; ?>" alt="<?php echo htmlspecialchars((string) $newsItem['judul'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" onerror="this.hidden=true; this.nextElementSibling.hidden=false" class="w-full h-full object-cover hover:scale-105 transition duration-500">
                                <div hidden class="absolute inset-0 flex items-center justify-center text-sm font-bold text-gray-400">Informasi Desa</div>
                            <?php else: ?>
                                <div class="flex h-full items-center justify-center text-sm font-bold text-gray-400">Informasi Desa</div>
                            <?php endif; ?>
                        </div>
                        <div class="p-5 flex-1 flex flex-col justify-between">
                            <div>
                                <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2.5 py-1 rounded-full"><?php echo htmlspecialchars((string) ($newsItem['kategori'] ?? 'Informasi Desa'), ENT_QUOTES, 'UTF-8'); ?></span>
                                <h3 class="font-bold text-gray-900 text-base mt-3 leading-snug"><?php echo htmlspecialchars((string) $newsItem['judul'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <?php $newsSummary = (string) ($newsItem['ringkasan'] ?? ''); ?>
                                <?php if ($newsSummary === '') { $newsSummary = (string) ($newsItem['isi'] ?? ''); } ?>
                                <?php if ($newsSummary !== ''): ?>
                                    <p class="text-gray-500 text-xs mt-2 line-clamp-2"><?php echo htmlspecialchars($newsSummary, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between text-[11px] text-gray-400">
                                <span><?php
                                    echo htmlspecialchars(home_public_date((string) $newsItem['tanggal']), ENT_QUOTES, 'UTF-8');
                                ?></span>
                                <a href="berita.php?id=<?php echo (int) $newsItem['id']; ?>" class="font-bold text-indigo-900 hover:underline">Baca Selengkapnya</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- 6. POTENSI DESA -->
    <section class="max-w-6xl mx-auto scroll-mt-28 px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-3xl p-5 sm:p-6 md:p-8 shadow-lg border border-gray-100">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6 reveal-on-scroll" style="--stagger-index: 1;">
                <div>
                    <h2 class="text-3xl font-black text-amber-500 uppercase tracking-tight">POTENSI DESA</h2>
                    <p class="text-gray-600 text-sm mt-2 max-w-2xl leading-relaxed">
                        Informasi tentang potensi dan kemajuan Desa Pemayahan di berbagai bidang seperti ekonomi, pariwisata, pertanian, industri kreatif, dan kelestarian lingkungan.
                    </p>
                </div>
                <a href="potensi.php" class="inline-flex min-h-10 items-center gap-2 self-start py-2 text-sm font-extrabold tracking-wide text-slate-900 transition hover:text-amber-700 focus:outline-none focus-visible:rounded focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-4">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V4.75A1.75 1.75 0 0 1 9.75 3h8.5A1.75 1.75 0 0 1 20 4.75v11.5A1.75 1.75 0 0 1 18.25 18H16M5.75 6H14.25A1.75 1.75 0 0 1 16 7.75v12.5A1.75 1.75 0 0 1 14.25 22h-8.5A1.75 1.75 0 0 1 4 20.25V7.75A1.75 1.75 0 0 1 5.75 6ZM7 10h5.5M7 13h5.5M7 16h4" />
                    </svg>
                    LIHAT POTENSI LEBIH BANYAK
                </a>
            </div>

            <?php if ($homePotensiItems === []): ?>
                <p class="py-8 text-center text-sm text-gray-500">Informasi potensi desa belum tersedia.</p>
            <?php else: ?>
                <?php $homePotensiCount = count($homePotensiItems); ?>
                <div id="home-potential-carousel" class="mx-auto flex w-full flex-col items-center py-1" aria-roledescription="carousel" aria-label="Pilihan potensi Desa Pemayahan">
                    <div class="potential-carousel-track grid w-full grid-cols-1 items-start gap-7 md:grid-cols-2 md:gap-10">
                        <?php foreach ($homePotensiItems as $index => $item): ?>
                            <?php
                            $nextItem = $homePotensiItems[($index + 1) % $homePotensiCount];
                            $lastItem = $homePotensiItems[($index + 2) % $homePotensiCount];
                            ?>
                            <article class="potential-carousel-slide flex min-w-0 flex-col items-center text-center" data-potential-index="<?php echo (int) $index; ?>" aria-roledescription="slide" aria-label="<?php echo (int) ($index + 1); ?> dari <?php echo (int) $homePotensiCount; ?>" <?php echo $index < min(2, $homePotensiCount) ? '' : 'hidden'; ?>>
                                <a href="potensi.php#potensi-<?php echo (int) $item['id']; ?>" class="group flex w-full flex-col items-center rounded-3xl focus:outline-none focus-visible:ring-4 focus-visible:ring-amber-500/40" aria-label="Lihat <?php echo htmlspecialchars((string) $item['kategori'], ENT_QUOTES, 'UTF-8'); ?> di halaman Potensi Desa">
                                    <div class="potential-artwork relative mx-auto overflow-hidden transition-transform duration-500 group-hover:scale-[1.02]">
                                        <?php if (is_string($item['nama_file'] ?? null) && $item['nama_file'] !== ''): ?>
                                            <img class="potential-artwork__image" src="potensi_image.php?id=<?php echo (int) $item['id']; ?>" alt="" loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>" onerror="this.remove()">
                                        <?php else: ?>
                                            <div class="absolute inset-0 flex items-center justify-center px-10 text-sm font-semibold text-slate-700">Foto belum tersedia</div>
                                        <?php endif; ?>
                                        <?php if ($homePotensiCount > 1 && is_string($nextItem['nama_file'] ?? null) && $nextItem['nama_file'] !== ''): ?>
                                            <div class="potential-artwork__facet potential-artwork__facet--right">
                                                <img src="potensi_image.php?id=<?php echo (int) $nextItem['id']; ?>" alt="" loading="lazy" onerror="this.parentElement.remove()">
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($homePotensiCount > 2 && is_string($lastItem['nama_file'] ?? null) && $lastItem['nama_file'] !== ''): ?>
                                            <div class="potential-artwork__facet potential-artwork__facet--lower">
                                                <img src="potensi_image.php?id=<?php echo (int) $lastItem['id']; ?>" alt="" loading="lazy" onerror="this.parentElement.remove()">
                                            </div>
                                        <?php endif; ?>
                                        <div class="potential-artwork__accent" aria-hidden="true"></div>
                                        <div class="pointer-events-none absolute inset-0 z-[3] rounded-full bg-gradient-to-tr from-slate-950/10 via-transparent to-amber-200/10"></div>
                                    </div>
                                    <h3 class="mt-4 w-full max-w-[22rem] break-words px-2 text-lg font-black uppercase leading-snug tracking-[.12em] text-gray-900 transition group-hover:text-amber-600 sm:text-xl">
                                        <?php echo htmlspecialchars((string) $item['kategori'], ENT_QUOTES, 'UTF-8'); ?>
                                    </h3>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="potential-carousel-control mt-5 flex items-center gap-4 rounded-full border border-slate-200 bg-white/95 px-3 py-2 shadow-sm" aria-label="Navigasi potensi">
                        <button type="button" data-potential-previous aria-label="Potensi sebelumnya" class="grid h-11 w-11 place-items-center rounded-full bg-slate-900 text-white transition hover:bg-slate-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-amber-300 disabled:cursor-not-allowed disabled:opacity-40" <?php echo $homePotensiCount < 2 ? 'disabled' : ''; ?>>
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
                        </button>
                        <span data-potential-count class="min-w-16 text-center text-xs font-extrabold tracking-widest text-slate-800" aria-live="polite">01 / <?php echo str_pad((string) $homePotensiCount, 2, '0', STR_PAD_LEFT); ?></span>
                        <button type="button" data-potential-next aria-label="Potensi berikutnya" class="grid h-11 w-11 place-items-center rounded-full bg-slate-900 text-white transition hover:bg-slate-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-amber-300 disabled:cursor-not-allowed disabled:opacity-40" <?php echo $homePotensiCount < 2 ? 'disabled' : ''; ?>>
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                </div>
                <script>
                    (function () {
                        const carousel = document.getElementById('home-potential-carousel');
                        if (!carousel) return;

                        const slides = Array.from(carousel.querySelectorAll('[data-potential-index]'));
                        const counter = carousel.querySelector('[data-potential-count]');
                        let activeIndex = 0;

                        function showSlide(nextIndex) {
                            activeIndex = (nextIndex + slides.length) % slides.length;
                            const visibleCount = window.matchMedia('(min-width: 768px)').matches
                                ? Math.min(2, slides.length)
                                : 1;
                            slides.forEach((slide, index) => {
                                const distance = (index - activeIndex + slides.length) % slides.length;
                                const isVisible = distance < visibleCount;
                                slide.hidden = !isVisible;
                                slide.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
                            });
                            if (counter) {
                                counter.textContent = String(activeIndex + 1).padStart(2, '0')
                                    + ' / ' + String(slides.length).padStart(2, '0');
                            }
                        }

                        carousel.querySelector('[data-potential-previous]')?.addEventListener('click', () => showSlide(activeIndex - 1));
                        carousel.querySelector('[data-potential-next]')?.addEventListener('click', () => showSlide(activeIndex + 1));
                        window.addEventListener('resize', () => showSlide(activeIndex), { passive: true });
                        showSlide(activeIndex);
                    })();
                </script>
            <?php endif; ?>
        </div>
    </section>

    <!-- 7. WISATA DESA -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6 flex items-center justify-between reveal-on-scroll" style="--stagger-index: 1;">
            <div>
                <span class="text-amber-500 text-xs font-bold uppercase tracking-widest">DESTINASI UNGGULAN</span>
                <h2 class="text-2xl md:text-3xl font-black text-gray-900 mt-0.5">Wisata & Pesona Pemayahan</h2>
            </div>
            <a href="potensi.php" class="text-xs font-bold text-indigo-900 hover:text-amber-500 transition">Jelajahi Semua Wisata →</a>
        </div>

        <div class="relative rounded-3xl overflow-hidden shadow-2xl min-h-[380px] md:min-h-[440px] flex items-end bg-cover bg-center transition-all duration-500 border border-gray-800 reveal-on-scroll"
             style="--stagger-index: 2; background-image: linear-gradient(to top, rgba(15,23,42,0.95) 0%, rgba(15,23,42,0.4) 60%, rgba(15,23,42,0.2) 100%), url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1200&q=80');">
            
            <div class="p-6 md:p-10 w-full flex flex-col md:flex-row md:items-end justify-between gap-6 z-10">
                <div class="max-w-3xl">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="bg-amber-500 text-gray-950 text-[10px] font-black px-3 py-1 rounded-md uppercase tracking-wider shadow">
                            ⭐ WISATA UNGGULAN
                        </span>
                        <span class="bg-slate-800/80 text-slate-300 text-[10px] font-semibold px-2.5 py-1 rounded-md border border-slate-700/80">
                            Destinasi 1 dari 3
                        </span>
                    </div>

                    <h3 class="text-xl md:text-3xl font-black text-white tracking-tight leading-snug mb-3">
                        Ekowisata Hutan Pinus & Situ Danau Alami Desa Pemayahan
                    </h3>

                    <div class="flex items-center gap-4 text-xs text-slate-300 font-medium">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"/></svg>
                            Buka Setiap Hari (08.00 - 17.00 WIB)
                        </span>
                        <span>•</span>
                        <span class="flex items-center gap-1.5 text-amber-400 font-bold">
                            📍 Desa Pemayahan
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-end md:self-auto bg-slate-900/80 backdrop-blur-md p-2 rounded-2xl border border-slate-700/60 shadow-xl shrink-0">
                    <button class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition">
                        ❮
                    </button>
                    <div class="flex items-center gap-1 px-2">
                        <span class="w-6 h-2 bg-amber-500 rounded-full"></span>
                        <span class="w-2 h-2 bg-slate-700 rounded-full"></span>
                        <span class="w-2 h-2 bg-slate-700 rounded-full"></span>
                    </div>
                    <button class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition">
                        ❯
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- 8. GALERI DESA -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-lg md:p-10">
            <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between lg:mb-8 reveal-on-scroll" style="--stagger-index: 1;">
                <div class="max-w-2xl">
                    <h2 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Galeri Kegiatan Desa Pemayahan</h2>
                    <p class="mt-2 text-sm font-semibold text-slate-500">Foto &amp; Dokumentasi Lapangan</p>
                </div>
                <a href="galeri.php" class="inline-flex min-h-10 items-center gap-2 self-start py-2 text-sm font-extrabold tracking-wide text-slate-900 transition hover:text-amber-700 focus:outline-none focus-visible:rounded focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-4">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V4.75A1.75 1.75 0 0 1 9.75 3h8.5A1.75 1.75 0 0 1 20 4.75v11.5A1.75 1.75 0 0 1 18.25 18H16M5.75 6H14.25A1.75 1.75 0 0 1 16 7.75v12.5A1.75 1.75 0 0 1 14.25 22h-8.5A1.75 1.75 0 0 1 4 20.25V7.75A1.75 1.75 0 0 1 5.75 6ZM7 10h5.5M7 13h5.5M7 16h4" />
                    </svg>
                    LIHAT FOTO LEBIH BANYAK
                </a>
            </div>

            <?php if ($homeGalleryError): ?>
                <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
                    Dokumentasi Galeri belum dapat dimuat saat ini. Silakan coba kembali nanti.
                </div>
            <?php elseif ($homeGalleryItems === []): ?>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-10 text-center">
                    <p class="font-bold text-slate-700">Belum ada foto yang dipublikasikan.</p>
                    <p class="mt-1 text-sm text-slate-500">Foto akan tampil di sini setelah tersedia dan disetujui untuk publikasi.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5">
                    <?php foreach ($homeGalleryItems as $galleryIndex => $photo):
                        $galleryPhotoUrl = 'galeri_image.php?id=' . (int) $photo['id'];
                        $galleryTitle = (string) $photo['judul'];
                    ?>
                        <a href="galeri.php" class="group block overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 reveal-on-scroll" style="--stagger-index: <?php echo (int) $galleryIndex + 1; ?>;">
                            <div class="aspect-[4/3] overflow-hidden bg-slate-100">
                                <img src="<?php echo htmlspecialchars($galleryPhotoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($galleryTitle, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                            </div>
                            <div class="p-4">
                                <p class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700"><?php echo htmlspecialchars((string) $photo['kategori'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <h3 class="mt-1 line-clamp-2 text-sm font-extrabold leading-5 text-slate-900"><?php echo htmlspecialchars($galleryTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="mt-2 text-xs text-slate-500"><?php echo htmlspecialchars((string) $photo['tanggal_dokumentasi'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- 9. STATUS LAPORAN PENGADUAN -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-gradient-to-r from-slate-900 via-blue-950 to-slate-900 rounded-3xl p-6 md:p-10 text-center text-white shadow-2xl relative overflow-hidden border border-slate-800 reveal-on-scroll" style="--stagger-index: 1;">
            <span class="inline-block bg-amber-500/20 text-amber-400 border border-amber-500/30 text-[10px] font-extrabold px-3.5 py-1 rounded-full uppercase tracking-widest mb-3">
                TRANSPARANSI LAYANAN
            </span>

            <h3 class="text-2xl md:text-3xl font-black tracking-tight mb-2 flex items-center justify-center gap-2">
                <span>🔍</span> Cek Status Laporan Pengaduan
            </h3>
            <p class="text-slate-300 text-xs md:text-sm max-w-xl mx-auto leading-relaxed mb-6">
                Pantau perkembangan tindak lanjut laporan menggunakan nomor kontak dan token akses yang diterbitkan saat pengaduan dikirim.
            </p>

            <form action="aduan.php" method="POST" class="max-w-md mx-auto flex flex-col gap-2.5">
                <input 
                    type="text" 
                    name="cek_nohp" 
                    required 
                    placeholder="KETIK NOMOR HP (CONTOH: 08123456789)" 
                    class="w-full bg-slate-900/80 border border-slate-700 text-white placeholder-slate-500 text-xs font-semibold rounded-xl px-4 py-3.5 focus:outline-none focus:border-amber-500 transition shadow-inner"
                >
                <input
                    type="password"
                    name="access_token"
                    required
                    minlength="64"
                    maxlength="64"
                    pattern="[A-Fa-f0-9]{64}"
                    autocomplete="off"
                    placeholder="TOKEN AKSES 64 KARAKTER DARI SAAT PENGADUAN"
                    class="w-full bg-slate-900/80 border border-slate-700 text-white placeholder-slate-500 text-xs font-semibold rounded-xl px-4 py-3.5 focus:outline-none focus:border-amber-500 transition shadow-inner"
                >
                <button 
                    type="submit" 
                    class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs px-6 py-3.5 rounded-xl transition shadow-lg uppercase tracking-wider"
                >
                    Cek Status
                </button>
            </form>
        </div>
    </section>

    <!-- 10. ASPIRASI PUBLIK (KOTAK SARAN & MASUKAN) -->
    <section id="kotak-saran" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 mb-12">
        <?php if (is_array($homeAduanSuccess) || is_string($homeAduanError)): ?>
            <div class="mb-5 rounded-2xl border <?php echo is_array($homeAduanSuccess) ? 'border-amber-300 bg-amber-50 text-slate-900' : 'border-red-200 bg-white text-red-800'; ?> p-5 shadow-sm" role="<?php echo is_array($homeAduanSuccess) ? 'status' : 'alert'; ?>">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-extrabold"><?php echo is_array($homeAduanSuccess) ? htmlspecialchars((string) ($homeAduanSuccess['message'] ?? 'Laporan berhasil dikirim.'), ENT_QUOTES, 'UTF-8') : htmlspecialchars($homeAduanError, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if (is_array($homeAduanSuccess) && is_string($homeAduanSuccess['token'] ?? null)): ?>
                            <p class="mt-2 text-sm text-slate-700">Simpan token ini untuk mengecek status aduan Anda:</p>
                            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                                <code id="home-aduan-token" class="min-w-0 break-all rounded-lg border border-amber-200 bg-white px-3 py-2 font-mono text-sm text-slate-900"><?php echo htmlspecialchars($homeAduanSuccess['token'], ENT_QUOTES, 'UTF-8'); ?></code>
                                <button type="button" data-copy-token="home-aduan-token" class="shrink-0 rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white transition hover:bg-slate-700">Salin token</button>
                                <span data-copy-feedback class="self-center text-xs font-semibold text-slate-600" aria-live="polite"></span>
                            </div>
                            <p class="mt-2 text-xs text-slate-600">Gunakan token ini bersama nomor kontak Anda pada fitur Cek Status Aduan.</p>
                        <?php endif; ?>
                    </div>
                    <button type="button" data-dismiss-notice class="shrink-0 rounded-lg px-2 py-1 text-sm font-bold text-slate-600 hover:bg-black/5" aria-label="Tutup notifikasi">Tutup</button>
                </div>
            </div>
        <?php endif; ?>
        <div class="bg-white rounded-3xl p-6 md:p-10 shadow-lg border border-gray-100 max-w-4xl mx-auto reveal-on-scroll" style="--stagger-index: 1;">
            <div class="text-center mb-8">
                <span class="bg-blue-50 text-blue-900 border border-blue-100 text-[10px] font-extrabold px-3 py-1 rounded-full uppercase tracking-widest">
                    ASPIRASI PUBLIK
                </span>
                <h3 class="text-2xl md:text-3xl font-black text-gray-900 mt-2">Kotak Saran & Masukan</h3>
                <p class="text-gray-500 text-xs md:text-sm mt-1">Sampaikan ide, kritik, atau saran Anda untuk kemajuan Desa Pemayahan kita bersama.</p>
            </div>

            <form action="proses-aduan.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(masyarakat_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="return_to" value="index.php#kotak-saran">
                <input type="hidden" name="judul" value="Saran & Masukan Warga Pemayahan">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <input 
                            type="text" 
                            name="nama" 
                            placeholder="Nama Lengkap (Opsional)" 
                            class="w-full bg-gray-50 border border-gray-200 text-gray-800 placeholder-gray-400 text-xs font-medium rounded-xl p-3.5 focus:outline-none focus:border-amber-500 transition"
                        >
                    </div>
                    <div>
                        <input 
                            type="text" 
                            name="no_hp" 
                            maxlength="20"
                            placeholder="No. HP / Email (Opsional)" 
                            class="w-full bg-gray-50 border border-gray-200 text-gray-800 placeholder-gray-400 text-xs font-medium rounded-xl p-3.5 focus:outline-none focus:border-amber-500 transition"
                        >
                    </div>
                </div>

                <div>
                    <select name="kategori" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs font-medium rounded-xl p-3.5 focus:outline-none focus:border-amber-500 transition">
                        <option value="Saran Pembangunan">Kategori: Saran Pembangunan Desa</option>
                        <option value="Saran Pelayanan">Kategori: Pelayanan & Administrasi</option>
                        <option value="Saran Kebersihan">Kategori: Kebersihan & Lingkungan</option>
                        <option value="Lainnya">Kategori: Lainnya</option>
                    </select>
                </div>

                <div>
                    <textarea 
                        name="isi" 
                        rows="4" 
                        required 
                        placeholder="Tuliskan saran atau masukan Anda secara jelas..." 
                        class="w-full bg-gray-50 border border-gray-200 text-gray-800 placeholder-gray-400 text-xs font-medium rounded-xl p-3.5 focus:outline-none focus:border-amber-500 transition"
                    ></textarea>
                </div>

                <div class="flex justify-end">
                    <button 
                        type="submit" 
                        class="bg-blue-900 hover:bg-blue-800 text-white font-extrabold text-xs px-8 py-3.5 rounded-xl transition shadow-md uppercase tracking-wider"
                    >
                        Kirim Masukan
                    </button>
                </div>
            </form>
        </div>
    </section>

    <?php $showHomeFooterDetails = true; require __DIR__ . '/includes/footer.php'; ?>

    <!-- AOS JS & INISIALISASI HIGH-PERFORMANCE SCROLL ENGINE -->
    <script>
    (() => {
        document.querySelectorAll('[data-dismiss-notice]').forEach((button) => {
            button.addEventListener('click', () => button.closest('[role="status"], [role="alert"]')?.remove());
        });
        document.querySelectorAll('[data-copy-token]').forEach((button) => {
            button.addEventListener('click', async () => {
                const token = document.getElementById(button.dataset.copyToken);
                const feedback = button.parentElement.querySelector('[data-copy-feedback]');
                if (!token || !feedback) return;
                try {
                    await navigator.clipboard.writeText(token.textContent.trim());
                    feedback.textContent = 'Token berhasil disalin.';
                } catch (error) {
                    feedback.textContent = 'Gagal menyalin. Silakan salin token secara manual.';
                }
            });
        });
    })();
    </script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="assets/js/index.js"></script>
</body>
</html>