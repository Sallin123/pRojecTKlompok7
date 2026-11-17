<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/layout.php';

$flash = admin_module_take_flash('potensi_flash');
$oldInput = admin_module_take_old_input('potensi_old_input');
$errorMessage = '';
$tableAvailable = false;
$potentials = [];
$categories = [];
$record = null;
$formMode = '';
$detailId = null;

try {
    $potensiTableExists = admin_module_missing_tables($koneksi, ['potensi_desa']) === [];
    $tableAvailable = $potensiTableExists;
    if ($potensiTableExists) {
        $priceColumn = admin_module_execute(
            $koneksi,
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            'ss',
            ['potensi_desa', 'harga']
        );
        $priceColumn->bind_result($priceColumnCount);
        $priceColumn->fetch();
        $priceColumn->close();
        $tableAvailable = (int) $priceColumnCount === 1;
    }
    if (!$tableAvailable) {
        $errorMessage = $potensiTableExists
            ? 'Kolom harga belum tersedia. Jalankan database/potensi_harga.sql satu kali pada database db_desa_pemulihan.'
            : 'Tabel Potensi Desa belum tersedia. Jalankan SQL migrasi untuk mengaktifkan pengelolaan.';
    }
} catch (Throwable $exception) {
    error_log('Admin Potensi schema check failed: ' . $exception->getMessage());
    $errorMessage = 'Struktur Potensi Desa tidak dapat diperiksa saat ini.';
}

$rawId = $_GET['id'] ?? '';
if (is_string($rawId) && ctype_digit($rawId) && (int) $rawId > 0) {
    $detailId = (int) $rawId;
} elseif (isset($_GET['id'])) {
    $errorMessage = 'ID potensi tidak valid.';
}
$rawForm = $_GET['form'] ?? '';
if (is_string($rawForm) && in_array($rawForm, ['tambah', 'edit'], true)) {
    $formMode = $rawForm;
}
if ($formMode === 'edit' && $detailId === null) {
    $formMode = '';
    $errorMessage = 'ID potensi harus tersedia untuk mengedit.';
}

if ($tableAvailable) {
    try {
        $categoryQuery = admin_module_execute($koneksi, 'SELECT DISTINCT kategori FROM potensi_desa ORDER BY kategori LIMIT 100');
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
                'SELECT id, kategori, judul, harga, deskripsi, nama_file FROM potensi_desa WHERE id = ?',
                'i',
                [$detailId]
            );
            $result = $lookup->get_result();
            $record = $result ? $result->fetch_assoc() : null;
            $lookup->close();
            if ($record === null) {
                $formMode = '';
                $detailId = null;
                $errorMessage = 'Data potensi tidak ditemukan.';
            }
        }

        if ($formMode === '') {
            $list = admin_module_execute(
                $koneksi,
                'SELECT id, kategori, judul, harga, deskripsi, nama_file FROM potensi_desa ORDER BY id DESC'
            );
            $result = $list->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $potentials[] = $row;
                }
            }
            $list->close();
        }
    } catch (Throwable $exception) {
        error_log('Admin Potensi query failed: ' . $exception->getMessage());
        $errorMessage = 'Data Potensi Desa tidak dapat dimuat saat ini.';
        $potentials = [];
        $record = null;
        $formMode = '';
        $detailId = null;
    }
}

$pageTitle = $formMode === 'tambah' ? 'Tambah Potensi' : ($formMode === 'edit' ? 'Edit Potensi' : 'Potensi Desa');
admin_render_header($pageTitle, $_SESSION['admin_username']);
?>
<div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
        <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Konten Website Publik</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950"><?php echo admin_module_escape($pageTitle); ?></h2>
        <p class="mt-1 text-sm text-slate-500">Kelola kategori, judul, harga, deskripsi, dan gambar potensi desa.</p>
    </div>
    <?php if ($tableAvailable && $formMode === ''): ?>
        <a href="potensi.php?form=tambah" class="rounded-xl bg-amber-500 px-4 py-3 text-center text-xs font-extrabold text-slate-950 hover:bg-amber-400">Tambah Potensi</a>
    <?php elseif ($tableAvailable && $formMode !== ''): ?>
        <a href="potensi.php" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Kembali ke Potensi</a>
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
        <p>Modul Potensi Desa belum dapat dikelola karena struktur tabel database belum lengkap. Tidak ada perubahan database yang dijalankan otomatis.</p>
        <p class="mt-2">SQL awal tersedia di <code>database/potensi.sql</code>. Untuk tabel yang sudah ada, jalankan <code>database/potensi_harga.sql</code> satu kali pada database <code>db_desa_pemulihan</code>.</p>
    </section>
<?php elseif ($formMode !== ''): ?>
    <?php
    $formValues = [
        'kategori' => (string) ($oldInput['kategori'] ?? $record['kategori'] ?? ''),
        'judul' => (string) ($oldInput['judul'] ?? $record['judul'] ?? ''),
        'harga' => (string) ($oldInput['harga'] ?? $record['harga'] ?? ''),
        'deskripsi' => (string) ($oldInput['deskripsi'] ?? $record['deskripsi'] ?? ''),
    ];
    ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <form method="POST" action="potensi_action.php" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
            <input type="hidden" name="action" value="<?php echo $formMode === 'tambah' ? 'create' : 'update'; ?>">
            <?php if ($formMode === 'edit'): ?><input type="hidden" name="id" value="<?php echo (int) $record['id']; ?>"><?php endif; ?>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="kategori" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Kategori *</label>
                    <input id="kategori" name="kategori" maxlength="100" required list="potensi-categories" value="<?php echo admin_module_escape($formValues['kategori']); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    <datalist id="potensi-categories"><?php foreach ($categories as $category): ?><option value="<?php echo admin_module_escape($category); ?>"><?php endforeach; ?></datalist>
                </div>
                <div>
                    <label for="judul" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Judul *</label>
                    <input id="judul" name="judul" maxlength="150" required value="<?php echo admin_module_escape($formValues['judul']); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>
            </div>
            <div>
                <label for="harga" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Harga (Rp, opsional)</label>
                <input id="harga" name="harga" type="number" min="0" max="999999999999999" step="1" value="<?php echo admin_module_escape($formValues['harga']); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                <p class="mt-2 text-xs text-slate-500">Kosongkan jika harga tidak dicantumkan.</p>
            </div>
            <div>
                <label for="deskripsi" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" maxlength="5000" rows="4" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15"><?php echo admin_module_escape($formValues['deskripsi']); ?></textarea>
            </div>
            <div>
                <label for="gambar" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600"><?php echo $formMode === 'tambah' ? 'Gambar (opsional)' : 'Ganti gambar (opsional)'; ?></label>
                <input id="gambar" name="gambar" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-amber-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-amber-950">
                <p class="mt-2 text-xs text-slate-500">JPEG, PNG, atau WebP; maksimal 5 MB.</p>
                <?php if ($formMode === 'edit' && !empty($record['nama_file'])): ?>
                    <img src="../potensi_image.php?id=<?php echo (int) $record['id']; ?>&amp;preview=admin" alt="Gambar Potensi saat ini" class="mt-4 max-h-64 rounded-xl border border-slate-200 object-contain">
                <?php endif; ?>
            </div>
            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                <a href="potensi.php" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-xs font-extrabold text-slate-700">Batal</a>
                <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-extrabold text-white hover:bg-slate-800">Simpan Potensi</button>
            </div>
        </form>
    </section>
<?php elseif ($potentials === []): ?>
    <section class="rounded-2xl border border-slate-200 bg-white px-5 py-12 text-center">
        <p class="font-extrabold text-slate-800">Belum ada informasi potensi desa.</p>
        <p class="mt-2 text-sm text-slate-500">Tambahkan potensi desa untuk ditampilkan pada website publik.</p>
    </section>
<?php else: ?>
    <?php
    $potensiIcons = [
        'Sektor Pertanian' => '🌾',
        'Sektor Ekonomi Kreatif' => '🛍️',
        'Sektor Pariwisata' => '🏞️',
    ];
    ?>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($potentials as $potential): ?>
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md">
                <?php if (!empty($potential['nama_file'])): ?>
                    <img src="../potensi_image.php?id=<?php echo (int) $potential['id']; ?>&amp;preview=admin" alt="<?php echo admin_module_escape((string) $potential['judul']); ?>" loading="lazy" class="h-32 w-full bg-slate-100 object-cover sm:h-36">
                <?php else: ?>
                    <div class="grid h-32 place-items-center bg-amber-50 text-5xl text-amber-800 sm:h-36" aria-hidden="true"><?php echo $potensiIcons[(string) $potential['kategori']] ?? '✦'; ?></div>
                <?php endif; ?>
                <div class="p-4 sm:p-5">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700"><?php echo admin_module_escape((string) $potential['kategori']); ?></p>
                    <h3 class="mt-2 text-lg font-black text-slate-950"><?php echo admin_module_escape((string) $potential['judul']); ?></h3>
                    <p class="mt-2 text-sm font-extrabold text-emerald-700"><?php echo $potential['harga'] === null ? 'Harga belum dicantumkan' : 'Rp ' . number_format((int) $potential['harga'], 0, ',', '.'); ?></p>
                    <p class="mt-3 text-sm leading-6 text-slate-600"><?php echo nl2br(admin_module_escape((string) ($potential['deskripsi'] ?? ''))); ?></p>
                    <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                        <a href="potensi.php?form=edit&amp;id=<?php echo (int) $potential['id']; ?>" class="text-xs font-extrabold text-amber-700 hover:text-amber-900">Edit</a>
                        <form method="POST" action="potensi_action.php" onsubmit="return confirm('Hapus data potensi ini? Tindakan ini tidak dapat dibatalkan.');">
                            <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $potential['id']; ?>">
                            <button type="submit" class="text-xs font-extrabold text-red-700 hover:text-red-900">Hapus</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php admin_render_footer(); ?>
