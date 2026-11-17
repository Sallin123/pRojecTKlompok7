<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/../includes/mobil_siaga_helpers.php';
require_once __DIR__ . '/layout.php';

$loadErrors = [];
$profile = null;
$facilities = [];
$vehicleStatus = null;
$applications = [];
$applicationDetails = [];
$applicationHistories = [];
try {
    $profile = mobil_siaga_get_profile($koneksi);
} catch (Throwable $exception) {
    error_log('Admin Mobil Siaga profile load failed: ' . $exception->getMessage());
    $loadErrors['profile'] = true;
}
try {
    $facilities = mobil_siaga_list_facilities($koneksi, false);
} catch (Throwable $exception) {
    error_log('Admin Mobil Siaga facilities load failed: ' . $exception->getMessage());
    $loadErrors['facilities'] = true;
}
try {
    $vehicleStatus = mobil_siaga_get_vehicle_status($koneksi);
} catch (Throwable $exception) {
    error_log('Admin Mobil Siaga status load failed: ' . $exception->getMessage());
    $loadErrors['status'] = true;
}
try {
    $applications = mobil_siaga_list_applications($koneksi, null, 50, 0);
} catch (Throwable $exception) {
    error_log('Admin Mobil Siaga application list load failed: ' . $exception->getMessage());
    $loadErrors['applications'] = true;
}
foreach ($applications as $applicationRow) {
    $applicationId = (int) ($applicationRow['id'] ?? 0);
    if ($applicationId < 1) {
        continue;
    }
    try {
        $applicationDetails[$applicationId] = mobil_siaga_get_application($koneksi, $applicationId);
        $applicationHistories[$applicationId] = mobil_siaga_list_application_history($koneksi, $applicationId);
    } catch (Throwable $exception) {
        error_log('Admin Mobil Siaga application detail load failed: ' . $exception->getMessage());
        $applicationDetails[$applicationId] = null;
        $applicationHistories[$applicationId] = [];
    }
}

$escape = static function ($value): string {
    return admin_module_escape(is_scalar($value) ? (string) $value : '');
};
$profile ??= null;
$flash = admin_module_take_flash('admin_mobil_siaga_flash');
$csrfToken = admin_csrf_token();
$statusAvailable = $vehicleStatus === 'siap_beroperasi' ? 'tersedia'
    : (in_array($vehicleStatus, ['sedang_digunakan', 'dalam_perawatan', 'tidak_dapat_beroperasi'], true)
        ? 'tidak_tersedia' : '');
$applicationStatusLabels = [
    'menunggu_verifikasi' => 'Menunggu verifikasi',
    'diproses' => 'Diproses',
    'disetujui' => 'Disetujui',
    'ditolak' => 'Ditolak',
    'selesai' => 'Selesai',
];

admin_render_header('Mobil Siaga', $_SESSION['admin_username']);
?>
<div class="mb-6">
    <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Layanan kendaraan desa</p>
    <h2 class="mt-1 text-2xl font-black text-slate-950">Profil Mobil Siaga</h2>
    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Kelola informasi kendaraan, fasilitas, dan ketersediaan yang ditampilkan pada halaman publik.</p>
</div>
<?php if (is_array($flash)): ?>
    <div role="<?php echo ($flash['type'] ?? '') === 'error' ? 'alert' : 'status'; ?>" class="mb-5 rounded-xl border px-4 py-3 text-sm font-semibold <?php echo ($flash['type'] ?? '') === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-950'; ?>"><?php echo $escape($flash['message'] ?? ''); ?></div>
<?php endif; ?>
<?php if (isset($loadErrors['profile'])): ?>
    <p role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Profil kendaraan tidak dapat dimuat. Periksa koneksi database atau struktur tabel.</p>
<?php endif; ?>
<div class="grid gap-5 xl:grid-cols-[1.2fr_.8fr]">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <div class="mb-5">
            <p class="text-[11px] font-extrabold uppercase tracking-[.16em] text-amber-600">Informasi utama</p>
            <h3 class="mt-1 text-xl font-black text-slate-950">Profil Kendaraan</h3>
            <p class="mt-1 text-sm leading-6 text-slate-500">Perubahan akan ditampilkan di halaman publik setelah berhasil disimpan.</p>
        </div>
        <form action="mobil_siaga_action.php" method="post" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
            <input type="hidden" name="action" value="save_profile">
            <label class="block text-xs font-bold text-slate-700">Nama kendaraan
                <input name="nama_kendaraan" required maxlength="150" value="<?php echo is_array($profile) ? $escape($profile['nama_kendaraan'] ?? '') : ''; ?>" class="mt-1 w-full rounded-xl border border-slate-200 p-3 text-sm focus:border-amber-500 focus:outline-none">
            </label>
            <label class="block text-xs font-bold text-slate-700">Deskripsi kendaraan
                <textarea name="deskripsi" rows="4" maxlength="10000" class="mt-1 w-full rounded-xl border border-slate-200 p-3 text-sm focus:border-amber-500 focus:outline-none"><?php echo is_array($profile) ? $escape($profile['deskripsi'] ?? '') : ''; ?></textarea>
            </label>
            <label class="block text-xs font-bold text-slate-700">Kapasitas penumpang
                <input name="kapasitas_penumpang" type="number" min="1" max="65535" value="<?php echo is_array($profile) ? $escape($profile['kapasitas_penumpang'] ?? '') : ''; ?>" class="mt-1 w-full rounded-xl border border-slate-200 p-3 text-sm focus:border-amber-500 focus:outline-none">
            </label>
            <div>
                <label class="block text-xs font-bold text-slate-700" for="foto_kendaraan">Foto kendaraan</label>
                <?php if (is_array($profile) && !empty($profile['foto_file'])): ?>
                    <img src="../mobil_siaga_image.php?type=vehicle&amp;id=1" alt="Foto kendaraan saat ini" class="my-3 h-44 w-full rounded-xl border border-slate-200 object-cover sm:w-80">
                <?php else: ?>
                    <p class="my-2 rounded-xl bg-slate-50 p-3 text-xs text-slate-500">Foto kendaraan belum tersedia.</p>
                <?php endif; ?>
                <input id="foto_kendaraan" name="foto_kendaraan" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm text-slate-600">
                <p class="mt-1 text-xs text-slate-500">Opsional untuk mengganti foto. Format JPG, PNG, atau WebP, maksimal 5 MB.</p>
            </div>
            <button type="submit" class="w-full rounded-xl bg-amber-500 px-5 py-3 text-sm font-extrabold text-slate-950 transition hover:bg-amber-400 sm:w-auto">Simpan Profil</button>
        </form>
    </section>

    <div class="space-y-5">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-4">
                <p class="text-[11px] font-extrabold uppercase tracking-[.16em] text-amber-600">Ketersediaan layanan</p>
                <h3 class="mt-1 text-lg font-black text-slate-950">Status Kendaraan</h3>
                <p class="mt-1 text-sm leading-6 text-slate-500">Status menggunakan nilai operasional yang sudah tersedia di database.</p>
            </div>
            <?php if (isset($loadErrors['status'])): ?>
                <p role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-800">Status kendaraan tidak dapat dimuat.</p>
            <?php else: ?>
                <div class="mb-4 rounded-xl bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Status saat ini</p>
                    <p class="mt-1 text-lg font-black text-slate-950"><?php echo $statusAvailable === 'tersedia' ? 'Tersedia' : ($statusAvailable === 'tidak_tersedia' ? 'Tidak tersedia' : 'Belum ditetapkan'); ?></p>
                </div>
                <?php if (is_array($profile)): ?>
                    <form action="mobil_siaga_action.php" method="post" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                        <input type="hidden" name="action" value="set_vehicle_status">
                        <label class="block text-xs font-bold text-slate-700">Ubah status
                            <select name="status" required class="mt-1 w-full rounded-xl border border-slate-200 p-3 text-sm">
                                <option value="siap_beroperasi" <?php echo $statusAvailable === 'tersedia' ? 'selected' : ''; ?>>Tersedia</option>
                                <option value="tidak_dapat_beroperasi" <?php echo $statusAvailable === 'tidak_tersedia' ? 'selected' : ''; ?>>Tidak tersedia</option>
                            </select>
                        </label>
                        <label class="block text-xs font-bold text-slate-700">Catatan (opsional)
                            <textarea name="catatan" rows="2" maxlength="10000" class="mt-1 w-full rounded-xl border border-slate-200 p-3 text-sm"></textarea>
                        </label>
                        <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-800 hover:bg-slate-50">Simpan Status</button>
                    </form>
                <?php else: ?>
                    <p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-950">Simpan profil kendaraan terlebih dahulu sebelum menetapkan status.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-4">
                <p class="text-[11px] font-extrabold uppercase tracking-[.16em] text-amber-600">Perlengkapan</p>
                <h3 class="mt-1 text-lg font-black text-slate-950">Fasilitas Kendaraan</h3>
                <p class="mt-1 text-sm leading-6 text-slate-500">Fasilitas aktif ditampilkan kepada pengunjung. Nonaktifkan untuk menyembunyikan tanpa menghapus datanya.</p>
            </div>
            <?php if (isset($loadErrors['facilities'])): ?>
                <p role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-800">Daftar fasilitas tidak dapat dimuat.</p>
            <?php else: ?>
                <?php if (is_array($profile)): ?>
                    <form action="mobil_siaga_action.php" method="post" class="mb-4 space-y-3 rounded-xl bg-slate-50 p-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                        <input type="hidden" name="action" value="save_facility">
                        <label class="block text-xs font-bold text-slate-700">Nama fasilitas
                            <input name="nama_fasilitas" required maxlength="150" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5 text-sm">
                        </label>
                        <label class="block text-xs font-bold text-slate-700">Keterangan
                            <input name="keterangan" maxlength="10000" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5 text-sm">
                        </label>
                        <div class="grid grid-cols-[1fr_auto] gap-3">
                            <label class="block text-xs font-bold text-slate-700">Urutan
                                <input name="urutan" type="number" min="0" max="65535" value="0" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5 text-sm">
                            </label>
                            <label class="block text-xs font-bold text-slate-700">Aktif
                                <select name="aktif" class="mt-1 rounded-lg border border-slate-200 bg-white p-2.5 text-sm"><option value="1">Ya</option><option value="0">Tidak</option></select>
                            </label>
                        </div>
                        <button type="submit" class="rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800">Tambah Fasilitas</button>
                    </form>
                <?php else: ?>
                    <p class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">Simpan profil kendaraan terlebih dahulu sebelum mengatur fasilitas.</p>
                <?php endif; ?>
                <?php if ($facilities === []): ?>
                    <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-center text-sm text-slate-500">Belum ada data fasilitas.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($facilities as $facility): ?>
                            <form action="mobil_siaga_action.php" method="post" class="space-y-3 rounded-xl border border-slate-200 p-4">
                                <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                                <input type="hidden" name="action" value="save_facility">
                                <input type="hidden" name="facility_id" value="<?php echo (int) $facility['id']; ?>">
                                <label class="block text-xs font-bold text-slate-700">Nama fasilitas
                                    <input name="nama_fasilitas" required maxlength="150" value="<?php echo $escape($facility['nama_fasilitas'] ?? ''); ?>" class="mt-1 w-full rounded-lg border border-slate-200 p-2.5 text-sm">
                                </label>
                                <label class="block text-xs font-bold text-slate-700">Keterangan
                                    <input name="keterangan" maxlength="10000" value="<?php echo $escape($facility['keterangan'] ?? ''); ?>" class="mt-1 w-full rounded-lg border border-slate-200 p-2.5 text-sm">
                                </label>
                                <div class="grid grid-cols-[1fr_auto] gap-3">
                                    <label class="block text-xs font-bold text-slate-700">Urutan
                                        <input name="urutan" type="number" min="0" max="65535" value="<?php echo $escape($facility['urutan'] ?? '0'); ?>" class="mt-1 w-full rounded-lg border border-slate-200 p-2.5 text-sm">
                                    </label>
                                    <label class="block text-xs font-bold text-slate-700">Aktif
                                        <select name="aktif" class="mt-1 rounded-lg border border-slate-200 p-2.5 text-sm">
                                            <option value="1" <?php echo (int) ($facility['aktif'] ?? 0) === 1 ? 'selected' : ''; ?>>Ya</option>
                                            <option value="0" <?php echo (int) ($facility['aktif'] ?? 0) !== 1 ? 'selected' : ''; ?>>Tidak</option>
                                        </select>
                                    </label>
                                </div>
                                <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold text-slate-800 hover:bg-slate-50">Simpan Fasilitas</button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</div>
<section id="permohonan" class="mt-6 scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
    <div class="mb-5">
        <p class="text-[11px] font-extrabold uppercase tracking-[.16em] text-amber-600">Permohonan warga</p>
        <h3 class="mt-1 text-xl font-black text-slate-950">Daftar Permohonan Mobil Siaga</h3>
        <p class="mt-1 text-sm leading-6 text-slate-500">Setujui atau tolak permohonan. Keputusan hanya memperbarui status dan riwayat; tidak membuat jadwal maupun catatan penggunaan.</p>
    </div>
    <?php if (isset($loadErrors['applications'])): ?>
        <p role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">Daftar permohonan tidak dapat dimuat saat ini.</p>
    <?php elseif ($applications === []): ?>
        <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500">Belum ada permohonan Mobil Siaga.</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($applications as $application): ?>
                <?php
                $applicationId = (int) ($application['id'] ?? 0);
                $detail = $applicationDetails[$applicationId] ?? null;
                $applicationStatus = (string) ($application['status'] ?? '');
                $canReview = in_array($applicationStatus, ['menunggu_verifikasi', 'diproses'], true);
                $phone = is_string($application['no_hp_pemohon'] ?? null) ? trim($application['no_hp_pemohon']) : '';
                $waPhone = preg_replace('/\D+/', '', $phone);
                if (strpos($waPhone, '0') === 0) {
                    $waPhone = '62' . substr($waPhone, 1);
                }
                $validWaPhone = preg_match('/\A[0-9]{8,15}\z/', $waPhone) === 1;
                ?>
                <article class="overflow-hidden rounded-2xl border border-slate-200">
                    <div class="flex flex-col justify-between gap-3 bg-slate-50 p-4 sm:flex-row sm:items-start">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="font-black text-slate-950"><?php echo $escape($application['nama_pemohon'] ?? ''); ?></h4>
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-extrabold text-amber-950"><?php echo $escape($applicationStatusLabels[$applicationStatus] ?? 'Status tidak diketahui'); ?></span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Kode: <?php echo $escape($application['kode_pengajuan'] ?? ''); ?> · Masuk: <?php echo $escape($application['created_at'] ?? ''); ?></p>
                        </div>
                        <?php if ($validWaPhone): ?>
                            <a href="https://wa.me/<?php echo $escape($waPhone); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800">WhatsApp pemohon</a>
                        <?php endif; ?>
                    </div>
                    <div class="grid gap-4 p-4 lg:grid-cols-2">
                        <div class="space-y-2 text-sm text-slate-700">
                            <p><b>Nomor WhatsApp:</b> <?php echo $escape($phone); ?></p>
                            <p><b>Alamat penjemputan:</b> <?php echo $escape(is_array($detail) ? ($detail['alamat_pemohon'] ?? 'Belum diisi') : 'Detail tidak tersedia'); ?></p>
                            <p><b>Tujuan:</b> <?php echo $escape($application['tujuan_penggunaan'] ?? ''); ?></p>
                            <p><b>Tanggal dimohonkan:</b> <?php echo $escape($application['tanggal_penggunaan'] ?? ''); ?></p>
                            <p><b>Waktu preferensi:</b> <?php echo $escape(substr((string) ($application['jam_mulai'] ?? ''), 0, 5)); ?> <span class="text-xs text-slate-500">(bukan jadwal terkonfirmasi)</span></p>
                            <p><b>Jumlah penumpang:</b> <?php echo $escape($application['jumlah_penumpang'] ?? ''); ?></p>
                            <p><b>Keterangan:</b> <?php echo $escape(is_array($detail) ? ($detail['catatan'] ?? 'Tidak ada') : 'Detail tidak tersedia'); ?></p>
                            <?php if (is_array($detail) && !empty($detail['alasan_penolakan'])): ?>
                                <p class="text-red-700"><b>Alasan penolakan:</b> <?php echo $escape($detail['alasan_penolakan']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($canReview): ?>
                            <div class="space-y-3">
                                <form action="mobil_siaga_action.php" method="post" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="approve_application">
                                    <input type="hidden" name="application_id" value="<?php echo $applicationId; ?>">
                                    <p class="mb-3 text-xs leading-5 text-amber-950">Persetujuan memperbarui status menjadi Disetujui dan mencatat riwayat. Tidak ada jadwal keberangkatan atau data penggunaan yang dibuat.</p>
                                    <label class="mb-3 block text-xs font-bold text-slate-700">Pesan untuk pemohon (opsional)
                                        <textarea name="pesan" maxlength="10000" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2.5 text-sm"></textarea>
                                    </label>
                                    <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2.5 text-xs font-extrabold text-slate-950 hover:bg-amber-400">Setujui</button>
                                </form>
                                <form action="mobil_siaga_action.php" method="post" class="rounded-xl border border-slate-200 p-4">
                                    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="transition_application">
                                    <input type="hidden" name="application_id" value="<?php echo $applicationId; ?>">
                                    <input type="hidden" name="status" value="ditolak">
                                    <label class="mb-3 block text-xs font-bold text-slate-700">Alasan penolakan (opsional)
                                        <textarea name="pesan" maxlength="10000" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 p-2.5 text-sm"></textarea>
                                    </label>
                                    <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-50">Tolak</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php $history = $applicationHistories[$applicationId] ?? []; ?>
                    <div class="border-t border-slate-100 p-4">
                        <h5 class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Riwayat status</h5>
                        <?php if ($history === []): ?>
                            <p class="mt-2 text-sm text-slate-500">Riwayat belum tersedia.</p>
                        <?php else: ?>
                            <ol class="mt-2 space-y-2">
                                <?php foreach ($history as $historyItem): ?>
                                    <li class="rounded-lg bg-slate-50 p-3 text-sm">
                                        <p class="font-bold text-slate-900"><?php echo $escape($applicationStatusLabels[$historyItem['status'] ?? ''] ?? 'Status diperbarui'); ?></p>
                                        <p class="mt-1 text-xs text-slate-500"><?php echo $escape($historyItem['dibuat_at'] ?? ''); ?> · <?php echo $escape($historyItem['diubah_oleh'] ?? 'Pemohon'); ?></p>
                                        <?php if (!empty($historyItem['pesan'])): ?><p class="mt-1 whitespace-pre-line text-slate-600"><?php echo $escape($historyItem['pesan']); ?></p><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php admin_render_footer(); ?>
