<?php
include '../Koneksi.php'; 

$email = $_GET['email'] ?? '';
$message = "";

if (isset($_POST['reset'])) {
    $pass_baru = mysqli_real_escape_string($koneksi, $_POST['password']);
    
    // Update password di database
    $update = mysqli_query($koneksi, "UPDATE users SET password='$pass_baru' WHERE email='$email'");
    
    if ($update) {
        echo "<script>
                alert('Password berhasil diganti! Silakan login kembali.'); 
                window.location.href = 'login.php'; 
              </script>";
        exit();
    } else {
        $message = "Gagal mereset password. Silakan coba lagi.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Keuangan RT</title>
    <style>
        body { background-color: #1e293b; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; font-family: sans-serif; }
        .card { background: white; padding: 40px; border-radius: 15px; width: 350px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background-color: #10b981; border: none; color: white; border-radius: 8px; cursor: pointer; font-weight: bold; margin-top: 10px; }
        button:hover { background-color: #059669; }
        .info { color: #64748b; font-size: 13px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="color: #334155;">Buat Password Baru</h2>
        <p class="info">Untuk email: <b><?php echo htmlspecialchars($email); ?></b></p>
        
        <form method="POST">
            <input type="password" name="password" placeholder="Masukkan Password Baru" required minlength="5">
            <button type="submit" name="reset">Simpan Password Baru</button>
        </form>
    </div>
</body>
</html>