<?php
include "../Koneksi.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nama'])) {
    header("Location: ../folder login,logout dan register/login.php");
    exit();
}

$nama_user = $_SESSION['nama'];

$query_user = mysqli_query($koneksi, "SELECT * FROM users WHERE nama = '" . mysqli_real_escape_string($koneksi, $nama_user) . "'");
$data_user = mysqli_fetch_assoc($query_user);

$role_user = strtoupper($data_user['role'] ?? 'WARGA');
$id_user = $data_user['id'] ?? 0;

// Create koperasi tables if they do not exist
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS koperasi_anggota (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    tanggal_gabung DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(30) DEFAULT 'aktif',
    FOREIGN KEY (id_user) REFERENCES users(id)
)");

mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS koperasi_simpanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    jenis ENUM('setoran','penarikan') NOT NULL,
    jumlah DECIMAL(14,2) NOT NULL,
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
    catatan TEXT,
    FOREIGN KEY (id_user) REFERENCES users(id)
)");

mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS koperasi_pinjaman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    jumlah DECIMAL(14,2) NOT NULL,
    tenor INT NOT NULL,
    bunga DECIMAL(5,2) NOT NULL,
    status ENUM('pending','disetujui','ditolak','lunas') DEFAULT 'pending',
    tanggal_pengajuan DATETIME DEFAULT CURRENT_TIMESTAMP,
    keterangan TEXT,
    metode_pengajuan VARCHAR(50) DEFAULT 'manual',
    scan_device_id VARCHAR(50) NULL,
    nik_peminjam VARCHAR(50) NULL,
    FOREIGN KEY (id_user) REFERENCES users(id)
)");

mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS koperasi_cicilan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pinjaman INT NOT NULL,
    jumlah DECIMAL(14,2) NOT NULL,
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
    catatan TEXT,
    FOREIGN KEY (id_pinjaman) REFERENCES koperasi_pinjaman(id)
)");

mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS iot_koperasi_scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) DEFAULT 'IOT-READER-01',
    nik VARCHAR(50) NULL,
    rfid_uid VARCHAR(50) NULL,
    id_user INT NULL,
    status ENUM('pending', 'processed', 'expired') DEFAULT 'pending',
    scanned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (status),
    INDEX (scanned_at)
)");

function formatRupiah($value) {
    return 'Rp ' . number_format($value, 0, ',', '.');
}

function getTotalSimpanan($koneksi, $id_user) {
    $q = mysqli_query($koneksi, "SELECT SUM(CASE WHEN jenis='setoran' THEN jumlah WHEN jenis='penarikan' THEN -jumlah ELSE 0 END) as total FROM koperasi_simpanan WHERE id_user='" . intval($id_user) . "'");
    $d = mysqli_fetch_assoc($q);
    return floatval($d['total'] ?? 0);
}

function getTotalCicilan($koneksi, $id_pinjaman) {
    $q = mysqli_query($koneksi, "SELECT SUM(jumlah) as total FROM koperasi_cicilan WHERE id_pinjaman='" . intval($id_pinjaman) . "'");
    $d = mysqli_fetch_assoc($q);
    return floatval($d['total'] ?? 0);
}

$anggota = [];
$anggota_tersedia = [];
$simpanan = [];
$pinjaman = [];
$cicilan = [];
$riwayat_simpanan = [];
$riwayat_pinjaman = [];
$riwayat_cicilan = [];
$anggota_sendiri = null;

if ($role_user === 'ADMIN') {
    $anggota_query = mysqli_query($koneksi, "SELECT ka.*, u.nama, u.email, u.no_hp, u.nik FROM koperasi_anggota ka LEFT JOIN users u ON ka.id_user = u.id ORDER BY ka.tanggal_gabung DESC");
    while ($row = mysqli_fetch_assoc($anggota_query)) {
        $anggota[] = $row;
    }

    $anggota_tersedia_query = mysqli_query($koneksi, "SELECT * FROM users WHERE id NOT IN (SELECT id_user FROM koperasi_anggota)");
    while ($row = mysqli_fetch_assoc($anggota_tersedia_query)) {
        $anggota_tersedia[] = $row;
    }

    $simpanan_query = mysqli_query($koneksi, "SELECT ks.*, u.nama FROM koperasi_simpanan ks LEFT JOIN users u ON ks.id_user = u.id ORDER BY ks.tanggal DESC");
    while ($row = mysqli_fetch_assoc($simpanan_query)) {
        $simpanan[] = $row;
    }

    $pinjaman_query = mysqli_query($koneksi, "SELECT kp.*, u.nama, u.nik, u.foto FROM koperasi_pinjaman kp LEFT JOIN users u ON kp.id_user = u.id ORDER BY kp.tanggal_pengajuan DESC");
    while ($row = mysqli_fetch_assoc($pinjaman_query)) {
        $row['dibayar'] = getTotalCicilan($koneksi, $row['id']);
        $pinjaman[] = $row;
    }

    $cicilan_query = mysqli_query($koneksi, "SELECT kc.*, kp.jumlah as jumlah_pinjaman, u.nama FROM koperasi_cicilan kc LEFT JOIN koperasi_pinjaman kp ON kc.id_pinjaman = kp.id LEFT JOIN users u ON kp.id_user = u.id ORDER BY kc.tanggal DESC");
    while ($row = mysqli_fetch_assoc($cicilan_query)) {
        $cicilan[] = $row;
    }
} else {
    $anggota_sendiri_query = mysqli_query($koneksi, "SELECT * FROM koperasi_anggota WHERE id_user='" . intval($id_user) . "' LIMIT 1");
    $anggota_sendiri = mysqli_fetch_assoc($anggota_sendiri_query);

    $riwayat_simpanan_query = mysqli_query($koneksi, "SELECT * FROM koperasi_simpanan WHERE id_user='" . intval($id_user) . "' ORDER BY tanggal DESC");
    while ($row = mysqli_fetch_assoc($riwayat_simpanan_query)) {
        $riwayat_simpanan[] = $row;
    }

    $riwayat_pinjaman_query = mysqli_query($koneksi, "SELECT * FROM koperasi_pinjaman WHERE id_user='" . intval($id_user) . "' ORDER BY tanggal_pengajuan DESC");
    while ($row = mysqli_fetch_assoc($riwayat_pinjaman_query)) {
        $row['dibayar'] = getTotalCicilan($koneksi, $row['id']);
        $riwayat_pinjaman[] = $row;
    }

    $riwayat_cicilan_query = mysqli_query($koneksi, "SELECT kc.*, kp.jumlah as jumlah_pinjaman, kp.status FROM koperasi_cicilan kc LEFT JOIN koperasi_pinjaman kp ON kc.id_pinjaman = kp.id WHERE kp.id_user='" . intval($id_user) . "' ORDER BY kc.tanggal DESC");
    while ($row = mysqli_fetch_assoc($riwayat_cicilan_query)) {
        $riwayat_cicilan[] = $row;
    }
}

$title = 'Koperasi RT - RT RW Digital Bersatu';
include '../includes/header.php';
?>
<style>
    .koperasi-panel { background: #ffffff; padding: 24px; border-radius: 18px; box-shadow: 0 12px 30px rgba(0,0,0,0.06); margin-bottom: 24px; }
    .koperasi-panel h2 { margin-bottom: 18px; color: #1f3d7a; font-size: 1.3rem; }
    .koperasi-grid { display: grid; gap: 20px; }
    .koperasi-row { display: flex; flex-wrap: wrap; gap: 18px; }
    .koperasi-card { background: #f7fbff; border: 1px solid #d6e4f2; border-radius: 14px; padding: 18px; flex: 1; min-width: 220px; }
    .koperasi-card strong { display: block; margin-bottom: 8px; color: #1f3d7a; }
    .table-responsive { overflow-x: auto; }
    .table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    .table th, .table td { padding: 12px 14px; border: 1px solid #e7ecf1; font-size: 0.9rem; }
    .table th { background: #eef5fc; text-align: left; }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; margin-bottom: 6px; font-weight: 700; color: #2c3e50; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #d4dce6; border-radius: 10px; font-size: 0.95rem; }
    .form-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 10px; }
    .btn-primary, .btn-secondary, .btn-danger, .btn-success { border: none; border-radius: 10px; padding: 10px 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
    .btn-primary { background: #2563eb; color: white; }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-secondary { background: #f3f4f6; color: #111827; }
    .btn-danger { background: #dc2626; color: white; }
    .btn-success { background: #16a34a; color: white; }
    .status-pill { display: inline-flex; padding: 5px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-disetujui { background: #d1fae5; color: #065f46; }
    .status-ditolak { background: #fee2e2; color: #991b1b; }
    .status-lunas { background: #dbeafe; color: #1e40af; }
    .action-buttons { display: flex; flex-wrap: wrap; gap: 8px; }
    .hidden { display: none; }

    /* ==========================================================================
       IOT E-KTP SMART LOAN STATION STYLES
       ========================================================================== */
    .iot-station-banner {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 55%, #0284c7 100%);
        border-radius: 20px;
        padding: 26px 28px;
        color: #ffffff;
        box-shadow: 0 16px 36px rgba(15, 23, 42, 0.2);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(56, 189, 248, 0.3);
    }
    .iot-station-banner::after {
        content: '';
        position: absolute;
        top: -50px;
        right: -50px;
        width: 220px;
        height: 220px;
        background: radial-gradient(circle, rgba(56, 189, 248, 0.25) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    .iot-header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 14px;
    }
    .iot-badge-live {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(16, 185, 129, 0.2);
        border: 1px solid #10b981;
        color: #6ee7b7;
        font-size: 0.8rem;
        font-weight: 700;
        padding: 5px 14px;
        border-radius: 999px;
        letter-spacing: 0.02em;
    }
    .pulse-dot {
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        animation: pulseGreen 1.8s infinite;
    }
    @keyframes pulseGreen {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    .iot-title {
        font-size: 1.55rem;
        font-weight: 800;
        color: #ffffff;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }
    .iot-desc {
        color: #cbd5e1;
        font-size: 0.94rem;
        line-height: 1.5;
        max-width: 680px;
        margin-bottom: 20px;
    }
    .iot-actions-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }
    .btn-iot-scan {
        background: linear-gradient(135deg, #38bdf8 0%, #0284c7 100%);
        color: #ffffff;
        border: none;
        padding: 12px 22px;
        border-radius: 12px;
        font-size: 0.95rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 6px 18px rgba(2, 132, 199, 0.4);
        transition: all 0.25s ease;
    }
    .btn-iot-scan:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(2, 132, 199, 0.5);
    }
    .btn-iot-sim {
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.25);
        padding: 12px 20px;
        border-radius: 12px;
        font-size: 0.92rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }
    .btn-iot-sim:hover {
        background: rgba(255, 255, 255, 0.22);
    }
    .btn-iot-code {
        background: transparent;
        color: #93c5fd;
        border: 1px dashed rgba(147, 197, 253, 0.4);
        padding: 12px 18px;
        border-radius: 12px;
        font-size: 0.88rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-left: auto;
    }
    .btn-iot-code:hover {
        background: rgba(147, 197, 253, 0.1);
        color: #ffffff;
    }

    /* BADGE VERIFIKASI IOT DI TABEL */
    .badge-iot {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #f0fdf4;
        color: #15803d;
        border: 1px solid #bbf7d0;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        margin-left: 6px;
    }

    /* ==========================================================================
       MODAL STYLES (SCAN KTP & PENGAJUAN PINJAMAN)
       ========================================================================== */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.7);
        backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 99999;
        padding: 20px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    .modal-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    .modal-card {
        background: #ffffff;
        border-radius: 24px;
        max-width: 680px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 30px;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
        transform: scale(0.92);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
    }
    .modal-overlay.active .modal-card {
        transform: scale(1);
    }
    .modal-close-btn {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: #f1f5f9;
        color: #475569;
        font-size: 1.1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .modal-close-btn:hover {
        background: #fee2e2;
        color: #dc2626;
    }

    /* KARTU E-KTP DIGITAL VISUAL */
    .ektp-card-visual {
        background: linear-gradient(135deg, #1e3a8a 0%, #0369a1 50%, #0284c7 100%);
        border-radius: 18px;
        padding: 20px 22px;
        color: #ffffff;
        box-shadow: 0 10px 24px rgba(2, 132, 199, 0.25);
        margin-bottom: 22px;
        position: relative;
        overflow: hidden;
        border: 2px solid #38bdf8;
    }
    .ektp-chip {
        width: 38px;
        height: 28px;
        background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
        border-radius: 6px;
        border: 1px solid #fef08a;
        margin-bottom: 12px;
        box-shadow: inset 0 0 4px rgba(0,0,0,0.3);
    }
    .ektp-header-text {
        text-align: center;
        margin-bottom: 12px;
    }
    .ektp-header-text h3 {
        font-size: 0.95rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin: 0;
        color: #ffffff;
    }
    .ektp-header-text p {
        font-size: 0.8rem;
        margin: 0;
        color: #bae6fd;
    }
    .ektp-body-grid {
        display: grid;
        grid-template-columns: 85px 1fr;
        gap: 16px;
        align-items: center;
    }
    .ektp-photo-wrap {
        width: 85px;
        height: 105px;
        border-radius: 10px;
        overflow: hidden;
        border: 2px solid #ffffff;
        background: #0f172a;
    }
    .ektp-photo-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .ektp-info-table {
        font-size: 0.86rem;
        line-height: 1.45;
    }
    .ektp-nik-display {
        font-family: 'Consolas', monospace;
        font-size: 1.25rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        color: #fef08a;
        margin-bottom: 4px;
    }
    .ektp-badge-verified {
        position: absolute;
        bottom: 14px;
        right: 18px;
        background: rgba(16, 185, 129, 0.9);
        color: white;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }

    /* QUICK PILLS JUMLAH PINJAMAN */
    .quick-amount-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }
    .amount-pill {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 700;
        color: #334155;
        cursor: pointer;
        transition: all 0.15s;
    }
    .amount-pill:hover, .amount-pill.active {
        background: #0284c7;
        color: white;
        border-color: #0284c7;
    }

    /* LOAN SUMMARY BOX */
    .loan-summary-box {
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px;
        margin: 18px 0;
    }
    .loan-summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.9rem;
        margin-bottom: 8px;
        color: #475569;
    }
    .loan-summary-row.total {
        font-size: 1.05rem;
        font-weight: 800;
        color: #0369a1;
        border-top: 1px dashed #cbd5e1;
        padding-top: 10px;
        margin-top: 6px;
        margin-bottom: 0;
    }

    /* SCANNING RADAR ANIMATION */
    .radar-scan-box {
        width: 120px;
        height: 120px;
        margin: 10px auto 20px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(2, 132, 199, 0.15) 0%, rgba(2, 132, 199, 0.02) 70%);
        border: 2px dashed #0284c7;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        animation: spinRadar 6s linear infinite;
    }
    .radar-scan-icon {
        font-size: 44px;
        color: #0284c7;
        animation: none;
    }
    @keyframes spinRadar {
        100% { transform: rotate(360deg); }
    }
</style>

<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>

    <div class="app-main">
        <header class="app-topbar">
            <div class="app-topbar__left">
                <button id="toggle-sidebar" class="app-topbar__toggle" aria-label="Toggle menu" aria-expanded="true">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <strong style="color: var(--text);">Koperasi RT</strong>
                </div>
            </div>
            <div class="app-topbar__actions">
                <span class="order-pill" style="color:var(--text-soft); font-weight:700;">Role: <?php echo htmlspecialchars($role_user); ?></span>
            </div>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-hand-holding-dollar" style="color: var(--primary);"></i> Koperasi RT RW Digital</h1>
                    <p style="margin: 6px 0 0; color: var(--text-soft);">Kelola simpanan, pengajuan pinjaman, dan integrasi IoT e-KTP scanner warga.</p>
                </div>
            </div>

            <!-- =================================================================
                 IOT E-KTP SMART LOAN STATION BANNER
                 ================================================================= -->
            <div class="iot-station-banner">
                <div class="iot-header-top">
                    <div class="iot-badge-live">
                        <span class="pulse-dot"></span> IoT RFID/NFC Scanner Listener Aktif
                    </div>
                    <span style="font-size:0.82rem; color:#bae6fd;"><i class="fa-solid fa-tower-broadcast"></i> Device ID: IOT-KOP-READER</span>
                </div>
                <h2 class="iot-title">
                    <i class="fa-solid fa-microchip" style="color: #38bdf8;"></i> IoT e-KTP Smart Loan Station
                </h2>
                <p class="iot-desc">
                    Fitur peminjaman uang otomatis berbasis Internet of Things (IoT). Cukup tempelkan (tap) kartu e-KTP pada scanner RFID/NFC, sistem secara instan memverifikasi identitas kependudukan dan membuka formulir peminjaman uang koperasi.
                </p>
                <div class="iot-actions-bar">
                    <button class="btn-iot-scan" onclick="openScanTerminalModal()">
                        <i class="fa-solid fa-id-card-clip"></i> Scan e-KTP Sekarang
                    </button>
                    <button class="btn-iot-sim" onclick="openSimulasiModal()">
                        <i class="fa-solid fa-bolt"></i> Simulasi Tap KTP (Cepat)
                    </button>
                    <button class="btn-iot-code" onclick="openIotDocModal()">
                        <i class="fa-solid fa-code"></i> Panduan ESP32 IoT
                    </button>
                </div>
            </div>

            <div class="koperasi-panel">
                <div class="koperasi-row">
                    <div class="koperasi-card">
                        <strong>Keanggotaan Koperasi</strong>
                        <?php if ($role_user === 'ADMIN'): ?>
                            <p>Admin dapat mengelola anggota koperasi, mencatat simpanan, dan memproses pinjaman warga yang diverifikasi via IoT scan KTP.</p>
                        <?php else: ?>
                            <p>Status anggota koperasi Anda:</p>
                            <p><strong><?php echo $anggota_sendiri ? 'Aktif' : 'Belum Terdaftar'; ?></strong></p>
                            <?php if ($anggota_sendiri): ?>
                                <p>Tanggal bergabung: <?php echo date('d/m/Y H:i', strtotime($anggota_sendiri['tanggal_gabung'])); ?></p>
                            <?php else: ?>
                                <button class="btn-primary" onclick="submitKoperasiAction('join_member', { })">Gabung Koperasi</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="koperasi-card">
                        <strong>Ringkasan Koperasi</strong>
                        <?php if ($role_user === 'ADMIN'): ?>
                            <p>Jumlah anggota: <?php echo count($anggota); ?> orang</p>
                            <p>Total simpanan: <?php echo formatRupiah(array_sum(array_map(function($item) { return $item['jenis'] === 'setoran' ? floatval($item['jumlah']) : -floatval($item['jumlah']); }, $simpanan))); ?></p>
                            <p>Pinjaman tertunda: <?php echo count(array_filter($pinjaman, fn($row) => $row['status'] === 'pending')); ?> pengajuan</p>
                        <?php else: ?>
                            <p>Total simpanan Anda: <?php echo formatRupiah(getTotalSimpanan($koneksi, $id_user)); ?></p>
                            <p>Pinjaman Anda: <?php echo count($riwayat_pinjaman); ?> catatan</p>
                            <p>Cicilan Anda: <?php echo count($riwayat_cicilan); ?> pembayaran</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($role_user === 'ADMIN'): ?>
                <!-- ADMIN: MANAJEMEN PINJAMAN DENGAN BADGE IOT -->
                <div class="koperasi-panel">
                    <h2>Daftar Pengajuan Pinjaman Warga</h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Peminjam</th>
                                    <th>NIK</th>
                                    <th>Jumlah</th>
                                    <th>Tenor</th>
                                    <th>Bunga</th>
                                    <th>Metode Verifikasi</th>
                                    <th>Status</th>
                                    <th>Dibayar</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($pinjaman) === 0): ?>
                                    <tr><td colspan="10" style="text-align:center;">Belum ada pengajuan pinjaman</td></tr>
                                <?php endif; ?>
                                <?php foreach ($pinjaman as $item): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($item['nama']); ?></strong></td>
                                        <td><code><?php echo htmlspecialchars($item['nik_peminjam'] ?: ($item['nik'] ?? '-')); ?></code></td>
                                        <td><strong><?php echo formatRupiah($item['jumlah']); ?></strong></td>
                                        <td><?php echo intval($item['tenor']); ?> bulan</td>
                                        <td><?php echo floatval($item['bunga']); ?>%</td>
                                        <td>
                                            <?php if (($item['metode_pengajuan'] ?? '') === 'iot_scan_ktp'): ?>
                                                <span class="badge-iot"><i class="fa-solid fa-microchip"></i> IoT e-KTP</span>
                                            <?php else: ?>
                                                <span style="font-size:0.75rem; color:#64748b;"><i class="fa-solid fa-pen-to-square"></i> Manual</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="status-pill status-<?php echo htmlspecialchars($item['status']); ?>"><?php echo htmlspecialchars($item['status']); ?></span></td>
                                        <td><?php echo formatRupiah($item['dibayar']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal_pengajuan'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($item['status'] === 'pending'): ?>
                                                    <button class="btn-success" onclick="submitKoperasiAction('approve_loan', { id: <?php echo intval($item['id']); ?> })"><i class="fa-solid fa-check"></i> Setujui</button>
                                                    <button class="btn-danger" onclick="submitKoperasiAction('reject_loan', { id: <?php echo intval($item['id']); ?> })"><i class="fa-solid fa-xmark"></i> Tolak</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="koperasi-panel">
                    <h2>Manajemen Anggota Koperasi</h2>
                    <div class="form-group">
                        <label>Tambah Anggota dari Warga</label>
                        <select id="anggota_baru_id">
                            <option value="">-- Pilih Warga --</option>
                            <?php foreach ($anggota_tersedia as $warga): ?>
                                <option value="<?php echo intval($warga['id']); ?>"><?php echo htmlspecialchars($warga['nama'] . ' (' . $warga['email'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn-primary" onclick="submitKoperasiAction('add_member', { id_user: document.getElementById('anggota_baru_id').value })">Tambah Anggota</button>

                    <div class="table-responsive">
                        <table class="table" style="margin-top:20px;">
                            <thead>
                                <tr>
                                    <th>Nama Anggota</th>
                                    <th>NIK</th>
                                    <th>Email</th>
                                    <th>No. HP</th>
                                    <th>Tanggal Gabung</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anggota as $item): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($item['nama']); ?></strong></td>
                                        <td><code><?php echo htmlspecialchars($item['nik'] ?? '-'); ?></code></td>
                                        <td><?php echo htmlspecialchars($item['email']); ?></td>
                                        <td><?php echo htmlspecialchars($item['no_hp']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal_gabung'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="koperasi-panel">
                    <h2>Pencatatan Simpanan Warga</h2>
                    <div class="form-group">
                        <label>Pilih Anggota</label>
                        <select id="simpanan_user_id">
                            <option value="">-- Pilih Anggota --</option>
                            <?php foreach ($anggota as $item): ?>
                                <option value="<?php echo intval($item['id_user']); ?>"><?php echo htmlspecialchars($item['nama']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jenis Simpanan</label>
                        <select id="jenis_simpanan">
                            <option value="setoran">Setoran</option>
                            <option value="penarikan">Penarikan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jumlah</label>
                        <input type="number" id="jumlah_simpanan" step="0.01" min="0" placeholder="Masukkan jumlah" />
                    </div>
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea id="catatan_simpanan" rows="3" placeholder="Catatan simpanan"></textarea>
                    </div>
                    <button class="btn-primary" onclick="submitKoperasiAction('add_simpanan', {
                        id_user: document.getElementById('simpanan_user_id').value,
                        jenis: document.getElementById('jenis_simpanan').value,
                        jumlah: document.getElementById('jumlah_simpanan').value,
                        catatan: document.getElementById('catatan_simpanan').value
                    })">Simpan Catatan Simpanan</button>

                    <div class="table-responsive">
                        <table class="table" style="margin-top:20px;">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Jenis</th>
                                    <th>Jumlah</th>
                                    <th>Catatan</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($simpanan as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($item['jenis']); ?></td>
                                        <td><?php echo formatRupiah($item['jumlah']); ?></td>
                                        <td><?php echo htmlspecialchars($item['catatan']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="koperasi-panel">
                    <h2>Riwayat Cicilan</h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Jumlah Cicilan</th>
                                    <th>Catatan</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cicilan as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['nama']); ?></td>
                                        <td><?php echo formatRupiah($item['jumlah']); ?></td>
                                        <td><?php echo htmlspecialchars($item['catatan']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- WARGA VIEW: RIWAYAT PINJAMAN SAYA & STATUS IOT -->
                <div class="koperasi-panel">
                    <h2>Riwayat Pinjaman Saya</h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Jumlah</th>
                                    <th>Tenor</th>
                                    <th>Metode</th>
                                    <th>Status</th>
                                    <th>Telah Dibayar</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($riwayat_pinjaman) === 0): ?>
                                    <tr><td colspan="6" style="text-align:center;">Belum ada riwayat pinjaman. Gunakan tombol <strong>Scan e-KTP</strong> di atas untuk mengajukan.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($riwayat_pinjaman as $item): ?>
                                    <tr>
                                        <td><strong><?php echo formatRupiah($item['jumlah']); ?></strong></td>
                                        <td><?php echo intval($item['tenor']); ?> bulan</td>
                                        <td>
                                            <?php if (($item['metode_pengajuan'] ?? '') === 'iot_scan_ktp'): ?>
                                                <span class="badge-iot"><i class="fa-solid fa-microchip"></i> IoT e-KTP</span>
                                            <?php else: ?>
                                                <span style="font-size:0.75rem; color:#64748b;">Manual</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="status-pill status-<?php echo htmlspecialchars($item['status']); ?>"><?php echo htmlspecialchars($item['status']); ?></span></td>
                                        <td><?php echo formatRupiah($item['dibayar']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal_pengajuan'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="koperasi-panel">
                    <h2>Riwayat Simpanan Saya</h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Jenis</th>
                                    <th>Jumlah</th>
                                    <th>Catatan</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($riwayat_simpanan) === 0): ?>
                                    <tr><td colspan="4" style="text-align:center;">Belum ada riwayat simpanan</td></tr>
                                <?php endif; ?>
                                <?php foreach ($riwayat_simpanan as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['jenis']); ?></td>
                                        <td><?php echo formatRupiah($item['jumlah']); ?></td>
                                        <td><?php echo htmlspecialchars($item['catatan']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="koperasi-panel">
                    <h2>Riwayat Cicilan Saya</h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Jumlah Cicilan</th>
                                    <th>Status Pinjaman</th>
                                    <th>Catatan</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($riwayat_cicilan) === 0): ?>
                                    <tr><td colspan="4" style="text-align:center;">Belum ada cicilan</td></tr>
                                <?php endif; ?>
                                <?php foreach ($riwayat_cicilan as $item): ?>
                                    <tr>
                                        <td><?php echo formatRupiah($item['jumlah']); ?></td>
                                        <td><span class="status-pill status-<?php echo htmlspecialchars($item['status']); ?>"><?php echo htmlspecialchars($item['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($item['catatan']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- ==========================================================================
     MODAL 1: TERMINAL SCAN KTP (USB / KEYBOARD EMULATION / NIK MANUAL)
     ========================================================================== -->
<div class="modal-overlay" id="modalScanTerminal">
    <div class="modal-card" style="max-width: 500px; text-align: center;">
        <button class="modal-close-btn" onclick="closeModal('modalScanTerminal')">&times;</button>
        <div class="radar-scan-box">
            <i class="fa-solid fa-nfc-directional radar-scan-icon"></i>
        </div>
        <h3 style="color:#0f172a; margin-bottom: 6px;">Terminal Scan e-KTP IoT</h3>
        <p style="color:#64748b; font-size:0.9rem; margin-bottom: 20px;">
            Tempelkan kartu e-KTP pada reader RFID/NFC fisik atau masukkan 16 digit NIK di bawah ini:
        </p>
        <div class="form-group" style="text-align: left;">
            <label><i class="fa-regular fa-id-card"></i> Input NIK KTP / UID RFID</label>
            <input type="text" id="terminal_ktp_input" placeholder="Contoh: 3270000878865522" autofocus />
            <small style="color:#64748b; font-size:0.78rem; display:block; margin-top:4px;">
                Jika menggunakan USB RFID/Barcode scanner, arahkan kursor ke kotak ini lalu scan KTP.
            </small>
        </div>
        <button class="btn-primary" style="width: 100%; justify-content: center;" onclick="lookupTerminalKtp()">
            <i class="fa-solid fa-magnifying-glass"></i> Verifikasi Data e-KTP
        </button>
    </div>
</div>

<!-- ==========================================================================
     MODAL 2: E-KTP TERDETEKSI & FORM PENGAJUAN PINJAMAN UANG
     ========================================================================== -->
<div class="modal-overlay" id="modalLoanForm">
    <div class="modal-card">
        <button class="modal-close-btn" onclick="closeModal('modalLoanForm')">&times;</button>

        <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
            <span style="background:#dcfce7; color:#15803d; width:36px; height:36px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:18px;">
                <i class="fa-solid fa-check"></i>
            </span>
            <div>
                <h3 style="margin:0; color:#0f172a; font-size:1.25rem;">e-KTP Berhasil Diverifikasi!</h3>
                <small style="color:#64748b;">Silakan tentukan rincian pengajuan pinjaman dana koperasi</small>
            </div>
        </div>

        <!-- KARTU E-KTP DIGITAL -->
        <div class="ektp-card-visual">
            <div class="ektp-header-text">
                <h3>REPUBLIK INDONESIA</h3>
                <p>KARTU TANDA PENDUDUK ELEKTRONIK (e-KTP)</p>
            </div>
            <div class="ektp-chip"></div>
            <div class="ektp-body-grid">
                <div class="ektp-photo-wrap">
                    <img id="modal_ektp_photo" src="../img/profile.jpg" alt="Foto KTP">
                </div>
                <div class="ektp-info-table">
                    <div class="ektp-nik-display" id="modal_ektp_nik">3270000000000000</div>
                    <div><strong>Nama:</strong> <span id="modal_ektp_nama">-</span></div>
                    <div><strong>Alamat:</strong> <span id="modal_ektp_alamat">-</span></div>
                    <div><strong>Wilayah:</strong> RT <span id="modal_ektp_rt">-</span> / RW <span id="modal_ektp_rw">19</span></div>
                    <div><strong>Pekerjaan:</strong> <span id="modal_ektp_pekerjaan">-</span></div>
                </div>
            </div>
            <div class="ektp-badge-verified">
                <i class="fa-solid fa-microchip"></i> IoT RFID Verified
            </div>
        </div>

        <!-- FORM PENGAJUAN PINJAMAN DANA KOPERASI -->
        <input type="hidden" id="loan_user_id" value="">
        <input type="hidden" id="loan_user_nik" value="">
        <input type="hidden" id="loan_scan_device" value="IOT-KOP-READER">

        <div class="form-group">
            <label><i class="fa-solid fa-money-bill-wave"></i> Nominal Pinjaman (Rp)</label>
            <input type="number" id="loan_amount" min="100000" step="50000" value="1000000" oninput="calculateLoanSimulation()" placeholder="Contoh: 1000000" />
            <div class="quick-amount-pills">
                <span class="amount-pill" onclick="setQuickAmount(500000)">Rp 500 Rb</span>
                <span class="amount-pill active" onclick="setQuickAmount(1000000)">Rp 1 Juta</span>
                <span class="amount-pill" onclick="setQuickAmount(2500000)">Rp 2.5 Juta</span>
                <span class="amount-pill" onclick="setQuickAmount(5000000)">Rp 5 Juta</span>
                <span class="amount-pill" onclick="setQuickAmount(10000000)">Rp 10 Juta</span>
            </div>
        </div>

        <div class="form-group">
            <label><i class="fa-regular fa-clock"></i> Jangka Waktu Cicilan (Tenor)</label>
            <select id="loan_tenor" onchange="calculateLoanSimulation()">
                <option value="1">1 Bulan</option>
                <option value="3" selected>3 Bulan</option>
                <option value="6">6 Bulan</option>
                <option value="12">12 Bulan (1 Tahun)</option>
                <option value="24">24 Bulan (2 Tahun)</option>
            </select>
        </div>

        <div class="form-group">
            <label><i class="fa-solid fa-percent"></i> Bunga Koperasi per Bulan (%)</label>
            <input type="number" id="loan_bunga" step="0.1" min="0" value="1.5" oninput="calculateLoanSimulation()" />
            <small style="color:#64748b; font-size:0.8rem;">Bunga standar simpan pinjam koperasi RT RW (1.5% per bulan)</small>
        </div>

        <!-- KALKULATOR LIVE PINJAMAN -->
        <div class="loan-summary-box">
            <div class="loan-summary-row">
                <span>Pokok Pinjaman:</span>
                <strong id="sim_pokok">Rp 1.000.000</strong>
            </div>
            <div class="loan-summary-row">
                <span>Angsuran Pokok / Bulan:</span>
                <span id="sim_pokok_bulan">Rp 333.333</span>
            </div>
            <div class="loan-summary-row">
                <span>Jasa Bunga Koperasi / Bulan:</span>
                <span id="sim_bunga_bulan">Rp 15.000</span>
            </div>
            <div class="loan-summary-row total">
                <span>Total Cicilan per Bulan:</span>
                <strong id="sim_total_bulan">Rp 348.333 / bln</strong>
            </div>
        </div>

        <div class="form-group">
            <label><i class="fa-solid fa-comment-dots"></i> Tujuan / Keperluan Pinjaman</label>
            <textarea id="loan_purpose" rows="2" placeholder="Contoh: Modal tambahan usaha warung RT / Kebutuhan darurat / Biaya sekolah"></textarea>
        </div>

        <div class="form-actions" style="margin-top:20px;">
            <button class="btn-primary" style="flex:1; justify-content:center; padding:12px;" onclick="submitIoTLoan()">
                <i class="fa-solid fa-paper-plane"></i> Kirim Pengajuan Pinjaman Koperasi
            </button>
            <button class="btn-secondary" onclick="closeModal('modalLoanForm')">Batal</button>
        </div>
    </div>
</div>

<!-- ==========================================================================
     MODAL 3: SIMULASI TAP KTP CEPAT (DEMO SEKALI KLIK TANPA HARDWARE)
     ========================================================================== -->
<div class="modal-overlay" id="modalSimulasi">
    <div class="modal-card" style="max-width: 520px;">
        <button class="modal-close-btn" onclick="closeModal('modalSimulasi')">&times;</button>
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
            <span style="background:#e0f2fe; color:#0284c7; width:40px; height:40px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:18px;">
                <i class="fa-solid fa-bolt"></i>
            </span>
            <div>
                <h3 style="margin:0; color:#0f172a;">Simulasi Tap e-KTP Cepat</h3>
                <small style="color:#64748b;">Uji coba instan tanpa menghubungkan alat sensor fisik</small>
            </div>
        </div>

        <div class="form-group">
            <label>Pilih Warga yang Menempelkan e-KTP:</label>
            <select id="simulasi_warga_select">
                <option value="">-- Memuat daftar warga... --</option>
            </select>
        </div>

        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-bottom:20px; font-size:0.86rem; color:#475569;">
            <i class="fa-solid fa-circle-info" style="color:#0284c7;"></i>
            Saat tombol <strong>"Tap KTP Sekarang"</strong> diklik, server IoT akan menerima sinyal scan seolah-olah e-KTP ditempelkan ke modul RFID reader sungguhan!
        </div>

        <button class="btn-primary" style="width:100%; justify-content:center; padding:12px;" onclick="executeSimulatedTap()">
            <i class="fa-solid fa-nfc-symbol"></i> Tap e-KTP Sekarang
        </button>
    </div>
</div>

<!-- ==========================================================================
     MODAL 4: PANDUAN INTEGRASI PERANGKAT KERAS IOT (ESP32 / ARDUINO)
     ========================================================================== -->
<div class="modal-overlay" id="modalIotDoc">
    <div class="modal-card" style="max-width: 650px;">
        <button class="modal-close-btn" onclick="closeModal('modalIotDoc')">&times;</button>
        <h3 style="color:#0f172a; margin-bottom:6px;"><i class="fa-solid fa-microchip text-primary"></i> Panduan Hardware IoT (ESP32 + RC522)</h3>
        <p style="color:#64748b; font-size:0.88rem; margin-bottom:16px;">
            Alat IoT Anda cukup membaca UID/NIK dari kartu e-KTP via sensor RFID (MFRC522/PN532) dan mengirimkan HTTP POST ke endpoint sistem RT RW.
        </p>

        <div style="background:#0f172a; color:#f8fafc; padding:16px; border-radius:14px; font-family:'Consolas', monospace; font-size:0.82rem; overflow-x:auto; margin-bottom:16px;">
            <span style="color:#38bdf8;">// Endpoint API IoT RT RW Digital:</span><br>
            <strong>POST http://[IP_KOMPUTER_ANDA]/Keuangan rt apps/folder dashboard/api_iot_koperasi.php?action=push_scan</strong><br><br>
            <span style="color:#38bdf8;">// Payload JSON:</span><br>
            {<br>
            &nbsp;&nbsp;"nik": "3270000878865522",<br>
            &nbsp;&nbsp;"rfid_uid": "E2806894",<br>
            &nbsp;&nbsp;"device_id": "ESP32-KOPERASI-01"<br>
            }
        </div>

        <button class="btn-secondary" style="width:100%; justify-content:center;" onclick="closeModal('modalIotDoc')">Tutup Panduan</button>
    </div>
</div>

<script>
    // Utility Modal
    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }
    function openScanTerminalModal() {
        openModal('modalScanTerminal');
        setTimeout(() => {
            const input = document.getElementById('terminal_ktp_input');
            if (input) input.focus();
        }, 150);
    }
    function openIotDocModal() {
        openModal('modalIotDoc');
    }

    // Modal Simulasi: Ambil daftar warga
    function openSimulasiModal() {
        openModal('modalSimulasi');
        const select = document.getElementById('simulasi_warga_select');
        select.innerHTML = '<option value="">Memuat data...</option>';

        fetch('api_iot_koperasi.php?action=get_simulation_list')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.list.length > 0) {
                    select.innerHTML = '';
                    data.list.forEach(w => {
                        const opt = document.createElement('option');
                        opt.value = w.nik;
                        opt.textContent = `${w.nama} - NIK: ${w.nik} (${w.wilayah})`;
                        select.appendChild(opt);
                    });
                } else {
                    select.innerHTML = '<option value="">Tidak ada data warga</option>';
                }
            })
            .catch(() => {
                select.innerHTML = '<option value="">Gagal memuat warga</option>';
            });
    }

    // Eksekusi Tap Simulasi
    function executeSimulatedTap() {
        const nik = document.getElementById('simulasi_warga_select').value;
        if (!nik) {
            alert('Pilih warga terlebih dahulu!');
            return;
        }

        closeModal('modalSimulasi');

        // Kirim event scan ke API IoT
        const formData = new FormData();
        formData.append('nik', nik);
        formData.append('device_id', 'SIMULATOR-WEB-IOT');

        fetch('api_iot_koperasi.php?action=push_scan', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Mainkan suara beep sukses via Web Audio API
                playBeepSound();
                // Buka modal formulir pinjaman dengan data warga
                loadWargaToLoanForm(data.data.nik);
            } else {
                alert(data.message || 'Gagal memverifikasi kartu');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Terjadi kesalahan saat simulasi scan KTP');
        });
    }

    // Lookup Terminal KTP (Input Manual / USB Barcode Scanner)
    function lookupTerminalKtp() {
        const keyword = document.getElementById('terminal_ktp_input').value.trim();
        if (!keyword) {
            alert('Masukkan NIK KTP atau UID RFID!');
            return;
        }

        closeModal('modalScanTerminal');
        loadWargaToLoanForm(keyword);
    }

    // Muat data warga ke formulir pinjaman
    function loadWargaToLoanForm(keyword) {
        fetch('api_iot_koperasi.php?action=lookup_ktp&keyword=' + encodeURIComponent(keyword))
            .then(res => res.json())
            .then(data => {
                if (data.success && data.user) {
                    const u = data.user;
                    document.getElementById('loan_user_id').value = u.id;
                    document.getElementById('loan_user_nik').value = u.nik;

                    document.getElementById('modal_ektp_photo').src = u.foto || '../img/profile.jpg';
                    document.getElementById('modal_ektp_nik').textContent = u.nik;
                    document.getElementById('modal_ektp_nama').textContent = u.nama;
                    document.getElementById('modal_ektp_alamat').textContent = u.alamat;
                    document.getElementById('modal_ektp_rt').textContent = u.rt || '06';
                    document.getElementById('modal_ektp_rw').textContent = u.rw || '19';
                    document.getElementById('modal_ektp_pekerjaan').textContent = u.pekerjaan || 'Warga RT';

                    calculateLoanSimulation();
                    openModal('modalLoanForm');
                } else {
                    alert(data.message || 'Warga tidak ditemukan!');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Gagal mengambil data warga');
            });
    }

    // Quick Nominal Pills
    function setQuickAmount(amount) {
        document.getElementById('loan_amount').value = amount;
        document.querySelectorAll('.amount-pill').forEach(p => p.classList.remove('active'));
        if (event && event.target) {
            event.target.classList.add('active');
        }
        calculateLoanSimulation();
    }

    // Kalkulator Cicilan Pinjaman Live
    function calculateLoanSimulation() {
        const amount = parseFloat(document.getElementById('loan_amount').value) || 0;
        const tenor = parseInt(document.getElementById('loan_tenor').value) || 1;
        const bungaPersen = parseFloat(document.getElementById('loan_bunga').value) || 0;

        const pokokBulan = tenor > 0 ? (amount / tenor) : 0;
        const bungaBulan = amount * (bungaPersen / 100);
        const totalBulan = pokokBulan + bungaBulan;

        const formatRp = (num) => 'Rp ' + Math.round(num).toLocaleString('id-ID');

        document.getElementById('sim_pokok').textContent = formatRp(amount);
        document.getElementById('sim_pokok_bulan').textContent = formatRp(pokokBulan);
        document.getElementById('sim_bunga_bulan').textContent = formatRp(bungaBulan);
        document.getElementById('sim_total_bulan').textContent = formatRp(totalBulan) + ' / bln';
    }

    // Submit Pinjaman IoT
    function submitIoTLoan() {
        const idUser = document.getElementById('loan_user_id').value;
        const nik = document.getElementById('loan_user_nik').value;
        const jumlah = document.getElementById('loan_amount').value;
        const tenor = document.getElementById('loan_tenor').value;
        const bunga = document.getElementById('loan_bunga').value;
        const keterangan = document.getElementById('loan_purpose').value.trim() || 'Pengajuan Pinjaman melalui Verifikasi IoT e-KTP Scanner';

        if (!idUser || !jumlah || parseFloat(jumlah) <= 0) {
            alert('Data pengajuan tidak lengkap atau jumlah pinjaman tidak valid!');
            return;
        }

        const formData = new FormData();
        formData.append('csrf_token', '<?php echo csrf_token(); ?>');
        formData.append('id_user', idUser);
        formData.append('nik', nik);
        formData.append('jumlah', jumlah);
        formData.append('tenor', tenor);
        formData.append('bunga', bunga);
        formData.append('keterangan', keterangan);
        formData.append('scan_device_id', 'IOT-KOP-READER');

        fetch('proses_koperasi.php?action=request_loan_iot', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                closeModal('modalLoanForm');
                location.reload();
            }
        })
        .catch(err => {
            console.error(err);
            alert('Gagal mengirimkan pengajuan pinjaman!');
        });
    }

    // Web Audio API Beep Sound (Bip saat e-KTP terbaca sensor IoT)
    function playBeepSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime); // 880 Hz (A5)
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            osc.start();
            osc.stop(ctx.currentTime + 0.15);
        } catch (e) {
            // Audio context tidak didukung atau diblokir browser
        }
    }

    // REAL-TIME BACKGROUND LISTENER IOT
    // Polling setiap 2.5 detik untuk mendeteksi scan dari alat IoT fisik
    let iotPollingInterval = setInterval(() => {
        // Hanya cek jika tidak sedang membuka modal pinjaman
        if (document.getElementById('modalLoanForm').classList.contains('active')) {
            return;
        }

        fetch('api_iot_koperasi.php?action=check_latest_scan')
            .then(res => res.json())
            .then(data => {
                if (data.found && data.user) {
                    playBeepSound();
                    loadWargaToLoanForm(data.user.nik);
                }
            })
            .catch(() => {});
    }, 2500);

    // Event listener untuk tombol Enter di terminal scan KTP
    document.addEventListener('DOMContentLoaded', () => {
        const termInput = document.getElementById('terminal_ktp_input');
        if (termInput) {
            termInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    lookupTerminalKtp();
                }
            });
        }
    });

    // Default koperasi action
    function submitKoperasiAction(action, payload) {
        const params = new URLSearchParams();
        params.append('action', action);

        const formData = new FormData();
        formData.append('csrf_token', '<?php echo csrf_token(); ?>');
        for (const key in payload) {
            formData.append(key, payload[key]);
        }

        fetch('proses_koperasi.php?' + params.toString(), {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => {
            console.error(error);
            alert('Terjadi kesalahan saat menyimpan data koperasi');
        });
    }
</script>

<?php include '../includes/footer.php'; ?>
