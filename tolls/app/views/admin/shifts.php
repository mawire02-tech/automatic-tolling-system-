<?php $title = 'Shift Management'; ?>
<div class="d-flex align-center justify-between mb-20">
  <div><h2 style="font-family:var(--font-mono);font-size:18px"><i class="fa-solid fa-user-clock" style="color:var(--accent-cyan);margin-right:8px"></i>Shift Management</h2>
  <p class="text-muted text-sm">Track operator shifts and booth assignments</p></div>
  <?php if (Security::isAdmin()): ?><button class="btn btn-primary" onclick="openModal('shiftModal')"><i class="fa-solid fa-play"></i> Start Shift</button><?php endif; ?>
</div>

<?php if (!empty($myShift)): ?>
<div class="alert alert-success mb-20">
  <i class="fa-solid fa-circle-check"></i> <strong>Your shift is active</strong> — Started at <?= date('H:i', strtotime($myShift['start_time'])) ?> on <?= htmlspecialchars($myShift['device_name']??'No gate assigned') ?>
  <button class="btn btn-danger btn-sm" style="margin-left:16px" onclick="endMyShift(<?= $myShift['id'] ?>)"><i class="fa-solid fa-stop"></i> End My Shift</button>
</div>
<?php elseif (Security::isOperator()): ?>
<div class="alert alert-warning mb-20">
  <i class="fa-solid fa-circle-info"></i> You don't have an active shift. <button class="btn btn-primary btn-sm" style="margin-left:16px" onclick="openModal('shiftModal')"><i class="fa-solid fa-play"></i> Start Shift</button>
</div>
<?php endif; ?>

<?php if (!empty($activeShifts)): ?>
<div class="card mb-20">
  <div class="card-header"><i class="fa-solid fa-circle" style="color:var(--accent-green)"></i><span class="card-title">Active Shifts (<?= count($activeShifts) ?>)</span></div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Operator</th><th>Gate</th><th>Started</th><th>Duration</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($activeShifts as $s): $dur = floor((time()-strtotime($s['start_time']))/60); ?>
        <tr>
          <td><div style="font-weight:600"><?= htmlspecialchars($s['full_name']) ?></div><div class="text-xs text-muted">@<?= htmlspecialchars($s['username']) ?></div></td>
          <td class="text-muted text-sm"><?= htmlspecialchars($s['device_name']??'Unassigned') ?></td>
          <td class="mono text-xs"><?= date('M d H:i', strtotime($s['start_time'])) ?></td>
          <td><span class="badge badge-success"><?= floor($dur/60) ?>h <?= $dur%60 ?>m</span></td>
          <td><?php if(Security::isAdmin()):?><button class="btn btn-warning btn-xs" onclick="endShift(<?= $s['id'] ?>)"><i class="fa-solid fa-stop"></i> End</button><?php endif;?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><i class="fa-solid fa-clock-rotate-left" style="color:var(--accent-cyan)"></i><span class="card-title">Shift History</span></div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Operator</th><th>Gate</th><th>Start</th><th>End</th><th>Duration</th><th>Notes</th></tr></thead>
      <tbody>
        <?php if(empty($shiftHistory)):?>
        <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">No shift records yet</td></tr>
        <?php else: foreach($shiftHistory as $s): $dm = (int)$s['duration_min']; ?>
        <tr>
          <td style="font-weight:600"><?= htmlspecialchars($s['full_name']) ?></td>
          <td class="text-muted text-xs"><?= htmlspecialchars($s['device_name']??'—') ?></td>
          <td class="mono text-xs"><?= date('M d H:i', strtotime($s['start_time'])) ?></td>
          <td class="mono text-xs"><?= $s['end_time'] ? date('H:i', strtotime($s['end_time'])) : '<span class="badge badge-success">Active</span>' ?></td>
          <td><span class="mono text-xs"><?= floor($dm/60) ?>h <?= $dm%60 ?>m</span></td>
          <td class="text-muted text-xs"><?= htmlspecialchars($s['notes']??'—') ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="shiftModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-header"><span class="modal-title"><i class="fa-solid fa-play"></i> Start Shift</span><button class="modal-close" onclick="closeModal('shiftModal')">&#x2715;</button></div>
    <div class="modal-body">
      <form id="shiftForm">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= Security::csrfToken() ?>">
        <?php if(Security::isAdmin()&&!empty($operators)):?>
        <div class="form-group"><label class="form-label">Operator</label>
          <select name="operator_id" class="form-control">
            <?php foreach($operators as $op):?><option value="<?=$op['id']?>"><?=htmlspecialchars($op['full_name'])?></option><?php endforeach;?>
          </select>
        </div>
        <?php endif;?>
        <div class="form-group"><label class="form-label">Assign Gate</label>
          <select name="device_id" class="form-control">
            <option value="">No specific gate</option>
            <?php foreach($devices as $d):?><option value="<?=$d['id']?>"><?=htmlspecialchars($d['device_name'])?></option><?php endforeach;?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control" placeholder="e.g. Morning shift"></div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('shiftModal')">Cancel</button>
      <button class="btn btn-primary" onclick="startShift()"><i class="fa-solid fa-play"></i> Start Shift</button>
    </div>
  </div>
</div>

<?php
$inUrl  = url('admin/shifts/checkin');
$outUrl = url('admin/shifts/checkout');
$cn = CSRF_TOKEN_NAME; $cv = Security::csrfToken();
$pageScript = "
var SH_IN  = '" . addslashes($inUrl)  . "';
var SH_OUT = '" . addslashes($outUrl) . "';
var SH_CK  = '" . addslashes($cn)     . "';
var SH_CV  = '" . addslashes($cv)     . "';
function startShift() {
  var data = new FormData(document.getElementById('shiftForm')); data.set(SH_CK, SH_CV);
  safeFetch(SH_IN,{method:'POST',body:data},function(d){showToast(d.message,'success');closeModal('shiftModal');setTimeout(function(){location.reload();},900);},function(msg){showToast(msg,'error');});
}
function endShift(id) {
  if(!confirm('End this shift?'))return;
  var fd=new FormData(); fd.append('shift_id',id); fd.append(SH_CK,SH_CV);
  safeFetch(SH_OUT,{method:'POST',body:fd},function(d){showToast(d.message,'success');setTimeout(function(){location.reload();},900);},function(msg){showToast(msg,'error');});
}
function endMyShift(id){ endShift(id); }
";
?>
