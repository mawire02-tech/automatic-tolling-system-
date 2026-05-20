<?php $title = 'Wallet & Top-Up'; ?>

<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">Wallet &amp; Top-Up</h2>
    <p class="text-muted text-sm">Manage your toll wallet balance</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('topupModal')">+ Request Top-Up</button>
</div>

<div class="wallet-balance-card mb-24">
  <div class="text-muted text-xs mono" style="margin-bottom:8px;letter-spacing:1px">CURRENT BALANCE</div>
  <div class="wallet-amount">$<?= number_format($user['wallet_balance'],2) ?></div>
  <div class="text-muted text-sm" style="margin-top:8px">
    <?= htmlspecialchars($user['full_name']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($user['email']) ?>
  </div>
  <?php if ((float)$user['wallet_balance'] < $threshold): ?>
  <div style="margin-top:12px;background:rgba(255,179,0,.1);border:1px solid rgba(255,179,0,.3);border-radius:var(--radius);padding:8px 12px;font-size:12px;color:var(--accent-amber)">
    <i class="fa-solid fa-triangle-exclamation"></i> Balance is below minimum threshold of $<?= number_format($threshold,2) ?>. Top up to avoid denied access.
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header">
    <span style="color:var(--accent-amber)"><i class="fa-solid fa-clipboard-list"></i></span>
    <span class="card-title">Top-Up History</span>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Reference</th><th>Amount</th><th>Method</th><th>Ext. Ref</th><th>Status</th><th>Note</th><th>Requested</th><th>Processed</th></tr>
      </thead>
      <tbody>
        <?php foreach ($topups as $t): ?>
        <tr>
          <td class="mono text-xs text-cyan"><?= htmlspecialchars($t['request_ref']) ?></td>
          <td class="text-green mono">$<?= number_format($t['amount'],2) ?></td>
          <td><?= (array('ecocash'=>'EcoCash','onemoney'=>'OneMoney','bank'=>'Bank Transfer','cash'=>'Cash','bank_transfer'=>'Bank Transfer','gcash'=>'GCash','maya'=>'Maya','card'=>'Card')[$t['payment_method']] ?? ucfirst($t['payment_method'])) ?></td>
          <td class="text-muted text-xs"><?= htmlspecialchars($t['reference_number']?:'—') ?></td>
          <td><span class="badge badge-<?= $t['status']==='approved'?'success':($t['status']==='rejected'?'danger':'warning') ?>"><?= strtoupper($t['status']) ?></span></td>
          <td class="text-muted text-xs"><?= htmlspecialchars($t['admin_note']?:'—') ?></td>
          <td class="text-muted text-xs"><?= date('M d Y H:i',strtotime($t['requested_at'])) ?></td>
          <td class="text-muted text-xs"><?= $t['processed_at'] ? date('M d Y H:i',strtotime($t['processed_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($topups)): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">No top-up requests yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- TOP-UP MODAL -->
<div class="modal-overlay" id="topupModal">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <span class="modal-title"><i class="fa-solid fa-wallet"></i> Request Top-Up</span>
      <button class="modal-close" onclick="closeModal('topupModal')">&#x2715;</button>
    </div>
    <div class="modal-body">
      <div class="alert alert-info" style="margin-bottom:16px">Top-up requests are reviewed and approved by an administrator.</div>
      <form id="topupForm">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= Security::csrfToken() ?>">
        <div class="form-group">
          <label class="form-label">Amount ($) *</label>
          <input type="number" name="amount" class="form-control" required min="50" step="0.01" placeholder="e.g. 500.00">
        </div>
        <div class="form-group">
          <label class="form-label">Payment Method *</label>
          <select name="payment_method" class="form-control" required>
            <option value="">Select method...</option>
            <option value="ecocash">EcoCash</option>
            <option value="onemoney">OneMoney</option>
            <option value="bank">Bank Transfer</option>
            <option value="cash">Cash (Walk-in)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Reference / Receipt Number</label>
          <input type="text" name="reference_number" class="form-control" placeholder="Transaction reference (if applicable)">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('topupModal')">Cancel</button>
      <button class="btn btn-primary" onclick="submitTopup()">Submit Request</button>
    </div>
  </div>
</div>

<?php
$topupUrl = url('user/topup/request');
$csrfName = CSRF_TOKEN_NAME;
$csrfVal  = Security::csrfToken();
$pageScript = "
var T_URL      = '" . addslashes($topupUrl) . "';
var T_CSRF_KEY = '" . addslashes($csrfName) . "';
var T_CSRF_VAL = '" . addslashes($csrfVal) . "';

function submitTopup() {
  var form = document.getElementById('topupForm');
  var data = new FormData(form);
  data.set(T_CSRF_KEY, T_CSRF_VAL);
  fetch(T_URL, { method: 'POST', body: data })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.success) {
        showToast(d.message, 'success');
        closeModal('topupModal');
        setTimeout(function(){ location.reload(); }, 1500);
      } else {
        showToast(d.error || 'Error submitting request', 'error');
      }
    }).catch(function(){ showToast('Cannot reach server. Check XAMPP is running.', 'error'); });
}
";
?>
