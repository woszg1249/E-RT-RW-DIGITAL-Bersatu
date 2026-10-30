<?php
include "../Koneksi.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nama']) || strtoupper($_SESSION['role'] ?? 'WARGA') !== 'ADMIN') {
    echo "<script>alert('Akses Ditolak! Anda bukan Admin.'); window.location='dashboard.php';</script>";
    exit();
}

$nama_user = $_SESSION['nama'];
$role_user = $_SESSION['role'] ?? 'ADMIN';

// Pastikan tabel pengajuan surat ada.
$create_table_sql = "CREATE TABLE IF NOT EXISTS surat_pengajuan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    nama VARCHAR(255) NOT NULL,
    jenis_surat VARCHAR(100) NOT NULL,
    nik VARCHAR(50),
    ttl VARCHAR(255),
    alamat TEXT,
    tujuan TEXT,
    nama_rt VARCHAR(100),
    nama_rw VARCHAR(100),
    kelurahan VARCHAR(100),
    kecamatan VARCHAR(100),
    kota VARCHAR(100),
    tanggal_permohonan DATE,
    detail_usaha TEXT,
    keterangan TEXT,
    status VARCHAR(50) DEFAULT 'Menunggu',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($koneksi, $create_table_sql);

$feedback_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['surat_action'])) {
    $surat_id = mysqli_real_escape_string($koneksi, $_POST['surat_id']);
    $new_status = mysqli_real_escape_string($koneksi, $_POST['surat_action']);
    if (in_array($new_status, ['ACC', 'Ditolak'])) {
        $update_sql = "UPDATE surat_pengajuan SET status = '$new_status' WHERE id = '$surat_id'";
        if (mysqli_query($koneksi, $update_sql)) {
            $feedback_message = 'Status pengajuan surat berhasil diperbarui.';
        } else {
            $feedback_message = 'Gagal memperbarui status: ' . mysqli_error($koneksi);
        }
    }
}

$surat_list = [];
$result = mysqli_query($koneksi, "SELECT * FROM surat_pengajuan ORDER BY created_at DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $surat_list[] = $row;
    }
}

$title = 'Kelola Pengajuan Surat - RT RW Digital Bersatu';
include '../includes/header.php';
?>

<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>

    <div class="app-main">
        <header class="app-topbar">
            <div class="app-topbar__left">
                <button id="toggle-sidebar" class="app-topbar__toggle" aria-label="Toggle menu" aria-expanded="true">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <strong style="color: var(--text);">Kelola Surat</strong>
                </div>
            </div>
            <div class="app-topbar__actions">
                <a href="pengajuan_surat.php" class="ghost-btn" style="text-decoration:none;">
                    <i class="fa-solid fa-file-signature"></i> Buat Permohonan Baru
                </a>
            </div>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-file-circle-check" style="color: var(--primary);"></i> Kelola Pengajuan Surat</h1>
                    <p style="margin: 6px 0 0; color: var(--text-soft);">Tinjau, setujui (ACC), atau tolak permohonan surat pengantar dari warga.</p>
                </div>
            </div>

            <?php if (!empty($feedback_message)): ?>
                <div class="glass-panel" style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0; padding:12px 18px; margin-bottom:18px; border-radius:12px; font-weight:700;">
                    <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($feedback_message); ?>
                </div>
            <?php endif; ?>

            <div class="module-panel">
                <div class="module-panel__header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-inbox" style="color:var(--primary); margin-right:8px;"></i> Daftar Pengajuan Surat Warga</span>
                    <span style="font-size:0.85rem; color:var(--text-soft); font-weight:600;">Total: <?php echo count($surat_list); ?> Pengajuan</span>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Nama Pemohon</th>
                                <th>Jenis Surat</th>
                                <th>NIK</th>
                                <th>Alamat</th>
                                <th>Tujuan</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($surat_list)): ?>
                                <?php foreach ($surat_list as $surat): ?>
                                    <tr>
                                        <td style="color:var(--text-soft); font-weight:600; font-size:0.88rem; white-space:nowrap;">
                                            <?php echo htmlspecialchars(!empty($surat['tanggal_permohonan']) ? date('d-m-Y', strtotime($surat['tanggal_permohonan'])) : date('d-m-Y', strtotime($surat['created_at']))); ?>
                                        </td>
                                        <td style="font-weight: 700; color: var(--text);"><?php echo htmlspecialchars($surat['nama']); ?></td>
                                        <td>
                                            <span style="font-weight:600; color:var(--primary-dark);"><?php echo htmlspecialchars($surat['jenis_surat']); ?></span>
                                        </td>
                                        <td style="font-family: monospace; font-size: 0.88rem; color: var(--text-soft);"><?php echo htmlspecialchars($surat['nik']); ?></td>
                                        <td style="color: var(--text-soft); font-size: 0.88rem;"><?php echo htmlspecialchars($surat['alamat']); ?></td>
                                        <td style="color: var(--text-soft); font-size: 0.88rem;"><?php echo htmlspecialchars($surat['tujuan']); ?></td>
                                        <td style="text-align: center;">
                                            <?php
                                                $status = $surat['status'] ?? 'Menunggu';
                                                if ($status === 'ACC') {
                                                    echo '<span class="status-badge status-badge--approved">ACC</span>';
                                                } elseif ($status === 'Ditolak') {
                                                    echo '<span class="status-badge status-badge--rejected">DITOLAK</span>';
                                                } else {
                                                    echo '<span class="status-badge status-badge--pending">MENUNGGU</span>';
                                                }
                                            ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if ($status === 'Menunggu'): ?>
                                                <div style="display:inline-flex; gap:6px;">
                                                    <form method="post" style="margin:0;">
                                                        <input type="hidden" name="surat_id" value="<?php echo htmlspecialchars($surat['id']); ?>">
                                                        <input type="hidden" name="surat_action" value="ACC">
                                                        <button type="submit" class="primary-btn" style="padding:6px 12px; font-size:0.8rem; background:#16a34a;">ACC</button>
                                                    </form>
                                                    <form method="post" style="margin:0;">
                                                        <input type="hidden" name="surat_id" value="<?php echo htmlspecialchars($surat['id']); ?>">
                                                        <input type="hidden" name="surat_action" value="Ditolak">
                                                        <button type="submit" class="ghost-btn" style="padding:6px 12px; font-size:0.8rem; color:#dc2626; border-color:#fca5a5;">Tolak</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-soft); font-size: 0.82rem;">Selesai</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state">Belum ada pengajuan surat dari warga.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
