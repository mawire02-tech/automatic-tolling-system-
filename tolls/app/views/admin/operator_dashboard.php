<?php $title = 'Operator Dashboard'; ?>

<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">
      <i class="fa-solid fa-gauge-high" style="color:var(--accent-cyan);margin-right:8px"></i>Operator Dashboard
    </h2>
    <p class="text-muted text-sm"><?= date('l, F j, Y') ?></p>
  </div>
</div>

<!-- Assigned Gate Banner -->
<?php if (!empty($assignedDevice) && !empty($assignedDevice['device_name'])): ?>
<div class="card mb-20" style="border-color:rgba(0,212,255,.3);border-left:4px solid var(--accent-cyan)">
  <div class="card-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;padding:16px">
    <div style="width:48px;height:48px;background:rgba(0,212,255,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <i class="fa-solid fa-satellite-dish" style="color:var(--accent-cyan);font-size:20px"></i>
    </div>
    <div style="flex:1">
      <div style="font-weight:700;font-size:15px"><?= htmlspecialchars($assignedDevice['device_name']) ?></div>
      <div class="text-xs text-muted">Your assigned gate</div>
    </div>
    <div class="d-flex gap-12 align-center flex-wrap">
      <div style="text-align:center">
        <div class="text-xs text-muted">Gate Status</div>
        <span class="badge badge-<?= $assignedDevice['dstatus']==='online'?'success':($assignedDevice['dstatus']==='maintenance'?'warning':'danger') ?>" style="font-size:12px">
          <?= strtoupper($assignedDevice['dstatus']??'OFFLINE') ?>
        </span>
      </div>
      <div style="text-align:center">
        <div class="text-xs text-muted">Barrier</div>
        <span class="badge badge-<?= ($assignedDevice['barrier_status']??'closed')==='open'?'success':'info' ?>" style="font-size:12px">
          <?= strtoupper($assignedDevice['barrier_status']??'CLOSED') ?>
        </span>
      </div>
    </div>
    <a href="<?= url('admin/gate-override') ?>" class="btn btn-primary">
      <i class="fa-solid fa-sliders"></i> Control Gate
    </a>
  </div>
</div>
<?php else: ?>
<div class="alert alert-warning mb-20">
  <i class="fa-solid fa-triangle-exclamation"></i>
  <strong>No gate assigned.</strong> Contact your administrator to be assigned to a gate.
</div>
<?php endif; ?>

<!-- Stats (no revenue) -->
<div class="stats-grid mb-24" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">
  <div class="stat-card" style="--accent-color:var(--accent-cyan);--icon-bg:rgba(0,212,255,.1)">
    <div class="stat-icon"><i class="fa-solid fa-right-to-bracket"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($stats['today_transactions']??0) ?></div>
      <div class="stat-label">Vehicles Today</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-red);--icon-bg:rgba(255,61,90,.1)">
    <div class="stat-icon"><i class="fa-solid fa-ban"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($stats['denied_today']??0) ?></div>
      <div class="stat-label">Denied Today</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-green);--icon-bg:rgba(0,255,157,.1)">
    <div class="stat-icon"><i class="fa-solid fa-satellite-dish"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($stats['active_devices']??0) ?></div>
      <div class="stat-label">Gates Online</div>
    </div>
  </div>
</div>

<!-- Recent transactions on assigned gate -->
<div class="card mb-20">
  <div class="card-header">
    <i class="fa-solid fa-clock-rotate-left" style="color:var(--accent-cyan)"></i>
    <span class="card-title">Recent Transactions <?= !empty($assignedDevice['device_name'])?'— '.htmlspecialchars($assignedDevice['device_name']):'' ?></span>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Time</th><th>Plate</th><th>Vehicle</th><th>Status</th><th>Reason</th></tr>
      </thead>
      <tbody>
        <?php if (empty($recentTx)): ?>
        <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted)">No transactions yet today</td></tr>
        <?php else: foreach ($recentTx as $tx):
          $s=$tx['status']; $bc=$s==='success'?'success':($s==='denied'?'danger':'warning');
        ?>
        <tr>
          <td class="mono text-xs text-muted"><?= date('H:i:s', strtotime($tx['processed_at'])) ?></td>
          <td class="mono" style="font-weight:700"><?= htmlspecialchars($tx['plate_number']??'—') ?></td>
          <td><span class="badge badge-info" style="font-size:9px"><?= strtoupper($tx['vehicle_type']??'—') ?></span></td>
          <td><span class="badge badge-<?= $bc ?>"><?= strtoupper($s) ?></span></td>
          <td class="text-xs text-muted"><?= htmlspecialchars($tx['deny_reason']??'') ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Hourly traffic (only if assigned gate) -->
<?php if (!empty($hourlyTraffic)): ?>
<div class="card">
  <div class="card-header">
    <i class="fa-solid fa-chart-bar" style="color:var(--accent-purple)"></i>
    <span class="card-title">Hourly Traffic Today</span>
  </div>
  <div class="card-body"><div style="position:relative;height:200px"><canvas id="hourlyChart"></canvas></div></div>
</div>
<?php endif; ?>

<?php
$jHL = json_encode(array_keys($hourlyTraffic ?? array()));
$jHV = json_encode(array_values($hourlyTraffic ?? array()));
$pageScript = "
document.addEventListener('DOMContentLoaded', function() {
  var el = document.getElementById('hourlyChart');
  if (!el) return;
  new Chart(el, {
    type: 'bar',
    data: {
      labels: " . $jHL . ",
      datasets: [{ label: 'Vehicles', data: " . $jHV . ", backgroundColor: 'rgba(0,212,255,0.6)', borderRadius: 3 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false } },
        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { stepSize: 1 } }
      }
    }
  });
});
";
?>
