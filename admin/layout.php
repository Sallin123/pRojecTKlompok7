<?php

function admin_navigation_item_is_current(array $item, string $currentPage): bool
{
    if ($currentPage !== ($item['file'] ?? '')) {
        return false;
    }

    if ($currentPage !== 'profil.php') {
        return true;
    }

    $currentSection = $_GET['section'] ?? '';
    if (!is_string($currentSection)) {
        $currentSection = '';
    }

    return isset($item['section'])
        ? $currentSection === $item['section']
        : $currentSection !== 'struktur';
}

function admin_render_header(string $pageTitle, string $username): void
{
    $safeTitle = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8');
    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $csrfToken = htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8');
    $currentPage = basename($_SERVER['PHP_SELF']);
    $navigationGroups = [
        [
            'title' => 'Ringkasan',
            'items' => [
                ['file' => 'index.php', 'href' => 'index.php', 'label' => 'Dashboard'],
            ],
        ],
        [
            'title' => 'Layanan warga',
            'items' => [
                ['file' => 'penduduk.php', 'href' => 'penduduk.php', 'label' => 'Data Penduduk'],
                ['file' => 'akun_masyarakat.php', 'href' => 'akun_masyarakat.php', 'label' => 'Persetujuan Akun'],
                ['file' => 'bansos.php', 'href' => 'bansos.php?view=program', 'label' => 'Informasi Bansos'],
                ['file' => 'profil.php', 'section' => 'struktur', 'href' => 'profil.php?section=struktur#struktur', 'label' => 'Profil Desa'],
                ['file' => 'aduan.php', 'href' => 'aduan.php', 'label' => 'Pengaduan Online'],
                ['file' => 'surat.php', 'href' => 'surat.php', 'label' => 'Surat Online'],
                ['file' => 'mobil_siaga.php', 'href' => 'mobil_siaga.php', 'label' => 'Mobil Siaga'],
                ['file' => 'posyandu.php', 'href' => 'posyandu.php', 'label' => 'Posyandu'],
            ],
        ],
        [
            'title' => 'Konten desa',
            'items' => [
                ['file' => 'beranda.php', 'href' => 'beranda.php', 'label' => 'Beranda'],
                ['file' => 'profil.php', 'href' => 'profil.php', 'label' => 'Profil Desa'],
                ['file' => 'berita.php', 'href' => 'berita.php', 'label' => 'Berita'],
                ['file' => 'potensi.php', 'href' => 'potensi.php', 'label' => 'Potensi Desa'],
                ['file' => 'galeri.php', 'href' => 'galeri.php', 'label' => 'Galeri Desa'],
            ],
        ],
    ];
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $safeTitle; ?> - Admin Desa Pemayahan</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <div class="min-h-screen lg:flex">
            <aside class="hidden w-64 shrink-0 flex-col bg-slate-950 text-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:overflow-y-auto">
                <a href="index.php" class="flex min-h-[4.5rem] items-center gap-3 border-b border-white/10 px-5">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-amber-500 text-lg font-black text-slate-950">D</span>
                    <span>
                        <span class="block text-sm font-extrabold tracking-tight">DESA <span class="text-amber-400">PEMAYAHAN</span></span>
                        <span class="block text-[10px] uppercase tracking-[0.18em] text-slate-400">Admin Portal</span>
                    </span>
                </a>
                <nav aria-label="Navigasi dashboard" class="flex-1 space-y-5 px-3 py-5">
                    <?php foreach ($navigationGroups as $group): ?>
                        <section>
                            <h2 class="mb-1 px-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-slate-500"><?php echo htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <div class="space-y-0.5">
                                <?php foreach ($group['items'] as $item):
                                    $isCurrent = admin_navigation_item_is_current($item, $currentPage);
                                ?>
                                    <?php if (!empty($item['disabled'])): ?>
                                        <span class="flex min-h-10 cursor-not-allowed items-center justify-between gap-2 rounded-lg px-3 py-2 text-[13px] font-bold text-slate-500" aria-disabled="true">
                                            <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="shrink-0 text-[9px] font-bold uppercase tracking-wider text-slate-600">Belum aktif</span>
                                        </span>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isCurrent ? 'aria-current="page"' : ''; ?>
                                           class="flex min-h-10 items-center justify-between gap-2 rounded-lg px-3 py-2 text-[13px] font-bold transition <?php echo $isCurrent ? 'bg-amber-500 text-slate-950 shadow-sm shadow-amber-950/20' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                                            <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if (!empty($item['readonly'])): ?><span class="shrink-0 text-[9px] font-bold uppercase tracking-wider <?php echo $isCurrent ? 'text-slate-800' : 'text-slate-500'; ?>">Baca-saja</span><?php endif; ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </nav>
                <div class="mt-auto border-t border-white/10 p-4">
                    <a href="../index.php" class="block rounded-lg border border-white/10 px-3 py-2.5 text-center text-xs font-bold text-slate-300 transition hover:border-amber-500/50 hover:text-amber-300">Lihat Website Publik</a>
                </div>
            </aside>

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
                    <div class="flex min-h-20 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <div class="flex items-center gap-3">
                            <details class="relative lg:hidden">
                                <summary class="grid h-10 w-10 cursor-pointer list-none place-items-center rounded-xl border border-slate-200 text-lg font-bold text-slate-700" aria-label="Buka menu dashboard">☰</summary>
                                <nav aria-label="Navigasi dashboard mobile" class="absolute left-0 top-12 z-30 max-h-[75vh] w-[min(20rem,calc(100vw-2rem))] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-3 shadow-xl">
                                    <?php foreach ($navigationGroups as $group): ?>
                                        <section class="mb-3 last:mb-0">
                                            <h2 class="px-3 pb-1 pt-2 text-[10px] font-extrabold uppercase tracking-widest text-slate-400"><?php echo htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                                            <div class="space-y-0.5">
                                                <?php foreach ($group['items'] as $item):
                                                    $isCurrent = admin_navigation_item_is_current($item, $currentPage);
                                                ?>
                                                    <?php if (!empty($item['disabled'])): ?>
                                                        <span class="flex min-h-10 cursor-not-allowed items-center justify-between gap-2 rounded-xl px-3 py-2 text-xs font-bold text-slate-400" aria-disabled="true">
                                                            <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                            <span class="shrink-0 text-[9px] font-bold uppercase tracking-wider text-slate-400">Belum aktif</span>
                                                        </span>
                                                    <?php else: ?>
                                                        <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isCurrent ? 'aria-current="page"' : ''; ?>
                                                           class="flex min-h-10 items-center justify-between gap-2 rounded-xl px-3 py-2 text-xs font-bold <?php echo $isCurrent ? 'bg-amber-100 text-slate-950' : 'text-slate-700 hover:bg-amber-50'; ?>">
                                                            <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                            <?php if (!empty($item['readonly'])): ?><span class="shrink-0 text-[9px] font-bold uppercase tracking-wider text-slate-400">Baca-saja</span><?php endif; ?>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </section>
                                    <?php endforeach; ?>
                                    <a href="../index.php" class="mt-2 block rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50">Lihat Website Publik</a>
                                </nav>
                            </details>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest text-amber-600">Administrator</p>
                                <h1 class="text-lg font-black tracking-tight text-slate-950 sm:text-xl"><?php echo $safeTitle; ?></h1>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="hidden text-right sm:block">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Masuk sebagai</p>
                                <p class="max-w-40 truncate text-sm font-extrabold text-slate-800"><?php echo $safeUsername; ?></p>
                            </div>
                            <form method="POST" action="../logout.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <button type="submit" class="rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-extrabold text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700">Keluar</button>
                            </form>
                        </div>
                    </div>
                </header>
                <main class="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
    <?php
}

function admin_render_footer(): void
{
    ?>
                </main>
                <footer class="px-4 pb-6 text-center text-[11px] font-medium text-slate-400 sm:px-6 lg:px-8">
                    &copy; <?php echo date('Y'); ?> Portal Resmi Desa Pemayahan
                </footer>
            </div>
        </div>
    </body>
    </html>
    <?php
}
