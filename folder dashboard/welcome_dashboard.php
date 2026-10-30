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
            $q_saldo = mysqli_query($koneksi, "SELECT SUM(CASE WHEN jenis='masuk' THEN jumlah ELSE -jumlah END) as total FROM keuangan");
            $d_saldo = mysqli_fetch_assoc($q_saldo);
        ?>
        <h2 class="stat-value">Rp <?php echo number_format($d_saldo['total'] ?? 0, 0, ',', '.'); ?></h2>
    </div>
    <div class="stat-card card-border-green">
        <p class="stat-label">TOTAL WARGA TERDAFTAR</p>
        <?php
            $q_warga = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role='Warga'");
            $d_warga = mysqli_fetch_assoc($q_warga);
        ?>
        <h2 class="stat-value"><?php echo $d_warga['total']; ?> Orang</h2>
    </div>
</div>