<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/../berita_storage.php';
require_once __DIR__ . '/layout.php';

function admin_news_redirect(string $form = '', ?int $id = null): void
{
    $parameters = [];
    if ($form !== '') {
        $parameters['form'] = $form;
    }
    if ($id !== null) {
        $parameters['id'] = $id;
    }

    header('Location: berita.php' . ($parameters === [] ? '' : '?' . http_build_query($parameters)));
    exit;
}

function admin_news_date(string $date): string
{
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
        return $date;
    }

    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    return (int) $dateObject->format('j') . ' ' . $months[(int) $dateObject->format('n')] . ' ' . $dateObject->format('Y');
}

function admin_news_upload(bool $required): ?string
{
    if (!isset($_FILES['gambar']) || !is_array($_FILES['gambar'])) {
        if ($required) {
            throw new InvalidArgumentException('Pilih foto berita untuk diunggah.');
        }

        return null;
    }

    $file = $_FILES['gambar'];
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($error) || $error === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            throw new InvalidArgumentException('Pilih foto berita untuk diunggah.');
        }

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

    $directory = berita_storage_ensure_directory();
    $fileName = bin2hex(random_bytes(16)) . '.' . $storedExtension;
    if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $fileName)) {
        throw new RuntimeException('Foto berita gagal disimpan.');
    }

    return $fileName;
}

function admin_news_cleanup_if_unused(mysqli $connection, ?string $fileName): void
{
    if ($fileName === null || $fileName === '') {
        return;
    }

    $usage = admin_module_execute($connection, 'SELECT COUNT(*) FROM berita WHERE gambar = ?', 's', [$fileName]);
    $usage->bind_result($count);
    $usage->fetch();
    $usage->close();
    if ((int) $count === 0 && !berita_storage_delete_file($fileName)) {
        throw new RuntimeException('Foto berita lama tidak dapat dibersihkan.');
    }
}

$flash = admin_module_take_flash('berita_flash');
$oldInput = admin_module_take_old_input('berita_old_input');
$tableAvailable = false;
$errorMessage = '';

try {
    $tableAvailable = admin_module_missing_tables($koneksi, ['berita']) === [];
} catch (Throwable $exception) {
    error_log('Admin Berita schema check failed: ' . $exception->getMessage());
    $errorMessage = 'Struktur berita tidak dapat diperiksa saat ini.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$tableAvailable) {
        admin_module_flash('berita_flash', 'error', 'Tabel berita belum tersedia. Pengelolaan berita belum dapat digunakan.');
        admin_news_redirect();
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(admin_csrf_token(), $token)) {
        admin_module_flash('berita_flash', 'error', 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
        admin_news_redirect();
    }

    $action = $_POST['action'] ?? '';
    if (!is_string($action) || !in_array($action, ['create', 'update', 'delete'], true)) {
        admin_module_flash('berita_flash', 'error', 'Aksi berita tidak valid.');
        admin_news_redirect();
    }

    $id = null;
    if ($action !== 'create') {
        $rawId = $_POST['id'] ?? '';
        if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
            admin_module_flash('berita_flash', 'error', 'ID berita tidak valid.');
            admin_news_redirect();
        }
        $id = (int) $rawId;
    }

    $storedNewFile = null;
    try {
        if ($action === 'delete') {
            $lookup = admin_module_execute($koneksi, 'SELECT gambar FROM berita WHERE id = ?', 'i', [$id]);
            $record = $lookup->get_result()->fetch_assoc();
            $lookup->close();
            if (!$record) {
                admin_module_flash('berita_flash', 'error', 'Berita tidak ditemukan.');
                admin_news_redirect();
            }

            $statement = admin_module_execute($koneksi, 'DELETE FROM berita WHERE id = ?', 'i', [$id]);
            $deleted = $statement->affected_rows === 1;
            $statement->close();
            if (!$deleted) {
                admin_module_flash('berita_flash', 'error', 'Berita tidak ditemukan.');
                admin_news_redirect();
            }

            try {
                admin_news_cleanup_if_unused($koneksi, is_string($record['gambar']) ? $record['gambar'] : null);
                admin_module_flash('berita_flash', 'success', 'Berita berhasil dihapus.');
            } catch (Throwable $exception) {
                error_log('Deleted Berita image cleanup failed: ' . $exception->getMessage());
                admin_module_flash('berita_flash', 'error', 'Berita telah dihapus, tetapi file fotonya perlu dibersihkan oleh administrator.');
            }
            admin_news_redirect();
        }

        $rawTitle = $_POST['judul'] ?? '';
        $rawDate = $_POST['tanggal'] ?? '';
        $rawSummary = $_POST['ringkasan'] ?? '';
        $rawCategory = $_POST['kategori'] ?? '';
        $rawContent = $_POST['isi'] ?? '';
        if (!is_string($rawTitle) || !is_string($rawDate) || !is_string($rawSummary)
            || !is_string($rawCategory) || !is_string($rawContent)) {
            throw new InvalidArgumentException('Data formulir berita tidak valid.');
        }

        $title = trim($rawTitle);
        $date = trim($rawDate);
        $summary = trim($rawSummary);
        $category = trim($rawCategory);
        $content = trim($rawContent);
        $oldInput = [
            'judul' => $title,
            'tanggal' => $date,
            'ringkasan' => $summary,
            'kategori' => $category,
            'isi' => $content,
        ];

        if ($title === '' || mb_strlen($title, 'UTF-8') > 150) {
            throw new InvalidArgumentException('Judul wajib diisi dan maksimal 150 karakter.');
        }
        if ($category === '' || mb_strlen($category, 'UTF-8') > 100) {
            throw new InvalidArgumentException('Kategori wajib diisi dan maksimal 100 karakter.');
        }
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)
            || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            throw new InvalidArgumentException('Tanggal berita tidak valid.');
        }
        if (mb_strlen($summary, 'UTF-8') > 5000) {
            throw new InvalidArgumentException('Ringkasan maksimal 5.000 karakter.');
        }
        if (mb_strlen($content, 'UTF-8') > 15000) {
            throw new InvalidArgumentException('Isi berita maksimal 15.000 karakter.');
        }

        $oldFile = null;
        if ($action === 'update') {
            $lookup = admin_module_execute($koneksi, 'SELECT gambar FROM berita WHERE id = ?', 'i', [$id]);
            $record = $lookup->get_result()->fetch_assoc();
            $lookup->close();
            if (!$record) {
                admin_module_flash('berita_flash', 'error', 'Berita tidak ditemukan.');
                admin_news_redirect();
            }
            $oldFile = is_string($record['gambar']) ? $record['gambar'] : null;
        }

        $storedNewFile = admin_news_upload($action === 'create');
        $summaryValue = $summary === '' ? null : $summary;
        $contentValue = $content === '' ? null : $content;
        if ($action === 'create') {
            $statement = admin_module_execute(
                $koneksi,
                'INSERT INTO berita (tanggal, judul, ringkasan, gambar, kategori, isi) VALUES (?, ?, ?, ?, ?, ?)',
                'ssssss',
                [$date, $title, $summaryValue, $storedNewFile, $category, $contentValue]
            );
            $statement->close();
            $storedNewFile = null;
            admin_module_flash('berita_flash', 'success', 'Berita berhasil ditambahkan.');
            admin_news_redirect();
        }

        if ($storedNewFile !== null) {
            $statement = admin_module_execute(
                $koneksi,
                'UPDATE berita SET tanggal = ?, judul = ?, ringkasan = ?, gambar = ?, kategori = ?, isi = ? WHERE id = ?',
                'ssssssi',
                [$date, $title, $summaryValue, $storedNewFile, $category, $contentValue, $id]
            );
        } else {
            $statement = admin_module_execute(
                $koneksi,
                'UPDATE berita SET tanggal = ?, judul = ?, ringkasan = ?, kategori = ?, isi = ? WHERE id = ?',
                'sssssi',
                [$date, $title, $summaryValue, $category, $contentValue, $id]
            );
        }
        $affectedRows = $statement->affected_rows;
        $statement->close();
        if ($affectedRows === 0) {
            $check = admin_module_execute($koneksi, 'SELECT id FROM berita WHERE id = ?', 'i', [$id]);
            $exists = $check->fetch();
            $check->close();
            if (!$exists) {
                admin_module_flash('berita_flash', 'error', 'Berita tidak ditemukan.');
                admin_news_redirect();
            }
        }

        if ($storedNewFile !== null) {
            $storedNewFile = null;
            try {
                admin_news_cleanup_if_unused($koneksi, $oldFile);
            } catch (Throwable $exception) {
                error_log('Replaced Berita image cleanup failed: ' . $exception->getMessage());
                admin_module_flash('berita_flash', 'error', 'Berita diperbarui, tetapi foto lama perlu dibersihkan oleh administrator.');
                admin_news_redirect();
            }
        }

        admin_module_flash('berita_flash', 'success', 'Berita berhasil diperbarui.');
        admin_news_redirect();
    } catch (InvalidArgumentException $exception) {
        if ($storedNewFile !== null) {
            try {
                berita_storage_delete_file($storedNewFile);
            } catch (Throwable $cleanupException) {
                error_log('Temporary Berita photo cleanup failed: ' . $cleanupException->getMessage());
            }
        }
        admin_module_store_old_input('berita_old_input', $_POST, [
            'judul' => 150,
            'tanggal' => 10,
            'ringkasan' => 5000,
            'kategori' => 100,
            'isi' => 15000,
        ]);
        admin_module_flash('berita_flash', 'error', $exception->getMessage());
        admin_news_redirect($action === 'create' ? 'tambah' : 'edit', $id);
    } catch (Throwable $exception) {
        if ($storedNewFile !== null) {
            try {
                berita_storage_delete_file($storedNewFile);
            } catch (Throwable $cleanupException) {
                error_log('Temporary Berita photo cleanup failed: ' . $cleanupException->getMessage());
            }
        }
        error_log('Admin Berita action failed: ' . $exception->getMessage());
        admin_module_store_old_input('berita_old_input', $_POST, [
            'judul' => 150,
            'tanggal' => 10,
            'ringkasan' => 5000,
            'kategori' => 100,
            'isi' => 15000,
        ]);
        admin_module_flash('berita_flash', 'error', 'Perubahan berita gagal diproses. Silakan coba kembali.');
        admin_news_redirect($action === 'create' ? 'tambah' : 'edit', $id);
    }
}

$formMode = '';
$detailId = null;
$record = null;
$rows = [];
$rawForm = $_GET['form'] ?? '';
if (is_string($rawForm) && in_array($rawForm, ['tambah', 'edit'], true)) {
    $formMode = $rawForm;
}
$rawId = $_GET['id'] ?? '';
if (is_string($rawId) && ctype_digit($rawId) && (int) $rawId > 0) {
    $detailId = (int) $rawId;
} elseif (isset($_GET['id'])) {
    $errorMessage = 'ID berita tidak valid.';
}
if ($formMode === 'edit' && $detailId === null) {
    $formMode = '';
    $errorMessage = 'ID berita harus tersedia untuk mengedit.';
}

if ($tableAvailable) {
    try {
        if ($formMode === 'edit' && $detailId !== null) {
            $statement = admin_module_execute(
                $koneksi,
                'SELECT id, tanggal, judul, ringkasan, gambar, kategori, isi FROM berita WHERE id = ?',
                'i',
                [$detailId]
            );
            $result = $statement->get_result();
            $record = $result ? $result->fetch_assoc() : null;
            $statement->close();
            if ($record === null) {
                $formMode = '';
                $errorMessage = 'Berita tidak ditemukan.';
            }
        }

        if ($formMode === '') {
            $result = $koneksi->query('SELECT id, tanggal, judul, ringkasan, gambar, kategori, isi FROM berita ORDER BY tanggal DESC, id DESC');
            if (!$result) {
                throw new RuntimeException('Daftar berita tidak dapat dimuat.');
            }
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
        }
    } catch (Throwable $exception) {
        error_log('Admin Berita query failed: ' . $exception->getMessage());
        $errorMessage = 'Data berita tidak dapat dimuat saat ini.';
        $rows = [];
        $record = null;
        $formMode = '';
    }
}

$pageTitle = $formMode === 'tambah' ? 'Tambah Berita' : ($formMode === 'edit' ? 'Edit Berita' : 'Berita Desa');
admin_render_header($pageTitle, $_SESSION['admin_username']);
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Konten Website Publik</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950"><?php echo admin_module_escape($pageTitle); ?></h2>
    </div>
    <?php if ($tableAvailable && $formMode === ''): ?>
        <a href="berita.php?form=tambah" class="rounded-xl bg-amber-500 px-4 py-3 text-center text-xs font-extrabold text-slate-950 hover:bg-amber-400">Tambah Berita</a>
    <?php elseif ($tableAvailable): ?>
        <a href="berita.php" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Kembali ke daftar</a>
    <?php endif; ?>
</div>

<?php if (is_array($flash) && isset($flash['message'])): ?>
    <div role="<?php echo ($flash['type'] ?? '') === 'success' ? 'status' : 'alert'; ?>" class="mb-5 rounded-xl border px-4 py-3 text-sm font-semibold <?php echo ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>"><?php echo admin_module_escape((string) $flash['message']); ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div role="alert" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900"><?php echo admin_module_escape($errorMessage); ?></div>
<?php endif; ?>

<?php if (!$tableAvailable): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 text-sm leading-6 text-slate-600">
        <p>Tabel berita belum tersedia. Pengelolaan berita dapat digunakan setelah struktur database disiapkan dan disetujui.</p>
        <p class="mt-2">Halaman publik akan menampilkan berita yang tersimpan di database setelah tabel tersedia.</p>
    </section>
<?php elseif ($formMode !== '' && ($formMode === 'tambah' || $record !== null)): ?>
    <?php
    $formValues = [
        'judul' => (string) ($oldInput['judul'] ?? $record['judul'] ?? ''),
        'tanggal' => (string) ($oldInput['tanggal'] ?? $record['tanggal'] ?? date('Y-m-d')),
        'ringkasan' => (string) ($oldInput['ringkasan'] ?? $record['ringkasan'] ?? ''),
        'kategori' => (string) ($oldInput['kategori'] ?? $record['kategori'] ?? 'Informasi Desa'),
        'isi' => (string) ($oldInput['isi'] ?? $record['isi'] ?? ''),
    ];
    ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <form method="POST" action="berita.php" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
            <input type="hidden" name="action" value="<?php echo $formMode === 'tambah' ? 'create' : 'update'; ?>">
            <?php if ($formMode === 'edit'): ?><input type="hidden" name="id" value="<?php echo (int) $record['id']; ?>"><?php endif; ?>
            <div>
                <label for="judul" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Judul berita *</label>
                <input id="judul" name="judul" maxlength="150" required value="<?php echo admin_module_escape($formValues['judul']); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
            </div>
            <div>
                <label for="kategori" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Kategori *</label>
                <input id="kategori" name="kategori" maxlength="100" required value="<?php echo admin_module_escape($formValues['kategori']); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
            </div>
            <div>
                <label for="tanggal" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Tanggal berita *</label>
                <input id="tanggal" name="tanggal" type="date" required value="<?php echo admin_module_escape($formValues['tanggal']); ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
            </div>
            <div>
                <label for="ringkasan" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Ringkasan</label>
                <textarea id="ringkasan" name="ringkasan" maxlength="5000" rows="5" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15"><?php echo admin_module_escape($formValues['ringkasan']); ?></textarea>
            </div>
            <div>
                <label for="isi" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600">Isi berita</label>
                <textarea id="isi" name="isi" maxlength="15000" rows="10" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15"><?php echo admin_module_escape($formValues['isi']); ?></textarea>
                <p class="mt-2 text-xs text-slate-500">Opsional. Jika dikosongkan, ringkasan akan digunakan pada halaman detail.</p>
            </div>
            <div>
                <label for="gambar" class="mb-2 block text-xs font-extrabold uppercase tracking-wider text-slate-600"><?php echo $formMode === 'tambah' ? 'Foto berita *' : 'Ganti foto (opsional)'; ?></label>
                <input id="gambar" name="gambar" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" <?php echo $formMode === 'tambah' ? 'required' : ''; ?> class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-amber-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-amber-950">
                <p class="mt-2 text-xs text-slate-500">JPEG, PNG, atau WebP; maksimal 5 MB.</p>
                <?php if ($formMode === 'edit' && !empty($record['gambar'])): ?>
                    <img src="../berita_image.php?id=<?php echo (int) $record['id']; ?>" alt="Foto berita saat ini" class="mt-4 max-h-64 rounded-xl border border-slate-200 object-contain">
                <?php endif; ?>
            </div>
            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                <a href="berita.php" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-xs font-extrabold text-slate-700">Batal</a>
                <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-extrabold text-white hover:bg-slate-800">Simpan Berita</button>
            </div>
        </form>
    </section>
<?php else: ?>
    <?php if ($rows === []): ?>
        <section class="rounded-2xl border border-slate-200 bg-white px-5 py-12 text-center">
            <p class="font-extrabold text-slate-800">Belum ada berita tersimpan.</p>
            <p class="mt-2 text-sm text-slate-500">Tambahkan berita untuk mulai mengelola konten dari dashboard.</p>
        </section>
    <?php else: ?>
        <section class="grid gap-5 lg:grid-cols-2">
            <?php foreach ($rows as $item): ?>
                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <?php if (!empty($item['gambar'])): ?>
                        <img src="../berita_image.php?id=<?php echo (int) $item['id']; ?>" alt="<?php echo admin_module_escape((string) $item['judul']); ?>" loading="lazy" class="mb-4 aspect-[16/9] w-full rounded-xl bg-slate-100 object-cover">
                    <?php endif; ?>
                    <p class="text-xs font-extrabold uppercase tracking-wider text-amber-700"><?php echo admin_module_escape(admin_news_date((string) $item['tanggal'])); ?></p>
                    <p class="mt-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-500"><?php echo admin_module_escape((string) $item['kategori']); ?></p>
                    <h3 class="mt-3 text-lg font-black leading-snug text-slate-950"><?php echo admin_module_escape((string) $item['judul']); ?></h3>
                    <?php if ((string) ($item['ringkasan'] ?? '') !== ''): ?>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600"><?php echo admin_module_escape((string) $item['ringkasan']); ?></p>
                    <?php endif; ?>
                    <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                        <a href="berita.php?form=edit&amp;id=<?php echo (int) $item['id']; ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50">Edit</a>
                        <form method="POST" action="berita.php" onsubmit="return confirm('Hapus berita ini?');">
                            <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                            <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-extrabold text-red-700 hover:bg-red-50">Hapus</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php admin_render_footer(); ?>
