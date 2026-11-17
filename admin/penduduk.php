<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/layout.php';

function penduduk_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function penduduk_bind(mysqli_stmt $statement, string $types, array &$values): bool
{
    if ($types === '') {
        return true;
    }

    $references = [];
    foreach ($values as &$value) {
        $references[] = &$value;
    }
    unset($value);

    return call_user_func_array([$statement, 'bind_param'], array_merge([$types], $references));
}

function penduduk_execute(mysqli $connection, string $query, string $types, array $values): mysqli_stmt
{
    $statement = $connection->prepare($query);
    if (!$statement || !penduduk_bind($statement, $types, $values) || !$statement->execute()) {
        if ($statement instanceof mysqli_stmt) {
            $statement->close();
        }

        throw new RuntimeException('Query Data Penduduk gagal dijalankan.');
    }

    return $statement;
}

$flash = $_SESSION['penduduk_flash'] ?? null;
unset($_SESSION['penduduk_flash']);
$oldInput = $_SESSION['penduduk_old_input'] ?? [];
unset($_SESSION['penduduk_old_input']);

$errorMessage = '';
$tableAvailable = false;
$tableProbe = null;
try {
    $tableProbe = $koneksi->query(
        'SELECT id, kode_internal, nama_lengkap, nik, tanggal_lahir, alamat, status_data, created_at, updated_at FROM penduduk LIMIT 0'
    );
    if ($tableProbe === false) {
        throw new RuntimeException('Pemeriksaan tabel penduduk gagal.');
    }
    $tableProbe->free();
    $tableAvailable = true;
} catch (mysqli_sql_exception $exception) {
    if ((int) $exception->getCode() === 1146) {
        $errorMessage = 'Tabel penduduk belum tersedia. Modul ini belum siap digunakan. Siapkan tabel sesuai rancangan yang telah disetujui sebelum mengelola data.';
    } else {
        error_log('Admin penduduk table check failed: ' . $exception->getMessage());
        $errorMessage = 'Data penduduk tidak dapat dimuat saat ini. Silakan coba lagi nanti.';
    }
} catch (Throwable $exception) {
    error_log('Admin penduduk table check failed: ' . $exception->getMessage());
    $errorMessage = 'Data penduduk tidak dapat dimuat saat ini. Silakan coba lagi nanti.';
}

$formMode = '';
$detailId = null;
$record = null;
$rows = [];
$totalRows = 0;
$currentPage = 1;
$totalPages = 1;
$pageSize = 10;
$search = '';
$statusFilter = '';
$pageError = '';
$searchValue = $_GET['q'] ?? '';
$statusValue = $_GET['status'] ?? '';
$pageValue = $_GET['page'] ?? '1';

if (is_string($searchValue)) {
    $search = trim($searchValue);
    if (preg_match('/\A.{0,120}\z/us', $search) !== 1) {
        $search = '';
        $pageError = 'Pencarian maksimal 120 karakter.';
    }
}

if (is_string($statusValue)) {
    if (in_array($statusValue, ['', 'aktif', 'arsip'], true)) {
        $statusFilter = $statusValue;
    } else {
        $pageError = 'Filter status tidak valid.';
    }
} elseif (isset($_GET['status'])) {
    $pageError = 'Filter status tidak valid.';
}

if (is_string($pageValue) && ctype_digit($pageValue)) {
    $validatedPage = filter_var($pageValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($validatedPage !== false) {
        $currentPage = $validatedPage;
    } else {
        $pageError = 'Nomor halaman tidak valid.';
    }
} elseif (isset($_GET['page'])) {
    $pageError = 'Nomor halaman tidak valid.';
}

$rawFormMode = $_GET['form'] ?? '';
if (is_string($rawFormMode) && in_array($rawFormMode, ['tambah', 'edit'], true)) {
    $formMode = $rawFormMode;
}

$rawDetailId = $_GET['id'] ?? '';
if (is_string($rawDetailId) && ctype_digit($rawDetailId) && (int) $rawDetailId > 0) {
    $detailId = (int) $rawDetailId;
} elseif (isset($_GET['id'])) {
    $formMode = '';
    $pageError = 'ID data penduduk tidak valid.';
}

if ($formMode === 'edit' && $detailId === null) {
    $formMode = '';
    $pageError = 'ID data penduduk tidak valid untuk diedit.';
}

if ($tableAvailable && $formMode === 'edit' && $detailId !== null) {
    try {
        $statement = penduduk_execute(
            $koneksi,
            'SELECT id, kode_internal, nama_lengkap, nik, tanggal_lahir, alamat, status_data, created_at, updated_at FROM penduduk WHERE id = ?',
            'i',
            [$detailId]
        );
        $result = $statement->get_result();
        $record = $result ? $result->fetch_assoc() : null;
        $statement->close();
        if ($record === null) {
            $formMode = '';
            $pageError = 'Data penduduk tidak ditemukan.';
        }
    } catch (Throwable $exception) {
        error_log('Admin penduduk edit load failed: ' . $exception->getMessage());
        $formMode = '';
        $pageError = 'Data penduduk tidak dapat dimuat saat ini.';
    }
} elseif ($tableAvailable && $detailId !== null && $formMode === '') {
    try {
        $statement = penduduk_execute(
            $koneksi,
            'SELECT id, kode_internal, nama_lengkap, nik, tanggal_lahir, alamat, status_data, created_at, updated_at FROM penduduk WHERE id = ?',
            'i',
            [$detailId]
        );
        $result = $statement->get_result();
        $record = $result ? $result->fetch_assoc() : null;
        $statement->close();
        if ($record === null) {
            $pageError = 'Data penduduk tidak ditemukan.';
        }
    } catch (Throwable $exception) {
        error_log('Admin penduduk detail load failed: ' . $exception->getMessage());
        $pageError = 'Data penduduk tidak dapat dimuat saat ini.';
    }
}

if ($tableAvailable && $formMode === '' && $detailId === null) {
    try {
        $conditions = [];
        $types = '';
        $values = [];
        if ($search !== '') {
            $conditions[] = 'nama_lengkap LIKE ?';
            $types .= 's';
            $values[] = '%' . $search . '%';
        }
        if ($statusFilter !== '') {
            $conditions[] = 'status_data = ?';
            $types .= 's';
            $values[] = $statusFilter;
        } else {
            $conditions[] = "status_data = 'aktif'";
        }

        $whereSql = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $countStatement = penduduk_execute(
            $koneksi,
            'SELECT COUNT(*) FROM penduduk' . $whereSql,
            $types,
            $values
        );
        $countStatement->bind_result($totalRows);
        $countStatement->fetch();
        $countStatement->close();
        $totalRows = (int) $totalRows;

        $totalPages = max(1, (int) ceil($totalRows / $pageSize));
        $currentPage = min($currentPage, $totalPages);
        $offset = ($currentPage - 1) * $pageSize;

        $listValues = array_merge($values, [$pageSize, $offset]);
        $listStatement = penduduk_execute(
            $koneksi,
            'SELECT id, kode_internal, nama_lengkap, status_data FROM penduduk' . $whereSql . ' ORDER BY nama_lengkap ASC, id ASC LIMIT ? OFFSET ?',
            $types . 'ii',
            $listValues
        );
        $result = $listStatement->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $listStatement->close();
    } catch (Throwable $exception) {
        error_log('Admin penduduk list query failed: ' . $exception->getMessage());
        $errorMessage = 'Data penduduk tidak dapat dimuat saat ini. Silakan coba lagi nanti.';
        $rows = [];
    }
}

$pageTitle = $formMode === 'tambah'
    ? 'Tambah Data Penduduk'
    : ($formMode === 'edit' ? 'Edit Data Penduduk' : ($detailId !== null ? 'Detail Penduduk' : 'Data Penduduk'));
admin_render_header($pageTitle, $_SESSION['admin_username']);
?>
<div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
        <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Pengelolaan Data</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950"><?php echo penduduk_escape($pageTitle); ?></h2>
        <p class="mt-1 text-sm text-slate-500">Data minimum tanpa NIK, nomor HP, atau tanggal lahir.</p>
    </div>
    <?php if ($tableAvailable && $formMode === '' && $detailId === null): ?>
        <a href="penduduk.php?form=tambah" class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-4 py-3 text-xs font-extrabold text-slate-950 shadow-sm hover:bg-amber-400">Tambah Penduduk</a>
    <?php elseif ($tableAvailable && ($formMode !== '' || $detailId !== null)): ?>
        <a href="penduduk.php" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Kembali ke daftar</a>
    <?php endif; ?>
</div>

<?php if (is_array($flash) && isset($flash['message']) && is_string($flash['message'])): ?>
    <div role="<?php echo ($flash['type'] ?? '') === 'success' ? 'status' : 'alert'; ?>" class="mb-5 rounded-xl border px-4 py-3 text-sm font-semibold <?php echo ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>">
        <?php echo penduduk_escape($flash['message']); ?>
    </div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div role="alert" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900"><?php echo penduduk_escape($errorMessage); ?></div>
<?php endif; ?>
<?php if ($pageError !== ''): ?>
    <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800"><?php echo penduduk_escape($pageError); ?></div>
<?php endif; ?>

<?php if ($tableAvailable && ($formMode === 'tambah' || $formMode === 'edit')): ?>
    <?php
    $formName = isset($oldInput['nama_lengkap']) && is_string($oldInput['nama_lengkap'])
        ? $oldInput['nama_lengkap']
        : (string) ($record['nama_lengkap'] ?? '');
    $formAddress = isset($oldInput['alamat']) && is_string($oldInput['alamat'])
        ? $oldInput['alamat']
        : (string) ($record['alamat'] ?? '');
    $formNik = isset($oldInput['nik']) && is_string($oldInput['nik'])
        ? $oldInput['nik']
        : (string) ($record['nik'] ?? '');
    $formTanggalLahir = isset($oldInput['tanggal_lahir']) && is_string($oldInput['tanggal_lahir'])
        ? $oldInput['tanggal_lahir']
        : (string) ($record['tanggal_lahir'] ?? '');
    ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <form method="POST" action="penduduk_action.php" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo penduduk_escape(admin_csrf_token()); ?>">
            <input type="hidden" name="action" value="<?php echo $formMode === 'tambah' ? 'create' : 'update'; ?>">
            <?php if ($formMode === 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo (int) $record['id']; ?>">
            <?php endif; ?>
            <div>
                <label for="nama_lengkap" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Nama lengkap <span class="text-red-600">*</span></label>
                <input id="nama_lengkap" name="nama_lengkap" type="text" maxlength="120" required value="<?php echo penduduk_escape($formName); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="nik" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">NIK <span class="font-medium normal-case text-slate-400">(opsional, 16 digit)</span></label>
                    <input id="nik" name="nik" type="text" inputmode="numeric" autocomplete="off" pattern="[0-9]{16}" maxlength="16" value="<?php echo penduduk_escape($formNik); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>
                <div>
                    <label for="tanggal_lahir" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Tanggal lahir <span class="font-medium normal-case text-slate-400">(opsional)</span></label>
                    <input id="tanggal_lahir" name="tanggal_lahir" type="date" value="<?php echo penduduk_escape($formTanggalLahir); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>
            </div>
            <div>
                <label for="alamat" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Alamat <span class="font-medium normal-case text-slate-400">(opsional, maksimal 1.000 karakter)</span></label>
                <textarea id="alamat" name="alamat" rows="4" maxlength="1000" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15"><?php echo penduduk_escape($formAddress); ?></textarea>
            </div>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="penduduk.php" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-xs font-extrabold text-slate-700 hover:bg-slate-50">Batal</a>
                <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-extrabold text-white hover:bg-slate-800">Simpan</button>
            </div>
        </form>
    </section>
<?php elseif ($tableAvailable && $detailId !== null): ?>
    <?php if ($record === null): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600">Data penduduk tidak ditemukan.</section>
    <?php else: ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-5">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Kode internal</p>
                    <p class="mt-1 break-all font-mono text-sm font-bold text-slate-800"><?php echo penduduk_escape((string) $record['kode_internal']); ?></p>
                </div>
                <span class="rounded-full border px-3 py-1 text-xs font-extrabold <?php echo $record['status_data'] === 'aktif' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-100 text-slate-700'; ?>">
                    <?php echo penduduk_escape((string) $record['status_data']); ?>
                </span>
            </div>
            <dl class="grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Nama lengkap</dt>
                    <dd class="mt-1 break-words text-sm font-semibold text-slate-800"><?php echo penduduk_escape((string) $record['nama_lengkap']); ?></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">NIK</dt>
                    <dd class="mt-1 text-sm text-slate-700"><?php echo $record['nik'] !== null && $record['nik'] !== '' ? penduduk_escape((string) $record['nik']) : 'Belum diisi'; ?></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Tanggal lahir</dt>
                    <dd class="mt-1 text-sm text-slate-700"><?php echo $record['tanggal_lahir'] !== null && $record['tanggal_lahir'] !== '' ? penduduk_escape((string) $record['tanggal_lahir']) : 'Belum diisi'; ?></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Alamat</dt>
                    <dd class="mt-1 break-words text-sm leading-6 text-slate-800"><?php echo nl2br(penduduk_escape((string) ($record['alamat'] ?? 'Tidak diisi'))); ?></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Dibuat</dt>
                    <dd class="mt-1 text-sm text-slate-700"><?php echo penduduk_escape((string) $record['created_at']); ?></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Terakhir diperbarui</dt>
                    <dd class="mt-1 text-sm text-slate-700"><?php echo penduduk_escape((string) $record['updated_at']); ?></dd>
                </div>
            </dl>
            <div class="mt-7 flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:items-center">
                <a href="penduduk.php?form=edit&amp;id=<?php echo (int) $record['id']; ?>" class="rounded-xl bg-amber-500 px-4 py-3 text-center text-xs font-extrabold text-slate-950 hover:bg-amber-400">Edit data</a>
                <form method="POST" action="penduduk_action.php" onsubmit="return confirm('Data hanya diarsipkan dan tidak dihapus permanen. Lanjutkan?');">
                    <input type="hidden" name="csrf_token" value="<?php echo penduduk_escape(admin_csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?php echo (int) $record['id']; ?>">
                    <input type="hidden" name="action" value="<?php echo $record['status_data'] === 'aktif' ? 'archive' : 'restore'; ?>">
                    <button type="submit" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-xs font-extrabold text-slate-700 hover:bg-slate-50 sm:w-auto">
                        <?php echo $record['status_data'] === 'aktif' ? 'Hapus / Arsipkan' : 'Aktifkan kembali'; ?>
                    </button>
                </form>
            </div>
        </section>
    <?php endif; ?>
<?php elseif ($tableAvailable): ?>
    <section class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="penduduk.php" class="grid gap-3 md:grid-cols-[1fr_12rem_auto]">
            <div>
                <label for="q" class="sr-only">Cari nama penduduk</label>
                <input id="q" name="q" type="search" maxlength="120" value="<?php echo penduduk_escape($search); ?>" placeholder="Cari nama penduduk..." class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
            </div>
            <div>
                <label for="status" class="sr-only">Filter status</label>
                <select id="status" name="status" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>Aktif (default)</option>
                    <option value="aktif" <?php echo $statusFilter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="arsip" <?php echo $statusFilter === 'arsip' ? 'selected' : ''; ?>>Arsip</option>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-extrabold text-white hover:bg-slate-800">Cari / Filter</button>
        </form>
        <?php if ($search !== '' || $statusFilter !== ''): ?>
            <a href="penduduk.php" class="mt-3 inline-block text-xs font-bold text-amber-700 hover:text-amber-900">Hapus pencarian dan filter</a>
        <?php endif; ?>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <?php if ($rows === []): ?>
            <div class="px-5 py-12 text-center">
                <p class="text-sm font-extrabold text-slate-800"><?php echo $totalRows === 0 ? 'Belum ada data pada filter ini.' : 'Data tidak dapat ditampilkan.'; ?></p>
                <p class="mt-2 text-xs text-slate-500">Tambahkan data penduduk atau ubah pencarian dan filter.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[42rem] text-left text-sm">
                    <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-4">Kode internal</th>
                            <th scope="col" class="px-5 py-4">Nama lengkap</th>
                            <th scope="col" class="px-5 py-4">Status</th>
                            <th scope="col" class="px-5 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $row): ?>
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-5 py-4 font-mono text-xs text-slate-500"><?php echo penduduk_escape((string) $row['kode_internal']); ?></td>
                                <td class="px-5 py-4 font-bold text-slate-800"><?php echo penduduk_escape((string) $row['nama_lengkap']); ?></td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full border px-2.5 py-1 text-[10px] font-extrabold <?php echo $row['status_data'] === 'aktif' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-100 text-slate-700'; ?>"><?php echo penduduk_escape((string) $row['status_data']); ?></span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="penduduk.php?id=<?php echo (int) $row['id']; ?>" class="font-extrabold text-amber-700 hover:text-amber-900">Detail</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                <p>Menampilkan <?php echo number_format(count($rows), 0, ',', '.'); ?> dari <?php echo number_format($totalRows, 0, ',', '.'); ?> data · Halaman <?php echo (int) $currentPage; ?> dari <?php echo (int) $totalPages; ?></p>
                <?php if ($totalPages > 1): ?>
                    <?php
                    $paginationParameters = [];
                    if ($search !== '') {
                        $paginationParameters['q'] = $search;
                    }
                    if ($statusFilter !== '') {
                        $paginationParameters['status'] = $statusFilter;
                    }
                    ?>
                    <nav aria-label="Pagination data penduduk" class="flex items-center gap-2">
                        <?php if ($currentPage > 1): ?>
                            <?php $paginationParameters['page'] = $currentPage - 1; ?>
                            <a href="penduduk.php?<?php echo penduduk_escape(http_build_query($paginationParameters)); ?>" class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-700 hover:bg-slate-50">Sebelumnya</a>
                        <?php endif; ?>
                        <?php if ($currentPage < $totalPages): ?>
                            <?php $paginationParameters['page'] = $currentPage + 1; ?>
                            <a href="penduduk.php?<?php echo penduduk_escape(http_build_query($paginationParameters)); ?>" class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-700 hover:bg-slate-50">Berikutnya</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php
admin_render_footer();
