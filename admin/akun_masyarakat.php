<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/module_helpers.php';

$flash = admin_module_take_flash('akun_masyarakat_flash');
$accounts = [];
$errorMessage = '';

try {
    $statement = admin_module_execute(
        $koneksi,
        'SELECT id, email, created_at FROM akun_masyarakat WHERE status = \'pending\' ORDER BY created_at ASC, id ASC'
    );
    $result = $statement->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }
    }
    $statement->close();
} catch (Throwable $exception) {
    error_log('Admin community account queue failed: ' . $exception->getMessage());
    $errorMessage = 'Daftar akun yang menunggu persetujuan tidak dapat dimuat saat ini.';
}

admin_render_header('Persetujuan Akun Masyarakat', $_SESSION['admin_username']);
?>
<div class="mb-6">
    <p class="text-xs font-extrabold uppercase tracking-widest text-amber-600">Layanan Warga</p>
    <h2 class="mt-1 text-2xl font-black text-slate-950">Persetujuan Akun Masyarakat</h2>
    <p class="mt-1 text-sm text-slate-500">Tinjau pendaftaran. Keputusan persetujuan dicatat bersama admin dan waktu peninjauan.</p>
</div>

<?php if (is_array($flash) && isset($flash['message'])): ?>
    <div role="<?php echo ($flash['type'] ?? '') === 'success' ? 'status' : 'alert'; ?>" class="mb-5 rounded-xl border px-4 py-3 text-sm font-semibold <?php echo ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>">
        <?php echo admin_module_escape((string) $flash['message']); ?>
    </div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800"><?php echo admin_module_escape($errorMessage); ?></div>
<?php endif; ?>

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <?php if ($accounts === [] && $errorMessage === ''): ?>
        <div class="px-5 py-12 text-center">
            <p class="font-extrabold text-slate-800">Tidak ada akun yang menunggu persetujuan.</p>
            <p class="mt-2 text-sm text-slate-500">Pendaftaran baru akan muncul di daftar ini.</p>
        </div>
    <?php elseif ($accounts !== []): ?>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[42rem] text-left text-sm">
                <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-4">Email</th>
                        <th class="px-5 py-4">Tanggal pendaftaran</th>
                        <th class="px-5 py-4 text-right">Keputusan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td class="px-5 py-4 font-semibold text-slate-800"><?php echo admin_module_escape((string) $account['email']); ?></td>
                            <td class="px-5 py-4 text-slate-600"><?php echo admin_module_escape((string) $account['created_at']); ?></td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <?php foreach (['approve' => 'Setujui', 'reject' => 'Tolak'] as $action => $label): ?>
                                        <form method="POST" action="akun_masyarakat_action.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo admin_module_escape(admin_csrf_token()); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int) $account['id']; ?>">
                                            <input type="hidden" name="action" value="<?php echo admin_module_escape($action); ?>">
                                            <button type="submit" class="rounded-lg px-3 py-2 text-xs font-extrabold <?php echo $action === 'approve' ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'border border-red-200 text-red-700 hover:bg-red-50'; ?>">
                                                <?php echo admin_module_escape($label); ?>
                                            </button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php admin_render_footer(); ?>
