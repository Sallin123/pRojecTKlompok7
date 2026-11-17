<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/../profil_storage.php';
require_once __DIR__ . '/layout.php';

function admin_profile_redirect(): void
{
    header('Location: profil.php');
    exit;
}

function admin_profile_upload(): ?string
{
    if (!isset($_FILES['foto_kepala_desa']) || !is_array($_FILES['foto_kepala_desa'])) {
        return null;
    }

    $file = $_FILES['foto_kepala_desa'];
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($error)) {
        throw new InvalidArgumentException('Status unggahan foto tidak valid.');
    }
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        $message = $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
            ? 'Ukuran foto melebihi batas unggah.'
            : 'Foto gagal diunggah. Pilih file lain dan coba kembali.';
        throw new InvalidArgumentException($message);
    }

    $temporaryPath = $file['tmp_name'] ?? '';
    $originalName = $file['name'] ?? '';
    if (!is_string($temporaryPath) || !is_string($originalName) || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('File unggahan tidak valid.');
    }

    $size = filesize($temporaryPath);
    if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran foto maksimal 5 MB.');
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $extensions = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'];
    $mimeTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    if (!isset($extensions[$extension])) {
        throw new InvalidArgumentException('Format foto yang diizinkan hanya JPEG, PNG, dan WebP.');
    }

    $storedExtension = $extensions[$extension];
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $imageInfo = @getimagesize($temporaryPath);
    if ($mimeType !== $mimeTypes[$storedExtension]
        || !is_array($imageInfo)
        || ($imageInfo['mime'] ?? '') !== $mimeTypes[$storedExtension]) {
        throw new InvalidArgumentException('Isi file tidak sesuai dengan format gambar yang diizinkan.');
    }
    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);
    if ($width < 1 || $height < 1 || $width * $height > 40000000) {
        throw new InvalidArgumentException('Dimensi foto tidak valid atau terlalu besar.');
    }

    $directory = profil_storage_ensure_directory();
    $fileName = bin2hex(random_bytes(16)) . '.' . $storedExtension;
    if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $fileName)) {
        throw new RuntimeException('Foto Profil gagal disimpan.');
    }

    return $fileName;
}

$flash = admin_module_take_flash('profil_flash');
$oldInput = admin_module_take_old_input('profil_old_input');
$record = null;
$errorMessage = '';

try {
    if (admin_module_missing_tables($koneksi, ['profil_desa']) !== []) {
        $errorMessage = 'Tabel Profil Desa belum tersedia.';
    }
} catch (Throwable $exception) {
    error_log('Admin Profil schema check failed: ' . $exception->getMessage());
    $errorMessage = 'Struktur Profil Desa tidak dapat diperiksa saat ini.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($errorMessage !== '') {
        admin_module_flash('profil_flash', 'error', $errorMessage);
        admin_profile_redirect();
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(admin_csrf_token(), $token)) {
        admin_module_flash('profil_flash', 'error', 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
        admin_profile_redirect();
    }

    $storedNewFile = null;
    try {
        $fields = [
            'visi' => 3000,
            'misi' => 5000,
            'sejarah' => 10000,
            'nama_kepala_desa' => 150,
            'sambutan' => 5000,
        ];
        $values = [];
        foreach ($fields as $field => $limit) {
            $value = $_POST[$field] ?? '';
            if (!is_string($value)) {
                throw new InvalidArgumentException('Data formulir Profil Desa tidak valid.');
            }
            $value = trim($value);
            if ($value === '' || mb_strlen($value, 'UTF-8') > $limit) {
                throw new InvalidArgumentException('Semua informasi wajib diisi dan tidak boleh melebihi batas karakter.');
            }
            $values[$field] = $value;
        }

        $current = admin_module_execute($koneksi, 'SELECT foto_kepala_desa FROM profil_desa WHERE id = 1');
        $currentRecord = $current->get_result()->fetch_assoc();
        $current->close();
        if (!$currentRecord) {
            throw new RuntimeException('Data awal Profil Desa tidak ditemukan.');
        }
        $oldFile = is_string($currentRecord['foto_kepala_desa']) ? $currentRecord['foto_kepala_desa'] : null;
        $storedNewFile = admin_profile_upload();
        $photoFile = $storedNewFile ?? $oldFile;

        $statement = admin_module_execute(
            $koneksi,
            'UPDATE profil_desa SET visi = ?, misi = ?, sejarah = ?, nama_kepala_desa = ?, sambutan = ?, foto_kepala_desa = ? WHERE id = 1',
            'ssssss',
            [
                $values['visi'],
                $values['misi'],
                $values['sejarah'],
                $values['nama_kepala_desa'],
                $values['sambutan'],
                $photoFile,
            ]
        );
        $statement->close();

        if ($storedNewFile !== null) {
            $storedNewFile = null;
            if ($oldFile !== null && $oldFile !== $photoFile) {
                try {
                    if (!profil_storage_delete_file($oldFile)) {
                        throw new RuntimeException('Foto Profil lama tidak dapat dibersihkan.');
                    }
                } catch (Throwable $exception) {
                    error_log('Replaced Profil photo cleanup failed: ' . $exception->getMessage());
                    admin_module_flash('profil_flash', 'error', 'Profil diperbarui, tetapi foto lama perlu dibersihkan oleh administrator.');
                    admin_profile_redirect();
                }
            }
        }

        admin_module_flash('profil_flash', 'success', 'Profil Desa berhasil diperbarui.');
        admin_profile_redirect();
    } catch (InvalidArgumentException $exception) {
        if ($storedNewFile !== null) {
            try {
                profil_storage_delete_file($storedNewFile);
            } catch (Throwable $cleanupException) {
                error_log('Temporary Profil photo cleanup failed: ' . $cleanupException->getMessage());
            }
        }
        admin_module_store_old_input('profil_old_input', $_POST, [
            'visi' => 3000,
            'misi' => 5000,
            'sejarah' => 10000,
            'nama_kepala_desa' => 150,
            'sambutan' => 5000,
        ]);
        admin_module_flash('profil_flash', 'error', $exception->getMessage());
        admin_profile_redirect();
    } catch (Throwable $exception) {
        if ($storedNewFile !== null) {
            try {
                profil_storage_delete_file($storedNewFile);
            } catch (Throwable $cleanupException) {
                error_log('Temporary Profil photo cleanup failed: ' . $cleanupException->getMessage());
            }
        }
        error_log('Admin Profil update failed: ' . $exception->getMessage());
        admin_module_store_old_input('profil_old_input', $_POST, [
            'visi' => 3000,
            'misi' => 5000,
            'sejarah' => 10000,
            'nama_kepala_desa' => 150,
            'sambutan' => 5000,
        ]);
        admin_module_flash('profil_flash', 'error', 'Perubahan Profil Desa gagal disimpan. Silakan coba kembali.');
        admin_profile_redirect();
    }
}

try {
    $statement = admin_module_execute(
        $koneksi,
        'SELECT visi, misi, sejarah, nama_kepala_desa, sambutan, foto_kepala_desa, updated_at FROM profil_desa WHERE id = 1'
    );
    $record = $statement->get_result()->fetch_assoc();
    $statement->close();
    if (!$record) {
        $errorMessage = 'Data awal Profil Desa tidak ditemukan.';
    }
} catch (Throwable $exception) {
    error_log('Admin Profil query failed: ' . $exception->getMessage());
    $errorMessage = 'Data Profil Desa tidak dapat dimuat saat ini.';
}

$perangkatFlash = admin_module_take_flash('perangkat_flash');
$perangkatOldInput = admin_module_take_old_input('perangkat_old_input');
$perangkatTableAvailable = false;
$perangkatErrorMessage = '';
$perangkatRows = [];
$perangkatRecord = null;
$perangkatFormMode = '';
$perangkatId = null;
$perangkatSearch = '';
$perangkatStatus = 'aktif';
$perangkatPage = 1;
$perangkatPageSize = 10;
$perangkatTotalRows = 0;
$perangkatTotalPages = 1;

try {
    $perangkatTableAvailable = admin_module_missing_tables($koneksi, ['perangkat_desa']) === [];
    if (!$perangkatTableAvailable) {
        $perangkatErrorMessage = 'Tabel perangkat_desa belum tersedia. Struktur organisasi belum dapat dikelola.';
    }
} catch (Throwable $exception) {
    error_log('Admin Profil perangkat schema check failed: ' . $exception->getMessage());
    $perangkatErrorMessage = 'Struktur data perangkat desa tidak dapat diperiksa saat ini.';
}

$rawPerangkatSearch = $_GET['perangkat_q'] ?? '';
if (is_string($rawPerangkatSearch)) {
    $perangkatSearch = trim($rawPerangkatSearch);
    if (mb_strlen($perangkatSearch, 'UTF-8') > 120) {
        $perangkatSearch = mb_substr($perangkatSearch, 0, 120, 'UTF-8');
    }
}
$rawPerangkatStatus = $_GET['perangkat_status'] ?? 'aktif';
if (is_string($rawPerangkatStatus) && in_array($rawPerangkatStatus, ['aktif', 'arsip', 'semua'], true)) {
    $perangkatStatus = $rawPerangkatStatus;
}
$rawPerangkatPage = $_GET['perangkat_page'] ?? '1';
if (is_string($rawPerangkatPage) && ctype_digit($rawPerangkatPage)) {
    $validatedPerangkatPage = filter_var($rawPerangkatPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($validatedPerangkatPage !== false) {
        $perangkatPage = $validatedPerangkatPage;
    }
}
$rawPerangkatId = $_GET['perangkat_id'] ?? '';
if (is_string($rawPerangkatId) && ctype_digit($rawPerangkatId) && (int) $rawPerangkatId > 0) {
    $perangkatId = (int) $rawPerangkatId;
}
$rawPerangkatForm = $_GET['perangkat_form'] ?? '';
if (is_string($rawPerangkatForm) && in_array($rawPerangkatForm, ['tambah', 'edit'], true)) {
    $perangkatFormMode = $rawPerangkatForm;
}
if ($perangkatFormMode === 'edit' && $perangkatId === null) {
    $perangkatFormMode = '';
    $perangkatErrorMessage = 'Pilih data perangkat yang valid untuk diedit.';
}

if ($perangkatTableAvailable) {
    try {
        if ($perangkatFormMode === 'edit' && $perangkatId !== null) {
            $perangkatStatement = admin_module_execute(
                $koneksi,
                'SELECT id, nama_lengkap, jabatan, informasi, foto, urutan, status_data FROM perangkat_desa WHERE id = ?',
                'i',
                [$perangkatId]
            );
            $perangkatResult = $perangkatStatement->get_result();
            $perangkatRecord = $perangkatResult ? $perangkatResult->fetch_assoc() : null;
            $perangkatStatement->close();
            if ($perangkatRecord === null) {
                $perangkatFormMode = '';
                $perangkatErrorMessage = 'Data perangkat desa tidak ditemukan.';
            }
        }

        if ($perangkatFormMode === '') {
            $conditions = [];
            $types = '';
            $values = [];
            if ($perangkatSearch !== '') {
                $conditions[] = '(nama_lengkap LIKE ? OR jabatan LIKE ?)';
                $types .= 'ss';
                $values[] = '%' . $perangkatSearch . '%';
                $values[] = '%' . $perangkatSearch . '%';
            }
            if ($perangkatStatus !== 'semua') {
                $conditions[] = 'status_data = ?';
                $types .= 's';
                $values[] = $perangkatStatus;
            }
            $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
            $countStatement = admin_module_execute(
                $koneksi,
                'SELECT COUNT(*) FROM perangkat_desa' . $where,
                $types,
                $values
            );
            $countStatement->bind_result($perangkatTotalRows);
            $countStatement->fetch();
            $countStatement->close();
            $perangkatTotalRows = (int) $perangkatTotalRows;
            $perangkatTotalPages = max(1, (int) ceil($perangkatTotalRows / $perangkatPageSize));
            $perangkatPage = min($perangkatPage, $perangkatTotalPages);
            $offset = ($perangkatPage - 1) * $perangkatPageSize;
            $listStatement = admin_module_execute(
                $koneksi,
                'SELECT id, nama_lengkap, jabatan, informasi, foto, urutan, status_data FROM perangkat_desa' . $where . ' ORDER BY urutan, nama_lengkap, id LIMIT ? OFFSET ?',
                $types . 'ii',
                array_merge($values, [$perangkatPageSize, $offset])
            );
            $listResult = $listStatement->get_result();
            if ($listResult) {
                while ($perangkatRow = $listResult->fetch_assoc()) {
                    $perangkatRows[] = $perangkatRow;
                }
            }
            $listStatement->close();
        }
    } catch (Throwable $exception) {
        error_log('Admin Profil perangkat query failed: ' . $exception->getMessage());
        $perangkatErrorMessage = 'Data perangkat desa tidak dapat dimuat saat ini.';
        $perangkatRows = [];
        $perangkatRecord = null;
    }
}

admin_render_header('Profil Desa', $_SESSION['admin_username']);
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Konten Website Publik</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">Profil Desa</h2>
    </div>
    <?php if (is_array($record) && !empty($record['updated_at'])): ?>
        <span class="text-xs font-semibold text-slate-500">Diperbarui <?php echo admin_module_escape((string) $record['updated_at']); ?></span>
    <?php endif; ?>
</div>

<?php if (is_array($flash) && isset($flash['message'])): ?>
    <div role="<?php echo ($flash['type'] ?? '') === 'success' ? 'status' : 'alert'; ?>" class="mb-5 rounded-xl border px-4 py-3 text-sm font-semibold <?php echo ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>"><?php echo admin_module_escape((string) $flash['message']); ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div role="alert" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900"><?php echo admin_module_escape($errorMessage); ?></div>
<?php endif; ?>

<?php if (is_array($record)): ?>
    <?php
    $profileValues = [];
    foreach (['visi', 'misi', 'sejarah', 'nama_kepala_desa', 'sambutan'] as $field) {
        $profileValues[$field] = (string) ($oldInput[$field] ?? $record[$field] ?? '');
    }
    ?>
    <form method="POST" action="profil.php" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
        <section class="mb-6 grid gap-5 md:grid-cols-2">
            <article class="rounded-2xl border border-amber-100 bg-white p-6 shadow-sm">
                <label for="visi" class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Visi Desa</label>
                <h3 class="mt-3 text-lg font-black text-slate-950">Pedoman Pembangunan Desa</h3>
                <textarea id="visi" name="visi" maxlength="3000" required rows="5" class="mt-3 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm leading-7 text-slate-700"><?php echo admin_module_escape($profileValues['visi']); ?></textarea>
            </article>
            <article class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
                <label for="misi" class="text-xs font-extrabold uppercase tracking-widest text-blue-700">Misi Desa</label>
                <p class="mt-3 text-sm text-slate-500">Tulis satu butir misi pada setiap baris.</p>
                <textarea id="misi" name="misi" maxlength="5000" required rows="7" class="mt-3 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm leading-6 text-slate-700"><?php echo admin_module_escape($profileValues['misi']); ?></textarea>
            </article>
        </section>

        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <label for="sejarah" class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Sejarah Desa</label>
            <h3 class="mt-2 text-lg font-black text-slate-950">Sekilas Tentang Desa Kami</h3>
            <textarea id="sejarah" name="sejarah" maxlength="10000" required rows="6" class="mt-3 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm leading-7 text-slate-700"><?php echo admin_module_escape($profileValues['sejarah']); ?></textarea>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Kepala Desa</p>
            <div class="mt-4 grid gap-5 md:grid-cols-2">
                <div>
                    <label for="nama_kepala_desa" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Nama Kepala Desa</label>
                    <input id="nama_kepala_desa" name="nama_kepala_desa" maxlength="150" required value="<?php echo admin_module_escape($profileValues['nama_kepala_desa']); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                </div>
                <div>
                    <label for="foto_kepala_desa" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Ganti foto (opsional)</label>
                    <input id="foto_kepala_desa" name="foto_kepala_desa" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">
                    <p class="mt-2 text-xs text-slate-500">JPEG, PNG, atau WebP; maksimal 5 MB.</p>
                    <img src="<?php echo !empty($record['foto_kepala_desa']) ? '../profil_image.php' : '../Kepala-Desa.jpg'; ?>" alt="Foto Kepala Desa saat ini" class="mt-4 max-h-64 rounded-xl border border-slate-200 object-contain">
                </div>
            </div>
            <div class="mt-5">
                <label for="sambutan" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Sambutan Kepala Desa</label>
                <textarea id="sambutan" name="sambutan" maxlength="5000" required rows="5" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm leading-7 text-slate-700"><?php echo admin_module_escape($profileValues['sambutan']); ?></textarea>
            </div>
        </section>

        <div class="flex justify-end border-t border-slate-200 pt-5">
            <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-extrabold text-white hover:bg-slate-800">Simpan Profil Desa</button>
        </div>
    </form>
<?php endif; ?>

<section id="struktur" class="mt-10 scroll-mt-6">
    <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Pengelolaan Aparatur</p>
            <h2 class="mt-1 text-2xl font-black text-slate-950">Struktur Organisasi Desa</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-600">Kelola perangkat desa, foto, jabatan, informasi, urutan, dan status aktif atau arsip.</p>
        </div>
        <?php if ($perangkatTableAvailable && $perangkatFormMode === ''): ?>
            <a href="profil.php?section=struktur&amp;perangkat_form=tambah#struktur" class="rounded-xl bg-amber-500 px-4 py-3 text-center text-xs font-extrabold text-slate-950 hover:bg-amber-400">Tambah Perangkat</a>
        <?php elseif ($perangkatFormMode !== ''): ?>
            <a href="profil.php?section=struktur#struktur" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-center text-xs font-extrabold text-slate-700">Kembali ke daftar</a>
        <?php endif; ?>
    </div>

    <?php if (is_array($perangkatFlash) && isset($perangkatFlash['message'])): ?>
        <div role="<?php echo ($perangkatFlash['type'] ?? '') === 'success' ? 'status' : 'alert'; ?>" class="mb-5 rounded-xl border px-4 py-3 text-sm font-semibold <?php echo ($perangkatFlash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>"><?php echo admin_module_escape((string) $perangkatFlash['message']); ?></div>
    <?php endif; ?>
    <?php if ($perangkatErrorMessage !== ''): ?>
        <div role="alert" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900"><?php echo admin_module_escape($perangkatErrorMessage); ?></div>
    <?php endif; ?>

    <?php if ($perangkatTableAvailable && ($perangkatFormMode === 'tambah' || $perangkatFormMode === 'edit')): ?>
        <?php
        $perangkatValues = [];
        foreach (['nama_lengkap', 'jabatan', 'informasi', 'urutan'] as $field) {
            $perangkatValues[$field] = (string) ($perangkatOldInput[$field] ?? $perangkatRecord[$field] ?? ($field === 'urutan' ? '0' : ''));
        }
        ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <h3 class="mb-5 text-lg font-black text-slate-950"><?php echo $perangkatFormMode === 'tambah' ? 'Tambah Perangkat Desa' : 'Edit Perangkat Desa'; ?></h3>
            <form method="POST" action="perangkat_action.php" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
                <input type="hidden" name="action" value="<?php echo $perangkatFormMode === 'tambah' ? 'create' : 'update'; ?>">
                <?php if ($perangkatFormMode === 'edit'): ?><input type="hidden" name="id" value="<?php echo (int) $perangkatRecord['id']; ?>"><?php endif; ?>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="perangkat_nama_lengkap" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Nama lengkap *</label>
                        <input id="perangkat_nama_lengkap" name="nama_lengkap" maxlength="120" required value="<?php echo admin_module_escape($perangkatValues['nama_lengkap']); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                    </div>
                    <div>
                        <label for="perangkat_jabatan" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Jabatan *</label>
                        <input id="perangkat_jabatan" name="jabatan" maxlength="120" required value="<?php echo admin_module_escape($perangkatValues['jabatan']); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                    </div>
                </div>
                <div>
                    <label for="perangkat_informasi" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Informasi tambahan</label>
                    <textarea id="perangkat_informasi" name="informasi" maxlength="2000" rows="4" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><?php echo admin_module_escape($perangkatValues['informasi']); ?></textarea>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="perangkat_urutan" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Urutan tampilan</label>
                        <input id="perangkat_urutan" name="urutan" type="number" min="0" max="65535" value="<?php echo admin_module_escape($perangkatValues['urutan']); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                    </div>
                    <div>
                        <label for="perangkat_foto" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Foto perangkat (opsional)</label>
                        <input id="perangkat_foto" name="foto" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">
                        <p class="mt-2 text-xs text-slate-500">JPEG, PNG, atau WebP; maksimal 5 MB. Kosongkan saat edit untuk mempertahankan foto saat ini.</p>
                        <?php if ($perangkatFormMode === 'edit' && !empty($perangkatRecord['foto'])): ?>
                            <img src="perangkat_image.php?id=<?php echo (int) $perangkatRecord['id']; ?>" alt="Foto <?php echo admin_module_escape((string) $perangkatRecord['nama_lengkap']); ?>" class="mt-3 max-h-48 rounded-xl border border-slate-200 object-contain">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                    <a href="profil.php?section=struktur#struktur" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-xs font-extrabold">Batal</a>
                    <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-extrabold text-white">Simpan Perangkat</button>
                </div>
            </form>
        </div>
    <?php elseif ($perangkatTableAvailable): ?>
        <section class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" action="profil.php?section=struktur#struktur" class="grid gap-3 md:grid-cols-[1fr_12rem_auto]">
                <input name="perangkat_q" maxlength="120" value="<?php echo admin_module_escape($perangkatSearch); ?>" placeholder="Cari nama atau jabatan..." class="rounded-xl border border-slate-300 px-4 py-3 text-sm">
                <select name="perangkat_status" class="rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm">
                    <option value="aktif" <?php echo $perangkatStatus === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="arsip" <?php echo $perangkatStatus === 'arsip' ? 'selected' : ''; ?>>Arsip</option>
                    <option value="semua" <?php echo $perangkatStatus === 'semua' ? 'selected' : ''; ?>>Semua</option>
                </select>
                <button class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-extrabold text-white">Cari / Filter</button>
            </form>
        </section>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <?php if ($perangkatRows === []): ?>
                <div class="px-5 py-12 text-center">
                    <p class="font-extrabold text-slate-800">Belum ada data perangkat pada filter ini.</p>
                    <p class="mt-2 text-sm text-slate-500">Tambahkan perangkat atau ubah filter pencarian.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[64rem] text-left text-sm">
                        <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                            <tr><th class="px-5 py-4">Foto</th><th class="px-5 py-4">Nama</th><th class="px-5 py-4">Jabatan</th><th class="px-5 py-4">Informasi</th><th class="px-5 py-4">Urutan</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Aksi</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($perangkatRows as $perangkatRow): ?>
                                <tr>
                                    <td class="px-5 py-3">
                                        <?php if (!empty($perangkatRow['foto'])): ?>
                                            <img src="perangkat_image.php?id=<?php echo (int) $perangkatRow['id']; ?>" alt="" class="h-12 w-12 rounded-lg border border-slate-200 object-cover">
                                        <?php else: ?>
                                            <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100 text-xs text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-4 font-bold"><?php echo admin_module_escape((string) $perangkatRow['nama_lengkap']); ?></td>
                                    <td class="px-5 py-4"><?php echo admin_module_escape((string) $perangkatRow['jabatan']); ?></td>
                                    <td class="max-w-xs px-5 py-4 whitespace-pre-line"><?php echo admin_module_escape((string) ($perangkatRow['informasi'] ?? '-')); ?></td>
                                    <td class="px-5 py-4"><?php echo (int) $perangkatRow['urutan']; ?></td>
                                    <td class="px-5 py-4"><?php echo admin_module_escape((string) $perangkatRow['status_data']); ?></td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="flex flex-wrap justify-end gap-3">
                                            <a class="font-bold text-amber-700" href="profil.php?section=struktur&amp;perangkat_form=edit&amp;perangkat_id=<?php echo (int) $perangkatRow['id']; ?>#struktur">Edit</a>
                                            <form method="POST" action="perangkat_action.php" onsubmit="return confirm('Data hanya diarsipkan atau dipulihkan, tidak dihapus permanen. Lanjutkan?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int) $perangkatRow['id']; ?>">
                                                <input type="hidden" name="action" value="<?php echo $perangkatRow['status_data'] === 'aktif' ? 'archive' : 'restore'; ?>">
                                                <button class="font-bold text-slate-700 hover:text-slate-950"><?php echo $perangkatRow['status_data'] === 'aktif' ? 'Arsipkan' : 'Pulihkan'; ?></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col gap-3 border-t px-5 py-4 text-xs text-slate-500 sm:flex-row sm:justify-between">
                    <p><?php echo count($perangkatRows); ?> dari <?php echo $perangkatTotalRows; ?> · Halaman <?php echo $perangkatPage; ?> dari <?php echo $perangkatTotalPages; ?></p>
                    <?php if ($perangkatTotalPages > 1): ?>
                        <nav aria-label="Pagination" class="flex gap-2">
                            <?php $perangkatPageParams = ['perangkat_q' => $perangkatSearch, 'perangkat_status' => $perangkatStatus]; ?>
                            <?php if ($perangkatPage > 1): ?>
                                <a class="rounded-lg border px-3 py-2" href="profil.php?<?php echo admin_module_escape(http_build_query(['section' => 'struktur'] + $perangkatPageParams + ['perangkat_page' => $perangkatPage - 1])); ?>#struktur">Sebelumnya</a>
                            <?php endif; ?>
                            <?php if ($perangkatPage < $perangkatTotalPages): ?>
                                <a class="rounded-lg border px-3 py-2" href="profil.php?<?php echo admin_module_escape(http_build_query(['section' => 'struktur'] + $perangkatPageParams + ['perangkat_page' => $perangkatPage + 1])); ?>#struktur">Berikutnya</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
<?php admin_render_footer(); ?>
