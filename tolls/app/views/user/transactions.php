<?php $title = 'My Transactions'; ?>
<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">My Transactions</h2>
    <p class="text-muted text-sm">Total spent: <span class="text-green mono">$<?= number_format($totalSpent,2) ?></span></p>
  </div>
  <button class="btn btn-outline" onclick="exportTable('userTxTable','my_transactions')">&#8595; Export CSV</button>
</div>

<div class="filter-bar">
  <form method="GET" class="d-flex gap-8 flex-wrap" style="width:100%">
    <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>" style="width:145px">
    <input type="date" name="date_to"   class="form-control" value="<?= $dateTo ?>"   style="width:145px">
    <button type="submit" class="btn btn-outline">Filter</button>
    <a href="<?= url('user/transactions') ?>" class="btn btn-ghost">Reset</a>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Results: <?= $total ?></span>
  </div>
  <div class="table-responsive">
    <table class="data-table" id="userTxTable">
      <thead><tr><th>Ref</th><th>Plate</th><th>Booth</th><th>Amount</th><th>Balance After</th><th>Status</th><th>Date &amp; Time</th></tr></thead>
      <tbody>
        <?php foreach ($txs as $tx): ?>
        <tr>
          <td class="mono text-xs text-cyan"><?= htmlspecialchars($tx['transaction_ref']) ?></td>
          <td class="mono"><?= htmlspecialchars($tx['plate_number'] ?? 'N/A') ?></td>
          <td class="text-muted text-xs"><?= htmlspecialchars($tx['device_name'] ?? 'N/A') ?></td>
          <td class="text-green mono">$<?= number_format($tx['toll_amount'],2) ?></td>
          <td class="mono text-xs"><?= $tx['balance_after'] !== null ? '$'.number_format($tx['balance_after'],2) : 'N/A' ?></td>
          <td><span class="badge badge-<?= $tx['status']==='success'?'success':($tx['status']==='denied'?'danger':'warning') ?>"><?= strtoupper($tx['status']) ?></span></td>
          <td class="text-muted text-xs mono"><?= date('Y-m-d H:i', strtotime($tx['processed_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($txs)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">No transactions in this period</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (ceil($total/$limit) > 1): ?>
  <div class="card-footer">
    <div class="pagination">
      <?php for ($i=1;$i<=ceil($total/$limit);$i++): ?>
      <a href="?page=<?=$i?>&date_from=<?=$dateFrom?>&date_to=<?=$dateTo?>" class="page-link <?=$i===$page?'active':''?>"><?=$i?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php $pageScript = "function exportTable(t,f){const tbl=document.getElementById(t);let c=[];tbl.querySelectorAll('tr').forEach(r=>{let d=[];r.querySelectorAll('th,td').forEach(cl=>d.push('\"'+cl.innerText.replace(/\"/g,'\"\"')+'\"'));c.push(d.join(','));});const b=new Blob([c.join('\n')],{type:'text/csv'});const a=document.createElement('a');a.href=URL.createObjectURL(b);a.download=f+'_'+new Date().toISOString().slice(0,10)+'.csv';a.click();}"; ?>
