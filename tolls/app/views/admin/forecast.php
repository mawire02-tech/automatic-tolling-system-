<?php
$title = 'Revenue Forecast';
$cur = Security::currency();
$jActualLabels = json_encode(array_column($actual, 'd'));
$jActualRevs   = json_encode(array_map('floatval', array_column($actual,'revenue')));
$jForecastLabels = json_encode(array_column($forecast,'day'));
$jForecastRevs   = json_encode(array_column($forecast,'projected_revenue'));
?>
<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">
      <i class="fa-solid fa-chart-area" style="color:var(--accent-cyan);margin-right:8px"></i>Revenue Forecast
    </h2>
    <p class="text-muted text-sm">7-day projection based on historical patterns</p>
  </div>
</div>

<div class="stats-grid mb-24" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">
  <div class="stat-card" style="--accent-color:var(--accent-cyan);--icon-bg:rgba(0,212,255,.1)">
    <div class="stat-icon"><i class="fa-solid fa-calendar-days"></i></div>
    <div class="stat-body"><div class="stat-value" style="font-size:16px"><?= $cur.number_format($summary['actual_30d'],2) ?></div><div class="stat-label">Last 30 Days</div></div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-green);--icon-bg:rgba(0,255,157,.1)">
    <div class="stat-icon"><i class="fa-solid fa-calendar-week"></i></div>
    <div class="stat-body"><div class="stat-value" style="font-size:16px"><?= $cur.number_format($summary['actual_7d'],2) ?></div><div class="stat-label">Last 7 Days</div></div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-purple);--icon-bg:rgba(155,89,255,.1)">
    <div class="stat-icon"><i class="fa-solid fa-crystal-ball"></i></div>
    <div class="stat-body"><div class="stat-value" style="font-size:16px"><?= $cur.number_format($summary['forecast_7d'],2) ?></div><div class="stat-label">Projected Next 7 Days</div></div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-amber);--icon-bg:rgba(255,179,0,.1)">
    <div class="stat-icon"><i class="fa-solid fa-chart-simple"></i></div>
    <div class="stat-body"><div class="stat-value" style="font-size:16px"><?= $cur.number_format($summary['avg_daily'],2) ?></div><div class="stat-label">Daily Average</div></div>
  </div>
</div>

<div class="card mb-24">
  <div class="card-header">
    <i class="fa-solid fa-chart-line" style="color:var(--accent-cyan)"></i>
    <span class="card-title">30-Day Actual vs 7-Day Forecast</span>
  </div>
  <div class="card-body"><div style="position:relative;height:300px"><canvas id="forecastChart"></canvas></div></div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header"><i class="fa-solid fa-calendar-check" style="color:var(--accent-green)"></i><span class="card-title">7-Day Projection</span></div>
    <div class="table-responsive">
      <table class="data-table">
        <thead><tr><th>Day</th><th>Projected Revenue</th><th>Trend</th></tr></thead>
        <tbody>
          <?php foreach ($forecast as $i => $f): ?>
          <tr>
            <td class="mono text-xs"><?= htmlspecialchars($f['day']) ?></td>
            <td class="text-green mono"><?= $cur.number_format($f['projected_revenue'],2) ?></td>
            <td>
              <?php $trend = $i > 0 ? $f['projected_revenue'] - $forecast[$i-1]['projected_revenue'] : 0; ?>
              <span style="color:<?= $trend>=0?'var(--accent-green)':'var(--accent-red)' ?>">
                <i class="fa-solid fa-arrow-<?= $trend>=0?'trend-up':'trend-down' ?>"></i>
                <?= $trend>=0?'+':'' ?><?= $cur.number_format($trend,2) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><i class="fa-solid fa-trophy" style="color:var(--accent-amber)"></i><span class="card-title">Best Performing Day</span></div>
    <div class="card-body" style="text-align:center;padding:40px">
      <i class="fa-solid fa-star fa-3x" style="color:var(--accent-amber);margin-bottom:16px;display:block"></i>
      <div style="font-size:22px;font-weight:700;color:var(--text-primary)"><?= date('l, M d Y', strtotime($summary['best_day'])) ?></div>
      <div style="font-size:28px;font-weight:700;color:var(--accent-green);margin:8px 0"><?= $cur.number_format($summary['best_revenue'],2) ?></div>
      <div class="text-muted text-sm">Highest single-day revenue</div>
    </div>
  </div>
</div>

<?php $pageScript = "
document.addEventListener('DOMContentLoaded', function() {
  var gridC = 'rgba(255,255,255,0.05)';
  var ctx = document.getElementById('forecastChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: " . $jActualLabels . ".concat(" . $jForecastLabels . "),
      datasets: [
        { label: 'Actual Revenue',    data: " . $jActualRevs . ".concat(Array(" . count($forecast) . ").fill(null)),
          borderColor:'#00d4ff', backgroundColor:'rgba(0,212,255,.08)', fill:true, tension:0.4, pointRadius:3 },
        { label: 'Projected Revenue', data: Array(" . count($actual) . ").fill(null).concat(" . $jForecastRevs . "),
          borderColor:'#9b59ff', backgroundColor:'rgba(155,89,255,.08)', fill:true, tension:0.4,
          borderDash:[6,3], pointRadius:4, pointStyle:'star' }
      ]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ position:'top', labels:{ boxWidth:12 } } },
      scales:{
        x:{ grid:{ color:gridC }, ticks:{ maxTicksLimit:14, maxRotation:45 } },
        y:{ grid:{ color:gridC }, ticks:{ callback:function(v){ return '" . addslashes(Security::currency()) . "'+v; } } }
      }
    }
  });
});
"; ?>
