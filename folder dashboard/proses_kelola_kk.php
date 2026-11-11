<?php
include "../Koneksi.php";

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        echo "<script>alert('Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba lagi.'); window.history.back();</script>";
        exit();
    }

    $action = $_POST['action'] ?? '';
    $redirect_url = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : 'kelola_kk.php';
    // Sanitasi redirect_url agar hanya mengarah ke halaman yang diizinkan
    $allowed_redirects = ['kelola_kk.php', 'kelola_kk_mobile.php', 'kelola_warga.php'];
    $redirect_basename = basename(parse_url($redirect_url, PHP_URL_PATH));
    if (!in_array($redirect_basename, $allowed_redirects)) {
        $redirect_url = 'kelola_kk.php';
    }

    // ==========================================
    // 1. AKSI EDIT ANGGOTA KK / HUBUNGAN KELUARGA
    // ==========================================
    if ($action === 'edit_anggota_kk') {
        $id_user = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
        $nama = trim($_POST['nama'] ?? '');
        $nik = trim($_POST['nik'] ?? '');
        $kelamin = trim($_POST['kelamin'] ?? '');
        $no_kk = trim($_POST['no_kk'] ?? '');
        $status_keluarga = trim($_POST['status_keluarga'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $pekerjaan = trim($_POST['pekerjaan'] ?? 'Belum / Tidak Bekerja');
        $status_perkawinan = trim($_POST['status_perkawinan'] ?? 'Kawin');
        $pendidikan = trim($_POST['pendidikan'] ?? 'SLTA / Sederajat');
        $agama = trim($_POST['agama'] ?? 'Islam');
        $rt = trim($_POST['rt'] ?? '06');

        if ($id_user <= 0 || empty($nama) || empty($nik) || empty($no_kk) || empty($status_keluarga)) {
            echo "<script>alert('Semua bidang wajib diisi dengan benar.'); window.history.back();</script>";
            exit();
        }

        if (!preg_match('/^\d{16}$/', $nik)) {
            echo "<script>alert('NIK harus terdiri dari 16 digit angka.'); window.history.back();</script>";
            exit();
        }

        if (!preg_match('/^\d{16}$/', $no_kk)) {
            echo "<script>alert('Nomor KK harus terdiri dari 16 digit angka.'); window.history.back();</script>";
            exit();
        }

        $allowed_statuses = ['Kepala Keluarga', 'Suami', 'Istri', 'Anak', 'Famili Lain'];
        if (!in_array($status_keluarga, $allowed_statuses)) {
            echo "<script>alert('Status hubungan keluarga tidak valid.'); window.history.back();</script>";
            exit();
        }

        if (!in_array($kelamin, ['Laki-laki', 'Perempuan'])) {
            $kelamin = 'Laki-laki';
        }

        mysqli_begin_transaction($koneksi);
        try {
            // Ambil data user lama
            $stmt_old = mysqli_prepare($koneksi, "SELECT id, no_kk, status_keluarga, alamat FROM users WHERE id = ? LIMIT 1 FOR UPDATE");
            mysqli_stmt_bind_param($stmt_old, "i", $id_user);
            mysqli_stmt_execute($stmt_old);
            $res_old = mysqli_stmt_get_result($stmt_old);
            $old_data = mysqli_fetch_assoc($res_old);
            mysqli_stmt_close($stmt_old);

            if (!$old_data) {
                throw new Exception("Data warga tidak ditemukan.");
            }

            // Periksa jika NIK dipakai warga lain
            $stmt_nik = mysqli_prepare($koneksi, "SELECT id FROM users WHERE nik = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($stmt_nik, "si", $nik, $id_user);
            mysqli_stmt_execute($stmt_nik);
            $res_nik = mysqli_stmt_get_result($stmt_nik);
            if (mysqli_num_rows($res_nik) > 0) {
                mysqli_stmt_close($stmt_nik);
                throw new Exception("NIK sudah digunakan oleh warga lain.");
            }
            mysqli_stmt_close($stmt_nik);

            // Jika status baru adalah "Kepala Keluarga":
            // Periksa apakah di KK target ($no_kk) sudah ada Kepala Keluarga lain selain user ini
            if ($status_keluarga === 'Kepala Keluarga') {
                $stmt_curr_kepala = mysqli_prepare($koneksi, "SELECT id, kelamin FROM users WHERE no_kk = ? AND status_keluarga = 'Kepala Keluarga' AND id != ?");
                mysqli_stmt_bind_param($stmt_curr_kepala, "si", $no_kk, $id_user);
                mysqli_stmt_execute($stmt_curr_kepala);
                $res_curr = mysqli_stmt_get_result($stmt_curr_kepala);
                
                // Jika sudah ada kepala keluarga sebelumnya di KK ini, alihkan statusnya
                while ($prev_kepala = mysqli_fetch_assoc($res_curr)) {
                    $demote_id = (int)$prev_kepala['id'];
                    // Jika yang baru perempuan (misal cerai atau istri jadi kepala), suami lama jadi Suami / Famili Lain
                    $new_status_for_prev = ($prev_kepala['kelamin'] === 'Perempuan') ? 'Istri' : 'Suami';
                    $stmt_demote = mysqli_prepare($koneksi, "UPDATE users SET status_keluarga = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_demote, "si", $new_status_for_prev, $demote_id);
                    mysqli_stmt_execute($stmt_demote);
                    mysqli_stmt_close($stmt_demote);
                }
                mysqli_stmt_close($stmt_curr_kepala);
            }

            // Update data user lengkap sesuai kolom Kartu Keluarga resmi
            $stmt_update = mysqli_prepare($koneksi, "UPDATE users SET nama = ?, nik = ?, kelamin = ?, no_kk = ?, status_keluarga = ?, alamat = ?, pekerjaan = ?, status_perkawinan = ?, pendidikan = ?, agama = ?, rt = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_update, "sssssssssssi", $nama, $nik, $kelamin, $no_kk, $status_keluarga, $alamat, $pekerjaan, $status_perkawinan, $pendidikan, $agama, $rt, $id_user);
            if (!mysqli_stmt_execute($stmt_update)) {
                mysqli_stmt_close($stmt_update);
                throw new Exception("Gagal memperbarui data warga.");
            }
            mysqli_stmt_close($stmt_update);

            mysqli_commit($koneksi);
            echo "<script>alert('Data Kartu Keluarga dan status hubungan keluarga berhasil diperbarui!'); window.location='" . $redirect_url . "';</script>";
            exit();

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $msg = addslashes($e->getMessage());
            echo "<script>alert('Gagal: " . $msg . "'); window.history.back();</script>";
            exit();
        }
    }

    // ==========================================
    // 2. AKSI PISAH KK (MISAL CERAI / KK MANDIRI)
    // ==========================================
    if ($action === 'pisah_kk') {
        $id_kepala = isset($_POST['id_kepala']) ? (int)$_POST['id_kepala'] : 0;
        $no_kk_baru = trim($_POST['no_kk_baru'] ?? '');
        $alamat_baru = trim($_POST['alamat_baru'] ?? '');
        $ikut_anggota = isset($_POST['ikut_anggota']) && is_array($_POST['ikut_anggota']) ? array_map('intval', $_POST['ikut_anggota']) : [];

        if ($id_kepala <= 0 || empty($no_kk_baru) || empty($alamat_baru)) {
            echo "<script>alert('Data Nomor KK Baru dan Alamat wajib diisi.'); window.history.back();</script>";
            exit();
        }

        if (!preg_match('/^\d{16}$/', $no_kk_baru)) {
            echo "<script>alert('Nomor KK Baru harus tepat 16 digit angka.'); window.history.back();</script>";
            exit();
        }

        mysqli_begin_transaction($koneksi);
        try {
            // Cek apakah No KK Baru sudah dipakai oleh KK lain yang bukan kelompok ini
            $stmt_check = mysqli_prepare($koneksi, "SELECT id, nama FROM users WHERE no_kk = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt_check, "s", $no_kk_baru);
            mysqli_stmt_execute($stmt_check);
            $res_check = mysqli_stmt_get_result($stmt_check);
            if (mysqli_num_rows($res_check) > 0) {
                mysqli_stmt_close($stmt_check);
                throw new Exception("Nomor KK " . $no_kk_baru . " sudah terdaftar di sistem. Gunakan nomor KK lain atau gunakan fitur edit biasa untuk menggabungkan.");
            }
            mysqli_stmt_close($stmt_check);

            // Update user utama menjadi Kepala Keluarga di KK baru
            $status_kk = 'Kepala Keluarga';
            $stmt_up_kepala = mysqli_prepare($koneksi, "UPDATE users SET no_kk = ?, status_keluarga = ?, alamat = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_up_kepala, "sssi", $no_kk_baru, $status_kk, $alamat_baru, $id_kepala);
            if (!mysqli_stmt_execute($stmt_up_kepala)) {
                mysqli_stmt_close($stmt_up_kepala);
                throw new Exception("Gagal memproses pembuatan KK baru untuk kepala keluarga.");
            }
            mysqli_stmt_close($stmt_up_kepala);

            // Update anggota/anak yang ikut pindah ke KK baru
            if (!empty($ikut_anggota)) {
                $placeholders = implode(',', array_fill(0, count($ikut_anggota), '?'));
                $stmt_up_child = mysqli_prepare($koneksi, "UPDATE users SET no_kk = ?, alamat = ? WHERE id IN ($placeholders)");
                $types_child = "ss" . str_repeat('i', count($ikut_anggota));
                $params_child = array_merge([$no_kk_baru, $alamat_baru], $ikut_anggota);
                mysqli_stmt_bind_param($stmt_up_child, $types_child, ...$params_child);
                if (!mysqli_stmt_execute($stmt_up_child)) {
                    mysqli_stmt_close($stmt_up_child);
                    throw new Exception("Gagal memindahkan anggota keluarga ke KK baru.");
                }
                mysqli_stmt_close($stmt_up_child);
            }

            mysqli_commit($koneksi);
            echo "<script>alert('Pemisahan Kartu Keluarga berhasil! Kepala Keluarga dan anggota terpilih telah dipindahkan ke No. KK " . $no_kk_baru . ".'); window.location='" . $redirect_url . "';</script>";
            exit();

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $msg = addslashes($e->getMessage());
            echo "<script>alert('Gagal memproses pemisahan KK: " . $msg . "'); window.history.back();</script>";
            exit();
        }
    }
}

header("Location: kelola_kk.php");
exit();
