<?php
session_start();
include '../Koneksi.php'; 

$message = "";
$is_blocked = false;

if (isset($_POST['verify'])) {
    $identifier = trim($_POST['identifier']);
    $nik_last4 = trim($_POST['nik_last4']);

    if ($identifier === '' || $nik_last4 === '') {
        $message = "Data verifikasi tidak lengkap. Silakan coba lagi.";
    } elseif (!preg_match('/^\d{4}$/', $nik_last4)) {
        $message = "Data verifikasi tidak valid. Silakan coba lagi.";
    } else {
        $is_email = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        
        if ($is_email) {
            $stmt = mysqli_prepare($koneksi, "SELECT id, nama, email, nik, reset_attempts, reset_locked_until FROM users WHERE email = ? LIMIT 1");
        } else {
            $stmt = mysqli_prepare($koneksi, "SELECT id, nama, no_hp, nik, reset_attempts, reset_locked_until FROM users WHERE no_hp = ? LIMIT 1");
        }
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $identifier);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $user = mysqli_fetch_assoc($result);
                
                if ($user['reset_locked_until'] !== null && strtotime($user['reset_locked_until']) > time()) {
                    $message = "Terlalu banyak percobaan. Silakan coba lagi nanti.";
                    $is_blocked = true;
                    mysqli_stmt_close($stmt);
                } elseif (strlen($user['nik']) >= 4 && substr($user['nik'], -4) === $nik_last4) {
                    $stmt_reset = mysqli_prepare($koneksi, "UPDATE users SET reset_attempts = 0, reset_locked_until = NULL WHERE id = ?");
                    if ($stmt_reset) {
                        mysqli_stmt_bind_param($stmt_reset, "i", $user['id']);
                        mysqli_stmt_execute($stmt_reset);
                        mysqli_stmt_close($stmt_reset);
                    }
                    
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_user_name'] = $user['nama'];
                    $_SESSION['reset_timestamp'] = time();
                    unset($_SESSION['reset_fail_count']);
                    
                    mysqli_stmt_close($stmt);
                    header("Location: reset_password.php");
                    exit();
                } else {
                    $new_attempts = (int)$user['reset_attempts'] + 1;
                    
                    if ($new_attempts >= 5) {
                        $lock_until = date('Y-m-d H:i:s', time() + 900);
                        $stmt_lock = mysqli_prepare($koneksi, "UPDATE users SET reset_attempts = ?, reset_locked_until = ? WHERE id = ?");
                        if ($stmt_lock) {
                            mysqli_stmt_bind_param($stmt_lock, "isi", $new_attempts, $lock_until, $user['id']);
                            mysqli_stmt_execute($stmt_lock);
                            mysqli_stmt_close($stmt_lock);
                        }
                        $message = "Terlalu banyak percobaan. Silakan coba lagi nanti.";
                        $is_blocked = true;
                    } else {
                        $stmt_inc = mysqli_prepare($koneksi, "UPDATE users SET reset_attempts = ? WHERE id = ?");
                        if ($stmt_inc) {
                            mysqli_stmt_bind_param($stmt_inc, "ii", $new_attempts, $user['id']);
                            mysqli_stmt_execute($stmt_inc);
                            mysqli_stmt_close($stmt_inc);
                        }
                        $message = "Data verifikasi tidak cocok. Silakan coba lagi.";
                    }
                    
                    if (!isset($_SESSION['reset_fail_count'])) {
                        $_SESSION['reset_fail_count'] = 0;
                    }
                    $_SESSION['reset_fail_count']++;
                }
            } else {
                if (!isset($_SESSION['reset_fail_count'])) {
                    $_SESSION['reset_fail_count'] = 0;
                }
                $_SESSION['reset_fail_count']++;
                
                if ($_SESSION['reset_fail_count'] >= 5) {
                    $message = "Terlalu banyak percobaan. Silakan coba lagi nanti.";
                    $is_blocked = true;
                } else {
                    $message = "Data verifikasi tidak cocok. Silakan coba lagi.";
                }
            }
            
            mysqli_stmt_close($stmt);
        } else {
            $message = "Terjadi kesalahan sistem. Silakan coba lagi.";
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
            background: rgba(254, 242, 242, 0.8);
            padding: 10px;
            border-radius: 8px;
        }

        .info {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.5;
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
        <p class="info">Masukkan email atau nomor telepon Anda dan <strong>4 digit terakhir NIK</strong> untuk verifikasi identitas.</p>
        
        <?php if ($message !== ""): ?>
            <div class="error"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (!$is_blocked): ?>
        <form method="POST">
            <input type="text" name="identifier" placeholder="Email atau Nomor Telepon" value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>" required>
            <input type="text" name="nik_last4" placeholder="4 Digit Terakhir NIK" maxlength="4" pattern="\d{4}" inputmode="numeric" value="<?php echo htmlspecialchars($_POST['nik_last4'] ?? ''); ?>" required>
            <button type="submit" name="verify">Verifikasi & Lanjutkan</button>
        </form>
        <?php endif; ?>
        
        <a href="login.php">← Kembali ke Login</a>
    </div>
</body>
</html>