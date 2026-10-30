<?php
include "../Koneksi.php";

$tagihanExists = mysqli_query($koneksi, "SHOW TABLES LIKE 'tagihan'");
if (mysqli_num_rows($tagihanExists) === 0) {
    mysqli_query($koneksi, "CREATE TABLE tagihan (
        id_tagihan INT AUTO_INCREMENT PRIMARY KEY,
        id_user INT NOT NULL,
        bulan_tahun VARCHAR(50) DEFAULT NULL,
        tanggal_tagihan DATE DEFAULT NULL,
        jumlah DECIMAL(15,2) NOT NULL DEFAULT 0,
        status_bayar VARCHAR(30) DEFAULT 'belum',
        tgl_buat DATETIME DEFAULT CURRENT_TIMESTAMP,
        tgl_update DATETIME NULL
    )");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nama'])) {
    header("Location: ../folder login,logout dan register/login.php");
    exit();
}

if (strtoupper($_SESSION['role'] ?? 'WARGA') !== 'ADMIN') {
    echo "<script>alert('Akses Ditolak! Anda bukan Admin.'); window.location='dashboard.php';</script>";
    exit();
}

$nama_user = $_SESSION['nama'];
$role_user = $_SESSION['role'] ?? 'ADMIN';

$form_error = '';
$form_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_tagihan'])) {
    $id_user = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
    $bulan_tahun = trim($_POST['bulan_tahun'] ?? '');
    $tanggal_tagihan = trim($_POST['tanggal_tagihan'] ?? '');
    $jumlah = isset($_POST['jumlah']) ? (float)$_POST['jumlah'] : 0;
    $status_bayar = trim($_POST['status_bayar'] ?? 'belum');

    if (!empty($bulan_tahun)) {
        $dateObj = DateTime::createFromFormat('Y-m', $bulan_tahun);
        if ($dateObj) {
            $bulan_tahun = $dateObj->format('F Y');
        }
    }

    if ($id_user > 0 && $bulan_tahun !== '' && $tanggal_tagihan !== '' && $jumlah >= 0) {
        $sql = "INSERT INTO tagihan (id_user, bulan_tahun, tanggal_tagihan, jumlah, status_bayar, tgl_buat)
                VALUES ('$id_user', '" . mysqli_real_escape_string($koneksi, $bulan_tahun) . "', '" . mysqli_real_escape_string($koneksi, $tanggal_tagihan) . "', '$jumlah', '" . mysqli_real_escape_string($koneksi, $status_bayar) . "', NOW())";

        if (mysqli_query($koneksi, $sql)) {
            header("Location: kelola_tagihan.php?success=1");
            exit();
        } else {
            $form_error = "Gagal menyimpan tagihan: " . mysqli_error($koneksi);
        }
    } else {
        $form_error = "Semua field harus diisi dengan benar.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id_tagihan = isset($_POST['id_tagihan']) ? (int)$_POST['id_tagihan'] : 0;
    $status_bayar = trim($_POST['status_bayar'] ?? 'belum');

    if ($id_tagihan > 0) {
        $sql = "UPDATE tagihan SET status_bayar = '" . mysqli_real_escape_string($koneksi, $status_bayar) . "', tgl_update = NOW() WHERE id_tagihan = '$id_tagihan'";
        if (!mysqli_query($koneksi, $sql)) {
            $form_error = "Gagal update status: " . mysqli_error($koneksi);
        } else {
            $form_success = "Status tagihan berhasil diperbarui.";
        }
    }
}

if (isset($_GET['hapus'])) {
    $id_tagihan = (int)$_GET['hapus'];
    if ($id_tagihan > 0) {
        mysqli_query($koneksi, "DELETE FROM tagihan WHERE id_tagihan = '$id_tagihan'");
        $form_success = "Tagihan berhasil dihapus.";
    }
}

if (isset($_GET['success']) && $_GET['success'] == '1') {
    $form_success = "Tagihan baru berhasil dibuat.";
}

$title = 'Kelola Tagihan Iuran Warga - RT RW Digital Bersatu';
include '../includes/header.php';
?>

<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>

    <div class="app-main">
        <header class="app-topbar">
            <div class="app-topbar__left">
                <button id="toggle-sidebar" class="app-topbar__toggle" aria-label="Toggle menu" aria-expanded="true">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <strong style="color: var(--text);">Kelola Tagihan</strong>
                </div>
            </div>
            <div class="app-topbar__actions">
                <a href="input_kas.php" class="ghost-btn" style="text-decoration:none;">
                    <i class="fa-solid fa-file-pen"></i> Input Kas RT
                </a>
            </div>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-file-invoice-dollar" style="color: var(--primary);"></i> Kelola Tagihan Iuran Warga</h1>
                    <p style="margin: 6px 0 0; color: var(--text-soft);">Buat tagihan iuran bulanan untuk warga dan pantau status pembayarannya secara langsung.</p>
                </div>
            </div>

            <?php if ($form_error !== ''): ?>
                <div class="glass-panel" style="background:#fee2e2; color:#991b1b; border:1px solid #fecaca; padding:12px 18px; margin-bottom:18px; border-radius:12px; font-weight:700;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($form_error); ?>
                </div>
            <?php endif; ?>

            <?php if ($form_success !== ''): ?>
                <div class="glass-panel" style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0; padding:12px 18px; margin-bottom:18px; border-radius:12px; font-weight:700;">
                    <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($form_success); ?>
                </div>
            <?php endif; ?>

            <!-- Form Buat Tagihan Baru -->
            <div class="module-panel" style="margin-bottom: 24px;">
                <div class="module-panel__header">
                    <i class="fa-solid fa-plus-circle" style="color:var(--primary); margin-right:8px;"></i> Buat Tagihan Baru
                </div>
                <div style="padding: 24px;">
                    <form action="" method="POST">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                            <div>
                                <label style="display:block; font-weight:700; color:var(--text); margin-bottom:6px; font-size:0.9rem;">Pilih Warga</label>
                                <select name="id_user" class="form-control" required style="width:100%; padding:10px 14px; border:1px solid var(--line); border-radius:8px; background:#fff;">
                                    <option value="">-- Pilih Warga --</option>
                                    <?php
                                    $q_warga = mysqli_query($koneksi, "SELECT id, nama FROM users WHERE role = 'Warga' ORDER BY nama ASC");
                                    while ($w = mysqli_fetch_assoc($q_warga)) {
                                        echo "<option value='{$w['id']}'>{$w['nama']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; color:var(--text); margin-bottom:6px; font-size:0.9rem;">Bulan & Tahun</label>
                                <input type="month" name="bulan_tahun" class="form-control" required value="<?php echo date('Y-m'); ?>" style="width:100%; padding:10px 14px; border:1px solid var(--line); border-radius:8px; background:#fff;">
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; color:var(--text); margin-bottom:6px; font-size:0.9rem;">Tanggal Tagihan</label>
                                <input type="date" name="tanggal_tagihan" class="form-control" required value="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:10px 14px; border:1px solid var(--line); border-radius:8px; background:#fff;">
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; color:var(--text); margin-bottom:6px; font-size:0.9rem;">Jumlah (Rp)</label>
                                <input type="number" name="jumlah" class="form-control" value="50000" required style="width:100%; padding:10px 14px; border:1px solid var(--line); border-radius:8px; background:#fff;">
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; color:var(--text); margin-bottom:6px; font-size:0.9rem;">Status Awal</label>
                                <select name="status_bayar" class="form-control" style="width:100%; padding:10px 14px; border:1px solid var(--line); border-radius:8px; background:#fff;">
                                    <option value="belum">Belum Bayar</option>
                                    <option value="lunas">Sudah Lunas</option>
                                    <option value="telat">Telat</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="simpan_tagihan" class="primary-btn">
                            <i class="fa-solid fa-paper-plane"></i> Kirim Tagihan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tabel Daftar Tagihan -->
            <div class="module-panel">
                <div class="module-panel__header">
                    <i class="fa-solid fa-list-check" style="color:var(--primary); margin-right:8px;"></i> Daftar Tagihan & Pembayaran Warga
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="text-align: center; width: 50px;">No</th>
                                <th>Nama Warga</th>
                                <th>Bulan / Tanggal</th>
                                <th style="text-align: right;">Nominal</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Aksi Update</th>
                                <th style="text-align: center; width: 60px;">Hapus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $q_tagihan = mysqli_query($koneksi, "SELECT t.*, u.nama FROM tagihan t JOIN users u ON t.id_user = u.id ORDER BY t.id_tagihan DESC");
                            
                            if (mysqli_num_rows($q_tagihan) > 0) {
                                while ($row = mysqli_fetch_assoc($q_tagihan)) {
                                    $status = strtolower($row['status_bayar']);
                            ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-soft); font-weight: 600;"><?= $no++; ?></td>
                                <td style="font-weight: 700; color: var(--text);"><?= htmlspecialchars($row['nama']); ?></td>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($row['bulan_tahun']); ?></div>
                                    <small style="color: var(--text-soft);"><?= !empty($row['tanggal_tagihan']) ? date('d-m-Y', strtotime($row['tanggal_tagihan'])) : '-'; ?></small>
                                </td>
                                <td style="text-align: right; font-weight: 800; color: var(--text);">
                                    Rp <?= number_format($row['jumlah'], 0, ',', '.'); ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($status === 'lunas'): ?>
                                        <span class="status-badge status-badge--approved">LUNAS</span>
                                    <?php elseif ($status === 'telat'): ?>
                                        <span class="status-badge status-badge--rejected">TELAT</span>
                                    <?php else: ?>
                                        <span class="status-badge status-badge--pending">BELUM</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <form action="" method="POST" style="display: inline-flex; align-items:center; gap: 6px;">
                                        <input type="hidden" name="id_tagihan" value="<?= $row['id_tagihan']; ?>">
                                        <select name="status_bayar" style="padding: 5px 8px; border-radius: 6px; border: 1px solid var(--line); font-size: 0.82rem; background:#fff;">
                                            <option value="belum" <?= ($status === 'belum') ? 'selected' : ''; ?>>Belum</option>
                                            <option value="lunas" <?= ($status === 'lunas') ? 'selected' : ''; ?>>Lunas</option>
                                            <option value="telat" <?= ($status === 'telat') ? 'selected' : ''; ?>>Telat</option>
                                        </select>
                                        <button type="submit" name="update_status" class="ghost-btn" style="padding: 5px 10px; font-size: 0.82rem;" title="Simpan Perubahan">
                                            <i class="fa-solid fa-floppy-disk" style="color: var(--primary);"></i>
                                        </button>
                                    </form>
                                </td>
                                <td style="text-align: center;">
                                    <a href="?hapus=<?= $row['id_tagihan']; ?>" onclick="return confirm('Yakin ingin menghapus tagihan ini?');" style="color: #ef4444; font-size: 1rem; padding: 6px;" title="Hapus Tagihan">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                                echo "<tr><td colspan='7' class='empty-state'>Belum ada data tagihan.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
