<?php

function admin_request_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function admin_request_bind(mysqli_stmt $statement, string $types, array &$values): bool
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

function admin_request_execute(mysqli $connection, string $query, string $types, array $values): mysqli_stmt
{
    $statement = $connection->prepare($query);
    if (!$statement || !admin_request_bind($statement, $types, $values) || !$statement->execute()) {
        if ($statement instanceof mysqli_stmt) {
            $statement->close();
        }
        throw new RuntimeException('Gagal menjalankan query pengelolaan permohonan.');
    }

    return $statement;
}

function admin_request_statuses(): array
{
    return ['Pending', 'Diproses', 'Selesai'];
}

function admin_request_status_class(?string $status): string
{
    return [
        'Pending' => 'bg-amber-100 text-amber-800 border-amber-200',
        'Diproses' => 'bg-blue-100 text-blue-800 border-blue-200',
        'Selesai' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
    ][$status ?? ''] ?? 'bg-slate-100 text-slate-700 border-slate-200';
}

function admin_request_run(array $config, mysqli $connection): void
{
    $table = $config['table'];
    $pageTitle = $config['title'];
    $statuses = admin_request_statuses();
    $errorMessage = '';
    $successMessage = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawId = $_POST['id'] ?? '';
        $newStatus = $_POST['status'] ?? '';
        $submittedToken = $_POST['csrf_token'] ?? '';

        if (!is_string($submittedToken) || !hash_equals(admin_csrf_token(), $submittedToken)) {
            $errorMessage = 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.';
        } elseif (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
            $errorMessage = 'ID permohonan tidak valid.';
        } elseif (!is_string($newStatus) || !in_array($newStatus, $statuses, true)) {
            $errorMessage = 'Status yang dipilih tidak valid.';
        } else {
            try {
                $statement = admin_request_execute(
                    $connection,
                    "UPDATE `$table` SET status = ? WHERE id = ?",
                    'si',
                    [$newStatus, (int) $rawId]
                );
                $affectedRows = $statement->affected_rows;
                $statement->close();

                if ($affectedRows === 0) {
                    $check = admin_request_execute($connection, "SELECT id FROM `$table` WHERE id = ?", 'i', [(int) $rawId]);
                    $exists = $check->fetch();
                    $check->close();

                    if (!$exists) {
                        $errorMessage = 'Data permohonan tidak ditemukan.';
                    }
                }

                if ($errorMessage === '') {
                    $redirect = basename($_SERVER['PHP_SELF']) . '?id=' . (int) $rawId . '&updated=1';
                    header('Location: ' . $redirect);
                    exit;
                }
            } catch (Throwable $exception) {
                error_log('Admin ' . $table . ' status update failed: ' . $exception->getMessage());
                $errorMessage = 'Status tidak dapat diperbarui saat ini. Silakan coba lagi.';
            }
        }
    }

    if (isset($_GET['updated']) && $_GET['updated'] === '1') {
        $successMessage = 'Status permohonan berhasil diperbarui.';
    }

    $rawSearch = $_GET['q'] ?? '';
    $search = is_string($rawSearch) ? trim($rawSearch) : '';
    if (mb_strlen($search, 'UTF-8') > 120) {
        $search = mb_substr($search, 0, 120, 'UTF-8');
        $errorMessage = 'Pencarian dipotong menjadi maksimal 120 karakter.';
    }
    $rawStatus = $_GET['status'] ?? '';
    $filterStatus = is_string($rawStatus) ? $rawStatus : '';
    if ($filterStatus !== '' && !in_array($filterStatus, $statuses, true)) {
        $filterStatus = '';
        $errorMessage = 'Filter status tidak valid; filter status dihapus.';
    }

    $rawDetailId = $_GET['id'] ?? '';
    $detailId = is_string($rawDetailId) && ctype_digit($rawDetailId) && (int) $rawDetailId > 0
        ? (int) $rawDetailId
        : null;
    $detail = null;
    $rows = [];
    $totalRows = 0;
    $pageSize = 10;
    $rawPage = $_GET['page'] ?? '1';
    $validatedPage = is_string($rawPage) && ctype_digit($rawPage)
        ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    $currentPage = $validatedPage !== false ? $validatedPage : 1;
    if (isset($_GET['page']) && $validatedPage === false) {
        $errorMessage = 'Nomor halaman tidak valid; halaman pertama ditampilkan.';
    }

    try {
        if ($detailId !== null) {
            $columns = implode(', ', array_map(static fn($column) => "`$column`", $config['detail_columns']));
            $statement = admin_request_execute(
                $connection,
                "SELECT $columns FROM `$table` WHERE id = ?",
                'i',
                [$detailId]
            );
            $result = $statement->get_result();
            $detail = $result ? $result->fetch_assoc() : null;
            $statement->close();

            if ($detail === null) {
                http_response_code(404);
            }
        } else {
            $conditions = [];
            $types = '';
            $values = [];

            if ($search !== '') {
                $searchConditions = [];
                foreach ($config['search_columns'] as $column) {
                    $searchConditions[] = "`$column` LIKE ?";
                    $types .= 's';
                    $values[] = '%' . $search . '%';
                }
                $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
            }

            if ($filterStatus !== '') {
                $conditions[] = 'status = ?';
                $types .= 's';
                $values[] = $filterStatus;
            }

            $whereSql = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
            $countStatement = admin_request_execute(
                $connection,
                "SELECT COUNT(*) FROM `$table`$whereSql",
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
            $listColumns = implode(', ', array_map(static fn($column) => "`$column`", $config['list_columns']));
            $listTypes = $types . 'ii';
            $listValues = array_merge($values, [$pageSize, $offset]);
            $listStatement = admin_request_execute(
                $connection,
                "SELECT $listColumns FROM `$table`$whereSql ORDER BY tanggal DESC, id DESC LIMIT ? OFFSET ?",
                $listTypes,
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
        error_log('Admin ' . $table . ' query failed: ' . $exception->getMessage());
        $errorMessage = 'Data tidak dapat dimuat saat ini. Silakan coba lagi nanti.';
        $rows = [];
        $detail = null;
    }

    admin_render_header($pageTitle, $_SESSION['admin_username']);
    ?>
    <div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Layanan Warga</p>
            <h2 class="mt-1 text-2xl font-black text-slate-950"><?php echo admin_request_escape($pageTitle); ?></h2>
            <p class="mt-1 text-sm text-slate-500">Kelola status permohonan tanpa mengubah isi laporan.</p>
        </div>
        <?php if ($detailId !== null): ?>
            <a href="<?php echo admin_request_escape(basename($_SERVER['PHP_SELF'])); ?>" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Kembali ke daftar</a>
        <?php endif; ?>
    </div>

    <?php if ($successMessage !== ''): ?>
        <div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800"><?php echo admin_request_escape($successMessage); ?></div>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800"><?php echo admin_request_escape($errorMessage); ?></div>
    <?php endif; ?>

    <?php if ($detailId !== null): ?>
        <?php if ($detail === null): ?>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600">Data permohonan tidak ditemukan.</section>
        <?php else: ?>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-5">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Nomor permohonan</p>
                        <h3 class="mt-1 text-xl font-black text-slate-950">#<?php echo (int) $detail['id']; ?></h3>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-xs font-extrabold <?php echo admin_request_status_class($detail['status'] ?? null); ?>">
                        <?php echo admin_request_escape((string) ($detail['status'] ?? 'Status tidak diatur')); ?>
                    </span>
                </div>

                <dl class="grid gap-4 sm:grid-cols-2">
                    <?php foreach ($config['detail_labels'] as $column => $label): ?>
                        <?php if ($column === 'foto' && empty($detail[$column])) { continue; } ?>
                        <div class="<?php echo in_array($column, ['isi', 'keterangan', 'foto'], true) ? 'sm:col-span-2' : ''; ?>">
                            <dt class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400"><?php echo admin_request_escape($label); ?></dt>
                            <dd class="mt-1 break-words text-sm leading-6 text-slate-800">
                                <?php if ($column === 'foto' && !empty($detail[$column])): ?>
                                    <?php $photoName = basename((string) $detail[$column]); ?>
                                    <?php if (is_file(__DIR__ . '/../uploads/' . $photoName)): ?>
                                        <?php echo admin_request_escape($photoName); ?>
                                    <?php else: ?>
                                        Lampiran tidak tersedia.
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php echo nl2br(admin_request_escape((string) ($detail[$column] ?? '-'))); ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>

                <form method="POST" action="<?php echo admin_request_escape(basename($_SERVER['PHP_SELF']) . '?id=' . (int) $detail['id']); ?>" class="mt-8 flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:items-end">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_request_escape(admin_csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?php echo (int) $detail['id']; ?>">
                    <div class="w-full sm:max-w-xs">
                        <label for="status" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Ubah status</label>
                        <select id="status" name="status" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo admin_request_escape($status); ?>" <?php echo ($detail['status'] ?? '') === $status ? 'selected' : ''; ?>><?php echo admin_request_escape($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="rounded-xl bg-amber-500 px-5 py-3 text-sm font-extrabold text-slate-950 transition hover:bg-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-500/30">Simpan Status</button>
                </form>
            </section>
        <?php endif; ?>
    <?php else: ?>
        <section class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <form method="GET" action="<?php echo admin_request_escape(basename($_SERVER['PHP_SELF'])); ?>" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px_auto]">
                <div>
                    <label for="q" class="sr-only">Cari permohonan</label>
                    <input id="q" name="q" type="search" maxlength="100" value="<?php echo admin_request_escape($search); ?>" placeholder="<?php echo admin_request_escape($config['search_hint']); ?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15">
                </div>
                <div>
                    <label for="filter-status" class="sr-only">Filter status</label>
                    <select id="filter-status" name="status" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <option value="">Semua status</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo admin_request_escape($status); ?>" <?php echo $filterStatus === $status ? 'selected' : ''; ?>><?php echo admin_request_escape($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-extrabold text-white transition hover:bg-blue-950">Cari / Filter</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <?php if (!$rows): ?>
                <div class="px-5 py-12 text-center">
                    <p class="text-base font-extrabold text-slate-800"><?php echo $totalRows === 0 ? 'Belum ada data permohonan.' : 'Tidak ada data pada halaman ini.'; ?></p>
                    <p class="mt-2 text-sm text-slate-500">Coba ubah kata kunci atau filter status.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left">
                        <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-5 py-4">No. / Tanggal</th>
                                <?php foreach ($config['list_labels'] as $column => $label): ?>
                                    <th class="px-5 py-4"><?php echo admin_request_escape($label); ?></th>
                                <?php endforeach; ?>
                                <th class="px-5 py-4">Status</th>
                                <th class="px-5 py-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php foreach ($rows as $row): ?>
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-5 py-4">
                                        <p class="font-extrabold text-slate-900">#<?php echo (int) $row['id']; ?></p>
                                        <p class="mt-1 text-xs text-slate-500"><?php echo admin_request_escape($row['tanggal'] ? date('d/m/Y H:i', strtotime($row['tanggal'])) : 'Tanggal tidak tersedia'); ?></p>
                                    </td>
                                    <?php foreach ($config['list_labels'] as $column => $label): ?>
                                        <td class="max-w-xs px-5 py-4 text-slate-700">
                                            <?php echo admin_request_escape((string) ($row[$column] ?? '-')); ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full border px-2.5 py-1 text-[10px] font-extrabold <?php echo admin_request_status_class($row['status'] ?? null); ?>">
                                            <?php echo admin_request_escape((string) ($row['status'] ?? 'Status tidak diatur')); ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <a href="<?php echo admin_request_escape(basename($_SERVER['PHP_SELF']) . '?id=' . (int) $row['id']); ?>" class="font-extrabold text-blue-800 hover:text-amber-700">Detail</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                $totalPages = max(1, (int) ceil($totalRows / $pageSize));
                $queryParameters = [];
                if ($search !== '') { $queryParameters['q'] = $search; }
                if ($filterStatus !== '') { $queryParameters['status'] = $filterStatus; }
                ?>
                <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                    <p>Menampilkan <?php echo count($rows); ?> dari <?php echo number_format($totalRows, 0, ',', '.'); ?> data</p>
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Navigasi halaman" class="flex items-center gap-2">
                            <?php if ($currentPage > 1): ?>
                                <?php $queryParameters['page'] = $currentPage - 1; ?>
                                <a href="?<?php echo admin_request_escape(http_build_query($queryParameters)); ?>" class="rounded-lg border px-3 py-2 font-bold hover:bg-slate-50">Sebelumnya</a>
                            <?php endif; ?>
                            <span class="px-2 font-bold">Halaman <?php echo $currentPage; ?> / <?php echo $totalPages; ?></span>
                            <?php if ($currentPage < $totalPages): ?>
                                <?php $queryParameters['page'] = $currentPage + 1; ?>
                                <a href="?<?php echo admin_request_escape(http_build_query($queryParameters)); ?>" class="rounded-lg border px-3 py-2 font-bold hover:bg-slate-50">Berikutnya</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php
    admin_render_footer();
}
