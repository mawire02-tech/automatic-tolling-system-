<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?? 'SmartToll' ?> — SmartToll System</title>
  <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
  <meta name="base-path" content="<?= BASE_PATH ?>">
</head>
<body>
<div class="auth-page">
  <?= $content ?>
</div>
<script>
document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'dark');
</script>
</body>
</html>
