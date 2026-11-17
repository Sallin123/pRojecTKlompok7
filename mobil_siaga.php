<?php
require_once __DIR__ . '/masyarakat_auth.php';
masyarakat_require_authentication();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/includes/mobil_siaga_helpers.php';

function mobil_siaga_public_escape($value): string
{
    return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
}

$sections = [];
$sectionErrors = [];
$load = static function (string $name, callable $loader, $fallback) use ($koneksi, &$sections, &$sectionErrors): void {
    try {
        $sections[$name] = $loader($koneksi);
    } catch (Throwable $exception) {
        error_log('Public Mobil Siaga ' . $name . ' load failed: ' . $exception->getMessage());
        $sections[$name] = $fallback;
        $sectionErrors[$name] = true;
    }
};

$load('profile', 'mobil_siaga_get_profile', null);
$load('facilities', 'mobil_siaga_list_facilities', []);
$load('status', 'mobil_siaga_get_vehicle_status', null);

$profile = $sections['profile'] ?? null;
$facilities = $sections['facilities'] ?? [];
$vehicleStatus = $sections['status'] ?? null;
$availabilityLabel = $vehicleStatus === 'siap_beroperasi'
    ? 'Tersedia'
    : (in_array($vehicleStatus, ['sedang_digunakan', 'dalam_perawatan', 'tidak_dapat_beroperasi'], true)
        ? 'Tidak tersedia' : null);
$emergencyContact = is_array($profile) && is_string($profile['kontak_darurat'] ?? null)
    ? trim($profile['kontak_darurat']) : '';
$telContact = preg_replace('/[^0-9+]/', '', $emergencyContact);
$whatsAppContact = ltrim($telContact, '+');
if (strpos($whatsAppContact, '0') === 0) {
    $whatsAppContact = '62' . substr($whatsAppContact, 1);
}
$validEmergencyContact = preg_match('/\A\+?[0-9][0-9(). -]{5,28}\z/', $emergencyContact) === 1
    && preg_match('/\A[0-9]{8,15}\z/', $whatsAppContact) === 1;
$csrfToken = masyarakat_csrf_token();
$formError = is_string($_SESSION['mobil_siaga_form_error'] ?? null)
    ? $_SESSION['mobil_siaga_form_error'] : '';
$submittedCode = is_string($_SESSION['mobil_siaga_application_code'] ?? null)
    ? $_SESSION['mobil_siaga_application_code'] : '';
unset($_SESSION['mobil_siaga_form_error'], $_SESSION['mobil_siaga_application_code']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobil Siaga - Desa Pemayahan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/public-notice.css?v=20261026" rel="stylesheet">
    <link href="assets/css/public-nav.css?v=20261117-desktop-nav" rel="stylesheet">
    <style>body{font-family:'Plus Jakarta Sans',sans-serif}</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
<?php require __DIR__ . '/includes/public_notice.php'; ?>
<header class="sticky top-0 z-50 border-b border-slate-100 bg-white shadow-sm">
    <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <a href="index.php" class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500 text-xl font-black text-slate-950">D</div>
            <div><h1 class="text-xl font-extrabold tracking-tight text-slate-900">DESA <span class="text-amber-500">DIGITAL</span></h1><p class="mt-1 text-[10px] uppercase tracking-widest text-slate-500">Portal Resmi Layanan Desa</p></div>
        </a>
        <?php require __DIR__ . '/includes/public_nav.php'; ?>
        <a href="login.php" class="hidden rounded-full bg-blue-900 px-5 py-2.5 text-xs font-bold text-white shadow-md lg:inline-flex">LOGIN ADMIN</a>
    </div>
</header>
<main class="mx-auto max-w-6xl space-y-7 px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
    <section class="overflow-hidden rounded-3xl bg-slate-950 text-white shadow-xl">
        <div class="grid md:grid-cols-[1.1fr_.9fr]">
            <div class="p-7 sm:p-10 lg:p-12">
                <p class="text-xs font-extrabold uppercase tracking-[.2em] text-amber-400">Layanan Desa Pemayahan</p>
                <h2 class="mt-3 text-3xl font-black leading-tight sm:text-4xl">Mobil Siaga Desa</h2>
                <p class="mt-4 max-w-xl leading-7 text-slate-300">Informasi profil kendaraan, kapasitas, fasilitas, dan ketersediaan layanan yang bersumber dari data desa.</p>
                <a href="#permohonan" class="mt-7 inline-flex rounded-xl bg-amber-500 px-5 py-3 text-sm font-extrabold text-slate-950 transition hover:bg-amber-400">Ajukan permohonan</a>
            </div>
            <div class="flex min-h-56 items-center justify-center bg-slate-900 p-6">
                <?php if (is_array($profile) && !empty($profile['foto_file'])): ?>
                    <img src="mobil_siaga_image.php?type=vehicle&id=1" alt="Foto <?php echo mobil_siaga_public_escape($profile['nama_kendaraan'] ?? 'kendaraan Mobil Siaga'); ?>" class="max-h-64 w-full rounded-2xl object-cover">
                <?php else: ?>
                    <div class="w-full rounded-2xl border border-dashed border-slate-600 px-6 py-12 text-center text-sm text-slate-400">Foto kendaraan belum tersedia.</div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-[1.15fr_.85fr]">
        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Informasi kendaraan</p><h3 class="mt-1 text-xl font-black text-slate-950"><?php echo mobil_siaga_public_escape(is_array($profile) ? ($profile['nama_kendaraan'] ?? 'Nama kendaraan belum tersedia') : 'Profil kendaraan belum tersedia'); ?></h3></div>
                <?php if (isset($sectionErrors['profile']) || isset($sectionErrors['status'])): ?>
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">Status belum dapat diperiksa</span>
                <?php elseif ($availabilityLabel !== null): ?>
                    <span class="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-extrabold text-amber-900"><?php echo mobil_siaga_public_escape($availabilityLabel); ?></span>
                <?php else: ?>
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">Status belum tersedia</span>
                <?php endif; ?>
            </div>
            <?php if (is_array($profile)): ?>
                <p class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-600"><?php echo mobil_siaga_public_escape($profile['deskripsi'] ?? 'Informasi deskripsi belum tersedia.'); ?></p>
                <div class="mt-5 rounded-xl bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase text-slate-500">Kapasitas penumpang</p>
                    <p class="mt-1 font-extrabold text-slate-900"><?php echo $profile['kapasitas_penumpang'] !== null && $profile['kapasitas_penumpang'] !== '' ? mobil_siaga_public_escape($profile['kapasitas_penumpang']) . ' penumpang' : 'Belum tersedia'; ?></p>
                </div>
            <?php else: ?>
                <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600"><?php echo isset($sectionErrors['profile']) ? 'Informasi kendaraan tidak dapat dimuat saat ini.' : 'Data profil kendaraan belum tersedia.'; ?></p>
            <?php endif; ?>
        </article>
        <?php if ($validEmergencyContact): ?>
            <aside class="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm sm:p-8">
                <p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">Kontak darurat Mobil Siaga</p>
                <a href="tel:<?php echo mobil_siaga_public_escape($telContact); ?>" class="mt-5 block break-all rounded-xl bg-slate-950 px-4 py-4 text-center text-lg font-black text-white"><?php echo mobil_siaga_public_escape($emergencyContact); ?></a>
                <?php if (is_string($profile['nama_kontak_darurat'] ?? null) && trim($profile['nama_kontak_darurat']) !== ''): ?><p class="mt-2 text-center text-sm font-semibold text-slate-700"><?php echo mobil_siaga_public_escape($profile['nama_kontak_darurat']); ?></p><?php endif; ?>
            </aside>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Perlengkapan</p>
        <h3 class="mt-1 text-xl font-black text-slate-950">Fasilitas kendaraan</h3>
        <?php if (isset($sectionErrors['facilities'])): ?>
            <p role="alert" class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Informasi fasilitas belum dapat dimuat saat ini.</p>
        <?php elseif ($facilities === []): ?>
            <p class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">Informasi fasilitas kendaraan belum tersedia.</p>
        <?php else: ?>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($facilities as $facility): ?>
                    <li class="rounded-xl bg-slate-50 p-4">
                        <p class="font-bold text-slate-900"><?php echo mobil_siaga_public_escape($facility['nama_fasilitas'] ?? ''); ?></p>
                        <?php if (is_string($facility['keterangan'] ?? null) && trim($facility['keterangan']) !== ''): ?>
                            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-600"><?php echo mobil_siaga_public_escape($facility['keterangan']); ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section id="permohonan" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="grid gap-7 lg:grid-cols-[.8fr_1.2fr]">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Permohonan warga</p>
                <h3 class="mt-1 text-xl font-black text-slate-950">Ajukan penggunaan Mobil Siaga</h3>
                <p class="mt-3 text-sm leading-6 text-slate-600">Kirim permohonan untuk ditinjau admin. Pengiriman formulir belum berarti permohonan disetujui. Waktu yang dimohonkan hanya preferensi warga, bukan jadwal keberangkatan yang dikonfirmasi.</p>
                <a href="mobil_siaga_lacak.php" class="mt-5 inline-flex rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-800 hover:bg-slate-50">Lacak permohonan dengan kode</a>
                <?php if ($validEmergencyContact): ?>
                    <a href="https://wa.me/<?php echo mobil_siaga_public_escape($whatsAppContact); ?>" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Hubungi kontak resmi melalui WhatsApp</a>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($submittedCode !== ''): ?>
                    <div role="status" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm font-bold text-slate-800">Permohonan tercatat. Simpan kode pelacakan berikut:</p>
                        <p class="mt-1 text-xl font-black tracking-wider text-slate-950"><?php echo mobil_siaga_public_escape($submittedCode); ?></p>
                        <a href="mobil_siaga_lacak.php" class="mt-2 inline-block text-sm font-bold text-slate-800 underline">Lihat status permohonan</a>
                        <?php if ($validEmergencyContact): ?>
                            <?php $whatsAppMessage = 'Permohonan Mobil Siaga saya sudah dikirim. Kode pelacakan: ' . $submittedCode; ?>
                            <a href="https://wa.me/<?php echo mobil_siaga_public_escape($whatsAppContact); ?>?text=<?php echo rawurlencode($whatsAppMessage); ?>" target="_blank" rel="noopener noreferrer" class="mt-3 block text-sm font-bold text-slate-800 underline">Kirim kode ini melalui WhatsApp</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($formError !== ''): ?>
                    <p role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-800"><?php echo mobil_siaga_public_escape($formError); ?></p>
                <?php endif; ?>
                <form action="mobil_siaga_ajukan.php" method="post" class="grid gap-4 sm:grid-cols-2">
                    <input type="hidden" name="csrf_token" value="<?php echo mobil_siaga_public_escape($csrfToken); ?>">
                    <label class="text-xs font-bold text-slate-700">Nama pemohon
                        <input name="nama_pemohon" required maxlength="150" autocomplete="name" class="mt-1 w-full rounded-xl border border-slate-200 p-3 text-sm">
                    </label>
                    <label class="text-xs font-bold text-slate-700">Nomor WhatsApp
                        <input name="no_hp_pemohon" type="tel" required maxlength="30" autocomplete="tel" class="mt-1 w-full rounded-xl border border-slate-200 p-3 text-sm">
                    </label>
                    <label class="text-xs font-bold text-slate-700 sm:col-span-2">Alamat penjemputan
                        <textarea name="alamat_pemohon" required maxlength="10000" rows="2" autocomplete="street-address" class="mt-1 w-full rounded-xl border border-slate-200 p-3 text-sm"></textarea>
                    </label>
                    <label class="text-xs font-bold text-slate-700 sm:col-span-2">Tujuan penggunaan
                        <input name="tujuan_penggunaan" required maxlength="255" class="mt-1 w-full rounded-xl border border-slate-200 p-3 text-sm">
                    </label>
                    <label class="text-xs font-bold text-slate-700">Tanggal yang dimohonkan
                        <input name="tanggal_penggunaan" type="date" required min="<?php echo date('Y-m-d'); ?>" class="mt-1 w-full rounded-xl border border-slate-200 p-3 text-sm">
                    </label>
                    <label class="text-xs font-bold text-slate-700">Jumlah penumpang
                        <input name="jumlah_penumpang" type="number" min="1" max="65535" value="1" required class="mt-1 w-full rounded-xl border border-slate-200 p-3 text-sm">
                    </label>
                    <label class="text-xs font-bold text-slate-700 sm:col-span-2">Waktu yang dimohonkan
                        <input name="jam_mulai" type="time" required class="mt-1 w-full rounded-xl border border-slate-200 p-3 text-sm">
                        <span class="mt-1 block font-normal leading-5 text-slate-500">Waktu ini hanya preferensi permohonan. Admin belum menetapkan jadwal keberangkatan.</span>
                    </label>
                    <label class="text-xs font-bold text-slate-700 sm:col-span-2">Keterangan
                        <textarea name="catatan" maxlength="10000" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 p-3 text-sm"></textarea>
                    </label>
                    <p class="text-xs leading-5 text-slate-500 sm:col-span-2">Permohonan dapat dipantau menggunakan kode setelah dikirim. Tidak ada jadwal keberangkatan atau waktu selesai yang dibuat otomatis.</p>
                    <button type="submit" class="rounded-xl bg-amber-500 px-5 py-3 text-sm font-extrabold text-slate-950 transition hover:bg-amber-400 sm:col-span-2">Kirim permohonan</button>
                </form>
            </div>
        </div>
    </section>
</main>
<footer class="border-t border-slate-200 bg-white py-6 text-center text-xs text-slate-500">Layanan Mobil Siaga Desa Pemayahan</footer>
</body>
</html>
