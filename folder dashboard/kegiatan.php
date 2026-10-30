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

// Ensure table exists
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS kegiatan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    deskripsi TEXT,
    tanggal_mulai DATE,
    tanggal_selesai DATE,
    waktu VARCHAR(50),
    lokasi VARCHAR(255),
    dibuat_oleh INT,
    tanggal_dibuat DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$events = [];
$q = mysqli_query($koneksi, "SELECT k.*, u.nama as pembuat FROM kegiatan k LEFT JOIN users u ON k.dibuat_oleh = u.id ORDER BY k.tanggal_mulai DESC");
while ($r = mysqli_fetch_assoc($q)) { $events[] = $r; }

$title = 'Kegiatan Warga - RT RW Digital Bersatu';
include '../includes/header.php';
?>

<style>
    .event-card { background:#ffffff; border:1px solid var(--line); padding:20px 24px; border-radius:14px; margin-bottom:18px; box-shadow: 0 4px 14px rgba(15,23,42,0.04); }
    .event-row { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; }
    .event-meta { color:var(--text-soft); font-size:0.9rem; margin-top:6px; display:flex; gap:14px; flex-wrap:wrap; }
    .event-card h3 { margin:0 0 6px 0; color:var(--text); font-size:1.15rem; font-weight:800; }

    /* Modal overlay and content */
    .modal { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.5); backdrop-filter: blur(2px); z-index: 9999; align-items: center; justify-content: center; padding: 20px; }
    .modal.active { display:flex; }
    .modal .modal-content { background: #fff; border-radius: 16px; max-width: 650px; width: 100%; padding: 26px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); position: relative; }
    .close-btn { position: absolute; right: 16px; top: 16px; background: #f1f5f9; border: none; padding: 6px 10px; border-radius: 8px; cursor: pointer; font-size: 18px; color: var(--text-soft); }

    .form-group { margin-bottom: 14px; }
    .form-group label { display:block; font-weight:700; margin-bottom:6px; color:var(--text); font-size:0.9rem; }
    .form-group input, .form-group textarea, .form-group select { width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:0.95rem; }
    .form-row { display:flex; gap:12px; }
    .form-row .form-group { flex:1; }

    @media (max-width: 640px) {
        .form-row { flex-direction: column; }
        .event-row { flex-direction: column; align-items:flex-start; }
        .modal .modal-content { padding: 18px; }
    }
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
                    <strong style="color: var(--text);">Kegiatan Warga</strong>
                </div>
            </div>
            <div class="app-topbar__actions">
                <?php if ($role_user === 'ADMIN'): ?>
                    <button class="primary-btn" onclick="openForm()">
                        <i class="fa-solid fa-plus"></i> Buat Kegiatan
                    </button>
                <?php endif; ?>
            </div>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-calendar-days" style="color: var(--primary);"></i> Daftar Kegiatan Warga</h1>
                    <p style="margin: 6px 0 0; color: var(--text-soft);">Jadwal gotong royong, musyawarah RT/RW, dan agenda bersama warga.</p>
                </div>
            </div>

            <?php if (count($events) === 0): ?>
                <div class="module-panel">
                    <div class="empty-state">Belum ada kegiatan terjadwal saat ini.</div>
                </div>
            <?php else: ?>
                <?php foreach ($events as $ev): ?>
                    <div class="event-card">
                        <div class="event-row">
                            <div style="flex:1;">
                                <h3><?php echo htmlspecialchars($ev['judul']); ?></h3>
                                <div class="event-meta">
                                    <span><i class="fa-regular fa-calendar" style="color:var(--primary); margin-right:4px;"></i> <?php echo htmlspecialchars($ev['tanggal_mulai']); ?><?php if (!empty($ev['tanggal_selesai']) && $ev['tanggal_selesai'] !== $ev['tanggal_mulai']): ?> s/d <?php echo htmlspecialchars($ev['tanggal_selesai']); ?><?php endif; ?></span>
                                    <?php if (!empty($ev['waktu'])): ?>
                                        <span><i class="fa-regular fa-clock" style="color:var(--primary); margin-right:4px;"></i> <?php echo htmlspecialchars($ev['waktu']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($ev['lokasi'])): ?>
                                        <span><i class="fa-solid fa-location-dot" style="color:#ef4444; margin-right:4px;"></i> <?php echo htmlspecialchars($ev['lokasi']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($ev['deskripsi'])): ?>
                                    <p style="margin:10px 0 0; color:var(--text-soft); font-size:0.92rem; line-height:1.5;">
                                        <?php echo nl2br(htmlspecialchars($ev['deskripsi'])); ?>
                                    </p>
                                <?php endif; ?>
                                <small style="display:block; margin-top:8px; color:#94a3b8; font-size:0.8rem;">
                                    Dibuat oleh: <?php echo htmlspecialchars($ev['pembuat'] ?? 'Admin'); ?>
                                </small>
                            </div>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <button type="button" class="ghost-btn" style="padding:7px 12px; font-size:0.85rem;" data-event="<?php echo htmlspecialchars(json_encode($ev)); ?>" onclick="showDetail(this)">
                                    <i class="fa-solid fa-eye"></i> Detail
                                </button>
                                <?php if ($role_user === 'ADMIN'): ?>
                                    <button type="button" class="ghost-btn" style="padding:7px 12px; font-size:0.85rem;" data-event="<?php echo htmlspecialchars(json_encode($ev)); ?>" onclick="editEvent(this)">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </button>
                                    <button type="button" class="ghost-btn" style="padding:7px 12px; font-size:0.85rem; color:#dc2626; border-color:#fca5a5;" onclick="deleteEvent(<?php echo $ev['id']; ?>)">
                                        <i class="fa-solid fa-trash"></i> Hapus
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Modal Form Buat/Ubah Kegiatan -->
<div class="modal" id="formModal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeForm()">&times;</button>
        <h3 id="modalTitle" style="margin:0 0 18px; color:var(--text);">Buat Kegiatan</h3>
        <form id="eventForm" onsubmit="submitEvent(event)">
            <input type="hidden" name="id" id="ev_id">
            <div class="form-group">
                <label>Nama Kegiatan *</label>
                <input type="text" name="judul" id="ev_judul" required placeholder="Contoh: Gotong Royong Kebersihan">
            </div>
            <div class="form-group">
                <label>Deskripsi Kegiatan</label>
                <textarea name="deskripsi" id="ev_deskripsi" rows="3" placeholder="Rincian informasi agenda..."></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Tanggal Mulai *</label>
                    <input type="date" name="tanggal_mulai" id="ev_tanggal_mulai" required>
                </div>
                <div class="form-group">
                    <label>Tanggal Selesai</label>
                    <input type="date" name="tanggal_selesai" id="ev_tanggal_selesai">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Waktu Kegiatan</label>
                    <input type="text" name="waktu" id="ev_waktu" placeholder="Contoh: 08.00 - Selesai">
                </div>
                <div class="form-group">
                    <label>Lokasi</label>
                    <input type="text" name="lokasi" id="ev_lokasi" placeholder="Contoh: Lapangan RT 06">
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="ghost-btn" onclick="closeForm()">Batal</button>
                <button type="submit" class="primary-btn">Simpan Kegiatan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Detail Kegiatan -->
<div class="modal" id="detailModal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeDetail()">&times;</button>
        <h3 id="detailTitle" style="margin:0 0 14px; color:var(--text);">Detail Kegiatan</h3>
        <div style="line-height:1.6; font-size:0.95rem; color:var(--text-soft);">
            <p id="detailDeskripsi" style="margin-bottom:14px; color:var(--text);"></p>
            <div style="background:#f8fafc; border:1px solid var(--line); border-radius:10px; padding:14px;">
                <p style="margin:4px 0;"><strong><i class="fa-regular fa-calendar"></i> Tanggal:</strong> <span id="detailTanggal">-</span></p>
                <p style="margin:4px 0;"><strong><i class="fa-regular fa-clock"></i> Waktu:</strong> <span id="detailWaktu">-</span></p>
                <p style="margin:4px 0;"><strong><i class="fa-solid fa-location-dot"></i> Lokasi:</strong> <span id="detailLokasi">-</span></p>
                <p style="margin:4px 0;"><strong><i class="fa-solid fa-user"></i> Penanggung Jawab:</strong> <span id="detailPembuat">-</span></p>
            </div>
        </div>
        <div style="display:flex; justify-content:flex-end; margin-top:18px;">
            <button type="button" class="primary-btn" onclick="closeDetail()">Tutup</button>
        </div>
    </div>
</div>

<script>
    function openForm() {
        document.getElementById('formModal').classList.add('active');
        document.getElementById('modalTitle').innerText = 'Buat Kegiatan';
        document.getElementById('eventForm').reset();
        document.getElementById('ev_id').value = '';
    }
    function closeForm() { document.getElementById('formModal').classList.remove('active'); }

    function submitEvent(e) {
        e.preventDefault();
        const form = document.getElementById('eventForm');
        const data = new FormData(form);
        const id = data.get('id');
        const action = id ? 'update' : 'add';

        fetch('proses_kegiatan.php?action=' + action, { method: 'POST', body: data })
        .then(r => r.json()).then(res => { alert(res.message); if (res.success) location.reload(); })
        .catch(() => alert('Terjadi kesalahan'));
    }

    function editEvent(button) {
        const raw = button.getAttribute('data-event');
        if (!raw) return;
        const data = JSON.parse(raw);
        openForm();
        document.getElementById('modalTitle').innerText = 'Ubah Kegiatan';
        document.getElementById('ev_id').value = data.id || '';
        document.getElementById('ev_judul').value = data.judul || '';
        document.getElementById('ev_deskripsi').value = data.deskripsi || '';
        document.getElementById('ev_tanggal_mulai').value = data.tanggal_mulai || '';
        document.getElementById('ev_tanggal_selesai').value = data.tanggal_selesai || '';
        document.getElementById('ev_waktu').value = data.waktu || '';
        document.getElementById('ev_lokasi').value = data.lokasi || '';
    }

    function deleteEvent(id) {
        if (!confirm('Hapus kegiatan ini?')) return;
        fetch('proses_kegiatan.php?action=delete&id=' + id, { method: 'POST' })
        .then(r => r.json()).then(res => { alert(res.message); if (res.success) location.reload(); })
        .catch(() => alert('Terjadi kesalahan'));
    }

    function showDetail(button) {
        const raw = button.getAttribute('data-event');
        if (!raw) return;
        const data = JSON.parse(raw);
        const detailModal = document.getElementById('detailModal');
        document.getElementById('detailTitle').innerText = data.judul || 'Detail Kegiatan';
        document.getElementById('detailDeskripsi').innerText = data.deskripsi || 'Tidak ada deskripsi.';
        const tanggalMulai = data.tanggal_mulai || '';
        const tanggalSelesai = data.tanggal_selesai || '';
        const tanggalText = tanggalMulai ? tanggalMulai + (tanggalSelesai ? ' - ' + tanggalSelesai : '') : '-';
        document.getElementById('detailTanggal').innerText = tanggalText;
        document.getElementById('detailWaktu').innerText = data.waktu || '-';
        document.getElementById('detailLokasi').innerText = data.lokasi || '-';
        document.getElementById('detailPembuat').innerText = data.pembuat || '-';
        detailModal.classList.add('active');
    }

    function closeDetail() {
        document.getElementById('detailModal').classList.remove('active');
    }
</script>

<?php include '../includes/footer.php'; ?>
