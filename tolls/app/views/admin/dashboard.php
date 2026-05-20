<?php $title = 'Dashboard'; ?>

<?php if (!empty($isOp)): ?>
<div class="alert alert-info mb-20" style="display:flex;align-items:center;gap:12px">
  <i class="fa-solid fa-satellite-dish fa-lg" style="color:var(--accent-cyan)"></i>
  <div>
    <?php if (!empty($assignedDevice) && !empty($assignedDevice['device_name'])): ?>
    <strong>Your assigned gate:</strong> <?= htmlspecialchars($assignedDevice['device_name']) ?>
    — Status: <span class="badge badge-<?= $assignedDevice['dstatus']==='online'?'success':'danger' ?>"><?= strtoupper($assignedDevice['dstatus']??'UNKNOWN') ?></span>
    &nbsp; Barrier: <span class="badge badge-<?= ($assignedDevice['barrier_status']??'closed')==='open'?'success':'info' ?>"><?= strtoupper($assignedDevice['barrier_status']??'CLOSED') ?></span>
    <?php else: ?>
    <strong>No gate assigned.</strong> Contact admin to assign you to a gate.
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php
// Load operator list and all gates for gate assignment (admin only)
if (Security::isAdmin()) {
    $operatorList = Database::getInstance()->fetchAll(
        "SELECT u.id, u.full_name, u.username, u.status,
                u.assigned_device_id, d.device_name, d.device_code, d.status as gate_status
         FROM users u
         LEFT JOIN devices d ON u.assigned_device_id = d.id
         WHERE u.role = 'operator'
         ORDER BY u.status ASC, u.full_name ASC"
    );
    $allGates = Database::getInstance()->fetchAll(
        "SELECT id, device_name, device_code, status FROM devices ORDER BY device_name ASC"
    );
} else {
    $operatorList = array();
    $allGates     = array();
}
?>



<?php
$revLabels = array_map(function($r){ return date('M d', strtotime($r['date'])); }, $revenueChart);
$revValues = array_map(function($r){ return (float)$r['revenue']; }, $revenueChart);
$revCounts = array_map(function($r){ return (int)$r['success_count']; }, $revenueChart);

$vtypes  = ['motorcycle','car','suv','truck','bus'];
$vtColors= ['#00d4ff','#00ff9d','#ffb300','#ff3d5a','#9b59ff'];
$vtypeData=[];
foreach ($vtypes as $vt) {
  $f = array_filter($vehicleStats, function($v) use ($vt){ return $v['vehicle_type'] === $vt; });
  $vtypeData[] = count($f) ? (int)array_values($f)[0]['count'] : 0;
}
$hourAxisLabels = array_map(function($h){ return str_pad($h,2,'0',STR_PAD_LEFT).':00'; }, range(0,23));
$hourValues = array_fill(0,24,0);
foreach ($hourlyTraffic as $h) $hourValues[(int)$h['hour']] = (int)$h['count'];
?>

<!-- STAT CARDS -->
<div class="stats-grid">
  <div class="stat-card" style="--accent-color:var(--accent-green);--icon-bg:rgba(0,255,157,.1)">
    <div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="stat-body">
      <div class="stat-value" data-stat="today_revenue" data-format="currency"><?= Security::currency() . number_format($stats['today_revenue'], 2) ?></div>
      <div class="stat-label">Today's Revenue</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-cyan);--icon-bg:rgba(0,212,255,.1)">
    <div class="stat-icon"><i class="fa-solid fa-arrows-rotate"></i></div>
    <div class="stat-body">
      <div class="stat-value" data-stat="today_count"><?= number_format($stats['today_transactions']) ?></div>
      <div class="stat-label">Transactions Today</div>
      <div class="stat-delta tm"><?= $stats['denied_today'] ?> denied</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-purple);--icon-bg:rgba(155,89,255,.1)">
    <div class="stat-icon"><i class="fa-solid fa-user"></i></div>
    <div class="stat-body">
      <div class="stat-value" data-stat="active_users"><?= number_format($stats['total_users']) ?></div>
      <div class="stat-label">Registered Users</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-amber);--icon-bg:rgba(255,179,0,.1)">
    <div class="stat-icon"><i class="fa-solid fa-car"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($stats['total_vehicles']) ?></div>
      <div class="stat-label">Total Vehicles</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-cyan);--icon-bg:rgba(0,212,255,.1)">
    <div class="stat-icon"><i class="fa-solid fa-satellite-dish"></i></div>
    <div class="stat-body">
      <div class="stat-value" data-stat="online_devices"><?= $stats['active_devices'] ?></div>
      <div class="stat-label">Online Devices</div>
      <div class="stat-delta">of <?= count($allDevices) ?> total</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-amber);--icon-bg:rgba(255,179,0,.1)">
    <div class="stat-icon">⏳</div>
    <div class="stat-body">
      <div class="stat-value" data-stat="pending_topups"><?= $stats['pending_topups'] ?></div>
      <div class="stat-label">Pending Top-Ups</div>
      <?php if ($stats['pending_topups'] > 0): ?>
      <div class="stat-delta"><a href="<?= url('admin/topups') ?>" style="color:var(--accent-amber)">Review &rarr;</a></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-green);--icon-bg:rgba(0,255,157,.06)">
    <div class="stat-icon"><i class="fa-solid fa-gem"></i></div>
    <div class="stat-body">
      <div class="stat-value" style="font-size:19px"><?= Security::currency() . number_format($stats['total_revenue'], 2) ?></div>
      <div class="stat-label">Total Revenue (All Time)</div>
    </div>
  </div>
</div>

<!-- ACTIVE GATES STRIP -->
<?php if (!empty($onlineDevices)): ?>
<div class="card mb-24" style="border-color:rgba(0,255,157,.25)">
  <div class="card-header" style="background:rgba(0,255,157,.04)">
    <span style="color:var(--accent-green)"><i class="fa-solid fa-satellite-dish"></i></span>
    <span class="card-title">Active Gates Now</span>
    <span class="badge badge-success"><?= count($onlineDevices) ?> Online</span>
    <a href="<?= url('admin/gate-override') ?>" class="btn btn-ghost btn-sm" style="margin-left:auto">Override &rarr;</a>
  </div>
  <div style="display:flex;gap:0;flex-wrap:wrap">
    <?php foreach ($onlineDevices as $i => $d): ?>
    <div style="flex:1;min-width:200px;padding:14px 18px;<?= $i > 0 ? 'border-left:1px solid var(--border)' : '' ?>">
      <div class="d-flex align-center gap-8 mb-8">
        <span class="status-dot online" data-device-dot="<?= $d['id'] ?>"></span>
        <span style="font-weight:600;color:var(--text-primary);font-size:13px"><?= htmlspecialchars($d['device_name']) ?></span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
        <div>
          <div style="font-size:9px;color:var(--text-muted);font-family:var(--font-mono);margin-bottom:2px">BARRIER</div>
          <span class="badge badge-<?= $d['barrier_status']==='open'?'success':'info' ?>">
            <?= strtoupper($d['barrier_status'] ?? 'UNK') ?>
          </span>
        </div>
        <div>
          <div style="font-size:9px;color:var(--text-muted);font-family:var(--font-mono);margin-bottom:2px">IP</div>
          <span class="mono text-xs"><?= htmlspecialchars($d['ip_address'] ?: 'N/A') ?></span>
        </div>
        <div>
          <div style="font-size:9px;color:var(--text-muted);font-family:var(--font-mono);margin-bottom:2px">LAST HB</div>
          <span class="mono text-xs"><?= $d['last_heartbeat'] ? date('H:i:s', strtotime($d['last_heartbeat'])) : '—' ?></span>
        </div>
        <div>
          <div style="font-size:9px;color:var(--text-muted);font-family:var(--font-mono);margin-bottom:2px">FW</div>
          <span class="mono text-xs">v<?= htmlspecialchars($d['firmware_version']) ?></span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php else: ?>
<div class="alert alert-danger mb-24" style="display:flex;align-items:center;gap:10px">
  <span style="font-size:18px"><i class="fa-solid fa-power-off"></i></span>
  <div>
    <strong>No gates online.</strong> All devices are offline or unreachable.
    <a href="<?= url('admin/devices') ?>" style="color:var(--accent-red);margin-left:8px">Check devices &rarr;</a>
  </div>
</div>
<?php endif; ?>

<!-- CHARTS ROW -->
<div class="grid-2 mb-24">
  <div class="card">
    <div class="card-header">
      <span style="color:var(--accent-cyan)"><i class="fa-solid fa-chart-line"></i></span>
      <span class="card-title">Revenue — Last 7 Days</span>
      <a href="<?= url('admin/reports') ?>" class="btn btn-ghost btn-sm">Full Reports &rarr;</a>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:220px"><canvas id="revenueChart"></canvas></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <span style="color:var(--accent-amber)"><i class="fa-solid fa-car"></i></span>
      <span class="card-title">Vehicle Types — Today</span>
    </div>
    <div class="card-body" style="display:flex;align-items:center;gap:20px">
      <div class="chart-container" style="height:200px;width:200px;flex-shrink:0"><canvas id="vtypeChart"></canvas></div>
      <div style="flex:1">
        <?php foreach ($vtypes as $i => $vt): ?>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
          <span style="width:10px;height:10px;border-radius:50%;background:<?= $vtColors[$i] ?>;display:inline-block;flex-shrink:0"></span>
          <span style="font-size:12px;color:var(--text-secondary);flex:1;text-transform:capitalize"><?= $vt ?></span>
          <span style="font-family:var(--font-mono);font-size:12px;color:var(--text-primary)"><?= $vtypeData[$i] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="card mb-24">
  <div class="card-header">
    <span style="color:var(--accent-purple)">⏱</span>
    <span class="card-title">Hourly Traffic — Today</span>
  </div>
  <div class="card-body">
    <div class="chart-container" style="height:160px"><canvas id="hourlyChart"></canvas></div>
  </div>
</div>

<div class="grid-2 mb-20">
  <!-- Recent Transactions -->
  <div class="card">
    <div class="card-header">
      <span style="color:var(--accent-cyan)"><i class="fa-solid fa-credit-card"></i></span>
      <span class="card-title">Recent Transactions</span>
      <a href="<?= url('admin/transactions') ?>" class="btn btn-ghost btn-sm">View all &rarr;</a>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead><tr><th>Plate</th><th>Amount</th><th>Booth</th><th>Status</th><th>Time</th></tr></thead>
        <tbody>
          <?php foreach ($recentTx as $tx): ?>
          <tr>
            <td><span class="mono"><?= htmlspecialchars($tx['plate_number'] ?? 'N/A') ?></span></td>
            <td><span class="text-green"><?= Security::currency() . number_format($tx['toll_amount'], 2) ?></span></td>
            <td class="text-muted text-sm"><?= htmlspecialchars($tx['device_name'] ?? '—') ?></td>
            <td><span class="badge badge-<?= $tx['status']==='success'?'success':($tx['status']==='denied'?'danger':'warning') ?>"><?= strtoupper($tx['status']) ?></span></td>
            <td class="text-muted text-sm"><?= date('H:i', strtotime($tx['processed_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- All Devices Status -->
  <div class="card">
    <div class="card-header">
      <span style="color:var(--accent-green)"><i class="fa-solid fa-satellite-dish"></i></span>
      <span class="card-title">All Devices</span>
      <a href="<?= url('admin/gate-override') ?>" class="btn btn-ghost btn-sm">Override &rarr;</a>
    </div>
    <div class="card-body" style="padding:12px">
      <?php foreach ($allDevices as $d): ?>
      <div class="device-card <?= $d['status'] ?>" style="margin-bottom:8px">
        <div class="d-flex align-center gap-8">
          <span class="status-dot <?= $d['status'] ?>" data-device-dot="<?= $d['id'] ?>"></span>
          <div class="flex-1">
            <div style="font-size:13px;font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($d['device_name']) ?></div>
            <div class="text-xs text-muted"><?= htmlspecialchars($d['location']) ?></div>
          </div>
          <div style="text-align:right">
            <span class="badge badge-<?= $d['status']==='online'?'success':($d['status']==='maintenance'?'warning':'danger') ?>"><?= strtoupper($d['status']) ?></span>
            <div class="text-xs text-muted" style="margin-top:3px"><?= $d['last_heartbeat'] ? date('H:i', strtotime($d['last_heartbeat'])) : 'Never' ?></div>
          </div>
        </div>
        <div style="display:flex;gap:16px;margin-top:8px;padding-top:8px;border-top:1px solid var(--border)">
          <div class="text-xs"><span class="text-muted">Tx: </span><span class="mono text-cyan"><?= number_format($d['total_transactions']) ?></span></div>
          <div class="text-xs"><span class="text-muted">Rev: </span><span class="mono text-green"><?= Security::currency() . number_format($d['total_revenue'], 2) ?></span></div>
          <div class="text-xs"><span class="text-muted">Barrier: </span><span class="mono"><?= strtoupper($d['barrier_status'] ?? 'UNK') ?></span></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ── Gate Assignment Panel (admin only) — bottom ──────────── -->
<?php if (Security::isAdmin() && !empty($operatorList)): ?>
<div class="card mt-24" id="gate-assignment-panel">
  <div class="card-header">
    <i class="fa-solid fa-user-gear" style="color:var(--accent-cyan)"></i>
    <span class="card-title">Operator Gate Assignments</span>
    <span class="text-muted text-xs"><?= count($operatorList) ?> operator(s)</span>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Operator</th><th>Status</th><th>Assigned Gate</th><th>Gate Status</th><th>Assign / Change</th></tr>
      </thead>
      <tbody>
        <?php foreach ($operatorList as $op): ?>
        <tr id="opgate-<?= $op['id'] ?>">
          <td>
            <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($op['full_name']) ?></div>
            <div class="text-xs text-muted">@<?= htmlspecialchars($op['username']) ?></div>
          </td>
          <td>
            <span class="badge badge-<?= $op['status']==='active'?'success':($op['status']==='pending'?'warning':'danger') ?>">
              <?= strtoupper($op['status']) ?>
            </span>
          </td>
          <td class="assigned-gate-cell">
            <?php if ($op['assigned_device_id']): ?>
            <span style="color:var(--accent-cyan);font-size:12px">
              <i class="fa-solid fa-satellite-dish"></i> <?= htmlspecialchars($op['device_name']??'—') ?>
            </span>
            <?php else: ?>
            <span class="text-muted text-xs"><i class="fa-solid fa-triangle-exclamation" style="color:var(--accent-amber)"></i> Not assigned</span>
            <?php endif; ?>
          </td>
          <td class="gate-status-cell">
            <?php if ($op['assigned_device_id']): ?>
            <span class="badge badge-<?= $op['gate_status']==='online'?'success':($op['gate_status']==='maintenance'?'warning':'danger') ?>">
              <?= strtoupper($op['gate_status']??'OFFLINE') ?>
            </span>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
         <td>
              <div class="d-flex align-items-center">
                
                <select id="gate-sel-<?= $op['id'] ?>"
                        class="form-control"
                        style="width:150px;font-size:11px;padding:4px 8px;margin-right:10px;">
                  <option value="">— No Gate —</option>

                  <?php foreach ($allGates as $g): ?>
                  <option value="<?= $g['id'] ?>"
                    <?= $op['assigned_device_id']==$g['id']?'selected':'' ?>>
                    <?= htmlspecialchars($g['device_name']) ?>
                    (<?= strtoupper($g['status']) ?>)
                  </option>
                  <?php endforeach; ?>
                </select>

                <button class="btn btn-primary btn-xs"
                        style="margin-right:8px;"
                        onclick="assignGate(<?= $op['id'] ?>)">
                  <i class="fa-solid fa-floppy-disk"></i> Save
                </button>

                <button class="btn btn-danger btn-xs"
                        onclick="removeGate(<?= $op['id'] ?>)"
                        title="Remove assignment">
                  <i class="fa-solid fa-xmark"></i>
                </button>

              </div>
            </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>


<?php
$curSym    = addslashes(Security::currency());
$csrfToken = Security::csrfToken();
$assignUrl = url('admin/assign-gate');
$jRL      = json_encode($revLabels);
$jRV      = json_encode($revValues);
$jRC      = json_encode($revCounts);
$jVT      = json_encode(array_map('ucfirst', $vtypes));
$jVD      = json_encode($vtypeData);
$jVC      = json_encode($vtColors);
$jHL      = json_encode($hourAxisLabels);
$jHV      = json_encode($hourValues);

$pageScript = "
document.addEventListener('DOMContentLoaded', function() {
  var gridC = 'rgba(255,255,255,0.04)';
  var curSym     = '" . $curSym . "';
  var CSRF       = '" . $csrfToken . "';
  var ASSIGN_URL = '" . $assignUrl . "';

  var revenueEl = document.getElementById('revenueChart');
  if (revenueEl) {
    new Chart(revenueEl, {
      type: 'line',
      data: {
        labels: " . $jRL . ",
        datasets: [{
          label: 'Revenue',
          data: " . $jRV . ",
          borderColor: '#00d4ff',
          backgroundColor: 'rgba(0,212,255,0.08)',
          fill: true, tension: 0.4,
          pointBackgroundColor: '#00d4ff', pointRadius: 4
        },{
          label: 'Transactions',
          data: " . $jRC . ",
          borderColor: '#00ff9d',
          backgroundColor: 'transparent',
          fill: false, tension: 0.4, yAxisID: 'y1',
          pointBackgroundColor: '#00ff9d', pointRadius: 3
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 10, font: { size: 11 } } } },
        scales: {
          y:  { grid: { color: gridC }, ticks: { callback: function(v) { return curSym + v; } } },
          y1: { position: 'right', grid: { display: false }, ticks: { callback: function(v) { return v + 'tx'; } } },
          x:  { grid: { color: gridC } }
        }
      }
    });
  }

  var vtypeEl = document.getElementById('vtypeChart');
  if (vtypeEl) {
    new Chart(vtypeEl, {
      type: 'doughnut',
      data: {
        labels: " . $jVT . ",
        datasets: [{ data: " . $jVD . ", backgroundColor: " . $jVC . ", borderWidth: 0 }]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, cutout: '65%' }
    });
  }

  var hourlyEl = document.getElementById('hourlyChart');
  if (hourlyEl) {
    new Chart(hourlyEl, {
      type: 'bar',
      data: {
        labels: " . $jHL . ",
        datasets: [{ label: 'Vehicles', data: " . $jHV . ", backgroundColor: 'rgba(0,212,255,0.6)', borderRadius: 3 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { grid: { display: false } }, y: { grid: { color: gridC }, ticks: { stepSize: 1 } } }
      }
    });
  }

  // ── Gate Assignment AJAX ─────────────────────────────────────
  function assignGate(opId) {
    var sel = document.getElementById('gate-sel-' + opId);
    var gateId = sel ? sel.value : '';
    if (!gateId) { alert('Please select a gate first.'); return; }
    var btn = sel.parentElement.querySelector('.btn-primary');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class=\'fa-solid fa-spinner fa-spin\'></i>'; }
    fetch(ASSIGN_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: 'operator_id=' + opId + '&device_id=' + gateId + '&csrf_token=' + encodeURIComponent(CSRF)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.success) {
        updateGateRow(opId, d.gate);
        showToast('Gate assigned successfully', 'success');
      } else {
        showToast(d.message || 'Failed to assign gate', 'danger');
      }
    })
    .catch(function(){ showToast('Request failed', 'danger'); })
    .finally(function(){
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class=\'fa-solid fa-floppy-disk\'></i> Save'; }
    });
  }

  function removeGate(opId) {
    if (!confirm('Remove gate assignment from this operator?')) return;
    var btn = document.querySelector('#opgate-' + opId + ' .btn-danger');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class=\'fa-solid fa-spinner fa-spin\'></i>'; }
    fetch(ASSIGN_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: 'operator_id=' + opId + '&device_id=&csrf_token=' + encodeURIComponent(CSRF)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.success) {
        updateGateRow(opId, null);
        showToast('Gate assignment removed', 'success');
      } else {
        showToast(d.message || 'Failed to remove gate', 'danger');
      }
    })
    .catch(function(){ showToast('Request failed', 'danger'); })
    .finally(function(){
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class=\'fa-solid fa-xmark\'></i>'; }
    });
  }

  function updateGateRow(opId, gate) {
    var row = document.getElementById('opgate-' + opId);
    if (!row) return;
    var gateCell = row.querySelector('.assigned-gate-cell');
    var statusCell = row.querySelector('.gate-status-cell');
    var sel = document.getElementById('gate-sel-' + opId);
    if (gate && gate.device_name) {
      if (gateCell) gateCell.innerHTML = '<span style=\'color:var(--accent-cyan);font-size:12px\'><i class=\'fa-solid fa-satellite-dish\'></i> ' + gate.device_name + '</span>';
      var badgeClass = gate.gate_status === 'online' ? 'success' : (gate.gate_status === 'maintenance' ? 'warning' : 'danger');
      if (statusCell) statusCell.innerHTML = '<span class=\'badge badge-' + badgeClass + '\'>' + (gate.gate_status || 'OFFLINE').toUpperCase() + '</span>';
      if (sel) sel.value = gate.device_id;
    } else {
      if (gateCell) gateCell.innerHTML = '<span class=\'text-muted text-xs\'><i class=\'fa-solid fa-triangle-exclamation\' style=\'color:var(--accent-amber)\'></i> Not assigned</span>';
      if (statusCell) statusCell.innerHTML = '<span class=\'text-muted\'>—</span>';
      if (sel) sel.value = '';
    }
  }

  function showToast(msg, type) {
    var t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;border-radius:8px;font-size:13px;font-weight:600;color:#fff;background:' + (type==='success'?'var(--accent-green, #00ff9d)':'var(--accent-red, #ff3d5a)') + ';color:#111;box-shadow:0 4px 16px rgba(0,0,0,.4);transition:opacity .4s';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function(){ t.style.opacity='0'; setTimeout(function(){ t.remove(); }, 400); }, 2800);
  }

  window.assignGate = assignGate;
  window.removeGate = removeGate;

}); // end DOMContentLoaded
";
?>
