<?php $title = 'Top-Up Requests'; ?>

<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">Top-Up Requests</h2>
    <p class="text-muted text-sm">Review and approve or reject wallet top-up requests</p>
  </div>
</div>

<div class="filter-bar mb-16">
  <div class="d-flex gap-8">
    <a href="?status=pending"  class="btn btn-sm <?= $status==='pending'  ?'btn-warning':'btn-ghost' ?>">Pending (<?= count(array_filter($requests??[], function($r){ return $r['status']==='pending'; })) ?>)</a>
    <a href="?status=approved" class="btn btn-sm <?= $status==='approved' ?'btn-success':'btn-ghost' ?>">Approved</a>
    <a href="?status=rejected" class="btn btn-sm <?= $status==='rejected' ?'btn-danger' :'btn-ghost' ?>">Rejected</a>
  </div>
</div>

<div class="card">
  <div class="card-header"><span class="card-title"><?= ucfirst($status) ?> Requests (<?= count($requests) ?>)</span></div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Reference</th><th>User</th><th>Amount</th><th>Method</th><th>Ext. Ref</th><th>Balance</th><th>Requested</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($requests as $r): ?>
        <tr id="topup-row-<?= $r['id'] ?>">
          <td class="mono text-xs text-cyan"><?= htmlspecialchars($r['request_ref']) ?></td>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($r['full_name']) ?></div>
            <div class="text-xs text-muted">@<?= htmlspecialchars($r['username']) ?></div>
          </td>
          <td><span class="text-green mono" style="font-size:15px;font-weight:700">$<?= number_format($r['amount'],2) ?></span></td>
          <td><?= (array('ecocash'=>'EcoCash','onemoney'=>'OneMoney','bank'=>'Bank Transfer','cash'=>'Cash','bank_transfer'=>'Bank Transfer','gcash'=>'GCash','maya'=>'Maya','card'=>'Card')[$r['payment_method']] ?? ucfirst(str_replace('_',' ',$r['payment_method']))) ?></td>
          <td class="text-muted text-xs mono"><?= htmlspecialchars($r['reference_number']?:'—') ?></td>
          <td class="mono text-xs">$<?= number_format($r['wallet_balance'],2) ?></td>
          <td class="text-muted text-xs"><?= date('M d Y H:i',strtotime($r['requested_at'])) ?></td>
          <td><span class="badge badge-<?= $r['status']==='approved'?'success':($r['status']==='rejected'?'danger':'warning') ?>"><?= strtoupper($r['status']) ?></span></td>
          <td>
            <?php if ($r['status']==='pending'): ?>
            <div class="d-flex gap-8">
              <button class="btn btn-success btn-xs" onclick="processTopup(<?= $r['id'] ?>,'approved')">Approve</button>
              <button class="btn btn-danger btn-xs"  onclick="processTopup(<?= $r['id'] ?>,'rejected')">Reject</button>
            </div>
            <?php else: ?>
            <span class="text-muted text-xs"><?= htmlspecialchars($r['admin_note']?:'—') ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($requests)): ?>
        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">No <?= $status ?> requests</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Process Modal -->
<div class="modal-overlay" id="processModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title" id="processTitle">Process Request</span>
      <button class="modal-close" onclick="closeModal('processModal')">&#x2715;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="processId">
      <input type="hidden" id="processAction">
      <div class="form-group">
        <label class="form-label">Admin Note (optional)</label>
        <textarea id="adminNote" class="form-control" rows="3" placeholder="Add a note for the user..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('processModal')">Cancel</button>
      <button class="btn btn-primary" id="processConfirmBtn" onclick="confirmProcess()">Confirm</button>
    </div>
  </div>
</div>

<?php
$processUrl = url('admin/topups/process');
$csrfName   = CSRF_TOKEN_NAME;
$csrfVal    = Security::csrfToken();
$pageScript = "
var P_URL      = '" . addslashes($processUrl) . "';
var P_CSRF_KEY = '" . addslashes($csrfName) . "';
var P_CSRF_VAL = '" . addslashes($csrfVal) . "';

function processTopup(id, action) {
  document.getElementById('processId').value     = id;
  document.getElementById('processAction').value = action;
  document.getElementById('adminNote').value     = '';
  document.getElementById('processTitle').textContent = action === 'approved' ? 'Approve Top-Up' : 'Reject Top-Up';
  var btn = document.getElementById('processConfirmBtn');
  btn.className = 'btn btn-' + (action === 'approved' ? 'success' : 'danger');
  btn.textContent = action === 'approved' ? 'Approve' : 'Reject';
  openModal('processModal');
}

function confirmProcess() {
  var fd = new FormData();
  fd.append('id',       document.getElementById('processId').value);
  fd.append('action',   document.getElementById('processAction').value);
  fd.append('note',     document.getElementById('adminNote').value);
  fd.append(P_CSRF_KEY, P_CSRF_VAL);
  fetch(P_URL, { method: 'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.success) {
        showToast(d.message, 'success');
        closeModal('processModal');
        setTimeout(function(){ location.reload(); }, 1000);
      } else {
        showToast(d.error || 'Error processing request', 'error');
      }
    }).catch(function(){ showToast('Cannot reach server. Check XAMPP is running.', 'error'); });
}
";
?>
