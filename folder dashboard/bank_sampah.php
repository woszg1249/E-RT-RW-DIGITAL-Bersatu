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
$role = strtoupper($_SESSION['role'] ?? 'WARGA');

if ($role === 'ADMIN' && isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    if ($id > 0) {
        mysqli_query($koneksi, "UPDATE bank_sampah SET status='approved', tanggal_diproses=NOW() WHERE id='$id'");
    }
    header('Location: bank_sampah.php');
    exit();
}

if ($role === 'ADMIN' && isset($_GET['reject'])) {
    $id = intval($_GET['reject']);
    if ($id > 0) {
        mysqli_query($koneksi, "UPDATE bank_sampah SET status='rejected', tanggal_diproses=NOW() WHERE id='$id'");
    }
    header('Location: bank_sampah.php');
    exit();
}

if ($role === 'ADMIN' && isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id > 0) {
        mysqli_query($koneksi, "DELETE FROM bank_sampah WHERE id='$id'");
    }
    header('Location: bank_sampah.php');
    exit();
}

$query_user = mysqli_query($koneksi, "SELECT * FROM users WHERE nama = '$nama_user'");
$data_user = mysqli_fetch_assoc($query_user);
$id_user = $data_user['id'];
$foto_db = $data_user['foto'] ?? "";

if (!empty($foto_db) && file_exists("../img/" . $foto_db)) {
    $tampilan_foto = "../img/" . $foto_db;
} else {
    $tampilan_foto = "https://ui-avatars.com/api/?name=" . urlencode($nama_user) . "&background=0284C7&color=fff";
}

mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS bank_sampah (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    nama_warga VARCHAR(100),
    jenis_sampah VARCHAR(100) NOT NULL,
    berat_kg DECIMAL(10, 2) NOT NULL,
    harga_per_kg DECIMAL(10, 2) NOT NULL,
    total_harga DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    tanggal_pengumpulan DATETIME DEFAULT CURRENT_TIMESTAMP,
    tanggal_diproses DATETIME,
    catatan TEXT,
    FOREIGN KEY (id_user) REFERENCES users(id)
)");

$total_berat = 0;
$total_harga = 0;
$riwayat = [];

if ($role === 'ADMIN') {
    $query_stats = mysqli_query($koneksi, "SELECT SUM(berat_kg) as total_berat, SUM(total_harga) as total_harga FROM bank_sampah WHERE status='approved'");
    $data_stats = mysqli_fetch_assoc($query_stats);
    $total_berat = $data_stats['total_berat'] ?? 0;
    $total_harga = $data_stats['total_harga'] ?? 0;

    $query_riwayat = mysqli_query($koneksi, "SELECT bs.*, u.nama FROM bank_sampah bs LEFT JOIN users u ON bs.id_user = u.id ORDER BY bs.tanggal_pengumpulan DESC LIMIT 20");
} else {
    $query_stats = mysqli_query($koneksi, "SELECT SUM(berat_kg) as total_berat, SUM(total_harga) as total_harga FROM bank_sampah WHERE id_user='$id_user' AND status='approved'");
    $data_stats = mysqli_fetch_assoc($query_stats);
    $total_berat = $data_stats['total_berat'] ?? 0;
    $total_harga = $data_stats['total_harga'] ?? 0;

    $query_riwayat = mysqli_query($koneksi, "SELECT * FROM bank_sampah WHERE id_user='$id_user' ORDER BY tanggal_pengumpulan DESC");
}

if ($query_riwayat) {
    while ($row = mysqli_fetch_assoc($query_riwayat)) {
        $riwayat[] = $row;
    }
}

$pending_count = 0;
if ($role === 'ADMIN') {
    $query_pending = mysqli_query($koneksi, "SELECT COUNT(*) as count FROM bank_sampah WHERE status='pending'");
    $data_pending = mysqli_fetch_assoc($query_pending);
    $pending_count = $data_pending['count'] ?? 0;
}

$title = 'Bank Sampah - RT RW Digital Bersatu';
include '../includes/header.php';

?>

<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="app-main">
        <header class="app-topbar">
            <div class="app-topbar__left">
                <button id="toggle-sidebar" class="app-topbar__toggle" aria-label="Toggle menu"><i class="fa-solid fa-bars"></i></button>
                <div><strong>Bank Sampah</strong></div>
            </div>
            <div class="app-topbar__actions">
                <button type="button" class="pwa-install-btn" style="display:none;"><i class="fa-solid fa-download"></i> Download Aplikasi</button>
            </div>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-recycle"></i> Bank Sampah</h1>
                </div>
                <div class="page-header__meta">
                    <a href="dashboard.php" class="ghost-btn" style="display:inline-flex; align-items:center; justify-content:center;">Kembali</a>
                    <?php if ($role !== 'ADMIN'): ?>
                        <button class="primary-btn" onclick="openForm()" type="button">Tambah Sampah</button>
                    <?php endif; ?>
                </div>
            </div>

            <section class="stat-grid module-stats">
                <article class="stat-card">
                    <span class="stat-card__label">Total Berat Sampah</span>
                    <h2 class="stat-card__value"><?php echo number_format($total_berat, 2); ?> kg</h2>
                </article>
                <article class="stat-card">
                    <span class="stat-card__label">Total Rupiah</span>
                    <h2 class="stat-card__value">Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></h2>
                </article>
                <?php if ($role === 'ADMIN'): ?>
                    <article class="stat-card">
                        <span class="stat-card__label">Total Entri</span>
                        <h2 class="stat-card__value"><?php echo count($riwayat); ?></h2>
                        <?php if ($pending_count > 0): ?>
                            <div class="status-badge status-badge--pending" style="margin-top: 10px;"><?php echo $pending_count; ?> Pending</div>
                        <?php endif; ?>
                    </article>
                <?php endif; ?>
            </section>

            <section class="module-panel">
                <div class="module-panel__header">
                    Riwayat Pengumpulan Sampah
                </div>
                <div class="table-wrap">
                    <?php if (count($riwayat) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <?php if ($role === 'ADMIN'): ?><th>Nama Warga</th><?php endif; ?>
                                    <th>Jenis Sampah</th>
                                    <th>Berat (kg)</th>
                                    <th>Harga/kg</th>
                                    <th>Total Harga</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <?php if ($role === 'ADMIN'): ?><th>Aksi</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riwayat as $index => $item): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <?php if ($role === 'ADMIN'): ?><td><?php echo htmlspecialchars($item['nama'] ?? 'N/A'); ?></td><?php endif; ?>
                                        <td><?php echo htmlspecialchars($item['jenis_sampah']); ?></td>
                                        <td><?php echo number_format($item['berat_kg'], 2); ?></td>
                                        <td>Rp <?php echo number_format($item['harga_per_kg'], 0, ',', '.'); ?></td>
                                        <td>Rp <?php echo number_format($item['total_harga'], 0, ',', '.'); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal_pengumpulan'])); ?></td>
                                        <td>
                                            <span class="status-badge status-badge--<?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span>
                                        </td>
                                        <?php if ($role === 'ADMIN'): ?>
                                            <td>
                                                <?php if ($item['status'] === 'pending'): ?>
                                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                                        <button class="primary-btn" style="padding:8px 12px; font-size:0.75rem;" type="button" onclick="approveEntry(<?php echo $item['id']; ?>)">Setujui</button>
                                                        <button class="ghost-btn" style="padding:8px 12px; font-size:0.75rem;" type="button" onclick="rejectEntry(<?php echo $item['id']; ?>)">Tolak</button>
                                                    </div>
                                                <?php else: ?>
                                                    <button class="ghost-btn" style="padding:8px 12px; font-size:0.75rem;" type="button" onclick="deleteEntry(<?php echo $item['id']; ?>)">Hapus</button>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox" style="font-size:2.5rem; display:block; margin-bottom:10px;"></i>
                            Belum ada data pengumpulan sampah.
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</div>

<div id="bankSampahModal" class="modal">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="closeForm()" aria-label="Tutup modal">×</button>
        <div class="modal-header">
            <div>
                <h2><i class="fa-solid fa-recycle" style="color:var(--primary);"></i> Tambah Pengumpulan Sampah</h2>
                <p>Catat penyetoran sampah anorganik warga dan hitung otomatis total rupiah.</p>
            </div>
        </div>

        <form id="bankSampahForm" onsubmit="submitBankSampah(event)">
            <div class="form-grid">
                <?php if ($role === 'ADMIN'): ?>
                    <div class="form-field full-width">
                        <label for="bank_id_user"><i class="fa-regular fa-user"></i> Nama Warga Penyetor</label>
                        <select id="bank_id_user" name="id_user" required>
                            <option value="">-- Pilih Warga Penyetor --</option>
                            <?php
                            $list_warga = mysqli_query($koneksi, "SELECT id, nama, alamat FROM users WHERE role='Warga' ORDER BY nama");
                            while ($warga = mysqli_fetch_assoc($list_warga)) {
                                $almt = !empty($warga['alamat']) ? ' (' . htmlspecialchars($warga['alamat']) . ')' : '';
                                echo '<option value="' . (int)$warga['id'] . '">' . htmlspecialchars($warga['nama']) . $almt . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="id_user" value="<?php echo $id_user; ?>">
                    <div class="form-field full-width">
                        <label><i class="fa-regular fa-user"></i> Nama Penyetor</label>
                        <input type="text" value="<?php echo htmlspecialchars($nama_user); ?>" readonly>
                    </div>
                <?php endif; ?>

                <div class="form-field full-width">
                    <label for="jenis_sampah"><i class="fa-solid fa-boxes-packing"></i> Jenis Sampah</label>
                    <input type="text" list="daftar_jenis_sampah" id="jenis_sampah" name="jenis_sampah" placeholder="Pilih atau ketik jenis sampah..." required autocomplete="off">
                    <datalist id="daftar_jenis_sampah">
                        <option value="Plastik Botol Bersih (PET)">
                        <option value="Plastik Campur / Kresek">
                        <option value="Kardus / Karton Tebal">
                        <option value="Kertas Arsip / HVS / Buku">
                        <option value="Logam Besi / Seng">
                        <option value="Kaleng Alumunium Minuman">
                        <option value="Botol Kaca / Beling">
                        <option value="Minyak Jelantah">
                        <option value="Elektronik Bekas / Kabel">
                    </datalist>
                    <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:4px;">
                        <span class="form-hint" style="margin-top:0;">Pilihan cepat:</span>
                        <button type="button" class="ghost-btn" style="padding:2px 8px; font-size:0.75rem;" onclick="setPresetSampah('Kardus / Karton Tebal', 1500)">Kardus</button>
                        <button type="button" class="ghost-btn" style="padding:2px 8px; font-size:0.75rem;" onclick="setPresetSampah('Plastik Botol Bersih (PET)', 3000)">Botol Plastik</button>
                        <button type="button" class="ghost-btn" style="padding:2px 8px; font-size:0.75rem;" onclick="setPresetSampah('Kaleng Alumunium Minuman', 12000)">Kaleng</button>
                        <button type="button" class="ghost-btn" style="padding:2px 8px; font-size:0.75rem;" onclick="setPresetSampah('Minyak Jelantah', 6000)">Jelantah</button>
                    </div>
                </div>

                <div class="side-by-side">
                    <div class="form-field">
                        <label for="berat_kg"><i class="fa-solid fa-weight-hanging"></i> Berat (kg)</label>
                        <div class="input-with-unit">
                            <input type="number" id="berat_kg" step="0.01" min="0.01" name="berat_kg" placeholder="Contoh: 2.50" required>
                            <span class="input-unit">kg</span>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="harga_per_kg"><i class="fa-solid fa-tags"></i> Harga Satuan / kg</label>
                        <div class="input-with-unit">
                            <input type="number" id="harga_per_kg" step="100" min="0" name="harga_per_kg" placeholder="Contoh: 3000" required>
                            <span class="input-unit">Rp/kg</span>
                        </div>
                    </div>
                </div>

                <!-- Total Harga Highlight Card -->
                <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1.5px solid #86efac; border-radius: 14px; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <span style="font-size: 0.78rem; font-weight: 800; color: #166534; text-transform: uppercase; letter-spacing: 0.03em;">Total Nilai Rupiah</span>
                        <div style="font-size: 0.82rem; color: #15803d; margin-top: 2px;">Dihitung otomatis: Berat (kg) × Harga/kg</div>
                    </div>
                    <div style="text-align: right;">
                        <span id="total_harga_display" style="font-size: 1.5rem; font-weight: 800; color: #14532d;">Rp 0</span>
                        <input type="hidden" name="total_harga" id="total_harga_input" value="0">
                    </div>
                </div>

                <div class="form-field full-width">
                    <label for="catatan_sampah"><i class="fa-regular fa-comment-dots"></i> Catatan Tambahan (Opsional)</label>
                    <textarea id="catatan_sampah" name="catatan" rows="2" placeholder="Keterangan kondisi sampah, lokasi penjemputan, dsb..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="primary-btn"><i class="fa-solid fa-check"></i> Simpan Setoran</button>
                    <button type="button" class="ghost-btn" onclick="closeForm()">Batal</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function setPresetSampah(jenis, harga) {
        const jenisInput = document.getElementById('jenis_sampah');
        const hargaInput = document.getElementById('harga_per_kg');
        if (jenisInput) jenisInput.value = jenis;
        if (hargaInput && (!hargaInput.value || parseFloat(hargaInput.value) === 0)) {
            hargaInput.value = harga;
        }
        updateTotalHarga();
    }

    function updateTotalHarga() {
        const form = document.getElementById('bankSampahForm');
        if (!form) return;

        const beratInput = form.querySelector('input[name="berat_kg"]');
        const hargaInput = form.querySelector('input[name="harga_per_kg"]');
        const berat = parseFloat(beratInput ? beratInput.value : 0) || 0;
        const harga = parseFloat(hargaInput ? hargaInput.value : 0) || 0;
        const total = berat * harga;

        const display = document.getElementById('total_harga_display');
        const hidden = document.getElementById('total_harga_input');

        if (total > 0) {
            if (display) display.textContent = 'Rp ' + Math.round(total).toLocaleString('id-ID');
            if (hidden) hidden.value = total.toFixed(2);
        } else {
            if (display) display.textContent = 'Rp 0';
            if (hidden) hidden.value = '0';
        }
    }

    function openForm() {
        const modal = document.getElementById('bankSampahModal');
        if (modal) modal.style.display = 'flex';
        updateTotalHarga();
    }

    function closeForm() {
        const modal = document.getElementById('bankSampahModal');
        const form = document.getElementById('bankSampahForm');
        if (modal) modal.style.display = 'none';
        if (form) form.reset();
        updateTotalHarga();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('bankSampahModal');
        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) closeForm();
            });
        }

        const form = document.getElementById('bankSampahForm');
        if (form) {
            const beratInput = form.querySelector('input[name="berat_kg"]');
            const hargaInput = form.querySelector('input[name="harga_per_kg"]');
            if (beratInput) {
                beratInput.addEventListener('input', updateTotalHarga);
                beratInput.addEventListener('change', updateTotalHarga);
            }
            if (hargaInput) {
                hargaInput.addEventListener('input', updateTotalHarga);
                hargaInput.addEventListener('change', updateTotalHarga);
            }
        }
    });

    function submitBankSampah(event) {
        event.preventDefault();
        const form = document.getElementById('bankSampahForm');
        updateTotalHarga();
        const formData = new FormData(form);

        fetch('proses_bank_sampah.php?action=add', {
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
            alert('Terjadi kesalahan saat menyimpan data bank sampah.');
        });
    }

    function approveEntry(id) {
        if (confirm('Setujui data setoran sampah ini?')) {
            window.location.href = 'bank_sampah.php?approve=' + id;
        }
    }

    function rejectEntry(id) {
        if (confirm('Tolak data setoran sampah ini?')) {
            window.location.href = 'bank_sampah.php?reject=' + id;
        }
    }

    function deleteEntry(id) {
        if (confirm('Hapus riwayat sampah ini?')) {
            window.location.href = 'bank_sampah.php?delete=' + id;
        }
    }
</script>

<?php include '../includes/footer.php'; ?>
