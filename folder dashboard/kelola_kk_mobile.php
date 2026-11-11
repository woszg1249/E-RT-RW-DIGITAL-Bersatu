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

$nama_user = $_SESSION['nama'];
$role_user = $_SESSION['role'] ?? 'ADMIN';

// Hitung Statistik
$res_kk = db_query($koneksi, "SELECT COUNT(DISTINCT no_kk) as total FROM users WHERE role = 'Warga' AND no_kk IS NOT NULL AND no_kk != ''", "");
$stat_kk = ($res_kk && mysqli_num_rows($res_kk) > 0) ? mysqli_fetch_assoc($res_kk) : [];
$total_kk = (int)($stat_kk['total'] ?? 0);

$res_warga = db_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga'", "");
$stat_warga = ($res_warga && mysqli_num_rows($res_warga) > 0) ? mysqli_fetch_assoc($res_warga) : [];
$total_warga = (int)($stat_warga['total'] ?? 0);

$res_kepala = db_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga' AND status_keluarga = 'Kepala Keluarga'", "");
$stat_kepala = ($res_kepala && mysqli_num_rows($res_kepala) > 0) ? mysqli_fetch_assoc($res_kepala) : [];
$total_kepala = (int)($stat_kepala['total'] ?? 0);

$res_anak = db_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga' AND status_keluarga = 'Anak'", "");
$stat_anak = ($res_anak && mysqli_num_rows($res_anak) > 0) ? mysqli_fetch_assoc($res_anak) : [];
$total_anak = (int)($stat_anak['total'] ?? 0);

$avg_anggota = ($total_kk > 0) ? round($total_warga / $total_kk, 1) : 0;

// Filter & Pencarian
$search = trim($_GET['q'] ?? '');
$filter_anggota = trim($_GET['anggota'] ?? '');
$filter_rt = trim($_GET['rt'] ?? '');

$where_clauses = ["role = 'Warga'", "no_kk IS NOT NULL", "no_kk != ''"];
$params = [];
$types = "";

if (!empty($search)) {
    $search_param = "%$search%";
    $where_clauses[] = "(no_kk LIKE ? OR nama LIKE ? OR alamat LIKE ? OR nik LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($filter_rt)) {
    $where_clauses[] = "rt = ?";
    $params[] = $filter_rt;
    $types .= "s";
}

$where_sql = implode(' AND ', $where_clauses);

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

// Execute query dengan prepared statement
$stmt = mysqli_prepare($koneksi, $kk_query_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res_all_kk = mysqli_stmt_get_result($stmt);
$total_filtered_kk = mysqli_num_rows($res_all_kk);
mysqli_stmt_close($stmt);

// Pagination untuk mobile: 6 per halaman
$limit = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$total_pages = ceil($total_filtered_kk / $limit);
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

$paged_kk_sql = $kk_query_sql . " LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($koneksi, $paged_kk_sql);
if (!empty($params)) {
    $types_with_limit = $types . "ii";
    $params_with_limit = array_merge($params, [$limit, $offset]);
    mysqli_stmt_bind_param($stmt, $types_with_limit, ...$params_with_limit);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
}
mysqli_stmt_execute($stmt);
$res_paged_kk = mysqli_stmt_get_result($stmt);

$paged_kk_list = [];
while ($row = mysqli_fetch_assoc($res_paged_kk)) {
    $paged_kk_list[] = $row;
}
mysqli_stmt_close($stmt);

$displayed_no_kk = array_column($paged_kk_list, 'no_kk');
$members_by_kk = [];

if (!empty($displayed_no_kk)) {
    $placeholders = implode(',', array_fill(0, count($displayed_no_kk), '?'));
    $types = str_repeat('s', count($displayed_no_kk));
    
    $mem_sql = "SELECT id, nama, username, email, nik, no_kk, alamat, kelamin, no_hp, foto, status_keluarga, pekerjaan, status_perkawinan, pendidikan, agama 
                FROM users 
                WHERE role = 'Warga' AND no_kk IN ($placeholders)
                ORDER BY FIELD(status_keluarga, 'Kepala Keluarga', 'Suami', 'Istri', 'Anak', 'Famili Lain'), id ASC";
    
    $stmt = mysqli_prepare($koneksi, $mem_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$displayed_no_kk);
    mysqli_stmt_execute($stmt);
    $mem_res = mysqli_stmt_get_result($stmt);
    
    while ($m = mysqli_fetch_assoc($mem_res)) {
        $members_by_kk[$m['no_kk']][] = $m;
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kartu Keluarga (Mobile) - RT RW Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius: 14px;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
        body {
            background: var(--bg);
            color: var(--text);
            padding-bottom: 75px;
        }

        /* Top Bar */
        .m-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #ffffff;
            border-bottom: 1px solid var(--border);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .m-header__left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .m-header__back {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text);
            text-decoration: none;
            font-size: 1rem;
        }
        .m-header__title {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.2;
        }
        .m-header__sub {
            font-size: 0.72rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .m-header__desktop-btn {
            font-size: 0.75rem;
            background: #e0f2fe;
            color: var(--primary-dark);
            padding: 6px 10px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Quick Stat Pill Carousel */
        .m-stats {
            padding: 14px 16px 8px;
            display: flex;
            gap: 10px;
            overflow-x: auto;
            scrollbar-width: none;
        }
        .m-stats::-webkit-scrollbar {
            display: none;
        }
        .m-stat-pill {
            flex: 0 0 auto;
            background: #ffffff;
            border: 1px solid var(--border);
            padding: 10px 14px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            min-width: 120px;
        }
        .m-stat-pill__label {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
        }
        .m-stat-pill__val {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--primary);
            margin-top: 2px;
        }

        /* Search Section */
        .m-search-wrap {
            padding: 8px 16px 12px;
        }
        .m-search-box {
            display: flex;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 6px 10px 6px 12px;
            align-items: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            gap: 8px;
        }
        .m-search-box input {
            border: none;
            outline: none;
            flex: 1;
            font-size: 0.9rem;
            background: transparent;
        }
        .m-search-box button {
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 7px 12px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
        }
        .m-filter-chips {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            scrollbar-width: none;
            margin-top: 8px;
        }
        .m-filter-chips::-webkit-scrollbar {
            display: none;
        }
        .m-chip {
            padding: 5px 12px;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            white-space: nowrap;
        }
        .m-chip.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        /* KK Cards List */
        .m-list {
            padding: 4px 16px 16px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .m-kk-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }
        .m-kk-head {
            padding: 12px 14px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .m-kk-badge {
            font-size: 0.82rem;
            font-weight: 800;
            color: #0284c7;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .m-kk-count {
            background: #e0f2fe;
            color: #0369a1;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
        }
        .m-kk-body {
            padding: 14px;
        }
        .m-kk-kepala {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
        }
        .m-kk-address {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.4;
            display: flex;
            align-items: flex-start;
            gap: 6px;
            margin-bottom: 12px;
        }
        
        /* Members accordion list */
        .m-member-list {
            border-top: 1px dashed var(--border);
            padding-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .m-member-item {
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 8px 10px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .m-member-name {
            font-size: 0.84rem;
            font-weight: 700;
            color: var(--text);
        }
        .m-member-nik {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-family: monospace;
            margin-top: 1px;
        }
        .m-tag {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            white-space: nowrap;
        }
        .m-tag-kepala { background: #e0f2fe; color: #0369a1; }
        .m-tag-suami { background: #e0e7ff; color: #4338ca; }
        .m-tag-istri { background: #fce7f3; color: #be185d; }
        .m-tag-anak { background: #dcfce7; color: #15803d; }
        .m-tag-famili { background: #fef3c7; color: #b45309; }

        .m-btn-edit {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 0.72rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
        }
        .m-btn-split {
            padding: 8px 10px;
            background: #fdf2f8;
            border: 1px solid #fbcfe8;
            color: #db2777;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .m-form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 12px;
        }
        .m-form-group label {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text);
        }
        .m-form-group input, .m-form-group select, .m-form-group textarea {
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.84rem;
            width: 100%;
            box-sizing: border-box;
        }

        .m-kk-actions {
            margin-top: 12px;
            display: flex;
            gap: 8px;
        }
        .m-btn-print {
            flex: 1;
            padding: 8px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
        }
        .m-btn-copy {
            padding: 8px 12px;
            background: #f1f5f9;
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
        }

        /* Bottom Nav */
        .m-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #ffffff;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-around;
            padding: 8px 0 10px;
            z-index: 99;
        }
        .m-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.72rem;
            font-weight: 600;
        }
        .m-nav-item.active {
            color: var(--primary);
            font-weight: 800;
        }
        .m-nav-item i {
            font-size: 1.15rem;
        }

        /* Modal Fullscreen Cetak di HP */
        .m-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.75);
            z-index: 1000;
            padding: 12px;
            overflow-y: auto;
        }
        .m-modal.active {
            display: block;
        }
        .m-modal-paper {
            background: #ffffff;
            border-radius: 12px;
            padding: 18px 14px;
            color: #000;
            font-size: 0.8rem;
        }
        .m-modal-paper table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: 0.72rem;
        }
        .m-modal-paper th, .m-modal-paper td {
            border: 1px solid #333;
            padding: 6px 4px;
            text-align: left;
        }
        .m-modal-paper th {
            background: #f1f5f9;
            text-align: center;
        }

        @media print {
            body * { visibility: hidden; }
            #mPrintable, #mPrintable * { visibility: visible; }
            #mPrintable {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 10px;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- Header Mobile -->
    <header class="m-header">
        <div class="m-header__left">
            <a href="dashboard.php" class="m-header__back" title="Kembali ke Dashboard">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div class="m-header__title">Data Kartu Keluarga</div>
                <div class="m-header__sub">Sistem RT RW Mobile</div>
            </div>
        </div>
        <div>
            <!-- Tombol pindah ke versi Desktop -->
            <a href="kelola_kk.php?view=desktop" class="m-header__desktop-btn" title="Buka Tampilan Desktop">
                <i class="fa-solid fa-desktop"></i> Desktop
            </a>
        </div>
    </header>

    <!-- Ringkasan Statistik (Pill Scrollable) -->
    <div class="m-stats">
        <div class="m-stat-pill">
            <div class="m-stat-pill__label">Total KK</div>
            <div class="m-stat-pill__val"><?php echo $total_kk; ?> KK</div>
        </div>
        <div class="m-stat-pill">
            <div class="m-stat-pill__label">Kepala Keluarga</div>
            <div class="m-stat-pill__val"><?php echo $total_kepala; ?> Jiwa</div>
        </div>
        <div class="m-stat-pill">
            <div class="m-stat-pill__label">Total Warga</div>
            <div class="m-stat-pill__val"><?php echo $total_warga; ?> Jiwa</div>
        </div>
        <div class="m-stat-pill">
            <div class="m-stat-pill__label">Rata-rata/KK</div>
            <div class="m-stat-pill__val"><?php echo $avg_anggota; ?> Jiwa</div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="m-search-wrap">
        <form action="kelola_kk_mobile.php" method="GET">
            <div class="m-search-box">
                <i class="fa-solid fa-magnifying-glass" style="color: var(--text-muted);"></i>
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari No. KK, Nama, NIK...">
                <?php if (!empty($filter_anggota)): ?>
                    <input type="hidden" name="anggota" value="<?php echo htmlspecialchars($filter_anggota); ?>">
                <?php endif; ?>
                <button type="submit">Cari</button>
            </div>
        </form>

        <!-- Filter Chips -->
        <div class="m-filter-chips">
            <a href="kelola_kk_mobile.php?q=<?php echo urlencode($search); ?>" class="m-chip <?php echo empty($filter_anggota) ? 'active' : ''; ?>">Semua</a>
            <a href="kelola_kk_mobile.php?anggota=2&q=<?php echo urlencode($search); ?>" class="m-chip <?php echo ($filter_anggota === '2') ? 'active' : ''; ?>">2 Anggota</a>
            <a href="kelola_kk_mobile.php?anggota=3&q=<?php echo urlencode($search); ?>" class="m-chip <?php echo ($filter_anggota === '3') ? 'active' : ''; ?>">3 Anggota</a>
            <a href="kelola_kk_mobile.php?anggota=4+&q=<?php echo urlencode($search); ?>" class="m-chip <?php echo ($filter_anggota === '4+') ? 'active' : ''; ?>">4+ Anggota</a>
        </div>
    </div>

    <!-- Daftar Kartu Keluarga Mobile -->
    <main class="m-list">
        <?php if (empty($paged_kk_list)): ?>
            <div style="background:#fff; padding:30px 16px; border-radius:12px; text-align:center; border:1px solid var(--border);">
                <i class="fa-solid fa-folder-open" style="font-size:2rem; color:var(--text-muted); margin-bottom:8px;"></i>
                <h4 style="font-size:0.95rem;">Tidak Ada Data KK</h4>
                <p style="font-size:0.8rem; color:var(--text-muted); margin-top:4px;">Coba gunakan kata kunci lain.</p>
                <a href="kelola_kk_mobile.php" style="display:inline-block; margin-top:12px; font-size:0.8rem; color:var(--primary); font-weight:700; text-decoration:none;">Reset Pencarian</a>
            </div>
        <?php else: ?>
            <?php foreach ($paged_kk_list as $kk): 
                $no_kk = $kk['no_kk'];
                $members = $members_by_kk[$no_kk] ?? [];
                $kepala = $kk['kepala_keluarga'] ?? 'Belum Ditentukan';
                $alamat_kk = $kk['alamat'] ?? '-';
            ?>
                <article class="m-kk-card">
                    <div class="m-kk-head">
                        <div class="m-kk-badge">
                            <i class="fa-solid fa-address-card"></i>
                            <span><?php echo htmlspecialchars($no_kk); ?></span>
                        </div>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <span style="background:#fef3c7; color:#b45309; font-size:0.7rem; font-weight:800; padding:3px 7px; border-radius:6px; border:1px solid #fde68a;">
                                RT <?php echo htmlspecialchars($kk['rt'] ?? '06'); ?>
                            </span>
                            <span class="m-kk-count"><?php echo count($members); ?> Anggota</span>
                        </div>
                    </div>

                    <div class="m-kk-body">
                        <div class="m-kk-kepala">
                            <i class="fa-solid fa-crown" style="color:#0284c7; font-size:0.85rem;"></i>
                            <span><?php echo htmlspecialchars($kepala); ?></span>
                        </div>
                        <div class="m-kk-address">
                            <i class="fa-solid fa-location-dot" style="color:#ef4444; margin-top:2px;"></i>
                            <span><?php echo htmlspecialchars($alamat_kk); ?></span>
                        </div>

                        <!-- Daftar Anggota Keluarga -->
                        <div class="m-member-list">
                            <?php foreach ($members as $mem): 
                                $st = $mem['status_keluarga'] ?? 'Anggota';
                                $tag_class = 'm-tag-famili';
                                if ($st === 'Kepala Keluarga') $tag_class = 'm-tag-kepala';
                                elseif ($st === 'Suami') $tag_class = 'm-tag-suami';
                                elseif ($st === 'Istri') $tag_class = 'm-tag-istri';
                                elseif ($st === 'Anak') $tag_class = 'm-tag-anak';
                            ?>
                                <div class="m-member-item">
                                    <div style="flex:1;">
                                        <div class="m-member-name"><?php echo htmlspecialchars($mem['nama']); ?></div>
                                        <div class="m-member-nik">
                                            NIK: <?php echo htmlspecialchars($mem['nik']); ?> (<?php echo ($mem['kelamin'] === 'Laki-laki') ? 'L' : 'P'; ?>)
                                            <span style="color:#0284c7; font-weight:600; margin-left:4px;">&bull; <?php echo htmlspecialchars($mem['pekerjaan'] ?? 'Belum Bekerja'); ?></span>
                                        </div>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <span class="m-tag <?php echo $tag_class; ?>"><?php echo htmlspecialchars($st); ?></span>
                                        <button type="button" class="m-btn-edit" onclick='openMobileEditMember(<?php echo json_encode($mem, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Perbaiki Hubungan / KK">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Aksi Cepat -->
                        <div class="m-kk-actions">
                            <button type="button" class="m-btn-print" onclick="openMobileKkModal('<?php echo $no_kk; ?>')">
                                <i class="fa-solid fa-print"></i> Format Cetak
                            </button>
                            <button type="button" class="m-btn-split" onclick="openMobileSplitKk('<?php echo $no_kk; ?>')" title="Pisah KK">
                                <i class="fa-solid fa-code-fork"></i> Pisah KK
                            </button>
                            <button type="button" class="m-btn-copy" onclick="copyKkMobile('<?php echo $no_kk; ?>')">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

            <!-- Pagination Mobile -->
            <?php if ($total_pages > 1): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                    <?php if ($page > 1): ?>
                        <a href="kelola_kk_mobile.php?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>&anggota=<?php echo urlencode($filter_anggota); ?>&rt=<?php echo urlencode($filter_rt); ?>" class="m-chip">&laquo; Sebelumnya</a>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>

                    <span style="font-size:0.75rem; color:var(--text-muted); font-weight:700;">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></span>

                    <?php if ($page < $total_pages): ?>
                        <a href="kelola_kk_mobile.php?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>&anggota=<?php echo urlencode($filter_anggota); ?>&rt=<?php echo urlencode($filter_rt); ?>" class="m-chip">Selanjutnya &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Bottom Navigation Bar Khusus Mobile -->
    <nav class="m-bottom-nav">
        <a href="dashboard.php" class="m-nav-item">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>
        <a href="kelola_warga.php" class="m-nav-item">
            <i class="fa-solid fa-users"></i>
            <span>Warga</span>
        </a>
        <a href="kelola_kk_mobile.php" class="m-nav-item active">
            <i class="fa-solid fa-people-roof"></i>
            <span>Kartu KK</span>
        </a>
        <a href="input_kas.php" class="m-nav-item">
            <i class="fa-solid fa-wallet"></i>
            <span>Kas RT</span>
        </a>
        <a href="../profil.php" class="m-nav-item">
            <i class="fa-solid fa-user-gear"></i>
            <span>Profil</span>
        </a>
    </nav>

    <!-- Modal Cetak Kartu Keluarga Mobile -->
    <div class="m-modal" id="mobileKkModal">
        <div class="m-modal-paper" id="mPrintable">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #ccc; padding-bottom:8px; margin-bottom:12px;" class="no-print">
                <strong style="font-size:0.9rem;"><i class="fa-solid fa-print"></i> Format Dokumen KK</strong>
                <div style="display:flex; gap:6px;">
                    <button type="button" onclick="window.print()" style="background:#0284c7; color:#fff; border:none; padding:4px 10px; border-radius:6px; font-size:0.75rem; font-weight:700;">Cetak</button>
                    <button type="button" onclick="closeMobileKkModal()" style="background:#e2e8f0; color:#333; border:none; padding:4px 10px; border-radius:6px; font-size:0.75rem;">Tutup</button>
                </div>
            </div>

            <div style="text-align:center; margin-bottom:12px;">
                <div style="font-weight:900; font-size:1rem; letter-spacing:0.05em;">REPUBLIK INDONESIA</div>
                <div style="font-weight:800; font-size:0.85rem;">KARTU KELUARGA</div>
                <div style="font-weight:900; font-size:0.95rem; margin-top:4px;" id="mModalNoKk">No. 3275010000000000</div>
            </div>

            <div style="font-size:0.75rem; line-height:1.4; margin-bottom:10px;">
                <div><strong>Nama Kepala:</strong> <span id="mModalKepala">-</span></div>
                <div><strong>Alamat:</strong> <span id="mModalAlamat">-</span></div>
                <div><strong>RT / RW:</strong> 06 / 19 | <strong>Kelurahan:</strong> Margahayu</div>
                <div><strong>Kecamatan:</strong> Bekasi Timur | <strong>Kota:</strong> Kota Bekasi</div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width:25px;">No</th>
                        <th>Nama Lengkap</th>
                        <th>NIK</th>
                        <th>JK</th>
                        <th>Pekerjaan</th>
                        <th>Hubungan</th>
                    </tr>
                </thead>
                <tbody id="mModalTableBody">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>

            <div style="display:flex; justify-content:space-between; margin-top:20px; text-align:center; font-size:0.7rem;">
                <div style="width:120px;">
                    <div>Kepala Keluarga,</div>
                    <div style="height:35px;"></div>
                    <strong id="mModalSignKepala">NAMA KEPALA</strong>
                </div>
                <div style="width:120px;">
                    <div id="mModalTitleRt">Ketua RT 06/RW 19,</div>
                    <div style="height:35px;"></div>
                    <strong id="mModalSignRt">DIMAS (KETUA RT 06)</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit Anggota KK Mobile -->
    <div class="m-modal" id="mobileEditModal">
        <div class="m-modal-paper" style="border:none; max-width:92vw;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:8px; margin-bottom:12px;">
                <strong style="font-size:0.9rem; color:var(--text);"><i class="fa-solid fa-pen-to-square" style="color:var(--primary);"></i> Perbaiki Data KK Warga</strong>
                <button type="button" onclick="closeMobileEditMember()" style="background:transparent; border:none; font-size:1.1rem; color:#64748b; cursor:pointer;">&times;</button>
            </div>

            <form action="proses_kelola_kk.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit_anggota_kk">
                <input type="hidden" name="id_user" id="m_edit_id_user" value="">
                <input type="hidden" name="redirect_to" value="kelola_kk_mobile.php">

                <div class="m-form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" id="m_edit_nama" name="nama" required>
                </div>

                <div class="m-form-group">
                    <label>NIK (16 Digit)</label>
                    <input type="text" id="m_edit_nik" name="nik" maxlength="16" pattern="\d{16}" required>
                </div>

                <div class="m-form-group">
                    <label>Jenis Kelamin</label>
                    <select id="m_edit_kelamin" name="kelamin" required>
                        <option value="Laki-laki">Laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                    </select>
                </div>

                <div class="m-form-group">
                    <label>Hubungan Dalam Keluarga</label>
                    <select id="m_edit_status_keluarga" name="status_keluarga" required>
                        <option value="Kepala Keluarga">👑 Kepala Keluarga</option>
                        <option value="Suami">👨 Suami</option>
                        <option value="Istri">👩 Istri</option>
                        <option value="Anak">👶 Anak</option>
                        <option value="Famili Lain">👥 Famili Lain</option>
                    </select>
                </div>

                <div class="m-form-group">
                    <label>Jenis Pekerjaan</label>
                    <select id="m_edit_pekerjaan" name="pekerjaan" required>
                        <option value="Karyawan Swasta">Karyawan Swasta</option>
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

                <div class="m-form-group">
                    <label>Status Perkawinan</label>
                    <select id="m_edit_status_perkawinan" name="status_perkawinan" required>
                        <option value="Kawin">Kawin Tercatat</option>
                        <option value="Belum Kawin">Belum Kawin</option>
                        <option value="Cerai Hidup">Cerai Hidup</option>
                        <option value="Cerai Mati">Cerai Mati</option>
                    </select>
                </div>

                <div class="m-form-group">
                    <label>Pendidikan Terakhir</label>
                    <select id="m_edit_pendidikan" name="pendidikan" required>
                        <option value="Tidak / Belum Sekolah">Tidak / Belum Sekolah</option>
                        <option value="SD / Sederajat">SD / Sederajat</option>
                        <option value="SMP / Sederajat">SMP / Sederajat</option>
                        <option value="SLTA / Sederajat">SMA / SMK / Sederajat</option>
                        <option value="Diploma (D1 - D4)">Diploma (D1 - D4)</option>
                        <option value="Sarjana (S1)">Sarjana (S1)</option>
                        <option value="Magister (S2)">Magister (S2)</option>
                        <option value="Doktor (S3)">Doktor (S3)</option>
                    </select>
                </div>

                <div class="m-form-group">
                    <label>Agama</label>
                    <select id="m_edit_agama" name="agama" required>
                        <option value="Islam">Islam</option>
                        <option value="Kristen Protestan">Kristen Protestan</option>
                        <option value="Katolik">Katolik</option>
                        <option value="Hindu">Hindu</option>
                        <option value="Buddha">Buddha</option>
                        <option value="Khonghucu">Khonghucu</option>
                    </select>
                </div>

                <div class="m-form-group">
                    <label>Nomor KK (16 Digit)</label>
                    <input type="text" id="m_edit_no_kk" name="no_kk" maxlength="16" pattern="\d{16}" required>
                </div>

                <div class="m-form-group">
                    <label>Alamat Domisili</label>
                    <input type="text" id="m_edit_alamat" name="alamat" required>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px;">
                    <button type="button" onclick="closeMobileEditMember()" style="padding:7px 12px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; font-size:0.8rem; cursor:pointer;">Batal</button>
                    <button type="submit" style="padding:7px 14px; background:var(--primary); color:#fff; border:none; border-radius:6px; font-size:0.8rem; font-weight:700; cursor:pointer;">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Pisah KK Mobile -->
    <div class="m-modal" id="mobileSplitModal">
        <div class="m-modal-paper" style="border:none; max-width:92vw;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:8px; margin-bottom:12px;">
                <strong style="font-size:0.9rem; color:#db2777;"><i class="fa-solid fa-code-fork"></i> Pisah Kartu Keluarga</strong>
                <button type="button" onclick="closeMobileSplitKk()" style="background:transparent; border:none; font-size:1.1rem; color:#64748b; cursor:pointer;">&times;</button>
            </div>

            <form action="proses_kelola_kk.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="pisah_kk">
                <input type="hidden" name="redirect_to" value="kelola_kk_mobile.php">

                <div class="m-form-group">
                    <label>Kepala Keluarga di KK Baru</label>
                    <select id="m_split_id_kepala" name="id_kepala" onchange="onMobileSplitKepalaChange()" required></select>
                </div>

                <div class="m-form-group">
                    <label>No. KK Baru (16 Digit)</label>
                    <div style="display:flex; gap:6px;">
                        <input type="text" id="m_split_no_kk_baru" name="no_kk_baru" maxlength="16" pattern="\d{16}" required style="flex:1;">
                        <button type="button" onclick="generateMobileRandomKk()" style="padding:6px 10px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; font-size:0.75rem;">Acak</button>
                    </div>
                </div>

                <div class="m-form-group">
                    <label>Alamat Baru</label>
                    <input type="text" id="m_split_alamat_baru" name="alamat_baru" required>
                </div>

                <div class="m-form-group">
                    <label>Pilih Anak/Anggota yang Ikut KK Baru:</label>
                    <div id="m_split_children_container" style="max-height:140px; overflow-y:auto; border:1px solid var(--border); border-radius:6px; padding:6px; display:flex; flex-direction:column; gap:6px; background:#f8fafc;"></div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px;">
                    <button type="button" onclick="closeMobileSplitKk()" style="padding:7px 12px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; font-size:0.8rem; cursor:pointer;">Batal</button>
                    <button type="submit" style="padding:7px 14px; background:#db2777; color:#fff; border:none; border-radius:6px; font-size:0.8rem; font-weight:700; cursor:pointer;">Proses Pisah</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const kkDataStore = <?php echo json_encode($members_by_kk, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const kkMetaStore = <?php echo json_encode($paged_kk_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        let activeMobileSplitKk = '';

        function copyKkMobile(text) {
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

        function openMobileKkModal(noKk) {
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
            document.getElementById('mModalNoKk').textContent = 'No. ' + noKk;
            document.getElementById('mModalKepala').textContent = kepala;
            document.getElementById('mModalAlamat').textContent = alamat;
            document.getElementById('mModalSignKepala').textContent = (kepala || '-').toUpperCase();

            if (rtKk === '06') {
                document.getElementById('mModalTitleRt').textContent = 'Ketua RT 06/RW 19,';
                document.getElementById('mModalSignRt').textContent = 'DIMAS (KETUA RT 06)';
            } else {
                document.getElementById('mModalTitleRt').textContent = 'Ketua RT 07/RW 19,';
                document.getElementById('mModalSignRt').textContent = 'BAMBANG (KETUA RT 07)';
            }

            const tbody = document.getElementById('mModalTableBody');
            tbody.innerHTML = '';

            members.forEach(function(m, idx) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="text-align:center;">${idx + 1}</td>
                    <td style="font-weight:700;">${m.nama}</td>
                    <td style="font-family:monospace; font-size:0.68rem; text-align:center;">${m.nik || '-'}</td>
                    <td style="text-align:center;">${(m.kelamin === 'Laki-laki') ? 'L' : 'P'}</td>
                    <td style="text-align:center; font-size:0.7rem;">${m.pekerjaan || 'Belum Bekerja'}</td>
                    <td style="text-align:center; font-weight:700;">${m.status_keluarga || 'Anggota'}</td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('mobileKkModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileKkModal() {
            document.getElementById('mobileKkModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Edit Member Mobile
        function openMobileEditMember(mem) {
            if (!mem) return;
            document.getElementById('m_edit_id_user').value = mem.id;
            document.getElementById('m_edit_nama').value = mem.nama || '';
            document.getElementById('m_edit_nik').value = mem.nik || '';
            document.getElementById('m_edit_kelamin').value = mem.kelamin || 'Laki-laki';
            document.getElementById('m_edit_no_kk').value = mem.no_kk || '';
            document.getElementById('m_edit_status_keluarga').value = mem.status_keluarga || 'Anak';
            document.getElementById('m_edit_pekerjaan').value = mem.pekerjaan || 'Belum / Tidak Bekerja';
            document.getElementById('m_edit_status_perkawinan').value = mem.status_perkawinan || 'Kawin';
            document.getElementById('m_edit_pendidikan').value = mem.pendidikan || 'SLTA / Sederajat';
            document.getElementById('m_edit_agama').value = mem.agama || 'Islam';
            document.getElementById('m_edit_alamat').value = mem.alamat || '';

            document.getElementById('mobileEditModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileEditMember() {
            document.getElementById('mobileEditModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Split KK Mobile
        function openMobileSplitKk(noKk) {
            activeMobileSplitKk = noKk;
            const members = kkDataStore[noKk] || [];
            let meta = null;
            for (let i = 0; i < kkMetaStore.length; i++) {
                if (kkMetaStore[i].no_kk === noKk) {
                    meta = kkMetaStore[i];
                    break;
                }
            }

            const sel = document.getElementById('m_split_id_kepala');
            sel.innerHTML = '';
            members.forEach(function(m) {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = `${m.nama} (${m.kelamin === 'Perempuan' ? 'P' : 'L'} - ${m.status_keluarga || 'Anggota'})`;
                sel.appendChild(opt);
            });

            document.getElementById('m_split_alamat_baru').value = meta ? meta.alamat : (members[0] ? members[0].alamat : '');
            document.getElementById('m_split_no_kk_baru').value = '';

            onMobileSplitKepalaChange();
            document.getElementById('mobileSplitModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function onMobileSplitKepalaChange() {
            const selectedId = parseInt(document.getElementById('m_split_id_kepala').value) || 0;
            const members = kkDataStore[activeMobileSplitKk] || [];
            const container = document.getElementById('m_split_children_container');
            container.innerHTML = '';

            const other = members.filter(m => parseInt(m.id) !== selectedId);
            if (!other.length) {
                container.innerHTML = '<div style="font-size:0.75rem; color:#64748b;">Hanya ada 1 anggota di KK ini.</div>';
                return;
            }

            other.forEach(function(m) {
                const row = document.createElement('label');
                row.style.cssText = 'display:flex; align-items:center; gap:6px; font-size:0.78rem; background:#fff; padding:6px; border-radius:4px; border:1px solid #e2e8f0;';
                const isChild = (m.status_keluarga === 'Anak');
                row.innerHTML = `<input type="checkbox" name="ikut_anggota[]" value="${m.id}" ${isChild ? 'checked' : ''}> <span>${m.nama} (${m.status_keluarga || 'Anggota'})</span>`;
                container.appendChild(row);
            });
        }

        function closeMobileSplitKk() {
            document.getElementById('mobileSplitModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function generateMobileRandomKk() {
            const prefix = '327501';
            let rand = '';
            for (let i = 0; i < 10; i++) rand += Math.floor(Math.random() * 10);
            document.getElementById('m_split_no_kk_baru').value = prefix + rand;
        }
    </script>
    <script src="../js/responsive-app.js"></script>
</body>
</html>
