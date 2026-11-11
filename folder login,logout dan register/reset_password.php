<?php
session_start();
include '../Koneksi.php'; 

$message = "";

if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_user_name']) || !isset($_SESSION['reset_timestamp'])) {
    echo "<script>alert('Sesi reset password tidak valid. Silakan mulai ulang dari lupa password.'); window.location.href = 'lupa_password.php';</script>";
    exit();
}

$user_id = $_SESSION['reset_user_id'];
$user_name = $_SESSION['reset_user_name'];
$reset_time = $_SESSION['reset_timestamp'];

if ((time() - $reset_time) > 900) { // 15 menit
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_user_name']);
    unset($_SESSION['reset_timestamp']);
    echo "<script>alert('Sesi reset password telah kedaluwarsa. Silakan mulai ulang dari lupa password.'); window.location.href = 'lupa_password.php';</script>";
    exit();
}

if (isset($_POST['reset'])) {
    $password = trim($_POST['password']);
    
    if ($password === '') {
        $message = "Password tidak boleh kosong.";
    } elseif (strlen($password) < 6) {
        $message = "Password minimal 6 karakter.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = mysqli_prepare($koneksi, "UPDATE users SET password = ? WHERE id = ? LIMIT 1");
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $password_hash, $user_id);
            $update = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            if ($update) {
                // Hapus session setelah berhasil
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_user_name']);
                unset($_SESSION['reset_timestamp']);
                
                echo "<script>
                        alert('Password berhasil diganti! Silakan login kembali.'); 
                        window.location.href = 'login.php'; 
                      </script>";
                exit();
            } else {
                $message = "Gagal mereset password. Silakan coba lagi.";
            }
        } else {
            $message = "Gagal mereset password. Silakan coba lagi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Keuangan RT</title>
    <style>
        body {
            background: linear-gradient(135deg, #e6f7ff 0%, #dff6ff 60%);
            font-family: 'Segoe UI', Tahoma, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            -webkit-font-smoothing: antialiased;
        }

        .card {
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(10px) saturate(130%);
            -webkit-backdrop-filter: blur(10px) saturate(130%);
            padding: 32px 28px 28px 28px;
            border-radius: 18px;
            width: 380px;
            max-width: 94vw;
            text-align: center;
            box-shadow: 0 10px 30px rgba(3,22,52,0.08);
            border: 1px solid rgba(15,105,163,0.12);
        }

        input {
            width: 100%;
            padding: 14px 16px;
            margin: 10px 0;
            border: 1px solid rgba(5,106,168,0.16);
            background: rgba(255,255,255,0.72);
            border-radius: 12px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 200ms ease, box-shadow 200ms ease, background-color 200ms ease;
        }

        input:focus {
            outline: none;
            border-color: rgba(5,106,168,0.3);
            box-shadow: 0 6px 18px rgba(6,78,120,0.06);
            background: rgba(255,255,255,0.88);
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(90deg, #7dd3fc 0%, #38bdf8 100%);
            color: #06283a;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 800;
            margin-top: 14px;
            box-shadow: 0 8px 22px rgba(56,189,248,0.18);
            transition: transform 180ms ease, box-shadow 180ms ease, filter 180ms ease;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(56,189,248,0.22);
            filter: brightness(0.98);
        }

        .info {
            color: #475569;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .error {
            color: #ef4444;
            font-size: 13px;
            margin-bottom: 10px;
            background: rgba(254, 242, 242, 0.8);
            padding: 10px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="color: #334155;">Buat Password Baru</h2>
        <p class="info">Untuk akun: <strong><?php echo htmlspecialchars($user_name); ?></strong></p>
        <?php if ($message !== ""): ?>
            <p class="error"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        
        <form method="POST">
            <input type="password" name="password" placeholder="Masukkan Password Baru (minimal 6 karakter)" required minlength="6">
            <button type="submit" name="reset">Simpan Password Baru</button>
        </form>
    </div>
</body>
</html>