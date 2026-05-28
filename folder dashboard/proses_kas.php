<?php
include "../Koneksi.php";

// 1. JALANKAN SESSION JIKA BELUM AKTIF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. PROTEKSI STRICT: Cek apakah user sudah login
if (!isset($_SESSION['nama'])) {
    header("Location: ../folder login,logout dan register/login.php");
    exit();
}

// Catatan: Pengecekan role (ADMIN) sudah dihapus di sini agar Warga bisa ikut menginput kas.

// 3. PROSES INPUT DATA KAS
if (isset($_POST['simpan_kas'])) {

    $nama_warga = mysqli_real_escape_string($koneksi, $_POST['nama_warga']);
    $keterangan = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
    $jenis_kas  = mysqli_real_escape_string($koneksi, $_POST['jenis_kas']);
    $jumlah_kas = (int) $_POST['jumlah_kas'];

    $tanggal = date("Y-m-d");

    if ($jenis_kas == "keluar") {
        $jumlah_final = -abs($jumlah_kas);
    } else {
        $jumlah_final = abs($jumlah_kas);
    }

    $sql = "INSERT INTO keuangan 
            (nama_warga, jenis, keterangan, jumlah, tanggal)
            VALUES
            ('$nama_warga', '$jenis_kas', '$keterangan', '$jumlah_final', '$tanggal')";

    $eksekusi = mysqli_query($koneksi, $sql);

    if ($eksekusi) {
        
        // ==================================================================
        // LOGIKA OTOMATIS UPDATE STATUS TAGIHAN WARGA JADI 'SUDAH BAYAR'
        // ==================================================================
        // Status tagihan hanya diupdate jika transaksi berupa uang MASUK (Pemasukan)
        if ($jenis_kas != "keluar") {
            
            // 1. Cari id_user di tabel users berdasarkan nama_warga dari form input
            $query_cari_id = mysqli_query($koneksi, "SELECT id FROM users WHERE nama = '$nama_warga'");
            $data_user = mysqli_fetch_assoc($query_cari_id);
            
            if ($data_user) {
                $id_user_warga = $data_user['id'];
                
                // --- LOGIKA OTOMATIS BULAN INDONESIA ---
                $bulan_inggris = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
                $bulan_indo    = array('Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
                
                // Menghasilkan text dinamis seperti "Mei 2026"
                $bulan_sekarang = str_replace($bulan_inggris, $bulan_indo, date('F Y')); 

                // 2. Update status_bayar di tabel tagihan secara otomatis
                $query_update_tagihan = "UPDATE tagihan 
                                         SET status_bayar = 'Sudah Bayar', tgl_update = NOW() 
                                         WHERE id_user = '$id_user_warga' 
                                         AND bulan_tahun = '$bulan_sekarang'
                                         AND status_bayar != 'Sudah Bayar'";
                                         
                mysqli_query($koneksi, $query_update_tagihan);
            }
        }
        // ==================================================================

        header("Location: input_kas.php?status=berhasil");
        exit();

    } else {
        echo "<h3>Gagal Menyimpan Data</h3>";
        echo "Error: " . mysqli_error($koneksi); // Menambahkan pesan error jika gagal
        exit();
    }

} else {
    header("Location: input_kas.php");
    exit();
}
?>