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

// Pagination untuk mobile: 6 per halaman
$limit = 6;
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
        .m-tag-istri { background: #fce7f3; color: #be185d; }
        .m-tag-anak { background: #dcfce7; color: #15803d; }

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
                        <span class="m-kk-count"><?php echo count($members); ?> Anggota</span>
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
                                $tag_class = 'm-tag-anak';
                                if ($st === 'Kepala Keluarga') $tag_class = 'm-tag-kepala';
                                elseif ($st === 'Istri') $tag_class = 'm-tag-istri';
                            ?>
                                <div class="m-member-item">
                                    <div>
                                        <div class="m-member-name"><?php echo htmlspecialchars($mem['nama']); ?></div>
                                        <div class="m-member-nik">NIK: <?php echo htmlspecialchars($mem['nik']); ?> (<?php echo ($mem['kelamin'] === 'Laki-laki') ? 'L' : 'P'; ?>)</div>
                                    </div>
                                    <span class="m-tag <?php echo $tag_class; ?>"><?php echo htmlspecialchars($st); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Aksi Cepat -->
                        <div class="m-kk-actions">
                            <button type="button" class="m-btn-print" onclick="openMobileKkModal('<?php echo $no_kk; ?>')">
                                <i class="fa-solid fa-print"></i> Format Resmi KK
                            </button>
                            <button type="button" class="m-btn-copy" onclick="copyKkMobile('<?php echo $no_kk; ?>')">
                                <i class="fa-regular fa-copy"></i> Salin
                            </button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

            <!-- Pagination Mobile -->
            <?php if ($total_pages > 1): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                    <?php if ($page > 1): ?>
                        <a href="kelola_kk_mobile.php?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>&anggota=<?php echo urlencode($filter_anggota); ?>" class="m-chip">&laquo; Sebelumnya</a>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>

                    <span style="font-size:0.75rem; color:var(--text-muted); font-weight:700;">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></span>

                    <?php if ($page < $total_pages): ?>
                        <a href="kelola_kk_mobile.php?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>&anggota=<?php echo urlencode($filter_anggota); ?>" class="m-chip">Selanjutnya &raquo;</a>
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
                <div><strong>RT / RW:</strong> 01 / 05 | <strong>Kelurahan:</strong> Margahayu</div>
                <div><strong>Kecamatan:</strong> Bekasi Timur | <strong>Kota:</strong> Kota Bekasi</div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width:25px;">No</th>
                        <th>Nama Lengkap</th>
                        <th>NIK</th>
                        <th>JK</th>
                        <th>Status</th>
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
                    <div>Ketua RT 01/RW 05,</div>
                    <div style="height:35px;"></div>
                    <strong>DIMAS (RT)</strong>
                </div>
            </div>
        </div>
    </div>

    <script>
        const kkDataStore = <?php echo json_encode($members_by_kk, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const kkMetaStore = <?php echo json_encode($paged_kk_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

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

            document.getElementById('mModalNoKk').textContent = 'No. ' + noKk;
            document.getElementById('mModalKepala').textContent = kepala;
            document.getElementById('mModalAlamat').textContent = alamat;
            document.getElementById('mModalSignKepala').textContent = kepala.toUpperCase();

            const tbody = document.getElementById('mModalTableBody');
            tbody.innerHTML = '';

            members.forEach(function(m, idx) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="text-align:center;">${idx + 1}</td>
                    <td style="font-weight:700;">${m.nama}</td>
                    <td style="font-family:monospace; font-size:0.68rem; text-align:center;">${m.nik || '-'}</td>
                    <td style="text-align:center;">${(m.kelamin === 'Laki-laki') ? 'L' : 'P'}</td>
                    <td style="text-align:center;">${m.status_keluarga || 'Anggota'}</td>
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
    </script>
    <script src="../js/responsive-app.js"></script>
</body>
</html>
