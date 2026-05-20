<?php
$title    = 'System Logs';
$limit    = isset($limit)    ? max(1,(int)$limit)   : 50;
$total    = isset($total)    ? (int)$total           : 0;
$page     = isset($page)     ? max(1,(int)$page)     : 1;
$type     = $type     ?? '';
$severity = $severity ?? '';
$dateFrom = $dateFrom ?? date('Y-m-d', strtotime('-7 days'));
$dateTo   = $dateTo   ?? date('Y-m-d');
$logs     = $logs     ?? array();
$pages    = ($limit > 0 && $total > 0) ? (int)ceil($total / $limit) : 1;
?>

<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">System Logs</h2>
    <p class="text-muted text-sm">Complete audit trail of all system events</p>
  </div>
  <button class="btn btn-outline" onclick="exportTable('logsTable','system_logs')">&#8595; Export CSV</button>
</div>

<div class="filter-bar">
  <form method="GET" action="<?= url('admin/logs') ?>" class="d-flex gap-8 flex-wrap" style="width:100%">
    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" style="width:135px">
    <input type="date" name="date_to"   class="form-control" value="<?= htmlspecialchars($dateTo) ?>"   style="width:135px">
    <select name="type" class="form-control" style="width:135px">
      <option value="">All Types</option>
      <?php foreach (array('auth','transaction','device','admin','error','security','system') as $t): ?>
      <option value="<?= $t ?>" <?= $type===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="severity" class="form-control" style="width:125px">
      <option value="">All Severity</option>
      <?php foreach (array('info','warning','error','critical') as $s): ?>
      <option value="<?= $s ?>" <?= $severity===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Filter</button>
    <a href="<?= url('admin/logs') ?>" class="btn btn-ghost">Reset</a>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Log Entries: <?= number_format($total) ?></span>
  </div>
  <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%">
    <table class="data-table" id="logsTable" style="min-width:700px;width:100%">
      <thead>
        <tr>
          <th style="min-width:100px">Time</th>
          <th style="min-width:90px">Type</th>
          <th style="min-width:80px">Severity</th>
          <th style="min-width:90px">User</th>
          <th style="min-width:90px">Action</th>
          <th>Description</th>
          <th style="min-width:90px">IP</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">No logs found</td></tr>
        <?php else: foreach ($logs as $l):
          $tc = array('auth'=>'bi','transaction'=>'bs','security'=>'bd','device'=>'bw','error'=>'bd','admin'=>'bp','system'=>'bm');
          $sc = array('info'=>'bi','warning'=>'bw','error'=>'bd','critical'=>'bd');
          $typeClass = $tc[$l['log_type'] ?? ''] ?? 'bm';
          $sevClass  = $sc[$l['severity']  ?? ''] ?? 'bm';
        ?>
        <tr>
          <td class="mono text-xs text-muted" style="white-space:nowrap">
            <?= isset($l['logged_at']) ? date('H:i:s', strtotime($l['logged_at'])) : '—' ?>
            <div style="font-size:9px"><?= isset($l['logged_at']) ? date('Y-m-d', strtotime($l['logged_at'])) : '' ?></div>
          </td>
          <td><span class="badge badge-<?= $typeClass ?>"><?= strtoupper($l['log_type'] ?? '—') ?></span></td>
          <td><span class="badge badge-<?= $sevClass  ?>"><?= strtoupper($l['severity']  ?? '—') ?></span></td>
          <td class="text-muted text-xs"><?= htmlspecialchars($l['username'] ?? 'System') ?></td>
          <td class="mono text-xs text-cyan" style="white-space:nowrap"><?= htmlspecialchars($l['action'] ?? '—') ?></td>
          <td class="text-muted text-xs" style="max-width:300px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($l['description'] ?? '') ?></td>
          <td class="mono text-xs text-muted"><?= htmlspecialchars($l['ip_address'] ?? '—') ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex justify-between align-center" style="flex-wrap:wrap;gap:8px">
    <span class="text-muted text-sm">Showing <?= count($logs) ?> of <?= number_format($total) ?></span>
    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php for ($i=1; $i<=min($pages,8); $i++): ?>
      <a href="?page=<?= $i ?>&type=<?= urlencode($type) ?>&severity=<?= urlencode($severity) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
         class="page-link <?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($pages > 8): ?><span class="page-link disabled">...</span><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
