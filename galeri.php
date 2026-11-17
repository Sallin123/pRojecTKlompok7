<?php
require_once __DIR__ . '/masyarakat_auth.php';
require_once __DIR__ . '/koneksi.php';

$photos = [];
$galleryError = false;
try {
    $statement = $koneksi->prepare(
        'SELECT id, judul, keterangan, kategori, tanggal_dokumentasi FROM galeri WHERE status_publikasi = \'publikasi\' ORDER BY tanggal_dokumentasi DESC, id DESC'
    );
    if (!$statement || !$statement->execute()) {
        if ($statement instanceof mysqli_stmt) {
            $statement->close();
        }
        throw new RuntimeException('Galeri public query failed.');
    }
    $result = $statement->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $photos[] = $row;
        }
    }
    $statement->close();
} catch (Throwable $exception) {
    error_log('Public Galeri query failed: ' . $exception->getMessage());
    $galleryError = true;
}

function galeri_public_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeri Desa - Portal Resmi Desa Pemayahan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/public-notice.css?v=20261026" rel="stylesheet">
    <link href="assets/css/public-nav.css?v=20261117-desktop-nav" rel="stylesheet">
    <style>
        body { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 text-gray-800 antialiased">
    <div class="flex min-h-screen flex-col">
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

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
        <div class="mx-auto mb-9 max-w-2xl text-center">
            <span class="text-xs font-extrabold uppercase tracking-widest text-amber-500">Dokumentasi Desa</span>
            <h1 class="mt-2 text-2xl font-black text-gray-900 sm:text-3xl">Galeri Desa Pemayahan</h1>
            <p class="mt-2 text-sm leading-6 text-gray-500">Foto dokumentasi yang telah dipublikasikan.</p>
        </div>

        <?php if ($galleryError): ?>
            <section role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center text-sm leading-6 text-amber-950">
                Galeri belum dapat dimuat saat ini. Silakan coba kembali nanti.
            </section>
        <?php elseif ($photos === []): ?>
            <section class="rounded-2xl border border-slate-200 bg-white px-5 py-12 text-center">
                <p class="font-extrabold text-slate-800">Belum ada foto yang dipublikasikan.</p>
                <p class="mt-2 text-sm text-slate-500">Foto akan tampil di sini setelah tersedia dan disetujui untuk publikasi.</p>
            </section>
        <?php else: ?>
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($photos as $photo):
                    $photoUrl = 'galeri_image.php?id=' . (int) $photo['id'];
                    $title = (string) $photo['judul'];
                ?>
                    <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:shadow-md">
                        <button type="button" class="group block w-full text-left" data-gallery-src="<?php echo galeri_public_escape($photoUrl); ?>" data-gallery-alt="<?php echo galeri_public_escape($title); ?>" aria-label="Perbesar foto: <?php echo galeri_public_escape($title); ?>">
                            <img src="<?php echo galeri_public_escape($photoUrl); ?>" alt="<?php echo galeri_public_escape($title); ?>" loading="lazy" class="aspect-[4/3] w-full bg-gray-100 object-cover transition duration-300 group-hover:scale-[1.02]">
                        </button>
                        <div class="p-4 sm:p-5">
                            <p class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700"><?php echo galeri_public_escape((string) $photo['kategori']); ?></p>
                            <h2 class="mt-1 break-words text-base font-extrabold text-gray-900"><?php echo galeri_public_escape($title); ?></h2>
                            <p class="mt-2 text-xs text-gray-500"><?php echo galeri_public_escape((string) $photo['tanggal_dokumentasi']); ?></p>
                            <?php if ((string) ($photo['keterangan'] ?? '') !== ''): ?>
                                <p class="mt-3 break-words text-sm leading-6 text-gray-600"><?php echo nl2br(galeri_public_escape((string) $photo['keterangan'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <dialog id="gallery-lightbox" class="max-h-[92vh] max-w-[min(96vw,72rem)] rounded-2xl bg-slate-950 p-2 text-white shadow-2xl backdrop:bg-slate-950/80">
        <div class="flex items-center justify-between gap-4 p-2">
            <p id="gallery-lightbox-caption" class="min-w-0 truncate text-sm font-bold"></p>
            <button type="button" id="gallery-lightbox-close" class="shrink-0 rounded-lg bg-white/10 px-3 py-2 text-xs font-bold hover:bg-white/20">Tutup</button>
        </div>
        <img id="gallery-lightbox-image" alt="" class="max-h-[80vh] max-w-full rounded-xl object-contain">
    </dialog>
    <?php require __DIR__ . '/includes/footer.php'; ?>
    <script>
    (() => {
        const dialog = document.getElementById('gallery-lightbox');
        const image = document.getElementById('gallery-lightbox-image');
        const caption = document.getElementById('gallery-lightbox-caption');
        document.querySelectorAll('[data-gallery-src]').forEach((button) => {
            button.addEventListener('click', () => {
                image.src = button.dataset.gallerySrc;
                image.alt = button.dataset.galleryAlt || '';
                caption.textContent = button.dataset.galleryAlt || '';
                if (typeof dialog.showModal === 'function') dialog.showModal();
            });
        });
        document.getElementById('gallery-lightbox-close').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
        dialog.addEventListener('close', () => { image.removeAttribute('src'); });
    })();
    </script>
    </div>
</body>
</html>
