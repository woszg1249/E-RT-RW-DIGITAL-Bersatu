<?php
session_start();
include 'Koneksi.php';

if (!isset($_SESSION['nama'])) {
    header("Location: folder login,logout dan register/login.php");
    exit();
}

$nama_user = $_SESSION['nama'];

$query = mysqli_query($koneksi, "SELECT * FROM users WHERE nama = '$nama_user'");
$data = mysqli_fetch_assoc($query);

// Mengambil data foto dan kelamin dari database
$foto_db = $data['foto'] ?? "";
$kelamin_user = $data['kelamin'] ?? ""; 

if (!empty($foto_db) && file_exists("img/" . $foto_db)) {
    $tampilan_foto = "img/" . $foto_db;
} else {
    $tampilan_foto = "https://ui-avatars.com/api/?name=" . urlencode($nama_user) . "&background=random&color=fff";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Profil - RT Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        h2 { margin-bottom: 25px; color: #333; }
        
        .profile-wrapper { position: relative; display: inline-block; margin-bottom: 20px; }
        #preview-foto { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #3498db; }
        .upload-icon { position: absolute; bottom: 5px; right: 5px; background: #3498db; color: white; width: 35px; height: 35px; border-radius: 50%; display: flex; justify-content: center; align-items: center; cursor: pointer; border: 3px solid white; }
        
        .form-group { text-align: left; margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: bold; color: #666; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 10px; box-sizing: border-box; font-family: inherit; font-size: 14px; }
        
        .btn-simpan { background: #3498db; color: white; border: none; padding: 15px; width: 100%; border-radius: 10px; font-weight: bold; cursor: pointer; margin-top: 10px; transition: 0.3s; }
        .btn-simpan:hover { background: #2980b9; }
        .back-link { display: block; margin-top: 15px; color: #888; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>

<div class="card">
    <h2>Edit Profil</h2>
    
    <form action="proses_update_profil.php" method="POST" enctype="multipart/form-data">
        <div class="profile-wrapper">
            <img src="<?php echo $tampilan_foto; ?>" id="preview-foto">
            <label for="input-foto" class="upload-icon">
                <i class="fas fa-camera"></i>
            </label>
            <input type="file" name="foto" id="input-foto" style="display:none" accept="image/*" onchange="previewImage(this)">
        </div>

        <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" name="nama" value="<?php echo htmlspecialchars($data['nama']); ?>" required>
        </div>

        <div class="form-group">
            <label>Alamat Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($data['email']); ?>" required>
        </div>

        <div class="form-group">
            <label>Nomor Telepon / WA</label>
            <input type="text" name="no_hp" value="<?php echo htmlspecialchars($data['no_hp']); ?>" placeholder="0812xxxx">
        </div>

        <div class="form-group">
            <label>Alamat Rumah</label>
            <input type="text" name="alamat" value="<?php echo htmlspecialchars($data['alamat']); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Jenis Kelamin</label>
            <select name="kelamin" required>
                <option value="" disabled <?php if(empty($kelamin_user)) echo 'selected'; ?>>-- Pilih Jenis Kelamin --</option>
                <option value="Laki-laki" <?php if($kelamin_user === 'Laki-laki') echo 'selected'; ?>>Laki-laki</option>
                <option value="Perempuan" <?php if($kelamin_user === 'Perempuan') echo 'selected'; ?>>Perempuan</option>
            </select>
        </div>

        <button type="submit" name="update_profil" class="btn-simpan">Simpan Perubahan</button>
        <a href="folder dashboard/dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>
    </form>
</div>

<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview-foto').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

</body>
</html>