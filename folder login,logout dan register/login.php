<?php

include '../Koneksi.php'; 
session_start();

$error_message = "";

if (isset($_POST['login'])) {
    
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!$koneksi) {
        $error_message = "Koneksi database gagal. Silakan coba lagi.";
    } else {
        
        $stmt = mysqli_prepare($koneksi, "SELECT id, nama, username, email, password, role, foto FROM users WHERE username = ? OR nama = ? LIMIT 1");
        
        if (!$stmt) {
            $error_message = "Login gagal. Silakan coba lagi.";
        } else {
            mysqli_stmt_bind_param($stmt, "ss", $username, $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $data = mysqli_fetch_assoc($result);
                
                $password_valid = false;
                
                if (password_verify($password, $data['password'])) {
                    $password_valid = true;
                } elseif (hash_equals($data['password'], $password)) {
                    $password_valid = true;
                    
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = mysqli_prepare($koneksi, "UPDATE users SET password = ? WHERE id = ? LIMIT 1");
                    if ($update_stmt) {
                        mysqli_stmt_bind_param($update_stmt, "si", $new_hash, $data['id']);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                    }
                }
                
                if ($password_valid) {
                    session_regenerate_id(true);
                    
                    $_SESSION['nama'] = $data['nama'];
                    $_SESSION['role'] = $data['role'];
                    $_SESSION['email'] = $data['email']; 
                    $_SESSION['foto'] = $data['foto']; 
                    $_SESSION['id'] = $data['id'];
                    $_SESSION['id_user'] = $data['id'];

                    $_SESSION['flash_welcome'] = "Selamat datang RT RW Digital Bersatu";

                    mysqli_stmt_close($stmt);
                    header("Location: ../folder dashboard/dashboard.php");
                    exit();
                } else {
                    $error_message = "Login Gagal! Username atau Password salah.";
                }
            } else {
                $error_message = "Login Gagal! Username atau Password salah.";
            }
            
            mysqli_stmt_close($stmt);
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
            <p style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed #cbd5e1; font-size: 0.85rem;">
                <a href="../index.php" style="color: #0284c7; text-decoration: none; font-weight: 600;">
                    &larr; Kembali ke Beranda & Panduan
                </a>
            </p>
        </form>
    </div>

</body>
</html>