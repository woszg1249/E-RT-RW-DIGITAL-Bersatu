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
    $tampilan_foto = "https://ui-avatars.com/api/?name=" . urlencode($nama_user) . "&background=3498db&color=fff";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Kas RT - RT Digital</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        
        .input-card {
            width: 100%;
            max-width: 650px;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            margin: 20px auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 700;
            color: #34495e;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #eef2f7;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
            outline: none;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #3498db;
        }
        .btn-simpan {
            width: 100%;
            padding: 15px;
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn-simpan:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            border: 1px solid #c3e6cb;
            font-weight: 600;
        }
    </style>
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
            <a href="dashboard.php" class="menu-item">
                <i class="fa-solid fa-table-columns"></i> Dashboard
            </a>
            <a href="kelola_warga.php" class="menu-item">
                <i class="fa-solid fa-users"></i> Kelola Data Warga
            </a>
            <a href="input_kas.php" class="menu-item active">
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

        <main class="content-body" style="display: flex; flex-direction: column; align-items: center;">
            
            <div class="input-card">
                <div style="text-align: center; margin-bottom: 30px;">
                    <h2 style="color: #2c3e50; font-weight: 800;">
                        <i class="fa-solid fa-money-bill-transfer" style="color: #3498db; margin-right: 10px;"></i>
                        Input Transaksi Kas
                    </h2>
                    <p style="color: #7f8c8d; font-size: 0.95rem;">Pastikan data pemasukan atau pengeluaran sudah benar.</p>
                </div>

                <?php if(isset($_GET['status']) && $_GET['status'] == 'berhasil'): ?>
                    <div class="alert-success">
                        <i class="fa-solid fa-check-double"></i> Transaksi kas berhasil dicatat ke database!
                    </div>
                <?php endif; ?>

                <form action="proses_kas.php" method="POST">
                    
                    <div class="form-group">
                        <label>Nama Warga Pembayar/Penerima</label>
                        <select name="nama_warga" required class="form-control">
                            <option value="">-- Pilih Warga --</option>
                            <?php
                            $warga = mysqli_query($koneksi, "SELECT nama FROM users ORDER BY nama ASC");
                            while($w = mysqli_fetch_array($warga)){
                                echo "<option value='".$w['nama']."'>".$w['nama']."</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Keterangan Transaksi</label>
                        <input type="text" name="keterangan" required class="form-control" placeholder="Contoh: Iuran Sampah, Perbaikan Atap, dll.">
                    </div>

                    <div style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label>Jenis Kas</label>
                            <select name="jenis_kas" required class="form-control">
                                <option value="masuk">Pemasukan (+)</option>
                                <option value="keluar">Pengeluaran (-)</option>
                            </select>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label>Jumlah (Rp)</label>
                            <input type="number" name="jumlah_kas" required class="form-control" placeholder="Angka saja (Cth: 50000)">
                        </div>
                    </div>

                    <button type="submit" name="simpan_kas" class="btn-simpan">
                        <i class="fa-solid fa-save"></i> Simpan Transaksi Kas
                    </button>

                </form>
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