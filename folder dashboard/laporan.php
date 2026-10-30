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
$role_user = $_SESSION['role'] ?? 'WARGA';

$q_masuk = mysqli_query($koneksi, "SELECT SUM(jumlah) AS total FROM keuangan WHERE jumlah > 0");
$d_masuk = mysqli_fetch_assoc($q_masuk);
$total_masuk = $d_masuk['total'] ?? 0;

$q_keluar = mysqli_query($koneksi, "SELECT SUM(jumlah) AS total FROM keuangan WHERE jumlah < 0");
$d_keluar = mysqli_fetch_assoc($q_keluar);
$total_keluar = abs($d_keluar['total'] ?? 0);

$saldo_akhir = $total_masuk - $total_keluar;

// Pagination setup: 10 items per page
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$count_query = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM keuangan");
$count_data = mysqli_fetch_assoc($count_query);
$total_records = $count_data['total'] ?? 0;

$total_pages = ceil($total_records / $items_per_page);
if ($total_pages < 1) $total_pages = 1;
if ($current_page > $total_pages) $current_page = $total_pages;

$offset = ($current_page - 1) * $items_per_page;

$title = 'Laporan Keuangan - RT RW Digital Bersatu';
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
                    <strong style="color: var(--text);">Laporan Keuangan</strong>
                </div>
            </div>
            <div class="app-topbar__actions">
                <a href="input_kas.php" class="ghost-btn" style="text-decoration:none;">
                    <i class="fa-solid fa-plus-circle"></i> Input Transaksi Kas
                </a>
            </div>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-file-lines" style="color: var(--primary);"></i> Laporan & Keuangan RT RW</h1>
                    <p style="margin: 6px 0 0; color: var(--text-soft);">Transparansi arus kas pemasukan dan pengeluaran warga secara real-time.</p>
                </div>
            </div>

            <!-- Kartu Saldo & Statistik Arus Kas -->
            <section class="stat-grid" style="margin-bottom: 24px;">
                <article class="stat-card" style="border-left: 5px solid #10b981;">
                    <span class="stat-card__label">Saldo RT RW Digital Saat Ini</span>
                    <h2 class="stat-card__value" style="color: #059669;">Rp <?php echo number_format($saldo_akhir, 0, ',', '.'); ?></h2>
                    <div class="stat-card__muted">Total sisa kas yang tersedia</div>
                </article>
                <article class="stat-card">
                    <span class="stat-card__label">Total Pemasukan</span>
                    <h2 class="stat-card__value" style="color: #16a34a;">+ Rp <?php echo number_format($total_masuk, 0, ',', '.'); ?></h2>
                    <div class="stat-card__muted">Iuran & donasi warga</div>
                </article>
                <article class="stat-card">
                    <span class="stat-card__label">Total Pengeluaran</span>
                    <h2 class="stat-card__value" style="color: #dc2626;">- Rp <?php echo number_format($total_keluar, 0, ',', '.'); ?></h2>
                    <div class="stat-card__muted">Operasional & belanja RT</div>
                </article>
            </section>

            <!-- Tabel Riwayat Pembukuan Kas -->
            <div class="module-panel">
                <div class="module-panel__header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-receipt" style="color:var(--primary); margin-right:8px;"></i> Buku Riwayat Transaksi Kas</span>
                    <span style="font-size:0.85rem; color:var(--text-soft); font-weight:600;">Total Catatan: <?php echo $total_records; ?></span>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="text-align: center; width: 50px;">No</th>
                                <th>Tanggal</th>
                                <th>Nama Warga</th>
                                <th>Keterangan Transaksi</th>
                                <th style="text-align: center;">Jenis Kas</th>
                                <th style="text-align: right;">Jumlah Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = $offset + 1;
                            $data = mysqli_query($koneksi, "SELECT * FROM keuangan ORDER BY id DESC LIMIT $items_per_page OFFSET $offset");
                            
                            if (mysqli_num_rows($data) > 0) {
                                while($d = mysqli_fetch_array($data)) { 
                                    $is_masuk = ($d['jenis'] === 'masuk');
                                    $tgl_display = !empty($d['tanggal']) ? date('d-m-Y', strtotime($d['tanggal'])) : '-';
                            ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-soft); font-weight: 600;"><?php echo $no++; ?></td>
                                <td style="color: var(--text-soft); font-size: 0.88rem; font-weight: 600;"><?php echo $tgl_display; ?></td>
                                <td style="font-weight: 700; color: var(--text);"><?php echo htmlspecialchars($d['nama_warga']); ?></td>
                                <td style="color: var(--text-soft);"><?php echo htmlspecialchars($d['keterangan']); ?></td>
                                <td style="text-align: center;">
                                    <?php if ($is_masuk): ?>
                                        <span class="status-badge" style="background:#dcfce7; color:#166534; border-color:#bbf7d0;">Pemasukan</span>
                                    <?php else: ?>
                                        <span class="status-badge" style="background:#fee2e2; color:#991b1b; border-color:#fecaca;">Pengeluaran</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-weight: 800; color: <?php echo $is_masuk ? '#16a34a' : '#dc2626'; ?>;">
                                    <?php echo $is_masuk ? '+' : '-'; ?> Rp <?php echo number_format(abs($d['jumlah']), 0, ',', '.'); ?>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                                echo "<tr><td colspan='6' class='empty-state'>Belum ada transaksi kas yang dicatat.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Kontrol Navigasi Halaman (Pagination) -->
                <?php if ($total_pages > 1): ?>
                <div style="display: flex; justify-content: center; align-items: center; gap: 8px; padding: 20px; border-top: 1px solid var(--line); flex-wrap: wrap;">
                    <?php if ($current_page > 1): ?>
                        <a href="laporan.php?page=<?php echo $current_page - 1; ?>" class="ghost-btn" style="padding: 7px 14px; font-size: 0.85rem;">
                            <i class="fa-solid fa-chevron-left"></i> Sebelumnya
                        </a>
                    <?php else: ?>
                        <button class="ghost-btn" disabled style="padding: 7px 14px; font-size: 0.85rem;">
                            <i class="fa-solid fa-chevron-left"></i> Sebelumnya
                        </button>
                    <?php endif; ?>

                    <?php 
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1) {
                        echo '<a href="laporan.php?page=1" class="ghost-btn" style="padding: 7px 12px; font-size: 0.85rem;">1</a>';
                        if ($start_page > 2) echo '<span style="color: var(--text-soft);">...</span>';
                    }
                    
                    for ($p = $start_page; $p <= $end_page; $p++) {
                        if ($p == $current_page) {
                            echo '<button class="primary-btn" style="padding: 7px 14px; font-size: 0.85rem; font-weight:800;">' . $p . '</button>';
                        } else {
                            echo '<a href="laporan.php?page=' . $p . '" class="ghost-btn" style="padding: 7px 12px; font-size: 0.85rem;">' . $p . '</a>';
                        }
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span style="color: var(--text-soft);">...</span>';
                        echo '<a href="laporan.php?page=' . $total_pages . '" class="ghost-btn" style="padding: 7px 12px; font-size: 0.85rem;">' . $total_pages . '</a>';
                    }
                    ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="laporan.php?page=<?php echo $current_page + 1; ?>" class="ghost-btn" style="padding: 7px 14px; font-size: 0.85rem; color: var(--primary);">
                            Berikutnya <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <button class="ghost-btn" disabled style="padding: 7px 14px; font-size: 0.85rem;">
                            Berikutnya <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    <?php endif; ?>
                </div>
                <div style="text-align: center; padding-bottom: 16px; color: var(--text-soft); font-size: 0.82rem;">
                    Halaman <?php echo $current_page; ?> dari <?php echo $total_pages; ?> (Total <?php echo $total_records; ?> transaksi)
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
