<?php
session_start();

// Jika tombol "Kembali ke Login" di SweetAlert sudah diklik, hancurkan session dan ke login.php
if (isset($_GET['confirm'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Ambil nama dari session sebelum session-nya dihancurkan.
// Jika session 'nama' tidak ada, dia akan memunculkan teks 'User' sebagai cadangan.
$nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'User';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Logout - RT Digital</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #1e293b; /* Warna gelap biar senada dengan sidebar kamu */
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
    </style>
</head>
<body>

    <script>
        Swal.fire({
            title: 'Logout Berhasil!',
            // Mengubah nama kaku 'Reival' menjadi dinamis menggunakan PHP echo
            text: 'Sampai jumpa lagi, <?php echo htmlspecialchars($nama_user); ?>!',
            icon: 'success',
            showConfirmButton: true,
            confirmButtonColor: '#38bdf8',
            confirmButtonText: 'Kembali ke Login',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirect untuk menjalankan proses hapus session di atas
                window.location.href = 'logout.php?confirm=1';
            }
        });
    </script>

</body>
</html>