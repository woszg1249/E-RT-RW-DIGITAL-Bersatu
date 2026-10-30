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

// Create table if not exists
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
    if ($role !== 'ADMIN') resp(false, 'Hanya admin yang dapat membuat kegiatan');

    $judul = isset($_POST['judul']) ? mysqli_real_escape_string($koneksi, $_POST['judul']) : '';
    $deskripsi = isset($_POST['deskripsi']) ? mysqli_real_escape_string($koneksi, $_POST['deskripsi']) : '';
    $tanggal_mulai = isset($_POST['tanggal_mulai']) ? $_POST['tanggal_mulai'] : null;
    $tanggal_selesai = isset($_POST['tanggal_selesai']) ? $_POST['tanggal_selesai'] : null;
    $waktu = isset($_POST['waktu']) ? mysqli_real_escape_string($koneksi, $_POST['waktu']) : '';
    $lokasi = isset($_POST['lokasi']) ? mysqli_real_escape_string($koneksi, $_POST['lokasi']) : '';

    if (empty($judul) || empty($tanggal_mulai)) resp(false, 'Data tidak lengkap');

    $ins = "INSERT INTO kegiatan (judul, deskripsi, tanggal_mulai, tanggal_selesai, waktu, lokasi, dibuat_oleh) VALUES ('" . $judul . "', '" . $deskripsi . "', '" . $tanggal_mulai . "', " . ($tanggal_selesai ? "'" . $tanggal_selesai . "'" : "NULL") . ", '" . $waktu . "', '" . $lokasi . "', '" . intval($id_user) . "')";
    if (mysqli_query($koneksi, $ins)) resp(true, 'Kegiatan berhasil dibuat');
    resp(false, 'Gagal menyimpan: ' . mysqli_error($koneksi));
}

if ($action === 'update') {
    if ($role !== 'ADMIN') resp(false, 'Hanya admin yang dapat mengubah kegiatan');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) resp(false, 'ID tidak valid');

    $judul = isset($_POST['judul']) ? mysqli_real_escape_string($koneksi, $_POST['judul']) : '';
    $deskripsi = isset($_POST['deskripsi']) ? mysqli_real_escape_string($koneksi, $_POST['deskripsi']) : '';
    $tanggal_mulai = isset($_POST['tanggal_mulai']) ? $_POST['tanggal_mulai'] : null;
    $tanggal_selesai = isset($_POST['tanggal_selesai']) ? $_POST['tanggal_selesai'] : null;
    $waktu = isset($_POST['waktu']) ? mysqli_real_escape_string($koneksi, $_POST['waktu']) : '';
    $lokasi = isset($_POST['lokasi']) ? mysqli_real_escape_string($koneksi, $_POST['lokasi']) : '';

    $upd = "UPDATE kegiatan SET judul='" . $judul . "', deskripsi='" . $deskripsi . "', tanggal_mulai=" . ($tanggal_mulai ? "'" . $tanggal_mulai . "'" : "NULL") . ", tanggal_selesai=" . ($tanggal_selesai ? "'" . $tanggal_selesai . "'" : "NULL") . ", waktu='" . $waktu . "', lokasi='" . $lokasi . "' WHERE id='" . $id . "'";
    if (mysqli_query($koneksi, $upd)) resp(true, 'Kegiatan berhasil diperbarui');
    resp(false, 'Gagal memperbarui: ' . mysqli_error($koneksi));
}

if ($action === 'delete') {
    if ($role !== 'ADMIN') resp(false, 'Hanya admin yang dapat menghapus kegiatan');
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) resp(false, 'ID tidak valid');

    $del = "DELETE FROM kegiatan WHERE id='" . $id . "'";
    if (mysqli_query($koneksi, $del)) resp(true, 'Kegiatan berhasil dihapus');
    resp(false, 'Gagal menghapus: ' . mysqli_error($koneksi));
}

resp(false, 'Action tidak dikenali');
?>
