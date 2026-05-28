<?php

include '../Koneksi.php'; 


if (isset($_POST['register'])) {
    $nama     = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $email    = mysqli_real_escape_string($koneksi, $_POST['email']);
    $password = mysqli_real_escape_string($koneksi, $_POST['password']);
    $alamat   = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $kelamin  = mysqli_real_escape_string($koneksi, $_POST['kelamin']); // Menangkap data jenis kelamin baru
    $no_hp    = mysqli_real_escape_string($koneksi, $_POST['no_hp']);
    $role     = "Warga"; 

    // Memasukkan variabel $kelamin ke dalam struktur kolom dan values query INSERT
    $query = "INSERT INTO users (nama, username, email, password, alamat, kelamin, no_hp, role) 
              VALUES ('$nama', '$username', '$email', '$password', '$alamat', '$kelamin', '$no_hp', '$role')";
    
    $insert = mysqli_query($koneksi, $query);

    if ($insert) {
        echo "<script>alert('Berhasil Daftar! Silakan Login.'); window.location='login.php';</script>";
    } else {
        echo "<script>alert('Gagal Daftar: " . mysqli_error($koneksi) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Akun - RT Digital</title>
    <link rel="stylesheet" href="css register/register.css">
</head>
<body>
    <div class="register-container">
        <form method="POST" action="">
            <h2>Daftar Akun Baru</h2>
            <input type="text" name="nama" placeholder="Nama Lengkap" required>
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="no_hp" placeholder="Nomor HP" required>
            <input type="text" name="alamat" placeholder="Alamat" required>
            
            <select name="kelamin" required style="width: 100%; padding: 12px; margin-bottom: 15px; border-radius: 5px; background-color: #1e2833; color: #fff; border: 1px solid #34495e; font-size: 14px; box-sizing: border-box; cursor: pointer;">
                <option value="" disabled selected style="color: #888;">-- Pilih Jenis Kelamin --</option>
                <option value="Laki-laki">Laki-laki</option>
                <option value="Perempuan">Perempuan</option>
            </select>

            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="register">Daftar Sekarang</button>
            <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
        </form>
    </div>
</body>
</html>