<?php

include "../Koneksi.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nama'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$nama_user = $_SESSION['nama'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

$query_user = mysqli_query($koneksi, "SELECT * FROM users WHERE nama = '$nama_user'");
$data_user = mysqli_fetch_assoc($query_user);
$id_user = $data_user['id'];
$role = strtoupper($data_user['role']);

// Ensure responses are sent as JSON
header('Content-Type: application/json; charset=utf-8');

// sanitize session username for DB usage
$safe_nama_user = mysqli_real_escape_string($koneksi, $nama_user);

// Create table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS bank_sampah (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    nama_warga VARCHAR(100),
    jenis_sampah VARCHAR(100) NOT NULL,
    berat_kg DECIMAL(10, 2) NOT NULL,
    harga_per_kg DECIMAL(10, 2) NOT NULL,
    total_harga DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    tanggal_pengumpulan DATETIME DEFAULT CURRENT_TIMESTAMP,
    tanggal_diproses DATETIME,
    catatan TEXT,
    FOREIGN KEY (id_user) REFERENCES users(id)
)";
mysqli_query($koneksi, $create_table);

if ($action === 'add') {
    $id_user_input = isset($_POST['id_user']) ? intval($_POST['id_user']) : 0;
    $jenis_sampah = isset($_POST['jenis_sampah']) ? mysqli_real_escape_string($koneksi, $_POST['jenis_sampah']) : '';
    $berat_kg = isset($_POST['berat_kg']) ? floatval($_POST['berat_kg']) : 0;
    $harga_per_kg = isset($_POST['harga_per_kg']) ? floatval($_POST['harga_per_kg']) : 0;
    $total_harga = isset($_POST['total_harga']) ? floatval($_POST['total_harga']) : ($berat_kg * $harga_per_kg);
    $catatan = isset($_POST['catatan']) ? mysqli_real_escape_string($koneksi, $_POST['catatan']) : '';

    if (empty($jenis_sampah) || $berat_kg <= 0 || $harga_per_kg <= 0) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit();
    }

    $insert_user_id = $id_user_input > 0 ? $id_user_input : $id_user;
    $query_insert = "INSERT INTO bank_sampah (id_user, nama_warga, jenis_sampah, berat_kg, harga_per_kg, total_harga, catatan, status) 
                    VALUES ('$insert_user_id', '$safe_nama_user', '$jenis_sampah', '$berat_kg', '$harga_per_kg', '$total_harga', '$catatan', 'pending')";
    
    if (mysqli_query($koneksi, $query_insert)) {
        echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data: ' . mysqli_error($koneksi)]);
    }
    exit();
}

if ($action === 'approve') {
    if ($role !== 'ADMIN') {
        echo json_encode(['success' => false, 'message' => 'Hanya admin yang dapat menyetujui']);
        exit();
    }

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }

    $query_update = "UPDATE bank_sampah SET status='approved', tanggal_diproses=NOW() WHERE id='$id'";
    
    if (mysqli_query($koneksi, $query_update)) {
        echo json_encode(['success' => true, 'message' => 'Data berhasil disetujui']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyetujui data']);
    }
    exit();
}

if ($action === 'reject') {
    if ($role !== 'ADMIN') {
        echo json_encode(['success' => false, 'message' => 'Hanya admin yang dapat menolak']);
        exit();
    }

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }

    $query_update = "UPDATE bank_sampah SET status='rejected', tanggal_diproses=NOW() WHERE id='$id'";
    
    if (mysqli_query($koneksi, $query_update)) {
        echo json_encode(['success' => true, 'message' => 'Data berhasil ditolak']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menolak data']);
    }
    exit();
}

if ($action === 'delete') {
    if ($role !== 'ADMIN') {
        echo json_encode(['success' => false, 'message' => 'Hanya admin yang dapat menghapus']);
        exit();
    }

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }

    $query_delete = "DELETE FROM bank_sampah WHERE id='$id'";
    
    if (mysqli_query($koneksi, $query_delete)) {
        echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
?>
