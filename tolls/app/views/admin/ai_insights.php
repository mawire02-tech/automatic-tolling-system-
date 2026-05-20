<?php $title = 'Smart AI Insights'; $cur = Security::currency(); ?>

<div class="d-flex align-center justify-between mb-20" style="flex-wrap:wrap;gap:12px">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">
      <i class="fa-solid fa-microchip" style="color:var(--accent-purple);margin-right:8px"></i>Smart AI Insights
    </h2>
    <p class="text-muted text-sm">Rule-based intelligence engine — real-time analysis, no external APIs required</p>
  </div>
  <div class="d-flex gap-8 align-center">
    <span class="badge badge-purple" style="padding:6px 12px;font-size:11px">
      <i class="fa-solid fa-microchip"></i> Rule Engine v1.0
    </span>
    <a href="<?= url('admin/ai-insights') ?>" class="btn btn-ghost btn-sm">
      <i class="fa-solid fa-arrows-rotate"></i> Refresh
    </a>
  </div>
</div>

<!-- ═══ SMART ALERTS (always first) ═══════════════════════════ -->
<?php if (!empty($alerts['alerts'])): ?>
<div class="card mb-20" style="border-color:rgba(255,61,90,.3)">
  <div class="card-header" style="background:rgba(255,61,90,.04)">
    <i class="fa-solid fa-bell-ring" style="color:var(--accent-red)"></i>
    <span class="card-title">Smart Alerts</span>
    <span class="badge badge-danger"><?= $alerts['count'] ?> active</span>
  </div>
  <div class="card-body" style="padding:12px 16px;display:grid;gap:10px">
    <?php foreach ($alerts['alerts'] as $a): ?>
    <div class="d-flex align-center gap-12" style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-left:3px solid <?= $a['level']==='danger'?'var(--accent-red)':($a['level']==='warning'?'var(--accent-amber)':'var(--accent-green)') ?>;border-radius:var(--radius);padding:10px 14px">
      <i class="fa-solid <?= $a['icon'] ?>" style="color:<?= $a['level']==='danger'?'var(--accent-red)':($a['level']==='warning'?'var(--accent-amber)':'var(--accent-green)') ?>;font-size:18px;width:20px;flex-shrink:0"></i>
      <div style="flex:1">
        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($a['title']) ?></div>
        <div class="text-xs text-muted"><?= htmlspecialchars($a['body']) ?></div>
      </div>
      <a href="<?= htmlspecialchars($a['action']) ?>" class="btn btn-ghost btn-xs">View <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php else: ?>
<div class="alert alert-success mb-20">
  <i class="fa-solid fa-circle-check"></i> <strong>All Clear</strong> — No active alerts. System operating normally.
</div>
<?php endif; ?>

<!-- ═══ ROW 1: Fraud Detection + Anomaly Detection ════════════ -->
<div class="grid-2 mb-20">

  <!-- FRAUD DETECTION -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-shield-halved" style="color:var(--accent-red)"></i>
      <span class="card-title">Fraud Detection</span>
      <span class="badge badge-<?= $fraud['risk_level']==='low'?'success':($fraud['risk_level']==='medium'?'warning':'danger') ?>">
        <?= strtoupper($fraud['risk_level']) ?> RISK
      </span>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (empty($fraud['flags'])): ?>
      <div style="padding:30px;text-align:center;color:var(--text-muted)">
        <i class="fa-solid fa-shield-check fa-2x" style="color:var(--accent-green);display:block;margin-bottom:10px"></i>
        No fraud indicators in last 24 hours
      </div>
      <?php else: foreach ($fraud['flags'] as $f): ?>
      <div style="padding:11px 16px;border-bottom:1px solid var(--border)">
        <div class="d-flex align-center gap-8 mb-4">
          <span class="badge badge-<?= $f['level']==='high'?'danger':'warning' ?>">
            <i class="fa-solid <?= $f['icon'] ?>"></i> <?= htmlspecialchars($f['rule']) ?>
          </span>
          <?php if ($f['plate']): ?>
          <span class="mono text-xs text-cyan"><?= htmlspecialchars($f['plate']) ?></span>
          <?php endif; ?>
        </div>
        <div class="text-xs text-muted"><?= htmlspecialchars($f['detail']) ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- ANOMALY DETECTION -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-wand-magic-sparkles" style="color:var(--accent-amber)"></i>
      <span class="card-title">Anomaly Detection</span>
      <span class="badge badge-<?= $anomalies['has_high']?'danger':($anomalies['count']>0?'warning':'success') ?>">
        <?= $anomalies['count'] ?> found
      </span>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (empty($anomalies['anomalies'])): ?>
      <div style="padding:30px;text-align:center;color:var(--text-muted)">
        <i class="fa-solid fa-wave-square fa-2x" style="color:var(--accent-green);display:block;margin-bottom:10px"></i>
        All metrics within normal range (z-score &lt; 2.0)
      </div>
      <?php else: foreach ($anomalies['anomalies'] as $a): ?>
      <div style="padding:11px 16px;border-bottom:1px solid var(--border)">
        <div class="d-flex justify-between align-center mb-4">
          <span class="d-flex align-center gap-6">
            <i class="fa-solid <?= $a['icon'] ?>" style="color:<?= $a['color'] ?>"></i>
            <span style="font-weight:600;font-size:12px"><?= htmlspecialchars($a['type']) ?></span>
          </span>
          <span class="badge badge-<?= $a['level']==='high'?'danger':'warning' ?>">z=<?= $a['zscore'] ?></span>
        </div>
        <div class="text-xs text-muted"><?= htmlspecialchars($a['date']) ?> — <?= htmlspecialchars($a['value']) ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- ═══ ROW 2: Traffic Patterns + Queue Estimation ═══════════ -->
<div class="grid-2 mb-20">

  <!-- TRAFFIC PATTERN ESTIMATION -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-traffic-light" style="color:var(--accent-cyan)"></i>
      <span class="card-title">Traffic Pattern Estimation</span>
      <span class="badge badge-<?= $traffic['level']==='high'?'danger':($traffic['level']==='medium'?'warning':'success') ?>">
        <?= strtoupper($traffic['level']) ?>
      </span>
    </div>
    <div class="card-body">
      <div class="d-flex gap-16 mb-16" style="flex-wrap:wrap">
        <div>
          <div class="text-muted text-xs">Total Today</div>
          <div class="mono" style="font-size:20px;font-weight:700"><?= $traffic['totalToday'] ?></div>
        </div>
        <div>
          <div class="text-muted text-xs">Peak Hour</div>
          <div class="mono" style="font-size:20px;font-weight:700;color:var(--accent-amber)"><?= sprintf('%02d:00',$traffic['peakHour']) ?></div>
        </div>
        <div>
          <div class="text-muted text-xs">vs Yesterday</div>
          <div class="mono" style="font-size:20px;font-weight:700;color:<?= $traffic['trend']>=0?'var(--accent-green)':'var(--accent-red)' ?>">
            <?= $traffic['trend']>=0?'+':'' ?><?= $traffic['trend'] ?>%
          </div>
        </div>
        <?php if ($traffic['busiest']): ?>
        <div>
          <div class="text-muted text-xs">Busiest Gate</div>
          <div style="font-size:12px;font-weight:600"><?= htmlspecialchars($traffic['busiest']['device_name']) ?></div>
        </div>
        <?php endif; ?>
      </div>
      <!-- CSS-only hourly bar chart -->
      <div style="display:flex;align-items:flex-end;gap:2px;height:60px;margin-bottom:6px">
        <?php $maxH=max(max($traffic['byHour']),1); foreach ($traffic['byHour'] as $hr=>$v): ?>
        <div title="<?= sprintf('%02d:00',$hr) ?>: <?= $v ?>"
             style="flex:1;height:<?= max(round($v/$maxH*100),2) ?>%;background:<?= $hr==$traffic['peakHour']?'var(--accent-amber)':'rgba(0,212,255,.5)' ?>;border-radius:2px 2px 0 0;min-height:2px"></div>
        <?php endforeach; ?>
      </div>
      <div class="d-flex justify-between" style="font-size:9px;color:var(--text-muted)">
        <span>00:00</span><span>06:00</span><span>12:00</span><span>18:00</span><span>23:00</span>
      </div>
      <?php if (!empty($traffic['weekly'])): ?>
      <div style="margin-top:14px;border-top:1px solid var(--border);padding-top:12px">
        <div class="text-xs text-muted mb-8">28-Day Day-of-Week Pattern</div>
        <div style="display:flex;gap:6px">
          <?php foreach ($traffic['weekly'] as $w): $avg=round($w['avg_vol']); ?>
          <div style="flex:1;text-align:center">
            <div style="height:<?= min($avg*3,50) ?>px;background:rgba(155,89,255,.5);border-radius:3px 3px 0 0;margin-bottom:3px;min-height:2px"></div>
            <div style="font-size:9px;color:var(--text-muted)"><?= substr($w['day_name'],0,3) ?></div>
            <div style="font-size:9px;font-family:var(--font-mono)"><?= $avg ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- QUEUE ESTIMATION -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-arrow-right-arrow-left" style="color:var(--accent-green)"></i>
      <span class="card-title">Queue Estimation</span>
      <span class="text-muted text-xs">Last 15 min: <strong><?= $queue['total15min'] ?></strong> vehicles</span>
    </div>
    <div class="card-body" style="padding:0">
      <?php if ($queue['recommendation'] !== 'All gates busy'): ?>
      <div style="padding:10px 16px;background:rgba(0,255,157,.06);border-bottom:1px solid rgba(0,255,157,.15)">
        <i class="fa-solid fa-circle-check" style="color:var(--accent-green)"></i>
        <span style="font-size:12px;color:var(--accent-green)">
          Recommended gate: <strong><?= htmlspecialchars($queue['recommendation']) ?></strong>
        </span>
      </div>
      <?php endif; ?>
      <?php foreach ($queue['gates'] as $g): ?>
      <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
        <div class="d-flex justify-between align-center mb-6">
          <div>
            <span style="font-weight:600;font-size:13px"><?= htmlspecialchars($g['gate']) ?></span>
            <?php if ($g['recommended']): ?>
            <span class="badge badge-success" style="margin-left:6px;font-size:9px">BEST</span>
            <?php endif; ?>
          </div>
          <span style="font-size:11px;color:<?= $g['color'] ?>;font-weight:600"><?= $g['status'] ?></span>
        </div>
        <div style="background:var(--bg-panel);border-radius:3px;height:6px;margin-bottom:6px">
          <div style="width:<?= $g['load'] ?>%;height:100%;background:<?= $g['color'] ?>;border-radius:3px"></div>
        </div>
        <div class="d-flex justify-between text-xs text-muted">
          <span><?= $g['vol15'] ?> vehicles/15min</span>
          <span><?= $g['wait_min'] ?>min wait</span>
          <span><?= $g['load'] ?>% load</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ═══ ROW 3: Dynamic Toll Suggestions + User Classification ═ -->
<div class="grid-2 mb-20">

  <!-- DYNAMIC TOLL SUGGESTIONS -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-tags" style="color:var(--accent-amber)"></i>
      <span class="card-title">Dynamic Toll Suggestions</span>
      <span class="badge badge-<?= $tollSugg['isPeak']?'warning':($tollSugg['isOffPeak']?'info':'muted') ?>">
        <?= $tollSugg['timeLabel'] ?>
      </span>
    </div>
    <div class="card-body">
      <div class="d-flex gap-8 flex-wrap mb-16">
        <?php if ($tollSugg['isCongested']): ?><span class="badge badge-danger"><i class="fa-solid fa-car-burst"></i> Congested</span><?php endif; ?>
        <?php if ($tollSugg['isPeak']):      ?><span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Peak Hours</span><?php endif; ?>
        <?php if ($tollSugg['isOffPeak']):   ?><span class="badge badge-info"><i class="fa-solid fa-moon"></i> Off-Peak</span><?php endif; ?>
        <?php if ($tollSugg['isWeekend']):   ?><span class="badge badge-success"><i class="fa-solid fa-umbrella-beach"></i> Weekend</span><?php endif; ?>
        <span class="text-muted text-xs"><?= $tollSugg['lastHrVol'] ?> vehicles/hr</span>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Vehicle</th><th>Current</th><th>Suggested</th><th>Change</th><th>Rule Applied</th></tr></thead>
          <tbody>
            <?php foreach ($tollSugg['suggestions'] as $s): ?>
            <tr>
              <td><i class="fa-solid fa-car text-muted" style="margin-right:5px"></i><?= $s['vehicle'] ?></td>
              <td class="mono"><?= $cur.number_format($s['current'],2) ?></td>
              <td class="mono" style="font-weight:700;color:<?= $s['change']>0?'var(--accent-red)':($s['change']<0?'var(--accent-green)':'var(--text-muted)') ?>">
                <?= $cur.number_format($s['suggested'],2) ?>
              </td>
              <td class="mono text-xs" style="color:<?= $s['change']>0?'var(--accent-red)':($s['change']<0?'var(--accent-green)':'var(--text-muted)') ?>">
                <?= $s['change']>=0?'+':'' ?><?= $cur.number_format($s['change'],2) ?>
              </td>
              <td class="text-xs text-muted"><?= htmlspecialchars($s['reason']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="text-muted text-xs" style="margin-top:10px">
        <i class="fa-solid fa-info-circle"></i> Recommendations only. Apply in
        <a href="<?= url('admin/settings') ?>" style="color:var(--accent-cyan)">Settings</a>.
      </p>
    </div>
  </div>

  <!-- USER USAGE CLASSIFICATION -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-users-gear" style="color:var(--accent-purple)"></i>
      <span class="card-title">User Usage Classification</span>
    </div>
    <div class="card-body">
      <?php
      $classColors = array('VIP'=>'var(--accent-amber)','Frequent'=>'var(--accent-cyan)','Occasional'=>'var(--accent-purple)','At-Risk'=>'var(--accent-red)','Inactive'=>'var(--text-muted)');
      $classIcons  = array('VIP'=>'fa-crown','Frequent'=>'fa-medal','Occasional'=>'fa-user','At-Risk'=>'fa-triangle-exclamation','Inactive'=>'fa-user-slash');
      $total = array_sum($userClass['totals']);
      ?>
      <div style="display:grid;gap:10px;margin-bottom:16px">
        <?php foreach ($userClass['totals'] as $cls => $cnt): if (!$cnt) continue;
          $pct = $total>0?round($cnt/$total*100):0;
          $col = $classColors[$cls]??'var(--text-muted)';
          $ico = $classIcons[$cls]??'fa-user';
        ?>
        <div>
          <div class="d-flex justify-between align-center mb-4">
            <span style="font-size:12px"><i class="fa-solid <?= $ico ?>" style="color:<?= $col ?>;width:14px"></i> <?= $cls ?></span>
            <span class="mono text-xs"><?= $cnt ?> (<?= $pct ?>%)</span>
          </div>
          <div style="background:var(--bg-panel);border-radius:3px;height:6px">
            <div style="width:<?= $pct ?>%;height:100%;background:<?= $col ?>;border-radius:3px"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (!empty($userClass['classes']['At-Risk'])): ?>
      <div class="alert alert-warning" style="font-size:11px;margin-bottom:10px">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <strong><?= count($userClass['classes']['At-Risk']) ?></strong> at-risk users may be denied on next scan.
        <a href="<?= url('admin/notifications') ?>" style="color:var(--accent-amber)">Send Alert <i class="fa-solid fa-arrow-right"></i></a>
      </div>
      <?php endif; ?>
      <div class="text-xs text-muted">
        <i class="fa-solid fa-info-circle"></i> VIP: &gt;<?= $cur ?>500 spent + 20+ trips/month. At-Risk: balance below threshold.
      </div>
    </div>
  </div>
</div>

<!-- ═══ ROW 4: Vehicle Behaviour (full width) ════════════════ -->
<div class="card mb-20">
  <div class="card-header">
    <i class="fa-solid fa-car-side" style="color:var(--accent-cyan)"></i>
    <span class="card-title">Vehicle Behaviour Classification</span>
    <span class="text-muted text-xs">Last 30 days &middot; min 3 trips per vehicle</span>
  </div>
  <div class="card-body">
    <?php if (!empty($behaviour['summary'])): ?>
    <div class="d-flex gap-12 flex-wrap mb-16">
      <?php
      $bColors = array('Frequent Regular'=>'var(--accent-cyan)','Rush Hour Commuter'=>'var(--accent-amber)','Night Traveller'=>'var(--accent-purple)','Weekend Driver'=>'var(--accent-green)','Cross-Gate Traveller'=>'var(--accent-red)','Occasional User'=>'var(--text-muted)');
      $bIcons  = array('Frequent Regular'=>'fa-medal','Rush Hour Commuter'=>'fa-traffic-light','Night Traveller'=>'fa-moon','Weekend Driver'=>'fa-umbrella-beach','Cross-Gate Traveller'=>'fa-route','Occasional User'=>'fa-user');
      foreach ($behaviour['summary'] as $prof=>$cnt):
        $col=$bColors[$prof]??'var(--text-muted)'; $ico=$bIcons[$prof]??'fa-car';
      ?>
      <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:var(--radius);padding:12px 16px;min-width:130px">
        <div style="font-size:22px;font-weight:700;color:<?= $col ?>"><?= $cnt ?></div>
        <div style="font-size:11px;color:var(--text-muted)"><i class="fa-solid <?= $ico ?>"></i> <?= $prof ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead><tr><th>Plate</th><th>Type</th><th>Behaviour Profile</th><th>Trips (30d)</th><th>Avg Hour</th><th>Gates Used</th></tr></thead>
        <tbody>
          <?php if (empty($behaviour['vehicles'])): ?>
          <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">
            Need 3+ trips per vehicle in last 30 days to classify behaviour
          </td></tr>
          <?php else: foreach (array_slice($behaviour['vehicles'],0,20) as $v):
            $col=$bColors[$v['profile']]??'var(--text-muted)'; $ico=$bIcons[$v['profile']]??'fa-car';
          ?>
          <tr>
            <td class="mono" style="font-weight:700"><?= htmlspecialchars($v['plate']) ?></td>
            <td><span class="badge badge-info"><?= $v['type'] ?></span></td>
            <td style="color:<?= $col ?>;font-size:12px"><i class="fa-solid <?= $ico ?>"></i> <?= $v['profile'] ?></td>
            <td class="mono text-xs"><?= $v['trips'] ?></td>
            <td class="mono text-xs"><?= sprintf('%02d:00',round($v['avgHour'])) ?></td>
            <td class="mono text-xs"><?= $v['gates'] ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
