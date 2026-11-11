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

$mutating_actions = ['add', 'edit', 'delete'];
if (in_array($action, $mutating_actions) && !csrf_check()) {
    resp(false, 'Token keamanan tidak valid');
}

$create = "CREATE TABLE IF NOT EXISTS kegiatan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    deskripsi TEXT,
    tanggal_mulai DATE,
    tanggal_selesai DATE,
    waktu VARCHAR(50),
    lokasi VARCHAR(255),
    dibuat_oleh INT,
    tanggal_dibuat DATETIME DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($koneksi, $create);

function resp($ok, $msg) {
    echo json_encode(['success' => $ok, 'message' => $msg]);
    exit();
}

if ($action === 'add') {
    require_admin();

    $judul = isset($_POST['judul']) ? trim($_POST['judul']) : '';
    $deskripsi = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';
    $tanggal_mulai = isset($_POST['tanggal_mulai']) ? $_POST['tanggal_mulai'] : null;
    $tanggal_selesai = isset($_POST['tanggal_selesai']) ? $_POST['tanggal_selesai'] : null;
    $waktu = isset($_POST['waktu']) ? trim($_POST['waktu']) : '';
    $lokasi = isset($_POST['lokasi']) ? trim($_POST['lokasi']) : '';

    if (empty($judul) || empty($tanggal_mulai)) resp(false, 'Data tidak lengkap');

    $stmt = mysqli_prepare($koneksi, "INSERT INTO kegiatan (judul, deskripsi, tanggal_mulai, tanggal_selesai, waktu, lokasi, dibuat_oleh) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssssi", $judul, $deskripsi, $tanggal_mulai, $tanggal_selesai, $waktu, $lokasi, $id_user);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            resp(true, 'Kegiatan berhasil dibuat');
        }
        mysqli_stmt_close($stmt);
    }
    resp(false, 'Gagal menyimpan data');
}

if ($action === 'edit' || $action === 'update') {
    require_admin();
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) resp(false, 'ID tidak valid');

    $judul = isset($_POST['judul']) ? trim($_POST['judul']) : '';
    $deskripsi = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';
    $tanggal_mulai = isset($_POST['tanggal_mulai']) ? $_POST['tanggal_mulai'] : null;
    $tanggal_selesai = isset($_POST['tanggal_selesai']) ? $_POST['tanggal_selesai'] : null;
    $waktu = isset($_POST['waktu']) ? trim($_POST['waktu']) : '';
    $lokasi = isset($_POST['lokasi']) ? trim($_POST['lokasi']) : '';

    $stmt = mysqli_prepare($koneksi, "UPDATE kegiatan SET judul=?, deskripsi=?, tanggal_mulai=?, tanggal_selesai=?, waktu=?, lokasi=? WHERE id=?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssssi", $judul, $deskripsi, $tanggal_mulai, $tanggal_selesai, $waktu, $lokasi, $id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            resp(true, 'Kegiatan berhasil diperbarui');
        }
        mysqli_stmt_close($stmt);
    }
    resp(false, 'Gagal memperbarui data');
}

if ($action === 'delete') {
    require_admin();
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) resp(false, 'ID tidak valid');

    $stmt = mysqli_prepare($koneksi, "DELETE FROM kegiatan WHERE id=?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            resp(true, 'Kegiatan berhasil dihapus');
        }
        mysqli_stmt_close($stmt);
    }
    resp(false, 'Gagal menghapus data');
}

resp(false, 'Action tidak dikenali');
?>
