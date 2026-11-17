<?php
require_once __DIR__ . '/masyarakat_auth.php';
$aduanReturnTo = $_POST['return_to'] ?? null;
masyarakat_require_authentication(is_string($aduanReturnTo) ? $aduanReturnTo : 'aduan.php');
require_once __DIR__ . '/koneksi.php';

function aduan_return_path(): string
{
    $returnTo = $_POST['return_to'] ?? '';
    if ($returnTo === 'index.php#kotak-saran') {
        return 'index.php#kotak-saran';
    }

    return 'aduan.php#form-aduan';
}

function aduan_redirect_with_error(string $message): void
{
    $_SESSION['aduan_form_error'] = $message;
    header('Cache-Control: no-store, private');
    header('Location: ' . aduan_return_path(), true, 303);
    exit;
}

function aduan_upload_photo(): ?string
{
    if (!isset($_FILES['foto']) || !is_array($_FILES['foto'])) {
        return null;
    }

    $file = $_FILES['foto'];
    $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($uploadError)) {
        throw new InvalidArgumentException('File lampiran tidak valid.');
    }
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($uploadError !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Foto gagal diunggah. Periksa ukuran file dan coba kembali.');
    }

    $temporaryPath = $file['tmp_name'] ?? '';
    $originalName = $file['name'] ?? '';
    if (!is_string($temporaryPath) || !is_string($originalName) || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('File lampiran tidak valid.');
    }

    $fileSize = filesize($temporaryPath);
    if ($fileSize === false || $fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran foto maksimal 5 MB.');
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $extensionTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    if (!isset($extensionTypes[$extension])) {
        throw new InvalidArgumentException('Format foto yang diizinkan hanya JPEG, PNG, dan WebP.');
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $imageInfo = @getimagesize($temporaryPath);
    $expectedMimeType = $extensionTypes[$extension];
    if ($mimeType !== $expectedMimeType
        || !is_array($imageInfo)
        || ($imageInfo['mime'] ?? '') !== $expectedMimeType) {
        throw new InvalidArgumentException('Isi file tidak sesuai dengan format gambar yang diizinkan.');
    }

    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);
    if ($width < 1 || $height < 1 || $width * $height > 40000000) {
        throw new InvalidArgumentException('Dimensi foto tidak valid atau terlalu besar.');
    }

    $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDirectory)
        && !mkdir($uploadDirectory, 0750, true)
        && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Direktori penyimpanan lampiran tidak tersedia.');
    }
    if (!is_writable($uploadDirectory)) {
        throw new RuntimeException('Direktori penyimpanan lampiran tidak dapat ditulis.');
    }

    $storedExtension = $expectedMimeType === 'image/jpeg'
        ? 'jpg'
        : ($expectedMimeType === 'image/png' ? 'png' : 'webp');
    $fileName = bin2hex(random_bytes(16)) . '.' . $storedExtension;
    $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('Foto gagal disimpan.');
    }

    return $fileName;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Metode permintaan tidak diizinkan.');
}

if (!masyarakat_csrf_is_valid($_POST['csrf_token'] ?? null)) {
    aduan_redirect_with_error('Sesi formulir berakhir atau tidak valid. Muat ulang halaman lalu coba kembali.');
}

$allowedCategories = [
    'Ketertiban & Keamanan',
    'Kebersihan & Lingkungan',
    'Infrastruktur & Jalan',
    'Pelayanan Administrasi',
    'Lainnya',
    'Saran Pembangunan',
    'Saran Pelayanan',
    'Saran Kebersihan',
];
$input = [];
foreach (['kategori', 'nama', 'no_hp', 'judul', 'isi'] as $field) {
    $value = $_POST[$field] ?? '';
    if (!is_string($value)) {
        aduan_redirect_with_error('Data formulir tidak valid. Periksa kembali isian Anda.');
    }
    $input[$field] = trim($value);
}

$kategori = $input['kategori'];
$nama = $input['nama'];
$no_hp = $input['no_hp'];
$judul = $input['judul'];
$isi = $input['isi'];

if (preg_match('/\A.{21,}\z/us', $no_hp) === 1) {
    aduan_redirect_with_error('Nomor kontak maksimal 20 karakter.');
}

if (!in_array($kategori, $allowedCategories, true)
    || $judul === ''
    || $isi === ''
    || preg_match('/\A.{0,150}\z/us', $nama) !== 1
    || preg_match('/\A.{1,150}\z/us', $judul) !== 1
    || preg_match('/\A.{1,5000}\z/us', $isi) !== 1
    || preg_match('/\A.{0,20}\z/us', $no_hp) !== 1) {
    aduan_redirect_with_error('Data formulir tidak valid. Periksa kembali isian Anda.');
}

if ($no_hp !== ''
    && filter_var($no_hp, FILTER_VALIDATE_EMAIL) === false
    && preg_match('/\A[0-9+().\-\s]{6,30}\z/', $no_hp) !== 1) {
    aduan_redirect_with_error('Nomor kontak tidak valid. Periksa kembali isian Anda.');
}

$photoName = null;
try {
    $photoName = aduan_upload_photo();
    $accessToken = $no_hp === '' ? null : bin2hex(random_bytes(32));
    $accessTokenHash = $accessToken === null ? null : hash('sha256', $accessToken);
    $statement = $koneksi->prepare(
        'INSERT INTO aduan (kategori, nama, no_hp, judul, isi, foto, access_token_hash) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$statement) {
        throw new RuntimeException('Aduan insert statement could not be prepared.');
    }
    if (!$statement->bind_param(
        'sssssss',
        $kategori,
        $nama,
        $no_hp,
        $judul,
        $isi,
        $photoName,
        $accessTokenHash
    )) {
        $statement->close();
        throw new RuntimeException('Aduan insert parameters could not be bound.');
    }
    if (!$statement->execute()) {
        $statement->close();
        throw new RuntimeException('Aduan insert statement could not be executed.');
    }
    $statement->close();

    $_SESSION['aduan_submission_flash'] = [
        'message' => 'Laporan pengaduan Anda berhasil dikirim.',
        'token' => $accessToken,
    ];
    header('Cache-Control: no-store, private');
    header('Location: ' . aduan_return_path(), true, 303);
    exit;
} catch (InvalidArgumentException $exception) {
    aduan_redirect_with_error($exception->getMessage());
} catch (Throwable $exception) {
    if ($photoName !== null) {
        $photoPath = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $photoName;
        if (is_file($photoPath) && !unlink($photoPath)) {
            error_log('Failed to remove unlinked aduan attachment: ' . $photoPath);
        }
    }
    error_log('Aduan submission failed: ' . $exception->getMessage());
    aduan_redirect_with_error('Laporan gagal dikirim saat ini. Data belum tersimpan. Silakan coba kembali nanti.');
}
