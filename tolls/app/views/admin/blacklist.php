<?php $title = 'Vehicle Blacklist'; ?>
<div class="d-flex align-center justify-between mb-20">
  <div><h2 style="font-family:var(--font-mono);font-size:18px"><i class="fa-solid fa-ban" style="color:var(--accent-red);margin-right:8px"></i>Vehicle Blacklist</h2>
  <p class="text-muted text-sm">Flagged vehicles are automatically denied at all gates</p></div>
  <button class="btn btn-danger" onclick="openModal('blacklistModal')"><i class="fa-solid fa-plus"></i> Add to Blacklist</button>
</div>
<?php if (!empty($list)): ?>
<div class="alert alert-danger" style="margin-bottom:16px">
  <i class="fa-solid fa-triangle-exclamation"></i> <strong><?= count($list) ?></strong> vehicle(s) are currently blacklisted and will be denied entry at all toll gates.
</div>
<?php endif; ?>
<div class="card">
  <div class="card-header"><i class="fa-solid fa-list" style="color:var(--accent-red)"></i><span class="card-title">Blacklisted Vehicles (<?= count($list) ?>)</span></div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Plate Number</th><th>Reason</th><th>Added By</th><th>Date Added</th><th>Action</th></tr></thead>
      <tbody>
        <?php if (empty($list)): ?>
        <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fa-solid fa-check-circle fa-2x" style="color:var(--accent-green);display:block;margin-bottom:10px"></i>No vehicles are blacklisted</td></tr>
        <?php else: foreach ($list as $b): ?>
        <tr>
          <td><span class="mono" style="color:var(--accent-red);font-weight:700;font-size:14px"><?= htmlspecialchars($b['plate_number']) ?></span></td>
          <td class="text-muted text-sm"><?= htmlspecialchars($b['reason']?:'No reason specified') ?></td>
          <td class="text-muted text-xs"><?= htmlspecialchars($b['added_by_name']??'System') ?></td>
          <td class="text-muted text-xs"><?= date('M d Y H:i', strtotime($b['created_at'])) ?></td>
          <td><button class="btn btn-success btn-xs" onclick="removeBl(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['plate_number'])) ?>')"><i class="fa-solid fa-unlock"></i> Remove</button></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="blacklistModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header"><span class="modal-title"><i class="fa-solid fa-ban"></i> Add to Blacklist</span><button class="modal-close" onclick="closeModal('blacklistModal')">&#x2715;</button></div>
    <div class="modal-body">
      <div class="alert alert-warning" style="margin-bottom:14px;font-size:12px"><i class="fa-solid fa-triangle-exclamation"></i> Blacklisted vehicles will be denied at ALL gates immediately.</div>
      <form id="blForm">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= Security::csrfToken() ?>">
        <div class="form-group"><label class="form-label">Plate Number *</label><input type="text" name="plate_number" class="form-control" placeholder="e.g. ABC-1234" required style="text-transform:uppercase"></div>
        <div class="form-group"><label class="form-label">Reason</label><textarea name="reason" class="form-control" rows="3" placeholder="e.g. Stolen vehicle, Fraud, Outstanding fines..."></textarea></div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('blacklistModal')">Cancel</button>
      <button class="btn btn-danger" onclick="addBl()"><i class="fa-solid fa-ban"></i> Add to Blacklist</button>
    </div>
  </div>
</div>

<?php
$saveUrl   = url('admin/blacklist/save');
$removeUrl = url('admin/blacklist/remove');
$csrfName  = CSRF_TOKEN_NAME;
$csrfVal   = Security::csrfToken();
$pageScript = "
var BL_SAVE   = '" . addslashes($saveUrl)   . "';
var BL_REMOVE = '" . addslashes($removeUrl) . "';
var BL_CSRF_K = '" . addslashes($csrfName)  . "';
var BL_CSRF_V = '" . addslashes($csrfVal)   . "';

function addBl() {
  var data = new FormData(document.getElementById('blForm'));
  var plate = data.get('plate_number').toUpperCase(); data.set('plate_number', plate);
  data.set(BL_CSRF_K, BL_CSRF_V);
  safeFetch(BL_SAVE, {method:'POST',body:data},
    function(d){ showToast(d.message,'success'); closeModal('blacklistModal'); setTimeout(function(){location.reload();},900); },
    function(msg){ showToast(msg,'error'); }
  );
}
function removeBl(id, plate) {
  if (!confirm('Remove ' + plate + ' from blacklist and restore vehicle access?')) return;
  var fd = new FormData(); fd.append('id',id); fd.append(BL_CSRF_K, BL_CSRF_V);
  safeFetch(BL_REMOVE, {method:'POST',body:fd},
    function(d){ showToast(d.message,'success'); setTimeout(function(){location.reload();},900); },
    function(msg){ showToast(msg,'error'); }
  );
}
";
?>
