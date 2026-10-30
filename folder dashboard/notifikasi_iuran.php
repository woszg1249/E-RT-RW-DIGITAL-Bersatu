<?php

if ($koneksi && isset($nama_user)) {
    
    $query_notif = mysqli_query($koneksi, "SELECT role, info_iuran, id FROM users WHERE nama = '$nama_user'");
    $data_notif = mysqli_fetch_assoc($query_notif);
    $current_user_id = $data_notif['id'] ?? $id_user ?? 0;

    // load dismissed notifications for this user
    $dismissed_ids = [];
    if ($current_user_id) {
        $q_dis = mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS notif_dismissed (id INT AUTO_INCREMENT PRIMARY KEY, id_tagihan INT NOT NULL, id_user INT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $q_load = mysqli_query($koneksi, "SELECT id_tagihan FROM notif_dismissed WHERE id_user = '$current_user_id'");
        if ($q_load) {
            while ($r = mysqli_fetch_assoc($q_load)) {
                $dismissed_ids[] = (int)$r['id_tagihan'];
            }
        }
    }

    if ($data_notif && $data_notif['role'] === 'Warga') {
        
        
        if (!empty($data_notif['info_iuran'])) {
            // skip if user dismissed the general announcement (we use id_tagihan = 0 to represent it)
            if (!in_array(0, $dismissed_ids)) {
            ?>
            <div class="notif-row" data-id="0" style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 15px; margin-bottom: 25px; border-radius: 10px; display: flex; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width: 100%; box-sizing: border-box; justify-content: space-between;">
                <div style="display:flex;align-items:center;flex:1;margin-right:12px;">
                    <div style="margin-right: 15px; font-size: 28px;">⚠️</div>
                    <div>
                        <strong style="display: block; margin-bottom: 3px; font-size: 16px;">Pengumuman Iuran RT RW Digital Bersatu</strong>
                        <span style="font-size: 14px; line-height: 1.5;"><?= htmlspecialchars($data_notif['info_iuran']); ?></span>
                    </div>
                </div>
                <div style="min-width:92px; text-align:right;">
                    <button class="btn-dismiss" data-id="0" style="background:rgba(255,255,255,0.9);border:1px solid rgba(133,100,4,0.12);color:#856404;cursor:pointer;font-weight:700;padding:6px 10px;border-radius:8px">Hapus</button>
                </div>
            </div>
            <?php
            }
        }

        
        if (isset($id_user)) {
            $query_tagihan = mysqli_query($koneksi, "SELECT * FROM tagihan WHERE id_user = '$id_user' ORDER BY id_tagihan DESC");
            
            if (mysqli_num_rows($query_tagihan) > 0) {
                while ($row = mysqli_fetch_assoc($query_tagihan)) {
                    // skip if dismissed for this user
                    if (in_array((int)$row['id_tagihan'], $dismissed_ids)) continue;
                    $bulan = htmlspecialchars($row['bulan_tahun']);
                    $jumlah = number_format($row['jumlah'], 0, ',', '.');
                    $status = strtolower($row['status_bayar']);
                    
                    if ($status == 'belum') {
                        ?>
                        <div class="notif-row" data-id="<?= $row['id_tagihan'] ?>" style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 15px; margin-bottom: 15px; border-radius: 10px; display: flex; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width: 100%; box-sizing: border-box; justify-content: space-between;">
                            <div style="margin-right: 15px; font-size: 28px;">📝</div>
                            <div style="flex:1; margin-right:20px;">
                                <strong style="display: block; margin-bottom: 3px; font-size: 16px;">Tagihan Bulan <?= $bulan ?></strong>
                                <span style="font-size: 14px; line-height: 1.5;">Anda memiliki tagihan sebesar <b>Rp <?= $jumlah ?></b>. Status: <b>BELUM DIBAYAR</b>.</span>
                            </div>
                            <div style="min-width:92px; text-align:right;">
                                <a href="proses_kas.php?pay_tagihan=<?= $row['id_tagihan'] ?>" style="background:#16a34a; color:#fff; text-decoration:none; padding:6px 12px; border-radius:8px; font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:5px; margin-right:8px; white-space:nowrap;"><i class="fa-solid fa-bolt"></i> Bayar Real-Time</a>
                                <button class="btn-dismiss" data-id="<?= $row['id_tagihan'] ?>" style="background:rgba(255,255,255,0.9);border:1px solid rgba(133,100,4,0.12);color:#856404;cursor:pointer;font-weight:700;padding:6px 10px;border-radius:8px">Hapus</button>
                            </div>
                        </div>
                        <?php
                    } elseif ($status == 'telat') {
                        ?>
                        <div class="notif-row" data-id="<?= $row['id_tagihan'] ?>" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; margin-bottom: 15px; border-radius: 10px; display: flex; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width: 100%; box-sizing: border-box; justify-content: space-between;">
                            <div style="margin-right: 15px; font-size: 28px;">🚨</div>
                            <div style="flex:1; margin-right:20px;">
                                <strong style="display: block; margin-bottom: 3px; font-size: 16px;">Peringatan Jatuh Tempo (<?= $bulan ?>)</strong>
                                <span style="font-size: 14px; line-height: 1.5;">Tagihan sebesar <b>Rp <?= $jumlah ?></b> sudah <b>TELAT</b>. Mohon segera melunasinya ke Admin.</span>
                            </div>
                            <div style="min-width:92px; text-align:right;">
                                <a href="proses_kas.php?pay_tagihan=<?= $row['id_tagihan'] ?>" style="background:#dc2626; color:#fff; text-decoration:none; padding:6px 12px; border-radius:8px; font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:5px; margin-right:8px; white-space:nowrap;"><i class="fa-solid fa-bolt"></i> Bayar Sekarang</a>
                                <button class="btn-dismiss" data-id="<?= $row['id_tagihan'] ?>" style="background:rgba(255,255,255,0.95);border:1px solid rgba(114,28,36,0.08);color:#721c24;cursor:pointer;font-weight:700;padding:6px 10px;border-radius:8px">Hapus</button>
                            </div>
                        </div>
                        <?php
                    } elseif ($status == 'lunas') { // Sesuaikan jika enum di database kamu 'lunas' atau 'sudah'
                        ?>
                        <div class="notif-row" data-id="<?= $row['id_tagihan'] ?>" style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; margin-bottom: 15px; border-radius: 10px; display: flex; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width: 100%; box-sizing: border-box; justify-content: space-between;">
                            <div style="margin-right: 15px; font-size: 28px;">✅</div>
                            <div style="flex:1; margin-right:20px;">
                                <strong style="display: block; margin-bottom: 3px; font-size: 16px;">Pembayaran Diterima (<?= $bulan ?>)</strong>
                                <span style="font-size: 14px; line-height: 1.5;">Terima kasih! Pembayaran tagihan sebesar <b>Rp <?= $jumlah ?></b> telah dikonfirmasi LUNAS.</span>
                            </div>
                            <div style="min-width:92px; text-align:right;">
                                <button class="btn-dismiss" data-id="<?= $row['id_tagihan'] ?>" style="background:rgba(255,255,255,0.95);border:1px solid rgba(21,87,36,0.08);color:#155724;cursor:pointer;font-weight:700;padding:6px 10px;border-radius:8px">Hapus</button>
                            </div>
                        </div>
                        <?php
                    }
                }
            } else {
                
                echo "<p style='color: #6c757d; font-size: 14px;'>Belum ada riwayat tagihan iuran untuk Anda saat ini.</p>";
            }
        }
    }
}
?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    function bindButtons(){
        document.querySelectorAll('.btn-dismiss').forEach(function(btn){
            btn.removeEventListener('click', handleDismiss);
            btn.addEventListener('click', handleDismiss);
        });
    }

    async function handleDismiss(e){
        var id = this.dataset.id;
        if (!id) return;
        if (!confirm('Hapus notifikasi ini?')) return;

        try {
            const res = await fetch('proses_hapus_notifikasi.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id_tagihan: id })
            });
            const data = await res.json();
            if (data.success) {
                // remove DOM row
                var row = this.closest('.notif-row');
                if (row) row.remove();
            } else {
                alert('Gagal: ' + (data.message || 'error'));
            }
        } catch(err){
            alert('Gagal koneksi');
        }
    }

    bindButtons();
    // bind dismiss-all button if present
    var btnAll = document.getElementById('btn-dismiss-all');
    if (btnAll) {
        btnAll.addEventListener('click', async function(){
            if (!confirm('Hapus semua notifikasi yang terlihat?')) return;
            var rows = Array.from(document.querySelectorAll('.notif-row'));
            for (let r of rows) {
                let id = r.dataset.id;
                if (typeof id === 'undefined') continue;
                try {
                    const res = await fetch('proses_hapus_notifikasi.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete', id_tagihan: id })
                    });
                    const data = await res.json();
                    if (data.success) {
                        r.remove();
                    }
                } catch (e) {
                    console.error('gagal hapus', id, e);
                }
            }
        });
    }
});
</script>