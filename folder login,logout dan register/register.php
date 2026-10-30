<?php
include '../Koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$successMessage = '';
$errorMessage = '';

if (isset($_POST['register'])) {
    $nama = mysqli_real_escape_string($koneksi, trim($_POST['nama'] ?? ''));
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username'] ?? ''));
    $email = mysqli_real_escape_string($koneksi, trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $alamat = mysqli_real_escape_string($koneksi, trim($_POST['alamat'] ?? ''));
    $kelamin = mysqli_real_escape_string($koneksi, trim($_POST['kelamin'] ?? ''));
    $no_hp = mysqli_real_escape_string($koneksi, trim($_POST['no_hp'] ?? ''));
    $nik = mysqli_real_escape_string($koneksi, trim($_POST['nik'] ?? ''));
    $no_kk = mysqli_real_escape_string($koneksi, trim($_POST['no_kk'] ?? ''));
    $role = 'Warga';

    if (!preg_match('/^\d{16}$/', $nik)) {
        $errorMessage = 'NIK harus terdiri dari 16 digit angka.';
    } elseif (!preg_match('/^\d{16}$/', $no_kk)) {
        $errorMessage = 'Nomor KK harus terdiri dari 16 digit angka.';
    } else {
        $checkUser = mysqli_query($koneksi, "SELECT id FROM users WHERE username = '$username' OR email = '$email' LIMIT 1");
        if (mysqli_num_rows($checkUser) > 0) {
            $errorMessage = 'Username atau email sudah terdaftar.';
        } else {
            $query = "INSERT INTO users (nama, username, email, password, alamat, kelamin, no_hp, nik, no_kk, role) VALUES ('$nama', '$username', '$email', '$password', '$alamat', '$kelamin', '$no_hp', '$nik', '$no_kk', '$role')";
            $insert = mysqli_query($koneksi, $query);

            if ($insert) {
                $successMessage = 'Berhasil mendaftar. Silakan login.';
                echo "<script>alert('Berhasil Daftar! Silakan Login.'); window.location='login.php';</script>";
                exit();
            } else {
                $errorMessage = 'Gagal daftar: ' . mysqli_error($koneksi);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - RT RW Digital Bersatu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="css register/register.css?v=<?php echo file_exists(__DIR__ . '/css register/register.css') ? filemtime(__DIR__ . '/css register/register.css') : '2'; ?>">
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <span class="brand-badge"><i class="fa-solid fa-user-plus"></i> Registrasi Warga</span>
                <h1>Buat Akun</h1>
                <p>Lengkapi data di bawah ini untuk bergabung sebagai warga RT RW Digital Bersatu</p>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div class="alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo htmlspecialchars($errorMessage); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm" novalidate>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="nama"><i class="fa-regular fa-user"></i> Nama Lengkap</label>
                        <input type="text" id="nama" name="nama" placeholder="Nama Lengkap sesuai KTP" value="<?php echo htmlspecialchars($_POST['nama'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="username"><i class="fa-solid fa-at"></i> Username</label>
                        <input type="text" id="username" name="username" placeholder="Username untuk login" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="nik"><i class="fa-regular fa-id-card"></i> NIK (16 Digit)</label>
                        <input type="text" id="nik" name="nik" maxlength="16" placeholder="Contoh: 3201010101010101" inputmode="numeric" value="<?php echo htmlspecialchars($_POST['nik'] ?? ''); ?>" required>
                        <span class="form-hint" id="nik-hint">Wajib 16 digit angka</span>
                    </div>

                    <div class="form-group">
                        <label for="no_kk"><i class="fa-solid fa-users-rectangle"></i> Nomor KK (16 Digit)</label>
                        <input type="text" id="no_kk" name="no_kk" maxlength="16" placeholder="Contoh: 3201010101010101" inputmode="numeric" value="<?php echo htmlspecialchars($_POST['no_kk'] ?? ''); ?>" required>
                        <span class="form-hint" id="kk-hint">Wajib 16 digit angka</span>
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fa-regular fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email" placeholder="contoh@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="no_hp"><i class="fa-brands fa-whatsapp"></i> Nomor HP / WA</label>
                        <input type="text" id="no_hp" name="no_hp" placeholder="08xxxxxxxxxx" value="<?php echo htmlspecialchars($_POST['no_hp'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="kelamin"><i class="fa-solid fa-venus-mars"></i> Jenis Kelamin</label>
                        <select id="kelamin" name="kelamin" required>
                            <option value="" disabled <?php echo empty($_POST['kelamin']) ? 'selected' : ''; ?>>-- Pilih Jenis Kelamin --</option>
                            <option value="Laki-laki" <?php echo (($_POST['kelamin'] ?? '') === 'Laki-laki') ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="Perempuan" <?php echo (($_POST['kelamin'] ?? '') === 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
                        <input type="password" id="password" name="password" placeholder="Minimal 6 karakter" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="alamat"><i class="fa-solid fa-location-dot"></i> Alamat Rumah</label>
                        <input type="text" id="alamat" name="alamat" placeholder="Jl. Mawar No. 12 RT 01 / RW 02" value="<?php echo htmlspecialchars($_POST['alamat'] ?? ''); ?>" required>
                    </div>
                </div>

                <button type="submit" name="register" class="btn-register">
                    <i class="fa-solid fa-user-plus"></i> Daftar Sekarang
                </button>
            </form>

            <p class="register-footer">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('registerForm');
            const nikInput = document.getElementById('nik');
            const noKkInput = document.getElementById('no_kk');
            const nikHint = document.getElementById('nik-hint');
            const kkHint = document.getElementById('kk-hint');

            const validate16Digits = (input, hint, label) => {
                const value = input.value.trim();
                if (value.length === 16 && /^\d{16}$/.test(value)) {
                    input.setCustomValidity('');
                    hint.style.color = '#16a34a';
                    hint.textContent = '✓ 16 digit valid';
                    return true;
                } else {
                    input.setCustomValidity('Harus tepat 16 digit angka');
                    hint.style.color = value.length > 0 ? '#dc2626' : '#64748b';
                    hint.textContent = value.length > 0 ? (label + ' saat ini ' + value.length + '/16 digit') : 'Wajib 16 digit angka';
                    return false;
                }
            };

            nikInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^\d]/g, '').slice(0, 16);
                validate16Digits(this, nikHint, 'NIK');
            });

            noKkInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^\d]/g, '').slice(0, 16);
                validate16Digits(this, kkHint, 'KK');
            });

            form.addEventListener('submit', function (event) {
                const isNikValid = validate16Digits(nikInput, nikHint, 'NIK');
                const isNoKkValid = validate16Digits(noKkInput, kkHint, 'KK');
                if (!isNikValid || !isNoKkValid) {
                    event.preventDefault();
                    if (!isNikValid) nikInput.focus();
                    else noKkInput.focus();
                }
            });
        });
    </script>
</body>
</html>
