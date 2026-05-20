<?php
$title    = 'Transactions';
$limit    = isset($limit)   ? max(1,(int)$limit)   : 20;
$total    = isset($total)   ? (int)$total           : 0;
$page     = isset($page)    ? max(1,(int)$page)     : 1;
$revenue  = isset($revenue) ? (float)$revenue       : 0;
$search   = $search   ?? '';
$dateFrom = $dateFrom ?? date('Y-m-d', strtotime('-30 days'));
$dateTo   = $dateTo   ?? date('Y-m-d');
$status   = $status   ?? '';
$deviceId = $deviceId ?? 0;
$txs      = $txs      ?? array();
$devices  = $devices  ?? array();
$pages    = ($limit > 0 && $total > 0) ? (int)ceil($total / $limit) : 1;
$cur      = Security::currency();
?>

<div class="d-flex align-center justify-between mb-20" style="flex-wrap:wrap;gap:12px">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">Transactions</h2>
    <p class="text-muted text-sm">Total Revenue: <span class="text-green mono"><?= $cur . number_format($revenue,2) ?></span></p>
  </div>
  <button class="btn btn-outline" onclick="exportTable('txTable','transactions')">&#8595; Export CSV</button>
</div>

<div class="card mb-16">
  <div class="card-body" style="padding:14px">
    <form method="GET" action="<?= url('admin/transactions') ?>" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end">
      <div style="flex:2;min-width:150px">
        <div class="form-label">Search</div>
        <input type="text" name="search" class="form-control" placeholder="Plate, ref, owner..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div style="flex:1;min-width:120px">
        <div class="form-label">From</div>
        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div style="flex:1;min-width:120px">
        <div class="form-label">To</div>
        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      <div style="flex:1;min-width:110px">
        <div class="form-label">Status</div>
        <select name="status" class="form-control">
          <option value="">All</option>
          <option value="success" <?= $status==='success'?'selected':'' ?>>Success</option>
          <option value="denied"  <?= $status==='denied' ?'selected':'' ?>>Denied</option>
          <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
        </select>
      </div>
      <div style="flex:1;min-width:130px">
        <div class="form-label">Device</div>
        <select name="device_id" class="form-control">
          <option value="">All Devices</option>
          <?php foreach ($devices as $d): ?>
          <option value="<?= $d['id'] ?>" <?= (int)$deviceId==(int)$d['id']?'selected':'' ?>><?= htmlspecialchars($d['device_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:6px;align-items:flex-end">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="<?= url('admin/transactions') ?>" class="btn btn-ghost">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Results: <?= number_format($total) ?> records</span>
  </div>
  <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%">
    <table class="data-table" id="txTable" style="min-width:750px;width:100%;table-layout:auto">
      <thead>
        <tr>
          <th style="min-width:90px">Reference</th>
          <th style="min-width:90px">Plate</th>
          <th style="min-width:110px">Owner</th>
          <th style="min-width:110px">Device</th>
          <th style="min-width:70px">Type</th>
          <th style="min-width:80px">Amount</th>
          <th style="min-width:80px">Balance</th>
          <th style="min-width:80px">Status</th>
          <th style="min-width:110px">Processed</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($txs)): ?>
        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">No transactions found</td></tr>
        <?php else: foreach ($txs as $tx):
          $s = $tx['status'] ?? 'pending';
          $bc = $s==='success'?'success':($s==='denied'?'danger':($s==='pending'?'warning':'muted'));
        ?>
        <tr>
          <td><span class="mono text-xs text-cyan" title="<?= htmlspecialchars($tx['transaction_ref']??'') ?>" style="display:block;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($tx['transaction_ref']??'—') ?></span></td>
          <td><span class="mono" style="color:var(--text-primary);font-weight:600;font-size:12px"><?= htmlspecialchars($tx['plate_number']??'—') ?></span></td>
          <td style="max-width:130px"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px"><?= htmlspecialchars($tx['full_name']??'Unknown') ?></span></td>
          <td style="max-width:120px"><span class="text-muted text-xs" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($tx['device_name']??'—') ?></span></td>
          <td><span class="badge badge-info" style="font-size:9px"><?= strtoupper($tx['vehicle_type']??'—') ?></span></td>
          <td><span class="text-green mono" style="font-size:12px;white-space:nowrap"><?= $cur.number_format((float)($tx['toll_amount']??0),2) ?></span></td>
          <td class="mono text-xs" style="font-size:11px;white-space:nowrap"><?= isset($tx['balance_after'])&&$tx['balance_after']!==null ? $cur.number_format((float)$tx['balance_after'],2) : '—' ?></td>
          <td>
            <span class="badge badge-<?= $bc ?>"><?= strtoupper($s) ?></span>
            <?php if (!empty($tx['deny_reason'])): ?>
            <div class="text-xs text-muted" style="font-size:9px;margin-top:2px;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($tx['deny_reason']) ?>"><?= htmlspecialchars($tx['deny_reason']) ?></div>
            <?php endif; ?>
          </td>
          <td class="text-muted mono" style="font-size:10px;white-space:nowrap"><?= isset($tx['processed_at']) ? date('m/d H:i', strtotime($tx['processed_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex justify-between align-center" style="flex-wrap:wrap;gap:8px">
    <span class="text-muted text-sm">Showing <?= count($txs) ?> of <?= number_format($total) ?></span>
    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php for ($i=1; $i<=min($pages,10); $i++): ?>
      <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&status=<?= urlencode($status) ?>&device_id=<?= (int)$deviceId ?>"
         class="page-link <?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($pages > 10): ?><span class="page-link disabled">...</span><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
