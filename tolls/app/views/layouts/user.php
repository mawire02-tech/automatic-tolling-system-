<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title ?? 'Portal') ?> — SmartToll PRO</title>
  <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <meta name="csrf-token" content="<?= Security::csrfToken() ?>">
  <meta name="base-path"  content="<?= BASE_PATH ?>/public">
  <meta name="currency"   content="<?= htmlspecialchars(Security::currency()) ?>">
</head>
<body>
<div class="app-wrapper">
  <aside class="sidebar" id="sidebar">
    <a href="<?= url('user/dashboard') ?>" class="sidebar-brand">
      <div class="brand-icon"><i class="fas fa-traffic-light"></i></div>
      <div class="brand-text">SmartToll <span>USER PORTAL</span></div>
    </a>
    <nav class="sidebar-nav">
      <div class="nav-group-label">My Account</div>
      <a href="<?= url('user/dashboard') ?>"    class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/user/dashboard')   !==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard
      </a>
      <a href="<?= url('user/transactions') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/user/transactions')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-credit-card"></i></span> My Transactions
      </a>
      <a href="<?= url('user/wallet') ?>"       class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/user/wallet')      !==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-wallet"></i></span> Wallet &amp; Top-Up
      </a>
      <a href="<?= url('user/vehicles') ?>"     class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/user/vehicles')    !==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-car"></i></span> My Vehicles
      </a>
      <a href="<?= url('user/profile') ?>"      class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/user/profile')     !==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-user"></i></span> Profile
      </a>
      <div class="nav-group-label">Account</div>
      <a href="<?= url('logout') ?>" class="nav-item">
        <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span> Logout
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></div>
        <div class="user-role">USER</div>
      </div>
    </div>
  </aside>
  <main class="main-content">
    <header class="topbar">
      <button class="btn-icon" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <div class="topbar-title"><?= htmlspecialchars($title ?? 'Dashboard') ?><small><?= date('l, F j, Y') ?></small></div>
      <div class="topbar-actions">
        <button class="btn-icon" id="themeToggle"><i class="fas fa-circle-half-stroke"></i></button>
        <a href="<?= url('logout') ?>" class="btn-icon"><i class="fas fa-right-from-bracket"></i></a>
      </div>
    </header>
    <div class="page-content"><?= $content ?></div>
  </main>
</div>
<div class="toast-container" id="toastContainer"></div>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (isset($pageScript)): ?><script><?= $pageScript ?></script><?php endif; ?>
</body>
</html>
