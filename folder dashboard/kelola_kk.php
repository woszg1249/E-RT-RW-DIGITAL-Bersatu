<?php
include "../Koneksi.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nama'])) {
    header("Location: ../folder login,logout dan register/login.php");
    exit();
}

// Hak Akses Khusus ADMIN
if (strtoupper($_SESSION['role'] ?? 'WARGA') !== 'ADMIN') {
    echo "<script>alert('Akses Ditolak! Halaman Kelola Kartu Keluarga hanya dapat diakses oleh Admin.'); window.location='dashboard.php';</script>";
    exit();
}

// Akses langsung ke versi mobile terpisah jika ada parameter mobile=1
if (isset($_GET['mobile']) && $_GET['mobile'] === '1') {
    header("Location: kelola_kk_mobile.php");
    exit();
}

$nama_user = $_SESSION['nama'];
$role_user = $_SESSION['role'] ?? 'ADMIN';

// Hitung Statistik
$stat_kk = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(DISTINCT no_kk) as total FROM users WHERE role = 'Warga' AND no_kk IS NOT NULL AND no_kk != ''"));
$total_kk = (int)($stat_kk['total'] ?? 0);

$stat_warga = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga'"));
$total_warga = (int)($stat_warga['total'] ?? 0);

$stat_kepala = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga' AND status_keluarga = 'Kepala Keluarga'"));
$total_kepala = (int)($stat_kepala['total'] ?? 0);

$stat_anak = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga' AND status_keluarga = 'Anak'"));
$total_anak = (int)($stat_anak['total'] ?? 0);

$avg_anggota = ($total_kk > 0) ? round($total_warga / $total_kk, 1) : 0;

// Filter & Pencarian
$search = trim($_GET['q'] ?? '');
$filter_anggota = trim($_GET['anggota'] ?? '');

$where_clauses = ["role = 'Warga'", "no_kk IS NOT NULL", "no_kk != ''"];
if (!empty($search)) {
    $search_safe = mysqli_real_escape_string($koneksi, $search);
    $where_clauses[] = "(no_kk LIKE '%$search_safe%' OR nama LIKE '%$search_safe%' OR alamat LIKE '%$search_safe%' OR nik LIKE '%$search_safe%')";
}

$where_sql = implode(' AND ', $where_clauses);

// Dapatkan daftar No KK unik dengan kriteria pencarian
$kk_query_sql = "SELECT no_kk, alamat, COUNT(*) as total_anggota,
                 MAX(CASE WHEN status_keluarga = 'Kepala Keluarga' THEN nama END) as kepala_keluarga,
                 MAX(CASE WHEN status_keluarga = 'Kepala Keluarga' THEN nik END) as nik_kepala
                 FROM users
                 WHERE $where_sql
                 GROUP BY no_kk, alamat";

if (!empty($filter_anggota)) {
    if ($filter_anggota === '2') {
        $kk_query_sql .= " HAVING total_anggota = 2";
    } elseif ($filter_anggota === '3') {
        $kk_query_sql .= " HAVING total_anggota = 3";
    } elseif ($filter_anggota === '4+') {
        $kk_query_sql .= " HAVING total_anggota >= 4";
    }
}

$kk_query_sql .= " ORDER BY no_kk ASC";

$res_all_kk = mysqli_query($koneksi, $kk_query_sql);
$total_filtered_kk = mysqli_num_rows($res_all_kk);

// Pagination
$limit = 8;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$total_pages = ceil($total_filtered_kk / $limit);
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

$paged_kk_sql = $kk_query_sql . " LIMIT $limit OFFSET $offset";
$res_paged_kk = mysqli_query($koneksi, $paged_kk_sql);

$paged_kk_list = [];
while ($row = mysqli_fetch_assoc($res_paged_kk)) {
    $paged_kk_list[] = $row;
}

// Ambil semua anggota untuk KK yang tampil di halaman ini
$displayed_no_kk = array_column($paged_kk_list, 'no_kk');
$members_by_kk = [];

if (!empty($displayed_no_kk)) {
    $escaped_kk = array_map(function($val) use ($koneksi) {
        return "'" . mysqli_real_escape_string($koneksi, $val) . "'";
    }, $displayed_no_kk);
    $in_kk_sql = implode(',', $escaped_kk);

    $mem_sql = "SELECT id, nama, username, email, nik, no_kk, alamat, kelamin, no_hp, foto, status_keluarga 
                FROM users 
                WHERE role = 'Warga' AND no_kk IN ($in_kk_sql)
                ORDER BY FIELD(status_keluarga, 'Kepala Keluarga', 'Istri', 'Anak', 'Famili Lain'), id ASC";
    $mem_res = mysqli_query($koneksi, $mem_sql);
    while ($m = mysqli_fetch_assoc($mem_res)) {
        $members_by_kk[$m['no_kk']][] = $m;
    }
}

$title = 'Kelola Kartu Keluarga - RT RW Digital Bersatu';
include '../includes/header.php';
?>
<link rel="stylesheet" href="dashboard.css?v=<?php echo filemtime(__DIR__ . '/dashboard.css'); ?>">

<style>
/* CSS Spesifik Kelola KK */
.kk-card {
    background: #ffffff;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
    margin-bottom: 24px;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.kk-card:hover {
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}
.kk-card__header {
    background: #f8fafc;
    border-bottom: 1px solid var(--line);
    padding: 16px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.kk-card__header-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.kk-badge-no {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #0284c7;
    color: #ffffff;
    padding: 6px 14px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.92rem;
    letter-spacing: 0.03em;
}
.kk-badge-count {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #e0f2fe;
    color: #0369a1;
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.84rem;
}
.kk-meta-address {
    padding: 12px 22px;
    background: #ffffff;
    font-size: 0.88rem;
    color: var(--text-soft);
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    gap: 8px;
}
.kk-table {
    width: 100%;
    border-collapse: collapse;
}
.kk-table th, .kk-table td {
    padding: 12px 20px;
    text-align: left;
    font-size: 0.9rem;
    border-bottom: 1px solid var(--line);
}
.kk-table th {
    background: #ffffff;
    color: var(--muted);
    font-weight: 700;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.kk-table tr:last-child td {
    border-bottom: none;
}
.kk-table tr:hover td {
    background: #f8fafc;
}
.status-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.76rem;
    font-weight: 700;
    white-space: nowrap;
}
.status-tag--kepala {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
}
.status-tag--istri {
    background: #fce7f3;
    color: #be185d;
    border: 1px solid #fbcfe8;
}
.status-tag--anak {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.status-tag--famili {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}
.btn-print-kk {
    background: #ffffff;
    color: var(--primary);
    border: 1px solid var(--primary);
    padding: 6px 14px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.btn-print-kk:hover {
    background: var(--primary);
    color: #ffffff;
}
.copy-btn {
    background: transparent;
    border: none;
    color: #ffffff;
    cursor: pointer;
    padding: 2px 4px;
    font-size: 0.8rem;
    opacity: 0.85;
}
.copy-btn:hover {
    opacity: 1;
}

/* Modal Cetak KK Format Resmi */
.kk-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(3px);
    z-index: 2000;
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow-y: auto;
}
.kk-modal-backdrop.active {
    display: flex;
}
.kk-modal-container {
    background: #ffffff;
    width: 100%;
    max-width: 950px;
    border-radius: 12px;
    box-shadow: 0 20px 45px rgba(0,0,0,0.25);
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    padding: 30px;
}
.kk-official-paper {
    border: 3px double #333333;
    padding: 25px;
    background: #ffffff;
    color: #000000;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
.kk-official-header {
    text-align: center;
    border-bottom: 2px solid #000;
    padding-bottom: 12px;
    margin-bottom: 18px;
}
.kk-official-header h2 {
    font-size: 1.5rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    margin: 0;
    color: #000;
}
.kk-official-header h3 {
    font-size: 1.15rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    margin: 4px 0;
    color: #000;
}
.kk-official-header .kk-number {
    font-size: 1.35rem;
    font-weight: 900;
    margin-top: 8px;
    letter-spacing: 0.08em;
}
.kk-grid-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
    font-size: 0.88rem;
}
.kk-grid-row {
    display: flex;
    margin-bottom: 4px;
}
.kk-grid-label {
    width: 150px;
    font-weight: 700;
}
.kk-grid-val {
    flex: 1;
}
.kk-official-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 24px;
    font-size: 0.85rem;
}
.kk-official-table th, .kk-official-table td {
    border: 1px solid #333333;
    padding: 8px 10px;
    text-align: left;
}
.kk-official-table th {
    background: #f1f5f9;
    text-align: center;
    font-weight: 800;
}
.kk-signatures {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    text-align: center;
    font-size: 0.88rem;
}
.kk-sig-box {
    width: 220px;
}

@media print {
    body * {
        visibility: hidden;
    }
    #printable-kk-content, #printable-kk-content * {
        visibility: visible;
    }
    #printable-kk-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
    }
    .no-print {
        display: none !important;
    }
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
                    <strong style="color: var(--text);">Kelola Kartu Keluarga (KK)</strong>
                </div>
            </div>
            <div class="app-topbar__actions">
                <a href="kelola_kk_mobile.php" class="ghost-btn" style="text-decoration:none; color:var(--primary); font-weight:700;">
                    <i class="fa-solid fa-mobile-screen"></i> Mode HP / Mobile
                </a>
                <a href="kelola_warga.php" class="ghost-btn" style="text-decoration:none;">
                    <i class="fa-solid fa-users"></i> Data Warga
                </a>
            </div>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-people-roof" style="color: var(--primary);"></i> Pengelompokan Kartu Keluarga (KK)</h1>
                    <p>Data 100+ warga telah dikelompokkan ke dalam Kartu Keluarga lengkap dengan NIK, No. KK, status keluarga, dan alamat.</p>
                </div>
            </div>

            <!-- Kartu Statistik -->
            <section class="stat-grid">
                <article class="stat-card">
                    <span class="stat-card__label">Total Kartu Keluarga</span>
                    <h2 class="stat-card__value"><?php echo number_format($total_kk); ?> KK</h2>
                    <div class="stat-card__muted">Terdaftar di sistem RT/RW</div>
                </article>
                <article class="stat-card">
                    <span class="stat-card__label">Kepala Keluarga</span>
                    <h2 class="stat-card__value"><?php echo number_format($total_kepala); ?> Orang</h2>
                    <div class="stat-card__muted">Penanggung jawab keluarga</div>
                </article>
                <article class="stat-card">
                    <span class="stat-card__label">Total Warga Terdaftar</span>
                    <h2 class="stat-card__value"><?php echo number_format($total_warga); ?> Orang</h2>
                    <div class="stat-card__muted">Termasuk Kepala, Istri & Anak</div>
                </article>
                <article class="stat-card">
                    <span class="stat-card__label">Rata-rata Anggota / KK</span>
                    <h2 class="stat-card__value"><?php echo $avg_anggota; ?> Jiwa</h2>
                    <div class="stat-card__muted">Kepadatan keluarga RT</div>
                </article>
            </section>

            <!-- Form Pencarian & Filter -->
            <section class="glass-panel" style="padding: 18px 22px;">
                <form action="kelola_kk.php" method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                    <div style="display: flex; gap: 10px; flex: 1 1 300px; flex-wrap: wrap;">
                        <div style="flex: 1 1 220px; position: relative;">
                            <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari No. KK, Nama Kepala, NIK, atau Alamat..." style="width: 100%; padding: 10px 14px 10px 36px; border: 1px solid var(--line); border-radius: 8px;">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted);"></i>
                        </div>
                        <select name="anggota" style="padding: 10px 14px; border: 1px solid var(--line); border-radius: 8px; background: #fff; min-width: 160px;">
                            <option value="">Semua Ukuran KK</option>
                            <option value="2" <?php echo ($filter_anggota === '2') ? 'selected' : ''; ?>>2 Anggota</option>
                            <option value="3" <?php echo ($filter_anggota === '3') ? 'selected' : ''; ?>>3 Anggota</option>
                            <option value="4+" <?php echo ($filter_anggota === '4+') ? 'selected' : ''; ?>>4+ Anggota</option>
                        </select>
                        <button type="submit" class="primary-btn">
                            <i class="fa-solid fa-filter"></i> Filter
                        </button>
                        <?php if (!empty($search) || !empty($filter_anggota)): ?>
                            <a href="kelola_kk.php" class="ghost-btn" style="text-decoration:none;">Reset</a>
                        <?php endif; ?>
                    </div>
                    <div style="color: var(--text-soft); font-size: 0.9rem;">
                        Menampilkan <strong><?php echo count($paged_kk_list); ?></strong> dari <strong><?php echo $total_filtered_kk; ?></strong> KK
                    </div>
                </form>
            </section>

            <!-- Daftar Kartu Keluarga -->
            <section>
                <?php if (empty($paged_kk_list)): ?>
                    <div class="glass-panel empty-state">
                        <i class="fa-solid fa-folder-open" style="font-size: 2.5rem; color: var(--muted); margin-bottom: 12px; display:block;"></i>
                        <h3>Tidak Ada Data Kartu Keluarga Ditemukan</h3>
                        <p style="margin-top: 6px;">Silakan periksa kembali kata kunci pencarian atau filter yang Anda terapkan.</p>
                        <a href="kelola_kk.php" class="primary-btn" style="margin-top: 14px; text-decoration:none;">Kembali ke Semua KK</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($paged_kk_list as $kk): 
                        $no_kk = $kk['no_kk'];
                        $members = $members_by_kk[$no_kk] ?? [];
                        $kepala = $kk['kepala_keluarga'] ?? 'Belum Ditentukan';
                        $alamat_kk = $kk['alamat'] ?? '-';
                    ?>
                        <article class="kk-card">
                            <div class="kk-card__header">
                                <div class="kk-card__header-left">
                                    <div class="kk-badge-no">
                                        <i class="fa-solid fa-address-card"></i>
                                        <span>No. KK: <?php echo htmlspecialchars($no_kk); ?></span>
                                        <button class="copy-btn" title="Salin No. KK" onclick="copyToClipboard('<?php echo $no_kk; ?>')">
                                            <i class="fa-regular fa-copy"></i>
                                        </button>
                                    </div>
                                    <div class="kk-badge-count">
                                        <i class="fa-solid fa-users"></i>
                                        <span><?php echo count($members); ?> Anggota</span>
                                    </div>
                                    <div style="font-size: 0.92rem; color: var(--text); font-weight: 700;">
                                        Kepala Keluarga: <span style="color: var(--primary);"><?php echo htmlspecialchars($kepala); ?></span>
                                    </div>
                                </div>
                                <div>
                                    <button type="button" class="btn-print-kk" onclick="openKkModal('<?php echo $no_kk; ?>')">
                                        <i class="fa-solid fa-print"></i> Format Cetak KK
                                    </button>
                                </div>
                            </div>

                            <div class="kk-meta-address">
                                <i class="fa-solid fa-location-dot" style="color: #ef4444;"></i>
                                <span><strong>Alamat:</strong> <?php echo htmlspecialchars($alamat_kk); ?></span>
                            </div>

                            <div class="table-wrap">
                                <table class="kk-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">No</th>
                                            <th>Nama Lengkap</th>
                                            <th>NIK</th>
                                            <th>Hubungan Keluarga</th>
                                            <th>Jenis Kelamin</th>
                                            <th>No. Telepon / HP</th>
                                            <th>Username Akun</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = 1;
                                        foreach ($members as $mem): 
                                            $st = $mem['status_keluarga'] ?? 'Anggota';
                                            $badge_class = 'status-tag--famili';
                                            if ($st === 'Kepala Keluarga') $badge_class = 'status-tag--kepala';
                                            elseif ($st === 'Istri') $badge_class = 'status-tag--istri';
                                            elseif ($st === 'Anak') $badge_class = 'status-tag--anak';
                                        ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td>
                                                    <div style="font-weight: 700; color: var(--text);">
                                                        <?php echo htmlspecialchars($mem['nama']); ?>
                                                    </div>
                                                </td>
                                                <td style="font-family: monospace; font-size: 0.95rem; color: var(--text-soft);">
                                                    <?php echo htmlspecialchars($mem['nik'] ?? '-'); ?>
                                                </td>
                                                <td>
                                                    <span class="status-tag <?php echo $badge_class; ?>">
                                                        <?php if ($st === 'Kepala Keluarga'): ?>
                                                            <i class="fa-solid fa-crown" style="font-size: 0.7rem;"></i>
                                                        <?php elseif ($st === 'Istri'): ?>
                                                            <i class="fa-solid fa-heart" style="font-size: 0.7rem;"></i>
                                                        <?php elseif ($st === 'Anak'): ?>
                                                            <i class="fa-solid fa-child" style="font-size: 0.7rem;"></i>
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($st); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($mem['kelamin'] === 'Laki-laki'): ?>
                                                        <span style="color: #0284c7; font-weight: 600;"><i class="fa-solid fa-mars"></i> Laki-laki</span>
                                                    <?php else: ?>
                                                        <span style="color: #db2777; font-weight: 600;"><i class="fa-solid fa-venus"></i> Perempuan</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="color: var(--text-soft);">
                                                    <?php echo htmlspecialchars($mem['no_hp'] ?? '-'); ?>
                                                </td>
                                                <td>
                                                    <code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.85rem;">
                                                        <?php echo htmlspecialchars($mem['username'] ?? '-'); ?>
                                                    </code>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div style="display: flex; justify-content: center; gap: 8px; margin-top: 20px; flex-wrap: wrap;">
                            <?php if ($page > 1): ?>
                                <a href="kelola_kk.php?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>&anggota=<?php echo urlencode($filter_anggota); ?>" class="ghost-btn" style="text-decoration:none;">&laquo; Sebelumnya</a>
                            <?php endif; ?>

                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                <a href="kelola_kk.php?page=<?php echo $p; ?>&q=<?php echo urlencode($search); ?>&anggota=<?php echo urlencode($filter_anggota); ?>" class="<?php echo ($p === $page) ? 'primary-btn' : 'ghost-btn'; ?>" style="text-decoration:none; min-width: 40px; text-align: center;">
                                    <?php echo $p; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="kelola_kk.php?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>&anggota=<?php echo urlencode($filter_anggota); ?>" class="ghost-btn" style="text-decoration:none;">Selanjutnya &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<!-- MODAL CETAK KARTU KELUARGA RESMI -->
<div class="kk-modal-backdrop" id="kkModalBackdrop">
    <div class="kk-modal-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;" class="no-print">
            <h3 style="margin: 0; color: var(--text);"><i class="fa-solid fa-print"></i> Pratinjau Dokumen Kartu Keluarga</h3>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="primary-btn" onclick="printKkDocument()">
                    <i class="fa-solid fa-print"></i> Cetak Dokumen
                </button>
                <button type="button" class="ghost-btn" onclick="closeKkModal()">
                    <i class="fa-solid fa-xmark"></i> Tutup
                </button>
            </div>
        </div>

        <!-- Dokumen Kartu Keluarga Resmi (Printable) -->
        <div class="kk-official-paper" id="printable-kk-content">
            <div class="kk-official-header">
                <h2>REPUBLIK INDONESIA</h2>
                <h3>KARTU KELUARGA</h3>
                <div class="kk-number" id="modalKkNumber">No. 3275010000000000</div>
            </div>

            <div class="kk-grid-info">
                <div>
                    <div class="kk-grid-row">
                        <div class="kk-grid-label">Nama Kepala Keluarga</div>
                        <div class="kk-grid-val">: <strong id="modalKkKepala">-</strong></div>
                    </div>
                    <div class="kk-grid-row">
                        <div class="kk-grid-label">Alamat</div>
                        <div class="kk-grid-val">: <span id="modalKkAlamat">-</span></div>
                    </div>
                    <div class="kk-grid-row">
                        <div class="kk-grid-label">RT / RW</div>
                        <div class="kk-grid-val">: 01 / 05</div>
                    </div>
                    <div class="kk-grid-row">
                        <div class="kk-grid-label">Desa / Kelurahan</div>
                        <div class="kk-grid-val">: Margahayu</div>
                    </div>
                </div>
                <div>
                    <div class="kk-grid-row">
                        <div class="kk-grid-label">Kecamatan</div>
                        <div class="kk-grid-val">: Bekasi Timur</div>
                    </div>
                    <div class="kk-grid-row">
                        <div class="kk-grid-label">Kabupaten / Kota</div>
                        <div class="kk-grid-val">: Kota Bekasi</div>
                    </div>
                    <div class="kk-grid-row">
                        <div class="kk-grid-label">Provinsi</div>
                        <div class="kk-grid-val">: Jawa Barat</div>
                    </div>
                    <div class="kk-grid-row">
                        <div class="kk-grid-label">Kode Pos</div>
                        <div class="kk-grid-val">: 17113</div>
                    </div>
                </div>
            </div>

            <!-- Tabel Anggota Keluarga -->
            <table class="kk-official-table">
                <thead>
                    <tr>
                        <th style="width: 35px;">No</th>
                        <th>Nama Lengkap</th>
                        <th>NIK</th>
                        <th>Jenis Kelamin</th>
                        <th>Status Hubungan Dalam Keluarga</th>
                        <th>No. Kontak</th>
                    </tr>
                </thead>
                <tbody id="modalKkMembersTable">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>

            <!-- Tanda Tangan Resmi -->
            <div class="kk-signatures">
                <div class="kk-sig-box">
                    <div>Tanda Tangan/Cap Jempol</div>
                    <div style="font-weight: 700; margin-bottom: 50px;">KEPALA KELUARGA</div>
                    <div style="font-weight: 800; text-decoration: underline;" id="modalSigKepala">NAMA KEPALA</div>
                </div>
                <div class="kk-sig-box">
                    <div>Dikeluarkan di: Kota Bekasi</div>
                    <div style="margin-bottom: 50px;">Pada Tanggal: <?php echo date('d F Y'); ?></div>
                    <div style="font-weight: 800;">RT RW DIGITAL BERSATU</div>
                </div>
                <div class="kk-sig-box">
                    <div>Mengetahui,</div>
                    <div style="font-weight: 700; margin-bottom: 50px;">KETUA RT 01 / RW 05</div>
                    <div style="font-weight: 800; text-decoration: underline;">DIMAS (RT)</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Data KK dalam format JSON untuk modal cetak
const kkDataStore = <?php echo json_encode($members_by_kk, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const kkMetaStore = <?php echo json_encode($paged_kk_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            alert('No. KK ' + text + ' berhasil disalin!');
        });
    } else {
        const temp = document.createElement('input');
        temp.value = text;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        alert('No. KK ' + text + ' berhasil disalin!');
    }
}

function openKkModal(noKk) {
    const members = kkDataStore[noKk] || [];
    let meta = null;
    for (let i = 0; i < kkMetaStore.length; i++) {
        if (kkMetaStore[i].no_kk === noKk) {
            meta = kkMetaStore[i];
            break;
        }
    }

    const kepala = meta ? meta.kepala_keluarga : (members[0] ? members[0].nama : '-');
    const alamat = meta ? meta.alamat : (members[0] ? members[0].alamat : '-');

    document.getElementById('modalKkNumber').textContent = 'No. ' + noKk;
    document.getElementById('modalKkKepala').textContent = kepala;
    document.getElementById('modalKkAlamat').textContent = alamat;
    document.getElementById('modalSigKepala').textContent = kepala.toUpperCase();

    const tbody = document.getElementById('modalKkMembersTable');
    tbody.innerHTML = '';

    members.forEach(function(m, idx) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="text-align: center;">${idx + 1}</td>
            <td style="font-weight: 700;">${m.nama}</td>
            <td style="font-family: monospace; letter-spacing: 0.05em; text-align: center;">${m.nik || '-'}</td>
            <td style="text-align: center;">${m.kelamin}</td>
            <td style="text-align: center; font-weight: 600;">${m.status_keluarga || 'Anggota'}</td>
            <td style="text-align: center;">${m.no_hp || '-'}</td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('kkModalBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeKkModal() {
    document.getElementById('kkModalBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

function printKkDocument() {
    window.print();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeKkModal();
    }
});

document.getElementById('kkModalBackdrop').addEventListener('click', function(e) {
    if (e.target === this) {
        closeKkModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
