<?php
include "../Koneksi.php";
session_start();

// Proteksi ekstra: Hanya Admin yang bisa masuk halaman ini!
if (!isset($_SESSION['nama']) || strtoupper($_SESSION['role'] ?? 'WARGA') !== 'ADMIN') {
    echo "<script>alert('Akses Ditolak! Anda bukan Admin.'); window.location='dashboard.php';</script>";
    exit();
}

$nama_user = $_SESSION['nama']; 
$query_user = mysqli_query($koneksi, "SELECT * FROM users WHERE nama = '$nama_user'");
$data_user = mysqli_fetch_assoc($query_user);
$foto_db = $data_user['foto'] ?? "";
$tampilan_foto = (!empty($foto_db) && file_exists("../img/" . $foto_db)) ? "../img/" . $foto_db : "https://ui-avatars.com/api/?name=" . urlencode($nama_user) . "&background=4e73df&color=fff";


// ==========================================
// LOGIKA PROSES DATA (TAMBAH, UPDATE, HAPUS)
// ==========================================

// 1. Proses Tambah Tagihan
if (isset($_POST['simpan_tagihan'])) {
    $id_warga = $_POST['id_user'];
    $bulan_tahun = mysqli_real_escape_string($koneksi, $_POST['bulan_tahun']);
    $jumlah = $_POST['jumlah'];
    $status = $_POST['status_bayar'];

    $insert = mysqli_query($koneksi, "INSERT INTO tagihan (id_user, bulan_tahun, jumlah, status_bayar) VALUES ('$id_warga', '$bulan_tahun', '$jumlah', '$status')");
    
    if ($insert) {
        echo "<script>alert('Tagihan berhasil dikirim ke warga!'); window.location='kelola_tagihan.php';</script>";
    } else {
        echo "<script>alert('Gagal membuat tagihan!');</script>";
    }
}

// 2. Proses Update Status Bayar
if (isset($_POST['update_status'])) {
    $id_tagihan = $_POST['id_tagihan'];
    $status_baru = $_POST['status_bayar'];
    
    mysqli_query($koneksi, "UPDATE tagihan SET status_bayar = '$status_baru', tgl_update = CURRENT_TIMESTAMP WHERE id_tagihan = '$id_tagihan'");
    echo "<script>alert('Status pembayaran berhasil diperbarui!'); window.location='kelola_tagihan.php';</script>";
}

// 3. Proses Hapus Tagihan
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    mysqli_query($koneksi, "DELETE FROM tagihan WHERE id_tagihan = '$id_hapus'");
    echo "<script>alert('Tagihan dihapus!'); window.location='kelola_tagihan.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tagihan - RT Digital</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Styling tambahan khusus halaman ini */
        .form-card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .btn-primary { background: #4e73df; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; }
        .btn-primary:hover { background: #2e59d9; }
        .table-responsive { overflow-x: auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background-color: #f8f9fc; }
        .badge { padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: bold; color: white; }
        .bg-warning { background: #f6c23e; }
        .bg-danger { background: #e74a3b; }
        .bg-success { background: #1cc88a; }
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
            <a href="kelola_tagihan.php" class="menu-item active">
                <i class="fa-solid fa-file-invoice-dollar"></i> Kelola Tagihan
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
            <button id="toggle-sidebar" class="hamburger-btn"><i class="fa-solid fa-bars"></i></button>
        </div>

        <main class="content-body">
            <h2 style="margin-bottom: 20px;">Kelola Tagihan Iuran Warga</h2>

            <div class="form-card">
                <h3>Buat Tagihan Baru</h3>
                <hr style="margin: 10px 0; border: 0; border-top: 1px solid #eee;">
                <form action="" method="POST">
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <div class="form-group" style="flex: 1; min-width: 200px;">
                            <label>Pilih Warga</label>
                            <select name="id_user" class="form-control" required>
                                <option value="">-- Pilih Warga --</option>
                                <?php
                                // Ambil data warga dari database
                                $q_warga = mysqli_query($koneksi, "SELECT id, nama FROM users WHERE role = 'Warga'");
                                while ($w = mysqli_fetch_assoc($q_warga)) {
                                    echo "<option value='{$w['id']}'>{$w['nama']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 200px;">
                            <label>Bulan & Tahun (Contoh: Mei 2026)</label>
                            <input type="text" name="bulan_tahun" class="form-control" required>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 200px;">
                            <label>Jumlah (Rp)</label>
                            <input type="number" name="jumlah" class="form-control" value="50000" required>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 200px;">
                            <label>Status Awal</label>
                            <select name="status_bayar" class="form-control">
                                <option value="belum">Belum Bayar</option>
                                <option value="lunas">Sudah Lunas</option>
                                <option value="telat">Telat</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="simpan_tagihan" class="btn-primary"><i class="fa-solid fa-paper-plane"></i> Kirim Tagihan</button>
                </form>
            </div>

            <div class="table-responsive">
                <h3>Daftar Tagihan & Pembayaran</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Warga</th>
                            <th>Bulan/Tahun</th>
                            <th>Nominal</th>
                            <th>Status</th>
                            <th>Aksi Update</th>
                            <th>Hapus</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        // Join tabel tagihan dan users agar nama warga tampil
                        $q_tagihan = mysqli_query($koneksi, "SELECT t.*, u.nama FROM tagihan t JOIN users u ON t.id_user = u.id ORDER BY t.id_tagihan DESC");
                        
                        if(mysqli_num_rows($q_tagihan) > 0) {
                            while ($row = mysqli_fetch_assoc($q_tagihan)) {
                                // Tentukan warna badge
                                $badge_class = 'bg-warning';
                                if ($row['status_bayar'] == 'lunas') $badge_class = 'bg-success';
                                if ($row['status_bayar'] == 'telat') $badge_class = 'bg-danger';
                        ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td><?= $row['nama']; ?></td>
                            <td><?= $row['bulan_tahun']; ?></td>
                            <td>Rp <?= number_format($row['jumlah'], 0, ',', '.'); ?></td>
                            <td><span class="badge <?= $badge_class; ?>"><?= strtoupper($row['status_bayar']); ?></span></td>
                            
                            <td>
                                <form action="" method="POST" style="display: flex; gap: 5px;">
                                    <input type="hidden" name="id_tagihan" value="<?= $row['id_tagihan']; ?>">
                                    <select name="status_bayar" style="padding: 5px; border-radius: 3px; border: 1px solid #ccc;">
                                        <option value="belum" <?= ($row['status_bayar'] == 'belum') ? 'selected' : ''; ?>>Belum</option>
                                        <option value="lunas" <?= ($row['status_bayar'] == 'lunas') ? 'selected' : ''; ?>>Lunas</option>
                                        <option value="telat" <?= ($row['status_bayar'] == 'telat') ? 'selected' : ''; ?>>Telat</option>
                                    </select>
                                    <button type="submit" name="update_status" style="background:#f6c23e; border:none; padding:5px 10px; border-radius:3px; cursor:pointer;"><i class="fa-solid fa-save"></i></button>
                                </form>
                            </td>
                            <td>
                                <a href="?hapus=<?= $row['id_tagihan']; ?>" onclick="return confirm('Yakin hapus tagihan ini?');" style="color: #e74a3b;"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php 
                            }
                        } else {
                            echo "<tr><td colspan='7' style='text-align:center;'>Belum ada data tagihan.</td></tr>";
                        }
                        ?>
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