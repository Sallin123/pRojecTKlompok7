<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/layout.php';

$flash = admin_module_take_flash('posyandu_flash');
$errorMessage = '';
$tableAvailable = false;
$profile = null;
$schedules = [];
$activities = [];
$agendaRecord = null;
$agendaSection = 'jadwal';

try {
    $tableAvailable = admin_module_missing_tables($koneksi, ['posyandu_profil', 'posyandu_agenda']) === [];

    if (!$tableAvailable) {
        $errorMessage = 'Tabel Profil dan Agenda Posyandu belum tersedia.';
    } else {
        $profileStatement = admin_module_execute(
            $koneksi,
            'SELECT nama_posyandu, deskripsi, lokasi, waktu_layanan FROM posyandu_profil WHERE id = 1 LIMIT 1'
        );
        $profileResult = $profileStatement->get_result();
        $profile = $profileResult ? $profileResult->fetch_assoc() : null;
        $profileStatement->close();

        $agendaStatement = admin_module_execute(
            $koneksi,
            'SELECT id, jenis, judul, deskripsi, tanggal, waktu_mulai, waktu_selesai, lokasi, status_publikasi
            FROM posyandu_agenda ORDER BY tanggal DESC, id DESC'
        );
        $agendaResult = $agendaStatement->get_result();
        if ($agendaResult) {
            while ($row = $agendaResult->fetch_assoc()) {
                if ($row['jenis'] === 'jadwal') {
                    $schedules[] = $row;
                } elseif (in_array($row['jenis'], ['kegiatan', 'imunisasi'], true)) {
                    $activities[] = $row;
                }
            }
        }
        $agendaStatement->close();

        $rawAgendaId = $_GET['edit_agenda'] ?? '';
        if ($rawAgendaId !== '') {
            if (!is_string($rawAgendaId) || !ctype_digit($rawAgendaId) || (int) $rawAgendaId < 1) {
                throw new InvalidArgumentException('ID agenda untuk diedit tidak valid.');
            }

            $agendaId = (int) $rawAgendaId;
            $lookup = admin_module_execute(
                $koneksi,
                'SELECT id, jenis, judul, deskripsi, tanggal, waktu_mulai, waktu_selesai, lokasi, status_publikasi
                FROM posyandu_agenda WHERE id = ? LIMIT 1',
                'i',
                [$agendaId]
            );
            $agendaRecord = $lookup->get_result()->fetch_assoc();
            $lookup->close();
            if (!$agendaRecord) {
                throw new InvalidArgumentException('Agenda Posyandu tidak ditemukan.');
            }

            $agendaSection = $agendaRecord['jenis'] === 'jadwal' ? 'jadwal' : 'kegiatan';
        }
    }
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('Admin Posyandu page could not load: ' . $exception->getMessage());
    $errorMessage = 'Data Posyandu tidak dapat dimuat saat ini.';
    $tableAvailable = false;
    $profile = null;
    $schedules = [];
    $activities = [];
}

$agendaTypes = [
    'jadwal' => 'Jadwal pelayanan',
    'kegiatan' => 'Kegiatan',
    'imunisasi' => 'Informasi imunisasi',
];

function admin_posyandu_render_agenda_form(string $section, ?array $record, array $types): void
{
    $isSchedule = $section === 'jadwal';
    $prefix = $isSchedule ? 'jadwal' : 'kegiatan';
    $defaultType = $isSchedule ? 'jadwal' : 'kegiatan';
    ?>
    <form method="POST" action="posyandu_action.php" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
        <input type="hidden" name="action" value="<?php echo $record ? 'update_agenda' : 'create_agenda'; ?>">
        <?php if ($record): ?><input type="hidden" name="id" value="<?php echo (int) $record['id']; ?>"><?php endif; ?>
        <?php if ($isSchedule): ?>
            <input type="hidden" name="jenis" value="jadwal">
        <?php else: ?>
            <div>
                <label for="<?php echo $prefix; ?>_jenis" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Jenis informasi *</label>
                <select id="<?php echo $prefix; ?>_jenis" name="jenis" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">
                    <?php foreach (['kegiatan', 'imunisasi'] as $type): ?>
                        <option value="<?php echo admin_module_escape($type); ?>" <?php echo ($record['jenis'] ?? $defaultType) === $type ? 'selected' : ''; ?>><?php echo admin_module_escape($types[$type]); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div>
            <label for="<?php echo $prefix; ?>_judul" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Judul *</label>
            <input id="<?php echo $prefix; ?>_judul" name="judul" maxlength="150" required value="<?php echo admin_module_escape((string) ($record['judul'] ?? '')); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
        </div>
        <div>
            <label for="<?php echo $prefix; ?>_tanggal" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Tanggal *</label>
            <input id="<?php echo $prefix; ?>_tanggal" name="tanggal" type="date" required value="<?php echo admin_module_escape((string) ($record['tanggal'] ?? date('Y-m-d'))); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="<?php echo $prefix; ?>_waktu_mulai" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Mulai</label>
                <input id="<?php echo $prefix; ?>_waktu_mulai" name="waktu_mulai" type="time" value="<?php echo admin_module_escape(isset($record['waktu_mulai']) ? substr((string) $record['waktu_mulai'], 0, 5) : ''); ?>" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm">
            </div>
            <div>
                <label for="<?php echo $prefix; ?>_waktu_selesai" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Selesai</label>
                <input id="<?php echo $prefix; ?>_waktu_selesai" name="waktu_selesai" type="time" value="<?php echo admin_module_escape(isset($record['waktu_selesai']) ? substr((string) $record['waktu_selesai'], 0, 5) : ''); ?>" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm">
            </div>
        </div>
        <div>
            <label for="<?php echo $prefix; ?>_lokasi" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Lokasi</label>
            <input id="<?php echo $prefix; ?>_lokasi" name="lokasi" maxlength="150" value="<?php echo admin_module_escape((string) ($record['lokasi'] ?? '')); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
        </div>
        <div>
            <label for="<?php echo $prefix; ?>_deskripsi" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Keterangan</label>
            <textarea id="<?php echo $prefix; ?>_deskripsi" name="deskripsi" maxlength="5000" rows="4" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><?php echo admin_module_escape((string) ($record['deskripsi'] ?? '')); ?></textarea>
        </div>
        <div>
            <label for="<?php echo $prefix; ?>_status" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Status publikasi *</label>
            <select id="<?php echo $prefix; ?>_status" name="status_publikasi" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">
                <option value="draft" <?php echo ($record['status_publikasi'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="publikasi" <?php echo ($record['status_publikasi'] ?? '') === 'publikasi' ? 'selected' : ''; ?>>Dipublikasikan</option>
            </select>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="rounded-xl bg-amber-500 px-5 py-3 text-xs font-extrabold text-slate-950 hover:bg-amber-400"><?php echo $record ? 'Simpan Perubahan' : ($isSchedule ? 'Tambah Jadwal' : 'Tambah Informasi'); ?></button>
            <?php if ($record): ?><a href="posyandu.php#<?php echo $section; ?>" class="rounded-xl border border-slate-200 px-4 py-3 text-xs font-extrabold text-slate-700">Batal</a><?php endif; ?>
        </div>
    </form>
    <?php
}

function admin_posyandu_render_agenda_list(array $records, string $section, array $types): void
{
    if ($records === []) {
        ?>
        <p class="rounded-xl bg-slate-50 p-5 text-sm text-slate-500">Belum ada data pada bagian ini.</p>
        <?php
        return;
    }
    ?>
    <div class="space-y-3">
        <?php foreach ($records as $item): ?>
            <article class="rounded-xl border border-slate-200 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-extrabold uppercase text-amber-800"><?php echo admin_module_escape($types[(string) $item['jenis']] ?? (string) $item['jenis']); ?></span>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold <?php echo $item['status_publikasi'] === 'publikasi' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-600'; ?>"><?php echo $item['status_publikasi'] === 'publikasi' ? 'Publikasi' : 'Draft'; ?></span>
                        </div>
                        <h4 class="mt-2 break-words font-extrabold text-slate-950"><?php echo admin_module_escape((string) $item['judul']); ?></h4>
                        <p class="mt-1 text-xs text-slate-500"><?php echo admin_module_escape((string) $item['tanggal']); ?><?php if (!empty($item['waktu_mulai'])): ?> · <?php echo admin_module_escape(substr((string) $item['waktu_mulai'], 0, 5)); ?><?php if (!empty($item['waktu_selesai'])): ?>–<?php echo admin_module_escape(substr((string) $item['waktu_selesai'], 0, 5)); ?><?php endif; ?><?php endif; ?></p>
                        <?php if (!empty($item['lokasi'])): ?><p class="mt-1 text-xs text-slate-500"><?php echo admin_module_escape((string) $item['lokasi']); ?></p><?php endif; ?>
                        <?php if (!empty($item['deskripsi'])): ?><p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600"><?php echo admin_module_escape((string) $item['deskripsi']); ?></p><?php endif; ?>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <a href="posyandu.php?edit_agenda=<?php echo (int) $item['id']; ?>#<?php echo $section; ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Edit</a>
                        <form method="POST" action="posyandu_action.php" onsubmit="return confirm('Hapus data ini?');">
                            <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
                            <input type="hidden" name="action" value="delete_agenda">
                            <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                            <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50">Hapus</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
}

admin_render_header('Pengelolaan Posyandu', $_SESSION['admin_username']);
?>
<div class="mb-7">
    <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Layanan warga</p>
    <h2 class="mt-1 text-2xl font-black text-slate-950">Pengelolaan Posyandu</h2>
    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Kelola profil, jadwal pelayanan, dan informasi kegiatan Posyandu. Jangan memasukkan rekam medis atau data kesehatan pribadi warga.</p>
    <p class="mt-2 text-xs text-slate-500">Dokumentasi foto dikelola melalui <a href="galeri.php?kategori=Posyandu" class="font-bold text-blue-900 underline hover:text-amber-700">Galeri Desa</a>.</p>
</div>

<?php if (is_array($flash) && isset($flash['message'])): ?>
    <div role="<?php echo ($flash['type'] ?? '') === 'success' ? 'status' : 'alert'; ?>" class="mb-5 rounded-xl border px-4 py-3 text-sm font-semibold <?php echo ($flash['type'] ?? '') === 'success' ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-red-200 bg-red-50 text-red-800'; ?>"><?php echo admin_module_escape((string) $flash['message']); ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div role="alert" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950"><?php echo admin_module_escape($errorMessage); ?></div>
<?php endif; ?>

<?php if (!$tableAvailable): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 text-sm leading-6 text-slate-600">
        Pengelolaan belum tersedia karena tabel Profil atau Agenda Posyandu belum tersedia.
    </section>
<?php else: ?>
    <section id="profil" class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <div class="mb-5">
            <p class="text-xs font-extrabold uppercase tracking-widest text-amber-700">Informasi Publik</p>
            <h3 class="mt-1 text-lg font-black text-slate-950">Kelola Profil Posyandu</h3>
        </div>
        <form method="POST" action="posyandu_action.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
            <input type="hidden" name="action" value="save_profile">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="nama_posyandu" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Nama Posyandu *</label>
                    <input id="nama_posyandu" name="nama_posyandu" maxlength="150" required value="<?php echo admin_module_escape((string) ($profile['nama_posyandu'] ?? '')); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none">
                </div>
                <div>
                    <label for="lokasi" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Lokasi / alamat layanan</label>
                    <input id="lokasi" name="lokasi" maxlength="255" value="<?php echo admin_module_escape((string) ($profile['lokasi'] ?? '')); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none">
                </div>
                <div class="md:col-span-2">
                    <label for="waktu_layanan" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Informasi waktu pelayanan</label>
                    <input id="waktu_layanan" name="waktu_layanan" maxlength="150" value="<?php echo admin_module_escape((string) ($profile['waktu_layanan'] ?? '')); ?>" placeholder="Contoh: Lihat jadwal pelayanan terbaru" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none">
                </div>
                <div class="md:col-span-2">
                    <label for="profil_deskripsi" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Profil / informasi</label>
                    <textarea id="profil_deskripsi" name="deskripsi" maxlength="5000" rows="5" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none"><?php echo admin_module_escape((string) ($profile['deskripsi'] ?? '')); ?></textarea>
                </div>
            </div>
            <button type="submit" class="rounded-xl bg-amber-500 px-5 py-3 text-xs font-extrabold text-slate-950 transition hover:bg-amber-400">Simpan Profil</button>
        </form>
        <?php if (is_array($profile)): ?>
            <form method="POST" action="posyandu_action.php" class="mt-4 border-t border-slate-100 pt-4" onsubmit="return confirm('Hapus profil Posyandu? Agenda tidak akan ikut terhapus.');">
                <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
                <input type="hidden" name="action" value="delete_profile">
                <button type="submit" class="rounded-xl border border-red-200 px-4 py-3 text-xs font-extrabold text-red-700 transition hover:bg-red-50">Hapus Profil</button>
            </form>
        <?php endif; ?>
    </section>

    <section id="jadwal" class="mb-8 grid items-start gap-6 xl:grid-cols-[.8fr_1.2fr]">
        <div class="xl:col-span-2">
            <p class="text-xs font-extrabold uppercase tracking-widest text-amber-700">Bagian Publik</p>
            <h3 class="mt-1 text-xl font-black text-slate-950">Kelola Jadwal Posyandu</h3>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="mb-5">
                <p class="text-xs font-extrabold uppercase tracking-widest text-amber-700">Jadwal Pelayanan</p>
                <h3 class="mt-1 text-lg font-black text-slate-950"><?php echo $agendaSection === 'jadwal' && $agendaRecord ? 'Edit Jadwal Posyandu' : 'Tambah Jadwal Posyandu'; ?></h3>
            </div>
            <?php admin_posyandu_render_agenda_form('jadwal', $agendaSection === 'jadwal' ? $agendaRecord : null, $agendaTypes); ?>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <h3 class="mb-5 text-lg font-black text-slate-950">Daftar Jadwal Posyandu</h3>
            <?php admin_posyandu_render_agenda_list($schedules, 'jadwal', $agendaTypes); ?>
        </div>
    </section>

    <section id="kegiatan" class="mb-8 grid items-start gap-6 xl:grid-cols-[.8fr_1.2fr]">
        <div class="xl:col-span-2">
            <p class="text-xs font-extrabold uppercase tracking-widest text-amber-700">Bagian Publik</p>
            <h3 class="mt-1 text-xl font-black text-slate-950">Kelola Informasi Kegiatan</h3>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="mb-5">
                <p class="text-xs font-extrabold uppercase tracking-widest text-amber-700">Informasi Publik</p>
                <h3 class="mt-1 text-lg font-black text-slate-950"><?php echo $agendaSection === 'kegiatan' && $agendaRecord ? 'Edit Informasi Kegiatan' : 'Tambah Informasi Kegiatan'; ?></h3>
            </div>
            <?php admin_posyandu_render_agenda_form('kegiatan', $agendaSection === 'kegiatan' ? $agendaRecord : null, $agendaTypes); ?>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <h3 class="mb-5 text-lg font-black text-slate-950">Daftar Informasi Kegiatan</h3>
            <?php admin_posyandu_render_agenda_list($activities, 'kegiatan', $agendaTypes); ?>
        </div>
    </section>
<?php endif; ?>
<?php admin_render_footer(); ?>
