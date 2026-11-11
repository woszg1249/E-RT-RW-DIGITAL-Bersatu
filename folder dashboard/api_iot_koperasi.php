<?php
include "../Koneksi.php";

header('Content-Type: application/json; charset=utf-8');

// Mendukung pemanggilan dari mikrokontroler IoT maupun AJAX web
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. PUSH SCAN DARI PERANGKAT IoT (ESP32 / Arduino / Raspberry Pi / Python / RFID Reader)
if ($action === 'push_scan') {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);

    $nik = trim($_POST['nik'] ?? $jsonData['nik'] ?? $_GET['nik'] ?? '');
    $rfid_uid = trim($_POST['rfid_uid'] ?? $jsonData['rfid_uid'] ?? $_GET['rfid_uid'] ?? '');
    $device_id = trim($_POST['device_id'] ?? $jsonData['device_id'] ?? $_GET['device_id'] ?? 'IOT-KOP-READER');

    if (empty($nik) && empty($rfid_uid)) {
        echo json_encode([
            'success' => false,
            'message' => 'Harap kirimkan parameter NIK atau RFID UID dari kartu e-KTP.'
        ]);
        exit();
    }

    // Cari warga berdasarkan NIK atau rfid_uid
    $user = null;
    if (!empty($nik)) {
        $stmt = mysqli_prepare($koneksi, "SELECT id, nama, nik, no_kk, alamat, rt, rw, no_hp, foto, role FROM users WHERE nik = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $nik);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }

    if (!$user && !empty($rfid_uid)) {
        $stmt = mysqli_prepare($koneksi, "SELECT id, nama, nik, no_kk, alamat, rt, rw, no_hp, foto, role FROM users WHERE rfid_uid = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $rfid_uid);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }

    if (!$user) {
        echo json_encode([
            'success' => false,
            'registered' => false,
            'message' => 'Kartu KTP terbaca (NIK: ' . ($nik ?: '-') . ' / UID: ' . ($rfid_uid ?: '-') . '), namun data warga belum terdaftar dalam sistem RT RW.'
        ]);
        exit();
    }

    $id_user = (int)$user['id'];
    $final_nik = $user['nik'];

    // Simpan ke log scan IoT antrean pending
    $stmt_scan = mysqli_prepare($koneksi, "INSERT INTO iot_koperasi_scans (device_id, nik, rfid_uid, id_user, status) VALUES (?, ?, ?, ?, 'pending')");
    mysqli_stmt_bind_param($stmt_scan, "sssi", $device_id, $final_nik, $rfid_uid, $id_user);
    $saved = mysqli_stmt_execute($stmt_scan);
    $scan_id = mysqli_insert_id($koneksi);
    mysqli_stmt_close($stmt_scan);

    echo json_encode([
        'success' => true,
        'registered' => true,
        'scan_id' => $scan_id,
        'message' => 'e-KTP berhasil diverifikasi! Nama: ' . $user['nama'],
        'data' => [
            'id' => $user['id'],
            'nama' => $user['nama'],
            'nik' => $user['nik'],
            'rt' => $user['rt'],
            'rw' => $user['rw'],
            'alamat' => $user['alamat'],
            'no_hp' => $user['no_hp']
        ]
    ]);
    exit();
}

// 2. CHECK LATEST SCAN (Polling Real-Time oleh Halaman Web koperasi.php)
if ($action === 'check_latest_scan') {
    // Ambil scan pending dalam 60 detik terakhir
    $query = "SELECT s.id as scan_id, s.device_id, s.scanned_at, u.id, u.nama, u.nik, u.no_kk, u.alamat, u.rt, u.rw, u.no_hp, u.foto, u.pekerjaan 
              FROM iot_koperasi_scans s
              JOIN users u ON s.id_user = u.id
              WHERE s.status = 'pending' AND s.scanned_at >= (NOW() - INTERVAL 60 SECOND)
              ORDER BY s.id DESC LIMIT 1";

    $res = mysqli_query($koneksi, $query);

    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $scan_id = (int)$row['scan_id'];
        $id_user = (int)$row['id'];

        // Tandai sudah diproses agar tidak memicu modal berulang kali
        mysqli_query($koneksi, "UPDATE iot_koperasi_scans SET status = 'processed' WHERE id = " . $scan_id);

        // Ambil data koperasi user
        $q_anggota = mysqli_query($koneksi, "SELECT * FROM koperasi_anggota WHERE id_user = " . $id_user . " LIMIT 1");
        $is_anggota = mysqli_num_rows($q_anggota) > 0;

        $q_simpanan = mysqli_query($koneksi, "SELECT SUM(CASE WHEN jenis='setoran' THEN jumlah WHEN jenis='penarikan' THEN -jumlah ELSE 0 END) as total FROM koperasi_simpanan WHERE id_user=" . $id_user);
        $d_simpanan = mysqli_fetch_assoc($q_simpanan);
        $total_simpanan = floatval($d_simpanan['total'] ?? 0);

        // Hitung pinjaman aktif
        $q_pinjaman = mysqli_query($koneksi, "SELECT COUNT(*) as aktif_count, SUM(jumlah) as total_pinjam FROM koperasi_pinjaman WHERE id_user=" . $id_user . " AND status IN ('pending', 'disetujui')");
        $d_pinjaman = mysqli_fetch_assoc($q_pinjaman);

        $foto_user = !empty($row['foto']) && file_exists('../img/' . $row['foto']) ? ('../img/' . $row['foto']) : ('../img/profile.jpg');

        echo json_encode([
            'found' => true,
            'scan_id' => $scan_id,
            'device_id' => $row['device_id'],
            'scanned_at' => date('d/m/Y H:i:s', strtotime($row['scanned_at'])),
            'user' => [
                'id' => $row['id'],
                'nama' => $row['nama'],
                'nik' => $row['nik'],
                'no_kk' => $row['no_kk'],
                'alamat' => $row['alamat'],
                'rt' => $row['rt'],
                'rw' => $row['rw'],
                'no_hp' => $row['no_hp'],
                'pekerjaan' => $row['pekerjaan'] ?? 'Warga RT',
                'foto' => $foto_user,
                'is_anggota' => $is_anggota,
                'total_simpanan' => $total_simpanan,
                'pinjaman_aktif_count' => (int)($d_pinjaman['aktif_count'] ?? 0),
                'total_pinjam_aktif' => floatval($d_pinjaman['total_pinjam'] ?? 0)
            ]
        ]);
    } else {
        echo json_encode(['found' => false]);
    }
    exit();
}

// 3. LOOKUP KTP LANGSUNG (Untuk Input Scanner Barcode/RFID Keyboard Emulation atau Pencarian Cepat)
if ($action === 'lookup_ktp') {
    $keyword = trim($_GET['keyword'] ?? $_POST['keyword'] ?? '');
    if (empty($keyword)) {
        echo json_encode(['success' => false, 'message' => 'Masukkan NIK atau UID KTP']);
        exit();
    }

    $stmt = mysqli_prepare($koneksi, "SELECT id, nama, nik, no_kk, alamat, rt, rw, no_hp, foto, pekerjaan, role FROM users WHERE nik = ? OR rfid_uid = ? OR username = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "sss", $keyword, $keyword, $keyword);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Data KTP tidak ditemukan untuk: ' . htmlspecialchars($keyword)]);
        exit();
    }

    $id_user = (int)$user['id'];

    $q_anggota = mysqli_query($koneksi, "SELECT * FROM koperasi_anggota WHERE id_user = " . $id_user . " LIMIT 1");
    $is_anggota = mysqli_num_rows($q_anggota) > 0;

    $q_simpanan = mysqli_query($koneksi, "SELECT SUM(CASE WHEN jenis='setoran' THEN jumlah WHEN jenis='penarikan' THEN -jumlah ELSE 0 END) as total FROM koperasi_simpanan WHERE id_user=" . $id_user);
    $d_simpanan = mysqli_fetch_assoc($q_simpanan);
    $total_simpanan = floatval($d_simpanan['total'] ?? 0);

    $q_pinjaman = mysqli_query($koneksi, "SELECT COUNT(*) as aktif_count, SUM(jumlah) as total_pinjam FROM koperasi_pinjaman WHERE id_user=" . $id_user . " AND status IN ('pending', 'disetujui')");
    $d_pinjaman = mysqli_fetch_assoc($q_pinjaman);

    $foto_user = !empty($user['foto']) && file_exists('../img/' . $user['foto']) ? ('../img/' . $user['foto']) : ('../img/profile.jpg');

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'nama' => $user['nama'],
            'nik' => $user['nik'],
            'no_kk' => $user['no_kk'],
            'alamat' => $user['alamat'],
            'rt' => $user['rt'],
            'rw' => $user['rw'],
            'no_hp' => $user['no_hp'],
            'pekerjaan' => $user['pekerjaan'] ?? 'Warga RT',
            'foto' => $foto_user,
            'is_anggota' => $is_anggota,
            'total_simpanan' => $total_simpanan,
            'pinjaman_aktif_count' => (int)($d_pinjaman['aktif_count'] ?? 0),
            'total_pinjam_aktif' => floatval($d_pinjaman['total_pinjam'] ?? 0)
        ]
    ]);
    exit();
}

// 4. DAFTAR WARGA UNTUK QUICK SIMULATION
if ($action === 'get_simulation_list') {
    $res = mysqli_query($koneksi, "SELECT id, nama, nik, rt, rw, role FROM users WHERE role = 'Warga' ORDER BY id ASC LIMIT 15");
    $list = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $list[] = [
            'id' => $r['id'],
            'nama' => $r['nama'],
            'nik' => $r['nik'],
            'wilayah' => 'RT ' . $r['rt'] . ' / RW ' . $r['rw']
        ];
    }
    echo json_encode(['success' => true, 'list' => $list]);
    exit();
}

echo json_encode(['error' => 'Aksi IoT tidak valid']);
exit();
