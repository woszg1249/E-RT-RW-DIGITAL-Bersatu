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


$query = mysqli_query($koneksi, "SELECT users.*, (SELECT SUM(jumlah) FROM keuangan WHERE keuangan.nama_warga = users.nama) as total FROM users");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data Warga - RT Digital</title>
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
            <a href="kelola_warga.php" class="menu-item active">
                <i class="fa-solid fa-users"></i> Kelola Data Warga
            </a>
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
            
            <div style="padding: 30px; background: white; border-radius: 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                <h2 style="color: #2c3e50; margin-bottom: 20px; font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; gap: 10px;">
                    <i class="fa fa-users" style="color: #3498db;"></i> Kelola Data & Akun Warga
                </h2>
                
                <hr style="border: 0; height: 1px; background: #eee; margin-bottom: 25px;">

                <table style="width: 100%; border-collapse: collapse; font-family: 'Segoe UI', sans-serif;">
                    <thead>
                        <tr style="background-color: #34495e; color: white; text-align: left;">
                            <th style="padding: 15px;">No</th>
                            <th style="padding: 15px;">Nama Warga</th>
                            <th style="padding: 15px;">Alamat</th>
                            <th style="padding: 15px;">No. HP</th>
                            <th style="padding: 15px;">Status Akun</th>
                            <th style="padding: 15px; text-align: center;">Total Kas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1; 
                        while($d = mysqli_fetch_array($query)) { 
                        ?>
                        <tr style="border-bottom: 1px solid #eee; transition: 0.2s;" onmouseover="this.style.backgroundColor='#f9f9f9'" onmouseout="this.style.backgroundColor='transparent'">
                            <td style="padding: 15px;"><?php echo $no++; ?></td>
                            <td style="padding: 15px; font-weight: bold; color: #34495e;"><?php echo $d['nama']; ?></td>
                            <td style="padding: 15px; color: #7f8c8d;"><?php echo $d['alamat'] ?? '-'; ?></td>
                            <td style="padding: 15px; color: #7f8c8d;"><?php echo $d['no_hp'] ?? '-'; ?></td>
                            <td style="padding: 15px;">
                                <span style="padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; background-color: #e8f4fd; color: #3498db; font-weight: 600;">
                                    <?php echo ucfirst($d['role']); ?>
                                </span>
                            </td>
                            <td style="padding: 15px; text-align: center; font-weight: bold; color: #2ecc71;">
                                Rp <?php echo number_format($d['total'] ?? 0, 0, ',', '.'); ?>
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