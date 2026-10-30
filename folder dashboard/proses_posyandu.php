<?php

include "../Koneksi.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nama'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

$nama_user = $_SESSION['nama'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

$query_user = mysqli_query($koneksi, "SELECT * FROM users WHERE nama = '$nama_user'");
$data_user = mysqli_fetch_assoc($query_user);
$id_user = $data_user['id'];
$role = strtoupper($data_user['role']);

// Hanya admin yang bisa akses
if ($role !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'Hanya admin yang dapat mengakses']);
    exit();
}

// Create table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS posyandu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_warga INT,
    nama_bayi VARCHAR(100) NOT NULL,
    jenis_kelamin ENUM('Laki-laki','Perempuan') DEFAULT 'Laki-laki',
    usia_bulan INT NOT NULL,
    berat_badan DECIMAL(5, 2) NOT NULL,
    tinggi_badan DECIMAL(5, 2),
    lingkar_kepala DECIMAL(5, 2),
    status_kesehatan VARCHAR(50),
    catatan TEXT,
    tanggal_pemeriksaan DATETIME DEFAULT CURRENT_TIMESTAMP,
    pemeriksaan_oleh VARCHAR(100),
    FOREIGN KEY (id_warga) REFERENCES users(id)
)";
mysqli_query($koneksi, $create_table);

$check_posyandu_col = mysqli_query($koneksi, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posyandu' AND COLUMN_NAME = 'jenis_kelamin' LIMIT 1");
if (mysqli_num_rows($check_posyandu_col) === 0) {
    mysqli_query($koneksi, "ALTER TABLE posyandu ADD COLUMN jenis_kelamin ENUM('Laki-laki','Perempuan') DEFAULT 'Laki-laki'");
}

if ($action === 'add') {
    $id_warga = isset($_POST['id_warga']) ? intval($_POST['id_warga']) : 0;
    $nama_bayi = isset($_POST['nama_bayi']) ? mysqli_real_escape_string($koneksi, $_POST['nama_bayi']) : '';
    $jenis_kelamin = isset($_POST['jenis_kelamin']) ? mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']) : 'Laki-laki';
    $usia_bulan = isset($_POST['usia_bulan']) ? intval($_POST['usia_bulan']) : 0;
    $berat_badan = isset($_POST['berat_badan']) ? floatval($_POST['berat_badan']) : 0;
    $tinggi_badan = isset($_POST['tinggi_badan']) ? floatval($_POST['tinggi_badan']) : 0;
    $lingkar_kepala = isset($_POST['lingkar_kepala']) ? floatval($_POST['lingkar_kepala']) : 0;
    $status_kesehatan = isset($_POST['status_kesehatan']) ? mysqli_real_escape_string($koneksi, $_POST['status_kesehatan']) : '';
    $catatan = isset($_POST['catatan']) ? mysqli_real_escape_string($koneksi, $_POST['catatan']) : '';
    $pemeriksaan_oleh = isset($_POST['pemeriksaan_oleh']) ? mysqli_real_escape_string($koneksi, $_POST['pemeriksaan_oleh']) : '';

    if (empty($nama_bayi) || $usia_bulan < 0 || $berat_badan <= 0) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit();
    }

    $query_insert = "INSERT INTO posyandu (id_warga, nama_bayi, jenis_kelamin, usia_bulan, berat_badan, tinggi_badan, lingkar_kepala, status_kesehatan, catatan, pemeriksaan_oleh) 
                    VALUES ('$id_warga', '$nama_bayi', '$jenis_kelamin', '$usia_bulan', '$berat_badan', '$tinggi_badan', '$lingkar_kepala', '$status_kesehatan', '$catatan', '$pemeriksaan_oleh')";
    
    if (mysqli_query($koneksi, $query_insert)) {
        echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data: ' . mysqli_error($koneksi)]);
    }
    exit();
}

if ($action === 'delete') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }

    $query_delete = "DELETE FROM posyandu WHERE id='$id'";
    
    if (mysqli_query($koneksi, $query_delete)) {
        echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
?>
