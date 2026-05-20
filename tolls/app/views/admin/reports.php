<?php $title = 'Reports & Analytics'; ?>
<?php
// Pre-process data for charts
$dailyLabels  = array();
$dailyRevenue = array();
$dailySuccess = array();
$dailyDenied  = array();

foreach ($dailySummary as $row) {
    $dailyLabels[]  = date('M d', strtotime($row['date']));
    $dailyRevenue[] = (float)$row['revenue'];
    $dailySuccess[] = (int)$row['success_count'];
    $dailyDenied[]  = (int)$row['denied_count'];
}

$tagLabels  = array_column($tagBreakdown, 'label');
$tagCounts  = array_column($tagBreakdown, 'count');
$tagColors  = array('#00ff9d','#ff3d5a','#ffb300','#9b59ff','#00d4ff','#ff6b6b','#74b9ff');

$vclassLabels  = array_column($vehicleClass, 'vehicle_type');
$vclassRevenue = array_column($vehicleClass, 'revenue');
$vclassCount   = array_column($vehicleClass, 'success_count');

$gateLabels  = array_column($gatePerformance, 'device_name');
$gateSuccess = array_column($gatePerformance, 'success_count');
$gateDenied  = array_column($gatePerformance, 'denied_count');
?>

<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">Reports &amp; Analytics</h2>
    <p class="text-muted text-sm">
      Showing data for
      <strong style="color:var(--text-primary)"><?= date('M d Y', strtotime($dateFrom)) ?></strong>
      to
      <strong style="color:var(--text-primary)"><?= date('M d Y', strtotime($dateTo)) ?></strong>
      (<?= $days ?> day<?= $days != 1 ? 's' : '' ?>)
    </p>
  </div>
  <button class="btn btn-outline" onclick="window.print()">Print / PDF</button>
</div>

<!-- DATE FILTER -->
<div class="card mb-24">
  <div class="card-body" style="padding:14px">
    <form method="GET" action="<?= url('admin/reports') ?>" class="d-flex gap-8 align-center flex-wrap">
      <label class="form-label" style="margin:0;white-space:nowrap">Date Range:</label>
      <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>" style="width:145px">
      <input type="date" name="date_to"   class="form-control" value="<?= $dateTo ?>"   style="width:145px">
      <button type="submit" class="btn btn-primary">Apply</button>
      <a href="<?= url('admin/reports') ?>?date_from=<?= date('Y-m-d',strtotime('-6 days')) ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-ghost">7 Days</a>
      <a href="<?= url('admin/reports') ?>?date_from=<?= date('Y-m-d',strtotime('-29 days')) ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-ghost">30 Days</a>
      <a href="<?= url('admin/reports') ?>?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-ghost">This Month</a>
    </form>
  </div>
</div>

<!-- SUMMARY KPI CARDS -->
<div class="stats-grid mb-24">
  <div class="stat-card" style="--accent-color:var(--accent-green);--icon-bg:rgba(0,255,157,.1)">
    <div class="stat-icon">$</div>
    <div class="stat-body">
      <div class="stat-value">$<?= number_format($totals['total_revenue'],2) ?></div>
      <div class="stat-label">Total Revenue</div>
      <div class="stat-delta">avg $<?= number_format($avgDailyRevenue,2) ?>/day</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-cyan);--icon-bg:rgba(0,212,255,.1)">
    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($totals['total_success']) ?></div>
      <div class="stat-label">Successful Transactions</div>
      <div class="stat-delta">avg <?= $avgDailyTx ?>/day</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-amber);--icon-bg:rgba(255,179,0,.1)">
    <div class="stat-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($totals['total_scans']) ?></div>
      <div class="stat-label">Total RFID Scans</div>
      <div class="stat-delta"><?= $totals['total_denied'] ?> denied</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-purple);--icon-bg:rgba(155,89,255,.1)">
    <div class="stat-icon"><i class="fa-solid fa-chart-line"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= $successRate ?>%</div>
      <div class="stat-label">Success Rate</div>
      <div class="stat-delta <?= $successRate < 85 ? 'down' : '' ?>">
        <?= $successRate >= 90 ? 'Excellent' : ($successRate >= 75 ? 'Good' : 'Needs attention') ?>
      </div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-cyan);--icon-bg:rgba(0,212,255,.08)">
    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($totals['unique_users']) ?></div>
      <div class="stat-label">Unique Users</div>
    </div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-amber);--icon-bg:rgba(255,179,0,.08)">
    <div class="stat-icon"><i class="fa-solid fa-car"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($totals['unique_vehicles']) ?></div>
      <div class="stat-label">Unique Vehicles</div>
    </div>
  </div>
</div>

<!-- ROW 1: Revenue Trend + Daily Success Rate -->
<div class="grid-2 mb-24">
  <div class="card">
    <div class="card-header">
      <span style="color:var(--accent-cyan)"><i class="fa-solid fa-chart-line"></i></span>
      <span class="card-title">Daily Revenue Trend</span>
    </div>
    <div class="card-body">
      <div style="position:relative;height:240px">
        <canvas id="revenueChart"></canvas>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <span style="color:var(--accent-green)"><i class="fa-solid fa-circle-check"></i></span>
      <span class="card-title">Daily Success Rate</span>
    </div>
    <div class="card-body">
      <div style="position:relative;height:240px">
        <canvas id="successRateChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- ROW 2: Tag Doughnut + Vehicle Class -->
<div class="grid-2 mb-24">
  <div class="card">
    <div class="card-header">
      <span style="color:var(--accent-amber)"><i class="fa-solid fa-tag"></i></span>
      <span class="card-title">RFID Scan Results Breakdown</span>
    </div>
    <div class="card-body" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
      <div style="position:relative;height:220px;width:220px;flex-shrink:0">
        <canvas id="tagDoughnutChart"></canvas>
      </div>
      <div style="flex:1;min-width:150px" id="tagLegend">
        <?php foreach ($tagBreakdown as $i => $row): ?>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:9px">
          <span style="width:10px;height:10px;border-radius:50%;background:<?= $tagColors[$i % count($tagColors)] ?>;display:inline-block;flex-shrink:0"></span>
          <span style="font-size:12px;color:var(--text-secondary);flex:1"><?= htmlspecialchars($row['label']) ?></span>
          <span style="font-family:var(--font-mono);font-size:12px;font-weight:700"><?= number_format($row['count']) ?></span>
          <span style="font-size:10px;color:var(--text-muted)">
            (<?= $totals['total_scans'] > 0 ? round($row['count']/$totals['total_scans']*100,1) : 0 ?>%)
          </span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($tagBreakdown)): ?>
        <p class="text-muted text-sm" style="text-align:center;padding:20px">No scan data for this period</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <span style="color:var(--accent-purple)"><i class="fa-solid fa-car"></i></span>
      <span class="card-title">Vehicle Class Breakdown</span>
    </div>
    <div class="card-body">
      <div style="position:relative;height:220px">
        <canvas id="vehicleClassChart"></canvas>
      </div>
      <?php if (!empty($vehicleClass)): ?>
      <div style="margin-top:14px;display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px">
        <?php
          $vcColors = array('motorcycle'=>'var(--accent-cyan)','car'=>'var(--accent-green)','suv'=>'var(--accent-amber)','truck'=>'var(--accent-red)','bus'=>'var(--accent-purple)');
        ?>
        <?php foreach ($vehicleClass as $vc): ?>
        <div style="background:var(--bg-panel);border-radius:var(--radius);padding:10px;border:1px solid var(--border)">
          <div class="text-xs text-muted" style="text-transform:capitalize"><?= htmlspecialchars($vc['vehicle_type']) ?></div>
          <div style="font-family:var(--font-mono);font-size:14px;font-weight:700;color:<?= $vcColors[$vc['vehicle_type']] ?? 'var(--text-primary)' ?>">
            <?= number_format($vc['success_count']) ?>
          </div>
          <div class="text-xs text-muted">$<?= number_format($vc['revenue'],2) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ROW 3: Gate Performance -->
<div class="card mb-24">
  <div class="card-header">
    <span style="color:var(--accent-green)"><i class="fa-solid fa-satellite-dish"></i></span>
    <span class="card-title">Gate Performance Comparison</span>
  </div>
  <div class="card-body">
    <div style="position:relative;height:220px">
      <canvas id="gateChart"></canvas>
    </div>
  </div>
  <?php if (!empty($gatePerformance)): ?>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Gate</th><th>Status</th><th>Success</th><th>Denied</th><th>Success Rate</th><th>Revenue</th></tr></thead>
      <tbody>
        <?php foreach ($gatePerformance as $g):
          $tot  = $g['success_count'] + $g['denied_count'];
          $rate = $tot > 0 ? round($g['success_count']/$tot*100,1) : 0;
        ?>
        <tr>
          <td>
            <div style="font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($g['device_name']) ?></div>
            <div class="text-xs text-muted mono"><?= htmlspecialchars($g['device_code']) ?></div>
          </td>
          <td><span class="badge badge-<?= $g['status']==='online'?'success':($g['status']==='maintenance'?'warning':'danger') ?>"><?= strtoupper($g['status']) ?></span></td>
          <td class="text-green mono"><?= number_format($g['success_count']) ?></td>
          <td class="text-red mono"><?= number_format($g['denied_count']) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="flex:1;height:6px;background:var(--bg-panel);border-radius:3px;overflow:hidden">
                <div style="height:100%;width:<?= $rate ?>%;background:<?= $rate>=90?'var(--accent-green)':($rate>=70?'var(--accent-amber)':'var(--accent-red)') ?>;border-radius:3px"></div>
              </div>
              <span class="mono text-xs"><?= $rate ?>%</span>
            </div>
          </td>
          <td class="text-green mono">$<?= number_format($g['revenue'],2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Daily Summary Table -->
<div class="card">
  <div class="card-header">
    <span style="color:var(--accent-cyan)"><i class="fa-solid fa-calendar-days"></i></span>
    <span class="card-title">Daily Summary Table</span>
    <button class="btn btn-ghost btn-sm" onclick="exportTableCSV('dailySummaryTable','daily_report')"><i class="fa-solid fa-download"></i> CSV</button>
  </div>
  <div class="table-responsive">
    <table class="data-table" id="dailySummaryTable">
      <thead>
        <tr><th>Date</th><th>Success</th><th>Denied</th><th>Total</th><th>Success Rate</th><th>Revenue</th></tr>
      </thead>
      <tbody>
        <?php foreach ($dailySummary as $row):
          $rate = $row['total_count'] > 0 ? round($row['success_count']/$row['total_count']*100,1) : 0;
        ?>
        <tr>
          <td class="mono"><?= date('D, M d Y', strtotime($row['date'])) ?></td>
          <td class="text-green"><?= number_format($row['success_count']) ?></td>
          <td class="text-red"><?= number_format($row['denied_count']) ?></td>
          <td><?= number_format($row['total_count']) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <div style="flex:1;height:5px;background:var(--bg-panel);border-radius:3px;overflow:hidden;max-width:80px">
                <div style="height:100%;width:<?= $rate ?>%;background:<?= $rate>=90?'var(--accent-green)':($rate>=70?'var(--accent-amber)':'var(--accent-red)') ?>;border-radius:3px"></div>
              </div>
              <span class="mono text-xs"><?= $rate ?>%</span>
            </div>
          </td>
          <td class="text-green mono">$<?= number_format($row['revenue'],2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($dailySummary)): ?>
        <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">No data for selected period</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
// Safely encode all chart data
$jLabels   = json_encode(array_values($dailyLabels));
$jRevenue  = json_encode(array_values($dailyRevenue));
$jSuccess  = json_encode(array_values($dailySuccess));
$jDenied   = json_encode(array_values($dailyDenied));
$jTL       = json_encode(array_values($tagLabels));
$jTC       = json_encode(array_values(array_map('intval', $tagCounts)));
$jTColors  = json_encode(array_values(array_slice($tagColors, 0, count($tagLabels))));
$jVC       = json_encode(array_values(array_map('ucfirst', $vclassLabels)));
$jVR       = json_encode(array_values(array_map('floatval', $vclassRevenue)));
$jVN       = json_encode(array_values(array_map('intval', $vclassCount)));
$jGL       = json_encode(array_values($gateLabels));
$jGS       = json_encode(array_values(array_map('intval', $gateSuccess)));
$jGD       = json_encode(array_values(array_map('intval', $gateDenied)));

$pageScript = "
document.addEventListener('DOMContentLoaded', function() {
  var gridC   = 'rgba(255,255,255,0.05)';
  var isDark  = document.documentElement.getAttribute('data-theme') !== 'light';

  // ---- 1. Revenue Trend ----
  var rCtx = document.getElementById('revenueChart');
  if (rCtx) {
    new Chart(rCtx, {
      type: 'line',
      data: {
        labels: " . $jLabels . ",
        datasets: [{
          label: 'Revenue',
          data: " . $jRevenue . ",
          borderColor: '#00d4ff',
          backgroundColor: 'rgba(0,212,255,0.08)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#00d4ff',
          pointRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { grid: { color: gridC }, ticks: { callback: function(v){ return '$'+v; } } },
          x: { grid: { color: gridC } }
        }
      }
    });
  }

  // ---- 2. Daily Success Rate ----
  var sCtx = document.getElementById('successRateChart');
  if (sCtx) {
    new Chart(sCtx, {
      type: 'bar',
      data: {
        labels: " . $jLabels . ",
        datasets: [
          { label: 'Success', data: " . $jSuccess . ", backgroundColor: 'rgba(0,255,157,0.7)' },
          { label: 'Denied',  data: " . $jDenied . ",  backgroundColor: 'rgba(255,61,90,0.6)' }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { boxWidth: 10 } } },
        scales: {
          x: { stacked: true, grid: { display: false } },
          y: { stacked: true, grid: { color: gridC } }
        }
      }
    });
  }

  // ---- 3. RFID Tag Doughnut ----
  var tData   = " . $jTC . ";
  var tLabels = " . $jTL . ";
  var tColors = " . $jTColors . ";
  var dCtx = document.getElementById('tagDoughnutChart');
  if (dCtx) {
    if (tData.length > 0) {
      new Chart(dCtx, {
        type: 'doughnut',
        data: {
          labels: tLabels,
          datasets: [{ data: tData, backgroundColor: tColors, borderWidth: 2, borderColor: isDark ? '#111827' : '#fff' }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '62%',
          plugins: { legend: { display: false } }
        }
      });
    } else {
      dCtx.parentElement.innerHTML = '<p style=\"text-align:center;padding:40px;color:var(--text-muted)\">No scan data</p>';
    }
  }

  // ---- 4. Vehicle Class ----
  var vCtx = document.getElementById('vehicleClassChart');
  if (vCtx) {
    new Chart(vCtx, {
      type: 'bar',
      data: {
        labels: " . $jVC . ",
        datasets: [
          { label: 'Count',   data: " . $jVN . ", backgroundColor: 'rgba(0,212,255,0.65)', yAxisID: 'y' },
          { label: 'Revenue', data: " . $jVR . ", backgroundColor: 'rgba(0,255,157,0.55)', yAxisID: 'y1' }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { boxWidth: 10 } } },
        scales: {
          y:  { grid: { color: gridC } },
          y1: { position: 'right', grid: { display: false }, ticks: { callback: function(v){ return '$'+v; } } },
          x:  { grid: { display: false } }
        }
      }
    });
  }

  // ---- 5. Gate Performance ----
  var gCtx = document.getElementById('gateChart');
  if (gCtx) {
    new Chart(gCtx, {
      type: 'bar',
      data: {
        labels: " . $jGL . ",
        datasets: [
          { label: 'Success', data: " . $jGS . ", backgroundColor: 'rgba(0,255,157,0.7)' },
          { label: 'Denied',  data: " . $jGD . ", backgroundColor: 'rgba(255,61,90,0.6)' }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { boxWidth: 10 } } },
        scales: {
          x: { grid: { display: false } },
          y: { grid: { color: gridC } }
        }
      }
    });
  }
}); // end DOMContentLoaded

function exportTableCSV(tableId, filename) {
  var tbl = document.getElementById(tableId);
  if (!tbl) return;
  var csv = [];
  var rows = tbl.querySelectorAll('tr');
  for (var i = 0; i < rows.length; i++) {
    var cells = rows[i].querySelectorAll('th,td');
    var row = [];
    for (var j = 0; j < cells.length; j++) {
      row.push('\"' + cells[j].innerText.replace(/\"/g,'\"\"') + '\"');
    }
    csv.push(row.join(','));
  }
  var a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([csv.join('\\n')], {type:'text/csv'}));
  a.download = (filename || 'report') + '_' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
}
";
?>
