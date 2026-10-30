<?php
include "../Koneksi.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nama'])) {
    header("Location: ../folder login,logout dan register/login.php");
    exit();
}

$nama_user = $_SESSION['nama'];
$role_user = strtoupper($_SESSION['role'] ?? 'WARGA');
$id_user = intval($_SESSION['id'] ?? 0);

$query_user = mysqli_query($koneksi, "SELECT * FROM users WHERE nama = '" . mysqli_real_escape_string($koneksi, $nama_user) . "'");
$data_user = mysqli_fetch_assoc($query_user);
$foto_db = $data_user['foto'] ?? '';

if (!empty($foto_db) && file_exists("../img/" . $foto_db)) {
    $tampilan_foto = "../img/" . $foto_db;
} else {
    $tampilan_foto = "https://ui-avatars.com/api/?name=" . urlencode($nama_user) . "&background=4e73df&color=fff";
}

if (!mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS aset (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(255) NOT NULL,
    deskripsi TEXT,
    kategori VARCHAR(100) DEFAULT 'Umum',
    kondisi VARCHAR(100) DEFAULT 'Baik',
    lokasi VARCHAR(255) DEFAULT '-',
    jumlah INT DEFAULT 1,
    status VARCHAR(50) DEFAULT 'Aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
    die('Gagal membuat tabel aset: ' . mysqli_error($koneksi));
}

if (!mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS peminjaman_aset (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_aset INT NOT NULL,
    id_user INT NOT NULL,
    jumlah_pinjam INT NOT NULL DEFAULT 1,
    tanggal_pinjam DATE NOT NULL,
    tanggal_kembali DATE DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'Menunggu',
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_aset) REFERENCES aset(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
    die('Gagal membuat tabel peminjaman_aset: ' . mysqli_error($koneksi));
}

$flash_message = $_SESSION['aset_flash'] ?? '';
unset($_SESSION['aset_flash']);
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_asset' && $role_user === 'ADMIN') {
        $nama_aset = mysqli_real_escape_string($koneksi, trim($_POST['nama_aset'] ?? ''));
        $deskripsi = mysqli_real_escape_string($koneksi, trim($_POST['deskripsi_aset'] ?? ''));
        $kategori = mysqli_real_escape_string($koneksi, trim($_POST['kategori_aset'] ?? 'Umum'));
        $kondisi = mysqli_real_escape_string($koneksi, trim($_POST['kondisi_aset'] ?? 'Baik'));
        $lokasi = mysqli_real_escape_string($koneksi, trim($_POST['lokasi_aset'] ?? '-'));
        $jumlah = max(1, intval($_POST['jumlah_aset'] ?? 1));
        $status = mysqli_real_escape_string($koneksi, trim($_POST['status_aset'] ?? 'Aktif'));

        if ($nama_aset === '') {
            $error_message = 'Nama aset tidak boleh kosong.';
        } else {
            $insert = "INSERT INTO aset (nama, deskripsi, kategori, kondisi, lokasi, jumlah, status) VALUES ('" . $nama_aset . "', '" . $deskripsi . "', '" . $kategori . "', '" . $kondisi . "', '" . $lokasi . "', '" . $jumlah . "', '" . $status . "')";
            if (mysqli_query($koneksi, $insert)) {
                $_SESSION['aset_flash'] = 'Aset berhasil ditambahkan.';
                header('Location: aset.php');
                exit();
            }
            $error_message = 'Gagal menyimpan aset: ' . mysqli_error($koneksi);
        }
    }

    if ($action === 'edit_asset' && $role_user === 'ADMIN') {
        $id_aset = intval($_POST['aset_id'] ?? 0);
        $nama_aset = mysqli_real_escape_string($koneksi, trim($_POST['nama_aset'] ?? ''));
        $deskripsi = mysqli_real_escape_string($koneksi, trim($_POST['deskripsi_aset'] ?? ''));
        $kategori = mysqli_real_escape_string($koneksi, trim($_POST['kategori_aset'] ?? 'Umum'));
        $kondisi = mysqli_real_escape_string($koneksi, trim($_POST['kondisi_aset'] ?? 'Baik'));
        $lokasi = mysqli_real_escape_string($koneksi, trim($_POST['lokasi_aset'] ?? '-'));
        $jumlah = max(1, intval($_POST['jumlah_aset'] ?? 1));
        $status = mysqli_real_escape_string($koneksi, trim($_POST['status_aset'] ?? 'Aktif'));

        if ($id_aset <= 0 || $nama_aset === '') {
            $error_message = 'Data aset tidak valid.';
        } else {
            $update = "UPDATE aset SET nama='" . $nama_aset . "', deskripsi='" . $deskripsi . "', kategori='" . $kategori . "', kondisi='" . $kondisi . "', lokasi='" . $lokasi . "', jumlah='" . $jumlah . "', status='" . $status . "' WHERE id='" . $id_aset . "'";
            if (mysqli_query($koneksi, $update)) {
                $_SESSION['aset_flash'] = 'Aset berhasil diperbarui.';
                header('Location: aset.php');
                exit();
            }
            $error_message = 'Gagal memperbarui aset: ' . mysqli_error($koneksi);
        }
    }

    if ($action === 'delete_asset' && $role_user === 'ADMIN') {
        $id_aset = intval($_POST['aset_id'] ?? 0);
        if ($id_aset > 0) {
            $delete = "DELETE FROM aset WHERE id='" . $id_aset . "'";
            if (mysqli_query($koneksi, $delete)) {
                $_SESSION['aset_flash'] = 'Aset berhasil dihapus.';
                header('Location: aset.php');
                exit();
            }
            $error_message = 'Gagal menghapus aset: ' . mysqli_error($koneksi);
        }
    }

    if ($action === 'request_asset' && $role_user !== 'ADMIN') {
        $id_aset = intval($_POST['aset_id'] ?? 0);
        $jumlah_pinjam = max(1, intval($_POST['jumlah_pinjam'] ?? 1));
        $tanggal_pinjam = mysqli_real_escape_string($koneksi, trim($_POST['tanggal_pinjam'] ?? ''));
        $tanggal_kembali = mysqli_real_escape_string($koneksi, trim($_POST['tanggal_kembali'] ?? ''));
        $catatan = mysqli_real_escape_string($koneksi, trim($_POST['catatan_pinjam'] ?? ''));

        if ($id_aset <= 0 || $tanggal_pinjam === '') {
            $error_message = 'Lengkapi data permohonan pinjaman.';
        } else {
            $asset_check = mysqli_query($koneksi, "SELECT jumlah, status FROM aset WHERE id='" . $id_aset . "'");
            $asset_data = mysqli_fetch_assoc($asset_check);
            if (!$asset_data || $asset_data['status'] !== 'Aktif' || intval($asset_data['jumlah']) < $jumlah_pinjam) {
                $error_message = 'Aset tidak tersedia atau jumlah pinjam melebihi stok.';
            } else {
                $insert = "INSERT INTO peminjaman_aset (id_aset, id_user, jumlah_pinjam, tanggal_pinjam, tanggal_kembali, catatan) VALUES ('" . $id_aset . "', '" . $id_user . "', '" . $jumlah_pinjam . "', '" . $tanggal_pinjam . "', " . ($tanggal_kembali ? "'" . $tanggal_kembali . "'" : "NULL") . ", '" . $catatan . "')";
                if (mysqli_query($koneksi, $insert)) {
                    $_SESSION['aset_flash'] = 'Permohonan pinjaman aset berhasil dikirim. Tunggu persetujuan RT/RW.';
                    header('Location: aset.php');
                    exit();
                }
                $error_message = 'Gagal mengajukan pinjaman: ' . mysqli_error($koneksi);
            }
        }
    }

    if ($role_user === 'ADMIN' && in_array($action, ['approve_request', 'reject_request', 'return_request'])) {
        $request_id = intval($_POST['request_id'] ?? 0);
        $request_check = mysqli_query($koneksi, "SELECT pa.*, a.jumlah AS aset_jumlah FROM peminjaman_aset pa LEFT JOIN aset a ON pa.id_aset = a.id WHERE pa.id='" . $request_id . "'");
        $request_data = mysqli_fetch_assoc($request_check);

        if (!$request_data) {
            $error_message = 'Permohonan tidak ditemukan.';
        } else {
            if ($action === 'approve_request') {
                if ($request_data['status'] !== 'Menunggu') {
                    $error_message = 'Permohonan sudah diproses.';
                } elseif (intval($request_data['aset_jumlah']) < intval($request_data['jumlah_pinjam'])) {
                    $error_message = 'Stok aset tidak mencukupi untuk persetujuan.';
                } else {
                    mysqli_query($koneksi, "UPDATE peminjaman_aset SET status='Disetujui' WHERE id='" . $request_id . "'");
                    mysqli_query($koneksi, "UPDATE aset SET jumlah=jumlah - '" . intval($request_data['jumlah_pinjam']) . "' WHERE id='" . intval($request_data['id_aset']) . "'");
                    $_SESSION['aset_flash'] = 'Permohonan berhasil disetujui.';
                    header('Location: aset.php');
                    exit();
                }
            }

            if ($action === 'reject_request') {
                if ($request_data['status'] !== 'Menunggu') {
                    $error_message = 'Permohonan sudah diproses.';
                } else {
                    mysqli_query($koneksi, "UPDATE peminjaman_aset SET status='Ditolak' WHERE id='" . $request_id . "'");
                    $_SESSION['aset_flash'] = 'Permohonan telah ditolak.';
                    header('Location: aset.php');
                    exit();
                }
            }

            if ($action === 'return_request') {
                if ($request_data['status'] !== 'Disetujui') {
                    $error_message = 'Hanya peminjaman yang disetujui yang dapat dikembalikan.';
                } else {
                    mysqli_query($koneksi, "UPDATE peminjaman_aset SET status='Dikembalikan' WHERE id='" . $request_id . "'");
                    mysqli_query($koneksi, "UPDATE aset SET jumlah=jumlah + '" . intval($request_data['jumlah_pinjam']) . "' WHERE id='" . intval($request_data['id_aset']) . "'");
                    $_SESSION['aset_flash'] = 'Status pengembalian aset berhasil dicatat.';
                    header('Location: aset.php');
                    exit();
                }
            }
        }
    }
}

$assets = [];
$result_aset = mysqli_query($koneksi, "SELECT * FROM aset ORDER BY nama ASC");
while ($row = mysqli_fetch_assoc($result_aset)) {
    $assets[] = $row;
}

$requests = [];
if ($role_user === 'ADMIN') {
    $result_req = mysqli_query($koneksi, "SELECT pa.*, a.nama AS aset_nama, a.kategori AS aset_kategori, u.nama AS peminjam_nama FROM peminjaman_aset pa LEFT JOIN aset a ON pa.id_aset = a.id LEFT JOIN users u ON pa.id_user = u.id ORDER BY pa.created_at DESC");
} else {
    $result_req = mysqli_query($koneksi, "SELECT pa.*, a.nama AS aset_nama, a.kategori AS aset_kategori FROM peminjaman_aset pa LEFT JOIN aset a ON pa.id_aset = a.id WHERE pa.id_user='" . $id_user . "' ORDER BY pa.created_at DESC");
}
while ($row = mysqli_fetch_assoc($result_req)) {
    $requests[] = $row;
}

function status_badge($status) {
    $color = '#e2e8f0';
    $text = '#334155';
    switch ($status) {
        case 'Disetujui': $color = '#dcfce7'; $text = '#166534'; break;
        case 'Menunggu': $color = '#fef9c3'; $text = '#92400e'; break;
        case 'Ditolak': $color = '#fee2e2'; $text = '#991b1b'; break;
        case 'Dikembalikan': $color = '#dbeafe'; $text = '#1e3a8a'; break;
    }
    return '<span style="display:inline-block;padding:6px 12px;border-radius:999px;background:' . $color . ';color:' . $text . ';font-size:0.9rem;font-weight:700;">' . htmlspecialchars($status) . '</span>';
}


$title = 'Aset RT - RT RW Digital Bersatu';
include '../includes/header.php';
?>
<style>
.section-card { background: #ffffff; padding: 24px; border-radius: 18px; box-shadow: 0 14px 40px rgb(16 78 139 / 8%); margin-bottom: 24px; }
.section-card h2 { margin-top: 0; }
.data-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
.data-table th, .data-table td { padding: 14px 12px; border: 1px solid #e2e8f0; text-align: left; vertical-align: middle; }
.data-table th { background: #f8fafc; color: #0f172a; font-weight: 700; }
.data-table tbody tr:hover { background: #f8fafc; }
.btn-primary, .btn-secondary, .btn-danger { border: none; cursor: pointer; border-radius: 10px; padding: 10px 16px; font-weight: 700; }
.btn-primary { background: #2563eb; color: #fff; }
.btn-secondary { background: #f3f4f6; color: #111827; }
.btn-danger { background: #dc2626; color: #fff; }
.btn-small { padding: 8px 12px; font-size: 0.95rem; }
.badge-active { background: #d1fae5; color: #065f46; padding: 6px 12px; border-radius: 999px; font-weight: 700; }
.badge-unavailable { background: #fee2e2; color: #991b1b; padding: 6px 12px; border-radius: 999px; font-weight: 700; }
.modal { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); z-index: 999; align-items: center; justify-content: center; padding: 18px; }
.modal.active { display: flex; }
.modal-content { width: 100%; max-width: 720px; background: #ffffff; border-radius: 18px; padding: 24px; position: relative; box-shadow: 0 24px 80px rgba(15,23,42,0.18); }
.close-btn { position: absolute; right: 18px; top: 18px; background: #f3f4f6; border: none; border-radius: 10px; width: 36px; height: 36px; cursor: pointer; font-size: 18px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 700; color: #0f172a; }
.form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 12px; background: #f8fafc; font-size: 1rem; color: #0f172a; }
.form-row { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 18px; }
.form-row .form-group { flex: 1; min-width: 220px; }
.form-actions { margin-top: 18px; display: flex; flex-wrap: wrap; gap: 12px; }
.info-box { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-top: 16px; }
.info-card { background: #f8fafc; border: 1px solid #e2e8f0; padding: 18px; border-radius: 16px; }
@media (max-width: 840px) { .form-row { flex-direction: column; } .info-box { grid-template-columns: 1fr; }}
</style>

<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>

    <div class="app-main">
        <header class="app-topbar">
            <div class="app-topbar__left">
                <button id="toggle-sidebar" class="app-topbar__toggle" aria-label="Toggle menu" aria-expanded="true">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <strong style="color: var(--text);">Aset RT</strong>
                </div>
            </div>
            <div class="app-topbar__actions">
                <?php if ($role_user === 'ADMIN'): ?>
                    <button class="primary-btn" onclick="openAssetModal()">
                        <i class="fa-solid fa-plus"></i> Tambah Aset Baru
                    </button>
                <?php endif; ?>
            </div>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-boxes-stacked" style="color: var(--primary);"></i> Pengelolaan Aset RT</h1>
                    <p style="margin: 6px 0 0; color: var(--text-soft);">Inventaris barang, fasilitas, dan peminjaman aset RT oleh warga.</p>
                </div>
            </div>
<?php if (!empty($flash_message)): ?>
                    <div class="section-card" style="border:1px solid #b7f3d0;background:#ecfdf5;color:#164e36;">
                        <?php echo htmlspecialchars($flash_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="section-card" style="border:1px solid #fecaca;background:#fef2f2;color:#991b1b;">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($role_user === 'ADMIN'): ?>
                    <div class="section-card">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                            <div>
                                <h2>Data Aset RT</h2>
                                <p>Kelola aset milik RT yang dapat dipinjam warga, termasuk stok, lokasi, dan kondisi.</p>
                            </div>
                            <button class="btn-primary" onclick="openAssetModal()"><i class="fa-solid fa-plus"></i> Tambah Aset</button>
                        </div>

                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Aset</th>
                                    <th>Kategori</th>
                                    <th>Kondisi</th>
                                    <th>Lokasi</th>
                                    <th>Stok</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($assets) === 0): ?>
                                    <tr><td colspan="8" style="text-align:center; color:#64748b;">Belum ada aset terdaftar.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($assets as $index => $aset): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($aset['nama']); ?></td>
                                            <td><?php echo htmlspecialchars($aset['kategori']); ?></td>
                                            <td><?php echo htmlspecialchars($aset['kondisi']); ?></td>
                                            <td><?php echo htmlspecialchars($aset['lokasi']); ?></td>
                                            <td><?php echo intval($aset['jumlah']); ?></td>
                                            <td><?php echo $aset['status'] === 'Aktif' ? '<span class="badge-active">Aktif</span>' : '<span class="badge-unavailable">Tidak Aktif</span>'; ?></td>
                                            <td>
                                                <button class="btn-secondary btn-small" onclick="openAssetEdit(this)" data-aset='<?php echo htmlspecialchars(json_encode($aset, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'>Ubah</button>
                                                <form method="POST" style="display:inline-block; margin:0;">
                                                    <input type="hidden" name="action" value="delete_asset">
                                                    <input type="hidden" name="aset_id" value="<?php echo intval($aset['id']); ?>">
                                                    <button type="submit" class="btn-danger btn-small" onclick="return confirm('Hapus aset ini?')">Hapus</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="section-card">
                        <h2>Permohonan Peminjaman</h2>
                        <p>Review dan kelola permohonan peminjaman aset dari warga.</p>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Aset</th>
                                    <th>Peminjam</th>
                                    <th>Jumlah</th>
                                    <th>Tanggal Pinjam</th>
                                    <th>Tanggal Kembali</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($requests) === 0): ?>
                                    <tr><td colspan="8" style="text-align:center; color:#64748b;">Belum ada pemohonan peminjaman.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($requests as $index => $req): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($req['aset_nama']); ?></td>
                                            <td><?php echo htmlspecialchars($req['peminjam_nama'] ?? '-'); ?></td>
                                            <td><?php echo intval($req['jumlah_pinjam']); ?></td>
                                            <td><?php echo htmlspecialchars($req['tanggal_pinjam']); ?></td>
                                            <td><?php echo htmlspecialchars($req['tanggal_kembali'] ?: '-'); ?></td>
                                            <td><?php echo status_badge($req['status']); ?></td>
                                            <td>
                                                <?php if ($req['status'] === 'Menunggu'): ?>
                                                    <form method="POST" style="display:inline-block; margin:0 4px 4px 0;">
                                                        <input type="hidden" name="action" value="approve_request">
                                                        <input type="hidden" name="request_id" value="<?php echo intval($req['id']); ?>">
                                                        <button type="submit" class="btn-primary btn-small">Setujui</button>
                                                    </form>
                                                    <form method="POST" style="display:inline-block; margin:0;">
                                                        <input type="hidden" name="action" value="reject_request">
                                                        <input type="hidden" name="request_id" value="<?php echo intval($req['id']); ?>">
                                                        <button type="submit" class="btn-danger btn-small">Tolak</button>
                                                    </form>
                                                <?php elseif ($req['status'] === 'Disetujui'): ?>
                                                    <form method="POST" style="display:inline-block; margin:0;">
                                                        <input type="hidden" name="action" value="return_request">
                                                        <input type="hidden" name="request_id" value="<?php echo intval($req['id']); ?>">
                                                        <button type="submit" class="btn-secondary btn-small">Catat Kembali</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="color:#475569; font-size:0.95rem;">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="section-card">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                            <div>
                                <h2>Daftar Aset Tersedia</h2>
                                <p>Pilih aset RT yang ingin dipinjam dan ajukan permohonan.</p>
                            </div>
                        </div>

                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Aset</th>
                                    <th>Kategori</th>
                                    <th>Kondisi</th>
                                    <th>Lokasi</th>
                                    <th>Stok</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($assets) === 0): ?>
                                    <tr><td colspan="7" style="text-align:center; color:#64748b;">Belum ada aset tersedia.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($assets as $index => $aset): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($aset['nama']); ?></td>
                                            <td><?php echo htmlspecialchars($aset['kategori']); ?></td>
                                            <td><?php echo htmlspecialchars($aset['kondisi']); ?></td>
                                            <td><?php echo htmlspecialchars($aset['lokasi']); ?></td>
                                            <td><?php echo intval($aset['jumlah']); ?></td>
                                            <td>
                                                <?php if ($aset['status'] === 'Aktif' && intval($aset['jumlah']) > 0): ?>
                                                    <button class="btn-primary btn-small" onclick="openRequestModal(this)" data-aset='<?php echo htmlspecialchars(json_encode($aset, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'>Ajukan Pinjam</button>
                                                <?php else: ?>
                                                    <span class="badge-unavailable">Tidak tersedia</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="section-card">
                        <h2>Riwayat Permohonan</h2>
                        <p>Melihat status peminjaman aset Anda.</p>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Aset</th>
                                    <th>Jumlah</th>
                                    <th>Tanggal Pinjam</th>
                                    <th>Tanggal Kembali</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($requests) === 0): ?>
                                    <tr><td colspan="6" style="text-align:center; color:#64748b;">Belum ada riwayat peminjaman.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($requests as $index => $req): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($req['aset_nama']); ?></td>
                                            <td><?php echo intval($req['jumlah_pinjam']); ?></td>
                                            <td><?php echo htmlspecialchars($req['tanggal_pinjam']); ?></td>
                                            <td><?php echo htmlspecialchars($req['tanggal_kembali'] ?: '-'); ?></td>
                                            <td><?php echo status_badge($req['status']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
        </main>
    </div>
</div>

<div id="assetModal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeAssetModal()">×</button>
            <h2 id="assetModalTitle">Tambah Aset</h2>
            <form method="POST" id="assetForm">
                <input type="hidden" name="action" id="assetAction" value="add_asset">
                <input type="hidden" name="aset_id" id="aset_id" value="0">
                <div class="form-group">
                    <label>Nama Aset</label>
                    <input type="text" name="nama_aset" id="nama_aset" required>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi_aset" id="deskripsi_aset" rows="4"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Kategori</label>
                        <input type="text" name="kategori_aset" id="kategori_aset" value="Umum">
                    </div>
                    <div class="form-group">
                        <label>Kondisi</label>
                        <select name="kondisi_aset" id="kondisi_aset">
                            <option value="Baik">Baik</option>
                            <option value="Perlu Perbaikan">Perlu Perbaikan</option>
                            <option value="Rusak">Rusak</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Lokasi</label>
                        <input type="text" name="lokasi_aset" id="lokasi_aset" value="-">
                    </div>
                    <div class="form-group">
                        <label>Jumlah Stok</label>
                        <input type="number" name="jumlah_aset" id="jumlah_aset" min="1" value="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status_aset" id="status_aset">
                        <option value="Aktif">Aktif</option>
                        <option value="Tidak Aktif">Tidak Aktif</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Simpan</button>
                    <button type="button" class="btn-secondary" onclick="closeAssetModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <div id="requestModal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeRequestModal()">×</button>
            <h2 id="requestModalTitle">Ajukan Pinjaman Aset</h2>
            <form method="POST" id="requestForm">
                <input type="hidden" name="action" value="request_asset">
                <input type="hidden" name="aset_id" id="request_aset_id" value="0">
                <div class="form-group">
                    <label>Nama Aset</label>
                    <input type="text" id="request_aset_name" readonly>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Jumlah Pinjam</label>
                        <input type="number" name="jumlah_pinjam" id="jumlah_pinjam" min="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Pinjam</label>
                        <input type="date" name="tanggal_pinjam" id="tanggal_pinjam" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal Kembali</label>
                        <input type="date" name="tanggal_kembali" id="tanggal_kembali">
                    </div>
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="catatan_pinjam" id="catatan_pinjam" rows="3"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Kirim Permohonan</button>
                    <button type="button" class="btn-secondary" onclick="closeRequestModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAssetModal() {
            document.getElementById('assetModalTitle').innerText = 'Tambah Aset';
            document.getElementById('assetAction').value = 'add_asset';
            document.getElementById('aset_id').value = '0';
            document.getElementById('nama_aset').value = '';
            document.getElementById('deskripsi_aset').value = '';
            document.getElementById('kategori_aset').value = 'Umum';
            document.getElementById('kondisi_aset').value = 'Baik';
            document.getElementById('lokasi_aset').value = '-';
            document.getElementById('jumlah_aset').value = '1';
            document.getElementById('status_aset').value = 'Aktif';
            document.getElementById('assetModal').classList.add('active');
        }

        function closeAssetModal() {
            document.getElementById('assetModal').classList.remove('active');
        }

        function openAssetEdit(button) {
            const raw = button.getAttribute('data-aset');
            if (!raw) return;
            const aset = JSON.parse(raw);
            document.getElementById('assetModalTitle').innerText = 'Ubah Aset';
            document.getElementById('assetAction').value = 'edit_asset';
            document.getElementById('aset_id').value = aset.id || '0';
            document.getElementById('nama_aset').value = aset.nama || '';
            document.getElementById('deskripsi_aset').value = aset.deskripsi || '';
            document.getElementById('kategori_aset').value = aset.kategori || 'Umum';
            document.getElementById('kondisi_aset').value = aset.kondisi || 'Baik';
            document.getElementById('lokasi_aset').value = aset.lokasi || '-';
            document.getElementById('jumlah_aset').value = aset.jumlah || '1';
            document.getElementById('status_aset').value = aset.status || 'Aktif';
            document.getElementById('assetModal').classList.add('active');
        }

        function openRequestModal(button) {
            const raw = button.getAttribute('data-aset');
            if (!raw) return;
            const aset = JSON.parse(raw);
            document.getElementById('request_aset_id').value = aset.id || '0';
            document.getElementById('request_aset_name').value = aset.nama || '';
            document.getElementById('jumlah_pinjam').value = aset.jumlah > 0 ? 1 : 0;
            document.getElementById('tanggal_pinjam').value = '';
            document.getElementById('tanggal_kembali').value = '';
            document.getElementById('catatan_pinjam').value = '';
            document.getElementById('requestModal').classList.add('active');
        }

        function closeRequestModal() {
            document.getElementById('requestModal').classList.remove('active');
        }

        document.addEventListener('click', function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        });
    </script>

<?php include '../includes/footer.php'; ?>
