<?php
require_once __DIR__ . '/../Koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$successMessage = '';
$errorMessage = '';

if (isset($_POST['register'])) {
    $mode_kk = trim($_POST['mode_kk'] ?? 'gabung_kk'); // 'gabung_kk' atau 'buat_kk'
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $alamat_input = trim($_POST['alamat'] ?? '');
    $kelamin = trim($_POST['kelamin'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $no_kk = trim($_POST['no_kk'] ?? '');
    $status_keluarga_input = trim($_POST['status_keluarga'] ?? '');
    $tempat_lahir = trim($_POST['tempat_lahir'] ?? 'Bekasi');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '2000-01-01');
    $agama = trim($_POST['agama'] ?? 'Islam');
    $status_perkawinan = trim($_POST['status_perkawinan'] ?? 'Belum Kawin');
    $pendidikan = trim($_POST['pendidikan'] ?? 'SMA / SMK');
    $pekerjaan = trim($_POST['pekerjaan'] ?? 'Karyawan Swasta');
    $rt_input = trim($_POST['rt'] ?? '06');
    $rw = '19'; // Sesuai aturan sistem: RW 19
    $role = 'Warga';

    // 1. Validasi NIK 16 digit
    if (!preg_match('/^\d{16}$/', $nik)) {
        $errorMessage = 'NIK harus terdiri dari 16 digit angka.';
    } elseif (!preg_match('/^\d{16}$/', $no_kk)) {
        $errorMessage = 'Nomor KK harus terdiri dari 16 digit angka.';
    } elseif (strlen($password) < 6) {
        $errorMessage = 'Password minimal 6 karakter.';
    } elseif (empty($nama) || empty($username) || empty($email) || empty($kelamin)) {
        $errorMessage = 'Mohon lengkapi seluruh kolom data diri yang wajib diisi.';
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            // Cek Username atau Email sudah terpakai
            $stmt_check_user = mysqli_prepare($koneksi, "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt_check_user, "ss", $username, $email);
            mysqli_stmt_execute($stmt_check_user);
            $res_user = mysqli_stmt_get_result($stmt_check_user);
            if (mysqli_num_rows($res_user) > 0) {
                mysqli_stmt_close($stmt_check_user);
                throw new Exception('Username atau email sudah digunakan akun lain. Silakan pilih yang lain.');
            }
            mysqli_stmt_close($stmt_check_user);

            // Cek NIK sudah terdaftar
            $stmt_check_nik = mysqli_prepare($koneksi, "SELECT id FROM users WHERE nik = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt_check_nik, "s", $nik);
            mysqli_stmt_execute($stmt_check_nik);
            $res_nik = mysqli_stmt_get_result($stmt_check_nik);
            if (mysqli_num_rows($res_nik) > 0) {
                mysqli_stmt_close($stmt_check_nik);
                throw new Exception('NIK ' . $nik . ' sudah terdaftar dalam sistem RT RW. Mohon periksa kembali NIK Anda.');
            }
            mysqli_stmt_close($stmt_check_nik);

            $final_status = '';
            $final_alamat = '';
            $final_rt = $rt_input;

            if ($mode_kk === 'buat_kk') {
                // Skenario 1: Buat Kartu Keluarga Baru (Kepala Keluarga: Ayah atau Ibu)
                $final_status = 'Kepala Keluarga';
                $final_alamat = $alamat_input;
                $final_rt = in_array($rt_input, ['06', '07']) ? $rt_input : '06';

                // Pastikan No KK belum pernah dipakai sebagai Kepala Keluarga lain
                $stmt_check_new_kk = mysqli_prepare($koneksi, "SELECT id FROM users WHERE no_kk = ? LIMIT 1");
                mysqli_stmt_bind_param($stmt_check_new_kk, "s", $no_kk);
                mysqli_stmt_execute($stmt_check_new_kk);
                $res_new_kk = mysqli_stmt_get_result($stmt_check_new_kk);
                if (mysqli_num_rows($res_new_kk) > 0) {
                    mysqli_stmt_close($stmt_check_new_kk);
                    throw new Exception('Nomor KK ' . $no_kk . ' sudah terdaftar di sistem. Jika Anda ingin bergabung, silakan pilih opsi "Gabung ke Kartu Keluarga yang Sudah Ada".');
                }
                mysqli_stmt_close($stmt_check_new_kk);

            } else {
                // Skenario 2: Gabung ke KK yang Sudah Ada (Anak, Istri, Suami, Famili Lain)
                $konfirmasi_kk = trim($_POST['konfirmasi_kk'] ?? '');
                if ($konfirmasi_kk !== 'ya') {
                    throw new Exception('Anda harus mengonfirmasi Kartu Keluarga Anda (pilih "Ya") sebelum melanjutkan pendaftaran.');
                }

                if (!in_array($status_keluarga_input, ['Istri', 'Anak', 'Suami', 'Famili Lain'])) {
                    throw new Exception('Untuk bergabung ke KK yang sudah ada, silakan pilih status: Istri, Anak, Suami, atau Famili Lain.');
                }

                // Ambil data KK dari database
                $stmt_kk = mysqli_prepare($koneksi, "SELECT alamat, rt, rw, status_keluarga FROM users WHERE no_kk = ?");
                mysqli_stmt_bind_param($stmt_kk, "s", $no_kk);
                mysqli_stmt_execute($stmt_kk);
                $res_kk = mysqli_stmt_get_result($stmt_kk);

                if (mysqli_num_rows($res_kk) === 0) {
                    mysqli_stmt_close($stmt_kk);
                    throw new Exception('Nomor Kartu Keluarga ' . $no_kk . ' tidak ditemukan di sistem. Harap periksa kembali atau pilih "Buat Kartu Keluarga Baru".');
                }

                $existing_statuses = [];
                $kk_alamat = '';
                $kk_rt = '06';

                while ($row_kk = mysqli_fetch_assoc($res_kk)) {
                    if (empty($kk_alamat)) {
                        $kk_alamat = $row_kk['alamat'];
                    }
                    if (!empty($row_kk['rt'])) {
                        $kk_rt = $row_kk['rt'];
                    }
                    $existing_statuses[] = $row_kk['status_keluarga'];
                }
                mysqli_stmt_close($stmt_kk);

                // Cek duplikasi peran tunggal dalam satu KK
                if ($status_keluarga_input === 'Suami' && in_array('Suami', $existing_statuses)) {
                    throw new Exception('Status "Suami" sudah ada dalam Kartu Keluarga ini.');
                }
                if ($status_keluarga_input === 'Istri' && in_array('Istri', $existing_statuses)) {
                    throw new Exception('Status "Istri" sudah ada dalam Kartu Keluarga ini.');
                }

                $final_status = $status_keluarga_input;
                $final_alamat = $kk_alamat;
                $final_rt = $kk_rt;
            }

            // Simpan data warga baru dengan default foto profil polos profile.jpg
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $foto_default = 'profile.jpg';

            $stmt_insert = mysqli_prepare($koneksi, "INSERT INTO users (nama, username, email, password, alamat, kelamin, no_hp, nik, no_kk, role, status_keluarga, tempat_lahir, tanggal_lahir, pekerjaan, status_perkawinan, pendidikan, agama, rt, rw, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_insert, "ssssssssssssssssssss", 
                $nama, 
                $username, 
                $email, 
                $password_hash, 
                $final_alamat, 
                $kelamin, 
                $no_hp, 
                $nik, 
                $no_kk, 
                $role, 
                $final_status,
                $tempat_lahir,
                $tanggal_lahir,
                $pekerjaan,
                $status_perkawinan,
                $pendidikan,
                $agama,
                $final_rt,
                $rw,
                $foto_default
            );

            if (!mysqli_stmt_execute($stmt_insert)) {
                $err = mysqli_stmt_error($stmt_insert);
                mysqli_stmt_close($stmt_insert);
                throw new Exception('Gagal menyimpan data ke database: ' . $err);
            }
            mysqli_stmt_close($stmt_insert);

            mysqli_commit($koneksi);

            echo "<script>alert('Pendaftaran Berhasil! Anda terdaftar sebagai warga di RT " . htmlspecialchars($final_rt) . " / RW 19. Silakan Login.'); window.location='login.php';</script>";
            exit();

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $errorMessage = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Akun Warga - RT RW Digital Bersatu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="css register/register.css?v=<?php echo file_exists(__DIR__ . '/css register/register.css') ? filemtime(__DIR__ . '/css register/register.css') : '3'; ?>">
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <span class="brand-badge"><i class="fa-solid fa-id-card"></i> Pendaftaran Warga Digital</span>
                <h1>Buat Akun Warga</h1>
                <p>Lengkapi data kependudukan Anda untuk terhubung di wilayah <strong>RW 19</strong></p>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div class="alert-danger">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size:1.3rem;"></i>
                    <div>
                        <strong style="display:block;">Pendaftaran Belum Berhasil!</strong>
                        <span><?php echo htmlspecialchars($errorMessage); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm" novalidate>
                <!-- 1. PILIH METODE PENDAFTARAN KARTU KELUARGA (KK) -->
                <div class="form-section-title">
                    <i class="fa-solid fa-house-chimney-user"></i> 1. Status Kartu Keluarga (KK)
                </div>

                <div class="kk-mode-selector">
                    <label class="kk-mode-option" id="opt-gabung">
                        <input type="radio" name="mode_kk" value="gabung_kk" <?php echo (!isset($_POST['mode_kk']) || $_POST['mode_kk'] === 'gabung_kk') ? 'checked' : ''; ?>>
                        <div class="kk-mode-info">
                            <h4><i class="fa-solid fa-people-roof text-primary"></i> Gabung KK yang Sudah Ada</h4>
                            <p>Untuk Anggota Keluarga (Anak, Istri, Suami, atau Famili) yang KK-nya sudah pernah didaftarkan.</p>
                        </div>
                    </label>

                    <label class="kk-mode-option" id="opt-buat">
                        <input type="radio" name="mode_kk" value="buat_kk" <?php echo (isset($_POST['mode_kk']) && $_POST['mode_kk'] === 'buat_kk') ? 'checked' : ''; ?>>
                        <div class="kk-mode-info">
                            <h4><i class="fa-solid fa-crown text-amber"></i> Buat Kartu Keluarga Baru</h4>
                            <p>Khusus Kepala Keluarga (Ayah atau Ibu) yang KK-nya belum terdaftar di sistem RT RW.</p>
                        </div>
                    </label>
                </div>

                <!-- PANEL A: GABUNG KE KK YANG SUDAH ADA -->
                <div id="panel-gabung-kk" style="display: none;">
                    <div class="form-group full-width">
                        <label for="search_no_kk"><i class="fa-solid fa-magnifying-glass"></i> Cari Nomor KK Keluarga Anda (16 Digit)</label>
                        <div class="search-kk-box">
                            <input type="text" id="search_no_kk" maxlength="16" inputmode="numeric" placeholder="Masukkan 16 digit No. KK keluarga Anda" value="<?php echo htmlspecialchars($_POST['no_kk'] ?? ''); ?>">
                            <button type="button" id="btnCariKk" class="btn-search-kk">
                                <i class="fa-solid fa-search"></i> Cari KK
                            </button>
                        </div>
                        <span class="form-hint" id="search-kk-hint">Ketik 16 digit nomor KK lalu klik tombol "Cari KK".</span>
                    </div>

                    <!-- PREVIEW TABEL KK HASIL CARI -->
                    <div id="kk-preview-box" class="kk-preview-card" style="display: none;">
                        <div class="kk-preview-header">
                            <div>
                                <span class="kk-meta-badge blue" id="prev-no-kk"><i class="fa-solid fa-id-badge"></i> No. KK: -</span>
                                <span class="kk-meta-badge emerald" id="prev-rt-rw"><i class="fa-solid fa-location-dot"></i> Wilayah: RT - / RW 19</span>
                            </div>
                            <span class="kk-meta-badge amber" id="prev-total-anggota"><i class="fa-solid fa-users"></i> - Anggota Terdaftar</span>
                        </div>
                        <div class="kk-detail-row">
                            <strong>Kepala Keluarga:</strong> <span id="prev-kepala-keluarga">-</span>
                        </div>
                        <div class="kk-detail-row">
                            <strong>Alamat Rumah:</strong> <span id="prev-alamat">-</span>
                        </div>

                        <!-- TABEL ANGGOTA KK -->
                        <div class="kk-members-table-wrap">
                            <table class="kk-members-table">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Anggota</th>
                                        <th>NIK</th>
                                        <th>Hubungan</th>
                                        <th>Jenis Kelamin</th>
                                    </tr>
                                </thead>
                                <tbody id="kk-members-tbody">
                                    <!-- Diisi via AJAX JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- KONFIRMASI TEGAS: APAKAH INI KK ANDA? HANYA YA DAN TIDAK -->
                        <div class="kk-confirm-box">
                            <div class="kk-confirm-title">
                                <i class="fa-solid fa-circle-question"></i> Apakah Anda yakin ini Kartu Keluarga Anda?
                            </div>
                            <div class="kk-confirm-options">
                                <label class="kk-confirm-radio ya">
                                    <input type="radio" name="konfirmasi_kk" value="ya" id="konfirmasi_ya">
                                    <span><i class="fa-solid fa-check"></i> Ya, ini KK Saya</span>
                                </label>
                                <label class="kk-confirm-radio tidak">
                                    <input type="radio" name="konfirmasi_kk" value="tidak" id="konfirmasi_tidak">
                                    <span><i class="fa-solid fa-xmark"></i> Tidak, Bukan KK Saya</span>
                                </label>
                            </div>
                            <span class="form-hint" id="konfirmasi-hint" style="margin-top:8px;">Pilih <strong>"Ya"</strong> untuk menyetujui bergabung ke Kartu Keluarga ini.</span>
                        </div>
                    </div>
                </div>

                <!-- PANEL B: BUAT KK BARU -->
                <div id="panel-buat-kk" style="display: none;">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="new_no_kk"><i class="fa-solid fa-id-card-clip"></i> Nomor Kartu Keluarga Baru (16 Digit)</label>
                            <input type="text" id="new_no_kk" maxlength="16" inputmode="numeric" placeholder="Contoh: 3270000000000001" value="<?php echo htmlspecialchars($_POST['no_kk'] ?? ''); ?>">
                            <span class="form-hint" id="new-kk-hint">Wajib 16 digit angka resmi Dukcapil</span>
                        </div>

                        <div class="form-group">
                            <label for="rt_select"><i class="fa-solid fa-map-location-dot"></i> Pilihan Wilayah RT</label>
                            <select id="rt_select" name="rt" required>
                                <option value="06" <?php echo (($_POST['rt'] ?? '') === '06') ? 'selected' : ''; ?>>Wilayah RT 06 (Ketua RT: Dimas)</option>
                                <option value="07" <?php echo (($_POST['rt'] ?? '') === '07') ? 'selected' : ''; ?>>Wilayah RT 07 (Ketua RT: Bambang)</option>
                            </select>
                            <span class="form-hint">Pilih RT tempat tinggal Anda (RW 19)</span>
                        </div>

                        <div class="form-group full-width">
                            <label for="alamat_baru"><i class="fa-solid fa-house"></i> Alamat Rumah Lengkap</label>
                            <input type="text" id="alamat_baru" placeholder="Contoh: Jl. Anggrek No. 15, RT 06 / RW 19" value="<?php echo htmlspecialchars($_POST['alamat'] ?? ''); ?>">
                            <span class="form-hint">Alamat ini akan otomatis menjadi alamat seluruh anggota keluarga Anda</span>
                        </div>
                    </div>
                </div>

                <!-- HIDDEN INPUT UNTUK FORM SUBMISSION -->
                <input type="hidden" name="no_kk" id="final_no_kk" value="<?php echo htmlspecialchars($_POST['no_kk'] ?? ''); ?>">
                <input type="hidden" name="alamat" id="final_alamat" value="<?php echo htmlspecialchars($_POST['alamat'] ?? ''); ?>">

                <!-- 2. DATA DIRI LENGKAP WARGA (SESUAI KK DUNIA ASLI) -->
                <div class="form-section-title">
                    <i class="fa-solid fa-user-check"></i> 2. Data Diri & Kependudukan (Sesuai KTP / KK)
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="nama"><i class="fa-regular fa-user"></i> Nama Lengkap</label>
                        <input type="text" id="nama" name="nama" placeholder="Nama Lengkap sesuai KTP" value="<?php echo htmlspecialchars($_POST['nama'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="username"><i class="fa-solid fa-at"></i> Username Akun</label>
                        <input type="text" id="username" name="username" placeholder="Username untuk login" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="nik"><i class="fa-regular fa-id-card"></i> NIK (Nomor Induk Kependudukan - 16 Digit)</label>
                        <input type="text" id="nik" name="nik" maxlength="16" placeholder="Contoh: 3201010101010101" inputmode="numeric" value="<?php echo htmlspecialchars($_POST['nik'] ?? ''); ?>" required>
                        <span class="form-hint" id="nik-hint">Wajib 16 digit angka. NIK yang sama akan ditolak sistem.</span>
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
                        <label for="status_keluarga"><i class="fa-solid fa-people-arrows"></i> Hubungan dalam Keluarga</label>
                        <select id="status_keluarga" name="status_keluarga" required>
                            <!-- Diisi secara dinamis tergantung Mode KK -->
                        </select>
                        <span class="form-hint" id="status-hint">Pilih status hubungan Anda dalam Kartu Keluarga</span>
                    </div>

                    <div class="form-group">
                        <label for="tempat_lahir"><i class="fa-solid fa-map-pin"></i> Tempat Lahir</label>
                        <input type="text" id="tempat_lahir" name="tempat_lahir" placeholder="Contoh: Bekasi, Jakarta, Bandung" value="<?php echo htmlspecialchars($_POST['tempat_lahir'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="tanggal_lahir"><i class="fa-regular fa-calendar-days"></i> Tanggal Lahir</label>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo htmlspecialchars($_POST['tanggal_lahir'] ?? '2000-01-01'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="agama"><i class="fa-solid fa-place-of-worship"></i> Agama</label>
                        <select id="agama" name="agama" required>
                            <option value="Islam" <?php echo (($_POST['agama'] ?? '') === 'Islam') ? 'selected' : ''; ?>>Islam</option>
                            <option value="Kristen Protestan" <?php echo (($_POST['agama'] ?? '') === 'Kristen Protestan') ? 'selected' : ''; ?>>Kristen Protestan</option>
                            <option value="Katolik" <?php echo (($_POST['agama'] ?? '') === 'Katolik') ? 'selected' : ''; ?>>Katolik</option>
                            <option value="Hindu" <?php echo (($_POST['agama'] ?? '') === 'Hindu') ? 'selected' : ''; ?>>Hindu</option>
                            <option value="Buddha" <?php echo (($_POST['agama'] ?? '') === 'Buddha') ? 'selected' : ''; ?>>Buddha</option>
                            <option value="Khonghucu" <?php echo (($_POST['agama'] ?? '') === 'Khonghucu') ? 'selected' : ''; ?>>Khonghucu</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status_perkawinan"><i class="fa-solid fa-ring"></i> Status Perkawinan</label>
                        <select id="status_perkawinan" name="status_perkawinan" required>
                            <option value="Belum Kawin" <?php echo (($_POST['status_perkawinan'] ?? '') === 'Belum Kawin') ? 'selected' : ''; ?>>Belum Kawin</option>
                            <option value="Kawin" <?php echo (($_POST['status_perkawinan'] ?? '') === 'Kawin') ? 'selected' : ''; ?>>Kawin</option>
                            <option value="Cerai Hidup" <?php echo (($_POST['status_perkawinan'] ?? '') === 'Cerai Hidup') ? 'selected' : ''; ?>>Cerai Hidup</option>
                            <option value="Cerai Mati" <?php echo (($_POST['status_perkawinan'] ?? '') === 'Cerai Mati') ? 'selected' : ''; ?>>Cerai Mati</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="pekerjaan"><i class="fa-solid fa-briefcase"></i> Jenis Pekerjaan</label>
                        <select id="pekerjaan" name="pekerjaan" required>
                            <option value="Karyawan Swasta" <?php echo (($_POST['pekerjaan'] ?? '') === 'Karyawan Swasta') ? 'selected' : ''; ?>>Karyawan Swasta</option>
                            <option value="Mengurus Rumah Tangga" <?php echo (($_POST['pekerjaan'] ?? '') === 'Mengurus Rumah Tangga') ? 'selected' : ''; ?>>Mengurus Rumah Tangga</option>
                            <option value="Pelajar / Mahasiswa" <?php echo (($_POST['pekerjaan'] ?? '') === 'Pelajar / Mahasiswa') ? 'selected' : ''; ?>>Pelajar / Mahasiswa</option>
                            <option value="Wiraswasta" <?php echo (($_POST['pekerjaan'] ?? '') === 'Wiraswasta') ? 'selected' : ''; ?>>Wiraswasta</option>
                            <option value="PNS / ASN" <?php echo (($_POST['pekerjaan'] ?? '') === 'PNS / ASN') ? 'selected' : ''; ?>>PNS / ASN</option>
                            <option value="Buruh Harian Lepas" <?php echo (($_POST['pekerjaan'] ?? '') === 'Buruh Harian Lepas') ? 'selected' : ''; ?>>Buruh Harian Lepas</option>
                            <option value="Guru / Dosen" <?php echo (($_POST['pekerjaan'] ?? '') === 'Guru / Dosen') ? 'selected' : ''; ?>>Guru / Dosen</option>
                            <option value="Tenaga Medis" <?php echo (($_POST['pekerjaan'] ?? '') === 'Tenaga Medis') ? 'selected' : ''; ?>>Tenaga Medis</option>
                            <option value="TNI / Polri" <?php echo (($_POST['pekerjaan'] ?? '') === 'TNI / Polri') ? 'selected' : ''; ?>>TNI / Polri</option>
                            <option value="Pensiunan" <?php echo (($_POST['pekerjaan'] ?? '') === 'Pensiunan') ? 'selected' : ''; ?>>Pensiunan</option>
                            <option value="Belum / Tidak Bekerja" <?php echo (($_POST['pekerjaan'] ?? '') === 'Belum / Tidak Bekerja') ? 'selected' : ''; ?>>Belum / Tidak Bekerja</option>
                            <option value="Lainnya" <?php echo (($_POST['pekerjaan'] ?? '') === 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="pendidikan"><i class="fa-solid fa-graduation-cap"></i> Pendidikan Terakhir</label>
                        <select id="pendidikan" name="pendidikan" required>
                            <option value="SMA / SMK" <?php echo (($_POST['pendidikan'] ?? '') === 'SMA / SMK') ? 'selected' : ''; ?>>SMA / SMK / Sederajat</option>
                            <option value="S1 / Sarjana" <?php echo (($_POST['pendidikan'] ?? '') === 'S1 / Sarjana') ? 'selected' : ''; ?>>S1 / Sarjana</option>
                            <option value="Diploma (D3/D4)" <?php echo (($_POST['pendidikan'] ?? '') === 'Diploma (D3/D4)') ? 'selected' : ''; ?>>Diploma (D1 / D2 / D3 / D4)</option>
                            <option value="SMP" <?php echo (($_POST['pendidikan'] ?? '') === 'SMP') ? 'selected' : ''; ?>>SMP / Sederajat</option>
                            <option value="SD" <?php echo (($_POST['pendidikan'] ?? '') === 'SD') ? 'selected' : ''; ?>>SD / Sederajat</option>
                            <option value="S2 / Magister" <?php echo (($_POST['pendidikan'] ?? '') === 'S2 / Magister') ? 'selected' : ''; ?>>S2 / Magister</option>
                            <option value="S3 / Doktor" <?php echo (($_POST['pendidikan'] ?? '') === 'S3 / Doktor') ? 'selected' : ''; ?>>S3 / Doktor</option>
                            <option value="Tidak / Belum Sekolah" <?php echo (($_POST['pendidikan'] ?? '') === 'Tidak / Belum Sekolah') ? 'selected' : ''; ?>>Tidak / Belum Sekolah</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fa-regular fa-envelope"></i> Email Aktif</label>
                        <input type="email" id="email" name="email" placeholder="contoh@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="no_hp"><i class="fa-brands fa-whatsapp"></i> Nomor WhatsApp / HP</label>
                        <input type="text" id="no_hp" name="no_hp" placeholder="Contoh: 081234567890" value="<?php echo htmlspecialchars($_POST['no_hp'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="password"><i class="fa-solid fa-lock"></i> Password Akun</label>
                        <input type="password" id="password" name="password" placeholder="Minimal 6 karakter" required>
                    </div>
                </div>

                <button type="submit" name="register" id="btnSubmit" class="btn-register">
                    <i class="fa-solid fa-user-plus"></i> Selesaikan Pendaftaran Warga
                </button>
            </form>

            <p class="register-footer">Sudah memiliki akun warga? <a href="login.php">Masuk di sini</a></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('registerForm');
            const optGabung = document.getElementById('opt-gabung');
            const optBuat = document.getElementById('opt-buat');
            const radioGabung = optGabung.querySelector('input[type="radio"]');
            const radioBuat = optBuat.querySelector('input[type="radio"]');
            
            const panelGabung = document.getElementById('panel-gabung-kk');
            const panelBuat = document.getElementById('panel-buat-kk');
            
            const searchNoKk = document.getElementById('search_no_kk');
            const btnCariKk = document.getElementById('btnCariKk');
            const searchKkHint = document.getElementById('search-kk-hint');
            
            const kkPreviewBox = document.getElementById('kk-preview-box');
            const prevNoKk = document.getElementById('prev-no-kk');
            const prevRtRw = document.getElementById('prev-rt-rw');
            const prevTotalAnggota = document.getElementById('prev-total-anggota');
            const prevKepala = document.getElementById('prev-kepala-keluarga');
            const prevAlamat = document.getElementById('prev-alamat');
            const membersTbody = document.getElementById('kk-members-tbody');
            
            const konfirmasiYa = document.getElementById('konfirmasi_ya');
            const konfirmasiTidak = document.getElementById('konfirmasi_tidak');
            const konfirmasiHint = document.getElementById('konfirmasi-hint');
            
            const newNoKk = document.getElementById('new_no_kk');
            const newKkHint = document.getElementById('new-kk-hint');
            const alamatBaru = document.getElementById('alamat_baru');
            const rtSelect = document.getElementById('rt_select');
            
            const finalNoKk = document.getElementById('final_no_kk');
            const finalAlamat = document.getElementById('final_alamat');
            const statusKeluargaSelect = document.getElementById('status_keluarga');
            const statusHint = document.getElementById('status-hint');
            
            const nikInput = document.getElementById('nik');
            const nikHint = document.getElementById('nik-hint');
            const btnSubmit = document.getElementById('btnSubmit');

            let isNikAvailable = false;
            let isKkConfirmed = false;

            // Switch Mode KK
            function updateModeUI() {
                const isGabung = radioGabung.checked;
                optGabung.classList.toggle('active', isGabung);
                optBuat.classList.toggle('active', !isGabung);

                if (isGabung) {
                    panelGabung.style.display = 'block';
                    panelBuat.style.display = 'none';

                    // Update dropdown status hubungan
                    statusKeluargaSelect.innerHTML = `
                        <option value="" disabled selected>-- Pilih Hubungan Keluarga --</option>
                        <option value="Istri">Istri</option>
                        <option value="Anak">Anak</option>
                        <option value="Suami">Suami</option>
                        <option value="Famili Lain">Famili Lain</option>
                    `;
                    statusHint.textContent = 'Pilih peran Anda dalam KK ini (Kepala Keluarga sudah terdaftar)';
                    syncGabungKK();
                } else {
                    panelGabung.style.display = 'none';
                    panelBuat.style.display = 'block';

                    // Kepala Keluarga otomatis
                    statusKeluargaSelect.innerHTML = `
                        <option value="Kepala Keluarga" selected>Kepala Keluarga (Otomatis)</option>
                    `;
                    statusHint.textContent = 'Status otomatis menjadi Kepala Keluarga (Ayah / Ibu)';
                    syncBuatKK();
                }
            }

            function syncBuatKK() {
                finalNoKk.value = newNoKk.value.trim();
                finalAlamat.value = alamatBaru.value.trim();
            }

            function syncGabungKK() {
                finalNoKk.value = searchNoKk.value.trim();
                finalAlamat.value = prevAlamat.textContent !== '-' ? prevAlamat.textContent : '';
            }

            radioGabung.addEventListener('change', updateModeUI);
            radioBuat.addEventListener('change', updateModeUI);

            // Sanitasi 16 digit input
            function sanitize16Digits(input) {
                input.value = input.value.replace(/[^\d]/g, '').slice(0, 16);
            }

            searchNoKk.addEventListener('input', function () {
                sanitize16Digits(this);
                syncGabungKK();
                // Reset preview jika diganti
                kkPreviewBox.style.display = 'none';
                konfirmasiYa.checked = false;
                konfirmasiTidak.checked = false;
                isKkConfirmed = false;
            });

            newNoKk.addEventListener('input', function () {
                sanitize16Digits(this);
                syncBuatKK();
                if (this.value.length === 16) {
                    newKkHint.className = 'form-hint success';
                    newKkHint.innerHTML = '✓ 16 digit No. KK baru valid.';
                } else {
                    newKkHint.className = 'form-hint';
                    newKkHint.textContent = this.value.length > 0 ? ('Saat ini ' + this.value.length + '/16 digit') : 'Wajib 16 digit angka resmi Dukcapil';
                }
            });

            alamatBaru.addEventListener('input', syncBuatKK);

            // CARI KK VIA AJAX
            btnCariKk.addEventListener('click', function () {
                const noKk = searchNoKk.value.trim();
                if (noKk.length !== 16) {
                    searchKkHint.className = 'form-hint error';
                    searchKkHint.textContent = 'Nomor KK harus tepat 16 digit angka!';
                    searchNoKk.focus();
                    return;
                }

                btnCariKk.disabled = true;
                btnCariKk.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mencari...';
                searchKkHint.className = 'form-hint';
                searchKkHint.textContent = 'Sedang mencari data Kartu Keluarga di sistem...';

                fetch('ajax_cek_kk.php?action=search_kk&no_kk=' + encodeURIComponent(noKk))
                    .then(response => response.json())
                    .then(data => {
                        btnCariKk.disabled = false;
                        btnCariKk.innerHTML = '<i class="fa-solid fa-search"></i> Cari KK';

                        if (data.found) {
                            searchKkHint.className = 'form-hint success';
                            searchKkHint.textContent = '✓ Data KK ditemukan! Silakan verifikasi anggota keluarga di bawah.';

                            prevNoKk.innerHTML = '<i class="fa-solid fa-id-badge"></i> No. KK: ' + data.no_kk;
                            prevRtRw.innerHTML = '<i class="fa-solid fa-location-dot"></i> Wilayah: RT ' + data.rt + ' / RW ' + data.rw;
                            prevTotalAnggota.innerHTML = '<i class="fa-solid fa-users"></i> ' + data.total_anggota + ' Anggota Terdaftar';
                            prevKepala.textContent = data.kepala_keluarga;
                            prevAlamat.textContent = data.alamat;

                            // Render table
                            membersTbody.innerHTML = '';
                            data.members.forEach((m, idx) => {
                                let badgeCls = 'famili';
                                if (m.status_keluarga === 'Kepala Keluarga') badgeCls = 'kepala';
                                else if (m.status_keluarga === 'Istri') badgeCls = 'istri';
                                else if (m.status_keluarga === 'Anak') badgeCls = 'anak';
                                else if (m.status_keluarga === 'Suami') badgeCls = 'suami';

                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                                    <td>${idx + 1}</td>
                                    <td><strong>${m.nama}</strong></td>
                                    <td>${m.nik}</td>
                                    <td><span class="badge-role ${badgeCls}">${m.status_keluarga}</span></td>
                                    <td>${m.kelamin}</td>
                                `;
                                membersTbody.appendChild(tr);
                            });

                            kkPreviewBox.style.display = 'block';
                            konfirmasiYa.checked = false;
                            konfirmasiTidak.checked = false;
                            isKkConfirmed = false;
                            syncGabungKK();

                        } else {
                            searchKkHint.className = 'form-hint error';
                            searchKkHint.textContent = data.message || 'Kartu Keluarga tidak ditemukan.';
                            kkPreviewBox.style.display = 'none';
                            isKkConfirmed = false;
                        }
                    })
                    .catch(err => {
                        btnCariKk.disabled = false;
                        btnCariKk.innerHTML = '<i class="fa-solid fa-search"></i> Cari KK';
                        searchKkHint.className = 'form-hint error';
                        searchKkHint.textContent = 'Terjadi kesalahan saat mencari KK. Silakan coba lagi.';
                    });
            });

            // KONFIRMASI KK (YA / TIDAK)
            konfirmasiYa.addEventListener('change', function () {
                if (this.checked) {
                    isKkConfirmed = true;
                    konfirmasiHint.className = 'form-hint success';
                    konfirmasiHint.textContent = '✓ Anda telah mengonfirmasi bahwa ini adalah Kartu Keluarga Anda. Silakan lanjutkan pengisian formulir.';
                    syncGabungKK();
                }
            });

            konfirmasiTidak.addEventListener('change', function () {
                if (this.checked) {
                    isKkConfirmed = false;
                    konfirmasiHint.className = 'form-hint error';
                    konfirmasiHint.textContent = 'Peringatan: Jika ini bukan KK Anda, silakan cari nomor KK yang benar atau pilih "Buat Kartu Keluarga Baru".';
                }
            });

            // VALIDASI REAL-TIME NIK
            let nikTimeout = null;
            nikInput.addEventListener('input', function () {
                sanitize16Digits(this);
                const val = this.value.trim();

                clearTimeout(nikTimeout);
                if (val.length === 16) {
                    nikHint.className = 'form-hint';
                    nikHint.textContent = 'Memeriksa ketersediaan NIK di database RT RW...';

                    nikTimeout = setTimeout(() => {
                        fetch('ajax_cek_kk.php?action=check_nik&nik=' + encodeURIComponent(val))
                            .then(res => res.json())
                            .then(data => {
                                if (data.valid && !data.exists) {
                                    isNikAvailable = true;
                                    nikHint.className = 'form-hint success';
                                    nikHint.innerHTML = '✓ ' + data.message;
                                    nikInput.setCustomValidity('');
                                } else {
                                    isNikAvailable = false;
                                    nikHint.className = 'form-hint error';
                                    nikHint.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + (data.message || 'NIK sudah terdaftar dalam sistem RT RW!');
                                    nikInput.setCustomValidity('NIK sudah terdaftar');
                                }
                            })
                            .catch(() => {
                                isNikAvailable = true; // biarkan backend yang finalisasi jika koneksi bermasalah
                            });
                    }, 350);
                } else {
                    isNikAvailable = false;
                    nikHint.className = 'form-hint';
                    nikHint.textContent = val.length > 0 ? ('NIK saat ini ' + val.length + '/16 digit angka') : 'Wajib 16 digit angka. NIK yang sama akan ditolak sistem.';
                    nikInput.setCustomValidity('');
                }
            });

            // SUBMIT VALIDATION
            form.addEventListener('submit', function (e) {
                const isGabung = radioGabung.checked;

                // Cek NIK
                const nikVal = nikInput.value.trim();
                if (nikVal.length !== 16) {
                    e.preventDefault();
                    alert('NIK harus terdiri dari 16 digit angka!');
                    nikInput.focus();
                    return;
                }

                if (!isNikAvailable && nikHint.classList.contains('error')) {
                    e.preventDefault();
                    alert('NIK ini sudah terdaftar dalam sistem RT RW! Harap periksa kembali NIK Anda.');
                    nikInput.focus();
                    return;
                }

                if (isGabung) {
                    syncGabungKK();
                    const noKkVal = finalNoKk.value.trim();
                    if (noKkVal.length !== 16) {
                        e.preventDefault();
                        alert('Silakan masukkan dan cari 16 digit Nomor KK keluarga Anda terlebih dahulu!');
                        searchNoKk.focus();
                        return;
                    }

                    if (!konfirmasiYa.checked) {
                        e.preventDefault();
                        alert('Sebelum mendaftar, Anda wajib mengonfirmasi: "Apakah Anda yakin ini Kartu Keluarga Anda?" dengan memilih "Ya"!');
                        konfirmasiYa.focus();
                        return;
                    }
                } else {
                    syncBuatKK();
                    const noKkVal = finalNoKk.value.trim();
                    if (noKkVal.length !== 16) {
                        e.preventDefault();
                        alert('Nomor KK Baru harus terdiri dari 16 digit angka!');
                        newNoKk.focus();
                        return;
                    }

                    if (alamatBaru.value.trim() === '') {
                        e.preventDefault();
                        alert('Harap isi Alamat Rumah Lengkap untuk Kartu Keluarga baru Anda!');
                        alamatBaru.focus();
                        return;
                    }
                }
            });

            // Inisialisasi awal
            updateModeUI();
        });
    </script>
</body>
</html>
