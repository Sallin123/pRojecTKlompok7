<?php
require_once __DIR__ . '/masyarakat_auth.php';
masyarakat_require_authentication();
require_once __DIR__ . '/surat_draft.php';
require_once __DIR__ . '/koneksi.php';

function surat_page_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$account = null;
$accountError = false;
if (masyarakat_is_authenticated()) {
    try {
        $account = masyarakat_current_account($koneksi);
    } catch (Throwable $exception) {
        error_log('Community account status lookup failed on surat page: ' . $exception->getMessage());
        $accountError = true;
    }
}

$draft = surat_draft_get();
$oldInput = surat_form_take_old_input();
$formValues = array_merge($draft['fields'] ?? [], $oldInput);
$isApproved = $account !== null && $account['status'] === 'approved';
$confirmDraft = $draft !== null && $isApproved;
$csrfToken = masyarakat_csrf_token();
$successMessage = !empty($_SESSION['surat_success'])
    ? 'Permohonan surat berhasil dikirim. Statusnya dapat dilihat pada riwayat permohonan.'
    : '';
$formError = is_string($_SESSION['surat_form_error'] ?? null) ? $_SESSION['surat_form_error'] : '';
unset($_SESSION['surat_success'], $_SESSION['surat_form_error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Online - Portal Resmi Desa Digital</title>
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

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 py-12">
        <div class="bg-white rounded-2xl p-6 md:p-10 shadow-sm border border-gray-100">
            <div class="mb-8 text-center">
                <span class="text-amber-500 text-xs font-extrabold uppercase tracking-widest">PELAYANAN ADMINISTRASI</span>
                <h2 class="text-2xl md:text-3xl font-black text-gray-900 mt-1">Permohonan Surat Online</h2>
                <p class="text-gray-500 text-sm mt-2">Isi formulir berikut untuk mengajukan surat keterangan resmi dari Pemerintah Desa.</p>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800"><?php echo surat_page_escape($successMessage); ?> <a href="riwayat_surat.php" class="underline">Lihat riwayat</a></div>
            <?php endif; ?>
            <?php if ($formError !== ''): ?>
                <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800"><?php echo surat_page_escape($formError); ?></div>
            <?php endif; ?>
            <?php if ($accountError): ?>
                <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">Status akun tidak dapat diperiksa saat ini. Permohonan belum dapat dilanjutkan.</div>
            <?php elseif ($account !== null && $account['status'] === 'pending'): ?>
                <div role="status" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">Akun menunggu persetujuan admin. Draft dapat dilanjutkan setelah akun disetujui.</div>
            <?php elseif ($account !== null && $account['status'] === 'rejected'): ?>
                <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">Akun belum disetujui, sehingga permohonan surat tidak dapat dikirim.</div>
            <?php elseif ($account === null): ?>
                <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">Anda dapat mengisi formulir terlebih dahulu. Login atau registrasi diperlukan saat melanjutkan pengajuan.</div>
            <?php endif; ?>
            <?php if ($confirmDraft): ?>
                <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">Periksa kembali draft di bawah ini sebelum mengirim. Draft berlaku selama 24 jam.</div>
            <?php elseif ($draft !== null): ?>
                <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">Draft pengajuan tersimpan sementara. Draft berlaku selama 24 jam.</div>
            <?php endif; ?>

            <form action="proses-surat.php" method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo surat_page_escape($csrfToken); ?>">
                <input type="hidden" name="action" value="<?php echo $confirmDraft ? 'confirm' : 'prepare'; ?>">
                <?php if ($confirmDraft): ?>
                    <input type="hidden" name="submission_token" value="<?php echo surat_page_escape($draft['token']); ?>">
                <?php endif; ?>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Jenis Surat yang Diajukan</label>
                    <select name="jenis_surat" required class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:border-amber-500">
                        <option value="">-- Pilih Jenis Surat --</option>
                        <?php foreach ([
                            'Surat Keterangan Usaha (SKU)',
                            'Surat Keterangan Tidak Mampu (SKTM)',
                            'Surat Keterangan Domisili',
                            'Surat Keterangan Belum Menikah',
                            'Surat Pengantar SKCK',
                        ] as $letterType): ?>
                            <option value="<?php echo surat_page_escape($letterType); ?>" <?php echo ($formValues['jenis_surat'] ?? '') === $letterType ? 'selected' : ''; ?>><?php echo surat_page_escape($letterType); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Nomor Induk Kependudukan (NIK)</label>
                        <input type="text" name="nik" required maxlength="16" value="<?php echo surat_page_escape((string) ($formValues['nik'] ?? '')); ?>" placeholder="16 digit NIK KTP Anda" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Nama Lengkap Pemohon</label>
                        <input type="text" name="nama" required maxlength="100" value="<?php echo surat_page_escape((string) ($formValues['nama'] ?? '')); ?>" placeholder="Sesuai KTP / KK" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:border-amber-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Nomor Telepon / WhatsApp</label>
                    <input type="text" name="no_hp" required maxlength="20" value="<?php echo surat_page_escape((string) ($formValues['no_hp'] ?? '')); ?>" placeholder="08xxxxxxxxxx" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:border-amber-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Maksud / Keterangan Keperluan</label>
                    <textarea name="keterangan" rows="4" required maxlength="5000" placeholder="Jelaskan secara singkat tujuan/keperluan pembuatan surat ini..." class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:border-amber-500"><?php echo surat_page_escape((string) ($formValues['keterangan'] ?? '')); ?></textarea>
                </div>

                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-gray-900 font-extrabold py-3.5 rounded-xl transition shadow-md text-sm">
                    <?php echo $confirmDraft ? 'Konfirmasi dan Kirim Permohonan' : 'Kirim Permohonan Surat'; ?>
                </button>
            </form>
            <?php if ($draft !== null): ?>
                <form action="proses-surat.php" method="POST" class="mt-3">
                    <input type="hidden" name="csrf_token" value="<?php echo surat_page_escape($csrfToken); ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="w-full rounded-xl border border-slate-200 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50">Batalkan dan hapus draft</button>
                </form>
            <?php endif; ?>
            <?php if ($account !== null): ?>
                <a href="riwayat_surat.php" class="mt-5 block text-center text-sm font-bold text-blue-900 hover:text-amber-700">Riwayat permohonan surat</a>
            <?php endif; ?>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>

</body>
</html>