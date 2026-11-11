<?php
require_once __DIR__ . '/../Koneksi.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. Cek ketersediaan NIK
if ($action === 'check_nik') {
    $nik = trim($_GET['nik'] ?? $_POST['nik'] ?? '');
    if (!preg_match('/^\d{16}$/', $nik)) {
        echo json_encode(['valid' => false, 'message' => 'NIK harus 16 digit angka.']);
        exit();
    }

    $stmt = mysqli_prepare($koneksi, "SELECT id, nama FROM users WHERE nik = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $nik);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $exists = mysqli_num_rows($res) > 0;
    mysqli_stmt_close($stmt);

    if ($exists) {
        echo json_encode([
            'valid' => false,
            'exists' => true,
            'message' => 'NIK ' . $nik . ' sudah terdaftar dalam sistem RT RW! Harap periksa kembali NIK Anda.'
        ]);
    } else {
        echo json_encode([
            'valid' => true,
            'exists' => false,
            'message' => 'NIK dapat digunakan.'
        ]);
    }
    exit();
}

// 2. Cari Data Kartu Keluarga untuk digabungi
if ($action === 'search_kk') {
    $no_kk = trim($_GET['no_kk'] ?? $_POST['no_kk'] ?? '');
    if (!preg_match('/^\d{16}$/', $no_kk)) {
        echo json_encode(['found' => false, 'message' => 'Nomor KK harus 16 digit angka.']);
        exit();
    }

    $stmt = mysqli_prepare($koneksi, "SELECT id, nama, nik, status_keluarga, kelamin, alamat, rt, rw FROM users WHERE no_kk = ? ORDER BY FIELD(status_keluarga, 'Kepala Keluarga', 'Suami', 'Istri', 'Anak', 'Famili Lain'), id ASC");
    mysqli_stmt_bind_param($stmt, "s", $no_kk);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $members = [];
    $kepala = null;
    $alamat = '';
    $rt = '06';
    $rw = '19';

    while ($row = mysqli_fetch_assoc($res)) {
        $members[] = [
            'nama' => $row['nama'],
            'nik' => substr($row['nik'], 0, 6) . '******' . substr($row['nik'], -4), // semi mask untuk privasi
            'status_keluarga' => $row['status_keluarga'],
            'kelamin' => $row['kelamin']
        ];
        if ($row['status_keluarga'] === 'Kepala Keluarga' && empty($kepala)) {
            $kepala = $row['nama'];
        }
        if (empty($alamat)) {
            $alamat = $row['alamat'];
        }
        if (!empty($row['rt'])) {
            $rt = $row['rt'];
        }
        if (!empty($row['rw'])) {
            $rw = $row['rw'];
        }
    }
    mysqli_stmt_close($stmt);

    if (!empty($members)) {
        echo json_encode([
            'found' => true,
            'no_kk' => $no_kk,
            'kepala_keluarga' => $kepala ?: ($members[0]['nama'] ?? 'Belum Ditentukan'),
            'alamat' => $alamat,
            'rt' => $rt,
            'rw' => $rw,
            'total_anggota' => count($members),
            'members' => $members
        ]);
    } else {
        echo json_encode([
            'found' => false,
            'message' => 'Kartu Keluarga dengan Nomor ' . $no_kk . ' belum terdaftar. Anda dapat memilih opsi "Buat Kartu Keluarga Baru" jika Anda adalah Kepala Keluarga.'
        ]);
    }
    exit();
}

echo json_encode(['error' => 'Aksi tidak valid']);
exit();
