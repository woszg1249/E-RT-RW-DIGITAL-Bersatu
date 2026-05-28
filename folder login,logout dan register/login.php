<?php
// 1. Aktifkan laporan error agar PHP memberitahu jika ada salah ketik
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Koneksi ke database
include '../Koneksi.php'; 
session_start();

$error_message = "";

if (isset($_POST['login'])) {
    // Ambil data dan amankan dari SQL Injection
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = mysqli_real_escape_string($koneksi, $_POST['password']);

    // 3. Cek apakah koneksi database ada
    if (!$koneksi) {
        $error_message = "Koneksi Database Gagal: " . mysqli_connect_error();
    } else {
        // 4. Jalankan Query
        $query = mysqli_query($koneksi, "SELECT * FROM users WHERE username='$username' AND password='$password'");
        
        if (!$query) {
            $error_message = "Query Error: " . mysqli_error($koneksi);
        } else {
            if (mysqli_num_rows($query) > 0) {
                $data = mysqli_fetch_assoc($query);
                
                // Simpan Session
               // Simpan Session
$_SESSION['nama'] = $data['nama'];
$_SESSION['role'] = $data['role'];
$_SESSION['email'] = $data['email']; // <--- TAMBAHKAN BARIS INI
$_SESSION['foto'] = $data['foto'];
$_SESSION['id'] = $data['id'];
// 5. Redirect ke Dashboard
header("Location: ../folder dashboard/dashboard.php");
                
                // 5. Redirect ke Dashboard
                header("Location: ../folder dashboard/dashboard.php");
                exit();
            } else {
                // Pesan jika username/password tidak ditemukan di tabel
                $error_message = "Login Gagal! Username atau Password salah.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Sistem RT Digital</title>
    <link rel="stylesheet" href="css login/login.css">
    <style>
        /* Tambahan style untuk pesan error */
        .alert {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <form method="POST" action="">
            <h2>Login Sistem</h2>

            <?php if ($error_message != ""): ?>
                <div class="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>

            <button type="submit" name="login">Masuk</button>
            <a href="lupa_password.php">Lupa Password?</a>
            <p>Belum punya akun? <a href="register.php">Daftar di sini</a></p>
        </form>
    </div>

</body>
</html>