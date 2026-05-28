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
$id_user = $_SESSION['id'] ?? ''; 

// Jika session ID kosong, kita cari manual berdasarkan nama session agar lebih aman
if (empty($id_user)) {
    $nama_session = $_SESSION['nama'];
    $cari_id = mysqli_query($koneksi, "SELECT id FROM users WHERE nama = '$nama_session'");
    $data_id = mysqli_fetch_assoc($cari_id);
    $id_user = $data_id['id'];
}

if (isset($_POST['update_profil'])) {
    $nama    = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $email   = mysqli_real_escape_string($koneksi, $_POST['email']);
    $no_hp   = mysqli_real_escape_string($koneksi, $_POST['no_hp']);
    $alamat  = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $kelamin = mysqli_real_escape_string($koneksi, $_POST['kelamin']); // Menangkap data jenis kelamin
    
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
    if (!empty($foto_baru)) {
        $query = "UPDATE users SET 
                  nama='$nama', 
                  email='$email', 
                  no_hp='$no_hp', 
                  alamat='$alamat',
                  kelamin='$kelamin',
                  foto='$foto_baru' 
                  WHERE id='$id_user'";
    } else {
        $query = "UPDATE users SET 
                  nama='$nama', 
                  email='$email', 
                  no_hp='$no_hp', 
                  alamat='$alamat',
                  kelamin='$kelamin' 
                  WHERE id='$id_user'";
    }

    $update = mysqli_query($koneksi, $query);

    if ($update) {
        // Perbarui data session agar sinkron dengan data baru
        $_SESSION['nama']  = $nama;
        if (!empty($foto_baru)) {
            $_SESSION['foto'] = $foto_baru;
        }

        echo "<script>alert('Profil dan foto berhasil diupdate!'); window.location='profil.php';</script>";
    } else {
        echo "Error Update: " . mysqli_error($koneksi);
    }
} else {
    header("Location: profil.php");
    exit();
}
?>