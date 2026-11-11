<?php
include "../Koneksi.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nama'])) {
    header("Location: ../folder login,logout dan register/login.php");
    exit();
}

// Akses: Admin (full) dan Warga (read-only untuk KK sendiri)
require_login();

$nama_user = $_SESSION['nama'];
$role_user = strtoupper($_SESSION['role'] ?? 'WARGA');
$is_admin = ($role_user === 'ADMIN');

// Ambil no_kk user yang login (untuk Warga)
$user_no_kk = '';
if (!$is_admin) {
    $user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    $stmt_kk = mysqli_prepare($koneksi, "SELECT no_kk FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt_kk, "i", $user_id);
    mysqli_stmt_execute($stmt_kk);
    $result_kk = mysqli_stmt_get_result($stmt_kk);
    if ($result_kk && $row_kk = mysqli_fetch_assoc($result_kk)) {
        $user_no_kk = trim($row_kk['no_kk'] ?? '');
    }
    mysqli_stmt_close($stmt_kk);
    
    // Jika Warga tidak punya no_kk, tampilkan pesan
    if (empty($user_no_kk)) {
        echo "<script>alert('Anda belum terdaftar dalam Kartu Keluarga. Silakan hubungi Admin RT.'); window.location='dashboard.php';</script>";
        exit();
    }
}

// Akses langsung ke versi mobile terpisah jika ada parameter mobile=1
if (isset($_GET['mobile']) && $_GET['mobile'] === '1') {
    header("Location: kelola_kk_mobile.php");
    exit();
}

// Hitung Statistik
if ($is_admin) {
    // Admin: lihat semua KK
    $stat_kk = mysqli_fetch_assoc(db_query($koneksi, "SELECT COUNT(DISTINCT no_kk) as total FROM users WHERE role = 'Warga' AND no_kk IS NOT NULL AND no_kk != ''"));
    $total_kk = (int)($stat_kk['total'] ?? 0);

    $stat_kk_06 = mysqli_fetch_assoc(db_query($koneksi, "SELECT COUNT(DISTINCT no_kk) as total FROM users WHERE role = 'Warga' AND rt='06' AND no_kk IS NOT NULL AND no_kk != ''"));
    $total_kk_06 = (int)($stat_kk_06['total'] ?? 0);

    $stat_kk_07 = mysqli_fetch_assoc(db_query($koneksi, "SELECT COUNT(DISTINCT no_kk) as total FROM users WHERE role = 'Warga' AND rt='07' AND no_kk IS NOT NULL AND no_kk != ''"));
    $total_kk_07 = (int)($stat_kk_07['total'] ?? 0);

    $stat_warga = mysqli_fetch_assoc(db_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga'"));
    $total_warga = (int)($stat_warga['total'] ?? 0);

    $stat_warga_06 = mysqli_fetch_assoc(db_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga' AND rt='06'"));
    $total_warga_06 = (int)($stat_warga_06['total'] ?? 0);

    $stat_warga_07 = mysqli_fetch_assoc(db_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga' AND rt='07'"));
    $total_warga_07 = (int)($stat_warga_07['total'] ?? 0);

    $stat_kepala = mysqli_fetch_assoc(db_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga' AND status_keluarga = 'Kepala Keluarga'"));
    $total_kepala = (int)($stat_kepala['total'] ?? 0);

    $stat_anak = mysqli_fetch_assoc(db_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga' AND status_keluarga = 'Anak'"));
    $total_anak = (int)($stat_anak['total'] ?? 0);

    $avg_anggota = ($total_kk > 0) ? round($total_warga / $total_kk, 1) : 0;
} else {
    // Warga: hanya lihat KK sendiri
    $total_kk = 1;
    
    $stmt_warga = mysqli_prepare($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga' AND no_kk = ?");
    mysqli_stmt_bind_param($stmt_warga, "s", $user_no_kk);
    mysqli_stmt_execute($stmt_warga);
    $stat_warga = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_warga));
    $total_warga = (int)($stat_warga['total'] ?? 0);
    mysqli_stmt_close($stmt_warga);
    
    $stmt_kepala = mysqli_prepare($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga' AND no_kk = ? AND status_keluarga = 'Kepala Keluarga'");
    mysqli_stmt_bind_param($stmt_kepala, "s", $user_no_kk);
    mysqli_stmt_execute($stmt_kepala);
    $stat_kepala = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_kepala));
    $total_kepala = (int)($stat_kepala['total'] ?? 0);
    mysqli_stmt_close($stmt_kepala);
    
    $stmt_anak = mysqli_prepare($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga' AND no_kk = ? AND status_keluarga = 'Anak'");
    mysqli_stmt_bind_param($stmt_anak, "s", $user_no_kk);
    mysqli_stmt_execute($stmt_anak);
    $stat_anak = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_anak));
    $total_anak = (int)($stat_anak['total'] ?? 0);
    mysqli_stmt_close($stmt_anak);
    
    $avg_anggota = $total_warga;
}

// Filter & Pencarian
$search = trim($_GET['q'] ?? '');
$filter_anggota = trim($_GET['anggota'] ?? '');
$filter_rt = trim($_GET['rt'] ?? '');

$where_clauses = ["role = 'Warga'", "no_kk IS NOT NULL", "no_kk != ''"];
$params = [];
$types = '';

// Warga: filter otomatis hanya no_kk sendiri
if (!$is_admin) {
    $where_clauses[] = "no_kk = ?";
    $params[] = $user_no_kk;
    $types .= 's';
}

if (!empty($search)) {
    $search_like = '%' . $search . '%';
    $where_clauses[] = "(no_kk LIKE ? OR nama LIKE ? OR alamat LIKE ? OR nik LIKE ?)";
    $params = array_merge($params, [$search_like, $search_like, $search_like, $search_like]);
    $types .= 'ssss';
}

if (!empty($filter_rt)) {
    $where_clauses[] = "rt = ?";
    $params[] = $filter_rt;
    $types .= 's';
}

$where_sql = implode(' AND ', $where_clauses);

// Dapatkan daftar No KK unik dengan kriteria pencarian
$kk_query_sql = "SELECT no_kk, alamat, MAX(rt) as rt, MAX(rw) as rw, COUNT(*) as total_anggota,
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

$stmt_all = mysqli_prepare($koneksi, $kk_query_sql);
if ($stmt_all && !empty($params)) {
    mysqli_stmt_bind_param($stmt_all, $types, ...$params);
    mysqli_stmt_execute($stmt_all);
    $res_all_kk = mysqli_stmt_get_result($stmt_all);
} else if ($stmt_all) {
    mysqli_stmt_execute($stmt_all);
    $res_all_kk = mysqli_stmt_get_result($stmt_all);
} else {
    $res_all_kk = false;
}
$total_filtered_kk = $res_all_kk ? mysqli_num_rows($res_all_kk) : 0;
if ($stmt_all) mysqli_stmt_close($stmt_all);

// Pagination
$limit = 8;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$total_pages = ceil($total_filtered_kk / $limit);
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

$paged_kk_sql = $kk_query_sql . " LIMIT ? OFFSET ?";
$stmt_paged = mysqli_prepare($koneksi, $paged_kk_sql);
if ($stmt_paged) {
    $params_with_limit = array_merge($params, [$limit, $offset]);
    $types_with_limit = $types . 'ii';
    if (!empty($params_with_limit)) {
        mysqli_stmt_bind_param($stmt_paged, $types_with_limit, ...$params_with_limit);
    }
    mysqli_stmt_execute($stmt_paged);
    $res_paged_kk = mysqli_stmt_get_result($stmt_paged);
    mysqli_stmt_close($stmt_paged);
} else {
    $res_paged_kk = false;
}

$paged_kk_list = [];
if ($res_paged_kk) {
    while ($row = mysqli_fetch_assoc($res_paged_kk)) {
        $paged_kk_list[] = $row;
    }
}

// Ambil semua anggota untuk KK yang tampil di halaman ini
$displayed_no_kk = array_column($paged_kk_list, 'no_kk');
$members_by_kk = [];

if (!empty($displayed_no_kk)) {
    // Bangun IN clause dengan placeholder
    $placeholders = implode(',', array_fill(0, count($displayed_no_kk), '?'));
    $mem_sql = "SELECT id, nama, username, email, nik, no_kk, alamat, kelamin, no_hp, foto, status_keluarga, pekerjaan, status_perkawinan, pendidikan, agama, rt, rw 
                FROM users 
                WHERE role = 'Warga' AND no_kk IN ($placeholders)
                ORDER BY FIELD(status_keluarga, 'Kepala Keluarga', 'Suami', 'Istri', 'Anak', 'Famili Lain'), id ASC";
    
    $stmt_mem = mysqli_prepare($koneksi, $mem_sql);
    if ($stmt_mem) {
        $types_mem = str_repeat('s', count($displayed_no_kk));
        mysqli_stmt_bind_param($stmt_mem, $types_mem, ...$displayed_no_kk);
        mysqli_stmt_execute($stmt_mem);
        $mem_res = mysqli_stmt_get_result($stmt_mem);
        while ($m = mysqli_fetch_assoc($mem_res)) {
            $members_by_kk[$m['no_kk']][] = $m;
        }
        mysqli_stmt_close($stmt_mem);
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
.status-tag--suami {
    background: #e0e7ff;
    color: #4338ca;
    border: 1px solid #c7d2fe;
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
.pekerjaan-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.76rem;
    font-weight: 600;
    color: #1e293b;
    background: #f1f5f9;
    padding: 3px 8px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    white-space: nowrap;
}
.pekerjaan-badge--irt {
    background: #fdf2f8;
    color: #9d174d;
    border-color: #fbcfe8;
}
.pekerjaan-badge--pelajar {
    background: #ecfdf5;
    color: #047857;
    border-color: #a7f3d0;
}
.pekerjaan-badge--swasta {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #bfdbfe;
}
.pekerjaan-badge--pns {
    background: #faf5ff;
    color: #6b21a8;
    border-color: #e9d5ff;
}
.kawin-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.74rem;
    font-weight: 600;
    color: #475569;
    background: #f8fafc;
    padding: 2px 7px;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    white-space: nowrap;
}
.kawin-badge--cerai {
    background: #fff1f2;
    color: #be123c;
    border-color: #fecdd3;
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
.btn-edit-member {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    padding: 5px 10px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
}
.btn-edit-member:hover {
    background: #2563eb;
    color: #ffffff;
}
.btn-split-kk {
    background: #fdf2f8;
    color: #db2777;
    border: 1px solid #fbcfe8;
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
.btn-split-kk:hover {
    background: #db2777;
    color: #ffffff;
}
.modal-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
.modal-form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.modal-form-group.full-width {
    grid-column: 1 / -1;
}
.modal-form-group label {
    font-size: 0.86rem;
    font-weight: 700;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 6px;
}
.modal-form-group input, 
.modal-form-group select, 
.modal-form-group textarea {
    padding: 9px 12px;
    border: 1.5px solid var(--line);
    border-radius: 8px;
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.2s;
    background: #ffffff;
}
.modal-form-group input:focus, 
.modal-form-group select:focus, 
.modal-form-group textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
}
.modal-info-alert {
    background: #f0f9ff;
    border: 1px solid #b9e6fe;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 0.86rem;
    color: #0369a1;
    margin-bottom: 18px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.split-members-list {
    border: 1.5px solid var(--line);
    border-radius: 8px;
    padding: 10px 14px;
    max-height: 180px;
    overflow-y: auto;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.split-member-item {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #ffffff;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid var(--line);
    cursor: pointer;
}
.split-member-item:hover {
    background: #f0fdf4;
    border-color: #86efac;
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
                    <strong style="color: var(--text);"><?php echo $is_admin ? 'Kelola Kartu Keluarga (KK)' : 'Data Keluarga Saya'; ?></strong>
                </div>
            </div>
            <?php if ($is_admin): ?>
            <div class="app-topbar__actions">
                <a href="kelola_kk_mobile.php" class="ghost-btn" style="text-decoration:none; color:var(--primary); font-weight:700;">
                    <i class="fa-solid fa-mobile-screen"></i> Mode HP / Mobile
                </a>
                <a href="kelola_warga.php" class="ghost-btn" style="text-decoration:none;">
                    <i class="fa-solid fa-users"></i> Data Warga
                </a>
            </div>
            <?php endif; ?>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-people-roof" style="color: var(--primary);"></i> <?php echo $is_admin ? 'Pengelompokan Kartu Keluarga (KK)' : 'Data Keluarga Saya'; ?></h1>
                    <p><?php echo $is_admin ? 'Data 100+ warga telah dikelompokkan ke dalam Kartu Keluarga lengkap dengan NIK, No. KK, status keluarga, dan alamat.' : 'Informasi lengkap anggota keluarga Anda yang terdaftar dalam sistem RT/RW.'; ?></p>
                </div>
            </div>

            <!-- Kartu Statistik -->
            <section class="stat-grid">
                <article class="stat-card">
                    <span class="stat-card__label"><?php echo $is_admin ? 'Total Kartu Keluarga' : 'Kartu Keluarga'; ?></span>
                    <h2 class="stat-card__value"><?php echo number_format($total_kk); ?> KK</h2>
                    <div class="stat-card__muted">
                        <?php if ($is_admin): ?>
                            <span style="color:#0284c7; font-weight:700;">RT 06: <?php echo $total_kk_06; ?> KK</span> &bull; 
                            <span style="color:#059669; font-weight:700;">RT 07: <?php echo $total_kk_07; ?> KK</span>
                        <?php else: ?>
                            No. KK: <?php echo htmlspecialchars($user_no_kk); ?>
                        <?php endif; ?>
                    </div>
                </article>
                <article class="stat-card">
                    <span class="stat-card__label">Kepala Keluarga</span>
                    <h2 class="stat-card__value"><?php echo number_format($total_kepala); ?> Orang</h2>
                    <div class="stat-card__muted">Penanggung jawab keluarga</div>
                </article>
                <article class="stat-card">
                    <span class="stat-card__label"><?php echo $is_admin ? 'Total Warga Terdaftar' : 'Total Anggota Keluarga'; ?></span>
                    <h2 class="stat-card__value"><?php echo number_format($total_warga); ?> Orang</h2>
                    <div class="stat-card__muted">
                        <?php if ($is_admin): ?>
                            <span style="color:#0284c7; font-weight:700;">RT 06: <?php echo $total_warga_06; ?></span> &bull; 
                            <span style="color:#059669; font-weight:700;">RT 07: <?php echo $total_warga_07; ?> Jiwa</span>
                        <?php else: ?>
                            Termasuk Kepala, Istri & Anak
                        <?php endif; ?>
                    </div>
                </article>
                <article class="stat-card">
                    <span class="stat-card__label"><?php echo $is_admin ? 'Rata-rata Anggota / KK' : 'Anak'; ?></span>
                    <h2 class="stat-card__value"><?php echo $is_admin ? $avg_anggota . ' Jiwa' : number_format($total_anak) . ' Orang'; ?></h2>
                    <div class="stat-card__muted"><?php echo $is_admin ? 'Kepadatan keluarga RT' : 'Jumlah anak dalam keluarga'; ?></div>
                </article>
            </section>

            <?php if ($is_admin): ?>
            <!-- Form Pencarian & Filter (Hanya Admin) -->
            <section class="glass-panel" style="padding: 18px 22px;">
                <form action="kelola_kk.php" method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                    <div style="display: flex; gap: 10px; flex: 1 1 300px; flex-wrap: wrap;">
                        <div style="flex: 1 1 220px; position: relative;">
                            <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari No. KK, Nama Kepala, NIK, atau Alamat..." style="width: 100%; padding: 10px 14px 10px 36px; border: 1px solid var(--line); border-radius: 8px;">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted);"></i>
                        </div>
                        <select name="rt" onchange="this.form.submit()" style="padding: 10px 14px; border: 1.5px solid var(--line); border-radius: 8px; background: #fff; min-width: 150px; font-weight:700; cursor:pointer;">
                            <option value="">Semua Wilayah RT</option>
                            <option value="06" <?php echo ($filter_rt === '06') ? 'selected' : ''; ?>>Wilayah RT 06</option>
                            <option value="07" <?php echo ($filter_rt === '07') ? 'selected' : ''; ?>>Wilayah RT 07</option>
                        </select>
                        <select name="anggota" onchange="this.form.submit()" style="padding: 10px 14px; border: 1.5px solid var(--line); border-radius: 8px; background: #fff; min-width: 150px; cursor:pointer;">
                            <option value="">Semua Ukuran KK</option>
                            <option value="2" <?php echo ($filter_anggota === '2') ? 'selected' : ''; ?>>2 Anggota</option>
                            <option value="3" <?php echo ($filter_anggota === '3') ? 'selected' : ''; ?>>3 Anggota</option>
                            <option value="4+" <?php echo ($filter_anggota === '4+') ? 'selected' : ''; ?>>4+ Anggota</option>
                        </select>
                        <button type="submit" class="primary-btn">
                            <i class="fa-solid fa-filter"></i> Filter
                        </button>
                        <?php if (!empty($search) || !empty($filter_anggota) || !empty($filter_rt)): ?>
                            <a href="kelola_kk.php" class="ghost-btn" style="text-decoration:none;">Reset</a>
                        <?php endif; ?>
                    </div>
                    <div style="color: var(--text-soft); font-size: 0.9rem;">
                        Menampilkan <strong><?php echo count($paged_kk_list); ?></strong> dari <strong><?php echo $total_filtered_kk; ?></strong> KK
                    </div>
                </form>
            </section>
            <?php endif; ?>

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
                                    <div style="background:#fef3c7; color:#b45309; padding:5px 12px; border-radius:8px; font-weight:800; font-size:0.84rem; display:inline-flex; align-items:center; gap:5px; border:1px solid #fde68a;">
                                        <i class="fa-solid fa-house-flag"></i> RT <?php echo htmlspecialchars($kk['rt'] ?? '06'); ?> / RW 19
                                    </div>
                                    <div style="font-size: 0.92rem; color: var(--text); font-weight: 700;">
                                        Kepala Keluarga: <span style="color: var(--primary);"><?php echo htmlspecialchars($kepala); ?></span>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <?php if ($is_admin): ?>
                                        <button type="button" class="btn-split-kk" onclick="openSplitKkModal('<?php echo $no_kk; ?>')" title="Pisah KK / Buat KK Baru (misal: Cerai atau Mandiri)">
                                            <i class="fa-solid fa-code-fork"></i> Pisah KK Baru
                                        </button>
                                    <?php endif; ?>
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
                                            <th style="width: 35px;">No</th>
                                            <th>Nama Lengkap</th>
                                            <th>NIK</th>
                                            <th>Hubungan Keluarga</th>
                                            <th>Pekerjaan</th>
                                            <th>Status Kawin</th>
                                            <th>Jenis Kelamin</th>
                                            <th>No. Telepon / HP</th>
                                            <?php if ($is_admin): ?>
                                                <th style="text-align: center; width: 100px;">Aksi</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = 1;
                                        foreach ($members as $mem): 
                                             $st = $mem['status_keluarga'] ?? 'Anggota';
                                             $badge_class = 'status-tag--famili';
                                             if ($st === 'Kepala Keluarga') $badge_class = 'status-tag--kepala';
                                             elseif ($st === 'Suami') $badge_class = 'status-tag--suami';
                                             elseif ($st === 'Istri') $badge_class = 'status-tag--istri';
                                             elseif ($st === 'Anak') $badge_class = 'status-tag--anak';

                                             $pek = $mem['pekerjaan'] ?? 'Belum / Tidak Bekerja';
                                             $pek_cls = 'pekerjaan-badge';
                                             $pek_icon = 'fa-briefcase';
                                             if (stripos($pek, 'Rumah Tangga') !== false) {
                                                 $pek_cls .= ' pekerjaan-badge--irt';
                                                 $pek_icon = 'fa-house-user';
                                             } elseif (stripos($pek, 'Pelajar') !== false || stripos($pek, 'Mahasiswa') !== false) {
                                                 $pek_cls .= ' pekerjaan-badge--pelajar';
                                                 $pek_icon = 'fa-graduation-cap';
                                             } elseif (stripos($pek, 'PNS') !== false || stripos($pek, 'ASN') !== false) {
                                                 $pek_cls .= ' pekerjaan-badge--pns';
                                                 $pek_icon = 'fa-building-columns';
                                             } elseif (stripos($pek, 'Swasta') !== false) {
                                                 $pek_cls .= ' pekerjaan-badge--swasta';
                                                 $pek_icon = 'fa-user-tie';
                                             }

                                             $kwn = $mem['status_perkawinan'] ?? 'Kawin';
                                             $kwn_cls = 'kawin-badge';
                                             if (stripos($kwn, 'Cerai') !== false) {
                                                 $kwn_cls .= ' kawin-badge--cerai';
                                             }
                                        ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td>
                                                    <div style="font-weight: 700; color: var(--text);">
                                                        <?php echo htmlspecialchars($mem['nama']); ?>
                                                    </div>
                                                </td>
                                                <td style="font-family: monospace; font-size: 0.92rem; color: var(--text-soft);">
                                                    <?php echo htmlspecialchars($mem['nik'] ?? '-'); ?>
                                                </td>
                                                <td>
                                                    <span class="status-tag <?php echo $badge_class; ?>">
                                                        <?php if ($st === 'Kepala Keluarga'): ?>
                                                            <i class="fa-solid fa-crown" style="font-size: 0.7rem;"></i>
                                                        <?php elseif ($st === 'Suami'): ?>
                                                            <i class="fa-solid fa-mars" style="font-size: 0.7rem;"></i>
                                                        <?php elseif ($st === 'Istri'): ?>
                                                            <i class="fa-solid fa-heart" style="font-size: 0.7rem;"></i>
                                                        <?php elseif ($st === 'Anak'): ?>
                                                            <i class="fa-solid fa-child" style="font-size: 0.7rem;"></i>
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($st); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="<?php echo $pek_cls; ?>">
                                                        <i class="fa-solid <?php echo $pek_icon; ?>"></i>
                                                        <?php echo htmlspecialchars($pek); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="<?php echo $kwn_cls; ?>">
                                                        <?php echo htmlspecialchars($kwn); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($mem['kelamin'] === 'Laki-laki'): ?>
                                                        <span style="color: #0284c7; font-weight: 600; font-size: 0.88rem;"><i class="fa-solid fa-mars"></i> Laki-laki</span>
                                                    <?php else: ?>
                                                        <span style="color: #db2777; font-weight: 600; font-size: 0.88rem;"><i class="fa-solid fa-venus"></i> Perempuan</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="color: var(--text-soft); font-size: 0.88rem;">
                                                    <?php echo htmlspecialchars($mem['no_hp'] ?? '-'); ?>
                                                </td>
                                                <?php if ($is_admin): ?>
                                                    <td style="text-align: center;">
                                                        <button type="button" class="btn-edit-member" onclick='openEditMemberModal(<?php echo json_encode($mem, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Perbaiki Status KK, Pekerjaan & Hubungan">
                                                            <i class="fa-solid fa-pen-to-square"></i> Perbaiki
                                                        </button>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    <?php endforeach; ?>

                    <!-- Pagination (Hanya Admin) -->
                    <?php if ($is_admin && $total_pages > 1): ?>
                        <div style="display: flex; justify-content: center; gap: 8px; margin-top: 20px; flex-wrap: wrap;">
                            <?php if ($page > 1): ?>
                                <a href="kelola_kk.php?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>&anggota=<?php echo urlencode($filter_anggota); ?>&rt=<?php echo urlencode($filter_rt); ?>" class="ghost-btn" style="text-decoration:none;">&laquo; Sebelumnya</a>
                            <?php endif; ?>

                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                <a href="kelola_kk.php?page=<?php echo $p; ?>&q=<?php echo urlencode($search); ?>&anggota=<?php echo urlencode($filter_anggota); ?>&rt=<?php echo urlencode($filter_rt); ?>" class="<?php echo ($p === $page) ? 'primary-btn' : 'ghost-btn'; ?>" style="text-decoration:none; min-width: 40px; text-align: center;">
                                    <?php echo $p; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="kelola_kk.php?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>&anggota=<?php echo urlencode($filter_anggota); ?>&rt=<?php echo urlencode($filter_rt); ?>" class="ghost-btn" style="text-decoration:none;">Selanjutnya &raquo;</a>
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
                        <div class="kk-grid-val">: <strong id="modalKkRtRw">06 / 05</strong></div>
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

            <!-- Tabel Anggota Keluarga Format Resmi Dukcapil / KK Dunia Nyata -->
            <table class="kk-official-table">
                <thead>
                    <tr>
                        <th style="width: 30px;">No</th>
                        <th>Nama Lengkap</th>
                        <th>NIK</th>
                        <th>Jenis Kelamin</th>
                        <th>Agama</th>
                        <th>Pendidikan</th>
                        <th>Jenis Pekerjaan</th>
                        <th>Status Perkawinan</th>
                        <th>Status Hubungan</th>
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
                    <div style="font-weight: 700; margin-bottom: 50px;" id="modalTitleRt">KETUA RT 06 / RW 19</div>
                    <div style="font-weight: 800; text-decoration: underline;" id="modalSigRt">DIMAS (KETUA RT 06)</div>
                </div>
                <div class="kk-sig-box">
                    <div>Menyetujui,</div>
                    <div style="font-weight: 700; margin-bottom: 50px;">KETUA RW 19</div>
                    <div style="font-weight: 800; text-decoration: underline;">FABIAN (KETUA RW 19)</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($is_admin): ?>
<!-- MODAL PERBAIKI DATA KK / HUBUNGAN KELUARGA -->
<div class="kk-modal-backdrop" id="editMemberModalBackdrop">
    <div class="kk-modal-container" style="max-width: 650px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1.5px solid var(--line); padding-bottom: 14px;">
            <h3 style="margin: 0; color: var(--text); font-size: 1.15rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-user-pen" style="color: var(--primary);"></i> Perbaiki Data KK & Hubungan Keluarga
            </h3>
            <button type="button" class="ghost-btn" onclick="closeEditMemberModal()" style="padding: 4px 10px;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-info-alert">
            <i class="fa-solid fa-circle-info" style="font-size: 1.1rem; margin-top: 2px;"></i>
            <div>
                <strong>Perhatian:</strong> Perbaiki kesalahan role/status hubungan warga yang salah input. Perempuan juga dapat berstatus <strong>Kepala Keluarga</strong> (misal cerai atau KK mandiri), dan anggota lain disesuaikan menjadi Suami, Istri, Anak, atau Famili Lain.
            </div>
        </div>

        <form action="proses_kelola_kk.php" method="POST" id="editMemberForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="edit_anggota_kk">
            <input type="hidden" name="id_user" id="edit_id_user" value="">
            <input type="hidden" name="redirect_to" value="kelola_kk.php">

            <div class="modal-form-grid">
                <div class="modal-form-group">
                    <label for="edit_nama"><i class="fa-solid fa-user"></i> Nama Lengkap</label>
                    <input type="text" id="edit_nama" name="nama" required>
                </div>

                <div class="modal-form-group">
                    <label for="edit_nik"><i class="fa-solid fa-id-card"></i> NIK (16 Digit)</label>
                    <input type="text" id="edit_nik" name="nik" maxlength="16" pattern="\d{16}" title="16 digit angka" required>
                </div>

                <div class="modal-form-group">
                    <label for="edit_kelamin"><i class="fa-solid fa-venus-mars"></i> Jenis Kelamin</label>
                    <select id="edit_kelamin" name="kelamin" required>
                        <option value="Laki-laki">Laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="edit_status_keluarga"><i class="fa-solid fa-people-arrows"></i> Hubungan Dalam Keluarga</label>
                    <select id="edit_status_keluarga" name="status_keluarga" required>
                        <option value="Kepala Keluarga">👑 Kepala Keluarga</option>
                        <option value="Suami">👨 Suami</option>
                        <option value="Istri">👩 Istri</option>
                        <option value="Anak">👶 Anak</option>
                        <option value="Famili Lain">👥 Famili Lain</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="edit_pekerjaan"><i class="fa-solid fa-briefcase"></i> Jenis Pekerjaan</label>
                    <select id="edit_pekerjaan" name="pekerjaan" required>
                        <option value="Karyawan Swasta">Karyawan Swasta (Pekerja)</option>
                        <option value="Pegawai Negeri Sipil (PNS / ASN)">Pegawai Negeri Sipil (PNS / ASN)</option>
                        <option value="Wiraswasta / Pedagang">Wiraswasta / Pedagang</option>
                        <option value="Mengurus Rumah Tangga">Mengurus Rumah Tangga (Ibu Rumah Tangga)</option>
                        <option value="Pelajar / Mahasiswa">Pelajar / Mahasiswa</option>
                        <option value="Buruh Harian Lepas">Buruh Harian Lepas</option>
                        <option value="Guru / Dosen">Guru / Tenaga Pengajar</option>
                        <option value="Tenaga Medis">Tenaga Medis / Kesehatan</option>
                        <option value="TNI / POLRI">TNI / POLRI</option>
                        <option value="Pensiunan">Pensiunan</option>
                        <option value="Belum / Tidak Bekerja">Belum / Tidak Bekerja</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="edit_status_perkawinan"><i class="fa-solid fa-ring"></i> Status Perkawinan</label>
                    <select id="edit_status_perkawinan" name="status_perkawinan" required>
                        <option value="Kawin">Kawin Tercatat</option>
                        <option value="Belum Kawin">Belum Kawin</option>
                        <option value="Cerai Hidup">Cerai Hidup</option>
                        <option value="Cerai Mati">Cerai Mati</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="edit_pendidikan"><i class="fa-solid fa-graduation-cap"></i> Pendidikan Terakhir</label>
                    <select id="edit_pendidikan" name="pendidikan" required>
                        <option value="Tidak / Belum Sekolah">Tidak / Belum Sekolah</option>
                        <option value="SD / Sederajat">SD / Sederajat</option>
                        <option value="SMP / Sederajat">SMP / Sederajat</option>
                        <option value="SLTA / Sederajat">SMA / SMK / SLTA Sederajat</option>
                        <option value="Diploma (D1 - D4)">Diploma (D1 - D4)</option>
                        <option value="Sarjana (S1)">Sarjana (S1)</option>
                        <option value="Magister (S2)">Magister (S2)</option>
                        <option value="Doktor (S3)">Doktor (S3)</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="edit_agama"><i class="fa-solid fa-mosque"></i> Agama</label>
                    <select id="edit_agama" name="agama" required>
                        <option value="Islam">Islam</option>
                        <option value="Kristen Protestan">Kristen Protestan</option>
                        <option value="Katolik">Katolik</option>
                        <option value="Hindu">Hindu</option>
                        <option value="Buddha">Buddha</option>
                        <option value="Khonghucu">Khonghucu</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="edit_rt"><i class="fa-solid fa-house-flag"></i> Wilayah RT</label>
                    <select id="edit_rt" name="rt" required>
                        <option value="06">Wilayah RT 06</option>
                        <option value="07">Wilayah RT 07</option>
                    </select>
                </div>

                <div class="modal-form-group full-width">
                    <label for="edit_no_kk"><i class="fa-solid fa-address-card"></i> Nomor Kartu Keluarga (KK)</label>
                    <input type="text" id="edit_no_kk" name="no_kk" maxlength="16" pattern="\d{16}" title="16 digit angka" required>
                    <small style="color: var(--text-soft); font-size: 0.8rem; margin-top: 3px;">
                        Ganti No. KK jika warga ini dipindahkan ke KK lain atau membuat KK baru.
                    </small>
                </div>

                <div class="modal-form-group full-width">
                    <label for="edit_alamat"><i class="fa-solid fa-location-dot"></i> Alamat Rumah / Domisili</label>
                    <textarea id="edit_alamat" name="alamat" rows="2" required></textarea>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1.5px solid var(--line); padding-top: 16px;">
                <button type="button" class="ghost-btn" onclick="closeEditMemberModal()">Batal</button>
                <button type="submit" class="primary-btn">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PISAH KK (CERAI / KK MANDIRI BARU) -->
<div class="kk-modal-backdrop" id="splitKkModalBackdrop">
    <div class="kk-modal-container" style="max-width: 680px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1.5px solid var(--line); padding-bottom: 14px;">
            <h3 style="margin: 0; color: var(--text); font-size: 1.15rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-code-fork" style="color: #db2777;"></i> Pisah Kartu Keluarga (KK Baru / Cerai / Mandiri)
            </h3>
            <button type="button" class="ghost-btn" onclick="closeSplitKkModal()" style="padding: 4px 10px;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-info-alert" style="background: #fdf2f8; border-color: #fbcfe8; color: #9d174d;">
            <i class="fa-solid fa-code-fork" style="font-size: 1.1rem; margin-top: 2px;"></i>
            <div>
                <strong>Pemisahan KK:</strong> Gunakan fitur ini jika ada warga (misal perempuan cerai) yang membuat KK terpisah menjadi <strong>Kepala Keluarga</strong> mandiri, serta memilih anak-anak yang ikut bergabung ke KK baru tersebut.
            </div>
        </div>

        <form action="proses_kelola_kk.php" method="POST" id="splitKkForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="pisah_kk">
            <input type="hidden" name="redirect_to" value="kelola_kk.php">

            <div class="modal-form-group" style="margin-bottom: 16px;">
                <label for="split_id_kepala"><i class="fa-solid fa-crown" style="color: #eab308;"></i> Pilih Anggota Yang Menjadi Kepala Keluarga Baru</label>
                <select id="split_id_kepala" name="id_kepala" onchange="onSplitKepalaChange()" required>
                    <!-- Dinamis via JS -->
                </select>
            </div>

            <div class="modal-form-grid">
                <div class="modal-form-group">
                    <label for="split_no_kk_baru">
                        <i class="fa-solid fa-address-card"></i> No. KK Baru (16 Digit)
                    </label>
                    <div style="display: flex; gap: 6px;">
                        <input type="text" id="split_no_kk_baru" name="no_kk_baru" maxlength="16" pattern="\d{16}" placeholder="Contoh: 327501..." style="flex: 1;" required>
                        <button type="button" class="ghost-btn" onclick="generateRandomKk()" title="Buat No. KK Acak" style="white-space: nowrap; padding: 6px 10px; font-size: 0.8rem;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Acak
                        </button>
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="split_alamat_baru"><i class="fa-solid fa-location-dot"></i> Alamat KK Baru</label>
                    <input type="text" id="split_alamat_baru" name="alamat_baru" required>
                </div>
            </div>

            <div class="modal-form-group full-width" style="margin-bottom: 20px;">
                <label><i class="fa-solid fa-child"></i> Pilih Anak / Anggota Yang Ikut ke KK Baru Ini (Opsional)</label>
                <div class="split-members-list" id="splitChildrenContainer">
                    <!-- Dinamis via JS -->
                </div>
                <small style="color: var(--text-soft); font-size: 0.8rem; margin-top: 4px;">
                    Anggota yang dicentang akan otomatis dipindahkan ke KK Baru bersama Kepala Keluarga di atas dengan peran tetap Anak/anggota keluarga.
                </small>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1.5px solid var(--line); padding-top: 16px;">
                <button type="button" class="ghost-btn" onclick="closeSplitKkModal()">Batal</button>
                <button type="submit" class="primary-btn" style="background: #db2777; border-color: #db2777;">
                    <i class="fa-solid fa-check"></i> Proses Pisah KK
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Data KK dalam format JSON untuk modal cetak & perbaikan
const kkDataStore = <?php echo json_encode($members_by_kk, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const kkMetaStore = <?php echo json_encode($paged_kk_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
let activeSplitKkNo = '';

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

    const rtKk = meta ? (meta.rt || '06') : (members[0] && members[0].rt ? members[0].rt : '06');
    document.getElementById('modalKkNumber').textContent = 'No. ' + noKk;
    document.getElementById('modalKkKepala').textContent = kepala;
    document.getElementById('modalKkAlamat').textContent = alamat;
    document.getElementById('modalSigKepala').textContent = (kepala || '-').toUpperCase();
    document.getElementById('modalKkRtRw').textContent = rtKk + ' / 19';

    if (rtKk === '06') {
        document.getElementById('modalTitleRt').textContent = 'KETUA RT 06 / RW 19';
        document.getElementById('modalSigRt').textContent = 'DIMAS (KETUA RT 06)';
    } else {
        document.getElementById('modalTitleRt').textContent = 'KETUA RT 07 / RW 19';
        document.getElementById('modalSigRt').textContent = 'BAMBANG (KETUA RT 07)';
    }

    const tbody = document.getElementById('modalKkMembersTable');
    tbody.innerHTML = '';

    members.forEach(function(m, idx) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="text-align: center;">${idx + 1}</td>
            <td style="font-weight: 700;">${m.nama}</td>
            <td style="font-family: monospace; letter-spacing: 0.05em; text-align: center;">${m.nik || '-'}</td>
            <td style="text-align: center;">${(m.kelamin === 'Laki-laki') ? 'LAKI-LAKI' : 'PEREMPUAN'}</td>
            <td style="text-align: center;">${(m.agama || 'ISLAM').toUpperCase()}</td>
            <td style="text-align: center;">${(m.pendidikan || 'SLTA / SEDERAJAT').toUpperCase()}</td>
            <td style="text-align: center; font-weight: 600;">${(m.pekerjaan || 'BELUM / TIDAK BEKERJA').toUpperCase()}</td>
            <td style="text-align: center;">${(m.status_perkawinan || 'KAWIN').toUpperCase()}</td>
            <td style="text-align: center; font-weight: 700;">${(m.status_keluarga || 'ANGGOTA').toUpperCase()}</td>
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

// ================= MODAL PERBAIKI ANGGOTA KK =================
function openEditMemberModal(mem) {
    if (!mem) return;
    document.getElementById('edit_id_user').value = mem.id;
    document.getElementById('edit_nama').value = mem.nama || '';
    document.getElementById('edit_nik').value = mem.nik || '';
    document.getElementById('edit_kelamin').value = mem.kelamin || 'Laki-laki';
    document.getElementById('edit_no_kk').value = mem.no_kk || '';
    document.getElementById('edit_status_keluarga').value = mem.status_keluarga || 'Anak';
    document.getElementById('edit_pekerjaan').value = mem.pekerjaan || 'Belum / Tidak Bekerja';
    document.getElementById('edit_status_perkawinan').value = mem.status_perkawinan || 'Kawin';
    document.getElementById('edit_pendidikan').value = mem.pendidikan || 'SLTA / Sederajat';
    document.getElementById('edit_agama').value = mem.agama || 'Islam';
    document.getElementById('edit_rt').value = mem.rt || '06';
    document.getElementById('edit_alamat').value = mem.alamat || '';

    const modal = document.getElementById('editMemberModalBackdrop');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeEditMemberModal() {
    const modal = document.getElementById('editMemberModalBackdrop');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// ================= MODAL PISAH KK =================
function openSplitKkModal(noKk) {
    activeSplitKkNo = noKk;
    const members = kkDataStore[noKk] || [];
    if (!members.length) {
        alert('Data KK tidak memiliki anggota untuk dipisah.');
        return;
    }

    let meta = null;
    for (let i = 0; i < kkMetaStore.length; i++) {
        if (kkMetaStore[i].no_kk === noKk) {
            meta = kkMetaStore[i];
            break;
        }
    }

    const selectKepala = document.getElementById('split_id_kepala');
    selectKepala.innerHTML = '';

    members.forEach(function(m) {
        const opt = document.createElement('option');
        opt.value = m.id;
        const iconGender = (m.kelamin === 'Perempuan') ? '♀' : '♂';
        opt.textContent = `${m.nama} (${iconGender} ${m.kelamin} - Saat ini: ${m.status_keluarga || 'Anggota'})`;
        selectKepala.appendChild(opt);
    });

    document.getElementById('split_alamat_baru').value = meta ? meta.alamat : (members[0] ? members[0].alamat : '');
    document.getElementById('split_no_kk_baru').value = '';

    onSplitKepalaChange();

    const modal = document.getElementById('splitKkModalBackdrop');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function onSplitKepalaChange() {
    const selectedId = parseInt(document.getElementById('split_id_kepala').value) || 0;
    const members = kkDataStore[activeSplitKkNo] || [];
    const container = document.getElementById('splitChildrenContainer');
    container.innerHTML = '';

    const otherMembers = members.filter(m => parseInt(m.id) !== selectedId);

    if (otherMembers.length === 0) {
        container.innerHTML = '<div style="color:var(--text-soft); font-size:0.85rem; padding:8px;">Hanya ada 1 anggota di KK ini.</div>';
        return;
    }

    otherMembers.forEach(function(m) {
        const item = document.createElement('label');
        item.className = 'split-member-item';
        // Checklist default dicentang jika statusnya anak
        const isChild = (m.status_keluarga === 'Anak');
        item.innerHTML = `
            <input type="checkbox" name="ikut_anggota[]" value="${m.id}" ${isChild ? 'checked' : ''} style="width:16px; height:16px; cursor:pointer;">
            <div style="flex:1;">
                <strong style="color:var(--text); font-size:0.88rem;">${m.nama}</strong>
                <span style="font-size:0.8rem; color:var(--text-soft); margin-left:6px;">(${m.status_keluarga || 'Anggota'}, NIK: ${m.nik || '-'})</span>
            </div>
        `;
        container.appendChild(item);
    });
}

function closeSplitKkModal() {
    const modal = document.getElementById('splitKkModalBackdrop');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function generateRandomKk() {
    // Generate 16 digit No KK acak dengan prefix 327501 (Bekasi) atau acak
    const prefix = '327501';
    let rand = '';
    for (let i = 0; i < 10; i++) {
        rand += Math.floor(Math.random() * 10);
    }
    document.getElementById('split_no_kk_baru').value = prefix + rand;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeKkModal();
        closeEditMemberModal();
        closeSplitKkModal();
    }
});

const editModal = document.getElementById('editMemberModalBackdrop');
if (editModal) {
    editModal.addEventListener('click', function(e) {
        if (e.target === this) closeEditMemberModal();
    });
}

const splitModal = document.getElementById('splitKkModalBackdrop');
if (splitModal) {
    splitModal.addEventListener('click', function(e) {
        if (e.target === this) closeSplitKkModal();
    });
}

document.getElementById('kkModalBackdrop').addEventListener('click', function(e) {
    if (e.target === this) {
        closeKkModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
