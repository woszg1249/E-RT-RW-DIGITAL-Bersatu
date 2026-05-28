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
$foto_db = $data_user['foto'] ?? "";

if (!empty($foto_db) && file_exists("../img/" . $foto_db)) {
    $tampilan_foto = "../img/" . $foto_db;
} else {
    $tampilan_foto = "https://ui-avatars.com/api/?name=" . urlencode($nama_user) . "&background=4e73df&color=fff";
}

$q_masuk = mysqli_query($koneksi, "SELECT SUM(jumlah) AS total FROM keuangan WHERE jumlah > 0");
$d_masuk = mysqli_fetch_assoc($q_masuk);
$total_masuk = $d_masuk['total'] ?? 0;

$q_keluar = mysqli_query($koneksi, "SELECT SUM(jumlah) AS total FROM keuangan WHERE jumlah < 0");
$d_keluar = mysqli_fetch_assoc($q_keluar);
$total_keluar = abs($d_keluar['total'] ?? 0); // Diubah ke positif untuk hitungan rumus matematika

$saldo_akhir = $total_masuk - $total_keluar;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - RT Digital</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="dashboard-wrapper">
    
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            RT DIGITAL
        </div>
        
        <div class="sidebar-profile">
            <div class="profile-avatar-container">
                <img src="<?php echo $tampilan_foto; ?>" class="sidebar-avatar" alt="User Profile">
            </div>
            <div class="sidebar-username"><?php echo $nama_user; ?></div>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="fa-solid fa-table-columns"></i> Dashboard
            </a>
            <a href="kelola_warga.php" class="menu-item">
                <i class="fa-solid fa-users"></i> Kelola Data Warga
            </a>
            <a href="input_kas.php" class="menu-item">
                <i class="fa-solid fa-file-pen"></i> Input Kas RT
            </a>
            <a href="laporan.php" class="menu-item active">
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
            
            <div style="background: white; padding: 25px; border-radius: 18px; box-shadow: 0 4px 8px rgba(0,0,0,0.03); margin-bottom: 25px; border-left: 6px solid #2ecc71;">
                <p style="color: #7f8c8d; font-weight: bold; margin-bottom: 5px; font-family: 'Segoe UI', sans-serif;">SALDO KAS RT SAAT INI</p>
                <h1 style="font-size: 2.5rem; color: #2c3e50; font-family: 'Segoe UI', sans-serif;">Rp <?php echo number_format($saldo_akhir, 0, ',', '.'); ?></h1>
            </div>

            <div style="padding: 30px; background: white; border-radius: 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                <h2 style="color: #2c3e50; margin-bottom: 20px; font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-file-invoice-dollar" style="color: #3498db;"></i> Jurnal & Laporan Keuangan RT
                </h2>
                
                <hr style="border: 0; height: 1px; background: #eee; margin-bottom: 25px;">

                <table style="width: 100%; border-collapse: collapse; font-family: 'Segoe UI', sans-serif;">
                    <thead>
                        <tr style="background-color: #34495e; color: white; text-align: left;">
                            <th style="padding: 15px;">No</th>
                            <th style="padding: 15px;">Nama Warga</th>
                            <th style="padding: 15px;">Keterangan Transaksi</th>
                            <th style="padding: 15px;">Jenis</th>
                            <th style="padding: 15px; text-align: right;">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1; 
                        $data = mysqli_query($koneksi, "SELECT * FROM keuangan ORDER BY id DESC");
                        while($d = mysqli_fetch_array($data)) { 
                            $is_masuk = ($d['jenis'] == 'masuk');
                        ?>
                        <tr style="border-bottom: 1px solid #eee; transition: 0.2s;" onmouseover="this.style.backgroundColor='#f9f9f9'" onmouseout="this.style.backgroundColor='transparent'">
                            <td style="padding: 15px;"><?php echo $no++; ?></td>
                            <td style="padding: 15px; font-weight: 600; color: #2c3e50;"><?php echo $d['nama_warga']; ?></td>
                            <td style="padding: 15px; color: #555;"><?php echo $d['keterangan']; ?></td>
                            <td style="padding: 15px;">
                                <span style="padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; background-color: <?php echo $is_masuk ? '#e8f8f5' : '#fdedec'; ?>; color: <?php echo $is_masuk ? '#2ecc71' : '#e74c3c'; ?>;">
                                    <?php echo $is_masuk ? 'Pemasukan' : 'Pengeluaran'; ?>
                                </span>
                            </td>
                            <td style="padding: 15px; text-align: right; font-weight: bold; color: <?php echo $is_masuk ? '#2ecc71' : '#e74c3c'; ?>;">
                                <?php echo $is_masuk ? '+' : '-'; ?> Rp <?php echo number_format($d['jumlah'], 0, ',', '.'); ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

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
        }
    });
</script>
</body>
</html>