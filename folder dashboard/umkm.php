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

if (!mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS umkm (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_usaha VARCHAR(255) NOT NULL,
    kategori VARCHAR(100) DEFAULT 'Umum',
    deskripsi TEXT,
    alamat VARCHAR(255) DEFAULT '-',
    jam_operasional VARCHAR(255) DEFAULT '-',
    whatsapp VARCHAR(50) DEFAULT '-',
    foto VARCHAR(255) DEFAULT NULL,
    produk_layanan TEXT,
    estimasi_jarak VARCHAR(100) DEFAULT '-',
    google_maps_link VARCHAR(255) DEFAULT '-',
    status VARCHAR(50) DEFAULT 'Aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
    die('Gagal membuat tabel UMKM: ' . mysqli_error($koneksi));
}

$flash_message = $_SESSION['umkm_flash'] ?? '';
unset($_SESSION['umkm_flash']);
$error_message = '';

function sanitize_whatsapp($value) {
    $digits = preg_replace('/[^0-9]/', '', $value);
    if (strpos($digits, '0') === 0) {
        $digits = '62' . substr($digits, 1);
    }
    return $digits;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_umkm' && $role_user === 'ADMIN') {
        $nama_usaha = mysqli_real_escape_string($koneksi, trim($_POST['nama_usaha'] ?? ''));
        $kategori = mysqli_real_escape_string($koneksi, trim($_POST['kategori'] ?? 'Umum'));
        $deskripsi = mysqli_real_escape_string($koneksi, trim($_POST['deskripsi'] ?? ''));
        $alamat = mysqli_real_escape_string($koneksi, trim($_POST['alamat'] ?? '-'));
        $jam_operasional = mysqli_real_escape_string($koneksi, trim($_POST['jam_operasional'] ?? '-'));
        $whatsapp = mysqli_real_escape_string($koneksi, trim($_POST['whatsapp'] ?? '-'));
        $produk_layanan = mysqli_real_escape_string($koneksi, trim($_POST['produk_layanan'] ?? ''));
        $estimasi_jarak = mysqli_real_escape_string($koneksi, trim($_POST['estimasi_jarak'] ?? '-'));
        $google_maps_link = mysqli_real_escape_string($koneksi, trim($_POST['google_maps_link'] ?? '-'));
        $status = mysqli_real_escape_string($koneksi, trim($_POST['status'] ?? 'Aktif'));
        $foto_file = null;

        if ($nama_usaha === '') {
            $error_message = 'Nama usaha tidak boleh kosong.';
        } else {
            if (!empty($_FILES['foto_usaha']['name']) && is_uploaded_file($_FILES['foto_usaha']['tmp_name'])) {
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                $ext = strtolower(pathinfo($_FILES['foto_usaha']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext)) {
                    $error_message = 'Format foto usaha tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.';
                } else {
                    $upload_name = 'umkm_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                    if (!move_uploaded_file($_FILES['foto_usaha']['tmp_name'], '../img/' . $upload_name)) {
                        $error_message = 'Gagal mengunggah foto usaha.';
                    } else {
                        $foto_file = $upload_name;
                    }
                }
            }
        }

        if ($error_message === '') {
            $insert = "INSERT INTO umkm (nama_usaha, kategori, deskripsi, alamat, jam_operasional, whatsapp, foto, produk_layanan, estimasi_jarak, google_maps_link, status) VALUES ('" . $nama_usaha . "', '" . $kategori . "', '" . $deskripsi . "', '" . $alamat . "', '" . $jam_operasional . "', '" . $whatsapp . "', '" . ($foto_file ? $foto_file : '') . "', '" . $produk_layanan . "', '" . $estimasi_jarak . "', '" . $google_maps_link . "', '" . $status . "')";
            if (mysqli_query($koneksi, $insert)) {
                $_SESSION['umkm_flash'] = 'UMKM berhasil ditambahkan.';
                header('Location: umkm.php');
                exit();
            }
            $error_message = 'Gagal menyimpan UMKM: ' . mysqli_error($koneksi);
        }
    }

    if ($action === 'edit_umkm' && $role_user === 'ADMIN') {
        $id_umkm = intval($_POST['umkm_id'] ?? 0);
        $nama_usaha = mysqli_real_escape_string($koneksi, trim($_POST['nama_usaha'] ?? ''));
        $kategori = mysqli_real_escape_string($koneksi, trim($_POST['kategori'] ?? 'Umum'));
        $deskripsi = mysqli_real_escape_string($koneksi, trim($_POST['deskripsi'] ?? ''));
        $alamat = mysqli_real_escape_string($koneksi, trim($_POST['alamat'] ?? '-'));
        $jam_operasional = mysqli_real_escape_string($koneksi, trim($_POST['jam_operasional'] ?? '-'));
        $whatsapp = mysqli_real_escape_string($koneksi, trim($_POST['whatsapp'] ?? '-'));
        $produk_layanan = mysqli_real_escape_string($koneksi, trim($_POST['produk_layanan'] ?? ''));
        $estimasi_jarak = mysqli_real_escape_string($koneksi, trim($_POST['estimasi_jarak'] ?? '-'));
        $google_maps_link = mysqli_real_escape_string($koneksi, trim($_POST['google_maps_link'] ?? '-'));
        $status = mysqli_real_escape_string($koneksi, trim($_POST['status'] ?? 'Aktif'));
        $foto_update = '';

        if ($id_umkm <= 0 || $nama_usaha === '') {
            $error_message = 'Data UMKM tidak valid.';
        } else {
            if (!empty($_FILES['foto_usaha']['name']) && is_uploaded_file($_FILES['foto_usaha']['tmp_name'])) {
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                $ext = strtolower(pathinfo($_FILES['foto_usaha']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext)) {
                    $error_message = 'Format foto usaha tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.';
                } else {
                    $upload_name = 'umkm_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                    if (!move_uploaded_file($_FILES['foto_usaha']['tmp_name'], '../img/' . $upload_name)) {
                        $error_message = 'Gagal mengunggah foto usaha.';
                    } else {
                        $foto_update = $upload_name;
                    }
                }
            }
        }

        if ($error_message === '') {
            $update = "UPDATE umkm SET nama_usaha='" . $nama_usaha . "', kategori='" . $kategori . "', deskripsi='" . $deskripsi . "', alamat='" . $alamat . "', jam_operasional='" . $jam_operasional . "', whatsapp='" . $whatsapp . "', produk_layanan='" . $produk_layanan . "', estimasi_jarak='" . $estimasi_jarak . "', google_maps_link='" . $google_maps_link . "', status='" . $status . "'";
            if ($foto_update !== '') {
                $update .= ", foto='" . $foto_update . "'";
            }
            $update .= " WHERE id='" . $id_umkm . "'";
            if (mysqli_query($koneksi, $update)) {
                $_SESSION['umkm_flash'] = 'UMKM berhasil diperbarui.';
                header('Location: umkm.php');
                exit();
            }
            $error_message = 'Gagal memperbarui UMKM: ' . mysqli_error($koneksi);
        }
    }

    if ($action === 'delete_umkm' && $role_user === 'ADMIN') {
        $id_umkm = intval($_POST['umkm_id'] ?? 0);
        if ($id_umkm > 0) {
            if (mysqli_query($koneksi, "DELETE FROM umkm WHERE id='" . $id_umkm . "'")) {
                $_SESSION['umkm_flash'] = 'UMKM berhasil dihapus.';
                header('Location: umkm.php');
                exit();
            }
            $error_message = 'Gagal menghapus UMKM: ' . mysqli_error($koneksi);
        }
    }
}

$search = mysqli_real_escape_string($koneksi, trim($_GET['search'] ?? ''));
$filter_category = mysqli_real_escape_string($koneksi, trim($_GET['category'] ?? ''));

$where_clauses = ["status = 'Aktif'"];
if ($search !== '') {
    $where_clauses[] = "(nama_usaha LIKE '%" . $search . "%' OR kategori LIKE '%" . $search . "%' OR deskripsi LIKE '%" . $search . "%')";
}
if ($filter_category !== '') {
    $where_clauses[] = "kategori = '" . $filter_category . "'";
}
$where_sql = '';
if (count($where_clauses) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

$umkm_query = "SELECT * FROM umkm " . $where_sql . " ORDER BY nama_usaha ASC";
$result_umkm = mysqli_query($koneksi, $umkm_query);
$umkm_list = [];
while ($row = mysqli_fetch_assoc($result_umkm)) {
    $umkm_list[] = $row;
}

$all_umkm = [];
$all_umkm_result = mysqli_query($koneksi, "SELECT * FROM umkm ORDER BY created_at DESC");
while ($row = mysqli_fetch_assoc($all_umkm_result)) {
    $all_umkm[] = $row;
}

$category_result = mysqli_query($koneksi, "SELECT DISTINCT kategori FROM umkm ORDER BY kategori ASC");
$categories = [];
while ($row = mysqli_fetch_assoc($category_result)) {
    $categories[] = $row['kategori'];
}

$title = 'UMKM Lokal Warga - RT RW Digital Bersatu';
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
        .badge-inactive { background: #fee2e2; color: #991b1b; padding: 6px 12px; border-radius: 999px; font-weight: 700; }
        .umkm-card { border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; display: grid; grid-template-columns: 320px minmax(0,1fr); gap: 0; margin-bottom: 20px; background: #fff; }
        .umkm-image { min-height: 260px; background: #f8fafc; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .umkm-image img { width: 100%; height: 100%; object-fit: cover; }
        .umkm-content { padding: 22px; }
        .umkm-content h3 { margin-top: 0; margin-bottom: 8px; }
        .umkm-content p { margin: 0 0 14px; color: #475569; line-height: 1.6; }
        .umkm-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 14px; }
        .umkm-meta div { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px; }
        .umkm-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 14px; }
        .umkm-actions a { text-decoration: none; }
        .umkm-detail-list { list-style: none; padding-left: 0; margin: 0; }
        .umkm-detail-list li { margin-bottom: 10px; }
        .umkm-detail-list strong { display: inline-block; width: 150px; color: #0f172a; }
        .form-row { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 18px; }
        .form-row .form-group { flex: 1; min-width: 220px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 700; color: #0f172a; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 12px; background: #f8fafc; font-size: 1rem; color: #0f172a; }
        .form-actions { margin-top: 18px; display: flex; flex-wrap: wrap; gap: 12px; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.65); z-index: 1999; align-items: center; justify-content: center; padding: 18px; overflow-y: auto; }
        .modal.active { display: flex; }
        .modal-content { width: 100%; max-width: 860px; background: #ffffff; border-radius: 18px; padding: 24px; position: relative; box-shadow: 0 24px 80px rgba(15,23,42,0.18); max-height: 90vh; overflow-y: auto; margin: auto; }
        .close-btn { position: absolute; right: 18px; top: 18px; background: #f3f4f6; border: none; border-radius: 10px; width: 36px; height: 36px; cursor: pointer; font-size: 18px; }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .form-group small { display: block; margin-top: 4px; color: #64748b; }
        @media (max-width: 960px) { .umkm-card { grid-template-columns: 1fr; } }
        @media (max-width: 680px) { .form-row { flex-direction: column; } .umkm-meta { grid-template-columns: 1fr; } }
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
                    <strong style="color: var(--text);">UMKM Lokal</strong>
                </div>
            </div>
            <div class="app-topbar__actions">
                <?php if ($role_user === 'ADMIN'): ?>
                    <button class="primary-btn" onclick="openAddUmkmModal()">
                        <i class="fa-solid fa-plus"></i> Tambah Usaha UMKM
                    </button>
                <?php endif; ?>
            </div>
        </header>

        <main class="page-shell">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-store" style="color: var(--primary);"></i> UMKM Lokal Warga</h1>
                    <p style="margin: 6px 0 0; color: var(--text-soft);">Katalog produk, jasa, dan usaha kuliner warga RT/RW setempat.</p>
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
                                <h2>Kelola UMKM Lokal</h2>
                                <p>Tambahkan dan kelola usaha warga, termasuk produk, kontak WhatsApp, dan lokasi Google Maps.</p>
                            </div>
                            <button class="btn-primary" onclick="openUmkmModal()"><i class="fa-solid fa-plus"></i> Tambah UMKM</button>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Usaha</th>
                                    <th>Kategori</th>
                                    <th>Alamat</th>
                                    <th>WhatsApp</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($all_umkm) === 0): ?>
                                    <tr><td colspan="7" style="text-align:center; color:#64748b;">Belum ada UMKM terdaftar.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($all_umkm as $index => $umkm): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($umkm['nama_usaha']); ?></td>
                                            <td><?php echo htmlspecialchars($umkm['kategori']); ?></td>
                                            <td><?php echo htmlspecialchars($umkm['alamat']); ?></td>
                                            <td><?php echo htmlspecialchars($umkm['whatsapp']); ?></td>
                                            <td><?php echo $umkm['status'] === 'Aktif' ? '<span class="badge-active">Aktif</span>' : '<span class="badge-inactive">Tidak Aktif</span>'; ?></td>
                                            <td>
                                                <button class="btn-secondary btn-small" onclick="openUmkmEdit(this)" data-umkm='<?php echo htmlspecialchars(json_encode($umkm, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'>Ubah</button>
                                                <form method="POST" style="display:inline-block; margin:0;">
                                                    <input type="hidden" name="action" value="delete_umkm">
                                                    <input type="hidden" name="umkm_id" value="<?php echo intval($umkm['id']); ?>">
                                                    <button type="submit" class="btn-danger btn-small" onclick="return confirm('Hapus UMKM ini?')">Hapus</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="section-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                        <div>
                            <h2>Daftar UMKM Lokal</h2>
                            <p>Cari usaha warga di lingkungan RT/RW berdasarkan nama atau kategori.</p>
                        </div>
                    </div>
                    <form method="GET" style="display:flex;flex-wrap:wrap;gap:14px;margin-top:16px;">
                        <div style="flex:1; min-width:220px;">
                            <input type="text" name="search" placeholder="Cari nama atau kategori..." value="<?php echo htmlspecialchars($search); ?>" style="width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px;background:#f8fafc;" />
                        </div>
                        <div style="flex:1; min-width:220px;">
                            <select name="category" style="width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px;background:#f8fafc;">
                                <option value="">Semua Kategori</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filter_category === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary" style="height:48px; align-self:flex-end;">Cari</button>
                    </form>
                </div>

                <?php if (count($umkm_list) === 0): ?>
                    <div class="section-card" style="text-align:center; color:#64748b;">Tidak ada UMKM yang sesuai dengan pencarian.</div>
                <?php else: ?>
                    <?php foreach ($umkm_list as $umkm): ?>
                        <div class="umkm-card">
                            <div class="umkm-image">
                                <?php if (!empty($umkm['foto']) && file_exists('../img/' . $umkm['foto'])): ?>
                                    <img src="../img/<?php echo htmlspecialchars($umkm['foto']); ?>" alt="Foto usaha <?php echo htmlspecialchars($umkm['nama_usaha']); ?>">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/640x420?text=Foto+UMKM" alt="Tidak ada foto usaha">
                                <?php endif; ?>
                            </div>
                            <div class="umkm-content">
                                <h3><?php echo htmlspecialchars($umkm['nama_usaha']); ?></h3>
                                <p><?php echo nl2br(htmlspecialchars($umkm['deskripsi'])); ?></p>
                                <div class="umkm-meta">
                                    <div><strong>Kategori</strong><div><?php echo htmlspecialchars($umkm['kategori']); ?></div></div>
                                    <div><strong>Jam Operasional</strong><div><?php echo htmlspecialchars($umkm['jam_operasional']); ?></div></div>
                                    <div><strong>Alamat</strong><div><?php echo htmlspecialchars($umkm['alamat']); ?></div></div>
                                    <div><strong>Estimasi Jarak</strong><div><?php echo htmlspecialchars($umkm['estimasi_jarak']); ?></div></div>
                                </div>
                                <div class="umkm-content" style="padding:0; margin-top:10px;">
                                    <h4>Produk / Layanan</h4>
                                    <ul class="umkm-detail-list">
                                        <?php foreach (preg_split('/\r?\n/', trim($umkm['produk_layanan'])) as $item): ?>
                                            <?php if (trim($item) !== ''): ?>
                                                <li><?php echo htmlspecialchars($item); ?></li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div class="umkm-actions">
                                    <?php $wa_number = sanitize_whatsapp($umkm['whatsapp']); ?>
                                    <?php if (!empty($wa_number)): ?>
                                        <a class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;" href="https://wa.me/<?php echo htmlspecialchars($wa_number); ?>" target="_blank"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                                    <?php endif; ?>
                                    <?php 
                                        $maps_url = '';
                                        if (!empty($umkm['google_maps_link']) && (strpos($umkm['google_maps_link'], 'http') === 0)) {
                                            $maps_url = $umkm['google_maps_link'];
                                        } elseif (!empty($umkm['alamat']) && $umkm['alamat'] !== '-') {
                                            $maps_url = 'https://www.google.com/maps/search/' . urlencode($umkm['alamat'] . ', Bekasi');
                                        }
                                    ?>
                                    <?php if (!empty($maps_url)): ?>
                                        <a class="btn-secondary" style="display:inline-flex;align-items:center;gap:8px;" href="<?php echo htmlspecialchars($maps_url); ?>" target="_blank"><i class="fa-solid fa-map-location-dot"></i> Google Maps</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </script>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
