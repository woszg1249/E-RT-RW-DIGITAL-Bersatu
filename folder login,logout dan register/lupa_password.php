<?php
include '../Koneksi.php'; 

$message = "";
$status = "";

if (isset($_POST['cek_email'])) {
    $identifier = trim(mysqli_real_escape_string($koneksi, $_POST['identifier']));

    if ($identifier === '') {
        $status = "error";
        $message = "Silakan masukkan email atau nomor telepon yang terdaftar.";
    } else {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $query = mysqli_query($koneksi, "SELECT * FROM users WHERE email='$identifier'");
        } else {
            $query = mysqli_query($koneksi, "SELECT * FROM users WHERE no_hp='$identifier'");
        }

        if (mysqli_num_rows($query) > 0) {
            echo "<script>window.location.href='reset_password.php?identifier=" . urlencode($identifier) . "';</script>";
            exit();
        } else {
            $status = "error";
            $message = "Data tidak ditemukan. Pastikan email atau nomor telepon sudah terdaftar.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lupa Password - Keuangan RT</title>
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

        .error {
            color: #ef4444;
            font-size: 13px;
            margin-bottom: 10px;
        }

        a {
            display: block;
            margin-top: 15px;
            color: #0b69a3;
            text-decoration: none;
            font-size: 13px;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>Lupa Password</h2>
        <p style="color: #64748b; font-size: 14px;">Masukkan email atau nomor telepon terdaftar.</p>
        
        <?php if ($message !== ""): ?>
            <div class="error"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="identifier" placeholder="Email atau Nomor Telepon" required>
            <button type="submit" name="cek_email">Verifikasi</button>
        </form>
        
        <a href="login.php">← Kembali ke Login</a>
    </div>
</body>
</html>