<?php
include "../Koneksi.php";
session_start();

header('Content-Type: application/json');

if (!$koneksi) {
    echo json_encode(["success" => false, "message" => "Koneksi database gagal."]);
    exit;
}

$user_id = $_SESSION['id'] ?? null;
if (!$user_id) {
    echo json_encode(["success" => false, "message" => "User belum login."]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'delete';

// ensure table exists
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS notif_dismissed (id INT AUTO_INCREMENT PRIMARY KEY, id_tagihan INT NOT NULL, id_user INT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

if ($action === 'delete') {
    // accept numeric id_tagihan including 0 (used for general announcement)
    if (!isset($input['id_tagihan']) || !is_numeric($input['id_tagihan'])) {
        echo json_encode(["success" => false, "message" => "ID tagihan tidak valid."]);
        exit;
    }
    $id_tagihan = (int)$input['id_tagihan'];

    // prevent duplicate dismiss
    $exists = mysqli_query($koneksi, "SELECT 1 FROM notif_dismissed WHERE id_tagihan = '$id_tagihan' AND id_user = '$user_id' LIMIT 1");
    if ($exists && mysqli_num_rows($exists) > 0) {
        echo json_encode(["success" => true, "message" => "Sudah dihapus."]);
        exit;
    }

    $ins = mysqli_query($koneksi, "INSERT INTO notif_dismissed (id_tagihan, id_user) VALUES ('$id_tagihan', '$user_id')");
    if ($ins) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => mysqli_error($koneksi)]);
    }
    exit;
}

if ($action === 'cleanup') {
    // For testing: remove dismissed records older than 1 minute
    $del = mysqli_query($koneksi, "DELETE FROM notif_dismissed WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    echo json_encode(["success" => true]);
    exit;
}

echo json_encode(["success" => false, "message" => "Action tidak dikenali."]);

?>
