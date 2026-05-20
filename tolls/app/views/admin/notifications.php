<?php $title = 'Notifications'; ?>
<div class="d-flex align-center justify-between mb-20">
  <div><h2 style="font-family:var(--font-mono);font-size:18px"><i class="fa-solid fa-bell" style="color:var(--accent-amber);margin-right:8px"></i>Notifications</h2>
  <p class="text-muted text-sm">Send alerts to users &amp; manage notification history</p></div>
  <div class="d-flex gap-8">
    <?php if($unread>0):?><button class="btn btn-ghost" onclick="markAllRead()"><i class="fa-solid fa-check-double"></i> Mark All Read</button><?php endif;?>
    <button class="btn btn-primary" onclick="openModal('notifModal')"><i class="fa-solid fa-paper-plane"></i> Send Notification</button>
  </div>
</div>
<?php if($unread>0):?><div class="alert alert-warning mb-16"><i class="fa-solid fa-bell-ring"></i> <strong><?=$unread?></strong> unread notification(s)</div><?php endif;?>
<div class="card">
  <div class="card-header"><i class="fa-solid fa-inbox" style="color:var(--accent-amber)"></i><span class="card-title">Notification Log (<?=count($notifications)?>)</span></div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Type</th><th>Recipient</th><th>Subject</th><th>Message</th><th>Status</th><th>Sent</th><th></th></tr></thead>
      <tbody>
        <?php if(empty($notifications)):?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fa-solid fa-bell-slash fa-2x" style="opacity:.3;display:block;margin-bottom:10px"></i>No notifications sent yet</td></tr>
        <?php else: foreach($notifications as $n):
          $tc=array('info'=>'info','warning'=>'warning','success'=>'success','danger'=>'danger');
        ?>
        <tr style="<?= !$n['is_read']?'background:rgba(255,179,0,.04)':'' ?>">
          <td><span class="badge badge-<?=$tc[$n['type']]??"info"?>"><i class="fa-solid fa-<?=$n['type']==='warning'?'triangle-exclamation':($n['type']==='success'?'check':'circle-info')?>"></i> <?=strtoupper($n['type'])?></span></td>
          <td class="text-sm"><?=htmlspecialchars($n['full_name']??'All Users')?></td>
          <td style="font-weight:600;font-size:13px"><?=htmlspecialchars($n['subject'])?></td>
          <td class="text-muted text-xs" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($n['message'])?></td>
          <td><span class="badge badge-<?=$n['is_read']?'muted':'warning'?>"><?=$n['is_read']?'READ':'UNREAD'?></span></td>
          <td class="text-muted text-xs mono"><?=date('M d H:i',strtotime($n['created_at']))?></td>
          <td><?php if(!$n['is_read']):?><button class="btn btn-ghost btn-xs" onclick="markRead(<?=$n['id']?>)"><i class="fa-solid fa-check"></i></button><?php endif;?></td>
        </tr>
        <?php endforeach; endif;?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="notifModal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header"><span class="modal-title"><i class="fa-solid fa-paper-plane"></i> Send Notification</span><button class="modal-close" onclick="closeModal('notifModal')">&#x2715;</button></div>
    <div class="modal-body">
      <form id="notifForm">
        <input type="hidden" name="<?=CSRF_TOKEN_NAME?>" value="<?=Security::csrfToken()?>">
        <div class="form-row cols-2">
          <div class="form-group"><label class="form-label">Send To</label>
            <select name="target" class="form-control">
              <option value="all">All Users</option>
              <option value="low_balance">Low Balance Users</option>
              <?php foreach($users as $u):?><option value="<?=$u['id']?>"><?=htmlspecialchars($u['full_name'])?></option><?php endforeach;?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Type</label>
            <select name="type" class="form-control">
              <option value="info">Info</option>
              <option value="warning">Warning</option>
              <option value="success">Success</option>
              <option value="danger">Alert</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label">Subject *</label><input type="text" name="subject" class="form-control" required placeholder="Notification subject"></div>
        <div class="form-group"><label class="form-label">Message *</label><textarea name="message" class="form-control" rows="4" required placeholder="Write your notification message..."></textarea></div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('notifModal')">Cancel</button>
      <button class="btn btn-primary" onclick="sendNotif()"><i class="fa-solid fa-paper-plane"></i> Send</button>
    </div>
  </div>
</div>

<?php
$sendUrl = url('admin/notifications/send');
$markUrl = url('admin/notifications/mark');
$cn=CSRF_TOKEN_NAME; $cv=Security::csrfToken();
$pageScript = "
var N_SEND='" . addslashes($sendUrl) . "'; var N_MARK='" . addslashes($markUrl) . "';
var N_CK='" . addslashes($cn) . "'; var N_CV='" . addslashes($cv) . "';
function sendNotif(){
  var data=new FormData(document.getElementById('notifForm')); data.set(N_CK,N_CV);
  safeFetch(N_SEND,{method:'POST',body:data},function(d){showToast(d.message,'success');closeModal('notifModal');setTimeout(function(){location.reload();},1000);},function(msg){showToast(msg,'error');});
}
function markRead(id){
  var fd=new FormData(); fd.append('id',id); fd.append(N_CK,N_CV);
  safeFetch(N_MARK,{method:'POST',body:fd},function(){location.reload();},function(m){showToast(m,'error');});
}
function markAllRead(){
  var fd=new FormData(); fd.append('id',0); fd.append(N_CK,N_CV);
  safeFetch(N_MARK,{method:'POST',body:fd},function(){showToast('All marked read','success');location.reload();},function(m){showToast(m,'error');});
}
";
?>
