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
$query_user = mysqli_query($koneksi, "SELECT * FROM users WHERE id = '" . (int)($_SESSION['id'] ?? $_SESSION['id_user'] ?? 0) . "' LIMIT 1");
if (!$query_user || mysqli_num_rows($query_user) === 0) {
    $query_user = mysqli_query($koneksi, "SELECT * FROM users WHERE nama = '" . mysqli_real_escape_string($koneksi, $nama_user) . "' LIMIT 1");
}
$data_user = mysqli_fetch_assoc($query_user);

$email_user = $data_user['email'] ?? ($_SESSION['email'] ?? '');
$role_user = $data_user['role'] ?? ($_SESSION['role'] ?? 'WARGA');
$foto_db = $data_user['foto'] ?? '';
$id_user = $data_user['id'] ?? 0;
$_SESSION['id_user'] = $id_user;
$_SESSION['role'] = $role_user;
$_SESSION['foto'] = $foto_db;

if (!empty($foto_db) && file_exists("../img/" . $foto_db)) {
    $tampilan_foto = "../img/" . $foto_db;
} else {
    $tampilan_foto = "https://ui-avatars.com/api/?name=" . urlencode($nama_user) . "&background=0284C7&color=fff";
}

$total_warga = 0;
if ($koneksi) {
    $q_warga = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users");
    if ($q_warga) {
        $data_warga = mysqli_fetch_assoc($q_warga);
        $total_warga = $data_warga['total'] ?? 0;
    }
}

$saldo_akhir = 0;
$pemasukan_persen = 0;
$pengeluaran_persen = 0;

if ($koneksi) {
    $q_masuk = mysqli_query($koneksi, "SELECT SUM(jumlah) AS total FROM keuangan WHERE jumlah > 0");
    $d_masuk = mysqli_fetch_assoc($q_masuk);
    $total_masuk = $d_masuk['total'] ?? 0;

    $q_keluar = mysqli_query($koneksi, "SELECT SUM(jumlah) AS total FROM keuangan WHERE jumlah < 0");
    $d_keluar = mysqli_fetch_assoc($q_keluar);
    $total_keluar = abs($d_keluar['total'] ?? 0);

    $saldo_akhir = $total_masuk - $total_keluar;

    $total_transaksi = $total_masuk + $total_keluar;
    if ($total_transaksi > 0) {
        $pemasukan_persen = round(($total_masuk / $total_transaksi) * 100);
        $pengeluaran_persen = round(($total_keluar / $total_transaksi) * 100);
    } else {
        $pemasukan_persen = 100;
        $pengeluaran_persen = 0;
    }
}

$title = 'Dashboard - RT RW Digital Bersatu';
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
                    <strong style="color: var(--text);">Dashboard</strong>
                </div>
            </div>
            <div class="app-topbar__actions">
                <button type="button" class="pwa-install-btn" style="display:none;"><i class="fa-solid fa-download"></i> Download Aplikasi</button>
            </div>
        </header>

        <main class="page-shell">
            <?php if (!empty($_SESSION['flash_welcome'])): ?>
                <div class="glass-panel" style="padding: 14px 18px; margin-bottom: 18px; background: rgba(14,165,233,0.08); border-color: rgba(14,165,233,0.12); color: var(--primary-dark); font-weight: 700;">
                    <?php echo $_SESSION['flash_welcome']; unset($_SESSION['flash_welcome']); ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <div>
                    <h1>Halo, <?php echo htmlspecialchars($nama_user); ?>!</h1>
                    <p style="margin: 8px 0 0; color: var(--text-soft);">Selamat datang di RT RW Digital Bersatu</p>
                </div>
                <?php if (strtolower($role_user) === 'warga') { ?>
                    <button id="btn-dismiss-all" class="ghost-btn">Hapus Semua Notifikasi</button>
                <?php } ?>
            </div>

            <?php include 'notifikasi_iuran.php'; ?>

            <section class="stat-grid">
                <article class="stat-card">
                    <span class="stat-card__label">Saldo RT RW Digital</span>
                    <h2 class="stat-card__value">Rp <?php echo number_format($saldo_akhir, 0, ',', '.'); ?></h2>
                    <div class="stat-card__muted">Total kas saat ini</div>
                </article>
                <article class="stat-card">
                    <span class="stat-card__label">Total Warga</span>
                    <h2 class="stat-card__value"><?php echo number_format($total_warga, 0, ',', '.'); ?> Orang</h2>
                    <div class="stat-card__muted">Terdaftar di sistem</div>
                </article>
            </section>

            <section class="glass-panel" style="padding: 22px; margin-bottom: 24px;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom: 16px;">
                    <h3 style="margin:0; color: var(--text);"><i class="fa-solid fa-chart-pie" style="color: var(--primary);"></i> Persentase Keuangan Bulanan</h3>
                </div>
                <div style="height: 260px;">
                    <canvas id="keuanganPieChart"></canvas>
                </div>
                <div style="display:flex; justify-content:space-between; gap:12px; margin-top:16px; font-weight:700; color: var(--text-soft); flex-wrap:wrap;">
                    <?php if ($pemasukan_persen + $pengeluaran_persen === 0): ?>
                        <span>Belum ada transaksi bulan ini</span>
                    <?php else: ?>
                        <span style="color: #16a34a;">Pemasukan: <?php echo $pemasukan_persen; ?>%</span>
                        <span style="color: #dc2626;">Pengeluaran: <?php echo $pengeluaran_persen; ?>%</span>
                    <?php endif; ?>
                </div>
            </section>

            <section class="glass-panel" style="padding: 22px;">
                <h3 style="margin: 0 0 16px; color: var(--text);">Menu Cepat</h3>
                <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 0;">
                    <button type="button" class="primary-btn" data-href="input_kas.php">Input Kas</button>
                    <button type="button" class="ghost-btn" data-href="laporan.php">Laporan</button>
                    <button type="button" class="ghost-btn" data-href="pengajuan_surat.php">Pengajuan Surat</button>
                    <button type="button" class="ghost-btn" data-href="bank_sampah.php">Bank Sampah</button>
                    <button type="button" class="ghost-btn" data-href="koperasi.php">Koperasi</button>
                    <button type="button" class="ghost-btn" data-href="kegiatan.php">Kegiatan</button>
                    <button type="button" class="ghost-btn" data-href="aset.php">Aset</button>
                    <button type="button" class="ghost-btn" data-href="umkm.php">UMKM</button>
                    <?php if (strtoupper($role_user) === 'ADMIN') : ?>
                        <button type="button" class="ghost-btn" data-href="kelola_kk.php"><i class="fa-solid fa-people-roof"></i> Kartu Keluarga</button>
                        <button type="button" class="ghost-btn" data-href="posyandu.php">Posyandu</button>
                    <?php else: ?>
                        <button type="button" class="ghost-btn" disabled title="Hanya admin yang dapat mengakses Posyandu">Posyandu (Admin)</button>
                    <?php endif; ?>
                </div>
            </section>

            <?php if (strtoupper($role_user) === 'ADMIN') { include 'buat_notifikasi.php'; } ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-href]').forEach(function (button) {
            button.addEventListener('click', function () {
                const href = this.getAttribute('data-href');
                if (href) window.location.href = href;
            });
        });

        const chartElement = document.getElementById('keuanganPieChart');
        if (chartElement) {
            const pemasukanPersen = <?php echo json_encode($pemasukan_persen); ?>;
            const pengeluaranPersen = <?php echo json_encode($pengeluaran_persen); ?>;
            const hasData = pemasukanPersen + pengeluaranPersen > 0;

            new Chart(chartElement, {
                type: 'doughnut',
                data: {
                    labels: ['Pemasukan', 'Pengeluaran'],
                    datasets: [{
                        data: hasData ? [pemasukanPersen, pengeluaranPersen] : [1, 0],
                        backgroundColor: hasData ? ['#22c55e', '#ef4444'] : ['#cbd5e1', '#e2e8f0'],
                        borderColor: ['#ffffff', '#ffffff'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true } },
                        tooltip: { callbacks: { label: function (context) { return context.label + ': ' + context.parsed + '%'; } } }
                    }
                }
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>