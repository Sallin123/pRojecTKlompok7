<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/module_helpers.php';

$flash = admin_module_take_flash('bansos_flash');
$oldInput = admin_module_take_old_input('bansos_old_input');
$viewValue = $_GET['view'] ?? 'program';
$view = is_string($viewValue) && in_array($viewValue, ['program', 'periode', 'penerima'], true) ? $viewValue : 'program';
$formValue = $_GET['form'] ?? '';
$formMode = is_string($formValue) && in_array($formValue, ['tambah', 'edit'], true) ? $formValue : '';
$errorMessage = '';
$missingTables = [];
$ready = false;
$rows = [];
$record = null;
$program = null;
$period = null;
$programs = [];
$residents = [];
$search = '';
$statusFilter = '';
$page = 1;
$pageSize = 10;
$totalRows = 0;
$totalPages = 1;
$detailId = null;

$contextValue = $view === 'periode' ? ($_GET['program_id'] ?? '') : ($view === 'penerima' ? ($_GET['periode_id'] ?? '') : '');
$contextId = null;
if ($view !== 'program') {
    if (is_string($contextValue) && ctype_digit($contextValue) && (int) $contextValue > 0) {
        $contextId = (int) $contextValue;
    } else {
        $errorMessage = $view === 'periode' ? 'Pilih program bantuan terlebih dahulu.' : 'Pilih periode bantuan terlebih dahulu.';
    }
}

$rawId = $_GET['id'] ?? '';
if (is_string($rawId) && ctype_digit($rawId) && (int) $rawId > 0) {
    $detailId = (int) $rawId;
} elseif (isset($_GET['id'])) {
    $errorMessage = 'ID data bansos tidak valid.';
}
if ($formMode === 'edit' && $detailId === null) {
    $formMode = '';
    $errorMessage = 'ID data wajib tersedia untuk diedit.';
}

$rawSearch = $_GET['q'] ?? '';
if (is_string($rawSearch)) {
    $search = trim($rawSearch);
    if (mb_strlen($search, 'UTF-8') > 120) {
        $search = mb_substr($search, 0, 120, 'UTF-8');
        $errorMessage = 'Pencarian dipotong menjadi maksimal 120 karakter.';
    }
}
$rawStatus = $_GET['status'] ?? '';
$validStatuses = $view === 'program' ? ['aktif', 'arsip', 'semua'] : ($view === 'periode' ? ['aktif', 'arsip', 'semua'] : ['terdaftar', 'disalurkan', 'dibatalkan', 'semua']);
if (is_string($rawStatus) && in_array($rawStatus, $validStatuses, true)) {
    $statusFilter = $rawStatus;
} elseif (isset($_GET['status'])) {
    $errorMessage = 'Filter status tidak valid.';
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

try {
    $missingTables = admin_module_missing_tables($koneksi, ['bansos_program', 'bansos_periode', 'bansos_penerima', 'penduduk']);
    $ready = $missingTables === [];
} catch (Throwable $exception) {
    error_log('Admin bansos schema check failed: ' . $exception->getMessage());
    $errorMessage = 'Struktur data bansos tidak dapat diperiksa saat ini.';
}

if ($ready) {
    try {
        $programList = admin_module_execute(
            $koneksi,
            'SELECT id, nama_program FROM bansos_program WHERE status_program = \'aktif\' ORDER BY nama_program, id'
        );
        $result = $programList->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $programs[] = $row;
            }
        }
        $programList->close();

        if ($view === 'periode' && $contextId !== null) {
            $statement = admin_module_execute(
                $koneksi,
                'SELECT id, nama_program, status_program FROM bansos_program WHERE id = ?',
                'i',
                [$contextId]
            );
            $result = $statement->get_result();
            $program = $result ? $result->fetch_assoc() : null;
            $statement->close();
            if ($program === null) {
                $errorMessage = 'Program bantuan tidak ditemukan.';
            }
        } elseif ($view === 'penerima' && $contextId !== null) {
            $statement = admin_module_execute(
                $koneksi,
                'SELECT bp.id, bp.nama_periode, bp.status_periode, bpr.nama_program FROM bansos_periode bp JOIN bansos_program bpr ON bpr.id = bp.program_id WHERE bp.id = ?',
                'i',
                [$contextId]
            );
            $result = $statement->get_result();
            $period = $result ? $result->fetch_assoc() : null;
            $statement->close();
            if ($period === null) {
                $errorMessage = 'Periode bantuan tidak ditemukan.';
            }
        }

        if ($formMode === 'edit' && $detailId !== null) {
            if ($view === 'program') {
                $query = 'SELECT id, nama_program, deskripsi, status_program FROM bansos_program WHERE id = ?';
                $types = 'i';
                $values = [$detailId];
            } elseif ($view === 'periode' && $contextId !== null) {
                $query = 'SELECT id, program_id, nama_periode, tanggal_mulai, tanggal_selesai, status_periode FROM bansos_periode WHERE id = ? AND program_id = ?';
                $types = 'ii';
                $values = [$detailId, $contextId];
            } elseif ($view === 'penerima' && $contextId !== null) {
                $query = 'SELECT bp.id, bp.periode_id, p.nama_lengkap, bp.penduduk_id, bp.status_penerima, bp.tanggal_penyaluran, bp.catatan FROM bansos_penerima bp JOIN penduduk p ON p.id = bp.penduduk_id WHERE bp.id = ? AND bp.periode_id = ?';
                $types = 'ii';
                $values = [$detailId, $contextId];
            } else {
                $query = '';
                $types = '';
                $values = [];
            }
            if ($query !== '') {
                $statement = admin_module_execute($koneksi, $query, $types, $values);
                $result = $statement->get_result();
                $record = $result ? $result->fetch_assoc() : null;
                $statement->close();
                if ($record === null) {
                    $formMode = '';
                    $errorMessage = 'Data bansos tidak ditemukan.';
                }
            }
        }

        if ($view === 'penerima' && $formMode === 'tambah') {
            $residentQuery = admin_module_execute(
                $koneksi,
                'SELECT id, nama_lengkap FROM penduduk WHERE status_data = \'aktif\' ORDER BY nama_lengkap, id'
            );
            $result = $residentQuery->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $residents[] = $row;
                }
            }
            $residentQuery->close();
        }

        if ($formMode === '' && $contextId !== null || $formMode === '' && $view === 'program') {
            $conditions = [];
            $types = '';
            $values = [];
            if ($view === 'program') {
                $from = ' FROM bansos_program';
                $whereColumn = 'status_program';
                $searchColumns = ['nama_program', 'deskripsi'];
                $orderBy = ' ORDER BY nama_program, id';
            } elseif ($view === 'periode') {
                $from = ' FROM bansos_periode bp JOIN bansos_program bpr ON bpr.id = bp.program_id';
                $conditions[] = 'bp.program_id = ?';
                $types .= 'i';
                $values[] = $contextId;
                $whereColumn = 'bp.status_periode';
                $searchColumns = ['bp.nama_periode', 'bpr.nama_program'];
                $orderBy = ' ORDER BY bp.tanggal_mulai DESC, bp.id DESC';
            } else {
                $from = ' FROM bansos_penerima bpn JOIN penduduk p ON p.id = bpn.penduduk_id JOIN bansos_periode bp ON bp.id = bpn.periode_id JOIN bansos_program bpr ON bpr.id = bp.program_id';
                $conditions[] = 'bpn.periode_id = ?';
                $types .= 'i';
                $values[] = $contextId;
                $whereColumn = 'bpn.status_penerima';
                $searchColumns = ['p.nama_lengkap', 'bpr.nama_program', 'bp.nama_periode'];
                $orderBy = ' ORDER BY bpn.created_at DESC, bpn.id DESC';
            }
            if ($search !== '') {
                $searchConditions = [];
                foreach ($searchColumns as $column) {
                    $searchConditions[] = $column . ' LIKE ?';
                    $types .= 's';
                    $values[] = '%' . $search . '%';
                }
                $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
            }
            if ($statusFilter !== '' && $statusFilter !== 'semua') {
                $conditions[] = $whereColumn . ' = ?';
                $types .= 's';
                $values[] = $statusFilter;
            }
            $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
            $countQuery = 'SELECT COUNT(*)' . $from . $where;
            $countStatement = admin_module_execute($koneksi, $countQuery, $types, $values);
            $countStatement->bind_result($totalRows);
            $countStatement->fetch();
            $countStatement->close();
            $totalRows = (int) $totalRows;
            $totalPages = max(1, (int) ceil($totalRows / $pageSize));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $pageSize;

            if ($view === 'program') {
                $select = 'SELECT id, nama_program, deskripsi, status_program';
            } elseif ($view === 'periode') {
                $select = 'SELECT bp.id, bp.program_id, bp.nama_periode, bp.tanggal_mulai, bp.tanggal_selesai, bp.status_periode, bpr.nama_program';
            } else {
                $select = 'SELECT bpn.id, bpn.periode_id, bpn.status_penerima, bpn.tanggal_penyaluran, bpn.created_at, p.nama_lengkap, bpr.nama_program, bp.nama_periode';
            }
            $listValues = array_merge($values, [$pageSize, $offset]);
            $listStatement = admin_module_execute(
                $koneksi,
                $select . $from . $where . $orderBy . ' LIMIT ? OFFSET ?',
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
        }
    } catch (Throwable $exception) {
        error_log('Admin bansos query failed: ' . $exception->getMessage());
        $errorMessage = 'Data bansos tidak dapat dimuat saat ini.';
        $rows = [];
        $record = null;
    }
}

$titles = ['program' => 'Program Bantuan', 'periode' => 'Periode Bantuan', 'penerima' => 'Penerima Bantuan'];
$pageTitle = $formMode === 'tambah' ? 'Tambah ' . $titles[$view] : ($formMode === 'edit' ? 'Edit ' . $titles[$view] : $titles[$view]);
$contextParameters = $view === 'periode'
    ? ['program_id' => $contextId]
    : ($view === 'penerima' ? ['periode_id' => $contextId] : []);
admin_render_header($pageTitle, $_SESSION['admin_username']);
?>
<div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div><p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Pengelolaan Bantuan Sosial</p><h2 class="mt-1 text-2xl font-black text-slate-950"><?php echo admin_module_escape($pageTitle); ?></h2></div>
    <?php if ($ready && $formMode === ''): ?>
        <a href="bansos.php?<?php echo admin_module_escape(http_build_query(['view' => $view] + $contextParameters + ['form' => 'tambah'])); ?>" class="rounded-xl bg-amber-500 px-4 py-3 text-center text-xs font-extrabold text-slate-950"><?php echo $view === 'penerima' ? 'Tambah Penerima' : ($view === 'periode' ? 'Tambah Periode' : 'Tambah Program'); ?></a>
    <?php endif; ?>
</div>
<nav aria-label="Jenis data bansos" class="mb-5 flex flex-wrap gap-2">
    <a class="rounded-xl px-4 py-2.5 text-xs font-extrabold <?php echo $view === 'program' ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-700'; ?>" href="bansos.php?view=program">Program</a>
    <?php if ($program !== null): ?><a class="rounded-xl px-4 py-2.5 text-xs font-extrabold bg-amber-100 text-slate-900" href="bansos.php?view=periode&amp;program_id=<?php echo (int) $program['id']; ?>">Periode: <?php echo admin_module_escape((string) $program['nama_program']); ?></a><?php endif; ?>
    <?php if ($period !== null): ?><a class="rounded-xl px-4 py-2.5 text-xs font-extrabold bg-amber-100 text-slate-900" href="bansos.php?view=penerima&amp;periode_id=<?php echo (int) $period['id']; ?>">Penerima: <?php echo admin_module_escape((string) $period['nama_periode']); ?></a><?php endif; ?>
</nav>
<?php if (is_array($flash) && isset($flash['message'])): ?><div role="<?php echo ($flash['type'] ?? '') === 'success' ? 'status' : 'alert'; ?>" class="mb-5 rounded-xl border px-4 py-3 text-sm font-semibold <?php echo ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>"><?php echo admin_module_escape((string) $flash['message']); ?></div><?php endif; ?>
<?php if ($errorMessage !== ''): ?><div role="alert" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900"><?php echo admin_module_escape($errorMessage); ?><?php if ($missingTables !== []): ?> Tabel yang belum tersedia: <?php echo admin_module_escape(implode(', ', $missingTables)); ?>.<?php endif; ?></div><?php endif; ?>
<?php if (!$ready): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 text-sm leading-6 text-slate-600">
        <p>Modul belum aktif sampai tabel program, periode, penerima, dan keterkaitan penduduk tersedia.</p>
        <?php if ($missingTables !== []): ?><p class="mt-2">Tabel yang belum tersedia: <strong><?php echo admin_module_escape(implode(', ', $missingTables)); ?></strong>.</p><?php endif; ?>
        <p class="mt-2">SQL usulan berada di <code>database/dashboard_admin_modules.sql</code>; file tersebut belum dijalankan.</p>
    </section>
<?php elseif ($view !== 'program' && $contextId === null): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600"><?php echo admin_module_escape($errorMessage); ?> Kembali ke tab Program lalu pilih data yang tersedia.</section>
<?php elseif ($formMode !== ''): ?>
    <?php
    $actionContext = $view === 'periode'
        ? ['program_id' => $contextId]
        : ($view === 'penerima' ? ['periode_id' => $contextId] : []);
    ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <form method="POST" action="bansos_action.php" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
            <input type="hidden" name="entity" value="<?php echo admin_module_escape($view); ?>">
            <input type="hidden" name="action" value="<?php echo $formMode === 'tambah' ? 'create' : 'update'; ?>">
            <?php foreach ($actionContext as $key => $value): ?><input type="hidden" name="<?php echo admin_module_escape($key); ?>" value="<?php echo (int) $value; ?>"><?php endforeach; ?>
            <?php if ($formMode === 'edit'): ?><input type="hidden" name="id" value="<?php echo (int) $record['id']; ?>"><?php endif; ?>
            <?php if ($view === 'program'): ?>
                <div><label for="nama_program" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Nama program *</label><input id="nama_program" name="nama_program" maxlength="150" required value="<?php echo admin_module_escape((string) ($oldInput['nama_program'] ?? $record['nama_program'] ?? '')); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"></div>
                <div><label for="deskripsi" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Deskripsi</label><textarea id="deskripsi" name="deskripsi" maxlength="5000" rows="4" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><?php echo admin_module_escape((string) ($oldInput['deskripsi'] ?? $record['deskripsi'] ?? '')); ?></textarea></div>
            <?php elseif ($view === 'periode'): ?>
                <?php if ($program !== null): ?><p class="rounded-xl bg-slate-50 p-3 text-sm">Program: <strong><?php echo admin_module_escape((string) $program['nama_program']); ?></strong></p><?php endif; ?>
                <div><label for="nama_periode" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Nama/periode *</label><input id="nama_periode" name="nama_periode" maxlength="100" required value="<?php echo admin_module_escape((string) ($oldInput['nama_periode'] ?? $record['nama_periode'] ?? '')); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><label for="tanggal_mulai" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Tanggal mulai</label><input id="tanggal_mulai" name="tanggal_mulai" type="date" value="<?php echo admin_module_escape((string) ($oldInput['tanggal_mulai'] ?? $record['tanggal_mulai'] ?? '')); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"></div><div><label for="tanggal_selesai" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Tanggal selesai</label><input id="tanggal_selesai" name="tanggal_selesai" type="date" value="<?php echo admin_module_escape((string) ($oldInput['tanggal_selesai'] ?? $record['tanggal_selesai'] ?? '')); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"></div></div>
            <?php elseif ($view === 'penerima'): ?>
                <?php if ($period !== null): ?><p class="rounded-xl bg-slate-50 p-3 text-sm"><?php echo admin_module_escape((string) $period['nama_program']); ?> · <strong><?php echo admin_module_escape((string) $period['nama_periode']); ?></strong></p><?php endif; ?>
                <?php if ($formMode === 'tambah'): ?><div><label for="penduduk_id" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Penduduk aktif *</label><select id="penduduk_id" name="penduduk_id" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm"><option value="">Pilih penduduk</option><?php foreach ($residents as $resident): ?><option value="<?php echo (int) $resident['id']; ?>" <?php echo (string) ($oldInput['penduduk_id'] ?? '') === (string) $resident['id'] ? 'selected' : ''; ?>><?php echo admin_module_escape((string) $resident['nama_lengkap']); ?></option><?php endforeach; ?></select><?php if ($residents === []): ?><p class="mt-2 text-xs text-amber-800">Tidak ada penduduk aktif yang dapat dipilih.</p><?php endif; ?></div>
                <?php else: ?>
                    <p class="rounded-xl bg-slate-50 p-3 text-sm">Penerima: <strong><?php echo admin_module_escape((string) $record['nama_lengkap']); ?></strong></p>
                    <div><label for="status_penerima" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Status penerima *</label><select id="status_penerima" name="status_penerima" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm"><?php foreach (['terdaftar' => 'Terdaftar', 'disalurkan' => 'Disalurkan', 'dibatalkan' => 'Dibatalkan'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($oldInput['status_penerima'] ?? $record['status_penerima'] ?? '') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
                    <div><label for="tanggal_penyaluran" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Tanggal penyaluran (wajib bila status disalurkan)</label><input id="tanggal_penyaluran" name="tanggal_penyaluran" type="date" value="<?php echo admin_module_escape((string) ($oldInput['tanggal_penyaluran'] ?? $record['tanggal_penyaluran'] ?? '')); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"></div>
                    <div><label for="catatan" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Catatan internal</label><textarea id="catatan" name="catatan" maxlength="3000" rows="4" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><?php echo admin_module_escape((string) ($oldInput['catatan'] ?? $record['catatan'] ?? '')); ?></textarea></div>
                <?php endif; ?>
            <?php endif; ?>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><a href="bansos.php?<?php echo admin_module_escape(http_build_query(['view' => $view] + $contextParameters)); ?>" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-xs font-extrabold">Batal</a><button class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-extrabold text-white">Simpan</button></div>
        </form>
    </section>
<?php else: ?>
    <?php if ($view === 'program' && $programs !== []): ?>
        <div class="mb-4 flex flex-wrap gap-2"><?php foreach ($programs as $item): ?><a href="bansos.php?view=periode&amp;program_id=<?php echo (int) $item['id']; ?>" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700"><?php echo admin_module_escape((string) $item['nama_program']); ?> · Periode</a><?php endforeach; ?></div>
    <?php endif; ?>
    <section class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" class="grid gap-3 md:grid-cols-[1fr_12rem_auto]">
            <input type="hidden" name="view" value="<?php echo admin_module_escape($view); ?>">
            <?php foreach ($contextParameters as $key => $value): ?><input type="hidden" name="<?php echo admin_module_escape($key); ?>" value="<?php echo (int) $value; ?>"><?php endforeach; ?>
            <input name="q" maxlength="120" value="<?php echo admin_module_escape($search); ?>" placeholder="<?php echo $view === 'penerima' ? 'Cari nama penerima...' : 'Cari program/periode...'; ?>" class="rounded-xl border border-slate-300 px-4 py-3 text-sm">
            <select name="status" class="rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm"><option value="semua">Semua status</option><?php foreach (array_filter($validStatuses, static fn($value) => $value !== 'semua') as $value): ?><option value="<?php echo admin_module_escape($value); ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>><?php echo admin_module_escape(ucfirst($value)); ?></option><?php endforeach; ?></select>
            <button class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-extrabold text-white">Cari / Filter</button>
        </form>
    </section>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <?php if ($rows === []): ?><div class="px-5 py-12 text-center"><p class="font-extrabold text-slate-800">Belum ada data pada filter ini.</p><p class="mt-2 text-sm text-slate-500">Tambahkan data atau sesuaikan pencarian dan filter.</p></div>
        <?php else: ?>
            <div class="overflow-x-auto"><table class="w-full min-w-[42rem] text-left text-sm"><thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-500"><tr>
                <?php if ($view === 'program'): ?><th class="px-5 py-4">Program</th><th class="px-5 py-4">Status</th>
                <?php elseif ($view === 'periode'): ?><th class="px-5 py-4">Periode</th><th class="px-5 py-4">Tanggal</th><th class="px-5 py-4">Status</th>
                <?php else: ?><th class="px-5 py-4">Penerima</th><th class="px-5 py-4">Program / Periode</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Penyaluran</th><?php endif; ?>
                <th class="px-5 py-4 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100">
                <?php foreach ($rows as $row): ?><tr>
                    <?php if ($view === 'program'): ?><td class="px-5 py-4 font-bold"><?php echo admin_module_escape((string) $row['nama_program']); ?></td><td class="px-5 py-4"><?php echo admin_module_escape((string) $row['status_program']); ?></td>
                    <?php elseif ($view === 'periode'): ?><td class="px-5 py-4 font-bold"><?php echo admin_module_escape((string) $row['nama_periode']); ?><span class="block text-xs font-normal text-slate-500"><?php echo admin_module_escape((string) $row['nama_program']); ?></span></td><td class="px-5 py-4"><?php echo admin_module_escape((string) ($row['tanggal_mulai'] ?? '-')); ?> — <?php echo admin_module_escape((string) ($row['tanggal_selesai'] ?? '-')); ?></td><td class="px-5 py-4"><?php echo admin_module_escape((string) $row['status_periode']); ?></td>
                    <?php else: ?><td class="px-5 py-4 font-bold"><?php echo admin_module_escape((string) $row['nama_lengkap']); ?></td><td class="px-5 py-4"><?php echo admin_module_escape((string) $row['nama_program']); ?><span class="block text-xs text-slate-500"><?php echo admin_module_escape((string) $row['nama_periode']); ?></span></td><td class="px-5 py-4"><?php echo admin_module_escape((string) $row['status_penerima']); ?></td><td class="px-5 py-4"><?php echo admin_module_escape((string) ($row['tanggal_penyaluran'] ?? '-')); ?></td><?php endif; ?>
                    <td class="px-5 py-4 text-right"><div class="flex flex-wrap justify-end gap-2">
                        <?php if ($view === 'program'): ?><a href="bansos.php?view=periode&amp;program_id=<?php echo (int) $row['id']; ?>" class="font-bold text-blue-700">Periode</a><?php elseif ($view === 'periode'): ?><a href="bansos.php?view=penerima&amp;periode_id=<?php echo (int) $row['id']; ?>" class="font-bold text-blue-700">Penerima</a><?php endif; ?>
                        <a href="bansos.php?<?php echo admin_module_escape(http_build_query(['view' => $view] + $contextParameters + ['form' => 'edit', 'id' => (int) $row['id']])); ?>" class="font-bold text-amber-700">Edit</a>
                        <form method="POST" action="bansos_action.php" onsubmit="return confirm('Data hanya diarsipkan/dibatalkan, tidak dihapus permanen. Lanjutkan?');"><input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>"><input type="hidden" name="entity" value="<?php echo admin_module_escape($view); ?>"><input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>"><input type="hidden" name="action" value="<?php echo $view === 'penerima' ? (($row['status_penerima'] ?? '') === 'dibatalkan' ? 'restore' : 'cancel') : ((($row[$view === 'program' ? 'status_program' : 'status_periode'] ?? '') === 'arsip') ? 'restore' : 'archive'); ?>"><?php foreach ($contextParameters as $key => $value): ?><input type="hidden" name="<?php echo admin_module_escape($key); ?>" value="<?php echo (int) $value; ?>"><?php endforeach; ?><button class="font-bold text-slate-600"><?php echo $view === 'penerima' ? (($row['status_penerima'] ?? '') === 'dibatalkan' ? 'Pulihkan' : 'Hapus / Batalkan') : ((($row[$view === 'program' ? 'status_program' : 'status_periode'] ?? '') === 'arsip') ? 'Pulihkan' : 'Hapus / Arsipkan'); ?></button></form>
                    </div></td>
                </tr><?php endforeach; ?>
            </tbody></table></div>
            <div class="flex flex-col gap-3 border-t px-5 py-4 text-xs text-slate-500 sm:flex-row sm:justify-between"><p><?php echo count($rows); ?> dari <?php echo $totalRows; ?> · Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?></p><?php if ($totalPages > 1): ?><nav aria-label="Pagination" class="flex gap-2"><?php $params = ['view' => $view] + $contextParameters + ['q' => $search, 'status' => $statusFilter]; if ($page > 1): ?><a class="rounded-lg border px-3 py-2" href="bansos.php?<?php echo admin_module_escape(http_build_query($params + ['page' => $page - 1])); ?>">Sebelumnya</a><?php endif; ?><?php if ($page < $totalPages): ?><a class="rounded-lg border px-3 py-2" href="bansos.php?<?php echo admin_module_escape(http_build_query($params + ['page' => $page + 1])); ?>">Berikutnya</a><?php endif; ?></nav><?php endif; ?></div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php admin_render_footer(); ?>
