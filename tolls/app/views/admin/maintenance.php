<?php $title = 'System Maintenance'; ?>

<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">
      <i class="fa-solid fa-screwdriver-wrench" style="color:var(--accent-amber);margin-right:8px"></i>System Maintenance
    </h2>
    <p class="text-muted text-sm">Database cleanup, diagnostics, and system health tools</p>
  </div>
  <span class="badge badge-warning" style="padding:6px 12px">
    <i class="fa-solid fa-triangle-exclamation"></i> Admin Only
  </span>
</div>

<!-- System Info Cards -->
<div class="stats-grid mb-24" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr))">
  <div class="stat-card" style="--accent-color:var(--accent-cyan);--icon-bg:rgba(0,212,255,.1)">
    <div class="stat-icon"><i class="fa-solid fa-database"></i></div>
    <div class="stat-body">
      <div class="stat-value" style="font-size:18px"><?= $stats['db_size_mb'] ?> MB</div>
      <div class="stat-label">Database Size</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-green);--icon-bg:rgba(0,255,157,.1)">
    <div class="stat-icon"><i class="fa-solid fa-code"></i></div>
    <div class="stat-body">
      <div class="stat-value" style="font-size:16px">PHP <?= $stats['php_version'] ?></div>
      <div class="stat-label">PHP Version</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-amber);--icon-bg:rgba(255,179,0,.1)">
    <div class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($stats['total_logs']) ?></div>
      <div class="stat-label">System Logs</div>
      <div class="stat-delta"><?= $stats['old_logs_30d'] ?> older than 30 days</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-red);--icon-bg:rgba(255,61,90,.1)">
    <div class="stat-icon"><i class="fa-solid fa-ban"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($stats['denied_tx']) ?></div>
      <div class="stat-label">Denied Transactions</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-purple);--icon-bg:rgba(155,89,255,.1)">
    <div class="stat-icon"><i class="fa-solid fa-bell"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($stats['total_notifications']) ?></div>
      <div class="stat-label">Notifications</div>
      <div class="stat-delta"><?= $stats['old_notifs_30d'] ?> older than 30 days</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-cyan);--icon-bg:rgba(0,212,255,.08)">
    <div class="stat-icon"><i class="fa-solid fa-credit-card"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($stats['total_transactions']) ?></div>
      <div class="stat-label">Total Transactions</div>
    </div>
  </div>
</div>

<div class="grid-2 mb-20">

  <!-- CLEANUP TOOLS -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-broom" style="color:var(--accent-amber)"></i>
      <span class="card-title">Database Cleanup</span>
    </div>
    <div class="card-body" style="display:grid;gap:12px">

      <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:var(--radius);padding:14px">
        <div class="d-flex justify-between align-center mb-6">
          <div>
            <div style="font-weight:600;font-size:13px"><i class="fa-solid fa-clipboard-list" style="color:var(--accent-cyan);margin-right:6px"></i>Old System Logs</div>
            <div class="text-xs text-muted"><?= number_format($stats['old_logs_30d']) ?> logs older than 30 days</div>
          </div>
          <button class="btn btn-warning btn-sm" onclick="runAction('clear_old_logs','Clear logs older than 30 days?')">
            <i class="fa-solid fa-trash"></i> Clear
          </button>
        </div>
      </div>

      <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:var(--radius);padding:14px">
        <div class="d-flex justify-between align-center mb-6">
          <div>
            <div style="font-weight:600;font-size:13px"><i class="fa-solid fa-bell" style="color:var(--accent-purple);margin-right:6px"></i>Read Notifications</div>
            <div class="text-xs text-muted"><?= number_format($stats['old_notifs_30d']) ?> read notifications older than 30 days</div>
          </div>
          <button class="btn btn-warning btn-sm" onclick="runAction('clear_old_notifications','Clear read notifications older than 30 days?')">
            <i class="fa-solid fa-trash"></i> Clear
          </button>
        </div>
      </div>

      <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:var(--radius);padding:14px">
        <div class="d-flex justify-between align-center">
          <div>
            <div style="font-weight:600;font-size:13px"><i class="fa-solid fa-ban" style="color:var(--accent-red);margin-right:6px"></i>Old Denied Transactions</div>
            <div class="text-xs text-muted"><?= number_format($stats['denied_tx']) ?> denied transactions (clears >90 days old)</div>
          </div>
          <button class="btn btn-warning btn-sm" onclick="runAction('clear_denied_tx','Clear denied transactions older than 90 days?')">
            <i class="fa-solid fa-trash"></i> Clear
          </button>
        </div>
      </div>

      <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:var(--radius);padding:14px">
        <div class="d-flex justify-between align-center">
          <div>
            <div style="font-weight:600;font-size:13px"><i class="fa-solid fa-sliders" style="color:var(--accent-green);margin-right:6px"></i>Gate Command Queue</div>
            <div class="text-xs text-muted">Clear executed/expired commands older than 7 days</div>
          </div>
          <button class="btn btn-warning btn-sm" onclick="runAction('clear_expired_commands','Clear old gate commands?')">
            <i class="fa-solid fa-trash"></i> Clear
          </button>
        </div>
      </div>

    </div>
  </div>

  <!-- DIAGNOSTICS -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-stethoscope" style="color:var(--accent-green)"></i>
      <span class="card-title">Diagnostics</span>
    </div>
    <div class="card-body" style="display:grid;gap:12px">

      <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:var(--radius);padding:14px">
        <div class="d-flex justify-between align-center">
          <div>
            <div style="font-weight:600;font-size:13px"><i class="fa-solid fa-database" style="color:var(--accent-cyan);margin-right:6px"></i>Database Ping</div>
            <div class="text-xs text-muted">Test database connection speed</div>
          </div>
          <button class="btn btn-outline btn-sm" onclick="runAction('test_db',null)">
            <i class="fa-solid fa-play"></i> Test
          </button>
        </div>
      </div>

      <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:var(--radius);padding:14px">
        <div class="d-flex justify-between align-center">
          <div>
            <div style="font-weight:600;font-size:13px"><i class="fa-solid fa-gauge" style="color:var(--accent-amber);margin-right:6px"></i>Reset Device Stats</div>
            <div class="text-xs text-muted">Reset all gate transaction/revenue counters to zero</div>
          </div>
          <button class="btn btn-danger btn-sm" onclick="runAction('reset_device_stats','This will reset ALL gate revenue and transaction counters to zero. Are you sure?')">
            <i class="fa-solid fa-rotate-left"></i> Reset
          </button>
        </div>
      </div>

      <!-- System info -->
      <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:var(--radius);padding:14px">
        <div class="text-xs text-muted mb-8" style="letter-spacing:.5px">ENVIRONMENT</div>
        <div style="display:grid;gap:6px;font-size:12px">
          <div class="d-flex justify-between">
            <span class="text-muted">PHP Version</span>
            <span class="mono text-cyan"><?= PHP_VERSION ?></span>
          </div>
          <div class="d-flex justify-between">
            <span class="text-muted">Server</span>
            <span class="mono" style="font-size:10px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($stats['server_software']) ?></span>
          </div>
          <div class="d-flex justify-between">
            <span class="text-muted">Memory Limit</span>
            <span class="mono text-cyan"><?= ini_get('memory_limit') ?></span>
          </div>
          <div class="d-flex justify-between">
            <span class="text-muted">Max Execution</span>
            <span class="mono text-cyan"><?= ini_get('max_execution_time') ?>s</span>
          </div>
          <div class="d-flex justify-between">
            <span class="text-muted">Database Size</span>
            <span class="mono text-green"><?= $stats['db_size_mb'] ?> MB</span>
          </div>
          <?php if ($stats['uptime'] !== 'N/A on Windows'): ?>
          <div class="d-flex justify-between">
            <span class="text-muted">Server Uptime</span>
            <span class="mono text-xs"><?= htmlspecialchars($stats['uptime']) ?></span>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Table Sizes -->
<?php if (!empty($tableSizes)): ?>
<div class="card mb-20">
  <div class="card-header">
    <i class="fa-solid fa-table" style="color:var(--accent-cyan)"></i>
    <span class="card-title">Database Table Sizes</span>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Table</th><th>Rows (est.)</th><th>Size</th><th>Visual</th></tr></thead>
      <tbody>
        <?php
        $maxMb = max(max(array_column($tableSizes,'size_mb')), 0.01);
        foreach ($tableSizes as $t):
        ?>
        <tr>
          <td class="mono text-xs"><?= htmlspecialchars($t['table_name']) ?></td>
          <td class="mono text-xs"><?= number_format($t['table_rows']) ?></td>
          <td class="mono text-xs text-cyan"><?= $t['size_mb'] ?> MB</td>
          <td style="width:200px">
            <div style="background:var(--bg-panel);border-radius:3px;height:6px">
              <div style="width:<?= min(round($t['size_mb']/$maxMb*100),100) ?>%;height:100%;background:var(--accent-cyan);border-radius:3px"></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Recent Error Logs -->
<?php if (!empty($errorLogs)): ?>
<div class="card">
  <div class="card-header">
    <i class="fa-solid fa-circle-exclamation" style="color:var(--accent-red)"></i>
    <span class="card-title">Recent Errors &amp; Critical Logs</span>
    <span class="badge badge-danger"><?= count($errorLogs) ?></span>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Time</th><th>Severity</th><th>Action</th><th>Description</th></tr></thead>
      <tbody>
        <?php foreach ($errorLogs as $l): ?>
        <tr>
          <td class="mono text-xs text-muted"><?= date('M d H:i', strtotime($l['logged_at'])) ?></td>
          <td><span class="badge badge-<?= $l['severity']==='critical'?'danger':'warning' ?>"><?= strtoupper($l['severity']) ?></span></td>
          <td class="mono text-xs text-cyan"><?= htmlspecialchars($l['action']??'') ?></td>
          <td class="text-xs text-muted" style="max-width:350px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($l['description']??'') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="card-body" style="text-align:center;padding:40px;color:var(--text-muted)">
    <i class="fa-solid fa-circle-check fa-2x" style="color:var(--accent-green);display:block;margin-bottom:10px"></i>
    No errors or critical events in system logs.
  </div>
</div>
<?php endif; ?>

<?php
$actionUrl = url('admin/maintenance/run');
$csrfName  = CSRF_TOKEN_NAME;
$csrfVal   = Security::csrfToken();
$pageScript = "
var MT_URL  = '" . addslashes($actionUrl) . "';
var MT_CSRF = '" . addslashes($csrfName)  . "';
var MT_CV   = '" . addslashes($csrfVal)   . "';

function runAction(action, confirmMsg) {
  if (confirmMsg && !confirm(confirmMsg)) return;
  var fd = new FormData();
  fd.append('action', action);
  fd.append(MT_CSRF, MT_CV);
  safeFetch(MT_URL, { method: 'POST', body: fd },
    function(d) { showToast(d.message, 'success'); setTimeout(function(){ location.reload(); }, 1500); },
    function(msg){ showToast(msg, 'error'); }
  );
}
";
?>
