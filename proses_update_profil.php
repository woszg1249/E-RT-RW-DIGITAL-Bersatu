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
        $file_tmp  = $_FILES['foto']['tmp_name'];
        $file_name = $_FILES['foto']['name'];
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $ekstensi_diperbolehkan = ['jpg', 'jpeg', 'png'];

        if (in_array($extension, $ekstensi_diperbolehkan)) {
            $foto_baru = "foto_" . $id_user . "_" . time() . "." . $extension;

            if (!is_dir('img')) {
                mkdir('img', 0755, true);
            }

            move_uploaded_file($file_tmp, "img/" . $foto_baru);
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

    $err = mysqli_error($koneksi);
    mysqli_stmt_close($stmt);
    echo "<script>alert('Gagal menyimpan data profil. Error: " . addslashes($err) . "'); history.back();</script>";
    exit();
} else {
    header("Location: profil.php");
    exit();
}
?>