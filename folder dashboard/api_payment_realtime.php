<?php
/**
 * RT RW Digital Bersatu - Real-Time Payment API Engine
 * Mengelola status transaksi, polling real-time, simulasi instan, dan webhook gateway.
 */

header('Content-Type: application/json; charset=utf-8');

include __DIR__ . "/../Koneksi.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 1. Cek Status Pembayaran Real-Time (Polling dari frontend)
if ($action === 'check_status') {
    if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Silakan login terlebih dahulu.']);
        exit();
    }

    $order_id = trim($_GET['order_id'] ?? '');

    if (empty($order_id)) {
        echo json_encode(['success' => false, 'message' => 'Order ID tidak valid']);
        exit();
    }

    $stmt = mysqli_prepare($koneksi, "SELECT * FROM transaksi_pembayaran WHERE order_id = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem']);
        exit();
    }

    mysqli_stmt_bind_param($stmt, "s", $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result || mysqli_num_rows($result) === 0) {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan']);
        exit();
    }

    $trx = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $user_role = strtoupper($_SESSION['role']);
    $user_id = (int)$_SESSION['id'];

    if ($user_role !== 'ADMIN' && (int)$trx['id_user'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden. Anda tidak memiliki akses ke transaksi ini.']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'order_id' => $trx['order_id'],
        'status' => $trx['status'],
        'jumlah' => (int)$trx['jumlah'],
        'nama_warga' => $trx['nama_warga'],
        'keterangan' => $trx['keterangan'],
        'paid_at' => $trx['paid_at'] ?? null,
    ]);
    exit();
}

// 2. Konfirmasi Pembayaran Instan / Simulasi Real-Time
// CATATAN: Ini adalah fitur SIMULASI untuk keperluan DEMO/TESTING.
// Pada production sungguhan, gunakan webhook dari payment gateway.
if ($action === 'confirm_pay') {
    if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Silakan login terlebih dahulu.']);
        exit();
    }

    $user_role = strtoupper($_SESSION['role']);
    if ($user_role !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden. Hanya Admin yang dapat mengkonfirmasi pembayaran.']);
        exit();
    }

    $order_id = trim($_POST['order_id'] ?? $_GET['order_id'] ?? '');

    if (empty($order_id)) {
        echo json_encode(['success' => false, 'message' => 'Order ID tidak valid']);
        exit();
    }

    $stmt = mysqli_prepare($koneksi, "SELECT * FROM transaksi_pembayaran WHERE order_id = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem']);
        exit();
    }

    mysqli_stmt_bind_param($stmt, "s", $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result || mysqli_num_rows($result) === 0) {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan']);
        exit();
    }

    $trx = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($trx['status'] === 'success') {
        echo json_encode([
            'success' => true,
            'message' => 'Transaksi sudah berstatus LUNAS',
            'status' => 'success',
            'paid_at' => $trx['paid_at']
        ]);
        exit();
    }

    mysqli_begin_transaction($koneksi);

    try {
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        $stmt_update = mysqli_prepare($koneksi, "UPDATE transaksi_pembayaran SET status = 'success', paid_at = ? WHERE order_id = ?");
        if (!$stmt_update) {
            throw new Exception('Gagal menyiapkan query update transaksi');
        }
        mysqli_stmt_bind_param($stmt_update, "ss", $now, $order_id);
        if (!mysqli_stmt_execute($stmt_update)) {
            throw new Exception('Gagal mengupdate status transaksi');
        }
        mysqli_stmt_close($stmt_update);

        $nama_warga = $trx['nama_warga'];
        $jenis_kas  = $trx['jenis_kas'];
        $keterangan = $trx['keterangan'] . ' [QRIS Real-Time: ' . $order_id . ']';
        $jumlah_final = ($jenis_kas === 'keluar') ? -abs((int)$trx['jumlah']) : abs((int)$trx['jumlah']);

        $stmt_keuangan = mysqli_prepare($koneksi, "INSERT INTO keuangan (nama_warga, jenis, keterangan, jumlah, tanggal) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt_keuangan) {
            throw new Exception('Gagal menyiapkan query insert keuangan');
        }
        mysqli_stmt_bind_param($stmt_keuangan, "sssis", $nama_warga, $jenis_kas, $keterangan, $jumlah_final, $today);
        if (!mysqli_stmt_execute($stmt_keuangan)) {
            throw new Exception('Gagal memasukkan data ke buku kas');
        }
        $id_keuangan = mysqli_insert_id($koneksi);
        mysqli_stmt_close($stmt_keuangan);

        $id_tagihan = (int)($trx['id_tagihan'] ?? 0);
        if ($id_tagihan > 0) {
            $stmt_tagihan = mysqli_prepare($koneksi, "UPDATE tagihan SET status_bayar = 'lunas', tgl_update = CURRENT_TIMESTAMP WHERE id_tagihan = ?");
            if (!$stmt_tagihan) {
                throw new Exception('Gagal menyiapkan query update tagihan');
            }
            mysqli_stmt_bind_param($stmt_tagihan, "i", $id_tagihan);
            mysqli_stmt_execute($stmt_tagihan);
            mysqli_stmt_close($stmt_tagihan);
        }

        mysqli_commit($koneksi);

        echo json_encode([
            'success' => true,
            'message' => 'Pembayaran berhasil diverifikasi secara Real-Time!',
            'status' => 'success',
            'paid_at' => $now,
            'id_keuangan' => $id_keuangan
        ]);
        exit();

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        echo json_encode(['success' => false, 'message' => 'Gagal memproses pembayaran: ' . $e->getMessage()]);
        exit();
    }
}

// 3. Webhook Receiver untuk Payment Gateway sungguhan (Midtrans / Tripay)
if ($action === 'webhook') {
    $raw_payload = file_get_contents('php://input');
    $data = json_decode($raw_payload, true);

    if (!isset($data['order_id']) || !isset($data['transaction_status'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
        exit();
    }

    $payment_config = include(__DIR__ . '/../config_payment.php');
    $server_key = $payment_config['midtrans']['server_key'] ?? '';

    if (empty($server_key)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Server key not configured']);
        exit();
    }

    $order_id = $data['order_id'];
    $status_code = $data['status_code'] ?? '';
    $gross_amount = $data['gross_amount'] ?? '';
    $signature_key = $data['signature_key'] ?? '';

    $expected_signature = hash('sha512', $order_id . $status_code . $gross_amount . $server_key);

    if (!hash_equals($expected_signature, $signature_key)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
        exit();
    }

    $stmt = mysqli_prepare($koneksi, "SELECT * FROM transaksi_pembayaran WHERE order_id = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
        exit();
    }

    mysqli_stmt_bind_param($stmt, "s", $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result || mysqli_num_rows($result) === 0) {
        mysqli_stmt_close($stmt);
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
        exit();
    }

    $trx = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $expected_amount = (string)$trx['jumlah'];
    if ($gross_amount !== $expected_amount) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Amount mismatch']);
        exit();
    }

    $trx_status = $data['transaction_status'];

    if ($trx_status === 'settlement' || $trx_status === 'capture') {
        mysqli_begin_transaction($koneksi);

        try {
            $now = date('Y-m-d H:i:s');
            $today = date('Y-m-d');

            $stmt_update = mysqli_prepare($koneksi, "UPDATE transaksi_pembayaran SET status = 'success', paid_at = ? WHERE order_id = ?");
            if (!$stmt_update) {
                throw new Exception('Failed to prepare update statement');
            }
            mysqli_stmt_bind_param($stmt_update, "ss", $now, $order_id);
            if (!mysqli_stmt_execute($stmt_update)) {
                throw new Exception('Failed to update transaction');
            }
            mysqli_stmt_close($stmt_update);

            $nama_warga = $trx['nama_warga'];
            $jenis_kas  = $trx['jenis_kas'];
            $keterangan = $trx['keterangan'] . ' [Midtrans Webhook: ' . $order_id . ']';
            $jumlah_final = ($jenis_kas === 'keluar') ? -abs((int)$trx['jumlah']) : abs((int)$trx['jumlah']);

            $stmt_keuangan = mysqli_prepare($koneksi, "INSERT INTO keuangan (nama_warga, jenis, keterangan, jumlah, tanggal) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt_keuangan) {
                throw new Exception('Failed to prepare keuangan insert');
            }
            mysqli_stmt_bind_param($stmt_keuangan, "sssis", $nama_warga, $jenis_kas, $keterangan, $jumlah_final, $today);
            if (!mysqli_stmt_execute($stmt_keuangan)) {
                throw new Exception('Failed to insert keuangan');
            }
            mysqli_stmt_close($stmt_keuangan);

            $id_tagihan = (int)($trx['id_tagihan'] ?? 0);
            if ($id_tagihan > 0) {
                $stmt_tagihan = mysqli_prepare($koneksi, "UPDATE tagihan SET status_bayar = 'lunas', tgl_update = CURRENT_TIMESTAMP WHERE id_tagihan = ?");
                if ($stmt_tagihan) {
                    mysqli_stmt_bind_param($stmt_tagihan, "i", $id_tagihan);
                    mysqli_stmt_execute($stmt_tagihan);
                    mysqli_stmt_close($stmt_tagihan);
                }
            }

            mysqli_commit($koneksi);

            echo json_encode(['status' => 'ok']);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit();
        }
    }

    echo json_encode(['status' => 'ignored']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal']);
exit();
