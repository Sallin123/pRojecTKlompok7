<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/module_helpers.php';

$flash = admin_module_take_flash('perangkat_flash');
$oldInput = admin_module_take_old_input('perangkat_old_input');
$errorMessage = '';
$tableAvailable = false;
$rows = [];
$record = null;
$formMode = '';
$detailId = null;
$search = '';
$status = '';
$pageSize = 10;
$page = 1;
$totalRows = 0;
$totalPages = 1;

try {
    $missingTables = admin_module_missing_tables($koneksi, ['perangkat_desa']);
    if ($missingTables === []) {
        $tableAvailable = true;
    } else {
        $errorMessage = 'Tabel perangkat_desa belum tersedia. Modul belum dapat mengelola data; lihat database/dashboard_admin_modules.sql dan jalankan setelah backup database.';
    }
} catch (Throwable $exception) {
    error_log('Admin perangkat schema check failed: ' . $exception->getMessage());
    $errorMessage = 'Struktur data perangkat desa tidak dapat diperiksa saat ini.';
}

$rawSearch = $_GET['q'] ?? '';
if (is_string($rawSearch)) {
    $search = trim($rawSearch);
    if (mb_strlen($search, 'UTF-8') > 120) {
        $search = mb_substr($search, 0, 120, 'UTF-8');
        $errorMessage = 'Pencarian dipotong menjadi maksimal 120 karakter.';
    }
}
$rawStatus = $_GET['status'] ?? 'aktif';
if (is_string($rawStatus) && in_array($rawStatus, ['aktif', 'arsip', 'semua'], true)) {
    $status = $rawStatus;
} else {
    $status = 'aktif';
    $errorMessage = 'Filter status tidak valid; status aktif ditampilkan.';
}
$rawPage = $_GET['page'] ?? '1';
if (is_string($rawPage) && ctype_digit($rawPage)) {
    $validatedPage = filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($validatedPage !== false) {
        $page = $validatedPage;
    } else {
        $errorMessage = 'Nomor halaman tidak valid; halaman pertama ditampilkan.';
    }
} elseif (isset($_GET['page'])) {
    $errorMessage = 'Nomor halaman tidak valid; halaman pertama ditampilkan.';
}
$rawId = $_GET['id'] ?? '';
if (is_string($rawId) && ctype_digit($rawId) && (int) $rawId > 0) {
    $detailId = (int) $rawId;
} elseif (isset($_GET['id'])) {
    $errorMessage = 'ID perangkat desa tidak valid.';
}
$rawForm = $_GET['form'] ?? '';
if (is_string($rawForm) && in_array($rawForm, ['tambah', 'edit'], true)) {
    $formMode = $rawForm;
}
if ($formMode === 'edit' && $detailId === null) {
    $formMode = '';
    $errorMessage = 'ID data wajib tersedia untuk mengedit perangkat desa.';
}

if ($tableAvailable) {
    try {
        if ($detailId !== null) {
            $statement = admin_module_execute(
                $koneksi,
                'SELECT id, nama_lengkap, jabatan, informasi, foto, urutan, status_data, created_at, updated_at FROM perangkat_desa WHERE id = ?',
                'i',
                [$detailId]
            );
            $result = $statement->get_result();
            $record = $result ? $result->fetch_assoc() : null;
            $statement->close();
            if ($record === null) {
                $formMode = '';
                $errorMessage = 'Data perangkat desa tidak ditemukan.';
            }
        }

        if ($formMode === '' && $detailId === null) {
            $conditions = [];
            $types = '';
            $values = [];
            if ($search !== '') {
                $conditions[] = '(nama_lengkap LIKE ? OR jabatan LIKE ?)';
                $types .= 'ss';
                $values[] = '%' . $search . '%';
                $values[] = '%' . $search . '%';
            }
            if ($status !== 'semua') {
                $conditions[] = 'status_data = ?';
                $types .= 's';
                $values[] = $status;
            }
            $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
            $count = admin_module_execute($koneksi, 'SELECT COUNT(*) FROM perangkat_desa' . $where, $types, $values);
            $count->bind_result($totalRows);
            $count->fetch();
            $count->close();
            $totalRows = (int) $totalRows;
            $totalPages = max(1, (int) ceil($totalRows / $pageSize));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $pageSize;
            $listValues = array_merge($values, [$pageSize, $offset]);
            $list = admin_module_execute(
                $koneksi,
                'SELECT id, nama_lengkap, jabatan, foto, urutan, status_data FROM perangkat_desa' . $where . ' ORDER BY urutan, nama_lengkap, id LIMIT ? OFFSET ?',
                $types . 'ii',
                $listValues
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
        error_log('Admin perangkat query failed: ' . $exception->getMessage());
        $errorMessage = 'Data perangkat desa tidak dapat dimuat saat ini.';
        $rows = [];
        $record = null;
    }
}

$pageTitle = $formMode === 'tambah'
    ? 'Tambah Perangkat Desa'
    : ($formMode === 'edit' ? 'Edit Perangkat Desa' : ($detailId !== null ? 'Detail Perangkat Desa' : 'Perangkat Desa'));
admin_render_header($pageTitle, $_SESSION['admin_username']);
?>
<div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
        <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Pengelolaan Aparatur</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950"><?php echo admin_module_escape($pageTitle); ?></h2>
    </div>
    <?php if ($tableAvailable && $formMode === '' && $detailId === null): ?>
        <a href="perangkat.php?form=tambah" class="rounded-xl bg-amber-500 px-4 py-3 text-center text-xs font-extrabold text-slate-950 hover:bg-amber-400">Tambah Perangkat</a>
    <?php elseif ($tableAvailable && ($formMode !== '' || $detailId !== null)): ?>
        <a href="perangkat.php" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-700">Kembali ke daftar</a>
    <?php endif; ?>
</div>
<?php if (is_array($flash) && isset($flash['message'])): ?>
    <div role="<?php echo ($flash['type'] ?? '') === 'success' ? 'status' : 'alert'; ?>" class="mb-5 rounded-xl border px-4 py-3 text-sm font-semibold <?php echo ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>"><?php echo admin_module_escape((string) $flash['message']); ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div role="alert" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900"><?php echo admin_module_escape($errorMessage); ?></div>
<?php endif; ?>
<?php if ($tableAvailable && ($formMode === 'tambah' || $formMode === 'edit')): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <form method="POST" action="perangkat_action.php" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
            <input type="hidden" name="action" value="<?php echo $formMode === 'tambah' ? 'create' : 'update'; ?>">
            <?php if ($formMode === 'edit'): ?><input type="hidden" name="id" value="<?php echo (int) $record['id']; ?>"><?php endif; ?>
            <div class="grid gap-5 sm:grid-cols-2">
                <div><label for="nama_lengkap" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Nama lengkap *</label><input id="nama_lengkap" name="nama_lengkap" maxlength="120" required value="<?php echo admin_module_escape((string) ($oldInput['nama_lengkap'] ?? $record['nama_lengkap'] ?? '')); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"></div>
                <div><label for="jabatan" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Jabatan *</label><input id="jabatan" name="jabatan" maxlength="120" required value="<?php echo admin_module_escape((string) ($oldInput['jabatan'] ?? $record['jabatan'] ?? '')); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"></div>
            </div>
            <div><label for="informasi" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Informasi tambahan</label><textarea id="informasi" name="informasi" maxlength="2000" rows="4" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><?php echo admin_module_escape((string) ($oldInput['informasi'] ?? $record['informasi'] ?? '')); ?></textarea></div>
            <div class="max-w-xs"><label for="urutan" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Urutan tampilan</label><input id="urutan" name="urutan" type="number" min="0" max="65535" value="<?php echo admin_module_escape((string) ($oldInput['urutan'] ?? $record['urutan'] ?? 0)); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"></div>
            <div class="max-w-xl">
                <label for="foto" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Foto perangkat (opsional)</label>
                <input id="foto" name="foto" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">
                <p class="mt-2 text-xs text-slate-500">JPEG, PNG, atau WebP; maksimal 5 MB. Jika mengedit, biarkan kosong untuk mempertahankan foto saat ini.</p>
                <?php if ($formMode === 'edit' && !empty($record['foto'])): ?>
                    <div class="mt-4">
                        <p class="mb-2 text-xs font-bold text-slate-600">Foto saat ini</p>
                        <img src="perangkat_image.php?id=<?php echo (int) $record['id']; ?>" alt="Foto <?php echo admin_module_escape((string) $record['nama_lengkap']); ?>" class="max-h-64 rounded-xl border border-slate-200 object-contain">
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><a href="perangkat.php" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-xs font-extrabold">Batal</a><button class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-extrabold text-white">Simpan</button></div>
        </form>
    </section>
<?php elseif ($tableAvailable && $detailId !== null): ?>
    <?php if ($record === null): ?><section class="rounded-2xl border bg-white p-6 text-sm text-slate-600">Data tidak ditemukan.</section><?php else: ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <?php if (!empty($record['foto'])): ?>
                <img src="perangkat_image.php?id=<?php echo (int) $record['id']; ?>" alt="Foto <?php echo admin_module_escape((string) $record['nama_lengkap']); ?>" class="mb-6 max-h-72 rounded-xl border border-slate-200 object-contain">
            <?php endif; ?>
            <div class="grid gap-5 sm:grid-cols-2">
                <?php foreach (['nama_lengkap' => 'Nama lengkap', 'jabatan' => 'Jabatan', 'informasi' => 'Informasi', 'urutan' => 'Urutan', 'status_data' => 'Status', 'created_at' => 'Dibuat', 'updated_at' => 'Diperbarui'] as $column => $label): ?>
                    <div><dt class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400"><?php echo $label; ?></dt><dd class="mt-1 whitespace-pre-line break-words text-sm text-slate-800"><?php echo admin_module_escape((string) ($record[$column] ?? '-')); ?></dd></div>
                <?php endforeach; ?>
            </div>
            <div class="mt-6 flex flex-col gap-3 border-t pt-5 sm:flex-row">
                <a href="perangkat.php?form=edit&amp;id=<?php echo (int) $record['id']; ?>" class="rounded-xl bg-amber-500 px-4 py-3 text-center text-xs font-extrabold">Edit</a>
                <form method="POST" action="perangkat_action.php" onsubmit="return confirm('Data hanya diarsipkan, tidak dihapus permanen. Lanjutkan?');">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>"><input type="hidden" name="id" value="<?php echo (int) $record['id']; ?>"><input type="hidden" name="action" value="<?php echo $record['status_data'] === 'aktif' ? 'archive' : 'restore'; ?>">
                    <button class="w-full rounded-xl border border-slate-300 px-4 py-3 text-xs font-extrabold"><?php echo $record['status_data'] === 'aktif' ? 'Hapus / Arsipkan' : 'Aktifkan kembali'; ?></button>
                </form>
            </div>
        </section>
    <?php endif; ?>
<?php elseif ($tableAvailable): ?>
    <section class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" class="grid gap-3 md:grid-cols-[1fr_12rem_auto]">
            <input name="q" maxlength="120" value="<?php echo admin_module_escape($search); ?>" placeholder="Cari nama atau jabatan..." class="rounded-xl border border-slate-300 px-4 py-3 text-sm">
            <select name="status" class="rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm"><option value="aktif" <?php echo $status === 'aktif' ? 'selected' : ''; ?>>Aktif</option><option value="arsip" <?php echo $status === 'arsip' ? 'selected' : ''; ?>>Arsip</option><option value="semua" <?php echo $status === 'semua' ? 'selected' : ''; ?>>Semua</option></select>
            <button class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-extrabold text-white">Cari / Filter</button>
        </form>
    </section>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <?php if ($rows === []): ?><div class="px-5 py-12 text-center"><p class="font-extrabold text-slate-800">Belum ada data perangkat pada filter ini.</p><p class="mt-2 text-sm text-slate-500">Tambahkan perangkat atau ubah filter pencarian.</p></div><?php else: ?>
            <div class="overflow-x-auto"><table class="w-full min-w-[40rem] text-left text-sm"><thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-4">Foto</th><th class="px-5 py-4">Nama</th><th class="px-5 py-4">Jabatan</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100">
            <?php foreach ($rows as $row): ?><tr><td class="px-5 py-3"><?php if (!empty($row['foto'])): ?><img src="perangkat_image.php?id=<?php echo (int) $row['id']; ?>" alt="" class="h-12 w-12 rounded-lg border border-slate-200 object-cover"><?php else: ?><span class="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100 text-xs text-slate-400">—</span><?php endif; ?></td><td class="px-5 py-4 font-bold"><?php echo admin_module_escape((string) $row['nama_lengkap']); ?></td><td class="px-5 py-4"><?php echo admin_module_escape((string) $row['jabatan']); ?></td><td class="px-5 py-4"><?php echo admin_module_escape((string) $row['status_data']); ?></td><td class="px-5 py-4 text-right"><a class="font-bold text-amber-700" href="perangkat.php?id=<?php echo (int) $row['id']; ?>">Detail</a></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <div class="flex flex-col gap-3 border-t px-5 py-4 text-xs text-slate-500 sm:flex-row sm:justify-between"><p><?php echo count($rows); ?> dari <?php echo $totalRows; ?> · Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?></p><?php if ($totalPages > 1): ?><nav aria-label="Pagination" class="flex gap-2"><?php $params = ['q' => $search, 'status' => $status]; if ($page > 1): ?><a class="rounded-lg border px-3 py-2" href="perangkat.php?<?php echo admin_module_escape(http_build_query($params + ['page' => $page - 1])); ?>">Sebelumnya</a><?php endif; ?><?php if ($page < $totalPages): ?><a class="rounded-lg border px-3 py-2" href="perangkat.php?<?php echo admin_module_escape(http_build_query($params + ['page' => $page + 1])); ?>">Berikutnya</a><?php endif; ?></nav><?php endif; ?></div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php admin_render_footer(); ?>
