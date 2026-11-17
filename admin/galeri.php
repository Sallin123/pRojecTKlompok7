<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/layout.php';

$flash = admin_module_take_flash('galeri_flash');
$oldInput = admin_module_take_old_input('galeri_old_input');
$errorMessage = '';
$tableAvailable = false;
$rows = [];
$record = null;
$categories = [];
$formMode = '';
$detailId = null;
$search = '';
$categoryFilter = '';
$statusFilter = '';
$page = 1;
$pageSize = 12;
$totalRows = 0;
$totalPages = 1;

try {
    $tableAvailable = admin_module_missing_tables($koneksi, ['galeri']) === [];
    if (!$tableAvailable) {
        $errorMessage = 'Tabel Galeri belum tersedia. Modul belum dapat digunakan sampai migrasi disetujui dan diterapkan.';
    }
} catch (Throwable $exception) {
    error_log('Admin Galeri schema check failed: ' . $exception->getMessage());
    $errorMessage = 'Struktur Galeri tidak dapat diperiksa saat ini.';
}

$rawSearch = $_GET['q'] ?? '';
if (is_string($rawSearch)) {
    $search = trim($rawSearch);
    if (mb_strlen($search, 'UTF-8') > 120) {
        $search = mb_substr($search, 0, 120, 'UTF-8');
        $errorMessage = 'Pencarian dipotong menjadi maksimal 120 karakter.';
    }
}
$rawCategory = $_GET['kategori'] ?? '';
if (is_string($rawCategory)) {
    $categoryFilter = trim($rawCategory);
    if (mb_strlen($categoryFilter, 'UTF-8') > 100) {
        $categoryFilter = '';
        $errorMessage = 'Filter kategori tidak valid.';
    }
}
$rawStatus = $_GET['status'] ?? 'semua';
if (is_string($rawStatus) && in_array($rawStatus, ['semua', 'draft', 'publikasi'], true)) {
    $statusFilter = $rawStatus;
} else {
    $statusFilter = 'semua';
    $errorMessage = 'Filter status tidak valid; semua status ditampilkan.';
}
$rawPage = $_GET['page'] ?? '1';
$validatedPage = is_string($rawPage) && ctype_digit($rawPage)
    ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($validatedPage !== false) {
    $page = $validatedPage;
} elseif (isset($_GET['page'])) {
    $errorMessage = 'Nomor halaman tidak valid; halaman pertama ditampilkan.';
}

$rawId = $_GET['id'] ?? '';
if (is_string($rawId) && ctype_digit($rawId) && (int) $rawId > 0) {
    $detailId = (int) $rawId;
} elseif (isset($_GET['id'])) {
    $errorMessage = 'ID foto tidak valid.';
}
$rawForm = $_GET['form'] ?? '';
if (is_string($rawForm) && in_array($rawForm, ['tambah', 'edit'], true)) {
    $formMode = $rawForm;
}
if ($formMode === 'edit' && $detailId === null) {
    $formMode = '';
    $errorMessage = 'ID foto harus tersedia untuk mengedit.';
}

if ($tableAvailable) {
    try {
        $categoryQuery = admin_module_execute($koneksi, 'SELECT DISTINCT kategori FROM galeri ORDER BY kategori LIMIT 100');
        $result = $categoryQuery->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = (string) $row['kategori'];
            }
        }
        $categoryQuery->close();

        if ($formMode === 'edit' && $detailId !== null) {
            $lookup = admin_module_execute(
                $koneksi,
                'SELECT id, judul, keterangan, kategori, tanggal_dokumentasi, status_publikasi FROM galeri WHERE id = ?',
                'i',
                [$detailId]
            );
            $result = $lookup->get_result();
            $record = $result ? $result->fetch_assoc() : null;
            $lookup->close();
            if ($record === null) {
                $formMode = '';
                $detailId = null;
                $errorMessage = 'Foto Galeri tidak ditemukan.';
            }
        }

        if ($formMode === '') {
            $conditions = [];
            $types = '';
            $values = [];
            if ($search !== '') {
                $conditions[] = '(judul LIKE ? OR keterangan LIKE ? OR kategori LIKE ?)';
                $types .= 'sss';
                $pattern = '%' . $search . '%';
                array_push($values, $pattern, $pattern, $pattern);
            }
            if ($categoryFilter !== '') {
                $conditions[] = 'kategori = ?';
                $types .= 's';
                $values[] = $categoryFilter;
            }
            if ($statusFilter !== 'semua') {
                $conditions[] = 'status_publikasi = ?';
                $types .= 's';
                $values[] = $statusFilter;
            }
            $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
            $count = admin_module_execute($koneksi, 'SELECT COUNT(*) FROM galeri' . $where, $types, $values);
            $count->bind_result($totalRows);
            $count->fetch();
            $count->close();
            $totalRows = (int) $totalRows;
            $totalPages = max(1, (int) ceil($totalRows / $pageSize));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $pageSize;
            $list = admin_module_execute(
                $koneksi,
                'SELECT id, judul, keterangan, kategori, tanggal_dokumentasi, status_publikasi FROM galeri'
                    . $where . ' ORDER BY tanggal_dokumentasi DESC, id DESC LIMIT ? OFFSET ?',
                $types . 'ii',
                array_merge($values, [$pageSize, $offset])
            );
            $result = $list->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
            $list->close();
        }
    } catch (Throwable $exception) {
        error_log('Admin Galeri query failed: ' . $exception->getMessage());
        $errorMessage = 'Data Galeri tidak dapat dimuat saat ini.';
        $rows = [];
        $record = null;
        $formMode = '';
        $detailId = null;
    }
}

$pageTitle = $formMode === 'tambah' ? 'Tambah Foto' : ($formMode === 'edit' ? 'Edit Foto' : 'Galeri Desa');
admin_render_header($pageTitle, $_SESSION['admin_username']);
?>
<div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
        <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Dokumentasi Desa</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950"><?php echo admin_module_escape($pageTitle); ?></h2>
        <p class="mt-1 text-sm text-slate-500">Kelola foto yang ditampilkan pada Galeri publik.</p>
    </div>
    <?php if ($tableAvailable && $formMode === ''): ?>
        <a href="galeri.php?form=tambah" class="rounded-xl bg-amber-500 px-4 py-3 text-center text-xs font-extrabold text-slate-950 hover:bg-amber-400">Tambah Foto</a>
    <?php elseif ($tableAvailable && $formMode !== ''): ?>
        <a href="galeri.php" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Kembali ke Galeri</a>
    <?php endif; ?>
</div>

<?php if (is_array($flash) && isset($flash['message'])): ?>
    <div role="<?php echo ($flash['type'] ?? '') === 'success' ? 'status' : 'alert'; ?>" class="mb-5 rounded-xl border px-4 py-3 text-sm font-semibold <?php echo ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>"><?php echo admin_module_escape((string) $flash['message']); ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div role="alert" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900"><?php echo admin_module_escape($errorMessage); ?></div>
<?php endif; ?>

<?php if (!$tableAvailable): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 text-sm leading-6 text-slate-600">
        <p>Modul Galeri belum aktif karena tabel penyimpanan belum tersedia. Tidak ada perubahan database yang dijalankan oleh aplikasi.</p>
        <p class="mt-2">Usulan SQL tersedia di <code>database/galeri.sql</code>. Buat backup dan jalankan migrasi secara manual setelah mendapat persetujuan.</p>
    </section>
<?php elseif ($formMode !== ''): ?>
    <?php
    $formValues = [
        'judul' => (string) ($oldInput['judul'] ?? $record['judul'] ?? ''),
        'keterangan' => (string) ($oldInput['keterangan'] ?? $record['keterangan'] ?? ''),
        'kategori' => (string) ($oldInput['kategori'] ?? $record['kategori'] ?? ''),
        'tanggal_dokumentasi' => (string) ($oldInput['tanggal_dokumentasi'] ?? $record['tanggal_dokumentasi'] ?? date('Y-m-d')),
        'status_publikasi' => (string) ($oldInput['status_publikasi'] ?? $record['status_publikasi'] ?? 'draft'),
    ];
    ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <form method="POST" action="galeri_action.php" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
            <input type="hidden" name="action" value="<?php echo $formMode === 'tambah' ? 'create' : 'update'; ?>">
            <?php if ($formMode === 'edit'): ?><input type="hidden" name="id" value="<?php echo (int) $record['id']; ?>"><?php endif; ?>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="judul" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Judul foto *</label>
                    <input id="judul" name="judul" maxlength="150" required value="<?php echo admin_module_escape($formValues['judul']); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>
                <div>
                    <label for="kategori" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Kategori *</label>
                    <input id="kategori" name="kategori" maxlength="100" required list="galeri-categories" value="<?php echo admin_module_escape($formValues['kategori']); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    <datalist id="galeri-categories"><?php foreach ($categories as $category): ?><option value="<?php echo admin_module_escape($category); ?>"><?php endforeach; ?></datalist>
                </div>
                <div>
                    <label for="tanggal_dokumentasi" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Tanggal dokumentasi *</label>
                    <input id="tanggal_dokumentasi" name="tanggal_dokumentasi" type="date" required value="<?php echo admin_module_escape($formValues['tanggal_dokumentasi']); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                </div>
                <div>
                    <label for="status_publikasi" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Status publikasi *</label>
                    <select id="status_publikasi" name="status_publikasi" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">
                        <option value="draft" <?php echo $formValues['status_publikasi'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="publikasi" <?php echo $formValues['status_publikasi'] === 'publikasi' ? 'selected' : ''; ?>>Dipublikasikan</option>
                    </select>
                </div>
            </div>
            <div>
                <label for="keterangan" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Keterangan</label>
                <textarea id="keterangan" name="keterangan" maxlength="5000" rows="4" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15"><?php echo admin_module_escape($formValues['keterangan']); ?></textarea>
            </div>
            <div>
                <label for="foto" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600"><?php echo $formMode === 'tambah' ? 'Pilih foto *' : 'Ganti foto (opsional)'; ?></label>
                <input id="foto" name="foto" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" <?php echo $formMode === 'tambah' ? 'required' : ''; ?> class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-amber-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-amber-950">
                <p class="mt-2 text-xs text-slate-500">JPEG, PNG, atau WebP; maksimal 5 MB.</p>
                <?php if ($formMode === 'edit'): ?>
                    <img id="foto-preview" src="../galeri_image.php?id=<?php echo (int) $record['id']; ?>&amp;preview=admin" data-current-src="../galeri_image.php?id=<?php echo (int) $record['id']; ?>&amp;preview=admin" alt="Pratinjau foto saat ini" class="mt-4 max-h-64 rounded-xl border border-slate-200 object-contain">
                <?php else: ?>
                    <img id="foto-preview" alt="Pratinjau foto pilihan" class="mt-4 hidden max-h-64 rounded-xl border border-slate-200 object-contain">
                <?php endif; ?>
            </div>
            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                <a href="galeri.php" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-xs font-extrabold text-slate-700">Batal</a>
                <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-extrabold text-white hover:bg-slate-800">Simpan Foto</button>
            </div>
        </form>
    </section>
<?php else: ?>
    <section class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_14rem_12rem_auto]">
            <input name="q" maxlength="120" value="<?php echo admin_module_escape($search); ?>" placeholder="Cari judul, keterangan, kategori..." class="min-w-0 rounded-xl border border-slate-300 px-4 py-3 text-sm">
            <select name="kategori" class="min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm">
                <option value="">Semua kategori</option>
                <?php foreach ($categories as $category): ?><option value="<?php echo admin_module_escape($category); ?>" <?php echo $categoryFilter === $category ? 'selected' : ''; ?>><?php echo admin_module_escape($category); ?></option><?php endforeach; ?>
            </select>
            <select name="status" class="min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm">
                <option value="semua" <?php echo $statusFilter === 'semua' ? 'selected' : ''; ?>>Semua status</option>
                <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="publikasi" <?php echo $statusFilter === 'publikasi' ? 'selected' : ''; ?>>Dipublikasikan</option>
            </select>
            <button class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-extrabold text-white hover:bg-slate-800">Cari / Filter</button>
        </form>
    </section>
    <?php if ($rows === []): ?>
        <section class="rounded-2xl border border-slate-200 bg-white px-5 py-12 text-center">
            <p class="font-extrabold text-slate-800">Belum ada foto pada filter ini.</p>
            <p class="mt-2 text-sm text-slate-500">Tambahkan foto dokumentasi atau sesuaikan pencarian.</p>
        </section>
    <?php else: ?>
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($rows as $row): ?>
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <img src="../galeri_image.php?id=<?php echo (int) $row['id']; ?>&amp;preview=admin" alt="<?php echo admin_module_escape((string) $row['judul']); ?>" loading="lazy" class="aspect-[4/3] w-full bg-slate-100 object-cover">
                    <div class="p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700"><?php echo admin_module_escape((string) $row['kategori']); ?></p>
                                <h3 class="mt-1 break-words text-base font-black text-slate-950"><?php echo admin_module_escape((string) $row['judul']); ?></h3>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-extrabold <?php echo $row['status_publikasi'] === 'publikasi' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'; ?>"><?php echo $row['status_publikasi'] === 'publikasi' ? 'Publikasi' : 'Draft'; ?></span>
                        </div>
                        <p class="mt-2 text-xs text-slate-500"><?php echo admin_module_escape((string) $row['tanggal_dokumentasi']); ?></p>
                        <?php if ((string) ($row['keterangan'] ?? '') !== ''): ?><p class="mt-3 line-clamp-3 break-words text-sm leading-6 text-slate-600"><?php echo nl2br(admin_module_escape((string) $row['keterangan'])); ?></p><?php endif; ?>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            <a href="galeri.php?form=edit&amp;id=<?php echo (int) $row['id']; ?>" class="text-xs font-extrabold text-amber-700 hover:text-amber-900">Edit</a>
                            <form method="POST" action="galeri_action.php" onsubmit="return confirm('Hapus foto ini dari galeri? Tindakan ini tidak dapat dibatalkan.');">
                                <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                <button type="submit" class="text-xs font-extrabold text-red-700 hover:text-red-900">Hapus</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
        <div class="mt-5 flex flex-col gap-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p><?php echo count($rows); ?> dari <?php echo $totalRows; ?> foto · Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?></p>
            <?php if ($totalPages > 1):
                $parameters = ['q' => $search, 'kategori' => $categoryFilter, 'status' => $statusFilter];
            ?>
                <nav aria-label="Pagination galeri" class="flex gap-2">
                    <?php if ($page > 1): ?><a class="rounded-lg border border-slate-200 bg-white px-3 py-2" href="galeri.php?<?php echo admin_module_escape(http_build_query($parameters + ['page' => $page - 1])); ?>">Sebelumnya</a><?php endif; ?>
                    <?php if ($page < $totalPages): ?><a class="rounded-lg border border-slate-200 bg-white px-3 py-2" href="galeri.php?<?php echo admin_module_escape(http_build_query($parameters + ['page' => $page + 1])); ?>">Berikutnya</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
(() => {
    const input = document.getElementById('foto');
    const preview = document.getElementById('foto-preview');
    if (!input || !preview) return;
    let previousUrl = null;
    input.addEventListener('change', () => {
        if (previousUrl) URL.revokeObjectURL(previousUrl);
        const file = input.files && input.files[0];
        if (!file) {
            if (preview.dataset.currentSrc) {
                preview.src = preview.dataset.currentSrc;
            } else {
                preview.removeAttribute('src');
                preview.classList.add('hidden');
            }
            return;
        }
        previousUrl = URL.createObjectURL(file);
        preview.src = previousUrl;
        preview.classList.remove('hidden');
    });
})();
</script>
<?php admin_render_footer(); ?>
