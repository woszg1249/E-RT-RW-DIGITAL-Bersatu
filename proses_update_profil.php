<?php
session_start(); // 1. WAJIB DIKUTIP PALING ATAS AGAR SESSION ID TERBACA
include "Koneksi.php"; // 2. SESUAIKAN HURUF BESAR/KECIL FILE KONEKSI ANDA

// Validasi jika user belum login atau session hilang
if (!isset($_SESSION['nama'])) {
    header("Location: folder login,logout dan register/login.php");
    exit();
}

// Ambil ID user dari data session yang sedang login
// Pastikan saat login, Anda sudah menyimpan $_SESSION['id'] = $data['id'];
$id_user = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

if ($id_user <= 0) {
    $id_user = $_SESSION['id'] ?? 0;
}

if ($id_user <= 0) {
    $nama_session = $_SESSION['nama'];
    $cari_id = mysqli_query($koneksi, "SELECT id FROM users WHERE nama = '" . mysqli_real_escape_string($koneksi, $nama_session) . "' LIMIT 1");
    if ($cari_id && mysqli_num_rows($cari_id) > 0) {
        $data_id = mysqli_fetch_assoc($cari_id);
        $id_user = (int)($data_id['id'] ?? 0);
    }
}

if ($id_user <= 0) {
    echo "<script>alert('Sesi user tidak valid. Silakan login ulang.'); window.location='folder login,logout dan register/login.php';</script>";
    exit();
}

if (isset($_POST['update_profil'])) {
    $nama    = mysqli_real_escape_string($koneksi, trim($_POST['nama'] ?? ''));
    $email   = mysqli_real_escape_string($koneksi, trim($_POST['email'] ?? ''));
    $no_hp   = mysqli_real_escape_string($koneksi, trim($_POST['no_hp'] ?? ''));
    $alamat  = mysqli_real_escape_string($koneksi, trim($_POST['alamat'] ?? ''));
    $kelamin = mysqli_real_escape_string($koneksi, trim($_POST['kelamin'] ?? ''));
    $nik     = mysqli_real_escape_string($koneksi, trim($_POST['nik'] ?? ''));
    $no_kk   = mysqli_real_escape_string($koneksi, trim($_POST['no_kk'] ?? ''));

    if (empty($nama) || empty($email) || empty($alamat) || empty($kelamin)) {
        echo "<script>alert('Nama, email, alamat, dan jenis kelamin wajib diisi.'); history.back();</script>";
        exit();
    }

    // Validasi dan proses ubah password (jika diisi)
    $password_lama = trim($_POST['password_lama'] ?? '');
    $password_baru = trim($_POST['password_baru'] ?? '');
    $password_konfirmasi = trim($_POST['password_konfirmasi'] ?? '');
    $update_password = false;
    $password_hash_baru = '';

    if (!empty($password_lama) || !empty($password_baru) || !empty($password_konfirmasi)) {
        // Jika salah satu field password diisi, semua harus diisi
        if (empty($password_lama)) {
            echo "<script>alert('Password lama harus diisi jika ingin mengubah password.'); history.back();</script>";
            exit();
        }
        if (empty($password_baru)) {
            echo "<script>alert('Password baru harus diisi.'); history.back();</script>";
            exit();
        }
        if (empty($password_konfirmasi)) {
            echo "<script>alert('Konfirmasi password harus diisi.'); history.back();</script>";
            exit();
        }

        // Validasi password baru minimal 6 karakter
        if (strlen($password_baru) < 6) {
            echo "<script>alert('Password baru minimal 6 karakter.'); history.back();</script>";
            exit();
        }

        // Validasi password baru dan konfirmasi cocok
        if ($password_baru !== $password_konfirmasi) {
            echo "<script>alert('Password baru dan konfirmasi tidak cocok.'); history.back();</script>";
            exit();
        }

        // Ambil password lama dari database untuk verifikasi
        $stmt_pwd = mysqli_prepare($koneksi, "SELECT password FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_pwd, "i", $id_user);
        mysqli_stmt_execute($stmt_pwd);
        $result_pwd = mysqli_stmt_get_result($stmt_pwd);
        
        if (!$result_pwd || mysqli_num_rows($result_pwd) === 0) {
            mysqli_stmt_close($stmt_pwd);
            echo "<script>alert('Data user tidak ditemukan.'); history.back();</script>";
            exit();
        }

        $user_data = mysqli_fetch_assoc($result_pwd);
        $password_db = $user_data['password'];
        mysqli_stmt_close($stmt_pwd);

        // Verifikasi password lama
        if (!password_verify($password_lama, $password_db)) {
            // Cek juga plain text untuk backward compatibility
            if (!hash_equals($password_db, $password_lama)) {
                echo "<script>alert('Password lama tidak sesuai.'); history.back();</script>";
                exit();
            }
        }

        // Jika validasi lolos, hash password baru
        $password_hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);
        $update_password = true;
    }

    if ($nik !== '' && !preg_match('/^\d{16}$/', $nik)) {
        echo "<script>alert('NIK harus 16 digit angka jika diisi.'); history.back();</script>";
        exit();
    }

    if ($no_kk !== '' && !preg_match('/^\d{16}$/', $no_kk)) {
        echo "<script>alert('Nomor KK harus 16 digit angka jika diisi.'); history.back();</script>";
        exit();
    }

    // Proses upload foto
    $foto_baru = "";
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $upload_result = secure_image_upload($_FILES['foto'], 'foto_user_' . $id_user);
        
        if (!$upload_result['success']) {
            echo "<script>alert('" . addslashes($upload_result['message']) . "'); history.back();</script>";
            exit();
        }

        $foto_baru = $upload_result['filename'];

        if (!is_dir('img')) {
            mkdir('img', 0755, true);
        }

        if (!move_uploaded_file($_FILES['foto']['tmp_name'], "img/" . $foto_baru)) {
            echo "<script>alert('Gagal menyimpan file foto.'); history.back();</script>";
            exit();
        }
    }

    // Eksekusi Update ke Database
    $setClauses = [
        'nama = ?',
        'email = ?',
        'no_hp = ?',
        'alamat = ?',
        'kelamin = ?',
        'nik = ?',
        'no_kk = ?'
    ];

    $bind_types = 'sssssss';
    $bind_values = [$nama, $email, $no_hp, $alamat, $kelamin, $nik, $no_kk];

    if (!empty($foto_baru)) {
        $setClauses[] = 'foto = ?';
        $bind_types .= 's';
        $bind_values[] = $foto_baru;
    }

    if ($update_password) {
        $setClauses[] = 'password = ?';
        $bind_types .= 's';
        $bind_values[] = $password_hash_baru;
    }

    $sql = 'UPDATE users SET ' . implode(', ', $setClauses) . ' WHERE id = ? LIMIT 1';
    $stmt = mysqli_prepare($koneksi, $sql);

    if (!$stmt) {
        echo "<script>alert('Gagal menyiapkan query update profil.'); history.back();</script>";
        exit();
    }

    $bind_values[] = $id_user;
    $bind_types .= 'i';

    mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_values);

    $update = mysqli_stmt_execute($stmt);

    if ($update) {
        $_SESSION['nama'] = $nama;
        if (!empty($foto_baru)) {
            $_SESSION['foto'] = $foto_baru;
        }

        mysqli_stmt_close($stmt);
        echo "<script>alert('Profil berhasil diupdate!'); window.location='profil.php';</script>";
        exit();
    }

    mysqli_stmt_close($stmt);
    echo "<script>alert('Gagal menyimpan data profil. Silakan coba lagi.'); history.back();</script>";
    exit();
} else {
    header("Location: profil.php");
    exit();
}
?>