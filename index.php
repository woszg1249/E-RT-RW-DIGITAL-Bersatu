<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/Koneksi.php';

// Ambil statistik riil dari database
$total_warga = 0;
$total_kk = 0;
$total_rt06 = 0;
$total_rt07 = 0;

if ($koneksi) {
    $q_warga = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga'");
    if ($r = mysqli_fetch_assoc($q_warga)) {
        $total_warga = (int)$r['total'];
    }

    $q_kk = mysqli_query($koneksi, "SELECT COUNT(DISTINCT no_kk) as total FROM users WHERE role = 'Warga' AND no_kk IS NOT NULL AND no_kk != ''");
    if ($r = mysqli_fetch_assoc($q_kk)) {
        $total_kk = (int)$r['total'];
    }

    $q_rt06 = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga' AND rt = '06'");
    if ($r = mysqli_fetch_assoc($q_rt06)) {
        $total_rt06 = (int)$r['total'];
    }

    $q_rt07 = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'Warga' AND rt = '07'");
    if ($r = mysqli_fetch_assoc($q_rt07)) {
        $total_rt07 = (int)$r['total'];
    }
}

// Cek apakah user sedang login
$is_logged_in = isset($_SESSION['nama']);
$user_name = $_SESSION['nama'] ?? '';
$user_role = strtoupper($_SESSION['role'] ?? 'WARGA');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RT RW Digital Bersatu - Portal Layanan Warga & Panduan Aplikasi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap">
    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --primary-light: #e0f2fe;
            --accent: #0ea5e9;
            --dark: #0f172a;
            --text: #1e293b;
            --text-soft: #64748b;
            --bg-light: #f8fafc;
            --white: #ffffff;
            --card-border: rgba(2, 132, 199, 0.15);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text);
            background-color: #f0f7fc;
            line-height: 1.6;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* NAVBAR */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--dark);
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
        }

        .brand-text h2 {
            font-size: 1.15rem;
            font-weight: 800;
            line-height: 1.2;
            color: #0f172a;
        }

        .brand-text span {
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--primary);
            letter-spacing: 0.04em;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: #475569;
            font-weight: 600;
            font-size: 0.92rem;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-nav-login {
            padding: 9px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            background: var(--primary);
            color: var(--white);
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.25);
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-nav-login:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-nav-register {
            padding: 9px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            background: rgba(2, 132, 199, 0.1);
            color: var(--primary-dark);
            border: 1px solid rgba(2, 132, 199, 0.25);
            transition: all 0.2s;
        }

        .btn-nav-register:hover {
            background: rgba(2, 132, 199, 0.18);
        }

        /* HERO SECTION */
        .hero {
            padding: 140px 24px 80px;
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 40px;
            align-items: center;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #e0f2fe;
            color: #0369a1;
            padding: 6px 16px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
            margin-bottom: 20px;
            border: 1px solid #bae6fd;
        }

        .hero-title {
            font-size: 2.85rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.18;
            letter-spacing: -0.025em;
            margin-bottom: 20px;
        }

        .hero-title span {
            color: var(--primary);
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.05rem;
            color: #64748b;
            line-height: 1.65;
            margin-bottom: 32px;
        }

        .hero-btns {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 36px;
        }

        .btn-hero-primary {
            padding: 14px 28px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            box-shadow: 0 10px 24px rgba(2, 132, 199, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.25s;
        }

        .btn-hero-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(2, 132, 199, 0.4);
        }

        .btn-hero-secondary {
            padding: 14px 26px;
            background: var(--white);
            color: #334155;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            border: 1.5px solid #cbd5e1;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }

        .btn-hero-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #f8fafc;
        }

        /* HERO CARD / VISUAL */
        .hero-visual-card {
            background: linear-gradient(145deg, #ffffff 0%, #f0f9ff 100%);
            border: 1.5px solid #bae6fd;
            border-radius: 28px;
            padding: 32px;
            box-shadow: 0 20px 48px rgba(2, 132, 199, 0.12);
            position: relative;
        }

        .hero-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px dashed #cbd5e1;
        }

        .hero-card-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .hero-live-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #dcfce7;
            color: #15803d;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
        }

        .hero-stats-mini-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 20px;
        }

        .mini-stat-box {
            background: var(--white);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px;
            text-align: center;
        }

        .mini-stat-num {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.1;
        }

        .mini-stat-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            margin-top: 4px;
        }

        .hero-iot-banner-mini {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            border-radius: 16px;
            padding: 16px;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .hero-iot-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: rgba(56, 189, 248, 0.2);
            color: #38bdf8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .hero-iot-text h4 {
            font-size: 0.92rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 2px;
        }

        .hero-iot-text p {
            font-size: 0.78rem;
            color: #cbd5e1;
            margin: 0;
            line-height: 1.3;
        }

        /* SECTION HEADERS */
        .section-header {
            text-align: center;
            max-width: 680px;
            margin: 0 auto 50px;
        }

        .section-tag {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 999px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .section-title {
            font-size: 2.1rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 12px;
        }

        .section-subtitle {
            font-size: 0.98rem;
            color: #64748b;
        }

        /* STATS COUNTER STRIP */
        .stats-strip {
            background: var(--white);
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 40px 24px;
            margin-bottom: 80px;
        }

        .stats-strip-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            text-align: center;
        }

        .stat-item h3 {
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.1;
            margin-bottom: 4px;
        }

        .stat-item p {
            font-size: 0.9rem;
            font-weight: 600;
            color: #475569;
        }

        /* FEATURES GRID */
        .features-section {
            max-width: 1200px;
            margin: 0 auto 100px;
            padding: 0 24px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }

        .feature-card {
            background: var(--white);
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 28px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px rgba(2, 132, 199, 0.1);
            border-color: #bae6fd;
        }

        .feature-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 18px;
        }

        .feature-card.blue .feature-icon { background: #e0f2fe; color: #0284c7; }
        .feature-card.emerald .feature-icon { background: #dcfce7; color: #16a34a; }
        .feature-card.amber .feature-icon { background: #fef3c7; color: #d97706; }
        .feature-card.purple .feature-icon { background: #f3e8ff; color: #9333ea; }
        .feature-card.rose .feature-icon { background: #ffe4e6; color: #e11d48; }
        .feature-card.indigo .feature-icon { background: #e0e7ff; color: #4f46e5; }

        .feature-card h3 {
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .feature-card p {
            font-size: 0.9rem;
            color: #64748b;
            line-height: 1.6;
        }

        /* PANDUAN APLIKASI (USER GUIDE TABS) */
        .guide-section {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 90px 24px;
            margin-bottom: 80px;
        }

        .guide-container {
            max-width: 1080px;
            margin: 0 auto;
        }

        .guide-tabs {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 26px;
            border-radius: 999px;
            font-size: 0.95rem;
            font-weight: 700;
            border: 2px solid #e2e8f0;
            background: #f8fafc;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .tab-btn.active {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
            box-shadow: 0 6px 18px rgba(2, 132, 199, 0.3);
        }

        .guide-content {
            display: none;
            animation: fadeIn 0.35s ease;
        }

        .guide-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* STEP CARDS */
        .steps-timeline {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .step-card {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 18px;
            padding: 24px;
            position: relative;
            transition: all 0.2s;
        }

        .step-card:hover {
            background: #ffffff;
            border-color: #bae6fd;
            box-shadow: 0 10px 24px rgba(2, 132, 199, 0.08);
        }

        .step-number {
            width: 38px;
            height: 38px;
            background: var(--primary);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1rem;
            margin-bottom: 16px;
        }

        .step-card h4 {
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .step-card p {
            font-size: 0.88rem;
            color: #64748b;
            line-height: 1.55;
            margin-bottom: 12px;
        }

        .step-badge {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 0.74rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
        }

        /* FAQ ACCORDION */
        .faq-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            margin-bottom: 12px;
            overflow: hidden;
            transition: all 0.2s;
        }

        .faq-question {
            padding: 18px 22px;
            font-weight: 700;
            color: #0f172a;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            user-select: none;
        }

        .faq-question i {
            color: var(--primary);
            transition: transform 0.25s;
        }

        .faq-answer {
            padding: 0 22px 18px;
            font-size: 0.92rem;
            color: #475569;
            line-height: 1.6;
            display: none;
        }

        .faq-item.active {
            background: #ffffff;
            border-color: #bae6fd;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.08);
        }

        .faq-item.active .faq-answer {
            display: block;
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        /* PENGURUS SECTION */
        .pengurus-section {
            max-width: 1200px;
            margin: 0 auto 100px;
            padding: 0 24px;
        }

        .pengurus-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        .pengurus-card {
            background: var(--white);
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 4px 14px rgba(0,0,0,0.03);
            transition: all 0.2s;
        }

        .pengurus-card:hover {
            transform: translateY(-3px);
            border-color: #bae6fd;
            box-shadow: 0 12px 28px rgba(2, 132, 199, 0.1);
        }

        .pengurus-avatar {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background: #e0f2fe;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 14px;
            border: 3px solid #bae6fd;
        }

        .pengurus-card h4 {
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .pengurus-role {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 6px;
        }

        .pengurus-wilayah {
            font-size: 0.8rem;
            color: #64748b;
        }

        /* CTA BANNER */
        .cta-strip {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #0284c7 100%);
            border-radius: 28px;
            padding: 60px 32px;
            text-align: center;
            color: var(--white);
            max-width: 1150px;
            margin: 0 auto 100px;
            box-shadow: 0 20px 48px rgba(15, 23, 42, 0.25);
            position: relative;
            overflow: hidden;
        }

        .cta-strip h2 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .cta-strip p {
            font-size: 1.05rem;
            color: #cbd5e1;
            max-width: 650px;
            margin: 0 auto 30px;
        }

        .cta-actions {
            display: flex;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        /* FOOTER */
        .footer {
            background: #0f172a;
            color: #94a3b8;
            padding: 60px 24px 30px;
            font-size: 0.9rem;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-col h4 {
            color: var(--white);
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .footer-col p {
            line-height: 1.6;
            margin-bottom: 14px;
        }

        .footer-col ul {
            list-style: none;
        }

        .footer-col ul li {
            margin-bottom: 10px;
        }

        .footer-col ul li a {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-col ul li a:hover {
            color: #38bdf8;
        }

        .footer-bottom {
            max-width: 1200px;
            margin: 0 auto;
            padding-top: 24px;
            border-top: 1px solid #1e293b;
            text-align: center;
            font-size: 0.82rem;
            color: #64748b;
        }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .hero {
                grid-template-columns: 1fr;
                padding-top: 110px;
                text-align: center;
            }
            .hero-btns {
                justify-content: center;
            }
            .stats-strip-container {
                grid-template-columns: 1fr 1fr;
            }
            .footer-container {
                grid-template-columns: 1fr;
            }
            .nav-links {
                display: none;
            }
        }

        @media (max-width: 600px) {
            .hero-title {
                font-size: 2.1rem;
            }
            .stats-strip-container {
                grid-template-columns: 1fr;
            }
            .btn-hero-primary, .btn-hero-secondary {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="brand-logo">
                <div class="brand-icon">
                    <i class="fa-solid fa-house-user"></i>
                </div>
                <div class="brand-text">
                    <h2>RT RW Digital</h2>
                    <span>WILAYAH RW 19 BEKASI TIMUR</span>
                </div>
            </a>

            <ul class="nav-links">
                <li><a href="#beranda">Beranda</a></li>
                <li><a href="#fitur">Layanan</a></li>
                <li><a href="#panduan">Panduan Aplikasi</a></li>
                <li><a href="#pengurus">Pengurus Lingkungan</a></li>
                <li><a href="#faq">FAQ</a></li>
            </ul>

            <div class="nav-actions">
                <?php if ($is_logged_in): ?>
                    <a href="folder dashboard/dashboard.php" class="btn-nav-login">
                        <i class="fa-solid fa-table-columns"></i> Ke Dashboard
                    </a>
                <?php else: ?>
                    <a href="folder login,logout dan register/login.php" class="btn-nav-login">
                        <i class="fa-solid fa-right-to-bracket"></i> Masuk
                    </a>
                    <a href="folder login,logout dan register/register.php" class="btn-nav-register">
                        <i class="fa-solid fa-user-plus"></i> Daftar
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero" id="beranda">
        <div class="hero-content">
            <span class="hero-badge">
                <i class="fa-solid fa-shield-halved"></i> Portal Pelayanan Warga RT 06 & RT 07 / RW 19
            </span>
            <h1 class="hero-title">
                Sistem Informasi <span>RT RW Digital Bersatu</span>
            </h1>
            <p class="hero-subtitle">
                Platform terpadu untuk kemudahan administrasi warga, transparansi kas lingkungan, pengajuan surat pengantar resmi, pendataan Kartu Keluarga (KK), hingga koperasi RT dengan verifikasi IoT e-KTP.
            </p>
            <div class="hero-btns">
                <?php if ($is_logged_in): ?>
                    <a href="folder dashboard/dashboard.php" class="btn-hero-primary">
                        <i class="fa-solid fa-house"></i> Buka Dashboard Saya (<?php echo htmlspecialchars($user_name); ?>)
                    </a>
                <?php else: ?>
                    <a href="folder login,logout dan register/login.php" class="btn-hero-primary">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Masuk ke Akun Warga
                    </a>
                    <a href="folder login,logout dan register/register.php" class="btn-hero-secondary">
                        <i class="fa-solid fa-user-plus"></i> Daftar Akun Warga Baru
                    </a>
                <?php endif; ?>
                <a href="#panduan" class="btn-hero-secondary">
                    <i class="fa-solid fa-book-open-reader"></i> Baca Panduan Aplikasi
                </a>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-visual-card">
                <div class="hero-card-header">
                    <div class="hero-card-title">
                        <i class="fa-solid fa-circle-check text-primary" style="color:var(--primary);"></i>
                        <span>Ringkasan Lingkungan</span>
                    </div>
                    <span class="hero-live-pill"><i class="fa-solid fa-circle" style="font-size:6px;"></i> Aktif 24 Jam</span>
                </div>

                <div class="hero-stats-mini-grid">
                    <div class="mini-stat-box">
                        <div class="mini-stat-num"><?php echo $total_warga; ?></div>
                        <div class="mini-stat-label">Warga Terdaftar</div>
                    </div>
                    <div class="mini-stat-box">
                        <div class="mini-stat-num"><?php echo $total_kk; ?></div>
                        <div class="mini-stat-label">Kartu Keluarga</div>
                    </div>
                </div>

                <div class="hero-iot-banner-mini">
                    <div class="hero-iot-icon">
                        <i class="fa-solid fa-microchip"></i>
                    </div>
                    <div class="hero-iot-text">
                        <h4>Fitur IoT Scan e-KTP Siap Pakai</h4>
                        <p>Pengajuan peminjaman dana koperasi secara instan dengan verifikasi kartu e-KTP warga.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- STATS COUNTER STRIP -->
    <section class="stats-strip">
        <div class="stats-strip-container">
            <div class="stat-item">
                <h3><?php echo $total_warga; ?>+</h3>
                <p>Warga Terverifikasi</p>
            </div>
            <div class="stat-item">
                <h3><?php echo $total_kk; ?></h3>
                <p>Kepala Keluarga (KK)</p>
            </div>
            <div class="stat-item">
                <h3>RT 06 & RT 07</h3>
                <p>Wilayah Terpadu RW 19</p>
            </div>
            <div class="stat-item">
                <h3>8+ Layanan</h3>
                <p>Modul Digital Terintegrasi</p>
            </div>
        </div>
    </section>

    <!-- FITUR LAYANAN -->
    <section class="features-section" id="fitur">
        <div class="section-header">
            <span class="section-tag">Layanan Terpadu</span>
            <h2 class="section-title">Solusi Digital Lengkap untuk Warga & Pengurus</h2>
            <p class="section-subtitle">Semua kebutuhan administrasi RT dan pelayanan warga kini dapat diakses secara online dari ponsel atau komputer Anda.</p>
        </div>

        <div class="features-grid">
            <div class="feature-card blue">
                <div class="feature-icon"><i class="fa-solid fa-file-signature"></i></div>
                <h3>Layanan Surat Pengantar Online</h3>
                <p>Warga dapat mengajukan surat keterangan domisili, permohonan KTP/KK, izin usaha, hingga pengantar SKCK tanpa perlu antre ke rumah pengurus RT.</p>
            </div>

            <div class="feature-card emerald">
                <div class="feature-icon"><i class="fa-solid fa-coins"></i></div>
                <h3>Kas RT & Tagihan Iuran Transparan</h3>
                <p>Pencatatan uang kas masuk, kas keluar, dan tagihan iuran keamanan & kebersihan yang dapat dipantau bersama secara terbuka dan akuntabel.</p>
            </div>

            <div class="feature-card amber">
                <div class="feature-icon"><i class="fa-solid fa-people-roof"></i></div>
                <h3>Data Kependudukan & Kartu Keluarga (KK)</h3>
                <p>Pencatatan silsilah keluarga (Kepala Keluarga, Istri, Anak, Famili) sesuai standar Dukcapil lengkap dengan fitur cetak KK resmi.</p>
            </div>

            <div class="feature-card purple">
                <div class="feature-icon"><i class="fa-solid fa-microchip"></i></div>
                <h3>Koperasi RT Terintegrasi IoT e-KTP</h3>
                <p>Simpan pinjam warga yang dilengkapi teknologi IoT e-KTP Scanner. Cukup tempelkan e-KTP untuk verifikasi identitas dan simulasi angsuran cepat.</p>
            </div>

            <div class="feature-card rose">
                <div class="feature-icon"><i class="fa-solid fa-baby"></i></div>
                <h3>Posyandu Balita & Lansia</h3>
                <p>Jadwal imunisasi, penimbangan balita, pemeriksaan tensi lansia, dan grafik perkembangan kesehatan warga tercatat rapi.</p>
            </div>

            <div class="feature-card indigo">
                <div class="feature-icon"><i class="fa-solid fa-recycle"></i></div>
                <h3>Bank Sampah & Kebersihan</h3>
                <p>Pengelolaan sampah anorganik warga yang ditukar dengan saldo tabungan kas lingkungan untuk menciptakan kawasan asri.</p>
            </div>
        </div>
    </section>

    <!-- SECTION PANDUAN PENGGUNAAN APLIKASI (USER GUIDE INTERAKTIF) -->
    <section class="guide-section" id="panduan">
        <div class="guide-container">
            <div class="section-header">
                <span class="section-tag"><i class="fa-solid fa-book"></i> Panduan Penggunaan</span>
                <h2 class="section-title">Cara Memakai Aplikasi RT RW Digital</h2>
                <p class="section-subtitle">Pilih panduan sesuai peran Anda untuk mempelajari langkah-langkah penggunaan aplikasi dengan mudah.</p>
            </div>

            <!-- TAB SELECTOR -->
            <div class="guide-tabs">
                <button class="tab-btn active" onclick="switchGuideTab('warga')">
                    <i class="fa-solid fa-user"></i> Panduan untuk Warga
                </button>
                <button class="tab-btn" onclick="switchGuideTab('admin')">
                    <i class="fa-solid fa-user-shield"></i> Panduan untuk Pengurus RT / RW
                </button>
                <button class="tab-btn" onclick="switchGuideTab('faq')">
                    <i class="fa-solid fa-circle-question"></i> Tanya Jawab (FAQ)
                </button>
            </div>

            <!-- TAB 1: PANDUAN UNTUK WARGA -->
            <div class="guide-content active" id="guide-warga">
                <div class="steps-timeline">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h4>Pendaftaran Akun Baru</h4>
                        <p>Buka halaman <strong>Daftar</strong>. Terdapat dua pilihan:</p>
                        <ul style="font-size:0.85rem; color:#475569; padding-left:18px; margin-bottom:10px;">
                            <li><strong>Gabung KK yang Ada</strong>: Masukkan 16 digit No. KK keluarga Anda, klik "Cari KK", lalu konfirmasi <em>"Ya, ini KK Saya"</em>.</li>
                            <li><strong>Buat KK Baru</strong>: Khusus Kepala Keluarga baru (Ayah/Ibu), tentukan pilihan RT (RT 06 atau RT 07) di wilayah RW 19.</li>
                        </ul>
                        <span class="step-badge">Wajib NIK 16 Digit Unik</span>
                    </div>

                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h4>Masuk (Login) & Edit Profil</h4>
                        <p>Masukkan username dan password yang telah didaftarkan. Akun baru secara otomatis memiliki foto profil polosan <code>profile.jpg</code>. Anda dapat mengubah foto profil dan data diri kapan saja melalui menu <strong>Edit Profil</strong>.</p>
                        <span class="step-badge">Foto Profil Polos Default</span>
                    </div>

                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h4>Pengajuan Surat Pengantar</h4>
                        <p>Masuk ke menu <strong>Layanan Surat</strong>, klik "Ajukan Surat". Pilih keperluan (KTP, Domisili, SKCK, Usaha), isi data, lalu kirim. Setelah disetujui Ketua RT, Anda dapat langsung mengunduh surat resmi.</p>
                        <span class="step-badge">Proses Cepat & Resmi</span>
                    </div>

                    <div class="step-card">
                        <div class="step-number">4</div>
                        <h4>Pantau Kas & Bayar Tagihan</h4>
                        <p>Pada menu <strong>Laporan Keuangan</strong>, warga dapat melihat transparansi seluruh saldo kas RT, riwayat pemasukan, dan pengeluaran lingkungan. Cek status pembayaran iuran bulanan Anda.</p>
                        <span class="step-badge">100% Transparan</span>
                    </div>

                    <div class="step-card">
                        <div class="step-number">5</div>
                        <h4>Pinjam Dana Koperasi (IoT e-KTP)</h4>
                        <p>Masuk ke menu <strong>Koperasi RT</strong>. Tempelkan kartu e-KTP Anda pada alat sensor RFID/NFC di meja kasir koperasi (atau klik <em>Simulasi Tap KTP</em>). Sistem otomatis membaca data Anda, pilih nominal & tenor, lalu ajukan pinjaman.</p>
                        <span class="step-badge">Verifikasi Instan via IoT</span>
                    </div>

                    <div class="step-card">
                        <div class="step-number">6</div>
                        <h4>Layanan Lainnya</h4>
                        <p>Manfaatkan layanan <strong>Bank Sampah</strong> untuk menabung sampah daur ulang, cek jadwal <strong>Posyandu</strong> anak/lansia, serta pasang produk jualan Anda di katalog <strong>UMKM Lokal</strong> RT.</p>
                        <span class="step-badge">Pemberdayaan Warga</span>
                    </div>
                </div>
            </div>

            <!-- TAB 2: PANDUAN UNTUK PENGURUS RT / RW (ADMIN) -->
            <div class="guide-content" id="guide-admin">
                <div class="steps-timeline">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h4>Akses Login Pengurus Lingkungan</h4>
                        <p>Pengurus lingkungan login menggunakan akun yang telah disiapkan:</p>
                        <ul style="font-size:0.85rem; color:#475569; padding-left:18px; margin-bottom:10px;">
                            <li><strong>Ketua RT 06</strong>: Username <code>dimas</code></li>
                            <li><strong>Ketua RT 07</strong>: Username <code>rt07</code></li>
                            <li><strong>Ketua RW 19</strong>: Username <code>fabian</code></li>
                            <li><strong>Admin Utama</strong>: Username <code>admin</code></li>
                        </ul>
                        <span class="step-badge">Akses Multi-Role</span>
                    </div>

                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h4>Kelola Warga & Kartu Keluarga</h4>
                        <p>Buka menu <strong>Kelola Kartu Keluarga</strong>. Pengurus dapat memfilter warga per wilayah RT 06 / RT 07, memperbaiki status keluarga (Kepala, Istri, Anak), memproses pisah KK, dan mencetak format cetak KK resmi standar Dukcapil.</p>
                        <span class="step-badge">Sinkronisasi 100%</span>
                    </div>

                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h4>Verifikasi Surat Pengantar</h4>
                        <p>Pada menu <strong>Kelola Surat</strong>, pengurus RT akan menerima notifikasi pengajuan surat dari warga. Cek data pemohon, lalu klik <strong>Setujui</strong> atau <strong>Tolak</strong> (disertai alasan).</p>
                        <span class="step-badge">Tanda Tangan & Format Standar</span>
                    </div>

                    <div class="step-card">
                        <div class="step-number">4</div>
                        <h4>Pembukuan Kas RT & Tagihan</h4>
                        <p>Gunakan menu <strong>Input Kas RT</strong> untuk mencatat uang masuk dari iuran warga atau pengeluaran kegiatan lingkungan (kerja bakti, lampu jalan, dll.) lengkap dengan bukti nota pembayaran.</p>
                        <span class="step-badge">Laporan Neraca Real-time</span>
                    </div>

                    <div class="step-card">
                        <div class="step-number">5</div>
                        <h4>Kelola Koperasi & Sensor IoT</h4>
                        <p>Pada menu <strong>Koperasi RT</strong>, hubungkan modul IoT ESP32 / RFID Reader ke jaringan WiFi RT. Terima data scan e-KTP warga secara otomatis, verifikasi permohonan pinjaman, dan catat angsuran cicilan warga.</p>
                        <span class="step-badge">Terminal IoT Cerdas</span>
                    </div>

                    <div class="step-card">
                        <div class="step-number">6</div>
                        <h4>Inventaris Aset Lingkungan</h4>
                        <p>Pantau barang inventaris milik RT (tenda, kursi, sound system, mesin rumput) pada menu <strong>Aset RT</strong> untuk mengelola peminjaman dan kondisi barang secara rapi.</p>
                        <span class="step-badge">Pengawasan Fasilitas</span>
                    </div>
                </div>
            </div>

            <!-- TAB 3: FAQ / PERTANYAAN UMUM -->
            <div class="guide-content" id="guide-faq">
                <div class="faq-accordion">
                    <div class="faq-item active">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Bagaimana jika NIK saya tertolak saat mendaftar akun warga?</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Sistem RT RW Digital menerapkan validasi 16 digit angka unik untuk memastikan satu NIK hanya memiliki satu akun. Jika NIK tertolak dengan pesan "sudah terdaftar", artinya data Anda telah terdaftar sebelumnya. Silakan langsung login menggunakan username Anda atau hubungi Admin RT untuk verifikasi akun Anda.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Bagaimana cara anggota keluarga bergabung ke Kartu Keluarga (KK)?</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Pada halaman pendaftaran, pilih opsi <strong>"Gabung KK yang Sudah Ada"</strong>. Masukkan 16 digit Nomor KK keluarga Anda dan klik "Cari KK". Setelah data anggota KK muncul, pilih konfirmasi <strong>"Ya, ini KK Saya"</strong>, lalu pilih peran Anda (Istri, Anak, Suami, atau Famili Lain). Alamat dan wilayah RT/RW Anda akan otomatis mengikuti KK tersebut.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Bagaimana cara kerja fitur IoT Scan e-KTP di Koperasi RT?</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Di kantor Koperasi RT disediakan perangkat scanner RFID/NFC. Saat Anda menempelkan kartu fisik e-KTP pada sensor, alat IoT akan membaca chip kartu Anda dan mengirim sinyal ke server. Layar aplikasi koperasi akan otomatis membunyikan nada bip dan menampilkan data e-KTP Anda serta kalkulator simulasi pinjaman dana. Anda juga dapat mencoba demo fitur ini dengan tombol "Simulasi Tap KTP".
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Siapa saja yang memiliki hak akses sebagai Pengurus (Admin)?</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Pengurus yang memiliki hak akses pengelolaan adalah Ketua RT 06 (Dimas), Ketua RT 07 (Bambang), Ketua RW 19 (Fabian), serta Admin Utama Sistem (Reival Al Khafi). Pengurus dapat melihat data sesuai yurisdiksi wilayah RT masing-masing.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Bagaimana cara mengubah foto profil akun saya?</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Semua akun baru secara default memiliki foto profil polos <code>profile.jpg</code>. Untuk mengubahnya, silakan masuk (login), klik menu <strong>Edit Profil</strong> di sidebar kiri, klik ikon kamera pada lingkaran foto, pilih foto terbaik Anda (format JPG/PNG maks 2MB), lalu klik "Simpan Perubahan".
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PROFIL PENGURUS LINGKUNGAN -->
    <section class="pengurus-section" id="pengurus">
        <div class="section-header">
            <span class="section-tag">Struktur Kepengurusan</span>
            <h2 class="section-title">Pengurus Lingkungan RW 19</h2>
            <p class="section-subtitle">Amanah melayani seluruh warga RT 06 dan RT 07 di wilayah RW 19 Bekasi Timur.</p>
        </div>

        <div class="pengurus-grid">
            <div class="pengurus-card">
                <div class="pengurus-avatar"><i class="fa-solid fa-user-tie"></i></div>
                <h4>Fabian</h4>
                <div class="pengurus-role">Ketua RW 19</div>
                <div class="pengurus-wilayah">Koordinator Wilayah RW 19</div>
            </div>

            <div class="pengurus-card">
                <div class="pengurus-avatar"><i class="fa-solid fa-user-shield"></i></div>
                <h4>Dimas</h4>
                <div class="pengurus-role">Ketua RT 06</div>
                <div class="pengurus-wilayah">Pelayanan Warga RT 06 / RW 19</div>
            </div>

            <div class="pengurus-card">
                <div class="pengurus-avatar"><i class="fa-solid fa-user-shield"></i></div>
                <h4>Bambang</h4>
                <div class="pengurus-role">Ketua RT 07</div>
                <div class="pengurus-wilayah">Pelayanan Warga RT 07 / RW 19</div>
            </div>

            <div class="pengurus-card">
                <div class="pengurus-avatar"><i class="fa-solid fa-laptop-code"></i></div>
                <h4>Reival Al Khafi</h4>
                <div class="pengurus-role">Administrator Sistem</div>
                <div class="pengurus-wilayah">Pengembang & Pengelola IT RT RW</div>
            </div>
        </div>
    </section>

    <!-- CALL TO ACTION STRIP -->
    <section class="cta-strip">
        <h2>Siap Bergabung dengan RT RW Digital Bersatu?</h2>
        <p>Mari bersama-sama mewujudkan lingkungan warga yang modern, transparan, dan terhubung dalam satu genggaman.</p>
        <div class="cta-actions">
            <?php if ($is_logged_in): ?>
                <a href="folder dashboard/dashboard.php" class="btn-hero-primary" style="background:#ffffff; color:#0284c7;">
                    <i class="fa-solid fa-table-columns"></i> Buka Dashboard
                </a>
            <?php else: ?>
                <a href="folder login,logout dan register/login.php" class="btn-hero-primary" style="background:#ffffff; color:#0284c7;">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Masuk Sekarang
                </a>
                <a href="folder login,logout dan register/register.php" class="btn-hero-secondary" style="background:transparent; color:#ffffff; border-color:rgba(255,255,255,0.4);">
                    <i class="fa-solid fa-user-plus"></i> Daftar Warga Baru
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-col">
                <h4>RT RW Digital Bersatu</h4>
                <p>Sistem Informasi Manajemen Pelayanan Warga Rukun Tetangga dan Rukun Warga digital resmi untuk wilayah RT 06 dan RT 07 di lingkungan RW 19.</p>
                <p style="color:#64748b; font-size:0.84rem;"><i class="fa-solid fa-location-dot text-primary"></i> Kel. Margahayu, Kec. Bekasi Timur, Kota Bekasi, Jawa Barat 17113</p>
            </div>

            <div class="footer-col">
                <h4>Tautan Cepat</h4>
                <ul>
                    <li><a href="#beranda">Beranda Utama</a></li>
                    <li><a href="#fitur">Layanan Digital</a></li>
                    <li><a href="#panduan">Panduan Penggunaan</a></li>
                    <li><a href="folder login,logout dan register/login.php">Halaman Masuk (Login)</a></li>
                    <li><a href="folder login,logout dan register/register.php">Pendaftaran Warga Baru</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Jam Operasional Sekretariat</h4>
                <p>Pelayanan Online: <strong>24 Jam Setiap Hari</strong></p>
                <p>Pelayanan Tatap Muka: <strong>Senin - Sabtu (19.00 - 21.30 WIB)</strong></p>
                <p style="color:#38bdf8;"><i class="fa-brands fa-whatsapp"></i> Kontak Layanan Pengurus Siaga</p>
            </div>
        </div>

        <div class="footer-bottom">
            &copy; <?php echo date('Y'); ?> RT RW Digital Bersatu - Wilayah RW 19 (RT 06 & RT 07). Hak Cipta Dilindungi.
        </div>
    </footer>

    <script>
        // Script Tab Switcher Panduan
        function switchGuideTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.guide-content').forEach(content => content.classList.remove('active'));

            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            }

            const targetContent = document.getElementById('guide-' + tabName);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        }

        // Script Accordion FAQ
        function toggleFaq(element) {
            const item = element.parentElement;
            item.classList.toggle('active');
        }
    </script>
</body>
</html>
