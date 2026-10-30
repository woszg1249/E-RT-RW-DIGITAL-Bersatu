<?php
$session_path = dirname(__FILE__);
session_start();
include '../Koneksi.php';

if (!isset($_SESSION['id'])) {
    header('Location: ../folder login,logout dan register/login.php');
    exit();
}

$user_id = $_SESSION['id'];
$nama_user = $_SESSION['nama'] ?? 'Warga RT06/RW19';
$role_user = $_SESSION['role'] ?? 'WARGA';

// safer avatar fallback: prefer session foto when valid, else local file in img/, else external placeholder
$raw_foto = $_SESSION['foto'] ?? '';
$tampilan_foto = 'https://via.placeholder.com/90?text=RT';
if ($raw_foto) {
    if (preg_match('#^https?://#i', $raw_foto)) {
        $tampilan_foto = $raw_foto;
    } else {
        $foto_name = basename($raw_foto);
        $local_img_path = $session_path . '/../img/' . $foto_name;
        if (file_exists($local_img_path)) {
            $tampilan_foto = '../img/' . $foto_name;
        } else {
            $other_possible = $session_path . '/../' . $foto_name;
            if (file_exists($other_possible)) {
                $tampilan_foto = '../' . $foto_name;
            }
        }
    }
}

$nama = $_POST['nama'] ?? $nama_user;
$jenis_surat = $_POST['jenis_surat'] ?? 'Surat Keterangan Tidak Mampu (SKTM)';
$nik = $_POST['nik'] ?? '';
$ttl = $_POST['ttl'] ?? '';
$alamat = $_POST['alamat'] ?? '';
$tujuan = $_POST['tujuan'] ?? '';
$nama_rt = $_POST['nama_rt'] ?? 'RT 06';
$nama_rw = $_POST['nama_rw'] ?? 'RW 19';
$kelurahan = $_POST['kelurahan'] ?? 'Pengasinan';
$kecamatan = $_POST['kecamatan'] ?? 'Rawa Lumbu';
$kota = $_POST['kota'] ?? 'Bekasi';
$tanggal_permohonan = $_POST['tanggal_permohonan'] ?? date('Y-m-d');
$detail_usaha = $_POST['detail_usaha'] ?? '';
$keterangan = $_POST['keterangan'] ?? '';

$success_message = '';
$error_message = '';
$preview_data = null;

$create_table_sql = "CREATE TABLE IF NOT EXISTS surat_pengajuan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    nama VARCHAR(255) NOT NULL,
    jenis_surat VARCHAR(100) NOT NULL,
    nik VARCHAR(50),
    ttl VARCHAR(255),
    alamat TEXT,
    tujuan TEXT,
    nama_rt VARCHAR(100),
    nama_rw VARCHAR(100),
    kelurahan VARCHAR(100),
    kecamatan VARCHAR(100),
    kota VARCHAR(100),
    tanggal_permohonan DATE,
    detail_usaha TEXT,
    keterangan TEXT,
    status VARCHAR(50) DEFAULT 'Menunggu',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!mysqli_query($koneksi, $create_table_sql)) {
    $error_message = 'Gagal membuat tabel pengajuan surat: ' . mysqli_error($koneksi);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_surat'])) {
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $jenis_surat = mysqli_real_escape_string($koneksi, $_POST['jenis_surat']);
    $nik = mysqli_real_escape_string($koneksi, $_POST['nik']);
    $ttl = mysqli_real_escape_string($koneksi, $_POST['ttl']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $tujuan = mysqli_real_escape_string($koneksi, $_POST['tujuan']);
    $nama_rt = mysqli_real_escape_string($koneksi, $_POST['nama_rt']);
    $nama_rw = mysqli_real_escape_string($koneksi, $_POST['nama_rw']);
    $kelurahan = mysqli_real_escape_string($koneksi, $_POST['kelurahan']);
    $kecamatan = mysqli_real_escape_string($koneksi, $_POST['kecamatan']);
    $kota = mysqli_real_escape_string($koneksi, $_POST['kota']);
    $tanggal_permohonan = mysqli_real_escape_string($koneksi, $_POST['tanggal_permohonan']);
    $detail_usaha = mysqli_real_escape_string($koneksi, $_POST['detail_usaha']);
    $keterangan = mysqli_real_escape_string($koneksi, $_POST['keterangan']);

    if (empty($nama) || empty($jenis_surat) || empty($alamat)) {
        $error_message = 'Mohon lengkapi nama, jenis surat, dan alamat.';
    } else {
        $insert_sql = "INSERT INTO surat_pengajuan (id_user, nama, jenis_surat, nik, ttl, alamat, tujuan, nama_rt, nama_rw, kelurahan, kecamatan, kota, tanggal_permohonan, detail_usaha, keterangan) VALUES ('$user_id', '$nama', '$jenis_surat', '$nik', '$ttl', '$alamat', '$tujuan', '$nama_rt', '$nama_rw', '$kelurahan', '$kecamatan', '$kota', '$tanggal_permohonan', '$detail_usaha', '$keterangan')";

        if (mysqli_query($koneksi, $insert_sql)) {
            $success_message = 'Pengajuan surat berhasil di simpan. Silakan cetak atau tunggu proses RT/RW.';
            $preview_data = [
                'nama' => $nama,
                'jenis_surat' => $jenis_surat,
                'nik' => $nik,
                'ttl' => $ttl,
                'alamat' => $alamat,
                'tujuan' => $tujuan,
                'nama_rt' => $nama_rt,
                'nama_rw' => $nama_rw,
                'kelurahan' => $kelurahan,
                'kecamatan' => $kecamatan,
                'kota' => $kota,
                'tanggal_permohonan' => $tanggal_permohonan,
                'detail_usaha' => $detail_usaha,
                'keterangan' => $keterangan,
            ];
        } else {
            $error_message = 'Gagal menyimpan pengajuan: ' . mysqli_error($koneksi);
        }
    }
}

$request_list = [];
$list_sql = "SELECT * FROM surat_pengajuan WHERE id_user = '$user_id' ORDER BY created_at DESC";
$result = mysqli_query($koneksi, $list_sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $request_list[] = $row;
    }
}
$title = 'Pengajuan Surat Warga - RT RW Digital Bersatu';
include '../includes/header.php';
?>
<style>
        .request-card { background: #ffffff; padding: 24px; border-radius: 18px; box-shadow: 0 14px 40px rgba(16, 78, 139, 0.08); margin-bottom: 24px; }
        .request-card h2 { margin-bottom: 16px; color: #1d3557; }
        .request-grid { display: grid; gap: 18px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .request-grid .form-group { display: flex; flex-direction: column; }
        .request-grid .form-group label { margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .request-grid .form-group input,
        .request-grid .form-group select,
        .request-grid .form-group textarea { border: 1px solid #d8e2ef; border-radius: 12px; padding: 12px 14px; font-size: 0.95rem; color: #2f3b52; background: #fafbff; transition: border-color 0.2s ease; }
        .request-grid .form-group textarea { min-height: 90px; resize: vertical; }
        .request-grid .form-group input:focus,
        .request-grid .form-group select:focus,
        .request-grid .form-group textarea:focus { outline: none; border-color: #3a86ff; background: #ffffff; }
        .form-actions { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 10px; }
        .btn-submit { background: #3498db; border: none; color: #ffffff; padding: 13px 20px; border-radius: 12px; cursor: pointer; font-weight: 700; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 10px 20px rgba(52,152,219,0.25); }
        .letter-preview { background: #f7fbff; border: 1px solid rgba(52, 152, 219, 0.15); padding: 24px; border-radius: 18px; margin-top: 20px; }
        .letter-preview .letter-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 18px; }
        .city-logo { display: flex; align-items: center; gap: 14px; }
        .city-logo .logo-box { width: 110px; height: 110px; display: flex; flex-shrink: 0; align-items: center; justify-content: center; overflow: hidden; box-sizing: border-box; }
        .city-logo .logo-box img { max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; display: block; }
        .letter-preview h3 { margin: 0 0 6px; font-size: 1.05rem; letter-spacing: 0.02em; }
        .letter-preview .letter-title { font-size: 1.25rem; color: #0b3d91; margin-bottom: 8px; }
        .letter-preview .letter-meta { color: #4b5d78; font-size: 0.95rem; margin-bottom: 20px; }
        .letter-preview .letter-body p { margin-bottom: 14px; line-height: 1.75; color: #273248; }
        .letter-preview .signature-section { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 26px; }
        .signature-box { border-top: 1px dashed #7da3c6; padding-top: 10px; color: #23395b; font-weight: 600; }
        .signature-box span { display: block; color: #54698f; font-size: 0.95rem; margin-top: 6px; font-weight: 500; }
        .request-status { margin-top: 20px; border-collapse: collapse; width: 100%; }
        .request-status th,
        .request-status td { border: 1px solid #dce6f1; padding: 12px 14px; text-align: left; font-size: 0.95rem; }
        .request-status th { background: #eaf3ff; color: #34495e; }
        .request-status tbody tr:nth-child(even) { background: #f7fbff; }
        .badge-status { display: inline-flex; padding: 5px 10px; border-radius: 999px; font-size: 0.85rem; font-weight: 700; background: #d1e7ff; color: #0f4b8b; }
        @media (max-width: 980px) {
            .request-grid { grid-template-columns: 1fr; }
            .letter-preview .letter-header { flex-direction: column; align-items: flex-start; }
            .signature-section { grid-template-columns: 1fr; }
        }
    </style>

<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>

    <div class="app-main">
        <header class="app-topbar">
            <div class="app-topbar__left">
                <button id="toggle-sidebar" class="app-topbar__toggle" aria-label="Toggle menu" aria-expanded="true">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <strong style="color: var(--text);">Pengajuan Surat</strong>
                </div>
            </div>
            <div class="app-topbar__actions">
                <?php if (strtoupper($role_user) === 'ADMIN'): ?>
                    <a href="kelola_surat.php" class="ghost-btn" style="text-decoration:none;">
                        <i class="fa-solid fa-file-circle-check"></i> Kelola Surat Masuk
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-file-signature" style="color: var(--primary);"></i> Pengajuan Surat Warga</h1>
                    <p style="margin: 6px 0 0; color: var(--text-soft);">Permohonan surat pengantar pengurusan administrasi warga RT/RW.</p>
                </div>
            </div>
<div class="request-card">
                <h2>Pengajuan Surat Warga RT06/RW19</h2>
                <p style="color: #5a6b86; margin-bottom: 18px;">Ajukan surat seperti KTP, SKU, SKTM, Surat Domisili, atau permohonan lain untuk Kecamatan Rawa Lumbu, Kelurahan Pengasinan, Kota Bekasi.</p>

                <?php if (!empty($success_message)): ?>
                    <div style="background:#e4f4ff;border:1px solid #a9d8ff;color:#0b5fae;padding:14px;border-radius:12px;margin-bottom:16px;">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div style="background:#ffe5e5;border:1px solid #f4b3b3;color:#8a1c1c;padding:14px;border-radius:12px;margin-bottom:16px;">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="request-form">
                    <div class="request-grid">
                        <div class="form-group">
                            <label>Nama Lengkap</label>
                            <input type="text" name="nama" value="<?php echo htmlspecialchars($nama); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Jenis Surat</label>
                            <select name="jenis_surat" required>
                                <option value="Surat Keterangan Tidak Mampu (SKTM)" <?php echo $jenis_surat === 'Surat Keterangan Tidak Mampu (SKTM)' ? 'selected' : ''; ?>>Surat Keterangan Tidak Mampu (SKTM)</option>
                                <option value="Surat Keterangan Usaha (SKU)" <?php echo $jenis_surat === 'Surat Keterangan Usaha (SKU)' ? 'selected' : ''; ?>>Surat Keterangan Usaha (SKU)</option>
                                <option value="Surat Keterangan Domisili" <?php echo $jenis_surat === 'Surat Keterangan Domisili' ? 'selected' : ''; ?>>Surat Keterangan Domisili</option>
                                <option value="Surat Permohonan KTP" <?php echo $jenis_surat === 'Surat Permohonan KTP' ? 'selected' : ''; ?>>Surat Permohonan KTP</option>
                                <option value="Surat Pengantar SKCK" <?php echo $jenis_surat === 'Surat Pengantar SKCK' ? 'selected' : ''; ?>>Surat Pengantar SKCK</option>
                                <option value="Surat Keterangan Pengantar Lainnya" <?php echo $jenis_surat === 'Surat Keterangan Pengantar Lainnya' ? 'selected' : ''; ?>>Surat Keterangan Pengantar Lainnya</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>NIK</label>
                            <input type="text" name="nik" value="<?php echo htmlspecialchars($nik); ?>" placeholder="Contoh: 327301xxxxxxxxxx">
                        </div>
                        <div class="form-group">
                            <label>Tempat / Tanggal Lahir</label>
                            <input type="text" name="ttl" value="<?php echo htmlspecialchars($ttl); ?>" placeholder="Contoh: Bekasi, 10 Januari 1980">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Alamat Lengkap</label>
                            <textarea name="alamat" required><?php echo htmlspecialchars($alamat); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Tujuan Pengajuan</label>
                            <textarea name="tujuan"><?php echo htmlspecialchars($tujuan); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Detail Usaha / Keterangan</label>
                            <textarea name="detail_usaha"><?php echo htmlspecialchars($detail_usaha); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Nama RT</label>
                            <input type="text" name="nama_rt" value="<?php echo htmlspecialchars($nama_rt); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Nama RW</label>
                            <input type="text" name="nama_rw" value="<?php echo htmlspecialchars($nama_rw); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Kelurahan</label>
                            <input type="text" name="kelurahan" value="<?php echo htmlspecialchars($kelurahan); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Kecamatan</label>
                            <input type="text" name="kecamatan" value="<?php echo htmlspecialchars($kecamatan); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Kota / Kabupaten</label>
                            <input type="text" name="kota" value="<?php echo htmlspecialchars($kota); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Tanggal Permohonan</label>
                            <input type="date" name="tanggal_permohonan" value="<?php echo htmlspecialchars($tanggal_permohonan); ?>" required>
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Catatan Tambahan</label>
                            <textarea name="keterangan"><?php echo htmlspecialchars($keterangan); ?></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="submit_surat" class="btn-submit"><i class="fa-solid fa-paper-plane"></i> Ajukan Surat</button>
                        <button type="button" id="btn-preview-print" class="btn-submit" style="background:#0b69a3;"><i class="fa-solid fa-print"></i> Pratinjau & Cetak</button>
                    </div>
                </form>

                <script>
                    // Buat pratinjau surat dari nilai form tanpa mengirim ke server, lalu buka jendela cetak
                    function buildLetterHtml(data) {
                        const tanggal = new Date(data.tanggal_permohonan).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
                        return `<!doctype html><html><head><meta charset="utf-8"><title>Surat - ${data.jenis_surat}</title>
                        <style>body{font-family:Segoe UI, Tahoma, sans-serif;padding:28px;color:#1f2d3d} .header{display:flex;justify-content:space-between;align-items:center;gap:20px} .logo-container{display:flex;align-items:center;gap:12px} .logo-box{width:110px;height:110px;display:flex;align-items:center;justify-content:center;overflow:hidden} .logo-img{max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;display:block} .city-info{flex:1} .city-info h3{margin:0 0 4px;color:#0b3d91;font-size:16px} .city-info p{margin:0;color:#466a91;font-size:13px} .header-right{text-align:right;color:#164975;font-size:15px} .header-right strong{display:block} h2{color:#0b3d91;margin:24px 0 12px} .body-text{line-height:1.7;color:#273248;font-size:14px} .body-text p{margin:0 0 14px;} .sig{margin-top:40px;display:flex;justify-content:space-between} .sig div{width:40%;text-align:center}</style>
                        </head><body>
                        <div class="header">
                            <div class="logo-container">
                                <div class="logo-box"><img src="../img/logo_bekasi.png" class="logo-img" alt="Logo Bekasi"></div>
                                <div class="city-info">
                                    <h3>PEMERINTAH KOTA BEKASI</h3>
                                    <p>Kelurahan Pengasinan, Kecamatan Rawa Lumbu</p>
                                </div>
                            </div>
                            <div class="header-right">
                                <strong>RT 06 / RW 19</strong>
                                <span>${tanggal}</span>
                            </div>
                        </div>
                        <h2>${data.jenis_surat}</h2>
                        <div class="body-text">
                            <p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p>
                            <p><strong>Nama :</strong> ${data.nama}</p>
                            <p><strong>NIK :</strong> ${data.nik}</p>
                            <p><strong>Tempat/Tgl Lahir :</strong> ${data.ttl}</p>
                            <p><strong>Alamat :</strong> ${data.alamat}</p>
                            ${data.tujuan ? `<p><strong>Tujuan :</strong> ${data.tujuan}</p>` : ''}
                            ${data.detail_usaha ? `<p><strong>Detail Usaha / Keterangan :</strong> ${data.detail_usaha}</p>` : ''}
                            ${data.keterangan ? `<p><strong>Catatan :</strong> ${data.keterangan}</p>` : ''}
                            <p>Demikian surat ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>
                        </div>
                        <div class="sig">
                            <div><strong>Ketua RT 06</strong><p>${data.nama_rt}</p></div>
                            <div><strong>Ketua RW 19</strong><p>${data.nama_rw}</p></div>
                        </div>
</body></html>`;
                    }

                    document.getElementById('btn-preview-print').addEventListener('click', function() {
                        const f = document.querySelector('.request-form');
                        if (!f) return;

                        const data = {
                            nama: f.nama.value || '',
                            jenis_surat: f.jenis_surat.value || '',
                            nik: f.nik.value || '',
                            ttl: f.ttl.value || '',
                            alamat: f.alamat.value || '',
                            tujuan: f.tujuan.value || '',
                            detail_usaha: f.detail_usaha.value || '',
                            keterangan: f.keterangan.value || '',
                            nama_rt: f.nama_rt.value || '',
                            nama_rw: f.nama_rw.value || '',
                            tanggal_permohonan: f.tanggal_permohonan.value || new Date().toISOString().slice(0,10)
                        };

                        const html = buildLetterHtml(data);
                        const win = window.open('','_blank','width=900,height=700');
                        if (win) {
                            win.document.open();
                            win.document.write(html);
                            win.document.close();
                            setTimeout(function() { win.print(); }, 400);
                        } else {
                            alert('Gagal membuka jendela pratinjau. Pastikan pop-up tidak diblokir.');
                        }
                    });
                </script>
            </div>

            <?php if ($preview_data): ?>
                <div class="letter-preview">
                    <div class="letter-header">
                        <div class="city-logo">
                            <div class="logo-box">
                                <img src="../img/logo_bekasi.png" alt="Logo Bekasi" class="logo-img" onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/b/b2/Lambang_Kota_Bekasi.png'">
                            </div>
                            <div>
                                <h3>PEMERINTAH KOTA BEKASI</h3>
                                <p style="margin:0; color:#466a91; letter-spacing:0.03em;">Kelurahan Pengasinan, Kecamatan Rawa Lumbu</p>
                            </div>
                        </div>
                        <div style="text-align:right; color:#164975; font-size:0.95rem;">
                            <strong>RT 06 / RW 19</strong><br>
                            <span>Tanggal: <?php echo date('d F Y', strtotime($preview_data['tanggal_permohonan'])); ?></span>
                        </div>
                    </div>
                    <div>
                        <p class="letter-title"><?php echo htmlspecialchars($preview_data['jenis_surat']); ?></p>
                        <p class="letter-meta">Nomor: 06/RT06-RW19/<?php echo date('Y'); ?></p>
                    </div>
                    <div class="letter-body">
                        <p>Yang bertanda tangan di bawah ini, Ketua RT 06 dan Ketua RW 19, menerangkan bahwa:</p>
                        <p><strong>Nama&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: </strong><?php echo htmlspecialchars($preview_data['nama']); ?></p>
                        <p><strong>NIK&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: </strong><?php echo htmlspecialchars($preview_data['nik']); ?></p>
                        <p><strong>Tempat/Tgl Lahir&nbsp;&nbsp;&nbsp;: </strong><?php echo htmlspecialchars($preview_data['ttl']); ?></p>
                        <p><strong>Alamat&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: </strong><?php echo htmlspecialchars($preview_data['alamat']); ?></p>
                        <?php if (!empty($preview_data['tujuan'])): ?>
                            <p><strong>Tujuan&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: </strong><?php echo htmlspecialchars($preview_data['tujuan']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($preview_data['detail_usaha'])): ?>
                            <p><strong>Detail Usaha / Keterangan&nbsp;&nbsp;: </strong><?php echo htmlspecialchars($preview_data['detail_usaha']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($preview_data['keterangan'])): ?>
                            <p><strong>Catatan&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: </strong><?php echo htmlspecialchars($preview_data['keterangan']); ?></p>
                        <?php endif; ?>
                        <p>Demikian surat keterangan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>
                    </div>
                    <div class="signature-section">
                        <div class="signature-box">
                            Ketua RT 06
                            <span><?php echo htmlspecialchars($preview_data['nama_rt']); ?></span>
                        </div>
                        <div class="signature-box">
                            Ketua RW 19
                            <span><?php echo htmlspecialchars($preview_data['nama_rw']); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($request_list)): ?>
                <div class="request-card">
                    <h2>Riwayat Pengajuan Surat</h2>
                    <table class="request-status">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jenis Surat</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($request_list as $item): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($item['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['jenis_surat']); ?></td>
                                    <td><span class="badge-status"><?php echo htmlspecialchars($item['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars(substr($item['keterangan'], 0, 70)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
                </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
