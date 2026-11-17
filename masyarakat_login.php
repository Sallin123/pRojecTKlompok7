<?php
require_once __DIR__ . '/masyarakat_auth.php';
require_once __DIR__ . '/surat_draft.php';
require_once __DIR__ . '/koneksi.php';

function masyarakat_login_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$email = '';
$errorMessage = '';
$noticeMessage = ($_GET['registered'] ?? '') === '1'
    ? 'Pendaftaran diterima. Akun menunggu persetujuan admin; masuk untuk melihat statusnya.'
    : '';
$returnTo = masyarakat_safe_return_path($_GET['next'] ?? null);
$csrfToken = masyarakat_csrf_token();

if (masyarakat_is_authenticated()) {
    if ($returnTo !== null) {
        header('Location: ' . $returnTo);
        exit;
    }

    try {
        $currentAccount = masyarakat_current_account($koneksi);
        if ($currentAccount !== null && $currentAccount['status'] === 'approved' && surat_draft_get() !== null) {
            header('Location: surat.php?confirm=1');
            exit;
        }
    } catch (Throwable $exception) {
        error_log('Community account status lookup failed during login redirect: ' . $exception->getMessage());
    }
    header('Location: masyarakat_akun.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnTo = masyarakat_safe_return_path($_POST['next'] ?? null);
    $submittedToken = $_POST['csrf_token'] ?? '';
    $rawEmail = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = is_string($rawEmail) ? strtolower(trim($rawEmail)) : '';

    if (!masyarakat_csrf_is_valid($submittedToken)) {
        $errorMessage = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif (
        !is_string($rawEmail)
        || !is_string($password)
        || strlen($email) > 254
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        || $password === ''
        || strlen($password) > 72
    ) {
        $errorMessage = 'Email atau password tidak valid.';
    } else {
        try {
            $statement = $koneksi->prepare(
                'SELECT id, password_hash, status FROM akun_masyarakat WHERE email = ? LIMIT 1'
            );
            if (!$statement) {
                throw new RuntimeException('Login statement could not be prepared.');
            }
            if (!$statement->bind_param('s', $email) || !$statement->execute()) {
                $statement->close();
                throw new RuntimeException('Login statement could not be executed.');
            }
            if (!$statement->bind_result($accountId, $passwordHash, $accountStatus)) {
                $statement->close();
                throw new RuntimeException('Login results could not be bound.');
            }
            $found = $statement->fetch();
            $statement->close();

            if ($found !== true || !password_verify($password, (string) $passwordHash)) {
                $errorMessage = 'Email atau password tidak sesuai.';
            } else {
                masyarakat_establish_session((int) $accountId);
                if ($returnTo !== null) {
                    header('Location: ' . $returnTo);
                    exit;
                }
                if ($accountStatus === 'approved' && surat_draft_get() !== null) {
                    header('Location: surat.php?confirm=1');
                    exit;
                }
                header('Location: masyarakat_akun.php');
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Community account login failed: ' . $exception->getMessage());
            $errorMessage = 'Login belum dapat diproses. Silakan coba kembali nanti.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Masyarakat - Desa Pemayahan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-900 antialiased">
    <main class="min-h-screen grid lg:grid-cols-2">
        <section class="hidden lg:flex flex-col justify-between bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-12 text-white">
            <a href="index.php" class="inline-flex items-center gap-3" aria-label="Kembali ke portal Desa Pemayahan">
                <span class="grid h-12 w-12 place-items-center rounded-xl bg-amber-500 text-2xl font-black text-slate-950">D</span>
                <span>
                    <span class="block text-lg font-extrabold tracking-tight">DESA <span class="text-amber-400">PEMAYAHAN</span></span>
                    <span class="block text-xs tracking-widest text-slate-300">PORTAL LAYANAN DESA</span>
                </span>
            </a>
            <div class="max-w-lg">
                <p class="mb-3 text-xs font-extrabold uppercase tracking-[0.25em] text-amber-400">Layanan Masyarakat</p>
                <h1 class="text-4xl font-black leading-tight">Masuk ke akun layanan desa.</h1>
                <p class="mt-4 text-sm leading-7 text-slate-300">Akun yang menunggu persetujuan belum dapat digunakan untuk mengajukan surat.</p>
            </div>
            <p class="text-xs text-slate-400">&copy; <?php echo date('Y'); ?> Portal Resmi Desa Pemayahan</p>
        </section>
        <section class="flex items-center justify-center bg-slate-50 px-5 py-12">
            <div class="w-full max-w-md">
                <a href="index.php" class="mb-10 inline-flex items-center gap-3 lg:hidden">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-amber-500 text-xl font-black text-slate-950">D</span>
                    <span class="text-base font-extrabold">DESA <span class="text-amber-600">PEMAYAHAN</span></span>
                </a>
                <div class="mb-8">
                    <span class="text-xs font-extrabold uppercase tracking-[0.2em] text-amber-600">Akun Masyarakat</span>
                    <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Login</h2>
                    <p class="mt-2 text-sm text-slate-600">Gunakan email dan password akun masyarakat.</p>
                </div>
                <?php if ($noticeMessage !== ''): ?>
                    <div role="status" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900"><?php echo masyarakat_login_escape($noticeMessage); ?></div>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800"><?php echo masyarakat_login_escape($errorMessage); ?></div>
                <?php endif; ?>
                <form method="POST" action="masyarakat_login.php" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5">
                    <input type="hidden" name="csrf_token" value="<?php echo masyarakat_login_escape($csrfToken); ?>">
                    <input type="hidden" name="next" value="<?php echo masyarakat_login_escape($returnTo ?? ''); ?>">
                    <div>
                        <label for="email" class="mb-2 block text-sm font-bold text-slate-800">Email</label>
                        <input id="email" name="email" type="email" autocomplete="username" required maxlength="254" value="<?php echo masyarakat_login_escape($email); ?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15">
                    </div>
                    <div>
                        <label for="password" class="mb-2 block text-sm font-bold text-slate-800">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="72" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15">
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-amber-500 px-5 py-3.5 text-sm font-extrabold text-slate-950 shadow-lg shadow-amber-500/20 transition hover:bg-amber-400">Masuk</button>
                    <p class="text-center text-xs font-semibold text-slate-500">Belum memiliki akun? <a href="masyarakat_daftar.php" class="text-blue-900 hover:text-amber-700">Daftar</a></p>
                    <a href="index.php" class="block text-center text-xs font-semibold text-slate-500 transition hover:text-blue-900">Kembali ke website publik</a>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
