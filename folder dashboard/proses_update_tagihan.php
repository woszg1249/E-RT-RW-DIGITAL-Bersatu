<?php
include "../Koneksi.php";

require_admin();

if (isset($_POST['update_status'])) {
    // Validasi CSRF token
    if (!csrf_check()) {
        echo "<script>alert('Token keamanan tidak valid. Silakan coba lagi.'); window.location='kelola_tagihan.php';</script>";
        exit();
    }

    $id_tagihan = (int)$_POST['id_tagihan'];
    $status_bayar = trim($_POST['status_bayar'] ?? '');

    if ($id_tagihan <= 0 || $status_bayar === '') {
        echo "<script>alert('Data tidak valid.'); window.location='kelola_tagihan.php';</script>";
        exit();
    }

    $stmt = mysqli_prepare($koneksi, "UPDATE tagihan SET status_bayar = ?, tgl_update = CURRENT_TIMESTAMP WHERE id_tagihan = ?");
    mysqli_stmt_bind_param($stmt, "si", $status_bayar, $id_tagihan);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        echo "<script>alert('Status pembayaran berhasil diupdate!'); window.location='kelola_tagihan.php';</script>";
    } else {
        mysqli_stmt_close($stmt);
        echo "<script>alert('Gagal mengupdate status pembayaran.'); window.location='kelola_tagihan.php';</script>";
    }
    exit();
}
?>