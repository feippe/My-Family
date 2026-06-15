<?php $appUrl = rtrim((require BASE_PATH . '/app/Config/app.php')['url'], '/'); ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
<title>Familia</title>
<link rel="icon" href="<?= $appUrl ?>/assets/images/icon.svg" type="image/svg+xml">
<link rel="icon" href="<?= $appUrl ?>/assets/images/icon-192.png" type="image/png">
<link rel="apple-touch-icon" href="<?= $appUrl ?>/assets/images/apple-touch-icon.png">
<link rel="manifest" href="<?= $appUrl ?>/manifest.json">
<meta name="theme-color" content="#08080f">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Familia">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $appUrl ?>/assets/css/app.css">
</head>
<body class="auth-body">
<div class="auth-bg">
  <div class="auth-orb auth-orb-1"></div>
  <div class="auth-orb auth-orb-2"></div>
</div>
<main class="auth-wrapper">
  <?= $content ?>
</main>
<script>
  const APP_URL = '<?= $appUrl ?>';
</script>
<script src="<?= $appUrl ?>/assets/js/app.js"></script>
</body>
</html>
