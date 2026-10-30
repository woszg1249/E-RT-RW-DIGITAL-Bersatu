<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$decodedScriptName = urldecode($scriptName);
$inDashboardFolder = stripos($decodedScriptName, '/folder dashboard/') !== false || stripos($scriptName, '/folder%20dashboard/') !== false;
$assetBase = $inDashboardFolder ? '../' : '';
$rootBase = $inDashboardFolder ? '../' : '';
$roleUser = strtoupper($_SESSION['role'] ?? 'WARGA');
$userName = $_SESSION['nama'] ?? 'Pengguna';
$userPhoto = $_SESSION['foto'] ?? '';

if (!empty($userPhoto) && file_exists(($inDashboardFolder ? '../img/' : 'img/') . $userPhoto)) {
    $displayPhoto = ($inDashboardFolder ? '../img/' : 'img/') . $userPhoto;
} else {
    $displayPhoto = 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=0284C7&color=fff';
}

$dashboardCssFile = __DIR__ . '/../folder dashboard/dashboard.css';
$dashboardCssVersion = file_exists($dashboardCssFile) ? '?v=' . filemtime($dashboardCssFile) : '';
$legacyDashboardCss = ($inDashboardFolder ? 'dashboard.css' : 'folder dashboard/dashboard.css') . $dashboardCssVersion;

$modernCssFile = __DIR__ . '/../assets/css/modern-theme.css';
$modernCssVersion = file_exists($modernCssFile) ? '?v=' . filemtime($modernCssFile) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="theme-color" content="#0284C7">
    <meta name="description" content="RT RW Digital Bersatu">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="RT RW Digital Bersatu">
    <link rel="manifest" href="<?php echo $rootBase; ?>manifest.json">
    <link rel="icon" href="<?php echo $rootBase; ?>img/icon-192.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $legacyDashboardCss; ?>">
    <?php if (!$inDashboardFolder && file_exists($modernCssFile)): ?>
        <link rel="stylesheet" href="<?php echo $assetBase; ?>assets/css/modern-theme.css<?php echo $modernCssVersion; ?>">
    <?php endif; ?>
    <title><?php echo $title ?? 'RT RW Digital Bersatu'; ?></title>
</head>
<body>
<div class="app-backdrop" id="app-backdrop"></div>
