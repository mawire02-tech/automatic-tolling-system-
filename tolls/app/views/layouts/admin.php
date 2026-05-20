<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title ?? 'Admin Panel') ?> — SmartToll PRO</title>
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
    <a href="<?= url('admin/dashboard') ?>" class="sidebar-brand">
      <div class="brand-icon"><i class="fas fa-traffic-light"></i></div>
      <div class="brand-text">SmartToll <span>
        <?= Security::isAdmin() ? 'ADMIN' : 'OPERATOR' ?> v2.4
      </span></div>
    </a>

    <nav class="sidebar-nav">
      <div class="nav-group-label">Overview</div>
      <a href="<?= url('admin/dashboard') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/dashboard')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard
      </a>

      <?php if (Security::isAdmin()): ?>
      <div class="nav-group-label">Operations</div>
      <a href="<?= url('admin/transactions') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/transactions')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-credit-card"></i></span> Transactions
      </a>
      <a href="<?= url('admin/topups') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/topups')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-wallet"></i></span> Top-Up Requests
        <?php $pc = Database::getInstance()->fetchOne("SELECT COUNT(*) as c FROM topup_requests WHERE status='pending'")['c'] ?? 0;
              if ($pc > 0): ?><span class="nav-badge"><?= $pc ?></span><?php endif; ?>
      </a>
      <a href="<?= url('admin/reports') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/reports')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-chart-line"></i></span> Reports
      </a>
      <a href="<?= url('admin/notifications') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/notifications')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-bell"></i></span> Alerts
        <?php $ac = Database::getInstance()->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE is_read=0")['c'] ?? 0;
              if ($ac > 0): ?><span class="nav-badge"><?= $ac ?></span><?php endif; ?>
      </a>

      <div class="nav-group-label">Intelligence</div>
      <a href="<?= url('admin/ai-insights') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/ai-insights')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-brain"></i></span> AI Insights
      </a>
      <a href="<?= url('admin/forecast') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/forecast')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Revenue Forecast
      </a>

      <div class="nav-group-label">Registry</div>
      <a href="<?= url('admin/users') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/users')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-users"></i></span> Users
        <?php $po = Database::getInstance()->fetchOne("SELECT COUNT(*) as c FROM users WHERE role='operator' AND status='pending'")['c'] ?? 0;
              if ($po > 0): ?><span class="nav-badge"><?= $po ?></span><?php endif; ?>
      </a>
      <a href="<?= url('admin/vehicles') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/vehicles')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-car"></i></span> Vehicles &amp; RFID
      </a>
      <a href="<?= url('admin/blacklist') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/blacklist')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-ban"></i></span> Blacklist
      </a>

      <div class="nav-group-label">System</div>
      <a href="<?= url('admin/devices') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/devices')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-satellite-dish"></i></span> Devices
      </a>
      <?php endif; ?>

      <div class="nav-group-label">Gate Control</div>
      <a href="<?= url('admin/gate-override') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/gate-override')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-sliders"></i></span> Gate Override
      </a>

      <?php if (Security::isAdmin()): ?>
      <div class="nav-group-label">Maintenance</div>
      <a href="<?= url('admin/maintenance') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/maintenance')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-screwdriver-wrench"></i></span> Maintenance
      </a>
      <a href="<?= url('admin/logs') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/logs')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span> System Logs
      </a>
      <a href="<?= url('admin/settings') ?>" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'/admin/settings')!==false?'active':'' ?>">
        <span class="nav-icon"><i class="fas fa-gear"></i></span> Settings
      </a>
      <?php endif; ?>

      <div class="nav-group-label">Account</div>
      <a href="<?= url('logout') ?>" class="nav-item">
        <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span> Logout
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></div>
        <div class="user-role"><?= strtoupper($_SESSION['user_role'] ?? 'ADMIN') ?></div>
      </div>
    </div>
  </aside>

  <main class="main-content">
    <header class="topbar">
      <button class="btn-icon" id="sidebarToggle" title="Toggle Menu">
        <i class="fas fa-bars"></i>
      </button>
      <div class="topbar-title">
        <?= htmlspecialchars($title ?? 'Dashboard') ?>
        <small><?= date('l, F j, Y') ?></small>
      </div>
      <div class="topbar-actions">
        <div class="live-indicator"><span class="live-dot"></span> LIVE</div>
        <a href="<?= url('admin/notifications') ?>" class="btn-icon" title="Alerts">
          <i class="fas fa-bell"></i>
          <?php $ac = Database::getInstance()->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE is_read=0")['c'] ?? 0;
                if ($ac > 0): ?><span class="topbar-badge"><?= $ac ?></span><?php endif; ?>
        </a>
        <button class="btn-icon" id="themeToggle" title="Toggle Theme">
          <i class="fas fa-circle-half-stroke"></i>
        </button>
        <a href="<?= url('logout') ?>" class="btn-icon" title="Logout">
          <i class="fas fa-right-from-bracket"></i>
        </a>
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
