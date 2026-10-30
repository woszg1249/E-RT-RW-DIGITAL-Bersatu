<?php
session_start();
include "../koneksi.php";


if ($_SESSION['role'] != 'Admin') {
    exit("Akses ditolak");
}

if (isset($_POST['update_status'])) {
    $id_tagihan = $_POST['id_tagihan'];
    $status_bayar = mysqli_real_escape_string($koneksi, $_POST['status_bayar']);

   
    $sql = "UPDATE tagihan SET status_bayar = '$status_bayar', tgl_update = CURRENT_TIMESTAMP WHERE id_tagihan = '$id_tagihan'";
    
    if (mysqli_query($koneksi, $sql)) {
        echo "<script>alert('Status pembayaran berhasil diupdate!'); window.location='kelola_tagihan.php';</script>";
    } else {
        echo "Error: " . mysqli_error($koneksi);
    }
}
?>