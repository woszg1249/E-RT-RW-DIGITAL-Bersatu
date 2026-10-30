<?php
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$decodedScriptName = urldecode($scriptName);
$currentScript = basename($decodedScriptName);
$inDashboardFolder = stripos($decodedScriptName, '/folder dashboard/') !== false || stripos($scriptName, '/folder%20dashboard/') !== false;
$dashboardLink = $inDashboardFolder ? 'dashboard.php' : 'folder dashboard/dashboard.php';
$profileLink = $inDashboardFolder ? '../profil.php' : 'profil.php';
$logoutLink = $inDashboardFolder ? '../folder login,logout dan register/logout.php' : 'folder login,logout dan register/logout.php';
$roleUser = strtoupper($_SESSION['role'] ?? 'WARGA');
$userName = $_SESSION['nama'] ?? 'Pengguna';
$userPhoto = $_SESSION['foto'] ?? '';

if (!isset($displayPhoto) || empty($displayPhoto)) {
    if (!empty($userPhoto) && file_exists(($inDashboardFolder ? '../img/' : 'img/') . $userPhoto)) {
        $displayPhoto = ($inDashboardFolder ? '../img/' : 'img/') . $userPhoto;
    } else {
        $displayPhoto = 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=0284C7&color=fff';
    }
}

if (!function_exists('appModuleUrl')) {
    function appModuleUrl($page, $inDashboardFolder) {
        return $inDashboardFolder ? $page : 'folder dashboard/' . $page;
    }
}
?>
<aside class="app-sidebar" id="app-sidebar">
    <div class="app-sidebar__brand">
        <div class="app-sidebar__brand-icon">
            <i class="fa-solid fa-house-user"></i>
        </div>
        <div class="app-sidebar__brand-text">
            <span>RT RW Digital</span>
            <small>Sistem Informasi RT</small>
        </div>
    </div>

    <div class="app-sidebar__profile">
        <div class="app-sidebar__avatar-wrap">
            <img src="<?php echo htmlspecialchars($displayPhoto); ?>" alt="Profile" class="app-sidebar__avatar">
        </div>
        <div class="app-sidebar__name" title="<?php echo htmlspecialchars($userName); ?>"><?php echo htmlspecialchars($userName); ?></div>
        <span class="app-sidebar__role"><?php echo htmlspecialchars($roleUser); ?></span>
    </div>

    <nav class="app-sidebar__nav">
        <div class="app-sidebar__section-title">Menu Utama</div>

        <a href="<?php echo $dashboardLink; ?>" class="app-menu-item <?php echo ($currentScript === 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-table-columns"></i>
            <span>Dashboard</span>
        </a>

        <?php if ($roleUser === 'ADMIN') { ?>
            <a href="<?php echo appModuleUrl('kelola_warga.php', $inDashboardFolder); ?>" class="app-menu-item <?php echo ($currentScript === 'kelola_warga.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i>
                <span>Kelola Data Warga</span>
            </a>
            <a href="<?php echo appModuleUrl('kelola_kk.php', $inDashboardFolder); ?>" class="app-menu-item <?php echo ($currentScript === 'kelola_kk.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-people-roof"></i>
                <span>Kelola Kartu Keluarga</span>
            </a>
            <a href="<?php echo appModuleUrl('kelola_tagihan.php', $inDashboardFolder); ?>" class="app-menu-item <?php echo ($currentScript === 'kelola_tagihan.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span>Kelola Tagihan</span>
            </a>
            <a href="<?php echo appModuleUrl('kelola_surat.php', $inDashboardFolder); ?>" class="app-menu-item <?php echo ($currentScript === 'kelola_surat.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-circle-check"></i>
                <span>Kelola Surat</span>
            </a>
        <?php } ?>

        <a href="<?php echo appModuleUrl('input_kas.php', $inDashboardFolder); ?>" class="app-menu-item <?php echo ($currentScript === 'input_kas.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-file-pen"></i>
            <span>Input Kas RT</span>
        </a>
        <a href="<?php echo appModuleUrl('laporan.php', $inDashboardFolder); ?>" class="app-menu-item <?php echo ($currentScript === 'laporan.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-file-lines"></i>
            <span>Laporan Keuangan</span>
        </a>

        <div class="app-sidebar__section-title">Layanan Warga</div>

        <a href="<?php echo appModuleUrl('pengajuan_surat.php', $inDashboardFolder); ?>" class="app-menu-item <?php echo ($currentScript === 'pengajuan_surat.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-file-signature"></i>
            <span>Pengajuan Surat</span>
        </a>

        <?php if ($roleUser === 'ADMIN') { ?>
            <a href="<?php echo appModuleUrl('posyandu.php', $inDashboardFolder); ?>" class="app-menu-item <?php echo ($currentScript === 'posyandu.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-stethoscope"></i>
                <span>Posyandu</span>
            </a>
        <?php } ?>

        <a href="<?php echo appModuleUrl('bank_sampah.php', $inDashboardFolder); ?>" class="app-menu-item <?php echo ($currentScript === 'bank_sampah.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-recycle"></i>
            <span>Bank Sampah</span>
        </a>
        <a href="<?php echo appModuleUrl('kegiatan.php', $inDashboardFolder); ?>" class="app-menu-item <?php echo ($currentScript === 'kegiatan.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-calendar-days"></i>
            <span>Kegiatan Warga</span>
        </a>
        <a href="<?php echo appModuleUrl('umkm.php', $inDashboardFolder); ?>" class="app-menu-item <?php echo ($currentScript === 'umkm.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-store"></i>
            <span>UMKM Lokal</span>
        </a>
        <a href="<?php echo appModuleUrl('koperasi.php', $inDashboardFolder); ?>" class="app-menu-item <?php echo ($currentScript === 'koperasi.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-hand-holding-dollar"></i>
            <span>Koperasi RT</span>
        </a>
        <a href="<?php echo appModuleUrl('aset.php', $inDashboardFolder); ?>" class="app-menu-item <?php echo ($currentScript === 'aset.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-boxes-stacked"></i>
            <span>Aset RT</span>
        </a>

        <div class="app-sidebar__section-title">Pengaturan Akun</div>

        <a href="<?php echo $profileLink; ?>" class="app-menu-item <?php echo ($currentScript === 'profil.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-user-gear"></i>
            <span>Edit Profil</span>
        </a>

        <div class="sidebar-help-card">
            <div class="sidebar-help-card__header">
                <i class="fa-solid fa-headset"></i>
                <span>Lapor Admin</span>
            </div>
            <div class="sidebar-help-card__desc">
                Kendala bug atau file tidak tersimpan?
            </div>
            <div class="sidebar-help-card__admin">
                <i class="fa-solid fa-user-shield"></i>
                <span>Reival Al Khafi</span>
            </div>
            <a href="https://wa.me/628986450758?text=Halo%20Admin%20Reival%20Al%20Khafi%2C%20saya%20ingin%20melaporkan%20kendala%20%2F%20bug%20pada%20website%20RT%20RW%20Digital%3A" target="_blank" rel="noopener noreferrer" class="sidebar-help-card__btn" title="Klik untuk hubungi WhatsApp Reival Al Khafi">
                <i class="fa-brands fa-whatsapp"></i>
                <span>08986450758</span>
            </a>
        </div>
    </nav>

    <div class="app-sidebar__footer">
        <a href="<?php echo $logoutLink; ?>" class="app-menu-item app-menu-item--logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Keluar</span>
        </a>
    </div>
</aside>
