<?php
include "../Koneksi.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['nama'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$nama_user = $_SESSION['nama'];
$query_user = mysqli_query($koneksi, "SELECT * FROM users WHERE nama = '" . mysqli_real_escape_string($koneksi, $nama_user) . "'");
$data_user = mysqli_fetch_assoc($query_user);
$id_user = $data_user['id'] ?? 0;
$role = strtoupper($data_user['role'] ?? 'WARGA');
$action = isset($_GET['action']) ? $_GET['action'] : '';

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
    $q = mysqli_query($koneksi, "SELECT SUM(jumlah) as total FROM koperasi_cicilan WHERE id_pinjaman='" . intval($id_pinjaman) . "'");
    $d = mysqli_fetch_assoc($q);
    return floatval($d['total'] ?? 0);
}

if ($action === 'add_member') {
    if ($role !== 'ADMIN') {
        sendResponse(false, 'Hanya admin yang dapat menambahkan anggota');
    }

    $anggota_id = isset($_POST['id_user']) ? intval($_POST['id_user']) : 0;
    if ($anggota_id <= 0) {
        sendResponse(false, 'Pilih anggota yang valid');
    }

    $check = mysqli_query($koneksi, "SELECT id FROM koperasi_anggota WHERE id_user='" . $anggota_id . "' LIMIT 1");
    if (mysqli_num_rows($check) > 0) {
        sendResponse(false, 'Anggota sudah terdaftar');
    }

    $insert = "INSERT INTO koperasi_anggota (id_user, status) VALUES ('" . $anggota_id . "', 'aktif')";
    if (mysqli_query($koneksi, $insert)) {
        sendResponse(true, 'Anggota koperasi berhasil ditambahkan');
    }
    sendResponse(false, 'Gagal menambahkan anggota: ' . mysqli_error($koneksi));
}

if ($action === 'join_member') {
    $check = mysqli_query($koneksi, "SELECT id FROM koperasi_anggota WHERE id_user='" . intval($id_user) . "' LIMIT 1");
    if (mysqli_num_rows($check) > 0) {
        sendResponse(false, 'Anda sudah menjadi anggota koperasi');
    }

    $insert = "INSERT INTO koperasi_anggota (id_user, status) VALUES ('" . intval($id_user) . "', 'aktif')";
    if (mysqli_query($koneksi, $insert)) {
        sendResponse(true, 'Berhasil bergabung sebagai anggota koperasi');
    }
    sendResponse(false, 'Gagal bergabung: ' . mysqli_error($koneksi));
}

if ($action === 'add_simpanan') {
    if ($role !== 'ADMIN') {
        sendResponse(false, 'Hanya admin yang dapat mencatat simpanan');
    }

    $anggota_id = isset($_POST['id_user']) ? intval($_POST['id_user']) : 0;
    $jenis = isset($_POST['jenis']) ? mysqli_real_escape_string($koneksi, $_POST['jenis']) : '';
    $jumlah = isset($_POST['jumlah']) ? floatval($_POST['jumlah']) : 0;
    $catatan = isset($_POST['catatan']) ? mysqli_real_escape_string($koneksi, $_POST['catatan']) : '';

    if ($anggota_id <= 0 || !in_array($jenis, ['setoran', 'penarikan']) || $jumlah <= 0) {
        sendResponse(false, 'Data simpanan tidak valid');
    }

    $insert = "INSERT INTO koperasi_simpanan (id_user, jenis, jumlah, catatan) VALUES ('" . $anggota_id . "', '" . $jenis . "', '" . $jumlah . "', '" . $catatan . "')";
    if (mysqli_query($koneksi, $insert)) {
        sendResponse(true, 'Catatan simpanan berhasil disimpan');
    }
    sendResponse(false, 'Gagal menyimpan simpanan: ' . mysqli_error($koneksi));
}

if ($action === 'request_loan') {
    if ($role !== 'WARGA') {
        sendResponse(false, 'Hanya warga dapat mengajukan pinjaman');
    }

    $jumlah = isset($_POST['jumlah']) ? floatval($_POST['jumlah']) : 0;
    $tenor = isset($_POST['tenor']) ? intval($_POST['tenor']) : 0;
    $bunga = isset($_POST['bunga']) ? floatval($_POST['bunga']) : 0;
    $keterangan = isset($_POST['keterangan']) ? mysqli_real_escape_string($koneksi, $_POST['keterangan']) : '';

    if ($jumlah <= 0 || $tenor <= 0 || $bunga < 0) {
        sendResponse(false, 'Data pengajuan pinjaman tidak valid');
    }

    $insert = "INSERT INTO koperasi_pinjaman (id_user, jumlah, tenor, bunga, keterangan, status) VALUES ('" . intval($id_user) . "', '" . $jumlah . "', '" . $tenor . "', '" . $bunga . "', '" . $keterangan . "', 'pending')";
    if (mysqli_query($koneksi, $insert)) {
        sendResponse(true, 'Pengajuan pinjaman berhasil dikirim');
    }
    sendResponse(false, 'Gagal mengajukan pinjaman: ' . mysqli_error($koneksi));
}

if ($action === 'approve_loan' || $action === 'reject_loan') {
    if ($role !== 'ADMIN') {
        sendResponse(false, 'Hanya admin yang dapat memproses pinjaman');
    }

    $pinjaman_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($pinjaman_id <= 0) {
        sendResponse(false, 'ID pinjaman tidak valid');
    }

    $new_status = $action === 'approve_loan' ? 'disetujui' : 'ditolak';
    $update = "UPDATE koperasi_pinjaman SET status='" . $new_status . "' WHERE id='" . $pinjaman_id . "'";
    if (mysqli_query($koneksi, $update)) {
        sendResponse(true, 'Pinjaman berhasil ' . ($new_status === 'disetujui' ? 'disetujui' : 'ditolak'));
    }
    sendResponse(false, 'Gagal memperbarui status pinjaman: ' . mysqli_error($koneksi));
}

if ($action === 'add_cicilan') {
    if ($role !== 'ADMIN') {
        sendResponse(false, 'Hanya admin yang dapat mencatat cicilan');
    }

    $pinjaman_id = isset($_POST['id_pinjaman']) ? intval($_POST['id_pinjaman']) : 0;
    $jumlah = isset($_POST['jumlah']) ? floatval($_POST['jumlah']) : 0;
    $catatan = isset($_POST['catatan']) ? mysqli_real_escape_string($koneksi, $_POST['catatan']) : '';

    if ($pinjaman_id <= 0 || $jumlah <= 0) {
        sendResponse(false, 'Data cicilan tidak valid');
    }

    $insert = "INSERT INTO koperasi_cicilan (id_pinjaman, jumlah, catatan) VALUES ('" . $pinjaman_id . "', '" . $jumlah . "', '" . $catatan . "')";
    if (!mysqli_query($koneksi, $insert)) {
        sendResponse(false, 'Gagal menyimpan cicilan: ' . mysqli_error($koneksi));
    }

    $total_dibayar = getTotalCicilan($koneksi, $pinjaman_id);
    $pinjaman_data = mysqli_query($koneksi, "SELECT jumlah FROM koperasi_pinjaman WHERE id='" . $pinjaman_id . "' LIMIT 1");
    $pinjaman_row = mysqli_fetch_assoc($pinjaman_data);
    if ($pinjaman_row && $total_dibayar >= floatval($pinjaman_row['jumlah'])) {
        mysqli_query($koneksi, "UPDATE koperasi_pinjaman SET status='lunas' WHERE id='" . $pinjaman_id . "'");
    }

    sendResponse(true, 'Cicilan berhasil dicatat');
}

sendResponse(false, 'Action tidak dikenali');
