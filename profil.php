<?php
session_start();
include 'Koneksi.php';

if (!isset($_SESSION['nama'])) {
    header("Location: folder login,logout dan register/login.php");
    exit();
}

$nama_user = $_SESSION['nama'];

$user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

if ($user_id > 0) {
    $query = mysqli_query($koneksi, "SELECT * FROM users WHERE id = '$user_id' LIMIT 1");
} else {
    $query = mysqli_query($koneksi, "SELECT * FROM users WHERE nama = '" . mysqli_real_escape_string($koneksi, $nama_user) . "' LIMIT 1");
}

if (!$query || mysqli_num_rows($query) === 0) {
    echo "<script>alert('Data user tidak ditemukan. Silakan login ulang.'); window.location='folder login,logout dan register/login.php';</script>";
    exit();
}

$data = mysqli_fetch_assoc($query);

$foto_db = $data['foto'] ?? "";
$kelamin_user = $data['kelamin'] ?? "";
$nik_user = $data['nik'] ?? "";
$no_kk_user = $data['no_kk'] ?? "";

if (!empty($foto_db) && file_exists("img/" . $foto_db)) {
    $tampilan_foto = "img/" . $foto_db;
} else {
    $tampilan_foto = "https://ui-avatars.com/api/?name=" . urlencode($nama_user) . "&background=0284C7&color=fff";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil - RT RW Digital Bersatu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/modern-theme.css">
</head>
<body class="profile-page">
    <div class="auth-shell profile-shell">
        <div class="profile-card">
            <div class="page-header profile-header">
                <div>
                    <h1><i class="fa-solid fa-user-gear"></i> Edit Profil</h1>
                </div>
            </div>

            <form action="proses_update_profil.php" method="POST" enctype="multipart/form-data" class="form-grid profile-form" id="profileForm" novalidate>
                <input type="hidden" name="user_id" value="<?php echo (int)($data['id'] ?? 0); ?>">
                <div class="profile-layout">
                    <div class="profile-identity">
                        <div class="avatar-wrap">
                            <img src="<?php echo htmlspecialchars($tampilan_foto); ?>" id="preview-foto" alt="Foto profil">
                            <label for="input-foto" title="Ubah foto profil">
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="file" name="foto" id="input-foto" style="display:none" accept="image/*" onchange="previewImage(this)">
                        </div>
                        <div class="profile-summary">
                            <h3><?php echo htmlspecialchars($data['nama']); ?></h3>
                            <p>Kelola data akun dan identitas warga</p>
                        </div>
                    </div>

                    <div class="profile-fields">
                        <div class="form-field">
                            <label>Nama Lengkap</label>
                            <input type="text" name="nama" value="<?php echo htmlspecialchars($data['nama']); ?>" required>
                        </div>

                        <div class="form-grid side-by-side">
                            <div class="form-field">
                                <label>NIK (16 Digit)</label>
                                <input type="text" id="nik" name="nik" value="<?php echo htmlspecialchars($nik_user); ?>" maxlength="16" inputmode="numeric" placeholder="16 digit angka">
                            </div>
                            <div class="form-field">
                                <label>Nomor KK (16 Digit)</label>
                                <input type="text" id="no_kk" name="no_kk" value="<?php echo htmlspecialchars($no_kk_user); ?>" maxlength="16" inputmode="numeric" placeholder="16 digit angka">
                            </div>
                        </div>

                        <div class="form-grid side-by-side">
                            <div class="form-field">
                                <label>Alamat Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($data['email']); ?>" required>
                            </div>
                            <div class="form-field">
                                <label>Nomor Telepon / WA</label>
                                <input type="text" name="no_hp" value="<?php echo htmlspecialchars($data['no_hp']); ?>" placeholder="0812xxxx">
                            </div>
                        </div>

                        <div class="form-field">
                            <label>Alamat Rumah</label>
                            <input type="text" name="alamat" value="<?php echo htmlspecialchars($data['alamat']); ?>" required>
                        </div>

                        <div class="form-field">
                            <label>Jenis Kelamin</label>
                            <select name="kelamin" required>
                                <option value="" disabled <?php if (empty($kelamin_user)) echo 'selected'; ?>>-- Pilih Jenis Kelamin --</option>
                                <option value="Laki-laki" <?php if ($kelamin_user === 'Laki-laki') echo 'selected'; ?>>Laki-laki</option>
                                <option value="Perempuan" <?php if ($kelamin_user === 'Perempuan') echo 'selected'; ?>>Perempuan</option>
                            </select>
                        </div>

                        <div class="form-actions profile-actions">
                            <button type="submit" name="update_profil" class="primary-btn">Simpan Perubahan</button>
                            <a href="folder dashboard/dashboard.php" class="ghost-btn">Kembali</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('preview-foto').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('profileForm');
            const nikInput = document.getElementById('nik');
            const noKkInput = document.getElementById('no_kk');

            const validate16Digits = (input) => {
                const value = input.value.trim();

                if (value === '') {
                    input.setCustomValidity('');
                    return true;
                }

                if (!/^\d{16}$/.test(value)) {
                    input.setCustomValidity('Harus 16 digit angka');
                    return false;
                }

                input.setCustomValidity('');
                return true;
            };

            if (nikInput) {
                nikInput.addEventListener('input', function () {
                    this.value = this.value.replace(/[^\d]/g, '').slice(0, 16);
                    validate16Digits(this);
                });
            }

            if (noKkInput) {
                noKkInput.addEventListener('input', function () {
                    this.value = this.value.replace(/[^\d]/g, '').slice(0, 16);
                    validate16Digits(this);
                });
            }

            if (form && nikInput && noKkInput) {
                form.addEventListener('submit', function (event) {
                    const isNikValid = validate16Digits(nikInput);
                    const isNoKkValid = validate16Digits(noKkInput);
                    if (!isNikValid || !isNoKkValid) {
                        event.preventDefault();
                        if (!isNikValid) nikInput.focus();
                        else noKkInput.focus();
                    }
                });
            }
        });
    </script>
</body>
</html>