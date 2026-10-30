<?php
include "../Koneksi.php"; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nama'])) {
    header("Location: ../folder login,logout dan register/login.php");
    exit();
}

// Hak akses khusus ADMIN
if (strtoupper($_SESSION['role'] ?? 'WARGA') !== 'ADMIN') {
    echo "<script>alert('Akses Ditolak! Halaman Kelola Data Warga hanya dapat diakses oleh Admin.'); window.location='dashboard.php';</script>";
    exit();
}

$nama_user = $_SESSION['nama'];
$role_user = $_SESSION['role'] ?? 'ADMIN';

// Filter & Pencarian
$search = trim($_GET['q'] ?? '');
$filter_role = trim($_GET['role'] ?? '');
$filter_status_keluarga = trim($_GET['status_keluarga'] ?? '');

$where_clauses = ["1=1"];
if (!empty($search)) {
    $search_safe = mysqli_real_escape_string($koneksi, $search);
    $where_clauses[] = "(users.nama LIKE '%$search_safe%' OR users.nik LIKE '%$search_safe%' OR users.no_kk LIKE '%$search_safe%' OR users.alamat LIKE '%$search_safe%' OR users.no_hp LIKE '%$search_safe%')";
}
if (!empty($filter_role)) {
    $role_safe = mysqli_real_escape_string($koneksi, $filter_role);
    $where_clauses[] = "LOWER(users.role) = LOWER('$role_safe')";
}
if (!empty($filter_status_keluarga)) {
    $sk_safe = mysqli_real_escape_string($koneksi, $filter_status_keluarga);
    $where_clauses[] = "users.status_keluarga = '$sk_safe'";
}
$where_sql = implode(' AND ', $where_clauses);

// Hitung total warga yang cocok filter
$res_count = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE $where_sql");
$row_count = mysqli_fetch_assoc($res_count);
$total_filtered = (int)($row_count['total'] ?? 0);

// Hitung total seluruh akun di database
$res_all = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users");
$row_all = mysqli_fetch_assoc($res_all);
$total_all_users = (int)($row_all['total'] ?? 0);

// Pagination: 10 data per halaman (slide)
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$total_pages = ceil($total_filtered / $limit);
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

// Query data per halaman
$query_sql = "SELECT users.*, (SELECT SUM(jumlah) FROM keuangan WHERE keuangan.nama_warga = users.nama) as total 
              FROM users 
              WHERE $where_sql 
              ORDER BY users.id ASC 
              LIMIT $limit OFFSET $offset";
$query = mysqli_query($koneksi, $query_sql);

$title = 'Kelola Data Warga - RT RW Digital Bersatu';
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
                    <strong style="color: var(--text);">Kelola Data Warga</strong>
                </div>
            </div>
            <div class="app-topbar__actions">
                <a href="kelola_kk.php" class="ghost-btn" style="text-decoration:none;">
                    <i class="fa-solid fa-people-roof"></i> Data Kartu Keluarga
                </a>
            </div>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-users" style="color: var(--primary);"></i> Kelola Data & Akun Warga</h1>
                    <p style="margin: 6px 0 0; color: var(--text-soft);">Daftar seluruh warga terdaftar beserta nomor identitas (NIK & No. KK), peran akun, dan akumulasi kas.</p>
                </div>
            </div>

            <!-- Form Pencarian & Filter -->
            <section class="glass-panel" style="padding: 16px 20px; margin-bottom: 20px;">
                <form action="kelola_warga.php" method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                    <div style="display: flex; gap: 10px; flex: 1 1 300px; flex-wrap: wrap;">
                        <div style="flex: 1 1 240px; position: relative;">
                            <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari Nama, NIK, No. KK, Alamat, atau HP..." style="width: 100%; padding: 10px 14px 10px 36px; border: 1.5px solid var(--line); border-radius: 10px; background: #fff;">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted);"></i>
                        </div>
                        <select name="role" style="padding: 10px 14px; border: 1.5px solid var(--line); border-radius: 10px; background: #fff; min-width: 130px;">
                            <option value="">Semua Peran</option>
                            <option value="Warga" <?php echo (strcasecmp($filter_role, 'Warga') === 0) ? 'selected' : ''; ?>>Warga</option>
                            <option value="Admin" <?php echo (strcasecmp($filter_role, 'Admin') === 0) ? 'selected' : ''; ?>>Admin</option>
                        </select>
                        <select name="status_keluarga" style="padding: 10px 14px; border: 1.5px solid var(--line); border-radius: 10px; background: #fff; min-width: 160px;">
                            <option value="">Semua Status Keluarga</option>
                            <option value="Kepala Keluarga" <?php echo ($filter_status_keluarga === 'Kepala Keluarga') ? 'selected' : ''; ?>>Kepala Keluarga</option>
                            <option value="Istri" <?php echo ($filter_status_keluarga === 'Istri') ? 'selected' : ''; ?>>Istri</option>
                            <option value="Anak" <?php echo ($filter_status_keluarga === 'Anak') ? 'selected' : ''; ?>>Anak</option>
                        </select>
                        <button type="submit" class="primary-btn">
                            <i class="fa-solid fa-filter"></i> Filter
                        </button>
                        <?php if (!empty($search) || !empty($filter_role) || !empty($filter_status_keluarga)): ?>
                            <a href="kelola_warga.php" class="ghost-btn" style="text-decoration:none;">Reset</a>
                        <?php endif; ?>
                    </div>
                    <div style="color: var(--text-soft); font-size: 0.9rem;">
                        Menampilkan <strong><?php echo mysqli_num_rows($query); ?></strong> dari <strong><?php echo $total_filtered; ?></strong> Warga
                    </div>
                </form>
            </section>

            <div class="module-panel">
                <div class="module-panel__header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <span><i class="fa-solid fa-table-list" style="color:var(--primary); margin-right:8px;"></i> Daftar Warga RT RW Digital</span>
                    <span style="font-size:0.85rem; color:var(--text-soft); font-weight:600;">
                        Total: <?php echo $total_filtered; ?> Orang <?php if ($total_pages > 1): ?>(Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?>)<?php endif; ?>
                    </span>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="text-align: center; width: 50px;">No</th>
                                <th>Nama Warga</th>
                                <th>No. KK</th>
                                <th>NIK</th>
                                <th>Status Keluarga</th>
                                <th>Alamat</th>
                                <th>No. HP</th>
                                <th style="text-align: center;">Status Akun</th>
                                <th style="text-align: right;">Total Kas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (mysqli_num_rows($query) === 0) {
                                echo '<tr><td colspan="9" style="text-align:center; padding:32px; color:var(--text-soft);"><i class="fa-solid fa-inbox" style="font-size:2rem; display:block; margin-bottom:8px;"></i>Tidak ada data warga ditemukan.</td></tr>';
                            } else {
                                $no = $offset + 1; 
                                while($d = mysqli_fetch_array($query)) { 
                            ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-soft); font-weight: 600;"><?php echo $no++; ?></td>
                                <td style="font-weight: 700; color: var(--text);"><?php echo htmlspecialchars($d['nama']); ?></td>
                                <td style="font-family: monospace; font-size: 0.88rem; color: var(--text-soft);"><?php echo htmlspecialchars($d['no_kk'] ?? '-'); ?></td>
                                <td style="font-family: monospace; font-size: 0.88rem; color: var(--text-soft);"><?php echo htmlspecialchars($d['nik'] ?? '-'); ?></td>
                                <td>
                                    <?php 
                                    $sk = $d['status_keluarga'] ?? 'Kepala Keluarga';
                                    $sk_bg = '#e0f2fe';
                                    $sk_color = '#0369a1';
                                    if ($sk === 'Istri') {
                                        $sk_bg = '#fce7f3';
                                        $sk_color = '#be185d';
                                    } elseif ($sk === 'Anak') {
                                        $sk_bg = '#dcfce7';
                                        $sk_color = '#15803d';
                                    }
                                    ?>
                                    <span style="padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; background: <?php echo $sk_bg; ?>; color: <?php echo $sk_color; ?>; white-space: nowrap;">
                                        <?php echo htmlspecialchars($sk); ?>
                                    </span>
                                </td>
                                <td style="color: var(--text-soft);"><?php echo htmlspecialchars($d['alamat'] ?? '-'); ?></td>
                                <td style="color: var(--text-soft);"><?php echo htmlspecialchars($d['no_hp'] ?? '-'); ?></td>
                                <td style="text-align: center;">
                                    <?php if (strtolower($d['role']) === 'admin'): ?>
                                        <span class="status-badge" style="background:#e0f2fe; color:#0284c7; border-color:#bae6fd;">Admin</span>
                                    <?php else: ?>
                                        <span class="status-badge" style="background:#f1f5f9; color:#475569; border-color:#e2e8f0;">Warga</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-weight: 800; color: #16a34a;">
                                    Rp <?php echo number_format($d['total'] ?? 0, 0, ',', '.'); ?>
                                </td>
                            </tr>
                            <?php 
                                } 
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Mirip dengan Kelola KK (10 Data per Slide) -->
                <?php if ($total_pages > 1): ?>
                    <?php
                    $queryParams = [];
                    if (!empty($search)) $queryParams['q'] = $search;
                    if (!empty($filter_role)) $queryParams['role'] = $filter_role;
                    if (!empty($filter_status_keluarga)) $queryParams['status_keluarga'] = $filter_status_keluarga;
                    
                    function buildWargaPageUrl($p, $params) {
                        $params['page'] = $p;
                        return 'kelola_warga.php?' . http_build_query($params);
                    }
                    ?>
                    <div style="display: flex; justify-content: center; gap: 8px; margin: 24px 0 12px; flex-wrap: wrap;">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo buildWargaPageUrl($page - 1, $queryParams); ?>" class="ghost-btn" style="text-decoration:none;">&laquo; Sebelumnya</a>
                        <?php endif; ?>

                        <?php 
                        // Jika total halaman <= 12, tampilkan seluruh nomor halaman
                        if ($total_pages <= 12):
                            for ($p = 1; $p <= $total_pages; $p++): ?>
                                <a href="<?php echo buildWargaPageUrl($p, $queryParams); ?>" class="<?php echo ($p === $page) ? 'primary-btn' : 'ghost-btn'; ?>" style="text-decoration:none; min-width: 40px; text-align: center;">
                                    <?php echo $p; ?>
                                </a>
                            <?php endfor; ?>
                        <?php else: 
                            // Jika halaman banyak, gunakan windowing
                            $start_p = max(1, $page - 2);
                            $end_p = min($total_pages, $page + 2);
                            if ($start_p > 1): ?>
                                <a href="<?php echo buildWargaPageUrl(1, $queryParams); ?>" class="ghost-btn" style="text-decoration:none; min-width: 40px; text-align: center;">1</a>
                                <?php if ($start_p > 2): ?><span style="display:inline-flex; align-items:center; padding:0 4px; color:var(--text-soft);">...</span><?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $start_p; $p <= $end_p; $p++): ?>
                                <a href="<?php echo buildWargaPageUrl($p, $queryParams); ?>" class="<?php echo ($p === $page) ? 'primary-btn' : 'ghost-btn'; ?>" style="text-decoration:none; min-width: 40px; text-align: center;">
                                    <?php echo $p; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($end_p < $total_pages): ?>
                                <?php if ($end_p < $total_pages - 1): ?><span style="display:inline-flex; align-items:center; padding:0 4px; color:var(--text-soft);">...</span><?php endif; ?>
                                <a href="<?php echo buildWargaPageUrl($total_pages, $queryParams); ?>" class="ghost-btn" style="text-decoration:none; min-width: 40px; text-align: center;"><?php echo $total_pages; ?></a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo buildWargaPageUrl($page + 1, $queryParams); ?>" class="ghost-btn" style="text-decoration:none;">Selanjutnya &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
