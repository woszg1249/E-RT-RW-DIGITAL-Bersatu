<?php

include "../Koneksi.php";

require_login();

$nama_user = $_SESSION['nama'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

$stmt_user = mysqli_prepare($koneksi, "SELECT id, role FROM users WHERE nama = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_user, "s", $nama_user);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$data_user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

$id_user = (int)($data_user['id'] ?? 0);
$role = strtoupper($data_user['role'] ?? 'WARGA');

$mutating_actions = ['add', 'approve', 'reject', 'delete'];
if (in_array($action, $mutating_actions) && !csrf_check()) {
    echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

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
    $id_user_input = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
    $jenis_sampah = isset($_POST['jenis_sampah']) ? trim($_POST['jenis_sampah']) : '';
    $berat_kg = isset($_POST['berat_kg']) ? floatval($_POST['berat_kg']) : 0;
    $harga_per_kg = isset($_POST['harga_per_kg']) ? floatval($_POST['harga_per_kg']) : 0;
    $total_harga = isset($_POST['total_harga']) ? floatval($_POST['total_harga']) : ($berat_kg * $harga_per_kg);
    $catatan = isset($_POST['catatan']) ? trim($_POST['catatan']) : '';

    if (empty($jenis_sampah) || $berat_kg <= 0 || $harga_per_kg <= 0) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit();
    }

    $insert_user_id = ($id_user_input > 0 && $role === 'ADMIN') ? $id_user_input : $id_user;
    $status = 'pending';
    
    $stmt = mysqli_prepare($koneksi, "INSERT INTO bank_sampah (id_user, nama_warga, jenis_sampah, berat_kg, harga_per_kg, total_harga, catatan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "issdddss", $insert_user_id, $nama_user, $jenis_sampah, $berat_kg, $harga_per_kg, $total_harga, $catatan, $status);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan']);
    } else {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data. Silakan coba lagi.']);
    }
    exit();
}

if ($action === 'approve') {
    require_admin();

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }

    $status = 'approved';
    $stmt = mysqli_prepare($koneksi, "UPDATE bank_sampah SET status=?, tanggal_diproses=NOW() WHERE id=?");
    mysqli_stmt_bind_param($stmt, "si", $status, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => true, 'message' => 'Data berhasil disetujui']);
    } else {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => false, 'message' => 'Gagal menyetujui data']);
    }
    exit();
}

if ($action === 'reject') {
    require_admin();

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }

    $status = 'rejected';
    $stmt = mysqli_prepare($koneksi, "UPDATE bank_sampah SET status=?, tanggal_diproses=NOW() WHERE id=?");
    mysqli_stmt_bind_param($stmt, "si", $status, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => true, 'message' => 'Data berhasil ditolak']);
    } else {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => false, 'message' => 'Gagal menolak data']);
    }
    exit();
}

if ($action === 'delete') {
    require_admin();

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }

    $stmt = mysqli_prepare($koneksi, "DELETE FROM bank_sampah WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
    } else {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
?>
