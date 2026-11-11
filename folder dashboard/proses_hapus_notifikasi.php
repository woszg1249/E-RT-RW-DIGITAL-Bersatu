<?php
include "../Koneksi.php";

require_login();

header('Content-Type: application/json');

$user_id = $_SESSION['id'];

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'delete';

// Validasi CSRF dari header atau body JSON
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    echo json_encode(["success" => false, "message" => "Token keamanan tidak valid."]);
    exit;
}

// ensure table exists
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS notif_dismissed (id INT AUTO_INCREMENT PRIMARY KEY, id_tagihan INT NOT NULL, id_user INT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

if ($action === 'delete') {
    if (!isset($input['id_tagihan']) || !is_numeric($input['id_tagihan'])) {
        echo json_encode(["success" => false, "message" => "ID tagihan tidak valid."]);
        exit;
    }
    $id_tagihan = (int)$input['id_tagihan'];

    // Cek apakah sudah pernah dismiss
    $stmt = mysqli_prepare($koneksi, "SELECT 1 FROM notif_dismissed WHERE id_tagihan = ? AND id_user = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $id_tagihan, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        echo json_encode(["success" => true, "message" => "Sudah dihapus."]);
        exit;
    }
    mysqli_stmt_close($stmt);

    // Insert dismiss record
    $stmt = mysqli_prepare($koneksi, "INSERT INTO notif_dismissed (id_tagihan, id_user) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ii", $id_tagihan, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "Gagal menyimpan data. Silakan coba lagi."]);
    }
    mysqli_stmt_close($stmt);
    exit;
}

echo json_encode(["success" => false, "message" => "Action tidak dikenali."]);

?>
