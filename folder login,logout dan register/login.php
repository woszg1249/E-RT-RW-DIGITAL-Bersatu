<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


include '../Koneksi.php'; 
session_start();

$error_message = "";

if (isset($_POST['login'])) {
    
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = mysqli_real_escape_string($koneksi, $_POST['password']);

    
    if (!$koneksi) {
        $error_message = "Koneksi Database Gagal: " . mysqli_connect_error();
    } else {
        
        $query = mysqli_query($koneksi, "SELECT * FROM users WHERE (username='$username' OR nama='$username') AND password='$password'");
        
        if (!$query) {
            $error_message = "Query Error: " . mysqli_error($koneksi);
        } else {
            if (mysqli_num_rows($query) > 0) {
                $data = mysqli_fetch_assoc($query);
                
                
                
                
                $_SESSION['nama'] = $data['nama'];
                $_SESSION['role'] = $data['role'];
                $_SESSION['email'] = $data['email']; 
                $_SESSION['foto'] = $data['foto']; 
                $_SESSION['id'] = $data['id'];
                $_SESSION['id_user'] = $data['id'];

                // Flash welcome message to show on dashboard after login
                $_SESSION['flash_welcome'] = "Selamat datang RT RW Digital Bersatu";

                header("Location: ../folder dashboard/dashboard.php");
                exit();
            } else {
                
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
    <title>Login - Sistem RT RW Digital Bersatu</title>
    <link rel="stylesheet" href="css login/login.css?v=2">
    <style>
        
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
        <form method="POST" action="" class="login-form">
            <div class="login-header">
                <h1>Selamat datang</h1>
                <h2>RT RW Digital Bersatu</h2>
                <p class="subtitle">Masuk untuk mengelola kas dan data warga</p>
            </div>

            <?php if ($error_message != ""): ?>
                <div class="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <input type="text" name="username" placeholder="Username atau Nama Lengkap" required>
            <input type="password" name="password" placeholder="Password" required>

            <button type="submit" name="login">Masuk</button>
            <a href="lupa_password.php">Lupa Password?</a>
            <p>Belum punya akun? <a href="register.php">Daftar di sini</a></p>
        </form>
    </div>

</body>
</html>