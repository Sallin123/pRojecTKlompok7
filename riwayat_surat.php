<?php
require_once __DIR__ . '/masyarakat_auth.php';
require_once __DIR__ . '/koneksi.php';

function riwayat_surat_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

masyarakat_require_authentication();

$account = null;
$errorMessage = '';
$detail = null;
$rows = [];
$totalRows = 0;
$pageSize = 10;
$page = 1;

try {
    $account = masyarakat_current_account($koneksi);
    if ($account === null) {
        header('Location: masyarakat_login.php');
        exit;
    }

    $rawPage = $_GET['page'] ?? '1';
    $validatedPage = is_string($rawPage) && ctype_digit($rawPage)
        ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    if ($validatedPage === false) {
        throw new InvalidArgumentException('Nomor halaman tidak valid.');
    }
    $page = $validatedPage;

    $rawDetailId = $_GET['id'] ?? '';
    $detailId = null;
    if ($rawDetailId !== '') {
        if (!is_string($rawDetailId) || !ctype_digit($rawDetailId) || (int) $rawDetailId < 1) {
            throw new InvalidArgumentException('ID permohonan tidak valid.');
        }
        $detailId = (int) $rawDetailId;
    }

    if ($detailId !== null) {
        $statement = $koneksi->prepare(
            'SELECT id, tanggal, jenis_surat, nama, keterangan, status FROM surat WHERE id = ? AND akun_masyarakat_id = ? LIMIT 1'
        );
        if (!$statement) {
            throw new RuntimeException('Request detail query could not be prepared.');
        }
        $statement->bind_param('ii', $detailId, $account['id']);
        $statement->execute();
        $result = $statement->get_result();
        $detail = $result ? $result->fetch_assoc() : null;
        $statement->close();
        if ($detail === null) {
            http_response_code(404);
            $errorMessage = 'Permohonan tidak ditemukan.';
        }
    } else {
        $statement = $koneksi->prepare(
            'SELECT COUNT(*) FROM surat WHERE akun_masyarakat_id = ?'
        );
        if (!$statement) {
            throw new RuntimeException('Request count query could not be prepared.');
        }
        $statement->bind_param('i', $account['id']);
        $statement->execute();
        $statement->bind_result($totalRows);
        $statement->fetch();
        $statement->close();

        $totalRows = (int) $totalRows;
        $totalPages = max(1, (int) ceil($totalRows / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;

        $statement = $koneksi->prepare(
            'SELECT id, tanggal, jenis_surat, status FROM surat WHERE akun_masyarakat_id = ? ORDER BY tanggal DESC, id DESC LIMIT ? OFFSET ?'
        );
        if (!$statement) {
            throw new RuntimeException('Request history query could not be prepared.');
        }
        $statement->bind_param('iii', $account['id'], $pageSize, $offset);
        $statement->execute();
        $result = $statement->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $statement->close();
    }
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('Community surat history query failed: ' . $exception->getMessage());
    $errorMessage = 'Riwayat permohonan tidak dapat dimuat saat ini.';
}

$statusClasses = [
    'Pending' => 'border-amber-200 bg-amber-100 text-amber-800',
    'Diproses' => 'border-blue-200 bg-blue-100 text-blue-800',
    'Selesai' => 'border-emerald-200 bg-emerald-100 text-emerald-800',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Surat - Desa Pemayahan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="index.php" class="font-extrabold tracking-tight">DESA <span class="text-amber-600">PEMAYAHAN</span></a>
            <div class="flex items-center gap-2">
                <a href="surat.php" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Permohonan Surat</a>
                <form method="POST" action="masyarakat_logout.php">
                    <input type="hidden" name="csrf_token" value="<?php echo riwayat_surat_escape(masyarakat_csrf_token()); ?>">
                    <button type="submit" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Keluar</button>
                </form>
            </div>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <div class="mb-6">
            <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Layanan Masyarakat</p>
            <h1 class="mt-1 text-2xl font-black text-slate-950">Riwayat Permohonan Surat</h1>
            <p class="mt-1 text-sm text-slate-500">Riwayat ini hanya menampilkan permohonan yang terhubung dengan akun Anda.</p>
        </div>
        <?php if ($errorMessage !== ''): ?>
            <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800"><?php echo riwayat_surat_escape($errorMessage); ?></div>
        <?php endif; ?>

        <?php if ($detail !== null): ?>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="mb-5 flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-5">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Nomor permohonan</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">#<?php echo (int) $detail['id']; ?></h2>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-xs font-extrabold <?php echo $statusClasses[$detail['status'] ?? ''] ?? 'border-slate-200 bg-slate-100 text-slate-700'; ?>"><?php echo riwayat_surat_escape((string) ($detail['status'] ?? 'Status tidak tersedia')); ?></span>
                </div>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Tanggal</dt><dd class="mt-1 text-sm text-slate-800"><?php echo riwayat_surat_escape((string) ($detail['tanggal'] ?? 'Belum tersedia')); ?></dd></div>
                    <div><dt class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Jenis surat</dt><dd class="mt-1 text-sm text-slate-800"><?php echo riwayat_surat_escape((string) $detail['jenis_surat']); ?></dd></div>
                    <div class="sm:col-span-2"><dt class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Nama pemohon</dt><dd class="mt-1 text-sm text-slate-800"><?php echo riwayat_surat_escape((string) $detail['nama']); ?></dd></div>
                    <div class="sm:col-span-2"><dt class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Keperluan</dt><dd class="mt-1 whitespace-pre-wrap break-words text-sm leading-6 text-slate-800"><?php echo riwayat_surat_escape((string) $detail['keterangan']); ?></dd></div>
                </dl>
                <a href="riwayat_surat.php" class="mt-7 inline-flex rounded-xl border border-slate-200 px-4 py-3 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Kembali ke riwayat</a>
            </section>
        <?php elseif ($errorMessage === ''): ?>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <?php if ($rows === []): ?>
                    <div class="px-5 py-12 text-center">
                        <p class="font-extrabold text-slate-800">Belum ada permohonan yang tertaut ke akun ini.</p>
                        <a href="surat.php" class="mt-4 inline-flex rounded-xl bg-amber-500 px-4 py-3 text-xs font-extrabold text-slate-950 hover:bg-amber-400">Ajukan surat</a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[38rem] text-left text-sm">
                            <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                                <tr><th class="px-5 py-4">Nomor / Tanggal</th><th class="px-5 py-4">Jenis surat</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Detail</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td class="px-5 py-4"><strong>#<?php echo (int) $row['id']; ?></strong><span class="mt-1 block text-xs text-slate-500"><?php echo riwayat_surat_escape((string) ($row['tanggal'] ?? 'Tanggal tidak tersedia')); ?></span></td>
                                        <td class="px-5 py-4 font-semibold text-slate-800"><?php echo riwayat_surat_escape((string) $row['jenis_surat']); ?></td>
                                        <td class="px-5 py-4"><span class="rounded-full border px-2.5 py-1 text-[10px] font-extrabold <?php echo $statusClasses[$row['status'] ?? ''] ?? 'border-slate-200 bg-slate-100 text-slate-700'; ?>"><?php echo riwayat_surat_escape((string) ($row['status'] ?? 'Tidak diatur')); ?></span></td>
                                        <td class="px-5 py-4"><a href="riwayat_surat.php?id=<?php echo (int) $row['id']; ?>" class="font-extrabold text-blue-800 hover:text-amber-700">Lihat</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php $totalPages = max(1, (int) ceil($totalRows / $pageSize)); ?>
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Navigasi halaman" class="flex items-center justify-between border-t border-slate-100 px-5 py-4 text-xs">
                            <?php if ($page > 1): ?><a href="?page=<?php echo $page - 1; ?>" class="rounded-lg border px-3 py-2 font-bold hover:bg-slate-50">Sebelumnya</a><?php else: ?><span></span><?php endif; ?>
                            <span class="font-bold text-slate-600">Halaman <?php echo $page; ?> / <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page + 1; ?>" class="rounded-lg border px-3 py-2 font-bold hover:bg-slate-50">Berikutnya</a><?php else: ?><span></span><?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
