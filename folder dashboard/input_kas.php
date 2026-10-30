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
$role = isset($_SESSION['role']) ? strtoupper($_SESSION['role']) : 'WARGA';

$title = 'Input Kas RT - RT RW Digital Bersatu';
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
                    <strong style="color: var(--text);">Input Kas RT</strong>
                </div>
            </div>
            <div class="app-topbar__actions">
                <a href="laporan.php" class="ghost-btn" style="text-decoration:none;">
                    <i class="fa-solid fa-file-lines"></i> Laporan Keuangan
                </a>
            </div>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-file-pen" style="color: var(--primary);"></i> Input Transaksi Kas RT</h1>
                    <p style="margin: 6px 0 0; color: var(--text-soft);">Catat transaksi iuran masuk atau pengeluaran kas RT secara real-time maupun manual.</p>
                </div>
            </div>

            <?php if(isset($_GET['status']) && $_GET['status'] == 'berhasil'): ?>
                <div class="glass-panel" style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0; padding:14px 18px; margin-bottom:20px; border-radius:12px; font-weight:700;">
                    <i class="fa-solid fa-circle-check"></i> Transaksi kas berhasil diverifikasi dan dicatat ke pembukuan!
                </div>
            <?php endif; ?>

            <div style="max-width: 680px; margin: 0 auto;">
                <div class="module-panel">
                    <div class="module-panel__header">
                        <i class="fa-solid fa-money-bill-transfer" style="color:var(--primary); margin-right:8px;"></i> Formulir Transaksi Kas
                    </div>
                    <div style="padding: 28px;">
                        <form action="proses_kas.php" method="POST">
                            <input type="hidden" name="action" value="preview_payment">
                            
                            <div style="margin-bottom: 18px;">
                                <label style="display:block; font-weight:700; color:var(--text); margin-bottom:6px; font-size:0.9rem;">Nama Warga Pembayar / Penerima</label>
                                <?php if ($role === 'ADMIN'): ?>
                                <select name="nama_warga" required class="form-control" style="width:100%; padding:11px 14px; border:1px solid var(--line); border-radius:8px; background:#fff; font-size:0.95rem;">
                                    <option value="">-- Pilih Warga --</option>
                                    <?php
                                    $warga = mysqli_query($koneksi, "SELECT nama FROM users ORDER BY nama ASC");
                                    while($w = mysqli_fetch_array($warga)){
                                        echo "<option value='".htmlspecialchars($w['nama'], ENT_QUOTES)."'>".htmlspecialchars($w['nama'])."</option>";
                                    }
                                    ?>
                                </select>
                                <?php else: ?>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($nama_user); ?>" disabled style="width:100%; padding:11px 14px; border:1px solid var(--line); border-radius:8px; background:#f1f5f9; font-size:0.95rem;">
                                <input type="hidden" name="nama_warga" value="<?php echo htmlspecialchars($nama_user); ?>">
                                <?php endif; ?>
                            </div>

                            <div style="margin-bottom: 18px;">
                                <label style="display:block; font-weight:700; color:var(--text); margin-bottom:6px; font-size:0.9rem;">Keterangan Transaksi</label>
                                <input type="text" name="keterangan" required class="form-control" placeholder="Contoh: Iuran Kebersihan, Perbaikan Lampu Jalan, dll." style="width:100%; padding:11px 14px; border:1px solid var(--line); border-radius:8px; background:#fff; font-size:0.95rem;">
                            </div>

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px;">
                                <div>
                                    <label style="display:block; font-weight:700; color:var(--text); margin-bottom:6px; font-size:0.9rem;">Jenis Kas</label>
                                    <select name="jenis_kas" required class="form-control" style="width:100%; padding:11px 14px; border:1px solid var(--line); border-radius:8px; background:#fff; font-size:0.95rem;">
                                        <option value="masuk">Pemasukan (+)</option>
                                        <option value="keluar">Pengeluaran (-)</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block; font-weight:700; color:var(--text); margin-bottom:6px; font-size:0.9rem;">Jumlah (Rp)</label>
                                    <input type="number" name="jumlah_kas" required class="form-control" placeholder="Angka saja (Cth: 50000)" style="width:100%; padding:11px 14px; border:1px solid var(--line); border-radius:8px; background:#fff; font-size:0.95rem;">
                                </div>
                            </div>

                            <div style="margin-bottom: 24px;">
                                <label style="display:block; font-weight:700; color:var(--text); margin-bottom:6px; font-size:0.9rem;">Metode Pembayaran</label>
                                <select name="metode_bayar" class="form-control" style="width:100%; padding:11px 14px; border:1px solid var(--line); border-radius:8px; background:#fff; font-size:0.95rem; font-weight:700; color:var(--primary);">
                                    <option value="realtime">⚡ QRIS Real-Time (Otomatis Terverifikasi & Cepat)</option>
                                    <option value="manual">📁 Transfer Bank Manual (Upload Bukti Foto)</option>
                                </select>
                            </div>

                            <button type="submit" class="primary-btn" style="width:100%; padding:13px 20px; font-size:1rem; justify-content:center;">
                                <i class="fa-solid fa-bolt"></i> Lanjut ke Pembayaran Real-Time
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
