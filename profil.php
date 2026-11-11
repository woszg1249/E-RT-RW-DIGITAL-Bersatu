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
    $stmt = mysqli_prepare($koneksi, "SELECT * FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
} else {
    $stmt = mysqli_prepare($koneksi, "SELECT * FROM users WHERE nama = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $nama_user);
}

mysqli_stmt_execute($stmt);
$query = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

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
} elseif (file_exists("img/profile.jpg")) {
    $tampilan_foto = "img/profile.jpg";
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .profile-container { max-width: 900px; margin: 0 auto; }
        .profile-header { background: white; border-radius: 12px; padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .profile-header h1 { color: #2563eb; font-size: 28px; display: flex; align-items: center; gap: 12px; }
        .profile-header h1 i { font-size: 32px; }
        .profile-cards { display: grid; gap: 20px; }
        .card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .card-title { font-size: 20px; font-weight: 600; color: #1e40af; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; padding-bottom: 15px; border-bottom: 2px solid #e5e7eb; }
        .card-title i { font-size: 24px; color: #3b82f6; }
        
        .avatar-section { display: flex; flex-direction: column; align-items: center; gap: 15px; margin-bottom: 30px; }
        .avatar-wrap { position: relative; width: 150px; height: 150px; border-radius: 50%; overflow: hidden; border: 4px solid #3b82f6; box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
        .avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(37,99,235,0.9); padding: 10px; text-align: center; cursor: pointer; transition: all 0.3s; }
        .avatar-overlay:hover { background: rgba(29,78,216,0.95); }
        .avatar-overlay i { color: white; font-size: 18px; }
        .avatar-overlay input { display: none; }
        .avatar-name { font-size: 22px; font-weight: 600; color: #1e3a8a; }
        .avatar-role { color: #6b7280; font-size: 14px; }
        
        .form-grid { display: grid; gap: 20px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-field { display: flex; flex-direction: column; gap: 8px; }
        .form-field label { font-weight: 500; color: #374151; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .form-field label i { color: #3b82f6; width: 16px; }
        .input-wrapper { position: relative; }
        .form-field input, .form-field select { padding: 12px 14px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s; width: 100%; }
        .form-field input:focus, .form-field select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .form-field input[type="password"], .form-field input[type="text"].password-field { padding-right: 45px; }
        
        .password-toggle { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #6b7280; user-select: none; z-index: 10; font-size: 16px; transition: all 0.3s; }
        .password-toggle:hover { color: #2563eb; }
        
        .form-actions { display: flex; gap: 12px; margin-top: 30px; }
        .btn { padding: 12px 28px; border: none; border-radius: 8px; font-size: 15px; font-weight: 500; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37,99,235,0.4); }
        .btn-secondary { background: white; color: #6b7280; border: 2px solid #e5e7eb; }
        .btn-secondary:hover { border-color: #3b82f6; color: #3b82f6; }
        
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-info { background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; }
        .alert-info i { color: #3b82f6; }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .profile-header h1 { font-size: 22px; }
            .card { padding: 20px; }
            .avatar-wrap { width: 120px; height: 120px; }
            .form-actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <h1><i class="fas fa-user-circle"></i> Edit Profil Saya</h1>
        </div>

        <form action="proses_update_profil.php" method="POST" enctype="multipart/form-data" id="profileForm" novalidate>
            <input type="hidden" name="user_id" value="<?php echo (int)($data['id'] ?? 0); ?>">
            
            <div class="profile-cards">
                <!-- Card Informasi Profil -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-id-card"></i>
                        <span>Informasi Profil</span>
                    </div>
                    
                    <div class="avatar-section">
                        <div class="avatar-wrap">
                            <img src="<?php echo htmlspecialchars($tampilan_foto); ?>" id="preview-foto" alt="Foto profil">
                            <label class="avatar-overlay" for="input-foto" title="Klik untuk ganti foto">
                                <i class="fas fa-camera"></i>
                                <input type="file" name="foto" id="input-foto" accept="image/jpeg,image/png,image/gif" onchange="previewImage(this)">
                            </label>
                        </div>
                        <div style="text-align: center;">
                            <div class="avatar-name"><?php echo htmlspecialchars($data['nama']); ?></div>
                            <div class="avatar-role"><?php echo htmlspecialchars($data['role'] ?? 'Warga'); ?></div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class="fas fa-user"></i> Nama Lengkap</label>
                            <div class="input-wrapper">
                                <input type="text" name="nama" value="<?php echo htmlspecialchars($data['nama']); ?>" required placeholder="Masukkan nama lengkap">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-field">
                                <label><i class="fas fa-id-card"></i> NIK (16 Digit)</label>
                                <div class="input-wrapper">
                                    <input type="text" id="nik" name="nik" value="<?php echo htmlspecialchars($nik_user); ?>" maxlength="16" inputmode="numeric" placeholder="16 digit angka">
                                </div>
                            </div>
                            <div class="form-field">
                                <label><i class="fas fa-users"></i> Nomor KK (16 Digit)</label>
                                <div class="input-wrapper">
                                    <input type="text" id="no_kk" name="no_kk" value="<?php echo htmlspecialchars($no_kk_user); ?>" maxlength="16" inputmode="numeric" placeholder="16 digit angka">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-field">
                                <label><i class="fas fa-envelope"></i> Alamat Email</label>
                                <div class="input-wrapper">
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($data['email']); ?>" required placeholder="contoh@email.com">
                                </div>
                            </div>
                            <div class="form-field">
                                <label><i class="fas fa-phone"></i> Nomor Telepon / WA</label>
                                <div class="input-wrapper">
                                    <input type="text" name="no_hp" value="<?php echo htmlspecialchars($data['no_hp']); ?>" placeholder="08123456789">
                                </div>
                            </div>
                        </div>

                        <div class="form-field">
                            <label><i class="fas fa-home"></i> Alamat Rumah</label>
                            <div class="input-wrapper">
                                <input type="text" name="alamat" value="<?php echo htmlspecialchars($data['alamat']); ?>" required placeholder="Jl. Contoh No. 123, RT 01/RW 02">
                            </div>
                        </div>

                        <div class="form-field">
                            <label><i class="fas fa-venus-mars"></i> Jenis Kelamin</label>
                            <select name="kelamin" required>
                                <option value="" disabled <?php if (empty($kelamin_user)) echo 'selected'; ?>>-- Pilih Jenis Kelamin --</option>
                                <option value="Laki-laki" <?php if ($kelamin_user === 'Laki-laki') echo 'selected'; ?>>Laki-laki</option>
                                <option value="Perempuan" <?php if ($kelamin_user === 'Perempuan') echo 'selected'; ?>>Perempuan</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Card Ubah Password -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-lock"></i>
                        <span>Ubah Password</span>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span>Kosongkan jika tidak ingin mengubah password</span>
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class="fas fa-key"></i> Password Lama</label>
                            <div class="input-wrapper">
                                <input type="password" name="password_lama" id="password_lama" placeholder="Masukkan password lama">
                                <i class="fas fa-eye password-toggle" onclick="togglePassword('password_lama')"></i>
                            </div>
                        </div>

                        <div class="form-field">
                            <label><i class="fas fa-lock"></i> Password Baru</label>
                            <div class="input-wrapper">
                                <input type="password" name="password_baru" id="password_baru" placeholder="Minimal 6 karakter">
                                <i class="fas fa-eye password-toggle" onclick="togglePassword('password_baru')"></i>
                            </div>
                        </div>

                        <div class="form-field">
                            <label><i class="fas fa-lock"></i> Konfirmasi Password Baru</label>
                            <div class="input-wrapper">
                                <input type="password" name="password_konfirmasi" id="password_konfirmasi" placeholder="Ketik ulang password baru">
                                <i class="fas fa-eye password-toggle" onclick="togglePassword('password_konfirmasi')"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="card">
                    <div class="form-actions">
                        <button type="submit" name="update_profil" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                        <a href="folder dashboard/dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Preview foto sebelum upload
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validasi tipe file
                if (!file.type.match('image/(jpeg|jpg|png|gif)')) {
                    alert('File harus berupa gambar (JPEG, PNG, atau GIF)');
                    input.value = '';
                    return;
                }
                
                // Validasi ukuran file (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran file maksimal 2MB');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('preview-foto').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }

        // Toggle visibility password
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('profileForm');
            const nikInput = document.getElementById('nik');
            const noKkInput = document.getElementById('no_kk');
            const passwordLama = document.getElementById('password_lama');
            const passwordBaru = document.getElementById('password_baru');
            const passwordKonfirmasi = document.getElementById('password_konfirmasi');

            // Validasi 16 digit NIK/KK
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

            // Validasi password baru
            if (passwordBaru) {
                passwordBaru.addEventListener('input', function() {
                    if (this.value.length > 0 && this.value.length < 6) {
                        this.setCustomValidity('Password minimal 6 karakter');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }

            // Validasi form saat submit
            if (form) {
                form.addEventListener('submit', function (event) {
                    // Validasi NIK dan KK
                    const isNikValid = validate16Digits(nikInput);
                    const isNoKkValid = validate16Digits(noKkInput);
                    
                    if (!isNikValid || !isNoKkValid) {
                        event.preventDefault();
                        alert('NIK dan Nomor KK harus 16 digit angka');
                        if (!isNikValid) nikInput.focus();
                        else noKkInput.focus();
                        return;
                    }

                    // Validasi password jika diisi
                    const pwdLamaVal = passwordLama.value.trim();
                    const pwdBaruVal = passwordBaru.value.trim();
                    const pwdKonfirmasiVal = passwordKonfirmasi.value.trim();

                    // Jika salah satu field password diisi, semua harus diisi
                    if (pwdLamaVal || pwdBaruVal || pwdKonfirmasiVal) {
                        if (!pwdLamaVal) {
                            event.preventDefault();
                            alert('Password lama harus diisi');
                            passwordLama.focus();
                            return;
                        }
                        if (!pwdBaruVal) {
                            event.preventDefault();
                            alert('Password baru harus diisi');
                            passwordBaru.focus();
                            return;
                        }
                        if (pwdBaruVal.length < 6) {
                            event.preventDefault();
                            alert('Password baru minimal 6 karakter');
                            passwordBaru.focus();
                            return;
                        }
                        if (!pwdKonfirmasiVal) {
                            event.preventDefault();
                            alert('Konfirmasi password harus diisi');
                            passwordKonfirmasi.focus();
                            return;
                        }
                        if (pwdBaruVal !== pwdKonfirmasiVal) {
                            event.preventDefault();
                            alert('Password baru dan konfirmasi tidak cocok');
                            passwordKonfirmasi.focus();
                            return;
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>