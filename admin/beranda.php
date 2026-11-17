<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/../beranda_banner_storage.php';

function admin_beranda_redirect(): void
{
    header('Location: beranda.php');
    exit;
}

function admin_beranda_upload(): ?string
{
    if (!isset($_FILES['gambar']) || !is_array($_FILES['gambar'])) {
        return null;
    }

    $file = $_FILES['gambar'];
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($error) || $error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
            ? 'Ukuran gambar melebihi batas unggah.'
            : 'Gambar gagal diunggah. Pilih file lain dan coba kembali.');
    }

    $temporaryPath = $file['tmp_name'] ?? '';
    $originalName = $file['name'] ?? '';
    if (!is_string($temporaryPath) || !is_string($originalName) || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('File gambar tidak valid.');
    }

    $size = filesize($temporaryPath);
    if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran gambar maksimal 5 MB.');
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $extensions = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'];
    $mimeTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    if (!isset($extensions[$extension])) {
        throw new InvalidArgumentException('Format gambar yang diizinkan hanya JPEG, PNG, dan WebP.');
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
        throw new InvalidArgumentException('Dimensi gambar tidak valid atau terlalu besar.');
    }

    $directory = beranda_banner_storage_ensure_directory();
    $fileName = bin2hex(random_bytes(16)) . '.' . $storedExtension;
    if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $fileName)) {
        throw new RuntimeException('Gambar Banner Beranda gagal disimpan.');
    }

    return $fileName;
}

$flash = admin_module_take_flash('beranda_banner_flash');
$oldInput = admin_module_take_old_input('beranda_banner_old_input');
$tableAvailable = false;
$record = null;
$errorMessage = '';

try {
    $tableAvailable = admin_module_missing_tables($koneksi, ['beranda_banner']) === [];
    if (!$tableAvailable) {
        $errorMessage = 'Tabel Banner Utama belum tersedia. Jalankan SQL migrasi untuk mengaktifkan pengelolaan.';
    }
} catch (Throwable $exception) {
    error_log('Admin Beranda banner schema check failed: ' . $exception->getMessage());
    $errorMessage = 'Struktur Banner Utama tidak dapat diperiksa saat ini.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$tableAvailable) {
        admin_module_flash('beranda_banner_flash', 'error', $errorMessage);
        admin_beranda_redirect();
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(admin_csrf_token(), $token)) {
        admin_module_flash('beranda_banner_flash', 'error', 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
        admin_beranda_redirect();
    }

    $storedNewFile = null;
    try {
        $title = $_POST['judul'] ?? '';
        $description = $_POST['deskripsi'] ?? '';
        if (!is_string($title) || !is_string($description)) {
            throw new InvalidArgumentException('Data formulir Banner Utama tidak valid.');
        }
        $title = trim($title);
        $description = trim($description);
        if ($title === '' || mb_strlen($title, 'UTF-8') > 150
            || $description === '' || mb_strlen($description, 'UTF-8') > 5000) {
            throw new InvalidArgumentException('Judul wajib diisi (maksimal 150 karakter) dan deskripsi wajib diisi (maksimal 5.000 karakter).');
        }

        $current = admin_module_execute(
            $koneksi,
            'SELECT nama_file FROM beranda_banner WHERE id = 1'
        );
        $currentRecord = $current->get_result()->fetch_assoc();
        $current->close();
        if (!$currentRecord) {
            throw new RuntimeException('Data awal Banner Utama tidak ditemukan. Jalankan SQL migrasi kembali.');
        }

        $oldFile = is_string($currentRecord['nama_file']) ? $currentRecord['nama_file'] : null;
        $storedNewFile = admin_beranda_upload();
        $imageFile = $storedNewFile ?? $oldFile;
        $update = admin_module_execute(
            $koneksi,
            'UPDATE beranda_banner SET judul = ?, deskripsi = ?, nama_file = ? WHERE id = 1',
            'sss',
            [$title, $description, $imageFile]
        );
        $update->close();

        if ($storedNewFile !== null) {
            $storedNewFile = null;
            if ($oldFile !== null && $oldFile !== $imageFile) {
                try {
                    if (!beranda_banner_storage_delete_file($oldFile)) {
                        throw new RuntimeException('Gambar Banner Utama lama tidak dapat dibersihkan.');
                    }
                } catch (Throwable $exception) {
                    error_log('Replaced Home banner image cleanup failed: ' . $exception->getMessage());
                    admin_module_flash('beranda_banner_flash', 'error', 'Banner diperbarui, tetapi gambar lama perlu dibersihkan administrator.');
                    admin_beranda_redirect();
                }
            }
        }

        admin_module_flash('beranda_banner_flash', 'success', 'Banner Utama berhasil diperbarui.');
        admin_beranda_redirect();
    } catch (InvalidArgumentException $exception) {
        if ($storedNewFile !== null) {
            try {
                beranda_banner_storage_delete_file($storedNewFile);
            } catch (Throwable $cleanupException) {
                error_log('Temporary Home banner image cleanup failed: ' . $cleanupException->getMessage());
            }
        }
        admin_module_store_old_input('beranda_banner_old_input', $_POST, ['judul' => 150, 'deskripsi' => 5000]);
        admin_module_flash('beranda_banner_flash', 'error', $exception->getMessage());
        admin_beranda_redirect();
    } catch (Throwable $exception) {
        if ($storedNewFile !== null) {
            try {
                beranda_banner_storage_delete_file($storedNewFile);
            } catch (Throwable $cleanupException) {
                error_log('Temporary Home banner image cleanup failed: ' . $cleanupException->getMessage());
            }
        }
        error_log('Admin Home banner update failed: ' . $exception->getMessage());
        admin_module_store_old_input('beranda_banner_old_input', $_POST, ['judul' => 150, 'deskripsi' => 5000]);
        admin_module_flash('beranda_banner_flash', 'error', 'Perubahan Banner Utama gagal disimpan. Silakan coba kembali.');
        admin_beranda_redirect();
    }
}

if ($tableAvailable) {
    try {
        $query = admin_module_execute($koneksi, 'SELECT judul, deskripsi, nama_file, updated_at FROM beranda_banner WHERE id = 1');
        $record = $query->get_result()->fetch_assoc();
        $query->close();
        if (!$record) {
            $errorMessage = 'Data Banner Utama tidak ditemukan. Periksa isi tabel beranda_banner.';
        }
    } catch (Throwable $exception) {
        error_log('Admin Home banner query failed: ' . $exception->getMessage());
        $errorMessage = 'Data Banner Utama tidak dapat dimuat saat ini.';
    }
}

require_once __DIR__ . '/layout.php';
admin_render_header('Banner Utama Beranda', $_SESSION['admin_username']);
?>
<div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
        <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Konten Website Publik</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">Banner Utama Beranda</h2>
        <p class="mt-1 text-sm text-slate-500">Ubah gambar, judul utama, dan deskripsi pada hero halaman beranda.</p>
    </div>
    <a href="../index.php" target="_blank" rel="noopener" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-center text-xs font-extrabold text-slate-700 hover:bg-slate-50">Lihat Beranda</a>
</div>

<?php if (is_array($flash) && isset($flash['message'])): ?>
    <div role="<?php echo ($flash['type'] ?? '') === 'success' ? 'status' : 'alert'; ?>" class="mb-5 rounded-xl border px-4 py-3 text-sm font-semibold <?php echo ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>"><?php echo admin_module_escape((string) $flash['message']); ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div role="alert" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900"><?php echo admin_module_escape($errorMessage); ?></div>
<?php endif; ?>

<?php if (!$tableAvailable): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 text-sm leading-6 text-slate-600">
        <p>Banner Utama belum dapat dikelola karena tabel penyimpanan belum tersedia.</p>
        <p class="mt-2">Migrasi tersedia di <code>database/beranda_banner.sql</code>. Jalankan satu kali pada database <code>db_desa_pemulihan</code>.</p>
    </section>
<?php elseif (is_array($record)): ?>
    <?php
    $formTitle = (string) ($oldInput['judul'] ?? $record['judul']);
    $formDescription = (string) ($oldInput['deskripsi'] ?? $record['deskripsi']);
    $previewSource = !empty($record['nama_file']) ? '../beranda_banner_image.php' : '../desa-pemayahan.jpg';
    ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <form method="POST" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
            <div>
                <label for="judul" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Judul utama *</label>
                <input id="judul" name="judul" maxlength="150" required value="<?php echo admin_module_escape($formTitle); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
            </div>
            <div>
                <label for="deskripsi" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Deskripsi banner *</label>
                <textarea id="deskripsi" name="deskripsi" maxlength="5000" rows="4" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15"><?php echo admin_module_escape($formDescription); ?></textarea>
            </div>
            <div>
                <label for="gambar" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Gambar banner (opsional)</label>
                <input id="gambar" name="gambar" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-amber-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-amber-950">
                <p class="mt-2 text-xs text-slate-500">JPEG, PNG, atau WebP; maksimal 5 MB. Kosongkan bila gambar tidak ingin diganti.</p>
                <img src="<?php echo admin_module_escape($previewSource); ?>" alt="Pratinjau gambar Banner Utama" class="mt-4 h-48 w-full rounded-xl border border-slate-200 bg-slate-100 object-cover sm:h-64">
            </div>
            <p class="text-xs text-slate-500">Terakhir diperbarui: <?php echo admin_module_escape((string) $record['updated_at']); ?></p>
            <div class="flex justify-end border-t border-slate-100 pt-5">
                <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-extrabold text-white hover:bg-slate-800">Simpan Banner</button>
            </div>
        </form>
    </section>
<?php endif; ?>
<?php admin_render_footer(); ?>