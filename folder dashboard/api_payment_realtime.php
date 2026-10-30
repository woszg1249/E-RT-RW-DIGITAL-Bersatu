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
    $order_id = mysqli_real_escape_string($koneksi, trim($_GET['order_id'] ?? ''));

    if (empty($order_id)) {
        echo json_encode(['success' => false, 'message' => 'Order ID tidak valid']);
        exit();
    }

    $q = mysqli_query($koneksi, "SELECT * FROM transaksi_pembayaran WHERE order_id = '$order_id' LIMIT 1");
    if (!$q || mysqli_num_rows($q) === 0) {
        echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan']);
        exit();
    }

    $trx = mysqli_fetch_assoc($q);
    echo json_encode([
        'success' => true,
        'order_id' => $trx['order_id'],
        'status' => $trx['status'], // 'pending', 'success', 'expired'
        'jumlah' => (int)$trx['jumlah'],
        'nama_warga' => $trx['nama_warga'],
        'keterangan' => $trx['keterangan'],
        'paid_at' => $trx['paid_at'] ?? null,
    ]);
    exit();
}

// 2. Konfirmasi Pembayaran Instan / Simulasi Real-Time
if ($action === 'confirm_pay') {
    $order_id = mysqli_real_escape_string($koneksi, trim($_POST['order_id'] ?? $_GET['order_id'] ?? ''));

    if (empty($order_id)) {
        echo json_encode(['success' => false, 'message' => 'Order ID tidak valid']);
        exit();
    }

    $q = mysqli_query($koneksi, "SELECT * FROM transaksi_pembayaran WHERE order_id = '$order_id' LIMIT 1");
    if (!$q || mysqli_num_rows($q) === 0) {
        echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan']);
        exit();
    }

    $trx = mysqli_fetch_assoc($q);

    if ($trx['status'] === 'success') {
        echo json_encode([
            'success' => true,
            'message' => 'Transaksi sudah berstatus LUNAS',
            'status' => 'success',
            'paid_at' => $trx['paid_at']
        ]);
        exit();
    }

    // Update status transaksi menjadi success
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $update_trx = mysqli_query($koneksi, "UPDATE transaksi_pembayaran SET status = 'success', paid_at = '$now' WHERE order_id = '$order_id'");

    if (!$update_trx) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate status transaksi: ' . mysqli_error($koneksi)]);
        exit();
    }

    // Masukkan ke buku kas Keuangan secara otomatis
    $nama_warga = mysqli_real_escape_string($koneksi, $trx['nama_warga']);
    $jenis_kas  = mysqli_real_escape_string($koneksi, $trx['jenis_kas']);
    $keterangan = mysqli_real_escape_string($koneksi, $trx['keterangan'] . ' [QRIS Real-Time: ' . $order_id . ']');
    $jumlah_final = ($jenis_kas === 'keluar') ? -abs((int)$trx['jumlah']) : abs((int)$trx['jumlah']);

    $ins_keuangan = mysqli_query($koneksi, "INSERT INTO keuangan (nama_warga, jenis, keterangan, jumlah, tanggal) VALUES ('$nama_warga', '$jenis_kas', '$keterangan', '$jumlah_final', '$today')");

    $id_keuangan = mysqli_insert_id($koneksi);

    // Jika ini adalah pembayaran tagihan bulanan warga, ubah status tagihan jadi 'lunas'
    $id_tagihan = (int)($trx['id_tagihan'] ?? 0);
    if ($id_tagihan > 0) {
        mysqli_query($koneksi, "UPDATE tagihan SET status_bayar = 'lunas', tgl_update = CURRENT_TIMESTAMP WHERE id_tagihan = '$id_tagihan'");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Pembayaran berhasil diverifikasi secara Real-Time!',
        'status' => 'success',
        'paid_at' => $now,
        'id_keuangan' => $id_keuangan
    ]);
    exit();
}

// 3. Webhook Receiver untuk Payment Gateway sungguhan (Midtrans / Tripay)
if ($action === 'webhook') {
    $raw_payload = file_get_contents('php://input');
    $data = json_decode($raw_payload, true);

    // Midtrans notification handling
    if (isset($data['order_id']) && isset($data['transaction_status'])) {
        $order_id = mysqli_real_escape_string($koneksi, $data['order_id']);
        $trx_status = $data['transaction_status'];

        if ($trx_status === 'settlement' || $trx_status === 'capture') {
            $now = date('Y-m-d H:i:s');
            mysqli_query($koneksi, "UPDATE transaksi_pembayaran SET status = 'success', paid_at = '$now' WHERE order_id = '$order_id'");
            echo json_encode(['status' => 'ok']);
            exit();
        }
    }

    echo json_encode(['status' => 'ignored']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal']);
exit();
