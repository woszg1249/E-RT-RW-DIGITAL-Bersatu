<?php

include "../Koneksi.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nama'])) {
    header("Location: ../folder login,logout dan register/login.php");
    exit();
}

$role = isset($_SESSION['role']) ? strtoupper($_SESSION['role']) : 'WARGA';

if ($role !== 'ADMIN') {
    header("Location: dashboard.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo "ID tidak valid";
    exit();
}

$query = "SELECT p.*, u.nama as nama_warga, u.alamat, u.no_hp, u.nik, u.no_kk FROM posyandu p
         LEFT JOIN users u ON p.id_warga = u.id
         WHERE p.id = '$id'";

$result = mysqli_query($koneksi, $query);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    echo "Data tidak ditemukan";
    exit();
}

function getBBCategory($usia, $berat, $jenisKelamin = 'Laki-laki') {
    $reference = [
        'Laki-laki' => [
            1 => [3.2, 4.7], 2 => [4.5, 5.8], 3 => [5.5, 6.8], 4 => [6.1, 7.8],
            5 => [6.7, 8.6], 6 => [7.3, 9.3], 7 => [7.8, 9.8], 8 => [8.3, 10.5],
            9 => [8.7, 11.0], 10 => [9.1, 11.4], 11 => [9.4, 11.8], 12 => [9.7, 12.2]
        ],
        'Perempuan' => [
            1 => [3.1, 4.5], 2 => [4.3, 5.6], 3 => [5.2, 6.7], 4 => [5.9, 7.5],
            5 => [6.4, 8.1], 6 => [7.0, 8.7], 7 => [7.5, 9.2], 8 => [7.9, 9.8],
            9 => [8.3, 10.3], 10 => [8.7, 10.8], 11 => [9.0, 11.2], 12 => [9.3, 11.6]
        ]
    ];

    $bulan = max(1, min(12, (int)$usia));
    $jenis = in_array($jenisKelamin, ['Laki-laki', 'Perempuan']) ? $jenisKelamin : 'Laki-laki';
    $range = $reference[$jenis][$bulan] ?? [3.0, 12.0];
    $min = $range[0];
    $max = $range[1];

    if ($berat < $min) return 'Rendah';
    if ($berat > $max) return 'Tinggi';
    return 'Normal';
}

function getStatusClass($status) {
    $status = strtolower(trim($status));

    if ($status === 'sehat') return 'normal';
    if ($status === 'perlu perhatian') return 'kurang';
    if ($status === 'rawan') return 'tidak';

    return 'normal';
}

$kategori_bb = getBBCategory($data['usia_bulan'], $data['berat_badan'], $data['jenis_kelamin'] ?? 'Laki-laki');
$statusClass = getStatusClass($data['status_kesehatan'] ?? 'Sehat');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Hasil Pemeriksaan Posyandu</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: white; padding: 20px; }
        .print-container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border: 2px solid #333; }
        .header { text-align: center; border-bottom: 3px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .header-title { text-align: center; flex: 1; }
        .logo { width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; background: #ffffff; border-radius: 50%; overflow: hidden; border: 2px solid #2c3e50; }
        .logo img { width: 100%; height: 100%; object-fit: contain; }
        .header h1 { font-size: 22px; color: #2c3e50; font-weight: bold; margin-bottom: 5px; }
        .header p { font-size: 13px; color: #555; }
        .section { margin-bottom: 25px; }
        .section-title { font-size: 14px; font-weight: bold; color: white; background: #2c3e50; padding: 10px 15px; margin-bottom: 12px; border-radius: 4px; }
        .info-box { background: #f0f0f0; padding: 15px; margin-bottom: 20px; border-left: 4px solid #e74c3c; border-radius: 4px; }
        .info-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 10px; }
        .info-field { font-size: 13px; }
        .info-label { font-weight: bold; color: #2c3e50; width: 160px; display: inline-block; }
        .info-value { color: #333; }
        .measurement-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .measurement-item { background: #f9f9f9; padding: 12px; border: 1px solid #ddd; border-radius: 4px; }
        .measurement-label { font-size: 12px; color: #666; margin-bottom: 5px; }
        .measurement-value { font-size: 18px; font-weight: bold; color: #2c3e50; }
        .measurement-category { font-size: 11px; color: #e74c3c; margin-top: 3px; }
        .status-box { background: #e8f5e9; border-left: 4px solid #27ae60; padding: 12px; border-radius: 4px; margin-bottom: 15px; }
        .status-box.kurang { background: #fff8e1; border-left-color: #f39c12; }
        .status-box.tidak { background: #ffebee; border-left-color: #e74c3c; }
        .status-label { font-size: 12px; color: #666; margin-bottom: 3px; }
        .status-value { font-size: 14px; font-weight: bold; color: #27ae60; }
        .status-box.kurang .status-value { color: #f39c12; }
        .status-box.tidak .status-value { color: #e74c3c; }
        .catatan-box { background: #f5f5f5; padding: 12px; border-radius: 4px; border: 1px solid #ddd; min-height: 60px; }
        .catatan-box p { font-size: 13px; line-height: 1.6; color: #333; white-space: pre-wrap; }
        .signature-section { margin-top: 40px; border-top: 1px solid #ddd; padding-top: 20px; }
        .signature-box { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 30px; text-align: center; margin-bottom: 60px; }
        .signature-line { font-size: 12px; color: #666; margin-bottom: 60px; }
        .signature-name { border-top: 1px solid #333; padding-top: 5px; font-size: 12px; font-weight: bold; }
        .footer { text-align: right; font-size: 11px; color: #999; margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; }
        @media print { body { padding: 0; } .print-btn { display: none; } }
        .print-btn { text-align: center; margin-bottom: 20px; }
        .print-btn button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; }
        .print-btn button:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="print-btn">
        <button onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
    </div>

    <div class="print-container">
        <div class="header">
            <div class="header-top">
                <div class="logo">
                    <img src="../img/logo_bekasi.png" alt="Logo Bekasi">
                </div>
                <div class="header-title">
                    <h1>POSYANDU KESEHATAN ANAK</h1>
                    <p>RT 6 RW 19 - Kota Bekasi</p>
                    <p>Hasil Pemeriksaan</p>
                </div>
                <div style="width: 70px;"></div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">📋 DATA ORANGTUA / WARGA</div>
            <div class="info-row">
                <div class="info-field">
                    <span class="info-label">Nama Warga:</span>
                    <span class="info-value"><?php echo htmlspecialchars($data['nama_warga'] ?? '-'); ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">NIK:</span>
                    <span class="info-value"><?php echo htmlspecialchars($data['nik'] ?? '-'); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-field">
                    <span class="info-label">No. KK:</span>
                    <span class="info-value"><?php echo htmlspecialchars($data['no_kk'] ?? '-'); ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">No. HP:</span>
                    <span class="info-value"><?php echo htmlspecialchars($data['no_hp'] ?? '-'); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-field">
                    <span class="info-label">Alamat:</span>
                    <span class="info-value"><?php echo htmlspecialchars($data['alamat'] ?? '-'); ?></span>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">👶 DATA BAYI</div>
            <div class="info-row">
                <div class="info-field">
                    <span class="info-label">Nama Bayi:</span>
                    <span class="info-value"><?php echo htmlspecialchars($data['nama_bayi']); ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Usia:</span>
                    <span class="info-value"><?php echo (int)$data['usia_bulan']; ?> bulan</span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-field">
                    <span class="info-label">Jenis Kelamin:</span>
                    <span class="info-value"><?php echo htmlspecialchars($data['jenis_kelamin'] ?? 'Laki-laki'); ?></span>
                </div>
                <div class="info-field">
                    <span class="info-label">Tanggal Periksa:</span>
                    <span class="info-value"><?php echo date('d F Y H:i', strtotime($data['tanggal_pemeriksaan'])); ?></span>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">🩺 HASIL PEMERIKSAAN</div>

            <div class="measurement-grid">
                <div class="measurement-item">
                    <div class="measurement-label">Berat Badan</div>
                    <div class="measurement-value"><?php echo number_format((float)$data['berat_badan'], 2); ?> kg</div>
                    <div class="measurement-category">Kategori: <?php echo $kategori_bb; ?></div>
                </div>
                <div class="measurement-item">
                    <div class="measurement-label">Tinggi Badan</div>
                    <div class="measurement-value"><?php echo $data['tinggi_badan'] ? number_format((float)$data['tinggi_badan'], 1) . ' cm' : '-'; ?></div>
                </div>
            </div>

            <div class="measurement-grid">
                <div class="measurement-item">
                    <div class="measurement-label">Lingkar Kepala</div>
                    <div class="measurement-value"><?php echo $data['lingkar_kepala'] ? number_format((float)$data['lingkar_kepala'], 1) . ' cm' : '-'; ?></div>
                </div>
                <div class="measurement-item">
                    <div class="measurement-label">Periksa Oleh</div>
                    <div class="measurement-value" style="font-size: 14px;"><?php echo htmlspecialchars($data['pemeriksaan_oleh']); ?></div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">❤️ STATUS KESEHATAN</div>
            <div class="status-box <?php echo $statusClass; ?>">
                <div class="status-label">Hasil Pemeriksaan</div>
                <div class="status-value"><?php echo htmlspecialchars($data['status_kesehatan'] ?? 'Sehat'); ?></div>
            </div>
        </div>

        <?php if (!empty($data['catatan'])): ?>
        <div class="section">
            <div class="section-title">📝 CATATAN</div>
            <div class="catatan-box">
                <p><?php echo htmlspecialchars($data['catatan']); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="signature-section">
            <div class="signature-box">
                <div>
                    <div class="signature-line">Orangtua Bayi</div>
                    <div class="signature-name"><?php echo htmlspecialchars($data['nama_warga'] ?? '_______________'); ?></div>
                </div>
                <div>
                    <div class="signature-line">Petugas Kesehatan</div>
                    <div class="signature-name"><?php echo htmlspecialchars($data['pemeriksaan_oleh'] ?? '_______________'); ?></div>
                </div>
                <div>
                    <div class="signature-line">Ketua Posyandu</div>
                    <div class="signature-name">_______________</div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>Dokumen ini dicetak otomatis dari Sistem Informasi RT RW Digital Bersatu</p>
            <p><?php echo date('d F Y \p\u\k\l H:i'); ?></p>
        </div>
    </div>
</body>
</html>
