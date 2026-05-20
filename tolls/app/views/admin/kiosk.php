<?php $title = 'Quick Kiosk — Cash Top-Up'; $cur = Security::currency(); ?>
<div class="d-flex align-center justify-between mb-20">
  <div><h2 style="font-family:var(--font-mono);font-size:18px"><i class="fa-solid fa-cash-register" style="color:var(--accent-green);margin-right:8px"></i>Quick Kiosk</h2>
  <p class="text-muted text-sm">Process walk-in cash top-ups instantly by vehicle plate</p></div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header"><i class="fa-solid fa-money-bill-wave" style="color:var(--accent-green)"></i><span class="card-title">Process Cash Top-Up</span></div>
    <div class="card-body">
      <div class="alert alert-info" style="margin-bottom:20px;font-size:12px">
        <i class="fa-solid fa-circle-info"></i> Top-up is <strong>instant</strong> — balance updates immediately. Provide receipt to customer.
      </div>
      <form id="kioskForm">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= Security::csrfToken() ?>">
        <div class="form-group">
          <label class="form-label" style="font-size:14px"><i class="fa-solid fa-car"></i> Vehicle Plate Number *</label>
          <input type="text" name="plate_number" id="kioskPlate" class="form-control"
                 style="font-size:22px;text-transform:uppercase;text-align:center;letter-spacing:4px;font-weight:700;padding:14px"
                 placeholder="ABC-1234" required autocomplete="off" oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:14px"><i class="fa-solid fa-money-bill"></i> Amount (<?= $cur ?>) *</label>
          <input type="number" name="amount" id="kioskAmount" class="form-control"
                 style="font-size:22px;text-align:center;font-weight:700;padding:14px"
                 placeholder="0.00" min="1" step="0.01" required>
          <div class="d-flex gap-8 flex-wrap" style="margin-top:10px">
            <?php foreach(array(5,10,20,50,100,200) as $qa): ?>
            <button type="button" class="btn btn-ghost btn-sm" onclick="setAmount(<?= $qa ?>)"><?= $cur.number_format($qa,0) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:14px"><i class="fa-solid fa-receipt"></i> Receipt Number *</label>
          <input type="text" name="reference_number" id="kioskRef" class="form-control"
                 style="font-size:16px;text-transform:uppercase"
                 placeholder="Enter receipt/slip number" required autocomplete="off">
          <div class="form-hint"><i class="fa-solid fa-info-circle"></i> Must match the cash receipt given to customer. Unique per transaction.</div>
        </div>
        <button type="button" class="btn btn-primary w-100" id="kioskBtn" onclick="processKioskTopup()" style="padding:16px;font-size:16px;justify-content:center;margin-top:8px">
          <i class="fa-solid fa-bolt"></i> Process Top-Up Now
        </button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><i class="fa-solid fa-clock-rotate-left" style="color:var(--accent-cyan)"></i><span class="card-title">Recent Kiosk Top-Ups</span></div>
    <div style="overflow-y:auto;max-height:500px">
      <?php if (empty($recentTopups)): ?>
      <div style="padding:40px;text-align:center;color:var(--text-muted)"><i class="fa-solid fa-inbox fa-2x" style="opacity:.3;display:block;margin-bottom:10px"></i>No kiosk top-ups yet today</div>
      <?php else: foreach ($recentTopups as $t): ?>
      <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
        <div class="d-flex justify-between align-center">
          <span style="font-weight:700;font-size:14px;color:var(--text-primary)"><?= htmlspecialchars($t['full_name']) ?></span>
          <span class="text-green mono" style="font-weight:700;font-size:16px"><?= $cur.number_format($t['amount'],2) ?></span>
        </div>
        <div class="d-flex justify-between align-center" style="margin-top:4px">
          <span class="text-muted text-xs mono"><?= htmlspecialchars($t['reference_number']) ?></span>
          <span class="text-muted text-xs"><?= date('H:i', strtotime($t['processed_at'])) ?></span>
        </div>
        <div class="text-xs text-muted">Balance: <?= $cur.number_format($t['wallet_balance'],2) ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- Success receipt popup -->
<div class="modal-overlay" id="receiptModal">
  <div class="modal" style="max-width:380px;text-align:center">
    <div class="modal-body" style="padding:32px">
      <i class="fa-solid fa-circle-check fa-4x" style="color:var(--accent-green);margin-bottom:16px;display:block"></i>
      <h3 style="color:var(--accent-green);margin-bottom:8px">Top-Up Successful!</h3>
      <div id="receiptContent" style="background:var(--bg-panel);border-radius:var(--radius);padding:16px;margin:16px 0;text-align:left;font-size:13px;line-height:1.8"></div>
      <button class="btn btn-primary w-100" onclick="closeModal('receiptModal');resetKiosk()" style="justify-content:center"><i class="fa-solid fa-rotate-right"></i> New Transaction</button>
    </div>
  </div>
</div>

<?php
$topupUrl = url('admin/kiosk/topup');
$cn = CSRF_TOKEN_NAME; $cv = Security::csrfToken(); $curSym = addslashes($cur);
$pageScript = "
var KSK_URL='" . addslashes($topupUrl) . "'; var KSK_CK='" . addslashes($cn) . "'; var KSK_CV='" . addslashes($cv) . "';
function setAmount(a){ document.getElementById('kioskAmount').value=a; }
function resetKiosk(){ document.getElementById('kioskForm').reset(); document.getElementById('kioskPlate').focus(); }
function processKioskTopup(){
  var plate=document.getElementById('kioskPlate').value.trim();
  var amount=parseFloat(document.getElementById('kioskAmount').value);
  var ref=document.getElementById('kioskRef').value.trim();
  if(!plate){showToast('Enter plate number','error');return;}
  if(!amount||amount<1){showToast('Enter valid amount','error');return;}
  if(!ref){showToast('Enter receipt number','error');return;}
  var btn=document.getElementById('kioskBtn'); btn.disabled=true; btn.innerHTML='<i class=\"fa-solid fa-spinner fa-spin\"></i> Processing...';
  var fd=new FormData(); fd.append('plate_number',plate); fd.append('amount',amount); fd.append('reference_number',ref); fd.append(KSK_CK,KSK_CV);
  safeFetch(KSK_URL,{method:'POST',body:fd},
    function(d){
      btn.disabled=false; btn.innerHTML='<i class=\"fa-solid fa-bolt\"></i> Process Top-Up Now';
      var r=document.getElementById('receiptContent');
      r.innerHTML='<b>Owner:</b> '+d.owner+'<br><b>Amount:</b> " . $curSym . "'+amount.toFixed(2)+'<br><b>New Balance:</b> " . $curSym . "'+parseFloat(d.new_balance).toFixed(2)+'<br><b>Ref:</b> '+d.ref+'<br><b>Time:</b> '+new Date().toLocaleTimeString();
      openModal('receiptModal');
      setTimeout(function(){location.reload();},8000);
    },
    function(msg){ btn.disabled=false; btn.innerHTML='<i class=\"fa-solid fa-bolt\"></i> Process Top-Up Now'; showToast(msg,'error'); }
  );
}
document.addEventListener('DOMContentLoaded',function(){ var p=document.getElementById('kioskPlate'); if(p) p.focus(); });
";
?>
