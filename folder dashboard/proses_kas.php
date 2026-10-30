<?php
/**
 * RT RW Digital Bersatu - Pengolah Pembayaran & Kas
 * Mendukung Pembayaran Real-Time (QRIS Dinamis, Polling Otomatis, Simulasi Instan) & Upload Bukti Manual.
 */

include "../Koneksi.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nama'])) {
    header("Location: ../folder login,logout dan register/login.php");
    exit();
}

// Pastikan tabel pendukung ada
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS bukti_kas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_keuangan INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_keuangan) REFERENCES keuangan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS transaksi_pembayaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(64) UNIQUE NOT NULL,
    id_user INT NULL,
    nama_warga VARCHAR(100) NOT NULL,
    jenis_kas VARCHAR(20) DEFAULT 'masuk',
    keterangan VARCHAR(255) NOT NULL,
    jumlah INT NOT NULL,
    id_tagihan INT NULL,
    metode VARCHAR(50) DEFAULT 'QRIS',
    status VARCHAR(30) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$role_session = isset($_SESSION['role']) ? strtoupper($_SESSION['role']) : 'WARGA';

// Tangani direct payment dari link Notifikasi Tagihan Warga
$id_tagihan = 0;
if (isset($_GET['pay_tagihan'])) {
    $id_tagihan = (int)$_GET['pay_tagihan'];
    $q_tagihan = mysqli_query($koneksi, "SELECT t.*, u.nama, u.id as user_id FROM tagihan t JOIN users u ON t.id_user = u.id WHERE t.id_tagihan = '$id_tagihan' LIMIT 1");
    if ($q_tagihan && mysqli_num_rows($q_tagihan) > 0) {
        $tagihan_data = mysqli_fetch_assoc($q_tagihan);
        $_POST['action'] = 'preview_payment';
        $_POST['nama_warga'] = $tagihan_data['nama'];
        $_POST['keterangan'] = "Iuran Kas RT Bulan " . $tagihan_data['bulan_tahun'];
        $_POST['jenis_kas'] = "masuk";
        $_POST['jumlah_kas'] = (int)$tagihan_data['jumlah'];
        $_POST['id_tagihan'] = $id_tagihan;
        $_POST['metode_bayar'] = 'realtime';
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // =========================================================================
    // PREVIEW PEMBAYARAN (REAL-TIME QRIS & MANUAL PROOF)
    // =========================================================================
    if ($action === 'preview_payment') {
        $nama_warga   = mysqli_real_escape_string($koneksi, trim($_POST['nama_warga'] ?? ''));
        $keterangan   = mysqli_real_escape_string($koneksi, trim($_POST['keterangan'] ?? ''));
        $jenis_kas    = mysqli_real_escape_string($koneksi, trim($_POST['jenis_kas'] ?? 'masuk'));
        $jumlah_kas   = intval($_POST['jumlah_kas'] ?? 0);
        $id_tagihan   = intval($_POST['id_tagihan'] ?? 0);
        $metode_bayar = trim($_POST['metode_bayar'] ?? 'realtime');

        if ($role_session !== 'ADMIN') {
            $nama_warga = mysqli_real_escape_string($koneksi, $_SESSION['nama']);
        }

        if ($nama_warga === '' || $keterangan === '' || $jumlah_kas <= 0) {
            echo '<div style="font-family:sans-serif;padding:30px;text-align:center;"><h3>Data pembayaran tidak lengkap. Silakan kembali dan isi semua kolom dengan benar.</h3><a href="input_kas.php">Kembali</a></div>';
            exit();
        }

        $jumlah_terformat = number_format($jumlah_kas, 0, ',', '.');
        $order_id = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $user_id_val = (int)($_SESSION['id_user'] ?? $_SESSION['id'] ?? 0);

        // Catat order ke transaksi_pembayaran
        $sql_order = "INSERT INTO transaksi_pembayaran (order_id, id_user, nama_warga, jenis_kas, keterangan, jumlah, id_tagihan, metode, status) 
                      VALUES ('$order_id', '$user_id_val', '$nama_warga', '$jenis_kas', '$keterangan', '$jumlah_kas', " . ($id_tagihan > 0 ? "'$id_tagihan'" : "NULL") . ", 'QRIS', 'pending')";
        mysqli_query($koneksi, $sql_order);

        // Cari file logo QRIS fisik
        $qris_candidates = ['logo_Qris.jpeg', 'qris.png', 'qris.jpg', 'qris.jpeg'];
        $qris_file_relative = null;
        $qris_file_path = null;
        $qris_dir = dirname(__DIR__) . '/img/';
        foreach ($qris_candidates as $candidate) {
            if (file_exists($qris_dir . $candidate)) {
                $qris_file_path = $qris_dir . $candidate;
                $qris_file_relative = '../img/' . $candidate;
                break;
            }
        }

        // Dynamic QRIS Data String
        $qrData = "00020101021226600016ID.CO.RTRW.WWW01189360099900000000000215{$order_id}520458125303360540" . $jumlah_kas . "5802ID5914RT RW DIGITAL6006BEKASI6304";
        $dynamicQrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=" . urlencode($qrData);
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Pembayaran QRIS Real-Time - RT RW Digital Bersatu</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <link rel="stylesheet" href="dashboard.css">
            <style>
                .payment-wrap { max-width: 820px; margin: 30px auto; padding: 20px; }
                .payment-box { background: #ffffff; border-radius: 20px; box-shadow: 0 16px 40px rgba(15,23,42,0.1); border: 1px solid var(--line); overflow: hidden; }
                .payment-header { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; padding: 24px 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
                .payment-header h2 { margin: 0; font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }
                .order-pill { background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 999px; font-size: 0.82rem; font-family: monospace; font-weight: 700; }
                
                .payment-body { padding: 28px; }
                
                .realtime-alert { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 14px; padding: 14px 18px; margin-bottom: 24px; display: flex; align-items: center; gap: 14px; }
                .pulse-circle { width: 14px; height: 14px; border-radius: 50%; background: #10b981; box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); animation: pulseGreen 1.5s infinite; flex-shrink: 0; }
                @keyframes pulseGreen { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
                
                .payment-cols { display: grid; grid-template-columns: 320px 1fr; gap: 28px; align-items: start; }
                
                .qris-frame { background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 18px; padding: 18px; text-align: center; }
                .qris-img-wrap { width: 100%; max-width: 250px; margin: 0 auto 12px; background: #ffffff; border-radius: 12px; padding: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
                .qris-img-wrap img { width: 100%; height: auto; display: block; border-radius: 8px; }
                
                .countdown-timer { font-family: monospace; font-size: 1.15rem; font-weight: 800; color: #dc2626; display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 10px; }
                
                .trx-summary { display: flex; flex-direction: column; gap: 14px; }
                .summary-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; }
                .summary-item .label { color: #64748b; font-size: 0.85rem; font-weight: 600; }
                .summary-item .value { color: #0f172a; font-size: 0.95rem; font-weight: 700; text-align: right; }
                .summary-item.total { background: #eff6ff; border-color: #bfdbfe; }
                .summary-item.total .value { color: #0284c7; font-size: 1.35rem; font-weight: 800; }

                .action-section { margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 12px; }
                .btn-simulasi-pay { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; border: none; padding: 14px 20px; border-radius: 12px; font-weight: 800; font-size: 0.95rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35); transition: all 0.2s ease; width: 100%; }
                .btn-simulasi-pay:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5); }
                
                .manual-tab-toggle { background: transparent; border: 1px solid #cbd5e1; color: #475569; padding: 10px 16px; border-radius: 10px; font-size: 0.85rem; font-weight: 600; cursor: pointer; text-align: center; }
                .manual-tab-toggle:hover { background: #f8fafc; color: #0f172a; }
                
                #manualUploadSection { display: none; margin-top: 18px; padding: 18px; background: #f8fafc; border-radius: 14px; border: 1px solid #e2e8f0; }
                
                /* Success View Overlay */
                #successOverlay { display: none; text-align: center; padding: 30px 10px; }
                .success-check-icon { width: 80px; height: 80px; border-radius: 50%; background: #dcfce7; color: #16a34a; font-size: 2.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; box-shadow: 0 8px 24px rgba(22, 163, 74, 0.25); animation: bounceIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
                @keyframes bounceIn { 0% { transform: scale(0.3); opacity: 0; } 50% { transform: scale(1.05); } 100% { transform: scale(1); opacity: 1; } }

                .receipt-card { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 14px; padding: 20px; max-width: 480px; margin: 20px auto; text-align: left; }
                .receipt-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 0.9rem; }
                .receipt-row:last-child { border-bottom: none; }
                
                @media (max-width: 768px) {
                    .payment-cols { grid-template-columns: 1fr; }
                    .payment-header { flex-direction: column; align-items: flex-start; }
                }
            </style>
        </head>
        <body>
            <div class="payment-wrap">
                <div class="payment-box">
                    <div class="payment-header">
                        <h2><i class="fa-solid fa-bolt" style="color: #facc15;"></i> Pembayaran QRIS Real-Time</h2>
                        <span class="order-pill"><?php echo htmlspecialchars($order_id); ?></span>
                    </div>

                    <div class="payment-body">
                        <!-- Konten Pembayaran Aktif -->
                        <div id="activePaymentSection">
                            <div class="realtime-alert">
                                <div class="pulse-circle"></div>
                                <div style="flex:1;">
                                    <strong style="color: #065f46; font-size: 0.95rem; display:block;">Pendeteksi Pembayaran Real-Time Aktif</strong>
                                    <span style="color: #047857; font-size: 0.82rem;">Sistem otomatis memverifikasi saat Anda scan & bayar. Tidak perlu refresh halaman.</span>
                                </div>
                            </div>

                            <div class="payment-cols">
                                <!-- Kolom Kiri: QRIS & Timer -->
                                <div class="qris-frame">
                                    <div class="qris-img-wrap">
                                        <?php if ($qris_file_relative !== null && file_exists($qris_file_path)): ?>
                                            <img src="<?php echo $qris_file_relative; ?>" alt="QRIS RT RW Digital">
                                        <?php else: ?>
                                            <img src="<?php echo $dynamicQrUrl; ?>" alt="QRIS Real-Time">
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-weight:700; color:#334155; font-size:0.85rem;"><i class="fa-solid fa-qrcode"></i> Scan dengan BCA, GoPay, OVO, Dana, ShopeePay</div>
                                    <div class="countdown-timer">
                                        <i class="fa-regular fa-clock"></i> <span id="timerDisplay">14:59</span>
                                    </div>
                                </div>

                                <!-- Kolom Kanan: Detail & Aksi Real-Time -->
                                <div>
                                    <div class="trx-summary">
                                        <div class="summary-item">
                                            <span class="label">Nama Pembayar</span>
                                            <span class="value"><?php echo htmlspecialchars($nama_warga); ?></span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="label">Keperluan Transaksi</span>
                                            <span class="value"><?php echo htmlspecialchars($keterangan); ?></span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="label">Metode</span>
                                            <span class="value"><span style="color:#16a34a; font-weight:800;">⚡ QRIS Real-Time</span></span>
                                        </div>
                                        <div class="summary-item total">
                                            <span class="label">Total Pembayaran</span>
                                            <span class="value">Rp <?php echo $jumlah_terformat; ?></span>
                                        </div>
                                    </div>

                                    <div class="action-section">
                                        <!-- Tombol Simulasi Pembayaran Instan (Real-Time Webhook Simulator) -->
                                        <button type="button" class="btn-simulasi-pay" id="btnSimulatePay">
                                            <i class="fa-solid fa-circle-check"></i>
                                            <span>Simulasi / Konfirmasi Bayar Real-Time</span>
                                        </button>

                                        <!-- Opsi Bukti Manual -->
                                        <button type="button" class="manual-tab-toggle" id="btnToggleManual">
                                            <i class="fa-solid fa-file-arrow-up"></i> Ingin Upload Bukti Transfer Manual?
                                        </button>

                                        <div id="manualUploadSection">
                                            <form action="proses_kas.php" method="POST" enctype="multipart/form-data">
                                                <input type="hidden" name="action" value="submit_payment">
                                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">
                                                <input type="hidden" name="nama_warga" value="<?php echo htmlspecialchars($nama_warga, ENT_QUOTES); ?>">
                                                <input type="hidden" name="keterangan" value="<?php echo htmlspecialchars($keterangan, ENT_QUOTES); ?>">
                                                <input type="hidden" name="jenis_kas" value="<?php echo htmlspecialchars($jenis_kas, ENT_QUOTES); ?>">
                                                <input type="hidden" name="jumlah_kas" value="<?php echo intval($jumlah_kas); ?>">
                                                <input type="hidden" name="id_tagihan" value="<?php echo intval($id_tagihan); ?>">

                                                <label style="display:block; font-weight:700; font-size:0.85rem; margin-bottom:8px;">Pilih File Foto Bukti (JPG/PNG):</label>
                                                <input type="file" name="bukti_pembayaran" accept="image/jpeg,image/jpg,image/png" required style="width:100%; padding:10px; background:#fff; border:1px solid #cbd5e1; border-radius:8px; margin-bottom:12px;">
                                                <button type="submit" class="primary-btn" style="width:100%;">Unggah & Simpan Manual</button>
                                            </form>
                                        </div>

                                        <a href="input_kas.php" style="text-align:center; color:#64748b; font-size:0.85rem; text-decoration:none; margin-top:6px;">
                                            <i class="fa-solid fa-arrow-left"></i> Batalkan & Kembali
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tampilan Sukses Real-Time -->
                        <div id="successOverlay">
                            <div class="success-check-icon">
                                <i class="fa-solid fa-check"></i>
                            </div>
                            <h2 style="color:#0f172a; margin:0 0 6px; font-size:1.6rem; font-weight:800;">Pembayaran Berhasil!</h2>
                            <p style="color:#16a34a; font-weight:700; margin:0 0 20px;">Transaksi telah diverifikasi & dana otomatis masuk ke Kas RT RW Digital</p>

                            <div class="receipt-card">
                                <div class="receipt-row">
                                    <span style="color:#64748b;">No. Transaksi</span>
                                    <strong style="font-family:monospace;"><?php echo htmlspecialchars($order_id); ?></strong>
                                </div>
                                <div class="receipt-row">
                                    <span style="color:#64748b;">Nama Warga</span>
                                    <strong><?php echo htmlspecialchars($nama_warga); ?></strong>
                                </div>
                                <div class="receipt-row">
                                    <span style="color:#64748b;">Keterangan</span>
                                    <span><?php echo htmlspecialchars($keterangan); ?></span>
                                </div>
                                <div class="receipt-row">
                                    <span style="color:#64748b;">Waktu Bayar</span>
                                    <strong id="receiptPaidAt"><?php echo date('d-m-Y H:i:s'); ?></strong>
                                </div>
                                <div class="receipt-row" style="border-top:2px solid #e2e8f0; margin-top:8px; padding-top:10px;">
                                    <span style="font-weight:700; color:#0f172a;">Total Terbayar</span>
                                    <span style="font-size:1.2rem; font-weight:800; color:#16a34a;">Rp <?php echo $jumlah_terformat; ?></span>
                                </div>
                            </div>

                            <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap; margin-top:24px;">
                                <button type="button" class="ghost-btn" onclick="window.print()">
                                    <i class="fa-solid fa-print"></i> Cetak Bukti Pembayaran
                                </button>
                                <a href="dashboard.php" class="primary-btn">
                                    <i class="fa-solid fa-house"></i> Kembali ke Dashboard
                                </a>
                                <a href="laporan.php" class="ghost-btn">
                                    <i class="fa-solid fa-chart-line"></i> Lihat Laporan Kas
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                const ORDER_ID = "<?php echo $order_id; ?>";
                let isPolling = true;

                // 1. Live Countdown Timer (15 menit)
                let remainingSeconds = 15 * 60;
                const timerDisplay = document.getElementById('timerDisplay');
                const timerInterval = setInterval(function () {
                    remainingSeconds--;
                    if (remainingSeconds <= 0) {
                        clearInterval(timerInterval);
                        timerDisplay.innerText = "KADALUARSA";
                        timerDisplay.style.color = "#64748b";
                        isPolling = false;
                        return;
                    }
                    const mins = Math.floor(remainingSeconds / 60);
                    const secs = remainingSeconds % 60;
                    timerDisplay.innerText = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
                }, 1000);

                // 2. Tampilkan Layar Sukses
                function showSuccessScreen(data) {
                    isPolling = false;
                    clearInterval(timerInterval);
                    document.getElementById('activePaymentSection').style.display = 'none';
                    if (data && data.paid_at) {
                        document.getElementById('receiptPaidAt').innerText = data.paid_at;
                    }
                    document.getElementById('successOverlay').style.display = 'block';
                }

                // 3. Polling Real-Time Status setiap 3 detik
                function pollPaymentStatus() {
                    if (!isPolling) return;
                    fetch(`api_payment_realtime.php?action=check_status&order_id=${encodeURIComponent(ORDER_ID)}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.success && data.status === 'success') {
                                showSuccessScreen(data);
                            } else if (isPolling) {
                                setTimeout(pollPaymentStatus, 3000);
                            }
                        })
                        .catch(err => {
                            if (isPolling) setTimeout(pollPaymentStatus, 4000);
                        });
                }
                setTimeout(pollPaymentStatus, 2500);

                // 4. Tombol Simulasi Pembayaran Real-Time (Instant Webhook Trigger)
                const btnSimulate = document.getElementById('btnSimulatePay');
                if (btnSimulate) {
                    btnSimulate.addEventListener('click', function () {
                        btnSimulate.disabled = true;
                        btnSimulate.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses Verifikasi Real-Time...';

                        const formData = new FormData();
                        formData.append('action', 'confirm_pay');
                        formData.append('order_id', ORDER_ID);

                        fetch('api_payment_realtime.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                showSuccessScreen(data);
                            } else {
                                alert(data.message || 'Gagal memproses pembayaran');
                                btnSimulate.disabled = false;
                                btnSimulate.innerHTML = '<i class="fa-solid fa-circle-check"></i> Simulasi / Konfirmasi Bayar Real-Time';
                            }
                        })
                        .catch(err => {
                            alert('Terjadi kesalahan koneksi');
                            btnSimulate.disabled = false;
                            btnSimulate.innerHTML = '<i class="fa-solid fa-circle-check"></i> Simulasi / Konfirmasi Bayar Real-Time';
                        });
                    });
                }

                // 5. Toggle Bukti Manual
                const btnToggle = document.getElementById('btnToggleManual');
                const manualSection = document.getElementById('manualUploadSection');
                if (btnToggle && manualSection) {
                    btnToggle.addEventListener('click', function () {
                        const isHidden = manualSection.style.display === 'none' || manualSection.style.display === '';
                        manualSection.style.display = isHidden ? 'block' : 'none';
                        btnToggle.innerText = isHidden ? '✕ Sembunyikan Form Upload Manual' : '📁 Ingin Upload Bukti Transfer Manual?';
                    });
                }
            </script>
        </body>
        </html>
        <?php
        exit();
    }

    // =========================================================================
    // SUBMIT PEMBAYARAN MANUAL (UPLOAD FILE)
    // =========================================================================
    if ($action === 'submit_payment') {
        $nama_warga = mysqli_real_escape_string($koneksi, trim($_POST['nama_warga'] ?? ''));
        $keterangan = mysqli_real_escape_string($koneksi, trim($_POST['keterangan'] ?? ''));
        $jenis_kas  = mysqli_real_escape_string($koneksi, trim($_POST['jenis_kas'] ?? 'masuk'));
        $jumlah_kas = intval($_POST['jumlah_kas'] ?? 0);
        $id_tagihan = intval($_POST['id_tagihan'] ?? 0);
        $order_id   = mysqli_real_escape_string($koneksi, trim($_POST['order_id'] ?? ''));

        if ($role_session !== 'ADMIN') {
            $nama_warga = mysqli_real_escape_string($koneksi, $_SESSION['nama']);
        }

        if ($nama_warga === '' || $keterangan === '' || $jumlah_kas <= 0) {
            echo '<h3>Data transaksi tidak valid. Silakan ulangi proses dari awal.</h3>';
            exit();
        }

        if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK) {
            echo '<h3>Unggah bukti pembayaran terlebih dahulu.</h3>';
            exit();
        }

        $allowed = ['image/jpeg','image/jpg','image/png'];
        $file_type = mime_content_type($_FILES['bukti_pembayaran']['tmp_name']);
        if (!in_array($file_type, $allowed, true)) {
            echo '<h3>Format bukti pembayaran tidak valid. Gunakan JPG, JPEG, atau PNG.</h3>';
            exit();
        }

        $uploadDir = dirname(__DIR__) . '/img/bukti_kas';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = pathinfo($_FILES['bukti_pembayaran']['name'], PATHINFO_EXTENSION);
        $filename = 'bukti_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $destination = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $destination)) {
            echo '<h3>Gagal mengunggah bukti pembayaran. Silakan coba lagi.</h3>';
            exit();
        }

        $tanggal = date("Y-m-d");
        $jumlah_final = ($jenis_kas === 'keluar') ? -abs($jumlah_kas) : abs($jumlah_kas);

        $sql = "INSERT INTO keuangan (nama_warga, jenis, keterangan, jumlah, tanggal) VALUES ('$nama_warga', '$jenis_kas', '$keterangan', '$jumlah_final', '$tanggal')";
        $eksekusi = mysqli_query($koneksi, $sql);

        if (!$eksekusi) {
            echo '<h3>Gagal menyimpan transaksi: ' . mysqli_error($koneksi) . '</h3>';
            exit();
        }

        $id_keuangan = mysqli_insert_id($koneksi);
        $sql_bukti = "INSERT INTO bukti_kas (id_keuangan, filename) VALUES ('" . intval($id_keuangan) . "', '" . mysqli_real_escape_string($koneksi, $filename) . "')";
        mysqli_query($koneksi, $sql_bukti);

        // Jika tagihan, update status lunas
        if ($id_tagihan > 0) {
            mysqli_query($koneksi, "UPDATE tagihan SET status_bayar = 'lunas', tgl_update = CURRENT_TIMESTAMP WHERE id_tagihan = '$id_tagihan'");
        }

        // Update status order jika ada
        if (!empty($order_id)) {
            $now = date('Y-m-d H:i:s');
            mysqli_query($koneksi, "UPDATE transaksi_pembayaran SET status = 'success', paid_at = '$now' WHERE order_id = '$order_id'");
        }

        header('Location: input_kas.php?status=berhasil');
        exit();
    }
}

header("Location: input_kas.php");
exit();
