<?php

if ($koneksi && isset($nama_user)) {
    
    $query_notif = mysqli_query($koneksi, "SELECT role, info_iuran FROM users WHERE nama = '$nama_user'");
    $data_notif = mysqli_fetch_assoc($query_notif);

    if ($data_notif && $data_notif['role'] === 'Warga') {
        
        // Tampilkan pengumuman umum jika tidak kosong
        if (!empty($data_notif['info_iuran'])) {
            ?>
            <div style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 15px; margin-bottom: 25px; border-radius: 10px; display: flex; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width: 100%; box-sizing: border-box;">
                <div style="margin-right: 15px; font-size: 28px;">⚠️</div>
                <div>
                    <strong style="display: block; margin-bottom: 3px; font-size: 16px;">Pengumuman Iuran RT Digital</strong>
                    <span style="font-size: 14px; line-height: 1.5;"><?= htmlspecialchars($data_notif['info_iuran']); ?></span>
                </div>
            </div>
            <?php
        }

        // ==============================================================
        // 2. BAGIAN RINCIAN TAGIHAN (Dari tabel 'tagihan')
        // ==============================================================
        // Catatan: Pastikan kamu menggunakan $id_user dari session agar akurat
        if (isset($id_user)) {
            $query_tagihan = mysqli_query($koneksi, "SELECT * FROM tagihan WHERE id_user = '$id_user' ORDER BY id_tagihan DESC");
            
            if (mysqli_num_rows($query_tagihan) > 0) {
                while ($row = mysqli_fetch_assoc($query_tagihan)) {
                    $bulan = htmlspecialchars($row['bulan_tahun']);
                    $jumlah = number_format($row['jumlah'], 0, ',', '.');
                    $status = strtolower($row['status_bayar']);
                    
                    if ($status == 'belum') {
                        ?>
                        <div style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 15px; margin-bottom: 15px; border-radius: 10px; display: flex; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width: 100%; box-sizing: border-box;">
                            <div style="margin-right: 15px; font-size: 28px;">📝</div>
                            <div>
                                <strong style="display: block; margin-bottom: 3px; font-size: 16px;">Tagihan Bulan <?= $bulan ?></strong>
                                <span style="font-size: 14px; line-height: 1.5;">Anda memiliki tagihan sebesar <b>Rp <?= $jumlah ?></b>. Status: <b>BELUM DIBAYAR</b>.</span>
                            </div>
                        </div>
                        <?php
                    } elseif ($status == 'telat') {
                        ?>
                        <div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; margin-bottom: 15px; border-radius: 10px; display: flex; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width: 100%; box-sizing: border-box;">
                            <div style="margin-right: 15px; font-size: 28px;">🚨</div>
                            <div>
                                <strong style="display: block; margin-bottom: 3px; font-size: 16px;">Peringatan Jatuh Tempo (<?= $bulan ?>)</strong>
                                <span style="font-size: 14px; line-height: 1.5;">Tagihan sebesar <b>Rp <?= $jumlah ?></b> sudah <b>TELAT</b>. Mohon segera melunasinya ke Admin.</span>
                            </div>
                        </div>
                        <?php
                    } elseif ($status == 'lunas') { // Sesuaikan jika enum di database kamu 'lunas' atau 'sudah'
                        ?>
                        <div style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; margin-bottom: 15px; border-radius: 10px; display: flex; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width: 100%; box-sizing: border-box;">
                            <div style="margin-right: 15px; font-size: 28px;">✅</div>
                            <div>
                                <strong style="display: block; margin-bottom: 3px; font-size: 16px;">Pembayaran Diterima (<?= $bulan ?>)</strong>
                                <span style="font-size: 14px; line-height: 1.5;">Terima kasih! Pembayaran tagihan sebesar <b>Rp <?= $jumlah ?></b> telah dikonfirmasi LUNAS.</span>
                            </div>
                        </div>
                        <?php
                    }
                }
            } else {
                // Opsional: Pesan jika tidak ada tagihan sama sekali
                echo "<p style='color: #6c757d; font-size: 14px;'>Belum ada riwayat tagihan iuran untuk Anda saat ini.</p>";
            }
        }
    }
}
?>