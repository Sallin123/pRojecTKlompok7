<?php
$publicNavCurrentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<nav aria-label="Navigasi utama" class="public-nav-desktop hidden lg:flex items-center space-x-6 text-sm font-semibold text-gray-700">
    <a href="index.php"<?php echo $publicNavCurrentPage === 'index.php' ? ' aria-current="page"' : ''; ?>>Beranda</a>
    <a href="profil.php"<?php echo $publicNavCurrentPage === 'profil.php' ? ' aria-current="page"' : ''; ?>>Profil Desa</a>
    <a href="potensi.php"<?php echo $publicNavCurrentPage === 'potensi.php' ? ' aria-current="page"' : ''; ?>>Potensi</a>
    <a href="galeri.php"<?php echo $publicNavCurrentPage === 'galeri.php' ? ' aria-current="page"' : ''; ?>>Galeri</a>
    <a href="bansos.php"<?php echo $publicNavCurrentPage === 'bansos.php' ? ' aria-current="page"' : ''; ?>>Informasi Bansos</a>
    <div class="public-nav-dropdown">
        <button type="button" class="public-nav-dropdown-toggle" aria-expanded="false" aria-controls="public-services-menu"<?php echo in_array($publicNavCurrentPage, ['surat.php', 'aduan.php', 'mobil_siaga.php', 'mobil_siaga_lacak.php', 'posyandu.php'], true) ? ' aria-current="page"' : ''; ?>>
            Layanan Desa
            <svg aria-hidden="true" viewBox="0 0 16 16" fill="none"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div id="public-services-menu" class="public-nav-dropdown-menu">
            <a href="surat.php"<?php echo $publicNavCurrentPage === 'surat.php' ? ' aria-current="page"' : ''; ?>>Surat Online</a>
            <a href="aduan.php"<?php echo $publicNavCurrentPage === 'aduan.php' ? ' aria-current="page"' : ''; ?>>Aduan</a>
            <a href="mobil_siaga.php"<?php echo $publicNavCurrentPage === 'mobil_siaga.php' ? ' aria-current="page"' : ''; ?>>Mobil Siaga</a>
            <a href="posyandu.php"<?php echo $publicNavCurrentPage === 'posyandu.php' ? ' aria-current="page"' : ''; ?>>Posyandu</a>
        </div>
    </div>
    <a href="berita.php"<?php echo $publicNavCurrentPage === 'berita.php' ? ' aria-current="page"' : ''; ?>>Berita</a>
</nav>

<button type="button" class="public-mobile-toggle lg:hidden" aria-label="Buka navigasi" aria-expanded="false" aria-controls="public-mobile-nav">
    <svg class="public-mobile-menu-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    <svg class="public-mobile-close-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18"/></svg>
</button>

<div class="public-nav-actions" aria-label="Akun masyarakat">
    <span class="public-nav-notifications" role="img" aria-label="Notifikasi" title="Notifikasi">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
    </span>
    <?php if (masyarakat_is_authenticated()): ?>
        <details class="public-account-menu">
            <summary aria-label="Profil Akun Masyarakat" title="Profil Akun Masyarakat">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="8" r="4"/></svg>
            </summary>
            <div class="public-account-menu-panel">
                <a href="masyarakat_akun.php">Profil Akun</a>
                <form method="POST" action="masyarakat_logout.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(masyarakat_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit">Logout</button>
                </form>
            </div>
        </details>
    <?php else: ?>
        <a class="public-community-login" href="masyarakat_login.php">Login</a>
    <?php endif; ?>
</div>

<nav id="public-mobile-nav" aria-label="Navigasi utama mobile" class="public-mobile-nav lg:hidden" hidden>
    <a href="index.php"<?php echo $publicNavCurrentPage === 'index.php' ? ' aria-current="page"' : ''; ?>>Beranda</a>
    <a href="profil.php"<?php echo $publicNavCurrentPage === 'profil.php' ? ' aria-current="page"' : ''; ?>>Profil Desa</a>
    <a href="potensi.php"<?php echo $publicNavCurrentPage === 'potensi.php' ? ' aria-current="page"' : ''; ?>>Potensi</a>
    <a href="galeri.php"<?php echo $publicNavCurrentPage === 'galeri.php' ? ' aria-current="page"' : ''; ?>>Galeri</a>
    <a href="bansos.php"<?php echo $publicNavCurrentPage === 'bansos.php' ? ' aria-current="page"' : ''; ?>>Informasi Bansos</a>
    <div class="public-mobile-dropdown">
        <button type="button" class="public-mobile-dropdown-toggle" aria-expanded="false" aria-controls="public-mobile-services-menu"<?php echo in_array($publicNavCurrentPage, ['surat.php', 'aduan.php', 'mobil_siaga.php', 'mobil_siaga_lacak.php', 'posyandu.php'], true) ? ' aria-current="page"' : ''; ?>>
            Layanan Desa
            <svg aria-hidden="true" viewBox="0 0 16 16" fill="none"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div id="public-mobile-services-menu" class="public-mobile-dropdown-menu" hidden>
            <a href="surat.php"<?php echo $publicNavCurrentPage === 'surat.php' ? ' aria-current="page"' : ''; ?>>Surat Online</a>
            <a href="aduan.php"<?php echo $publicNavCurrentPage === 'aduan.php' ? ' aria-current="page"' : ''; ?>>Aduan</a>
            <a href="mobil_siaga.php"<?php echo $publicNavCurrentPage === 'mobil_siaga.php' ? ' aria-current="page"' : ''; ?>>Mobil Siaga</a>
            <a href="posyandu.php"<?php echo $publicNavCurrentPage === 'posyandu.php' ? ' aria-current="page"' : ''; ?>>Posyandu</a>
        </div>
    </div>
    <a href="berita.php"<?php echo $publicNavCurrentPage === 'berita.php' ? ' aria-current="page"' : ''; ?>>Berita</a>
    <a class="public-mobile-admin" href="login.php">Login Admin</a>
</nav>
<script src="assets/js/public-nav.js?v=20261110"></script>
