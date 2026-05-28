<?php
include '../Koneksi.php'; 

$message = "";
$status = "";

if (isset($_POST['cek_email'])) {
    $email = mysqli_real_escape_string($koneksi, $_POST['email']);
    
    // Cek apakah email terdaftar
    $query = mysqli_query($koneksi, "SELECT * FROM users WHERE email='$email'");
    
    if (mysqli_num_rows($query) > 0) {
        // Pindah ke halaman reset menggunakan JavaScript agar aman dari header error
        echo "<script>window.location.href='reset_password.php?email=$email';</script>";
        exit();
    } else {
        $status = "error";
        $message = "Email tidak ditemukan dalam sistem kami.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lupa Password - Keuangan RT</title>
    <style>
        body { background-color: #1e293b; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; font-family: sans-serif; }
        .card { background: white; padding: 40px; border-radius: 15px; width: 350px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background-color: #38bdf8; border: none; color: white; border-radius: 8px; cursor: pointer; font-weight: bold; }
        button:hover { background-color: #0ea5e9; }
        .error { color: #ef4444; font-size: 13px; margin-bottom: 10px; }
        a { display: block; margin-top: 15px; color: #64748b; text-decoration: none; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Lupa Password</h2>
        <p style="color: #64748b; font-size: 14px;">Masukkan email akun RT Anda</p>
        
        <?php if ($message !== ""): ?>
            <div class="error"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="email" name="email" placeholder="Email Terdaftar" required>
            <button type="submit" name="cek_email">Verifikasi Email</button>
        </form>
        
        <a href="login.php">← Kembali ke Login</a>
    </div>
</body>
</html>