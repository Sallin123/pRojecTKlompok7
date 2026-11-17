<?php
require_once __DIR__ . '/admin/auth.php';
require_once __DIR__ . '/admin/config.php';

if (admin_is_authenticated()) {
    header('Location: admin/index.php');
    exit;
}

$error = '';
$username = '';
$csrfToken = admin_csrf_token();
$credentialsConfigured = password_get_info(ADMIN_PASSWORD_HASH)['algoName'] !== 'unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedUsername = $_POST['username'] ?? '';
    $username = is_string($submittedUsername) ? trim($submittedUsername) : '';
    $password = $_POST['password'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif (!$credentialsConfigured) {
        $error = 'Hash password admin belum diatur di admin/config.php.';
    } elseif (
        is_string($password)
        && hash_equals(ADMIN_USERNAME, $username)
        && password_verify($password, ADMIN_PASSWORD_HASH)
    ) {
        session_regenerate_id(true);
        $_SESSION['admin_username'] = ADMIN_USERNAME;
        header('Location: admin/index.php');
        exit;
    } else {
        $error = 'Nama pengguna atau password tidak sesuai.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Desa Pemayahan</title>
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
                <p class="mb-3 text-xs font-extrabold uppercase tracking-[0.25em] text-amber-400">Ruang Administrasi</p>
                <h1 class="text-4xl font-black leading-tight">Pengelolaan layanan desa dalam satu tempat.</h1>
                <p class="mt-4 text-sm leading-7 text-slate-300">Masuk menggunakan kredensial admin yang telah dikonfigurasi pada server.</p>
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
                    <span class="text-xs font-extrabold uppercase tracking-[0.2em] text-amber-600">Admin</span>
                    <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Login Dashboard</h2>
                    <p class="mt-2 text-sm text-slate-600">Masukkan akun admin untuk melanjutkan.</p>
                </div>

                <?php if ($error !== ''): ?>
                    <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div>
                        <label for="username" class="mb-2 block text-sm font-bold text-slate-800">Nama pengguna</label>
                        <input id="username" name="username" type="text" autocomplete="username" required maxlength="100"
                               value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                               class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15">
                    </div>
                    <div>
                        <label for="password" class="mb-2 block text-sm font-bold text-slate-800">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15">
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-amber-500 px-5 py-3.5 text-sm font-extrabold text-slate-950 shadow-lg shadow-amber-500/20 transition hover:bg-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-500/30">
                        Masuk ke Dashboard
                    </button>
                    <a href="index.php" class="block text-center text-xs font-semibold text-slate-500 transition hover:text-blue-900">Kembali ke website publik</a>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
