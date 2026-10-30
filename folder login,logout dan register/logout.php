<?php
session_start();


if (isset($_GET['confirm'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}


$nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'User';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Logout - RT RW Digital Bersatu</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: linear-gradient(135deg, #e6f7ff 0%, #dff6ff 60%);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            font-family: 'Segoe UI', sans-serif;
        }
        .swal2-popup {
            border-radius: 18px;
            box-shadow: 0 16px 40px rgba(15, 49, 91, 0.15);
        }
        .swal2-confirm {
            background: #0b69a3 !important;
            color: #ffffff !important;
        }
    </style>
</head>
<body>

    <script>
        Swal.fire({
            title: 'Logout Berhasil!',
           
            text: 'Sampai jumpa lagi, <?php echo htmlspecialchars($nama_user); ?>!',
            icon: 'success',
            showConfirmButton: true,
            confirmButtonColor: '#0b69a3',
            confirmButtonText: 'Kembali ke Login',
            background: '#ffffff',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                
                window.location.href = 'logout.php?confirm=1';
            }
        });
    </script>

</body>
</html>