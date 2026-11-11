<?php
// ============================================================================
// KONFIGURASI DATABASE
// Ubah nilai di bawah ini sesuai dengan pengaturan hosting Anda
// ============================================================================
$host = "localhost";        // Host database (biasanya 'localhost')
$user = "root";             // Username database
$pass = "";                 // Password database
$db   = "keuangan_rt";      // Nama database

// Nonaktifkan unhandled exception otomatis agar bisa ditangani dengan aman
mysqli_report(MYSQLI_REPORT_OFF);

// Coba koneksi ke localhost
$koneksi = @mysqli_connect($host, $user, $pass, $db);

// Fallback ke 127.0.0.1 jika socket localhost belum siap
if (!$koneksi) {
    $koneksi = @mysqli_connect("127.0.0.1", $user, $pass, $db, 3306);
}

// Jika koneksi masih gagal (misal Laragon MySQL belum di-start)
if (!$koneksi) {
    if (php_sapi_name() === 'cli') {
        die("Tidak dapat terhubung ke database MySQL. Pastikan service MySQL Laragon berjalan.\n");
    }

    echo '<!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Belum Aktif - RT RW Digital Bersatu</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: "Plus Jakarta Sans", sans-serif; background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; color: #1e293b; }
            .db-card { background: #ffffff; border-radius: 20px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08); max-width: 520px; width: 100%; padding: 36px 30px; text-align: center; border: 1.5px solid #fed7aa; }
            .db-icon { width: 72px; height: 72px; background: #fff7ed; color: #ea580c; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 20px; }
            h2 { font-size: 1.35rem; color: #9a3412; margin-bottom: 12px; }
            p { font-size: 0.92rem; color: #64748b; line-height: 1.6; margin-bottom: 22px; }
            .db-steps { background: #f8fafc; border-radius: 12px; padding: 16px 20px; text-align: left; font-size: 0.88rem; color: #334155; margin-bottom: 24px; border: 1px solid #e2e8f0; }
            .db-steps ol { margin: 0; padding-left: 20px; }
            .db-steps li { margin-bottom: 6px; }
            .btn-refresh { background: #0284c7; color: white; border: none; padding: 12px 28px; border-radius: 10px; font-weight: 700; font-size: 0.92rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
            .btn-refresh:hover { background: #0369a1; transform: translateY(-1px); }
        </style>
    </head>
    <body>
        <div class="db-card">
            <div class="db-icon"><i class="fa-solid fa-database"></i></div>
            <h2>Layanan Database MySQL Belum Aktif</h2>
            <p>Aplikasi tidak dapat terhubung ke server database MySQL pada komputer lokal Anda.</p>
            <div class="db-steps">
                <strong>Panduan Menyalakan Database di Laragon:</strong>
                <ol>
                    <li>Buka jendela aplikasi <strong>Laragon</strong>.</li>
                    <li>Klik tombol <strong>"Start All"</strong> (pastikan tulisan MySQL berstatus Running).</li>
                    <li>Setelah menyala, muat ulang halaman ini.</li>
                </ol>
            </div>
            <a href="javascript:location.reload()" class="btn-refresh"><i class="fa-solid fa-rotate-right"></i> Muat Ulang Halaman</a>
        </div>
    </body>
    </html>';
    exit();
}

/**
 * Validasi dan simpan file upload gambar dengan aman
 * @param array $file - $_FILES['field_name']
 * @param string $prefix - prefix nama file (misal: 'foto', 'bukti', 'umkm')
 * @return array ['success' => bool, 'message' => string, 'filename' => string|null]
 */
function secure_image_upload($file, $prefix = 'upload') {
    // Cek error upload
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File tidak berhasil diunggah.', 'filename' => null];
    }

    $tmp_path = $file['tmp_name'];
    
    // Validasi ukuran maksimal 2MB
    $max_size = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Ukuran file maksimal 2 MB.', 'filename' => null];
    }

    // Validasi MIME type dengan finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp_path);
    finfo_close($finfo);

    // Validasi dengan getimagesize (memastikan benar-benar gambar)
    $image_info = @getimagesize($tmp_path);
    if ($image_info === false) {
        return ['success' => false, 'message' => 'File bukan gambar yang valid.', 'filename' => null];
    }

    // Map MIME ke ekstensi yang diperbolehkan
    $mime_to_ext = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif'
    ];

    if (!isset($mime_to_ext[$mime])) {
        return ['success' => false, 'message' => 'Format file tidak didukung. Gunakan JPG, PNG, atau GIF.', 'filename' => null];
    }

    $extension = $mime_to_ext[$mime];
    
    // Generate nama file acak
    $random_name = $prefix . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    
    return ['success' => true, 'message' => 'Validasi berhasil.', 'filename' => $random_name];
}

/**
 * Memastikan user sudah login
 */
function require_login() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['nama'])) {
        header("Location: " . (strpos($_SERVER['PHP_SELF'], 'folder dashboard') !== false ? '../' : '') . "folder login,logout dan register/login.php");
        exit();
    }
}

/**
 * Memastikan user adalah Admin (case-insensitive)
 */
function require_admin() {
    require_login();
    $role = strtoupper($_SESSION['role'] ?? 'WARGA');
    if ($role !== 'ADMIN') {
        echo "<script>alert('Akses Ditolak! Halaman ini hanya dapat diakses oleh Admin.'); window.history.back();</script>";
        exit();
    }
}

/**
 * Generate CSRF token dan simpan di session
 * @return string
 */
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden input CSRF token untuk form
 * @return string
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Validasi CSRF token dari POST request
 * @return bool
 */
function csrf_check() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token_session = $_SESSION['csrf_token'] ?? '';
    $token_post = $_POST['csrf_token'] ?? '';
    
    if ($token_session === '' || $token_post === '') {
        return false;
    }
    
    return hash_equals($token_session, $token_post);
}

/**
 * Helper untuk prepared statement sederhana
 * @param mysqli $conn - koneksi database
 * @param string $sql - SQL query dengan placeholder ?
 * @param string $types - tipe parameter (s=string, i=integer, d=double, b=blob)
 * @param mixed ...$params - parameter untuk binding
 * @return mysqli_result|bool - result untuk SELECT, bool untuk INSERT/UPDATE/DELETE
 */
function db_query($conn, $sql, $types = '', ...$params) {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    
    if ($types !== '' && count($params) > 0) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    if ($result === false) {
        // INSERT/UPDATE/DELETE - return true jika berhasil
        $success = mysqli_stmt_affected_rows($stmt) >= 0;
        mysqli_stmt_close($stmt);
        return $success;
    }
    
    mysqli_stmt_close($stmt);
    return $result;
}
?>