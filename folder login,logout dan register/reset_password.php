<?php
include '../Koneksi.php'; 

$identifier = mysqli_real_escape_string($koneksi, $_POST['identifier'] ?? $_GET['identifier'] ?? '');
$message = "";

if ($identifier === '') {
    echo "<script>alert('Data tidak valid. Silakan ulangi proses lupa password.'); window.location.href = 'lupa_password.php';</script>";
    exit();
}

if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
    $user_check = mysqli_query($koneksi, "SELECT * FROM users WHERE email='$identifier'");
    $label = 'email';
} else {
    $user_check = mysqli_query($koneksi, "SELECT * FROM users WHERE no_hp='$identifier'");
    $label = 'telepon';
}

if (mysqli_num_rows($user_check) === 0) {
    echo "<script>alert('Data tidak valid. Silakan ulangi proses lupa password.'); window.location.href = 'lupa_password.php';</script>";
    exit();
}

$user_data = mysqli_fetch_assoc($user_check);
$user_id = $user_data['id'];
$user_display = ($label === 'email') ? htmlspecialchars($user_data['email']) : htmlspecialchars($user_data['no_hp']);

if (isset($_POST['reset'])) {
    $pass_baru = mysqli_real_escape_string($koneksi, $_POST['password']);

    $update = mysqli_query($koneksi, "UPDATE users SET password='$pass_baru' WHERE id='$user_id'");

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
    </style>
</head>
<body>
    <div class="card">
        <h2 style="color: #334155;">Buat Password Baru</h2>
        <p class="info">Untuk <b><?php echo $label === 'email' ? 'email' : 'nomor telepon'; ?></b>: <b><?php echo $user_display; ?></b></p>
        <?php if ($message !== ""): ?>
            <p class="info" style="color: #dc2626; margin-bottom: 16px;"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="identifier" value="<?php echo htmlspecialchars($identifier); ?>">
            <input type="password" name="password" placeholder="Masukkan Password Baru" required minlength="5">
            <button type="submit" name="reset">Simpan Password Baru</button>
        </form>
    </div>
</body>
</html>