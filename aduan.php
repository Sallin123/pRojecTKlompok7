<?php
require_once __DIR__ . '/masyarakat_auth.php';
masyarakat_require_authentication();
require_once __DIR__ . '/koneksi.php';

$aduanSuccess = $_SESSION['aduan_submission_flash'] ?? null;
$aduanFormError = $_SESSION['aduan_form_error'] ?? null;
unset($_SESSION['aduan_submission_flash'], $_SESSION['aduan_form_error']);
if (is_array($aduanSuccess) || is_string($aduanFormError)) {
    header('Cache-Control: no-store, private');
}

// Cek status hanya untuk pemegang token akses yang diterbitkan saat pengaduan dikirim.
$hasil_cek = null;
$nohp_dicari = '';
$pencarian_dikirim = false;
$pencarian_gagal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cek_nohp'])) {
    $pencarian_dikirim = true;
    $rawPhone = $_POST['cek_nohp'];
    $accessToken = $_POST['access_token'] ?? '';
    if (is_string($rawPhone) && is_string($accessToken)) {
        $nohp_dicari = trim($rawPhone);
        if ($nohp_dicari !== ''
            && preg_match('/\A.{0,254}\z/us', $nohp_dicari) === 1
            && preg_match('/\A[a-f0-9]{64}\z/i', $accessToken) === 1) {
            try {
                $tokenHash = hash('sha256', $accessToken);
                $statement = $koneksi->prepare(
                    'SELECT tanggal, judul, isi, status FROM aduan WHERE no_hp = ? AND access_token_hash = ? ORDER BY tanggal DESC LIMIT 1'
                );
                if (!$statement) {
                    throw new RuntimeException('Aduan status query could not be prepared.');
                }
                $statement->bind_param('ss', $nohp_dicari, $tokenHash);
                if (!$statement->execute()) {
                    $statement->close();
                    throw new RuntimeException('Aduan status query could not be executed.');
                }
                $hasil_cek = $statement->get_result();
                $statement->close();
            } catch (Throwable $exception) {
                error_log('Aduan status lookup failed: ' . $exception->getMessage());
                $pencarian_gagal = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaduan Warga - Portal Resmi Desa Digital</title>
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

    <main class="max-w-4xl mx-auto px-4 py-10">
        <?php if (is_array($aduanSuccess) || is_string($aduanFormError)): ?>
            <div class="mb-8 rounded-2xl border <?php echo is_array($aduanSuccess) ? 'border-amber-300 bg-amber-50 text-slate-900' : 'border-red-200 bg-white text-red-800'; ?> p-5 shadow-sm" role="<?php echo is_array($aduanSuccess) ? 'status' : 'alert'; ?>">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-extrabold"><?php echo is_array($aduanSuccess) ? htmlspecialchars((string) ($aduanSuccess['message'] ?? 'Laporan berhasil dikirim.'), ENT_QUOTES, 'UTF-8') : htmlspecialchars($aduanFormError, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if (is_array($aduanSuccess) && is_string($aduanSuccess['token'] ?? null)): ?>
                            <p class="mt-2 text-sm text-slate-700">Simpan token ini untuk mengecek status aduan Anda:</p>
                            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                                <code id="aduan-submission-token" class="min-w-0 break-all rounded-lg border border-amber-200 bg-white px-3 py-2 font-mono text-sm text-slate-900"><?php echo htmlspecialchars($aduanSuccess['token'], ENT_QUOTES, 'UTF-8'); ?></code>
                                <button type="button" data-copy-token="aduan-submission-token" class="shrink-0 rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white transition hover:bg-slate-700">Salin token</button>
                                <span data-copy-feedback class="self-center text-xs font-semibold text-slate-600" aria-live="polite"></span>
                            </div>
                            <p class="mt-2 text-xs text-slate-600">Gunakan token ini bersama nomor kontak Anda pada fitur Cek Status Aduan.</p>
                        <?php endif; ?>
                    </div>
                    <button type="button" data-dismiss-notice class="shrink-0 rounded-lg px-2 py-1 text-sm font-bold text-slate-600 hover:bg-black/5" aria-label="Tutup notifikasi">Tutup</button>
                </div>
            </div>
        <?php endif; ?>

        <!-- 1. Banner Cek Status Laporan Pengaduan -->
        <section class="mb-10">
            <div class="bg-gradient-to-r from-slate-900 via-blue-950 to-slate-900 rounded-3xl p-6 md:p-10 text-center text-white shadow-2xl relative overflow-hidden border border-slate-800">
                <h3 class="text-2xl md:text-3xl font-black tracking-tight mb-2 flex items-center justify-center gap-2">
                    <span>🔍</span> Cek Status Laporan Pengaduan
                </h3>
                <p class="text-slate-300 text-xs md:text-sm max-w-xl mx-auto leading-relaxed mb-6">
                    Pantau perkembangan tindak lanjut laporan Anda menggunakan nomor kontak dan token akses yang diterbitkan saat pengaduan dikirim.
                </p>

                <form action="aduan.php" method="POST" class="max-w-md mx-auto flex flex-col gap-2.5">
                    <input 
                        type="text" 
                        name="cek_nohp" 
                        value="<?php echo htmlspecialchars($nohp_dicari); ?>"
                        required 
                        placeholder="KETIK NOMOR HP (CONTOH: 08123456789)" 
                        class="w-full bg-slate-900/80 border border-slate-700 text-white placeholder-slate-500 text-xs font-semibold rounded-xl px-4 py-3.5 focus:outline-none focus:border-amber-500 transition shadow-inner"
                    >
                    <input
                        type="password"
                        name="access_token"
                        required
                        minlength="64"
                        maxlength="64"
                        pattern="[A-Fa-f0-9]{64}"
                        autocomplete="off"
                        placeholder="TOKEN AKSES 64 KARAKTER DARI SAAT PENGADUAN"
                        class="w-full bg-slate-900/80 border border-slate-700 text-white placeholder-slate-500 text-xs font-semibold rounded-xl px-4 py-3.5 focus:outline-none focus:border-amber-500 transition shadow-inner"
                    >
                    <button 
                        type="submit" 
                        class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs px-6 py-3.5 rounded-xl transition shadow-lg uppercase tracking-wider"
                    >
                        Cek Status
                    </button>
                </form>
            </div>
        </section>

        <!-- 2. Tampilkan Hasil Pencarian Cek Status (Jika Ada) -->
        <?php if ($pencarian_gagal): ?>
            <div role="alert" class="bg-white rounded-2xl p-6 shadow-md border border-red-200 mb-10 text-sm text-red-800">
                Status aduan belum dapat dimuat saat ini. Silakan coba kembali nanti.
            </div>
        <?php elseif ($pencarian_dikirim): ?>
            <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100 mb-10">
                <h4 class="font-black text-base text-gray-900 mb-4 border-b pb-2">Hasil Pencarian Status Aduan</h4>

                <?php if ($hasil_cek instanceof mysqli_result && mysqli_num_rows($hasil_cek) > 0): ?>
                    <div class="space-y-4">
                        <?php while ($row = mysqli_fetch_assoc($hasil_cek)): ?>
                            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <span class="text-[10px] font-bold text-gray-400 uppercase"><?php echo date('d M Y - H:i', strtotime($row['tanggal'])); ?></span>
                                    <h5 class="font-extrabold text-sm text-gray-900 mt-0.5"><?php echo htmlspecialchars($row['judul']); ?></h5>
                                    <p class="text-xs text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars($row['isi']); ?></p>
                                </div>
                                <div class="shrink-0">
                                    <?php if ($row['status'] == 'Pending'): ?>
                                        <span class="bg-amber-100 text-amber-800 text-xs font-extrabold px-3 py-1 rounded-full border border-amber-200">Pending ⏳</span>
                                    <?php elseif ($row['status'] == 'Diproses'): ?>
                                        <span class="bg-blue-100 text-blue-800 text-xs font-extrabold px-3 py-1 rounded-full border border-blue-200">Diproses 🔄</span>
                                    <?php else: ?>
                                        <span class="bg-emerald-100 text-emerald-800 text-xs font-extrabold px-3 py-1 rounded-full border border-emerald-200">Selesai ✅</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-gray-500 text-center py-4">Aduan tidak ditemukan. Periksa nomor kontak dan token akses, lalu coba kembali.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 3. Form Input Layanan Pengaduan Online -->
        <div id="form-aduan" class="scroll-mt-24 bg-white rounded-3xl p-6 md:p-10 shadow-lg border border-gray-100">
            <div class="mb-8 text-center">
                <span class="text-amber-500 text-xs font-extrabold uppercase tracking-widest">ASPIRASI & PENGADUAN</span>
                <h2 class="text-2xl md:text-3xl font-black text-gray-900 mt-1">Form Layanan Pengaduan Online</h2>
                <p class="text-gray-500 text-sm mt-2">Sampaikan laporan gangguan ketertiban, kebersihan, atau usulan pembangunan secara resmi.</p>
            </div>

            <form action="proses-aduan.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(masyarakat_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="return_to" value="aduan.php#form-aduan">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Kategori Pengaduan</label>
                    <select name="kategori" required class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:border-amber-500">
                        <option value="">-- Pilih Kategori --</option>
                        <option value="Ketertiban & Keamanan">Ketertiban & Keamanan</option>
                        <option value="Kebersihan & Lingkungan">Kebersihan & Lingkungan</option>
                        <option value="Infrastruktur & Jalan">Infrastruktur & Jalan</option>
                        <option value="Pelayanan Administrasi">Pelayanan Administrasi</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Nama Lengkap Pelapor</label>
                        <input type="text" name="nama" required placeholder="Masukkan nama Anda" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Nomor WhatsApp / HP</label>
                        <input type="text" name="no_hp" required maxlength="20" placeholder="08xxxxxxxxxx" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:border-amber-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Judul Laporan</label>
                    <input type="text" name="judul" required placeholder="Ringkasan singkat isi pengaduan" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:border-amber-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Isi Laporan / Detail Pengaduan</label>
                    <textarea name="isi" rows="4" required placeholder="Jelaskan secara detail kejadian, lokasi, atau permasalahan yang dialami..." class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:border-amber-500"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Lampiran Foto Bukti (Opsional)</label>
                    <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-2.5 text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200">
                </div>

                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-gray-900 font-extrabold py-3.5 rounded-xl transition shadow-md text-sm">
                    Kirim Pengaduan Warga
                </button>
            </form>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>

    <script>
    (() => {
        document.querySelectorAll('[data-dismiss-notice]').forEach((button) => {
            button.addEventListener('click', () => button.closest('[role="status"], [role="alert"]')?.remove());
        });
        document.querySelectorAll('[data-copy-token]').forEach((button) => {
            button.addEventListener('click', async () => {
                const token = document.getElementById(button.dataset.copyToken);
                const feedback = button.parentElement.querySelector('[data-copy-feedback]');
                if (!token || !feedback) return;
                try {
                    await navigator.clipboard.writeText(token.textContent.trim());
                    feedback.textContent = 'Token berhasil disalin.';
                } catch (error) {
                    feedback.textContent = 'Gagal menyalin. Silakan salin token secara manual.';
                }
            });
        });
    })();
    </script>
</body>
</html>