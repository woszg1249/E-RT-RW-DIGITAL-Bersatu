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

$query_user = mysqli_query($koneksi, "SELECT * FROM users WHERE nama = '$nama_user'");
$data_user = mysqli_fetch_assoc($query_user);

$email_user = $data_user['email'] ?? "admin@gmail.com";
$role_user = $data_user['role'] ?? "ADMIN";
$foto_db = $data_user['foto'] ?? "";

// PERBAIKAN 1: Ambil id_user agar bisa dipakai oleh file notifikasi_iuran.php
$id_user = $data_user['id'] ?? 0; 
$_SESSION['id_user'] = $id_user; // Pastikan tersimpan di session juga

if (!empty($foto_db) && file_exists("../img/" . $foto_db)) {
    $tampilan_foto = "../img/" . $foto_db;
} else {
    $tampilan_foto = "https://ui-avatars.com/api/?name=" . urlencode($nama_user) . "&background=4e73df&color=fff";
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - RT Digital</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="dashboard-wrapper">
    
    <aside class="sidebar" id="sidebar">
        <div class="brand">RT DIGITAL</div>
        
        <div class="sidebar-profile">
            <div class="profile-avatar-container">
                <img src="<?php echo $tampilan_foto; ?>" class="sidebar-avatar" alt="User Profile">
            </div>
            <div class="sidebar-username"><?php echo $nama_user; ?></div>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item active">
                <i class="fa-solid fa-table-columns"></i> Dashboard
            </a>

            <?php if (strtoupper($role_user) === 'ADMIN') { ?>
                <a href="kelola_warga.php" class="menu-item">
                    <i class="fa-solid fa-users"></i> Kelola Data Warga
                </a>
                <a href="kelola_tagihan.php" class="menu-item">
                    <i class="fa-solid fa-file-invoice-dollar"></i> Kelola Tagihan
                </a>
            <?php } ?>

            <a href="input_kas.php" class="menu-item">
                <i class="fa-solid fa-file-pen"></i> Input Kas RT
            </a>
            <a href="laporan.php" class="menu-item">
                <i class="fa-solid fa-file-lines"></i> Laporan Keuangan
            </a>
            <a href="../profil.php" class="menu-item">
                <i class="fa-solid fa-user-gear"></i> Edit Profil
            </a>
            
            <div class="sidebar-footer">
                <a href="../folder login,logout dan register/logout.php" class="menu-item-logout">
                    <i class="fa-solid fa-right-from-bracket"></i> Keluar
                </a>
            </div>
        </nav>
    </aside>

    <div class="main-content" id="main-content">
        <div class="top-navbar">
            <button id="toggle-sidebar" class="hamburger-btn">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>

        <main class="content-body">
            
            <div class="welcome-card">
                <h1>Halo, <?php echo $nama_user; ?> !</h1>
                <p>Login sebagai: <strong><?php echo $role_user; ?></strong> | <?php echo $email_user; ?></p>
            </div>
            
            <?php include 'notifikasi_iuran.php'; ?>
            
            <div class="card-container">
                <div class="card card-blue-line">
                    <span class="card-label">SALDO KAS RT SAAT INI</span>
                    <h2 class="card-value">Rp <?php echo number_format($saldo_akhir, 0, ',', '.'); ?></h2>
                </div>

                <div class="card card-green-line">
                    <span class="card-label">TOTAL WARGA TERDAFTAR</span>
                    <h2 class="card-value"><?php echo $total_warga; ?> Orang</h2>
                </div>
            </div>

            <div class="chart-section">
                <div class="chart-card">
                    <h3><i class="fa-solid fa-chart-pie"></i> Persentase Keuangan Bulanan</h3>
                    <div class="chart-container">
                        <canvas id="keuanganPieChart"></canvas>
                    </div>
                </div>
            </div>

            <?php 
            if (strtoupper($role_user) === 'ADMIN') {
                include 'buat_notifikasi.php'; 
            }
            ?>

        </main>
    </div>
</div>

<script>
   document.addEventListener("DOMContentLoaded", function() {
    const toggleBtn = document.getElementById("toggle-sidebar");
    const sidebar = document.getElementById("sidebar");
    const mainContent = document.getElementById("main-content");

    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            
            if (sidebar.style.marginLeft === "-260px") {
                sidebar.style.marginLeft = "0px";
                mainContent.style.width = "calc(100% - 260px)";
            } else {
                sidebar.style.marginLeft = "-260px";
                mainContent.style.width = "100%";
            }
        });
    } else {
        console.log("Error: Elemen sidebar atau tombol tidak ditemukan!");
    }
});

    const ctx = document.getElementById('keuanganPieChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Pemasukan (%)', 'Pengeluaran (%)'],
            datasets: [{
                data: [<?php echo $pemasukan_persen; ?>, <?php echo $pengeluaran_persen; ?>],
                backgroundColor: ['#2ecc71', '#e74c3c'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
</script>
</body>
</html>