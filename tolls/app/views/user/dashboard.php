<?php $title = 'My Dashboard'; ?>

<?php if (!empty($userAlerts)): ?>
<div class="card mb-20" style="border-color:rgba(255,179,0,.3)">
  <div class="card-header" style="background:rgba(255,179,0,.06)">
    <i class="fa-solid fa-bell-ring" style="color:var(--accent-amber)"></i>
    <span class="card-title">Alerts from Admin</span>
    <span class="badge badge-warning"><?= count($userAlerts) ?> unread</span>
    <button class="btn btn-ghost btn-xs" onclick="markAllAlerts()" style="margin-left:auto">
      <i class="fa-solid fa-check-double"></i> Mark all read
    </button>
  </div>
  <div style="padding:0">
    <?php foreach ($userAlerts as $al):
      $ic = array('info'=>'fa-circle-info','warning'=>'fa-triangle-exclamation','success'=>'fa-circle-check','danger'=>'fa-circle-exclamation');
      $col= array('info'=>'var(--accent-cyan)','warning'=>'var(--accent-amber)','success'=>'var(--accent-green)','danger'=>'var(--accent-red)');
      $t  = $al['type']??'info';
    ?>
    <div id="alert-<?= $al['id'] ?>" class="d-flex align-center gap-12" style="padding:12px 16px;border-bottom:1px solid var(--border);border-left:3px solid <?= $col[$t]??'var(--accent-cyan)' ?>">
      <i class="fa-solid <?= $ic[$t]??'fa-circle-info' ?>" style="color:<?= $col[$t]??'var(--accent-cyan)' ?>;font-size:16px;flex-shrink:0"></i>
      <div style="flex:1">
        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($al['subject']) ?></div>
        <div class="text-xs text-muted"><?= htmlspecialchars($al['message']) ?></div>
        <div class="text-xs text-muted" style="margin-top:3px"><i class="fa-solid fa-clock"></i> <?= date('M d Y H:i', strtotime($al['created_at'])) ?></div>
      </div>
      <button class="btn btn-ghost btn-xs" onclick="markAlert(<?= $al['id'] ?>)" title="Mark as read">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($lowBalance): ?>
<div class="alert alert-warning" style="margin-bottom:16px">
  <i class="fa-solid fa-triangle-exclamation"></i> <strong>Low Balance Alert:</strong> Your wallet balance of
  <strong><?= Security::currency() . number_format($user['wallet_balance'], 2) ?></strong>
  is below the recommended minimum of <?= Security::currency() . number_format($threshold, 2) ?>.
  <a href="<?= url('user/wallet') ?>" style="color:var(--accent-amber);font-weight:600">Top up now &rarr;</a>
</div>
<?php endif; ?>

<!-- STAT CARDS -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
  <div class="stat-card" style="--accent-color:var(--accent-cyan);--icon-bg:rgba(0,212,255,0.1)">
    <div class="stat-icon"><i class="fa-solid fa-wallet"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= Security::currency() . number_format($user['wallet_balance'], 2) ?></div>
      <div class="stat-label">Wallet Balance</div>
      <div class="stat-delta"><a href="<?= url('user/wallet') ?>" style="color:var(--accent-cyan)">Top up &rarr;</a></div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-amber);--icon-bg:rgba(255,179,0,0.1)">
    <div class="stat-icon">$</div>
    <div class="stat-body">
      <div class="stat-value"><?= Security::currency() . number_format($monthRevenue, 2) ?></div>
      <div class="stat-label">Spent This Month</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-green);--icon-bg:rgba(0,255,157,0.1)">
    <div class="stat-icon"><i class="fa-solid fa-arrows-rotate"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($totalTrips) ?></div>
      <div class="stat-label">Total Trips</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-purple);--icon-bg:rgba(155,89,255,0.1)">
    <div class="stat-icon"><i class="fa-solid fa-car"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= count($vehicles) ?></div>
      <div class="stat-label">Registered Vehicles</div>
      <div class="stat-delta"><a href="<?= url('user/vehicles') ?>" style="color:var(--accent-purple)">Manage &rarr;</a></div>
    </div>
  </div>
</div>

<div class="grid-2 mb-24">
  <!-- Weekly Spending Chart -->
  <div class="card">
    <div class="card-header">
      <span style="color:var(--accent-cyan)"><i class="fa-solid fa-chart-bar"></i></span>
      <span class="card-title">Weekly Spending</span>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:200px">
        <canvas id="spendChart"></canvas>
      </div>
    </div>
  </div>

  <!-- My Vehicles -->
  <div class="card">
    <div class="card-header">
      <span style="color:var(--accent-amber)"><i class="fa-solid fa-car"></i></span>
      <span class="card-title">My Vehicles</span>
      <a href="<?= url('user/vehicles') ?>" class="btn btn-ghost btn-sm">Manage &rarr;</a>
    </div>
    <div class="card-body" style="padding:12px">
      <?php if (empty($vehicles)): ?>
      <div style="text-align:center;padding:30px;color:var(--text-muted)">
        <div style="font-size:32px;margin-bottom:8px"><i class="fa-solid fa-car"></i></div>
        <p>No vehicles registered.</p>
        <a href="<?= url('user/vehicles') ?>" class="btn btn-outline btn-sm" style="margin-top:8px">Register Vehicle</a>
      </div>
      <?php else: ?>
      <?php foreach ($vehicles as $v): ?>
      <div style="background:var(--bg-panel);border-radius:var(--radius);padding:12px;margin-bottom:8px;border:1px solid var(--border)">
        <div class="d-flex align-center justify-between">
          <div>
            <div style="font-family:var(--font-mono);font-size:16px;font-weight:700;color:var(--text-primary)">
              <?= htmlspecialchars($v['plate_number']) ?>
            </div>
            <div class="text-xs text-muted"><?= htmlspecialchars($v['make'].' '.$v['model'].' '.$v['year']) ?></div>
          </div>
          <div style="text-align:right">
            <span class="badge badge-info"><?= strtoupper($v['vehicle_type']) ?></span>
            <?php if ($v['card_uid']): ?>
            <div class="text-xs text-muted mono" style="margin-top:3px">RFID: <?= htmlspecialchars($v['card_uid']) ?></div>
            <?php else: ?>
            <div class="text-xs" style="color:var(--accent-amber);margin-top:3px">No RFID card</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="grid-2">
  <!-- Recent Transactions -->
  <div class="card">
    <div class="card-header">
      <span style="color:var(--accent-green)"><i class="fa-solid fa-credit-card"></i></span>
      <span class="card-title">Recent Transactions</span>
      <a href="<?= url('user/transactions') ?>" class="btn btn-ghost btn-sm">All &rarr;</a>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead><tr><th>Plate</th><th>Amount</th><th>Booth</th><th>Status</th><th>Time</th></tr></thead>
        <tbody>
          <?php foreach ($recentTx as $tx): ?>
          <tr>
            <td class="mono"><?= htmlspecialchars($tx['plate_number'] ?? '—') ?></td>
            <td class="text-green"><?= Security::currency() . number_format($tx['toll_amount'], 2) ?></td>
            <td class="text-muted text-xs"><?= htmlspecialchars($tx['device_name'] ?? '—') ?></td>
            <td><span class="badge badge-<?= $tx['status']==='success'?'success':($tx['status']==='denied'?'danger':'warning') ?>"><?= strtoupper($tx['status']) ?></span></td>
            <td class="text-muted text-xs"><?= date('M d H:i', strtotime($tx['processed_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recentTx)): ?>
          <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-muted)">No transactions yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pending Top-Ups -->
  <div class="card">
    <div class="card-header">
      <span style="color:var(--accent-amber)">⏳</span>
      <span class="card-title">Top-Up Status</span>
      <a href="<?= url('user/wallet') ?>" class="btn btn-ghost btn-sm">Wallet &rarr;</a>
    </div>
    <div class="card-body">
      <?php if (empty($pendingTopups)): ?>
      <div style="text-align:center;padding:20px;color:var(--text-muted)">
        <p>No recent top-up requests.</p>
        <a href="<?= url('user/wallet') ?>" class="btn btn-primary btn-sm" style="margin-top:12px">Request Top-Up</a>
      </div>
      <?php else: ?>
      <?php foreach ($pendingTopups as $t): ?>
      <div style="background:var(--bg-panel);border-radius:var(--radius);padding:12px;margin-bottom:8px;border:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <div>
          <div class="mono" style="color:var(--accent-green);font-weight:700"><?= Security::currency() . number_format($t['amount'], 2) ?></div>
          <div class="text-xs text-muted"><?= ucfirst(str_replace('_',' ',$t['payment_method'])) ?> · <?= date('M d Y', strtotime($t['requested_at'])) ?></div>
          <div class="text-xs mono text-muted"><?= htmlspecialchars($t['request_ref']) ?></div>
        </div>
        <span class="badge badge-<?= $t['status']==='approved'?'success':($t['status']==='rejected'?'danger':'warning') ?>">
          <?= strtoupper($t['status']) ?>
        </span>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$spendLabels = [];
$spendValues = [];
$from = strtotime('-6 days');

for ($i = 0; $i <= 6; $i++) {
    $d = date('Y-m-d', $from + $i * 86400);
    $spendLabels[] = date('M d', $from + $i * 86400);

    $found = array_filter($weeklySpend, function($s) use ($d) {
        return $s['date'] === $d;
    });

    $spendValues[] = $found ? (float)array_values($found)[0]['amount'] : 0;
}

$pageScript = "
var ALT_URL  = '" . addslashes(url('user/alerts/mark')) . "';
var ALT_CSRF = '" . addslashes(CSRF_TOKEN_NAME) . "';
var ALT_CV   = '" . addslashes(Security::csrfToken()) . "';

function markAlert(id) {
  var fd = new FormData();
  fd.append('id', id);
  fd.append(ALT_CSRF, ALT_CV);

  safeFetch(
    ALT_URL,
    {
      method:'POST',
      body:fd
    },
    function() {
      var el = document.getElementById('alert-' + id);

      if (el) {
        el.style.transition = 'opacity .3s';
        el.style.opacity = '0';

        setTimeout(function() {
          el.remove();
        }, 300);
      }
    },
    function(){}
  );
}

function markAllAlerts() {
  var fd = new FormData();

  fd.append('id', 0);
  fd.append(ALT_CSRF, ALT_CV);

  safeFetch(
    ALT_URL,
    {
      method:'POST',
      body:fd
    },
    function() {
      var box = document.querySelector(\"[style*='bell-ring']\");

      if (box) {
        box.closest('.card').remove();
      }
    },
    function(){}
  );
}

Chart.defaults.color = getComputedStyle(document.documentElement)
  .getPropertyValue('--text-muted')
  .trim();

new Chart(document.getElementById('spendChart'), {
  type: 'bar',

  data: {
    labels: " . json_encode($spendLabels) . ",

    datasets: [{
      label: 'Spent (' + (
        document.querySelector('meta[name=\"currency\"]')
          ? document.querySelector('meta[name=\"currency\"]').content
          : '$'
      ) + ')',

      data: " . json_encode($spendValues) . ",

      backgroundColor: 'rgba(0,212,255,0.6)',
      borderRadius: 4
    }]
  },

  options: {
    responsive: true,
    maintainAspectRatio: false,

    plugins: {
      legend: {
        display: false
      }
    },

    scales: {
      y: {
        grid: {
          color: 'rgba(255,255,255,0.04)'
        },

        ticks: {
          callback: function(v) {
            return window._cur + v;
          }
        }
      },

      x: {
        grid: {
          display: false
        }
      }
    }
  }
});
";
?>