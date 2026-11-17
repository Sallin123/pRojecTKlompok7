<?php
require_once __DIR__ . '/masyarakat_auth.php';
masyarakat_require_authentication('surat.php');
require_once __DIR__ . '/surat_draft.php';
require_once __DIR__ . '/koneksi.php';

function surat_redirect(string $location): void
{
    header('Location: ' . $location, true, 303);
    exit;
}

function surat_flash_error(string $message): void
{
    $_SESSION['surat_form_error'] = $message;
    surat_redirect('surat.php');
}

function surat_submission_payload_hash(array $fields): string
{
    $payload = '';
    foreach (['jenis_surat', 'nik', 'nama', 'no_hp', 'keterangan'] as $field) {
        if (!isset($fields[$field]) || !is_string($fields[$field])) {
            throw new InvalidArgumentException('Submission payload is incomplete.');
        }
        $payload .= strlen($fields[$field]) . ':' . $fields[$field] . ';';
    }

    return hash('sha256', $payload);
}

function surat_submission_retry_key(int $accountId, string $token): string
{
    return $accountId . ':' . $token;
}

function surat_submission_retry_get(int $accountId, string $token): ?array
{
    $retryKey = surat_submission_retry_key($accountId, $token);
    $retries = $_SESSION['surat_submission_retries'] ?? [];
    if (!is_array($retries) || !isset($retries[$retryKey]) || !is_array($retries[$retryKey])) {
        return null;
    }

    $retry = $retries[$retryKey];
    if (!isset($retry['account_id'], $retry['token'], $retry['payload_hash'], $retry['created_at'])
        || $retry['account_id'] !== $accountId
        || !is_string($retry['token'])
        || !hash_equals($token, $retry['token'])
        || !is_string($retry['payload_hash'])
        || preg_match('/\A[a-f0-9]{64}\z/', $retry['payload_hash']) !== 1
        || !is_int($retry['created_at'])
        || time() - $retry['created_at'] > SURAT_DRAFT_TTL) {
        unset($_SESSION['surat_submission_retries'][$retryKey]);
        if ($_SESSION['surat_submission_retries'] === []) {
            unset($_SESSION['surat_submission_retries']);
        }
        return null;
    }

    return $retry;
}

function surat_submission_retry_store(
    int $accountId,
    string $token,
    string $payloadHash,
    int $createdAt
): bool {
    $retry = surat_submission_retry_get($accountId, $token);
    if ($retry !== null) {
        return hash_equals($retry['payload_hash'], $payloadHash);
    }
    if (time() - $createdAt > SURAT_DRAFT_TTL) {
        return false;
    }

    $retryKey = surat_submission_retry_key($accountId, $token);
    if (!isset($_SESSION['surat_submission_retries'])
        || !is_array($_SESSION['surat_submission_retries'])) {
        $_SESSION['surat_submission_retries'] = [];
    }
    foreach ($_SESSION['surat_submission_retries'] as $storedKey => $storedRetry) {
        if (!is_array($storedRetry)
            || !isset($storedRetry['created_at'])
            || !is_int($storedRetry['created_at'])
            || time() - $storedRetry['created_at'] > SURAT_DRAFT_TTL) {
            unset($_SESSION['surat_submission_retries'][$storedKey]);
        }
    }
    $retryKey = surat_submission_retry_key($accountId, $token);
    $_SESSION['surat_submission_retries'][$retryKey] = [
        'account_id' => $accountId,
        'token' => $token,
        'payload_hash' => $payloadHash,
        'created_at' => $createdAt,
    ];

    return true;
}

function surat_find_existing_submission(mysqli $connection, int $accountId, string $token): ?array
{
    $statement = $connection->prepare(
        'SELECT id, jenis_surat, nik, nama, no_hp, keterangan FROM surat WHERE akun_masyarakat_id = ? AND submission_token = ? LIMIT 1'
    );
    if (!$statement) {
        throw new RuntimeException('Existing surat lookup could not be prepared.');
    }

    try {
        if (!$statement->bind_param('is', $accountId, $token)
            || !$statement->execute()
            || !$statement->bind_result($existingId, $jenisSurat, $nik, $nama, $noHp, $keterangan)) {
            throw new RuntimeException('Existing surat lookup could not be executed.');
        }

        $found = $statement->fetch();
        if ($found === false) {
            throw new RuntimeException('Existing surat lookup could not be fetched.');
        }

        if ($found !== true) {
            return null;
        }

        return [
            'id' => (int) $existingId,
            'payload_hash' => surat_submission_payload_hash([
                'jenis_surat' => (string) $jenisSurat,
                'nik' => (string) $nik,
                'nama' => (string) $nama,
                'no_hp' => (string) $noHp,
                'keterangan' => (string) $keterangan,
            ]),
        ];
    } finally {
        $statement->close();
    }
}

function surat_submission_clear_draft(int $accountId, string $token): void
{
    $draft = surat_draft_get();
    if ($draft === null || !hash_equals($draft['token'], $token)) {
        return;
    }

    if (!isset($draft['submission_account_id'])
        || !is_int($draft['submission_account_id'])
        || $draft['submission_account_id'] !== $accountId) {
        return;
    }

    surat_draft_clear();
}

function surat_submission_complete(int $accountId, string $token): void
{
    surat_submission_clear_draft($accountId, $token);
    unset($_SESSION['surat_form_old']);
    $_SESSION['surat_success'] = true;
    surat_redirect('surat.php?sent=1');
}

function surat_submission_conflict(): void
{
    surat_flash_error('Token pengajuan sudah terikat pada data berbeda. Data tidak diubah dan pengajuan ulang dengan token ini ditolak.');
}

function surat_submission_uncertain(string $logMessage): void
{
    error_log($logMessage);
    surat_flash_error('Status pengajuan belum dapat dipastikan. Draft dan token yang sama tetap dipertahankan; periksa riwayat sebelum mencoba kembali.');
}

function surat_validate_fields(array $source): ?array
{
    $fields = ['jenis_surat', 'nik', 'nama', 'no_hp', 'keterangan'];
    $input = [];
    foreach ($fields as $field) {
        $value = $source[$field] ?? '';
        if (!is_string($value)) {
            return null;
        }
        $input[$field] = trim($value);
    }

    $allowedTypes = [
        'Surat Keterangan Usaha (SKU)',
        'Surat Keterangan Tidak Mampu (SKTM)',
        'Surat Keterangan Domisili',
        'Surat Keterangan Belum Menikah',
        'Surat Pengantar SKCK',
    ];

    if (!in_array($input['jenis_surat'], $allowedTypes, true)
        || preg_match('/\A[0-9]{16}\z/', $input['nik']) !== 1
        || preg_match('/\A.{1,100}\z/us', $input['nama']) !== 1
        || preg_match('/\A[0-9+().\-\s]{6,20}\z/', $input['no_hp']) !== 1
        || preg_match('/\A.{1,5000}\z/us', $input['keterangan']) !== 1) {
        return null;
    }

    return $input;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Permohonan surat hanya menerima metode POST.');
}

if (!masyarakat_csrf_is_valid($_POST['csrf_token'] ?? '')) {
    surat_flash_error('Sesi formulir tidak valid atau telah kedaluwarsa. Muat ulang halaman dan coba lagi.');
}

$action = $_POST['action'] ?? 'prepare';
if (!is_string($action) || !in_array($action, ['prepare', 'confirm', 'cancel'], true)) {
    surat_flash_error('Aksi permohonan tidak valid.');
}

if ($action === 'cancel') {
    surat_draft_clear();
    unset($_SESSION['surat_form_old']);
    $_SESSION['surat_form_error'] = 'Draft pengajuan telah dibatalkan.';
    surat_redirect('surat.php');
}

$fields = surat_validate_fields($_POST);
if ($fields === null) {
    $rawFields = [];
    foreach (['jenis_surat', 'nik', 'nama', 'no_hp', 'keterangan'] as $field) {
        $rawValue = $_POST[$field] ?? '';
        $rawFields[$field] = is_string($rawValue) ? $rawValue : '';
    }
    surat_form_store_old_input($rawFields);
    surat_flash_error('Data formulir tidak valid. Periksa kembali isian dan batas panjang setiap kolom.');
}

if ($action === 'prepare') {
    surat_draft_store($fields);
} elseif ($action === 'confirm') {
    $submittedToken = $_POST['submission_token'] ?? '';
    if (!is_string($submittedToken) || preg_match('/\A[a-f0-9]{64}\z/', $submittedToken) !== 1) {
        surat_flash_error('Token konfirmasi tidak valid. Periksa kembali formulir.');
    }
}

if (!masyarakat_is_authenticated()) {
    surat_redirect('masyarakat_login.php');
}

try {
    $account = masyarakat_current_account($koneksi);
} catch (Throwable $exception) {
    error_log(
        'Community account verification failed during surat submission ('
            . get_class($exception) . ', code ' . (int) $exception->getCode() . ').'
    );
    if ($action === 'confirm') {
        surat_submission_uncertain('Community account lookup failed before confirming a surat submission.');
    }
    surat_flash_error('Status akun tidak dapat diperiksa saat ini. Permohonan belum dikirim.');
}

if ($account === null) {
    surat_redirect('masyarakat_login.php');
}
if ($account['status'] !== 'approved') {
    surat_redirect('masyarakat_akun.php');
}
if ($action === 'prepare') {
    surat_redirect('surat.php?confirm=1');
}

$accountId = $account['id'];
$token = $submittedToken;
$payloadHash = surat_submission_payload_hash($fields);
$draft = surat_draft_get();
$draftMatches = $draft !== null && hash_equals($draft['token'], $token);
$retry = surat_submission_retry_get($accountId, $token);
$retryMatches = $retry !== null;

if ($retryMatches && !hash_equals($retry['payload_hash'], $payloadHash)) {
    surat_submission_conflict();
}

if ($draftMatches) {
    if (!hash_equals(surat_submission_payload_hash($draft['fields']), $payloadHash)) {
        surat_submission_conflict();
    }
    if (isset($draft['submission_account_id'])
        && (!is_int($draft['submission_account_id']) || $draft['submission_account_id'] !== $accountId)) {
        surat_submission_conflict();
    }

    $_SESSION['surat_draft']['submission_account_id'] = $accountId;
}

try {
    $existing = surat_find_existing_submission($koneksi, $accountId, $token);
} catch (Throwable $exception) {
    if ($draftMatches && !$retryMatches
        && !surat_submission_retry_store($accountId, $token, $payloadHash, $draft['created_at'])) {
        surat_submission_conflict();
    }
    surat_submission_uncertain(
        'Existing surat lookup failed before submission ('
            . get_class($exception) . ', code ' . (int) $exception->getCode() . ').'
    );
}

if ($existing !== null) {
    if (!hash_equals($existing['payload_hash'], $payloadHash)) {
        surat_submission_conflict();
    }
    if ($draftMatches && !$retryMatches) {
        if (!surat_submission_retry_store($accountId, $token, $payloadHash, $draft['created_at'])) {
            surat_submission_conflict();
        }
    }
    surat_submission_complete($accountId, $token);
}

if ($draftMatches && !$retryMatches) {
    if (!surat_submission_retry_store($accountId, $token, $payloadHash, $draft['created_at'])) {
        surat_submission_conflict();
    }
    $retryMatches = true;
}

if (!$draftMatches && !$retryMatches) {
    surat_flash_error('Draft tidak ditemukan atau token tidak cocok. Permohonan baru tidak dibuat; mulai ulang hanya setelah memastikan pengajuan sebelumnya belum tersimpan.');
}

$jenisSurat = $fields['jenis_surat'];
$nik = $fields['nik'];
$nama = $fields['nama'];
$noHp = $fields['no_hp'];
$keterangan = $fields['keterangan'];

try {
    $statement = $koneksi->prepare(
        'INSERT INTO surat (akun_masyarakat_id, submission_token, jenis_surat, nik, nama, no_hp, keterangan) SELECT id, ?, ?, ?, ?, ?, ? FROM akun_masyarakat WHERE id = ? AND status = \'approved\''
    );
    if (!$statement) {
        throw new RuntimeException('Surat insert statement could not be prepared.');
    }
    try {
        if (!$statement->bind_param('ssssssi', $token, $jenisSurat, $nik, $nama, $noHp, $keterangan, $accountId)
            || !$statement->execute()) {
            throw new RuntimeException('Surat insert statement could not be executed.');
        }
        $inserted = $statement->affected_rows;
    } finally {
        $statement->close();
    }
    if ($inserted !== 1) {
        surat_flash_error('Status akun tidak lagi disetujui. Permohonan belum dikirim.');
    }
} catch (mysqli_sql_exception $exception) {
    if ((int) $exception->getCode() === 1062) {
        try {
            $existing = surat_find_existing_submission($koneksi, $accountId, $token);
        } catch (Throwable $duplicateException) {
            surat_submission_uncertain(
                'Duplicate surat lookup failed ('
                    . get_class($duplicateException) . ', code ' . (int) $duplicateException->getCode() . ').'
            );
        }

        if ($existing !== null) {
            if (!hash_equals($existing['payload_hash'], $payloadHash)) {
                surat_submission_conflict();
            }
            surat_submission_complete($accountId, $token);
        }

        surat_submission_uncertain(
            'Duplicate key occurred for surat submission, but no matching account and token row was found.'
        );
    }

    surat_submission_uncertain(
        'Surat submission outcome could not be confirmed ('
            . get_class($exception) . ', code ' . (int) $exception->getCode() . ').'
    );
} catch (Throwable $exception) {
    surat_submission_uncertain(
        'Surat submission outcome could not be confirmed ('
            . get_class($exception) . ', code ' . (int) $exception->getCode() . ').'
    );
}

surat_submission_complete($accountId, $token);
