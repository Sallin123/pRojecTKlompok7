<?php
require_once __DIR__ . '/masyarakat_auth.php';
require_once __DIR__ . '/koneksi.php';

$news = [];
$newsError = false;
$newsDetail = null;
$detailNotFound = false;
$detailRequested = array_key_exists('id', $_GET);
$pageSize = 9;
$currentPage = 1;
$totalPages = 1;

if ($detailRequested) {
    $rawId = $_GET['id'];
    if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
        http_response_code(404);
        $detailNotFound = true;
    } else {
        try {
            $statement = $koneksi->prepare(
                'SELECT id, tanggal, judul, ringkasan, kategori, isi, gambar FROM berita WHERE id = ?'
            );
            if (!$statement) {
                throw new RuntimeException('Public Berita detail query could not be prepared.');
            }

            $newsId = (int) $rawId;
            $statement->bind_param('i', $newsId);
            if (!$statement->execute()) {
                $statement->close();
                throw new RuntimeException('Public Berita detail query could not be executed.');
            }

            $result = $statement->get_result();
            $newsDetail = $result ? $result->fetch_assoc() : null;
            $statement->close();
            if ($newsDetail === null) {
                http_response_code(404);
                $detailNotFound = true;
            }
        } catch (Throwable $exception) {
            error_log('Public Berita detail query failed: ' . $exception->getMessage());
            $newsError = true;
        }
    }
} else {
    $rawPage = $_GET['page'] ?? '1';
    if (is_string($rawPage) && ctype_digit($rawPage) && (int) $rawPage > 0) {
        $currentPage = (int) $rawPage;
    }

    try {
        $countResult = $koneksi->query('SELECT COUNT(*) AS total FROM berita');
        if (!$countResult) {
            throw new RuntimeException('Public Berita count query failed.');
        }
        $countRow = $countResult->fetch_assoc();
        $countResult->free();
        $totalNews = (int) ($countRow['total'] ?? 0);
        $totalPages = max(1, (int) ceil($totalNews / $pageSize));
        $currentPage = min($currentPage, $totalPages);
        $offset = ($currentPage - 1) * $pageSize;

        $statement = $koneksi->prepare(
            'SELECT id, tanggal, judul, ringkasan, isi, kategori, gambar FROM berita ORDER BY tanggal DESC, id DESC LIMIT ? OFFSET ?'
        );
        if (!$statement) {
            throw new RuntimeException('Public Berita query could not be prepared.');
        }
        $statement->bind_param('ii', $pageSize, $offset);
        if (!$statement->execute()) {
            $statement->close();
            throw new RuntimeException('Public Berita query could not be executed.');
        }

        $result = $statement->get_result();
        if (!$result) {
            $statement->close();
            throw new RuntimeException('Public Berita result could not be read.');
        }
        while ($row = $result->fetch_assoc()) {
            $news[] = $row;
        }
        $statement->close();
    } catch (Throwable $exception) {
        error_log('Public Berita query failed: ' . $exception->getMessage());
        $newsError = true;
        $news = [];
    }
}

function berita_public_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function berita_public_date(string $date): string
{
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
        return $date;
    }

    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    return (int) $dateObject->format('j') . ' ' . $months[(int) $dateObject->format('n')] . ' ' . $dateObject->format('Y');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $newsDetail ? berita_public_escape((string) $newsDetail['judul']) . ' - ' : ''; ?>Berita Desa Pemayahan</title>
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

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <?php if ($detailRequested): ?>
            <div class="mx-auto max-w-4xl">
                <a href="berita.php" class="mb-6 inline-flex items-center text-sm font-bold text-indigo-900 hover:text-amber-600">← Kembali ke semua berita</a>
                <?php if ($newsError): ?>
                    <section role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-12 text-center text-sm text-amber-950">Berita belum dapat dimuat saat ini. Silakan coba kembali nanti.</section>
                <?php elseif ($detailNotFound): ?>
                    <section class="rounded-2xl border border-gray-100 bg-white px-5 py-12 text-center shadow-sm">
                        <h2 class="font-extrabold text-gray-800">Berita tidak ditemukan.</h2>
                        <p class="mt-2 text-sm text-gray-500">Berita mungkin telah dihapus atau tautannya tidak valid.</p>
                    </section>
                <?php else: ?>
                    <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                        <div class="relative max-h-[34rem] min-h-52 bg-gray-100">
                            <?php if (!empty($newsDetail['gambar'])): ?>
                                <img src="berita_image.php?id=<?php echo (int) $newsDetail['id']; ?>" alt="<?php echo berita_public_escape((string) $newsDetail['judul']); ?>" onerror="this.hidden=true; this.nextElementSibling.hidden=false" class="h-full max-h-[34rem] min-h-52 w-full object-cover">
                                <div hidden class="absolute inset-0 flex items-center justify-center font-bold text-gray-400">Informasi Desa</div>
                            <?php else: ?>
                                <div class="flex min-h-52 items-center justify-center font-bold text-gray-400">Informasi Desa</div>
                            <?php endif; ?>
                        </div>
                        <div class="p-6 sm:p-10">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold uppercase text-amber-700"><?php echo berita_public_escape((string) ($newsDetail['kategori'] ?? 'Informasi Desa')); ?></span>
                                <time class="text-sm font-semibold text-gray-500" datetime="<?php echo berita_public_escape((string) $newsDetail['tanggal']); ?>"><?php echo berita_public_escape(berita_public_date((string) $newsDetail['tanggal'])); ?></time>
                            </div>
                            <h2 class="mt-4 text-2xl font-black leading-tight text-gray-900 md:text-4xl"><?php echo berita_public_escape((string) $newsDetail['judul']); ?></h2>
                            <?php if ((string) ($newsDetail['ringkasan'] ?? '') !== ''): ?>
                                <p class="mt-5 border-l-4 border-amber-400 pl-4 text-base font-semibold leading-7 text-gray-600"><?php echo berita_public_escape((string) $newsDetail['ringkasan']); ?></p>
                            <?php endif; ?>
                            <div class="mt-7 whitespace-pre-line text-sm leading-7 text-gray-700 sm:text-base"><?php
                                $articleContent = (string) ($newsDetail['isi'] ?? '');
                                echo berita_public_escape($articleContent !== '' ? $articleContent : (string) ($newsDetail['ringkasan'] ?? 'Isi berita belum tersedia.'));
                            ?></div>
                        </div>
                    </article>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-center max-w-2xl mx-auto mb-12">
                <span class="text-amber-500 text-xs font-extrabold uppercase tracking-widest">INFORMASI TERKINI</span>
                <h2 class="text-2xl md:text-3xl font-black text-gray-900 mt-1">Berita & Pengumuman Desa</h2>
                <p class="text-gray-500 text-sm mt-2">Kumpulan berita resmi, liputan kegiatan, dan pengumuman untuk seluruh warga desa.</p>
            </div>

            <?php if ($newsError): ?>
                <div role="alert" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    Berita belum dapat dimuat saat ini. Silakan coba kembali nanti.
                </div>
            <?php elseif ($news === []): ?>
                <section class="rounded-2xl border border-gray-100 bg-white px-5 py-12 text-center shadow-sm">
                    <p class="font-extrabold text-gray-800">Belum ada berita yang dipublikasikan.</p>
                    <p class="mt-2 text-sm text-gray-500">Berita akan tampil di sini setelah ditambahkan melalui dashboard admin.</p>
                </section>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <?php foreach ($news as $item): ?>
                        <article class="bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-md transition">
                            <a href="berita.php?id=<?php echo (int) $item['id']; ?>" class="relative block h-48 bg-gray-200">
                                <?php if (!empty($item['gambar'])): ?>
                                    <img src="berita_image.php?id=<?php echo (int) $item['id']; ?>" alt="<?php echo berita_public_escape((string) $item['judul']); ?>" loading="lazy" onerror="this.hidden=true; this.nextElementSibling.hidden=false" class="h-full w-full object-cover">
                                    <span hidden class="absolute inset-0 flex items-center justify-center text-gray-400 font-bold">Informasi Desa</span>
                                <?php else: ?>
                                    <span class="absolute inset-0 flex items-center justify-center text-gray-400 font-bold">Informasi Desa</span>
                                <?php endif; ?>
                            </a>
                            <div class="p-6">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold uppercase text-amber-700"><?php echo berita_public_escape((string) ($item['kategori'] ?? 'Informasi Desa')); ?></span>
                                    <time class="text-xs font-semibold text-gray-500" datetime="<?php echo berita_public_escape((string) $item['tanggal']); ?>"><?php echo berita_public_escape(berita_public_date((string) $item['tanggal'])); ?></time>
                                </div>
                                <h3 class="font-extrabold text-lg text-gray-900 mt-3 leading-snug">
                                    <a href="berita.php?id=<?php echo (int) $item['id']; ?>" class="hover:text-indigo-900"><?php echo berita_public_escape((string) $item['judul']); ?></a>
                                </h3>
                                <?php $summary = (string) ($item['ringkasan'] ?? ''); ?>
                                <?php if ($summary === '') { $summary = (string) ($item['isi'] ?? ''); } ?>
                                <?php if ($summary !== ''): ?>
                                    <p class="text-gray-600 text-sm mt-2 line-clamp-3"><?php echo berita_public_escape($summary); ?></p>
                                <?php endif; ?>
                                <a href="berita.php?id=<?php echo (int) $item['id']; ?>" class="mt-5 inline-flex text-sm font-bold text-indigo-900 hover:text-amber-600">Baca Selengkapnya →</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Navigasi halaman berita" class="mt-10 flex flex-wrap items-center justify-center gap-2">
                        <?php if ($currentPage > 1): ?>
                            <a href="berita.php?page=<?php echo $currentPage - 1; ?>" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 hover:border-amber-400">Sebelumnya</a>
                        <?php endif; ?>
                        <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                            <a href="berita.php?page=<?php echo $pageNumber; ?>" aria-current="<?php echo $pageNumber === $currentPage ? 'page' : 'false'; ?>" class="rounded-lg border px-4 py-2 text-sm font-bold <?php echo $pageNumber === $currentPage ? 'border-indigo-900 bg-indigo-900 text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-amber-400'; ?>"><?php echo $pageNumber; ?></a>
                        <?php endfor; ?>
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="berita.php?page=<?php echo $currentPage + 1; ?>" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 hover:border-amber-400">Berikutnya</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>

</body>
</html>