<?php
require_once __DIR__ . '/masyarakat_auth.php';
require_once __DIR__ . '/koneksi.php';

$potensiItems = [];
try {
    $result = $koneksi->query('SELECT id, kategori, judul, harga, deskripsi, nama_file FROM potensi_desa ORDER BY id ASC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $potensiItems[] = $row;
        }
        $result->free();
    }
} catch (Throwable $exception) {
    error_log('Public Potensi query failed: ' . $exception->getMessage());
}

$potensiVisuals = [
    'Sektor Pertanian' => ['class' => 'bg-amber-500/10 text-amber-700'],
    'Sektor Ekonomi Kreatif' => ['class' => 'bg-amber-500/10 text-amber-700'],
    'Sektor Pariwisata' => ['class' => 'bg-blue-500/10 text-blue-700'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Potensi Desa - Portal Resmi Desa Digital</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/public-notice.css?v=20261026" rel="stylesheet">
    <link href="assets/css/public-nav.css?v=20261117-desktop-nav" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f3f3f3;
        }

        .potensi-shell {
            max-width: 1280px;
            margin: 0 auto;
            padding-top: 4px;
        }

        .potensi-card {
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
            min-width: 0;
            width: 100%;
            background: #fff;
            border: 1px solid #f3f4f6;
            border-radius: 1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        }

        .potensi-card-image {
            position: relative;
            flex: none;
            width: 100%;
            height: 200px;
            overflow: hidden;
            background: #e5e7eb;
        }

        .potensi-card-image img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .potensi-card-image-placeholder {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e5e7eb;
            color: #4b5563;
            font-size: 0.8125rem;
            font-weight: 700;
        }

        .potensi-card-body {
            display: flex;
            flex: 1 1 auto;
            flex-direction: column;
            min-height: 0;
            padding: 1.5rem;
        }

        .potensi-card-category {
            margin: 0;
            color: #b45309;
            font-size: 0.625rem;
            font-weight: 800;
            line-height: 1.4;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .potensi-card-title {
            margin: 0.75rem 0 0;
            font-size: 1.125rem;
            line-height: 1.35;
            font-weight: 800;
            color: #111827;
        }

        .potensi-card-price {
            margin: 0.5rem 0 0;
            font-size: 0.875rem;
            line-height: 1.4;
            font-weight: 800;
            color: #16a34a;
        }

        .potensi-card-description {
            flex: 1 1 auto;
            min-height: 0;
            margin: 0.5rem 0 0;
            color: #4b5563;
            font-size: 0.875rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
            -webkit-line-clamp: 3;
        }

        .potensi-card-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            flex: none;
            min-height: 40px;
            margin-top: auto;
            border: 0;
            border-radius: 0.5rem;
            background: #22c55e;
            color: #fff;
            font-size: 0.875rem;
            line-height: 1.25rem;
            font-weight: 700;
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            box-shadow: none;
        }

        .potensi-card-button:hover {
            background: #16a34a;
        }
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
    <main class="potensi-shell mx-auto w-full px-4 py-12 sm:px-6 lg:px-8">
        <div class="mx-auto mb-12 max-w-xl text-center">
            <span class="text-xs font-extrabold uppercase tracking-widest text-amber-500">POTENSI UNGGULAN</span>
            <h2 class="mt-1 text-2xl font-black text-gray-900 md:text-3xl">Potensi Desa &amp; UMKM</h2>
            <p class="mt-1 text-base leading-6 text-gray-600">Produk unggulan dan destinasi wisata desa kami.</p>
        </div>

        <?php if ($potensiItems === []): ?>
            <p class="py-10 text-center text-sm text-gray-500">Informasi potensi desa belum tersedia.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 items-stretch gap-8 md:grid-cols-3 xl:grid-cols-4">
                <?php foreach ($potensiItems as $item): ?>
                    <?php $hasImage = is_string($item['nama_file'] ?? null) && $item['nama_file'] !== ''; ?>
                    <article id="potensi-<?php echo (int) $item['id']; ?>" class="potensi-card group overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:shadow-md">
                        <div class="potensi-card-image">
                            <?php if ($hasImage): ?>
                                <img src="potensi_image.php?id=<?php echo (int) $item['id']; ?>" alt="<?php echo htmlspecialchars((string) $item['judul'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="potensi-card-image-placeholder" style="display:none;">Produk Desa</div>
                            <?php else: ?>
                                <div class="potensi-card-image-placeholder">Produk Desa</div>
                            <?php endif; ?>
                        </div>

                        <div class="potensi-card-body">
                            <p class="potensi-card-category"><?php echo htmlspecialchars((string) $item['kategori'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <h3 class="potensi-card-title"><?php echo htmlspecialchars((string) $item['judul'], ENT_QUOTES, 'UTF-8'); ?></h3>

                            <p class="potensi-card-price">
                                <?php
                                    $priceText = $item['harga'] === null
                                        ? 'Harga belum dicantumkan'
                                        : 'Rp ' . number_format((int) $item['harga'], 0, ',', '.');
                                    echo htmlspecialchars($priceText, ENT_QUOTES, 'UTF-8');
                                ?>
                            </p>

                            <p class="potensi-card-description">
                                <?php echo htmlspecialchars(trim((string) ($item['deskripsi'] ?? '')) !== '' ? (string) $item['deskripsi'] : 'Deskripsi produk desa belum tersedia.', ENT_QUOTES, 'UTF-8'); ?>
                            </p>

                            <button type="button" class="potensi-card-button">
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5 fill-current">
                                    <path d="M12.04 2a9.9 9.9 0 0 0-8.47 15.03L2 22l5.12-1.34A9.93 9.93 0 1 0 12.04 2Zm0 18.06a8.1 8.1 0 0 1-4.13-1.13l-.3-.18-3.04.8.81-2.96-.2-.31a8.1 8.1 0 1 1 6.86 3.78Zm4.45-6.07c-.24-.12-1.43-.71-1.65-.79-.22-.08-.38-.12-.54.12-.16.24-.62.79-.76.95-.14.16-.28.18-.52.06-.24-.12-1.02-.38-1.94-1.2-.72-.64-1.2-1.43-1.34-1.67-.14-.24-.02-.37.1-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.54-1.3-.74-1.78-.2-.47-.4-.4-.54-.41h-.46c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.69 2.58 4.1 3.62.57.25 1.02.4 1.37.51.58.18 1.1.15 1.52.09.47-.07 1.43-.58 1.63-1.14.2-.56.2-1.04.14-1.14-.06-.1-.22-.16-.46-.28Z"/>
                                </svg>
                                Hubungi Penjual
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>

</body>
</html>