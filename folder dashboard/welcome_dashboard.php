<div class="profile-header-card">
    <div class="user-details">
        <h1>Halo, <?php echo $_SESSION['nama']; ?> !</h1>
        <p>Login sebagai: <b><?php echo strtoupper($_SESSION['role']); ?></b> | <?php echo $_SESSION['email']; ?></p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <p class="stat-label">SALDO RT RW DIGITAL BERSATU SAAT INI</p>
        <?php
            $stmt = mysqli_prepare($koneksi, "SELECT SUM(CASE WHEN jenis='masuk' THEN jumlah ELSE -jumlah END) as total FROM keuangan");
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $d_saldo = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
        ?>
        <h2 class="stat-value">Rp <?php echo number_format($d_saldo['total'] ?? 0, 0, ',', '.'); ?></h2>
    </div>
    <div class="stat-card card-border-green">
        <p class="stat-label">TOTAL WARGA TERDAFTAR</p>
        <?php
            $stmt = mysqli_prepare($koneksi, "SELECT COUNT(*) as total FROM users WHERE role='Warga'");
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $d_warga = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
        ?>
        <h2 class="stat-value"><?php echo $d_warga['total']; ?> Orang</h2>
    </div>
</div>