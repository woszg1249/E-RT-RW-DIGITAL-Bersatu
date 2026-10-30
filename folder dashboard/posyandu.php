<?php
include "../Koneksi.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nama'])) { header("Location: ../folder login,logout dan register/login.php"); exit(); }

$nama_user = $_SESSION['nama'];
$role = strtoupper($_SESSION['role'] ?? 'WARGA');

if ($role !== 'ADMIN') {
    echo "<script>alert('Akses Ditolak! Anda bukan Admin.'); window.location='dashboard.php';</script>";
    exit();
}

$query_user = mysqli_query($koneksi, "SELECT * FROM users WHERE nama = '$nama_user'");
$data_user = mysqli_fetch_assoc($query_user);
$foto_db = $data_user['foto'] ?? "";
$id_user = $data_user['id'];

if (!empty($foto_db) && file_exists("../img/" . $foto_db)) {
    $tampilan_foto = "../img/" . $foto_db;
} else {
    $tampilan_foto = "https://ui-avatars.com/api/?name=" . urlencode($nama_user) . "&background=0284C7&color=fff";
}

mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS posyandu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_warga INT,
    nama_bayi VARCHAR(100) NOT NULL,
    jenis_kelamin ENUM('Laki-laki','Perempuan') DEFAULT 'Laki-laki',
    usia_bulan INT NOT NULL,
    berat_badan DECIMAL(5, 2) NOT NULL,
    tinggi_badan DECIMAL(5, 2),
    lingkar_kepala DECIMAL(5, 2),
    status_kesehatan VARCHAR(50),
    catatan TEXT,
    tanggal_pemeriksaan DATETIME DEFAULT CURRENT_TIMESTAMP,
    pemeriksaan_oleh VARCHAR(100),
    FOREIGN KEY (id_warga) REFERENCES users(id)
)");

$check_posyandu_col = mysqli_query($koneksi, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posyandu' AND COLUMN_NAME = 'jenis_kelamin' LIMIT 1");
if (mysqli_num_rows($check_posyandu_col) === 0) {
    mysqli_query($koneksi, "ALTER TABLE posyandu ADD COLUMN jenis_kelamin ENUM('Laki-laki','Perempuan') DEFAULT 'Laki-laki'");
}

if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    if ($delete_id > 0) {
        mysqli_query($koneksi, "DELETE FROM posyandu WHERE id = '$delete_id'");
        header('Location: posyandu.php');
        exit();
    }
}

$query_posyandu = mysqli_query($koneksi, "SELECT p.*, u.nama as nama_warga, u.alamat, u.no_hp FROM posyandu p LEFT JOIN users u ON p.id_warga = u.id ORDER BY p.tanggal_pemeriksaan DESC");
$data_posyandu = [];
if ($query_posyandu) { while ($row = mysqli_fetch_assoc($query_posyandu)) { $data_posyandu[] = $row; } }

$user_table_cols = [];
$res_cols = mysqli_query($koneksi, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = 'users' AND COLUMN_NAME IN ('nik','no_kk')");
if ($res_cols) { while ($r = mysqli_fetch_assoc($res_cols)) { $user_table_cols[] = $r['COLUMN_NAME']; } }

if (!in_array('nik', $user_table_cols)) {
    @mysqli_query($koneksi, "ALTER TABLE users ADD COLUMN nik VARCHAR(16) NULL");
}
if (!in_array('no_kk', $user_table_cols)) {
    @mysqli_query($koneksi, "ALTER TABLE users ADD COLUMN no_kk VARCHAR(16) NULL");
}

$query_warga = mysqli_query($koneksi, "SELECT id, nama, no_hp, alamat, nik, no_kk FROM users WHERE role='Warga' ORDER BY nama");
$daftar_warga = [];
if ($query_warga) { while ($row = mysqli_fetch_assoc($query_warga)) { $daftar_warga[] = $row; } }

function generate_random_digits_local($seed, $length) {
    $hash = hash('sha256', $seed);
    return substr(preg_replace('/[^0-9]/', '', $hash), 0, $length);
}

$total_pemeriksaan = count($data_posyandu);
$bayi_sehat = 0;
foreach ($data_posyandu as $item) {
    if ($item['status_kesehatan'] === 'Sehat') $bayi_sehat++;
}

$title = 'Posyandu - RT RW Digital Bersatu';
include '../includes/header.php';

?>

<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="app-main">
        <header class="app-topbar">
            <div class="app-topbar__left">
                <button id="toggle-sidebar" class="app-topbar__toggle" aria-label="Toggle menu"><i class="fa-solid fa-bars"></i></button>
                <div><strong>Posyandu</strong></div>
            </div>
            <div class="app-topbar__actions">
                <button type="button" class="pwa-install-btn" style="display:none;"><i class="fa-solid fa-download"></i> Download Aplikasi</button>
            </div>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-stethoscope"></i> Posyandu</h1>
                </div>
                <div class="page-header__meta">
                    <a href="dashboard.php" class="ghost-btn" style="display:inline-flex; align-items:center; justify-content:center;">Kembali</a>
                    <button class="primary-btn" type="button" onclick="openForm()">Pemeriksaan Baru</button>
                </div>
            </div>

            <section class="stat-grid module-stats">
                <article class="stat-card">
                    <span class="stat-card__label">Total Pemeriksaan</span>
                    <h2 class="stat-card__value"><?php echo $total_pemeriksaan; ?></h2>
                </article>
                <article class="stat-card">
                    <span class="stat-card__label">Bayi Sehat</span>
                    <h2 class="stat-card__value"><?php echo $bayi_sehat; ?></h2>
                </article>
            </section>

            <section class="module-panel">
                <div class="module-panel__header">
                    Riwayat Pemeriksaan Posyandu
                </div>
                <div class="table-wrap">
                    <?php if (count($data_posyandu) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Bayi</th>
                                    <th>Jenis Kelamin</th>
                                    <th>Nama Warga</th>
                                    <th>Usia</th>
                                    <th>Berat</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_posyandu as $index => $item): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($item['nama_bayi']); ?></td>
                                        <td><?php echo htmlspecialchars($item['jenis_kelamin'] ?? 'Laki-laki'); ?></td>
                                        <td><?php echo htmlspecialchars($item['nama_warga'] ?? 'N/A'); ?></td>
                                        <td><?php echo $item['usia_bulan']; ?> bulan</td>
                                        <td><?php echo number_format($item['berat_badan'], 2); ?> kg</td>
                                        <td>
                                            <span class="status-badge status-badge--<?php echo strtolower(str_replace(' ', '-', $item['status_kesehatan'])); ?>"><?php echo htmlspecialchars($item['status_kesehatan']); ?></span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal_pemeriksaan'])); ?></td>
                                        <td>
                                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                                <button class="ghost-btn" style="padding:8px 12px; font-size:0.75rem;" type="button" onclick="printRecord(<?php echo $item['id']; ?>)">Cetak</button>
                                                <button class="ghost-btn" style="padding:8px 12px; font-size:0.75rem;" type="button" onclick="deleteRecord(<?php echo $item['id']; ?>)">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox" style="font-size:2.5rem; display:block; margin-bottom:10px;"></i>
                            Belum ada data pemeriksaan posyandu.
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</div>

<div id="posyanduModal" class="modal">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="closeForm()" aria-label="Tutup modal">×</button>
        <div class="modal-header">
            <div>
                <h2><i class="fa-solid fa-stethoscope" style="color:var(--primary);"></i> Tambah Pemeriksaan Posyandu</h2>
                <p>Pencatatan data tumbuh kembang bayi & balita sesuai standar WHO.</p>
            </div>
        </div>

        <form id="posyanduForm" onsubmit="submitPosyandu(event)">
            <div class="form-grid">
                <div class="form-field full-width">
                    <label for="posyandu_id_warga"><i class="fa-regular fa-user"></i> Nama Orang Tua / Warga</label>
                    <select id="posyandu_id_warga" name="id_warga" required>
                        <option value="">-- Pilih Orang Tua / Warga --</option>
                        <?php foreach ($daftar_warga as $w): ?>
                            <option value="<?php echo (int)$w['id']; ?>">
                                <?php echo htmlspecialchars($w['nama']); ?> <?php echo !empty($w['alamat']) ? '(' . htmlspecialchars($w['alamat']) . ')' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="side-by-side">
                    <div class="form-field">
                        <label for="nama_bayi"><i class="fa-solid fa-baby"></i> Nama Bayi / Anak</label>
                        <input type="text" id="nama_bayi" name="nama_bayi" placeholder="Nama Bayi / Anak" required>
                    </div>
                    <div class="form-field">
                        <label for="jenis_kelamin"><i class="fa-solid fa-venus-mars"></i> Jenis Kelamin</label>
                        <select id="jenis_kelamin" name="jenis_kelamin" required>
                            <option value="Laki-laki">Laki-laki</option>
                            <option value="Perempuan">Perempuan</option>
                        </select>
                    </div>
                </div>

                <div class="side-by-side">
                    <div class="form-field">
                        <label for="usia_bulan"><i class="fa-solid fa-calendar-days"></i> Usia (Bulan)</label>
                        <input type="number" id="usia_bulan" name="usia_bulan" min="0" max="60" placeholder="0 - 12 bulan" required>
                        <span class="form-hint">Standar WHO berlaku usia 0 hingga 12 bulan</span>
                    </div>
                    <div class="form-field">
                        <label for="berat_badan"><i class="fa-solid fa-weight-scale"></i> Berat Badan (kg)</label>
                        <div class="input-with-unit">
                            <input type="number" id="berat_badan" step="0.01" min="0.1" name="berat_badan" placeholder="Contoh: 4.50" required>
                            <span class="input-unit">kg</span>
                        </div>
                    </div>
                </div>

                <div class="side-by-side">
                    <div class="form-field">
                        <label for="tinggi_badan"><i class="fa-solid fa-ruler-vertical"></i> Panjang / Tinggi Badan (cm)</label>
                        <div class="input-with-unit">
                            <input type="number" id="tinggi_badan" step="0.01" min="10" name="tinggi_badan" placeholder="Contoh: 54.00">
                            <span class="input-unit">cm</span>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="lingkar_kepala"><i class="fa-solid fa-arrows-to-circle"></i> Lingkar Kepala (cm)</label>
                        <div class="input-with-unit">
                            <input type="number" id="lingkar_kepala" step="0.01" min="10" name="lingkar_kepala" placeholder="Contoh: 36.50">
                            <span class="input-unit">cm</span>
                        </div>
                    </div>
                </div>

                <!-- Live WHO Reference & Health Status Card -->
                <div class="who-guide-card">
                    <div class="who-guide-card__header">
                        <span><i class="fa-solid fa-circle-info"></i> STANDAR WHO (<span id="who_ref_label">Laki-laki, 0 Bulan</span>)</span>
                        <span id="who_ref_source" style="font-size:0.75rem; color:#0284c7; font-weight:700;">Standar Rentang Normal WHO</span>
                    </div>
                    <div class="who-guide-card__body">
                        <div class="who-stat-pill">
                            <span>Berat Ideal (BB)</span>
                            <strong id="ref_bb_text">2.5 – 3.9 kg</strong>
                        </div>
                        <div class="who-stat-pill">
                            <span>Panjang Ideal (PB)</span>
                            <strong id="ref_pb_text">46.1 – 53.7 cm</strong>
                        </div>
                        <div class="who-stat-pill">
                            <span>Lingkar Kepala (LK)</span>
                            <strong id="ref_lk_text">32.1 – 36.9 cm</strong>
                        </div>
                    </div>

                    <div id="health_status_box" class="health-status-result health-status-result--uncalculated">
                        <div class="health-status-result__icon">
                            <i id="health_status_icon" class="fa-solid fa-circle-question"></i>
                        </div>
                        <div class="health-status-result__info">
                            <div class="health-status-result__title" id="health_status_title">Belum Dihitung</div>
                            <div class="health-status-result__desc" id="health_status_desc">Masukkan Usia dan Berat Badan untuk evaluasi otomatis status kesehatan bayi.</div>
                        </div>
                    </div>
                    <input type="hidden" name="status_kesehatan" id="status_kesehatan_input" value="">
                </div>

                <div class="form-field full-width">
                    <label for="catatan"><i class="fa-regular fa-clipboard"></i> Catatan & Rekomendasi Kader / Nakes</label>
                    <textarea id="catatan" name="catatan" rows="2" placeholder="Catatan perkembangan, imunisasi, vitamin A, atau saran gizi..."></textarea>
                </div>

                <div class="form-field full-width">
                    <label for="pemeriksaan_oleh"><i class="fa-solid fa-user-check"></i> Pemeriksaan Oleh</label>
                    <input type="text" id="pemeriksaan_oleh" name="pemeriksaan_oleh" value="Admin RT" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="primary-btn"><i class="fa-solid fa-check"></i> Simpan Pemeriksaan</button>
                    <button type="button" class="ghost-btn" onclick="closeForm()">Batal</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Tabel Standar Berat Badan Normal Bayi WHO (0-12 Bulan)
    const WHO_TABLE = {
        'Laki-laki': {
            0:  { bb: [2.5, 3.9], pb: [46.1, 53.7], lk: [32.1, 36.9] },
            1:  { bb: [3.4, 5.1], pb: [50.8, 60.6], lk: [34.9, 39.6] },
            2:  { bb: [4.3, 6.3], pb: [54.4, 62.4], lk: [36.8, 41.5] },
            3:  { bb: [5.0, 7.2], pb: [57.3, 65.5], lk: [38.1, 42.9] },
            4:  { bb: [5.6, 7.8], pb: [59.7, 68.0], lk: [39.2, 44.0] },
            5:  { bb: [6.0, 8.4], pb: [61.7, 70.4], lk: [40.1, 45.0] },
            6:  { bb: [6.4, 8.8], pb: [63.3, 74.0], lk: [40.9, 45.8] },
            7:  { bb: [6.7, 9.2], pb: [64.8, 74.2], lk: [41.6, 46.5] },
            8:  { bb: [6.9, 9.6], pb: [66.2, 75.8], lk: [42.2, 47.1] },
            9:  { bb: [7.1, 9.9], pb: [67.5, 77.4], lk: [42.7, 47.6] },
            10: { bb: [7.4, 10.2], pb: [68.7, 78.9], lk: [43.1, 48.0] },
            11: { bb: [7.6, 10.5], pb: [69.9, 80.5], lk: [43.4, 48.3] },
            12: { bb: [7.7, 10.8], pb: [71.0, 82.9], lk: [43.5, 48.6] }
        },
        'Perempuan': {
            0:  { bb: [2.4, 3.7], pb: [45.4, 52.9], lk: [31.5, 36.2] },
            1:  { bb: [3.2, 4.8], pb: [49.8, 59.5], lk: [34.2, 38.9] },
            2:  { bb: [3.9, 5.8], pb: [53.2, 60.9], lk: [35.8, 40.5] },
            3:  { bb: [4.5, 6.6], pb: [55.8, 63.8], lk: [37.1, 41.9] },
            4:  { bb: [5.0, 7.3], pb: [58.0, 66.2], lk: [38.1, 43.0] },
            5:  { bb: [5.4, 7.8], pb: [59.9, 68.5], lk: [38.9, 43.9] },
            6:  { bb: [5.7, 8.2], pb: [61.2, 72.5], lk: [39.6, 44.8] },
            7:  { bb: [6.0, 8.6], pb: [62.7, 72.8], lk: [40.3, 45.5] },
            8:  { bb: [6.3, 9.0], pb: [64.0, 74.3], lk: [40.8, 46.1] },
            9:  { bb: [6.5, 9.3], pb: [65.3, 75.8], lk: [41.3, 46.6] },
            10: { bb: [6.7, 9.6], pb: [66.5, 77.4], lk: [41.7, 47.0] },
            11: { bb: [6.9, 9.9], pb: [67.7, 78.9], lk: [42.0, 47.3] },
            12: { bb: [7.0, 10.1], pb: [68.9, 81.7], lk: [42.2, 47.6] }
        }
    };

    function evaluateHealth() {
        const form = document.getElementById('posyanduForm');
        if (!form) return;

        const usiaInput = form.querySelector('input[name="usia_bulan"]');
        const beratInput = form.querySelector('input[name="berat_badan"]');
        const tinggiInput = form.querySelector('input[name="tinggi_badan"]');
        const lingkarInput = form.querySelector('input[name="lingkar_kepala"]');
        const genderInput = form.querySelector('select[name="jenis_kelamin"]');

        const gender = genderInput ? genderInput.value : 'Laki-laki';
        const usiaVal = usiaInput ? usiaInput.value.trim() : '';
        const beratVal = beratInput ? beratInput.value.trim() : '';
        const tinggiVal = tinggiInput ? parseFloat(tinggiInput.value) || 0 : 0;
        const lingkarVal = lingkarInput ? parseFloat(lingkarInput.value) || 0 : 0;

        const usia = usiaVal !== '' ? Math.max(0, parseInt(usiaVal, 10)) : 0;
        const monthKey = Math.min(12, usia);
        const ref = (WHO_TABLE[gender] && WHO_TABLE[gender][monthKey])
                    ? WHO_TABLE[gender][monthKey]
                    : WHO_TABLE['Laki-laki'][monthKey];

        // Update card preview numbers
        const refLabel = document.getElementById('who_ref_label');
        if (refLabel) refLabel.textContent = gender + ', ' + usia + ' Bulan';
        const refBB = document.getElementById('ref_bb_text');
        if (refBB) refBB.textContent = ref.bb[0].toFixed(1) + ' – ' + ref.bb[1].toFixed(1) + ' kg';
        const refPB = document.getElementById('ref_pb_text');
        if (refPB) refPB.textContent = ref.pb[0].toFixed(1) + ' – ' + ref.pb[1].toFixed(1) + ' cm';
        const refLK = document.getElementById('ref_lk_text');
        if (refLK) refLK.textContent = ref.lk[0].toFixed(1) + ' – ' + ref.lk[1].toFixed(1) + ' cm';

        const statusBox = document.getElementById('health_status_box');
        const statusIcon = document.getElementById('health_status_icon');
        const statusTitle = document.getElementById('health_status_title');
        const statusDesc = document.getElementById('health_status_desc');
        const hiddenInput = document.getElementById('status_kesehatan_input');

        if (usiaVal === '' || beratVal === '') {
            if (statusBox) statusBox.className = 'health-status-result health-status-result--uncalculated';
            if (statusIcon) statusIcon.className = 'fa-solid fa-circle-question';
            if (statusTitle) statusTitle.textContent = 'Belum Dihitung';
            if (statusDesc) statusDesc.textContent = 'Masukkan Usia dan Berat Badan untuk evaluasi status kesehatan bayi.';
            if (hiddenInput) hiddenInput.value = '';
            return;
        }

        const berat = parseFloat(beratVal);
        const minBB = ref.bb[0];
        const maxBB = ref.bb[1];

        let finalStatus = 'Sehat';
        let statusClass = 'sehat';
        let iconClass = 'fa-solid fa-circle-check';
        let titleText = 'Sehat (Normal)';
        let descText = 'Berat badan (' + berat.toFixed(2) + ' kg) ideal sesuai rentang normal WHO (' + minBB.toFixed(1) + ' – ' + maxBB.toFixed(1) + ' kg).';

        if (berat < minBB) {
            if (berat < minBB * 0.85) {
                finalStatus = 'Rawan';
                statusClass = 'rawan';
                iconClass = 'fa-solid fa-triangle-exclamation';
                titleText = 'Rawan (Berat Sangat Kurang / Gizi Buruk)';
                descText = 'Berat badan (' + berat.toFixed(2) + ' kg) jauh di bawah standar normal WHO (' + minBB.toFixed(1) + ' – ' + maxBB.toFixed(1) + ' kg). Butuh penanganan segera!';
            } else {
                finalStatus = 'Perlu Perhatian';
                statusClass = 'perhatian';
                iconClass = 'fa-solid fa-circle-exclamation';
                titleText = 'Perlu Perhatian (Berat Badan Kurang)';
                descText = 'Berat badan (' + berat.toFixed(2) + ' kg) di bawah batas normal WHO (' + minBB.toFixed(1) + ' – ' + maxBB.toFixed(1) + ' kg). Pantau asupan gizi.';
            }
        } else if (berat > maxBB) {
            if (berat > maxBB * 1.15) {
                finalStatus = 'Rawan';
                statusClass = 'rawan';
                iconClass = 'fa-solid fa-triangle-exclamation';
                titleText = 'Rawan (Risiko Obesitas Berat)';
                descText = 'Berat badan (' + berat.toFixed(2) + ' kg) melampaui batas aman standar WHO (' + minBB.toFixed(1) + ' – ' + maxBB.toFixed(1) + ' kg).';
            } else {
                finalStatus = 'Perlu Perhatian';
                statusClass = 'perhatian';
                iconClass = 'fa-solid fa-circle-exclamation';
                titleText = 'Perlu Perhatian (Berat Badan Berlebih)';
                descText = 'Berat badan (' + berat.toFixed(2) + ' kg) berada di atas batas normal WHO (' + minBB.toFixed(1) + ' – ' + maxBB.toFixed(1) + ' kg).';
            }
        } else {
            // BB normal, cek tinggi dan lingkar jika diisi
            if (tinggiVal > 0 && tinggiVal < ref.pb[0] * 0.90) {
                finalStatus = 'Perlu Perhatian';
                statusClass = 'perhatian';
                iconClass = 'fa-solid fa-circle-exclamation';
                titleText = 'Perlu Perhatian (Panjang Badan Rendah)';
                descText = 'Berat normal (' + berat.toFixed(2) + ' kg), namun panjang badan (' + tinggiVal.toFixed(1) + ' cm) di bawah standar WHO (' + ref.pb[0].toFixed(1) + ' cm).';
            } else if (lingkarVal > 0 && (lingkarVal < ref.lk[0] * 0.92 || lingkarVal > ref.lk[1] * 1.08)) {
                finalStatus = 'Perlu Perhatian';
                statusClass = 'perhatian';
                iconClass = 'fa-solid fa-circle-exclamation';
                titleText = 'Perlu Perhatian (Lingkar Kepala Perlu Evaluasi)';
                descText = 'Berat normal (' + berat.toFixed(2) + ' kg), lingkar kepala (' + lingkarVal.toFixed(1) + ' cm) di luar rentang normal WHO (' + ref.lk[0].toFixed(1) + ' – ' + ref.lk[1].toFixed(1) + ' cm).';
            }
        }

        if (statusBox) statusBox.className = 'health-status-result health-status-result--' + statusClass;
        if (statusIcon) statusIcon.className = iconClass;
        if (statusTitle) statusTitle.textContent = titleText;
        if (statusDesc) statusDesc.textContent = descText;
        if (hiddenInput) hiddenInput.value = finalStatus;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('posyanduForm');
        if (!form) return;

        ['usia_bulan', 'berat_badan', 'tinggi_badan', 'lingkar_kepala'].forEach(function (fieldName) {
            const field = form.querySelector('input[name="' + fieldName + '"]');
            if (field) {
                field.addEventListener('input', evaluateHealth);
                field.addEventListener('change', evaluateHealth);
            }
        });

        const genderField = form.querySelector('select[name="jenis_kelamin"]');
        if (genderField) genderField.addEventListener('change', evaluateHealth);

        const modal = document.getElementById('posyanduModal');
        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) closeForm();
            });
        }

        evaluateHealth();
    });

    function openForm() {
        const modal = document.getElementById('posyanduModal');
        if (modal) modal.style.display = 'flex';
        evaluateHealth();
    }

    function closeForm() {
        const modal = document.getElementById('posyanduModal');
        const form = document.getElementById('posyanduForm');
        if (modal) modal.style.display = 'none';
        if (form) form.reset();
        evaluateHealth();
    }

    function submitPosyandu(event) {
        event.preventDefault();
        const form = document.getElementById('posyanduForm');
        evaluateHealth();
        const formData = new FormData(form);

        fetch('proses_posyandu.php?action=add', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message || 'Proses berhasil');
            if (result.success) {
                closeForm();
                location.reload();
            }
        })
        .catch(() => {
            alert('Terjadi kesalahan saat menyimpan data pemeriksaan.');
        });
    }

    function printRecord(id) {
        if (!id) return;
        window.open('cetak_posyandu.php?id=' + id, '_blank');
    }

    function deleteRecord(id) {
        if (confirm('Hapus data pemeriksaan ini?')) {
            window.location.href = 'posyandu.php?delete=' + id;
        }
    }
</script>

<?php include '../includes/footer.php'; ?>
