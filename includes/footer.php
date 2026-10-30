<?php
// Unified footer: responsive & PWA controllers
?>
<script src="<?php echo $assetBase ?? ''; ?>js/responsive-app.js?v=<?php echo file_exists(__DIR__ . '/../js/responsive-app.js') ? filemtime(__DIR__ . '/../js/responsive-app.js') : '1'; ?>"></script>
<script src="<?php echo $assetBase ?? ''; ?>js/pwa-install.js"></script>
</body>
</html>
