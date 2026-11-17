<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/layout.php';

function admin_load_status_summary(mysqli $connection, string $table): ?array
{
    if (!in_array($table, ['aduan', 'surat'], true)) {
        throw new InvalidArgumentException('Tabel ringkasan dashboard tidak valid.');
    }

    $summary = [
        'total' => 0,
        'Pending' => 0,
        'Diproses' => 0,
        'Selesai' => 0,
        'Tidak diatur' => 0,
    ];

    $statement = null;
    try {
        $statement = $connection->prepare("SELECT status, COUNT(*) AS jumlah FROM `$table` GROUP BY status");
        if (!$statement) {
            error_log('Dashboard summary query could not be prepared for ' . $table . '.');
            return null;
        }

        if (!$statement->execute() || !$statement->bind_result($status, $jumlah)) {
            error_log('Dashboard summary query could not be executed for ' . $table . '.');
            return null;
        }

        while (($fetchResult = $statement->fetch()) === true) {
            $jumlah = (int) $jumlah;
            $summary['total'] += $jumlah;

            if ($status === null) {
                $summary['Tidak diatur'] += $jumlah;
            } elseif (array_key_exists($status, $summary)) {
                $summary[$status] += $jumlah;
            } else {
                $summary['Tidak diatur'] += $jumlah;
            }
        }

        if ($fetchResult === false) {
            error_log('Dashboard summary results could not be read for ' . $table . '.');
            return null;
        }

        return $summary;
    } catch (mysqli_sql_exception $exception) {
        error_log('Dashboard summary query failed for ' . $table . ': ' . $exception->getMessage());
        return null;
    } finally {
        if ($statement instanceof mysqli_stmt) {
            $statement->close();
        }
    }
}

function admin_dashboard_table_exists(mysqli $connection, string $table): bool
{
    if (!in_array($table, [
        'penduduk', 'aduan', 'surat', 'bansos_program', 'bansos_periode',
        'bansos_penerima', 'perangkat_desa', 'posyandu_agenda', 'mobil_siaga_pengajuan',
    ], true)) {
        throw new InvalidArgumentException('Tabel dashboard tidak valid.');
    }

    try {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND TABLE_TYPE = \'BASE TABLE\''
        );
        if (!$statement) {
            error_log('Dashboard table availability query could not be prepared.');
            return false;
        }
        $statement->bind_param('s', $table);
        if (!$statement->execute() || !$statement->bind_result($count) || !$statement->fetch()) {
            error_log('Dashboard table availability query could not be completed.');
            $statement->close();
            return false;
        }
        $statement->close();
        return (int) $count === 1;
    } catch (mysqli_sql_exception $exception) {
        error_log('Dashboard table availability query failed: ' . $exception->getMessage());
        return false;
    }
}

function admin_dashboard_column_exists(mysqli $connection, string $table, string $column): bool
{
    $allowedColumns = [
        'penduduk' => ['created_at', 'status_data'],
        'aduan' => ['tanggal', 'status'],
        'surat' => ['tanggal', 'status'],
        'bansos_penerima' => ['created_at', 'status_penerima', 'periode_id'],
        'bansos_periode' => ['id', 'program_id', 'nama_periode', 'created_at'],
        'bansos_program' => ['id', 'nama_program'],
        'posyandu_agenda' => ['created_at', 'jenis', 'status_publikasi'],
        'mobil_siaga_pengajuan' => ['created_at', 'status'],
    ];
    if (!isset($allowedColumns[$table]) || !in_array($column, $allowedColumns[$table], true)) {
        throw new InvalidArgumentException('Kolom dashboard tidak valid.');
    }

    try {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        if (!$statement) {
            error_log('Dashboard column availability query could not be prepared.');
            return false;
        }
        $statement->bind_param('ss', $table, $column);
        if (!$statement->execute() || !$statement->bind_result($count) || !$statement->fetch()) {
            error_log('Dashboard column availability query could not be completed.');
            $statement->close();
            return false;
        }
        $statement->close();
        return (int) $count === 1;
    } catch (mysqli_sql_exception $exception) {
        error_log('Dashboard column availability query failed: ' . $exception->getMessage());
        return false;
    }
}

function admin_dashboard_posyandu_summary(mysqli $connection): ?array
{
    if (!admin_dashboard_table_exists($connection, 'posyandu_agenda')
        || !admin_dashboard_column_exists($connection, 'posyandu_agenda', 'jenis')
        || !admin_dashboard_column_exists($connection, 'posyandu_agenda', 'status_publikasi')) {
        return null;
    }

    $statement = null;
    try {
        $statement = $connection->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN jenis = 'jadwal' THEN 1 ELSE 0 END), 0) AS jadwal,
                COALESCE(SUM(CASE WHEN jenis IN ('kegiatan', 'imunisasi') THEN 1 ELSE 0 END), 0) AS kegiatan
            FROM posyandu_agenda
            WHERE status_publikasi = 'publikasi'"
        );
        if (!$statement || !$statement->execute() || !$statement->bind_result($jadwal, $kegiatan) || !$statement->fetch()) {
            error_log('Dashboard Posyandu summary query could not be completed.');
            return null;
        }

        return ['jadwal' => (int) $jadwal, 'kegiatan' => (int) $kegiatan];
    } catch (mysqli_sql_exception $exception) {
        error_log('Dashboard Posyandu summary query failed: ' . $exception->getMessage());
        return null;
    } finally {
        if ($statement instanceof mysqli_stmt) {
            $statement->close();
        }
    }
}

function admin_dashboard_mobil_siaga_summary(mysqli $connection): ?array
{
    if (!admin_dashboard_table_exists($connection, 'mobil_siaga_pengajuan')
        || !admin_dashboard_column_exists($connection, 'mobil_siaga_pengajuan', 'status')) {
        return null;
    }

    $statement = null;
    $schemaStatement = null;
    try {
        $schemaStatement = $connection->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        if (!$schemaStatement) {
            error_log('Dashboard Mobil Siaga status schema query could not be prepared.');
            return null;
        }
        $table = 'mobil_siaga_pengajuan';
        $column = 'status';
        $schemaStatement->bind_param('ss', $table, $column);
        if (!$schemaStatement->execute() || !$schemaStatement->bind_result($columnType) || !$schemaStatement->fetch()) {
            error_log('Dashboard Mobil Siaga status schema could not be read.');
            return null;
        }
        $schemaStatement->close();
        $schemaStatement = null;

        $statuses = [];
        if (is_string($columnType) && str_starts_with($columnType, 'enum(')
            && preg_match_all("~'([^']*)'~", $columnType, $matches) > 0) {
            $statuses = array_fill_keys($matches[1], 0);
        }

        $statement = $connection->prepare(
            'SELECT status, COUNT(*) AS jumlah
            FROM mobil_siaga_pengajuan
            GROUP BY status
            ORDER BY status'
        );
        if (!$statement || !$statement->execute() || !$statement->bind_result($status, $jumlah)) {
            error_log('Dashboard Mobil Siaga summary query could not be completed.');
            return null;
        }

        $total = 0;
        while (($fetchResult = $statement->fetch()) === true) {
            $label = $status === null || $status === '' ? 'Tidak diatur' : (string) $status;
            $statuses[$label] = (int) $jumlah;
            $total += (int) $jumlah;
        }
        if ($fetchResult === false) {
            error_log('Dashboard Mobil Siaga summary results could not be read.');
            return null;
        }

        return ['total' => $total, 'statuses' => $statuses];
    } catch (mysqli_sql_exception $exception) {
        error_log('Dashboard Mobil Siaga summary query failed: ' . $exception->getMessage());
        return null;
    } finally {
        if ($statement instanceof mysqli_stmt) {
            $statement->close();
        }
        if ($schemaStatement instanceof mysqli_stmt) {
            $schemaStatement->close();
        }
    }
}

function admin_dashboard_count(mysqli $connection, string $query): ?int
{
    $statement = null;
    try {
        $statement = $connection->prepare($query);
        if (!$statement || !$statement->execute() || !$statement->bind_result($count) || !$statement->fetch()) {
            error_log('Dashboard aggregate query could not be completed.');
            return null;
        }
        return (int) $count;
    } catch (mysqli_sql_exception $exception) {
        error_log('Dashboard aggregate query failed: ' . $exception->getMessage());
        return null;
    } finally {
        if ($statement instanceof mysqli_stmt) {
            $statement->close();
        }
    }
}

function admin_dashboard_recent_from_table(
    mysqli $connection,
    string $table,
    string $timeColumn,
    string $category,
    string $description,
    string $statusColumn
): array {
    $requiredColumns = [
        'penduduk' => ['created_at', 'status_data'],
        'aduan' => ['tanggal', 'status'],
        'surat' => ['tanggal', 'status'],
        'mobil_siaga_pengajuan' => ['created_at', 'status'],
    ];
    if (!isset($requiredColumns[$table])
        || !in_array($timeColumn, $requiredColumns[$table], true)
        || !in_array($statusColumn, $requiredColumns[$table], true)
        || !admin_dashboard_table_exists($connection, $table)) {
        return [];
    }
    foreach (array_unique([$timeColumn, $statusColumn]) as $column) {
        if (!admin_dashboard_column_exists($connection, $table, $column)) {
            return [];
        }
    }

    $statement = null;
    try {
        $statement = $connection->prepare(
            "SELECT ? AS kategori, `$timeColumn` AS waktu, ? AS keterangan,
                CONVERT(`$statusColumn` USING utf8mb4) AS status
            FROM `$table` ORDER BY `$timeColumn` DESC LIMIT 5"
        );
        if (!$statement) {
            error_log('Dashboard recent activity query could not be prepared for ' . $table . '.');
            return [];
        }
        $statement->bind_param('ss', $category, $description);
        if (!$statement->execute()) {
            error_log('Dashboard recent activity query failed for ' . $table . '.');
            return [];
        }
        $result = $statement->get_result();
        if (!$result) {
            error_log('Dashboard recent activity results could not be read for ' . $table . '.');
            return [];
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    } catch (mysqli_sql_exception $exception) {
        error_log('Dashboard recent activity query failed for ' . $table . ': ' . $exception->getMessage());
        return [];
    } finally {
        if ($statement instanceof mysqli_stmt) {
            $statement->close();
        }
    }
}

function admin_dashboard_recent_posyandu(mysqli $connection): array
{
    $table = 'posyandu_agenda';
    foreach (['created_at', 'jenis', 'status_publikasi'] as $column) {
        if (!admin_dashboard_column_exists($connection, $table, $column)) {
            return [];
        }
    }
    if (!admin_dashboard_table_exists($connection, $table)) {
        return [];
    }

    try {
        $result = $connection->query(
            "SELECT 'Posyandu' AS kategori, created_at AS waktu,
                CASE WHEN jenis = 'jadwal' THEN 'Jadwal Posyandu' ELSE 'Informasi kegiatan Posyandu' END AS keterangan,
                status_publikasi AS status
            FROM posyandu_agenda ORDER BY created_at DESC LIMIT 5"
        );
        if (!$result) {
            error_log('Dashboard recent Posyandu activity query failed.');
            return [];
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
        return $rows;
    } catch (mysqli_sql_exception $exception) {
        error_log('Dashboard recent Posyandu activity query failed: ' . $exception->getMessage());
        return [];
    }
}

function admin_dashboard_recent_activity(mysqli $connection, bool $includeBansos): ?array
{
    $rows = array_merge(
        admin_dashboard_recent_from_table($connection, 'penduduk', 'created_at', 'Penduduk', 'Data penduduk', 'status_data'),
        admin_dashboard_recent_from_table($connection, 'aduan', 'tanggal', 'Pengaduan', 'Laporan masuk', 'status'),
        admin_dashboard_recent_from_table($connection, 'surat', 'tanggal', 'Surat Online', 'Permohonan surat masuk', 'status'),
        admin_dashboard_recent_posyandu($connection),
        admin_dashboard_recent_from_table(
            $connection,
            'mobil_siaga_pengajuan',
            'created_at',
            'Mobil Siaga',
            'Pengajuan Mobil Siaga',
            'status'
        )
    );

    if ($includeBansos) {
        $bansosColumnsAvailable = admin_dashboard_column_exists($connection, 'bansos_penerima', 'created_at')
            && admin_dashboard_column_exists($connection, 'bansos_penerima', 'status_penerima')
            && admin_dashboard_column_exists($connection, 'bansos_periode', 'id')
            && admin_dashboard_column_exists($connection, 'bansos_periode', 'program_id')
            && admin_dashboard_column_exists($connection, 'bansos_periode', 'nama_periode')
            && admin_dashboard_column_exists($connection, 'bansos_periode', 'created_at')
            && admin_dashboard_column_exists($connection, 'bansos_program', 'id')
            && admin_dashboard_column_exists($connection, 'bansos_program', 'nama_program');
        if ($bansosColumnsAvailable) {
            try {
                $result = $connection->query(
                    "SELECT 'Bansos' AS kategori, bp.created_at AS waktu,
                        CONCAT(bpr.nama_program, ' · ', bp.nama_periode) AS keterangan,
                        bpn.status_penerima AS status
                    FROM bansos_penerima bpn
                    JOIN bansos_periode bp ON bp.id = bpn.periode_id
                    JOIN bansos_program bpr ON bpr.id = bp.program_id
                    ORDER BY bp.created_at DESC LIMIT 5"
                );
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $rows[] = $row;
                    }
                    $result->free();
                } else {
                    error_log('Dashboard recent Bansos activity query failed.');
                }
            } catch (mysqli_sql_exception $exception) {
                error_log('Dashboard recent Bansos activity query failed: ' . $exception->getMessage());
            }
        }
    }

    usort($rows, static function (array $left, array $right): int {
        return strcmp((string) ($right['waktu'] ?? ''), (string) ($left['waktu'] ?? ''));
    });

    return array_slice($rows, 0, 5);
}

$aduanSummary = admin_load_status_summary($koneksi, 'aduan');
$suratSummary = admin_load_status_summary($koneksi, 'surat');
$pendudukCount = admin_dashboard_table_exists($koneksi, 'penduduk')
    ? admin_dashboard_count($koneksi, "SELECT COUNT(*) FROM penduduk WHERE status_data = 'aktif'")
    : null;
$bansosTablesReady = admin_dashboard_table_exists($koneksi, 'bansos_program')
    && admin_dashboard_table_exists($koneksi, 'bansos_periode')
    && admin_dashboard_table_exists($koneksi, 'bansos_penerima');
$bansosCount = $bansosTablesReady
    ? admin_dashboard_count($koneksi, "SELECT COUNT(*) FROM bansos_penerima WHERE status_penerima <> 'dibatalkan'")
    : null;
$posyanduSummary = admin_dashboard_posyandu_summary($koneksi);
$mobilSiagaSummary = admin_dashboard_mobil_siaga_summary($koneksi);
$recentActivity = admin_dashboard_recent_activity($koneksi, $bansosTablesReady);

admin_render_header('Dashboard', $_SESSION['admin_username']);
?>
<section class="mb-8 rounded-3xl bg-gradient-to-r from-slate-950 via-blue-950 to-slate-900 p-6 text-white shadow-xl sm:p-8">
    <span class="inline-flex rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.18em] text-amber-300">Portal Admin</span>
    <h2 class="mt-4 text-2xl font-black tracking-tight sm:text-3xl">Selamat datang, <?php echo htmlspecialchars($_SESSION['admin_username'], ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Ringkasan pengajuan aduan dan surat dari database Desa Pemayahan.</p>
</section>

<section aria-labelledby="admin-stats-heading" class="mb-8">
    <div class="mb-4">
        <h2 id="admin-stats-heading" class="text-lg font-black text-slate-950">Statistik administrasi</h2>
        <p class="mt-1 text-xs text-slate-500">Angka ditarik langsung dari tabel yang tersedia. Skema yang belum dibuat ditandai, bukan dihitung sebagai nol.</p>
    </div>
    <?php
    $adminStats = [
        ['label' => 'Penduduk aktif', 'value' => $pendudukCount, 'hint' => 'Tabel penduduk belum tersedia atau ringkasan gagal dimuat.', 'href' => 'penduduk.php', 'accent' => 'amber'],
        ['label' => 'Penerima bansos', 'value' => $bansosCount, 'hint' => 'Skema bansos belum tersedia atau ringkasan gagal dimuat.', 'href' => 'bansos.php?view=program', 'accent' => 'emerald'],
        ['label' => 'Total pengaduan', 'value' => $aduanSummary['total'] ?? null, 'hint' => 'Ringkasan gagal dimuat.', 'href' => 'aduan.php', 'accent' => 'blue'],
        ['label' => 'Total permohonan surat', 'value' => $suratSummary['total'] ?? null, 'hint' => 'Ringkasan gagal dimuat.', 'href' => 'surat.php', 'accent' => 'slate'],
        ['label' => 'Jadwal Posyandu dipublikasikan', 'value' => $posyanduSummary['jadwal'] ?? null, 'hint' => 'Tabel atau kolom agenda Posyandu belum tersedia atau ringkasan gagal dimuat.', 'href' => 'posyandu.php#jadwal', 'accent' => 'amber'],
        ['label' => 'Informasi kegiatan dipublikasikan', 'value' => $posyanduSummary['kegiatan'] ?? null, 'hint' => 'Tabel atau kolom agenda Posyandu belum tersedia atau ringkasan gagal dimuat.', 'href' => 'posyandu.php#kegiatan', 'accent' => 'blue'],
        ['label' => 'Pengajuan Mobil Siaga', 'value' => $mobilSiagaSummary['total'] ?? null, 'hint' => 'Tabel atau kolom status pengajuan Mobil Siaga belum tersedia atau ringkasan gagal dimuat.', 'href' => 'mobil_siaga.php#pengajuan', 'accent' => 'slate'],
    ];
    ?>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($adminStats as $stat): ?>
            <a href="<?php echo htmlspecialchars($stat['href'], ENT_QUOTES, 'UTF-8'); ?>" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <p class="text-xs font-bold text-slate-500"><?php echo htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="mt-3 text-3xl font-black text-slate-950"><?php echo $stat['value'] === null ? 'Belum tersedia' : number_format($stat['value'], 0, ',', '.'); ?></p>
                <?php if ($stat['value'] === null): ?><p class="mt-1 text-[11px] text-amber-700"><?php echo htmlspecialchars($stat['hint'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section aria-labelledby="module-summary-heading" class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-4">
        <h2 id="module-summary-heading" class="text-base font-black text-slate-950">Rincian pengajuan Mobil Siaga</h2>
        <p class="mt-1 text-xs text-slate-500">Jumlah dikelompokkan menurut status yang tercatat pada pengajuan; jadwal dan penggunaan kendaraan tidak dihitung sebagai pengajuan.</p>
    </div>
    <?php if ($mobilSiagaSummary === null): ?>
        <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">Data pengajuan Mobil Siaga belum tersedia.</p>
    <?php elseif ($mobilSiagaSummary['statuses'] === []): ?>
        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">Belum ada pengajuan Mobil Siaga.</p>
    <?php else: ?>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <?php foreach ($mobilSiagaSummary['statuses'] as $status => $count): ?>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="break-words text-xs font-bold text-slate-500"><?php echo htmlspecialchars((string) $status, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="mt-2 text-2xl font-black text-slate-950"><?php echo number_format((int) $count, 0, ',', '.'); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section aria-labelledby="recent-heading" class="mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-5 py-4">
        <h2 id="recent-heading" class="text-base font-black text-slate-950">Ringkasan data terbaru</h2>
        <p class="mt-1 text-xs text-slate-500">Daftar tidak menampilkan nama penduduk, NIK, atau nomor kontak.</p>
    </div>
    <?php if ($recentActivity === null): ?>
        <p role="alert" class="p-5 text-sm text-red-700">Aktivitas terbaru tidak dapat dimuat saat ini.</p>
    <?php elseif ($recentActivity === []): ?>
        <p class="p-8 text-center text-sm text-slate-500">Belum ada data terbaru.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[38rem] text-left text-sm">
                <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                    <tr><th class="px-5 py-3">Modul</th><th class="px-5 py-3">Ringkasan</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Waktu</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($recentActivity as $activity): ?>
                        <tr>
                            <td class="px-5 py-3 font-bold"><?php echo htmlspecialchars((string) $activity['kategori'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-5 py-3"><?php echo htmlspecialchars((string) ($activity['keterangan'] ?? 'Data diperbarui'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-5 py-3"><?php echo htmlspecialchars((string) ($activity['status'] ?? 'Tidak diatur'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-5 py-3 whitespace-nowrap text-xs text-slate-500"><?php echo htmlspecialchars((string) ($activity['waktu'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section aria-labelledby="summary-heading" class="mb-8">
    <div class="mb-4">
        <h2 id="summary-heading" class="text-lg font-black text-slate-950">Ringkasan layanan</h2>
        <p class="mt-1 text-xs text-slate-500">Hanya jumlah pengajuan yang ditampilkan; data pribadi warga tidak ditampilkan di ringkasan.</p>
    </div>
    <?php
    $summaries = [
        ['title' => 'Pengaduan Online', 'data' => $aduanSummary, 'icon' => '📢'],
        ['title' => 'Permohonan Surat', 'data' => $suratSummary, 'icon' => '📄'],
    ];
    foreach ($summaries as $service):
        $summary = $service['data'];
    ?>
        <div class="mb-6 last:mb-0">
            <h3 class="mb-3 text-sm font-extrabold text-slate-700"><?php echo htmlspecialchars($service['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <?php if ($summary === null): ?>
                <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm font-semibold text-red-800">
                    Ringkasan tidak dapat dimuat saat ini. Silakan coba lagi nanti atau hubungi administrator.
                </div>
            <?php else: ?>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <?php
                    $metrics = [
                        ['label' => 'Total', 'key' => 'total', 'accent' => 'amber'],
                        ['label' => 'Pending', 'key' => 'Pending', 'accent' => 'amber'],
                        ['label' => 'Diproses', 'key' => 'Diproses', 'accent' => 'blue'],
                        ['label' => 'Selesai', 'key' => 'Selesai', 'accent' => 'emerald'],
                        ['label' => 'Status tidak diatur', 'key' => 'Tidak diatur', 'accent' => 'slate'],
                    ];
                    foreach ($metrics as $metric):
                        $value = $summary[$metric['key']];
                        $accentClass = [
                            'amber' => 'border-amber-200 text-amber-700 bg-amber-50',
                            'blue' => 'border-blue-200 text-blue-700 bg-blue-50',
                            'emerald' => 'border-emerald-200 text-emerald-700 bg-emerald-50',
                            'slate' => 'border-slate-200 text-slate-600 bg-slate-50',
                        ][$metric['accent']];
                    ?>
                        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-bold text-slate-500"><?php echo htmlspecialchars($metric['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <span class="rounded-lg px-2 py-1 text-[10px] font-extrabold <?php echo $accentClass; ?>"><?php echo $service['icon']; ?></span>
                            </div>
                            <p class="mt-3 text-2xl font-black text-slate-950"><?php echo number_format($value, 0, ',', '.'); ?></p>
                            <?php if ($metric['key'] === 'total' && $value === 0): ?>
                                <p class="mt-1 text-[11px] text-slate-500">Belum ada data pengajuan.</p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <h2 class="text-base font-black text-slate-950">Modul dashboard</h2>
    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Pengaduan, surat, penduduk, dan Potensi Desa terhubung ke database. Bansos dan perangkat menunggu tabel tambahan dari berkas SQL; profil desa dan berita masih menggunakan sumber konten yang sudah tersedia.</p>
</section>
<?php
admin_render_footer();
