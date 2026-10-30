<?php

if (isset($_POST['kirim_notifikasi'])) {
    $pesan_iuran = mysqli_real_escape_string($koneksi, $_POST['pesan_iuran']);
    
    
    $query_update = "UPDATE users SET info_iuran = '$pesan_iuran' WHERE role = 'Warga'";
    
    if (mysqli_query($koneksi, $query_update)) {
        echo "<script>alert('Notifikasi iuran berhasil dikirim ke seluruh warga!'); window.location='dashboard.php';</script>";
    } else {
        echo "<script>alert('Gagal mengirim notifikasi: " . mysqli_error($koneksi) . "');</script>";
    }
}
?>

<div style="background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-top: 20px;">
    <h3 style="margin-bottom: 15px; color: #333;"><i class="fa-solid fa-bullhorn"></i> Buat Pemberitahuan Iuran Warga</h3>
    <form action="" method="POST">
        <div style="margin-bottom: 15px;">
            <label Governors for="pesan_iuran" style="display:block; margin-bottom: 8px; font-weight: bold;">Isi Pesan Notifikasi:</label>
            <textarea name="pesan_iuran" id="pesan_iuran" rows="4" style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc; font-family: inherit;" placeholder="Contoh: Tolong bayar iuran bulanan sebesar Rp 50.000 sebelum tanggal 10 ya!" required></textarea>
        </div>
        <button type="submit" name="kirim_notifikasi" style="background-color: #4e73df; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; transition: 0.3s;">
            <i class="fa-solid fa-paper-plane"></i> Kirim ke Semua Warga
        </button>
    </form>
</div>