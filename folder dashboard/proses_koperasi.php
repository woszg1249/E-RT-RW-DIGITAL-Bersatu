<?php
include "../Koneksi.php";

require_login();

header('Content-Type: application/json; charset=utf-8');

$nama_user = $_SESSION['nama'];
$stmt_user = mysqli_prepare($koneksi, "SELECT id, role FROM users WHERE nama = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_user, "s", $nama_user);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$data_user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

$id_user = (int)($data_user['id'] ?? 0);
$role = strtoupper($data_user['role'] ?? 'WARGA');
$action = isset($_GET['action']) ? $_GET['action'] : '';

$mutating_actions = ['add_member', 'join_member', 'add_simpanan', 'request_loan', 'request_loan_iot', 'approve_loan', 'reject_loan', 'add_cicilan'];
if (in_array($action, $mutating_actions) && !csrf_check()) {
    sendResponse(false, 'Token keamanan tidak valid');
}

// Ensure relevant tables exist
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS koperasi_anggota (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    tanggal_gabung DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(30) DEFAULT 'aktif',
    FOREIGN KEY (id_user) REFERENCES users(id)
)");

mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS koperasi_simpanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    jenis ENUM('setoran','penarikan') NOT NULL,
    jumlah DECIMAL(14,2) NOT NULL,
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
    catatan TEXT,
    FOREIGN KEY (id_user) REFERENCES users(id)
)");

mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS koperasi_pinjaman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    jumlah DECIMAL(14,2) NOT NULL,
    tenor INT NOT NULL,
    bunga DECIMAL(5,2) NOT NULL,
    status ENUM('pending','disetujui','ditolak','lunas') DEFAULT 'pending',
    tanggal_pengajuan DATETIME DEFAULT CURRENT_TIMESTAMP,
    keterangan TEXT,
    FOREIGN KEY (id_user) REFERENCES users(id)
)");

mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS koperasi_cicilan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pinjaman INT NOT NULL,
    jumlah DECIMAL(14,2) NOT NULL,
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
    catatan TEXT,
    FOREIGN KEY (id_pinjaman) REFERENCES koperasi_pinjaman(id)
)");

function sendResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

function getTotalCicilan($koneksi, $id_pinjaman) {
    $id_pinjaman = (int)$id_pinjaman;
    $stmt = mysqli_prepare($koneksi, "SELECT SUM(jumlah) as total FROM koperasi_cicilan WHERE id_pinjaman=?");
    mysqli_stmt_bind_param($stmt, "i", $id_pinjaman);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $d = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return floatval($d['total'] ?? 0);
}

if ($action === 'add_member') {
    require_admin();

    $anggota_id = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
    if ($anggota_id <= 0) {
        sendResponse(false, 'Pilih anggota yang valid');
    }

    $stmt_check = mysqli_prepare($koneksi, "SELECT id FROM koperasi_anggota WHERE id_user=? LIMIT 1");
    mysqli_stmt_bind_param($stmt_check, "i", $anggota_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    
    if (mysqli_num_rows($result_check) > 0) {
        mysqli_stmt_close($stmt_check);
        sendResponse(false, 'Anggota sudah terdaftar');
    }
    mysqli_stmt_close($stmt_check);

    $status = 'aktif';
    $stmt = mysqli_prepare($koneksi, "INSERT INTO koperasi_anggota (id_user, status) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "is", $anggota_id, $status);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        sendResponse(true, 'Anggota koperasi berhasil ditambahkan');
    }
    mysqli_stmt_close($stmt);
    sendResponse(false, 'Gagal menambahkan anggota');
}

if ($action === 'join_member') {
    $stmt_check = mysqli_prepare($koneksi, "SELECT id FROM koperasi_anggota WHERE id_user=? LIMIT 1");
    mysqli_stmt_bind_param($stmt_check, "i", $id_user);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    
    if (mysqli_num_rows($result_check) > 0) {
        mysqli_stmt_close($stmt_check);
        sendResponse(false, 'Anda sudah menjadi anggota koperasi');
    }
    mysqli_stmt_close($stmt_check);

    $status = 'aktif';
    $stmt = mysqli_prepare($koneksi, "INSERT INTO koperasi_anggota (id_user, status) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "is", $id_user, $status);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        sendResponse(true, 'Berhasil bergabung sebagai anggota koperasi');
    }
    mysqli_stmt_close($stmt);
    sendResponse(false, 'Gagal bergabung');
}

if ($action === 'add_simpanan') {
    require_admin();

    $anggota_id = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
    $jenis = isset($_POST['jenis']) ? trim($_POST['jenis']) : '';
    $jumlah = isset($_POST['jumlah']) ? floatval($_POST['jumlah']) : 0;
    $catatan = isset($_POST['catatan']) ? trim($_POST['catatan']) : '';

    if ($anggota_id <= 0 || !in_array($jenis, ['setoran', 'penarikan']) || $jumlah <= 0) {
        sendResponse(false, 'Data simpanan tidak valid');
    }

    $stmt = mysqli_prepare($koneksi, "INSERT INTO koperasi_simpanan (id_user, jenis, jumlah, catatan) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isds", $anggota_id, $jenis, $jumlah, $catatan);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        sendResponse(true, 'Catatan simpanan berhasil disimpan');
    }
    mysqli_stmt_close($stmt);
    sendResponse(false, 'Gagal menyimpan simpanan');
}

if ($action === 'request_loan_iot') {
    // Ambil target warga (bisa dari scan KTP oleh Admin maupun mandiri oleh Warga)
    $target_user_id = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
    if ($role !== 'ADMIN' || $target_user_id <= 0) {
        $target_user_id = $id_user; // jika warga biasa, wajib id dirinya sendiri
    }

    $jumlah = isset($_POST['jumlah']) ? floatval($_POST['jumlah']) : 0;
    $tenor = isset($_POST['tenor']) ? (int)$_POST['tenor'] : 0;
    $bunga = isset($_POST['bunga']) ? floatval($_POST['bunga']) : 1.5;
    $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : 'Pengajuan Pinjaman via IoT e-KTP Scanner';
    $nik_peminjam = isset($_POST['nik']) ? trim($_POST['nik']) : '';
    $scan_device = isset($_POST['scan_device_id']) ? trim($_POST['scan_device_id']) : 'IOT-READER-01';
    $metode = 'iot_scan_ktp';

    if ($target_user_id <= 0 || $jumlah <= 0 || $tenor <= 0) {
        sendResponse(false, 'Data pengajuan pinjaman IoT tidak lengkap atau nominal tidak valid.');
    }

    // Ambil NIK warga jika kosong
    if (empty($nik_peminjam)) {
        $q_u = mysqli_query($koneksi, "SELECT nik FROM users WHERE id = " . $target_user_id . " LIMIT 1");
        if ($r_u = mysqli_fetch_assoc($q_u)) {
            $nik_peminjam = $r_u['nik'];
        }
    }

    // Otomatis daftarkan ke anggota koperasi jika belum terdaftar
    $check_anggota = mysqli_query($koneksi, "SELECT id FROM koperasi_anggota WHERE id_user = " . $target_user_id . " LIMIT 1");
    if (mysqli_num_rows($check_anggota) === 0) {
        mysqli_query($koneksi, "INSERT INTO koperasi_anggota (id_user, status) VALUES (" . $target_user_id . ", 'aktif')");
    }

    $status = 'pending';
    $stmt = mysqli_prepare($koneksi, "INSERT INTO koperasi_pinjaman (id_user, jumlah, tenor, bunga, keterangan, status, metode_pengajuan, scan_device_id, nik_peminjam) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ididsssss", $target_user_id, $jumlah, $tenor, $bunga, $keterangan, $status, $metode, $scan_device, $nik_peminjam);
    
    if (mysqli_stmt_execute($stmt)) {
        $pinjaman_id = mysqli_insert_id($koneksi);
        mysqli_stmt_close($stmt);
        sendResponse(true, 'Pengajuan pinjaman berhasil diverifikasi melalui IoT Scan KTP! ID Pengajuan: #' . $pinjaman_id);
    }
    
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    sendResponse(false, 'Gagal memproses pinjaman IoT: ' . $err);
}

if ($action === 'request_loan') {
    $jumlah = isset($_POST['jumlah']) ? floatval($_POST['jumlah']) : 0;
    $tenor = isset($_POST['tenor']) ? (int)$_POST['tenor'] : 0;
    $bunga = isset($_POST['bunga']) ? floatval($_POST['bunga']) : 0;
    $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';

    if ($jumlah <= 0 || $tenor <= 0 || $bunga < 0) {
        sendResponse(false, 'Data pengajuan pinjaman tidak valid');
    }

    $status = 'pending';
    $stmt = mysqli_prepare($koneksi, "INSERT INTO koperasi_pinjaman (id_user, jumlah, tenor, bunga, keterangan, status) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ididss", $id_user, $jumlah, $tenor, $bunga, $keterangan, $status);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        sendResponse(true, 'Pengajuan pinjaman berhasil dikirim');
    }
    mysqli_stmt_close($stmt);
    sendResponse(false, 'Gagal mengajukan pinjaman');
}

if ($action === 'approve_loan' || $action === 'reject_loan') {
    require_admin();

    $pinjaman_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($pinjaman_id <= 0) {
        sendResponse(false, 'ID pinjaman tidak valid');
    }

    $new_status = $action === 'approve_loan' ? 'disetujui' : 'ditolak';
    $stmt = mysqli_prepare($koneksi, "UPDATE koperasi_pinjaman SET status=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "si", $new_status, $pinjaman_id);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        sendResponse(true, 'Pinjaman berhasil ' . ($new_status === 'disetujui' ? 'disetujui' : 'ditolak'));
    }
    mysqli_stmt_close($stmt);
    sendResponse(false, 'Gagal memperbarui status pinjaman');
}

if ($action === 'add_cicilan') {
    require_admin();

    $pinjaman_id = isset($_POST['id_pinjaman']) ? (int)$_POST['id_pinjaman'] : 0;
    $jumlah = isset($_POST['jumlah']) ? floatval($_POST['jumlah']) : 0;
    $catatan = isset($_POST['catatan']) ? trim($_POST['catatan']) : '';

    if ($pinjaman_id <= 0 || $jumlah <= 0) {
        sendResponse(false, 'Data cicilan tidak valid');
    }

    $stmt = mysqli_prepare($koneksi, "INSERT INTO koperasi_cicilan (id_pinjaman, jumlah, catatan) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ids", $pinjaman_id, $jumlah, $catatan);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        sendResponse(false, 'Gagal menyimpan cicilan');
    }
    mysqli_stmt_close($stmt);

    $total_dibayar = getTotalCicilan($koneksi, $pinjaman_id);
    
    $stmt_pinjaman = mysqli_prepare($koneksi, "SELECT jumlah FROM koperasi_pinjaman WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt_pinjaman, "i", $pinjaman_id);
    mysqli_stmt_execute($stmt_pinjaman);
    $result_pinjaman = mysqli_stmt_get_result($stmt_pinjaman);
    $pinjaman_row = mysqli_fetch_assoc($result_pinjaman);
    mysqli_stmt_close($stmt_pinjaman);
    
    if ($pinjaman_row && $total_dibayar >= floatval($pinjaman_row['jumlah'])) {
        $status_lunas = 'lunas';
        $stmt_update = mysqli_prepare($koneksi, "UPDATE koperasi_pinjaman SET status=? WHERE id=?");
        mysqli_stmt_bind_param($stmt_update, "si", $status_lunas, $pinjaman_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
    }

    sendResponse(true, 'Cicilan berhasil dicatat');
}

sendResponse(false, 'Action tidak dikenali');
