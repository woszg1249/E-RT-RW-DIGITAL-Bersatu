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

$query_user = mysqli_query($koneksi, "SELECT * FROM users WHERE nama = '" . mysqli_real_escape_string($koneksi, $nama_user) . "'");
$data_user = mysqli_fetch_assoc($query_user);

$role_user = strtoupper($data_user['role'] ?? 'WARGA');
$id_user = $data_user['id'] ?? 0;

// Create koperasi tables if they do not exist
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS koperasi_anggota (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    tanggal_gabung DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(30) DEFAULT 'aktif',
    FOREIGN KEY (id_user) REFERENCES users(id)
)");

mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS koperasi_simpanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    jenis ENUM('setoran','penarikan') NOT NULL,
    jumlah DECIMAL(14,2) NOT NULL,
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
    catatan TEXT,
    FOREIGN KEY (id_user) REFERENCES users(id)
)");

mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS koperasi_pinjaman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    jumlah DECIMAL(14,2) NOT NULL,
    tenor INT NOT NULL,
    bunga DECIMAL(5,2) NOT NULL,
    status ENUM('pending','disetujui','ditolak','lunas') DEFAULT 'pending',
    tanggal_pengajuan DATETIME DEFAULT CURRENT_TIMESTAMP,
    keterangan TEXT,
    FOREIGN KEY (id_user) REFERENCES users(id)
)");

mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS koperasi_cicilan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pinjaman INT NOT NULL,
    jumlah DECIMAL(14,2) NOT NULL,
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
    catatan TEXT,
    FOREIGN KEY (id_pinjaman) REFERENCES koperasi_pinjaman(id)
)");

function formatRupiah($value) {
    return 'Rp ' . number_format($value, 0, ',', '.');
}

function getTotalSimpanan($koneksi, $id_user) {
    $q = mysqli_query($koneksi, "SELECT SUM(CASE WHEN jenis='setoran' THEN jumlah WHEN jenis='penarikan' THEN -jumlah ELSE 0 END) as total FROM koperasi_simpanan WHERE id_user='" . intval($id_user) . "'");
    $d = mysqli_fetch_assoc($q);
    return floatval($d['total'] ?? 0);
}

function getTotalCicilan($koneksi, $id_pinjaman) {
    $q = mysqli_query($koneksi, "SELECT SUM(jumlah) as total FROM koperasi_cicilan WHERE id_pinjaman='" . intval($id_pinjaman) . "'");
    $d = mysqli_fetch_assoc($q);
    return floatval($d['total'] ?? 0);
}

$anggota = [];
$anggota_tersedia = [];
$simpanan = [];
$pinjaman = [];
$cicilan = [];
$riwayat_simpanan = [];
$riwayat_pinjaman = [];
$riwayat_cicilan = [];
$anggota_sendiri = null;

if ($role_user === 'ADMIN') {
    $anggota_query = mysqli_query($koneksi, "SELECT ka.*, u.nama, u.email, u.no_hp FROM koperasi_anggota ka LEFT JOIN users u ON ka.id_user = u.id ORDER BY ka.tanggal_gabung DESC");
    while ($row = mysqli_fetch_assoc($anggota_query)) {
        $anggota[] = $row;
    }

    $anggota_tersedia_query = mysqli_query($koneksi, "SELECT * FROM users WHERE id NOT IN (SELECT id_user FROM koperasi_anggota)");
    while ($row = mysqli_fetch_assoc($anggota_tersedia_query)) {
        $anggota_tersedia[] = $row;
    }

    $simpanan_query = mysqli_query($koneksi, "SELECT ks.*, u.nama FROM koperasi_simpanan ks LEFT JOIN users u ON ks.id_user = u.id ORDER BY ks.tanggal DESC");
    while ($row = mysqli_fetch_assoc($simpanan_query)) {
        $simpanan[] = $row;
    }

    $pinjaman_query = mysqli_query($koneksi, "SELECT kp.*, u.nama FROM koperasi_pinjaman kp LEFT JOIN users u ON kp.id_user = u.id ORDER BY kp.tanggal_pengajuan DESC");
    while ($row = mysqli_fetch_assoc($pinjaman_query)) {
        $row['dibayar'] = getTotalCicilan($koneksi, $row['id']);
        $pinjaman[] = $row;
    }

    $cicilan_query = mysqli_query($koneksi, "SELECT kc.*, kp.jumlah as jumlah_pinjaman, u.nama FROM koperasi_cicilan kc LEFT JOIN koperasi_pinjaman kp ON kc.id_pinjaman = kp.id LEFT JOIN users u ON kp.id_user = u.id ORDER BY kc.tanggal DESC");
    while ($row = mysqli_fetch_assoc($cicilan_query)) {
        $cicilan[] = $row;
    }
} else {
    $anggota_sendiri_query = mysqli_query($koneksi, "SELECT * FROM koperasi_anggota WHERE id_user='" . intval($id_user) . "' LIMIT 1");
    $anggota_sendiri = mysqli_fetch_assoc($anggota_sendiri_query);

    $riwayat_simpanan_query = mysqli_query($koneksi, "SELECT * FROM koperasi_simpanan WHERE id_user='" . intval($id_user) . "' ORDER BY tanggal DESC");
    while ($row = mysqli_fetch_assoc($riwayat_simpanan_query)) {
        $riwayat_simpanan[] = $row;
    }

    $riwayat_pinjaman_query = mysqli_query($koneksi, "SELECT * FROM koperasi_pinjaman WHERE id_user='" . intval($id_user) . "' ORDER BY tanggal_pengajuan DESC");
    while ($row = mysqli_fetch_assoc($riwayat_pinjaman_query)) {
        $row['dibayar'] = getTotalCicilan($koneksi, $row['id']);
        $riwayat_pinjaman[] = $row;
    }

    $riwayat_cicilan_query = mysqli_query($koneksi, "SELECT kc.*, kp.jumlah as jumlah_pinjaman, kp.status FROM koperasi_cicilan kc LEFT JOIN koperasi_pinjaman kp ON kc.id_pinjaman = kp.id WHERE kp.id_user='" . intval($id_user) . "' ORDER BY kc.tanggal DESC");
    while ($row = mysqli_fetch_assoc($riwayat_cicilan_query)) {
        $riwayat_cicilan[] = $row;
    }
}
$title = 'Koperasi RT - RT RW Digital Bersatu';
include '../includes/header.php';
?>
<style>
        .koperasi-panel { background: #ffffff; padding: 24px; border-radius: 18px; box-shadow: 0 12px 30px rgba(0,0,0,0.06); margin-bottom: 24px; }
        .koperasi-panel h2 { margin-bottom: 18px; color: #1f3d7a; }
        .koperasi-grid { display: grid; gap: 20px; }
        .koperasi-row { display: flex; flex-wrap: wrap; gap: 18px; }
        .koperasi-card { background: #f7fbff; border: 1px solid #d6e4f2; border-radius: 14px; padding: 18px; flex: 1; min-width: 220px; }
        .koperasi-card strong { display: block; margin-bottom: 8px; color: #1f3d7a; }
        .table-responsive { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .table th, .table td { padding: 12px 14px; border: 1px solid #e7ecf1; font-size: 0.9rem; }
        .table th { background: #eef5fc; text-align: left; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 700; color: #2c3e50; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #d4dce6; border-radius: 10px; font-size: 0.95rem; }
        .form-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 10px; }
        .btn-primary, .btn-secondary, .btn-danger { border: none; border-radius: 10px; padding: 10px 16px; font-weight: 600; cursor: pointer; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-secondary { background: #f3f4f6; color: #111827; }
        .btn-danger { background: #dc2626; color: white; }
        .status-pill { display: inline-flex; padding: 5px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-disetujui { background: #d1fae5; color: #065f46; }
        .status-ditolak { background: #fee2e2; color: #991b1b; }
        .status-lunas { background: #dbeafe; color: #1e40af; }
        .action-buttons { display: flex; flex-wrap: wrap; gap: 8px; }
        .hidden { display: none; }
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
                    <strong style="color: var(--text);">Koperasi RT</strong>
                </div>
            </div>
            <div class="app-topbar__actions">
                <span class="order-pill" style="color:var(--text-soft); font-weight:700;">Role: <?php echo htmlspecialchars($role_user); ?></span>
            </div>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-hand-holding-dollar" style="color: var(--primary);"></i> Koperasi RT RW Digital</h1>
                    <p style="margin: 6px 0 0; color: var(--text-soft);">Kelola simpanan, pengajuan pinjaman, dan keanggotaan koperasi warga.</p>
                </div>
            </div>
<div class="koperasi-panel">
                    <div class="koperasi-row">
                        <div class="koperasi-card">
                            <strong>Keanggotaan</strong>
                            <?php if ($role_user === 'ADMIN'): ?>
                                <p>Admin dapat mengelola anggota koperasi, menyimpan catatan simpanan, dan memproses pinjaman.</p>
                            <?php else: ?>
                                <p>Status anggota koperasi Anda:</p>
                                <p><strong><?php echo $anggota_sendiri ? 'Aktif' : 'Belum Terdaftar'; ?></strong></p>
                                <?php if ($anggota_sendiri): ?>
                                    <p>Tanggal bergabung: <?php echo date('d/m/Y H:i', strtotime($anggota_sendiri['tanggal_gabung'])); ?></p>
                                <?php else: ?>
                                    <button class="btn-primary" onclick="submitKoperasiAction('join_member', { })">Gabung Koperasi</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="koperasi-card">
                            <strong>Ringkasan</strong>
                            <?php if ($role_user === 'ADMIN'): ?>
                                <p>Jumlah anggota: <?php echo count($anggota); ?></p>
                                <p>Total simpanan: <?php echo formatRupiah(array_sum(array_map(function($item) { return $item['jenis'] === 'setoran' ? floatval($item['jumlah']) : -floatval($item['jumlah']); }, $simpanan))); ?></p>
                                <p>Pinjaman tertunda: <?php echo count(array_filter($pinjaman, fn($row) => $row['status'] === 'pending')); ?></p>
                            <?php else: ?>
                                <p>Total simpanan Anda: <?php echo formatRupiah(getTotalSimpanan($koneksi, $id_user)); ?></p>
                                <p>Pinjaman Anda: <?php echo count($riwayat_pinjaman); ?> catatan</p>
                                <p>Cicilan Anda: <?php echo count($riwayat_cicilan); ?> pembayaran</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($role_user === 'ADMIN'): ?>
                    <div class="koperasi-panel">
                        <h2>Manajemen Anggota Koperasi</h2>
                        <div class="form-group">
                            <label>Tambah Anggota dari Warga</label>
                            <select id="anggota_baru_id">
                                <option value="">-- Pilih Warga --</option>
                                <?php foreach ($anggota_tersedia as $warga): ?>
                                    <option value="<?php echo intval($warga['id']); ?>"><?php echo htmlspecialchars($warga['nama'] . ' (' . $warga['email'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn-primary" onclick="submitKoperasiAction('add_member', { id_user: document.getElementById('anggota_baru_id').value })">Tambah Anggota</button>

                        <div class="table-responsive">
                            <table class="table" style="margin-top:20px;">
                                <thead>
                                    <tr>
                                        <th>Nama Anggota</th>
                                        <th>Email</th>
                                        <th>No. HP</th>
                                        <th>Tanggal Gabung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($anggota as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['nama']); ?></td>
                                            <td><?php echo htmlspecialchars($item['email']); ?></td>
                                            <td><?php echo htmlspecialchars($item['no_hp']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal_gabung'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="koperasi-panel">
                        <h2>Pencatatan Simpanan</h2>
                        <div class="form-group">
                            <label>Pilih Anggota</label>
                            <select id="simpanan_user_id">
                                <option value="">-- Pilih Anggota --</option>
                                <?php foreach ($anggota as $item): ?>
                                    <option value="<?php echo intval($item['id_user']); ?>"><?php echo htmlspecialchars($item['nama']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Jenis Simpanan</label>
                            <select id="jenis_simpanan">
                                <option value="setoran">Setoran</option>
                                <option value="penarikan">Penarikan</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Jumlah</label>
                            <input type="number" id="jumlah_simpanan" step="0.01" min="0" placeholder="Masukkan jumlah" />
                        </div>
                        <div class="form-group">
                            <label>Catatan</label>
                            <textarea id="catatan_simpanan" rows="3" placeholder="Catatan simpanan"></textarea>
                        </div>
                        <button class="btn-primary" onclick="submitKoperasiAction('add_simpanan', {
                            id_user: document.getElementById('simpanan_user_id').value,
                            jenis: document.getElementById('jenis_simpanan').value,
                            jumlah: document.getElementById('jumlah_simpanan').value,
                            catatan: document.getElementById('catatan_simpanan').value
                        })">Simpan Catatan Simpanan</button>

                        <div class="table-responsive">
                            <table class="table" style="margin-top:20px;">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Jenis</th>
                                        <th>Jumlah</th>
                                        <th>Catatan</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($simpanan as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['nama']); ?></td>
                                            <td><?php echo htmlspecialchars($item['jenis']); ?></td>
                                            <td><?php echo formatRupiah($item['jumlah']); ?></td>
                                            <td><?php echo htmlspecialchars($item['catatan']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="koperasi-panel">
                        <h2>Pengelolaan Pinjaman</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Jumlah</th>
                                        <th>Tenor</th>
                                        <th>Bunga</th>
                                        <th>Status</th>
                                        <th>Dibayar</th>
                                        <th>Tanggal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pinjaman as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['nama']); ?></td>
                                            <td><?php echo formatRupiah($item['jumlah']); ?></td>
                                            <td><?php echo intval($item['tenor']); ?> bulan</td>
                                            <td><?php echo floatval($item['bunga']); ?>%</td>
                                            <td><span class="status-pill status-<?php echo htmlspecialchars($item['status']); ?>"><?php echo htmlspecialchars($item['status']); ?></span></td>
                                            <td><?php echo formatRupiah($item['dibayar']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal_pengajuan'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($item['status'] === 'pending'): ?>
                                                        <button class="btn-secondary" onclick="submitKoperasiAction('approve_loan', { id: <?php echo intval($item['id']); ?> })">Setujui</button>
                                                        <button class="btn-danger" onclick="submitKoperasiAction('reject_loan', { id: <?php echo intval($item['id']); ?> })">Tolak</button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="koperasi-panel">
                        <h2>Riwayat Cicilan</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Jumlah Cicilan</th>
                                        <th>Catatan</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cicilan as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['nama']); ?></td>
                                            <td><?php echo formatRupiah($item['jumlah']); ?></td>
                                            <td><?php echo htmlspecialchars($item['catatan']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="koperasi-panel">
                        <h2>Riwayat Simpanan Saya</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Jenis</th>
                                        <th>Jumlah</th>
                                        <th>Catatan</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($riwayat_simpanan) === 0): ?>
                                        <tr><td colspan="4" style="text-align:center;">Belum ada riwayat simpanan</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($riwayat_simpanan as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['jenis']); ?></td>
                                            <td><?php echo formatRupiah($item['jumlah']); ?></td>
                                            <td><?php echo htmlspecialchars($item['catatan']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="koperasi-panel">
                        <h2>Pengajuan Pinjaman</h2>
                        <?php if ($anggota_sendiri): ?>
                            <div class="form-group">
                                <label>Jumlah Pinjaman</label>
                                <input type="number" id="pinjaman_jumlah" step="0.01" min="0" placeholder="Masukkan jumlah pinjaman" />
                            </div>
                            <div class="form-group">
                                <label>Tenor (bulan)</label>
                                <input type="number" id="pinjaman_tenor" min="1" placeholder="Masukkan tenor pinjaman" />
                            </div>
                            <div class="form-group">
                                <label>Bunga (%)</label>
                                <input type="number" id="pinjaman_bunga" step="0.01" min="0" value="1.5" />
                            </div>
                            <div class="form-group">
                                <label>Tujuan Pinjaman</label>
                                <textarea id="pinjaman_keterangan" rows="3" placeholder="Jelaskan tujuan pinjaman"></textarea>
                            </div>
                            <button class="btn-primary" onclick="submitKoperasiAction('request_loan', {
                                jumlah: document.getElementById('pinjaman_jumlah').value,
                                tenor: document.getElementById('pinjaman_tenor').value,
                                bunga: document.getElementById('pinjaman_bunga').value,
                                keterangan: document.getElementById('pinjaman_keterangan').value
                            })">Ajukan Pinjaman</button>
                        <?php else: ?>
                            <p>Untuk mengajukan pinjaman, Anda harus terlebih dahulu menjadi anggota koperasi.</p>
                        <?php endif; ?>
                        <div class="table-responsive" style="margin-top:20px;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Jumlah</th>
                                        <th>Tenor</th>
                                        <th>Status</th>
                                        <th>Telah Dibayar</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($riwayat_pinjaman) === 0): ?>
                                        <tr><td colspan="5" style="text-align:center;">Belum ada riwayat pinjaman</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($riwayat_pinjaman as $item): ?>
                                        <tr>
                                            <td><?php echo formatRupiah($item['jumlah']); ?></td>
                                            <td><?php echo intval($item['tenor']); ?> bulan</td>
                                            <td><span class="status-pill status-<?php echo htmlspecialchars($item['status']); ?>"><?php echo htmlspecialchars($item['status']); ?></span></td>
                                            <td><?php echo formatRupiah($item['dibayar']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal_pengajuan'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="koperasi-panel">
                        <h2>Riwayat Cicilan Saya</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Jumlah Cicilan</th>
                                        <th>Status Pinjaman</th>
                                        <th>Catatan</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($riwayat_cicilan) === 0): ?>
                                        <tr><td colspan="4" style="text-align:center;">Belum ada cicilan</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($riwayat_cicilan as $item): ?>
                                        <tr>
                                            <td><?php echo formatRupiah($item['jumlah']); ?></td>
                                            <td><span class="status-pill status-<?php echo htmlspecialchars($item['status']); ?>"><?php echo htmlspecialchars($item['status']); ?></span></td>
                                            <td><?php echo htmlspecialchars($item['catatan']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                    </main>
    </div>
</div>

<script>
        function submitKoperasiAction(action, payload) {
            const params = new URLSearchParams();
            params.append('action', action);

            const formData = new FormData();
            for (const key in payload) {
                formData.append(key, payload[key]);
            }

            fetch('proses_koperasi.php?' + params.toString(), {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error(error);
                alert('Terjadi kesalahan saat menyimpan data koperasi');
            });
        }
    </script>

<?php include '../includes/footer.php'; ?>
