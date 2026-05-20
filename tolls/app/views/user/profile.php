<?php $title = 'My Profile'; ?>

<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">My Profile</h2>
    <p class="text-muted text-sm">Update your personal information and password</p>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <span style="color:var(--accent-cyan)"><i class="fa-solid fa-user"></i></span>
      <span class="card-title">Personal Information</span>
    </div>
    <div class="card-body">
      <div style="text-align:center;margin-bottom:20px">
        <div class="user-avatar" style="width:72px;height:72px;font-size:28px;margin:0 auto 12px"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
        <div style="font-size:16px;font-weight:700;color:var(--text-primary)"><?= htmlspecialchars($user['full_name']) ?></div>
        <div class="text-muted text-sm">@<?= htmlspecialchars($user['username']) ?></div>
        <span class="badge badge-success" style="margin-top:6px"><?= strtoupper($user['status']) ?></span>
      </div>
      <form id="profileForm">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= Security::csrfToken() ?>">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']??'') ?>">
        </div>
        <button type="button" class="btn btn-primary w-100" onclick="updateProfile()" style="justify-content:center">Save Changes</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span style="color:var(--accent-amber)"><i class="fa-solid fa-lock"></i></span>
      <span class="card-title">Change Password</span>
    </div>
    <div class="card-body">
      <div class="alert alert-info" style="margin-bottom:16px">Leave blank if you don't want to change your password.</div>
      <div class="form-group">
        <label class="form-label">Current Password</label>
        <input type="password" id="currentPass" class="form-control" placeholder="Your current password">
      </div>
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input type="password" id="newPass" class="form-control" placeholder="Min. 8 chars, 1 uppercase, 1 number">
      </div>
      <div class="form-group">
        <label class="form-label">Confirm New Password</label>
        <input type="password" id="confirmPass" class="form-control" placeholder="Repeat new password">
        <div class="form-error" id="pwMatchErr" style="display:none">Passwords do not match.</div>
      </div>
      <button type="button" class="btn btn-warning w-100" onclick="changePassword()" style="justify-content:center">Update Password</button>

      <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--border)">
        <h4 class="text-muted text-xs mono" style="margin-bottom:12px;letter-spacing:1px">ACCOUNT DETAILS</h4>
        <div style="display:grid;gap:8px">
          <div class="d-flex justify-between"><span class="text-muted text-xs">Member since</span><span class="text-xs mono"><?= date('M d, Y',strtotime($user['created_at'])) ?></span></div>
          <div class="d-flex justify-between"><span class="text-muted text-xs">Last login</span><span class="text-xs mono"><?= $user['last_login'] ? date('M d Y H:i',strtotime($user['last_login'])) : 'N/A' ?></span></div>
          <div class="d-flex justify-between"><span class="text-muted text-xs">User ID</span><span class="text-xs mono">#<?= $user['id'] ?></span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$updateUrl = url('user/profile/update');
$csrfName  = CSRF_TOKEN_NAME;
$csrfVal   = Security::csrfToken();
$pageScript = "
var PR_URL      = '" . addslashes($updateUrl) . "';
var PR_CSRF_KEY = '" . addslashes($csrfName) . "';
var PR_CSRF_VAL = '" . addslashes($csrfVal) . "';

function updateProfile() {
  var form = document.getElementById('profileForm');
  var data = new FormData(form);
  data.set(PR_CSRF_KEY, PR_CSRF_VAL);
  fetch(PR_URL, { method: 'POST', body: data })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.success) showToast(d.message, 'success');
      else showToast(d.error || 'Error', 'error');
    }).catch(function(){ showToast('Cannot reach server. Check XAMPP is running.', 'error'); });
}

function changePassword() {
  var np = document.getElementById('newPass').value;
  var cp = document.getElementById('confirmPass').value;
  var err = document.getElementById('pwMatchErr');
  if (!np) { showToast('Please enter a new password', 'warning'); return; }
  if (np !== cp) { err.style.display = 'block'; return; }
  err.style.display = 'none';
  var data = new FormData(document.getElementById('profileForm'));
  data.set(PR_CSRF_KEY, PR_CSRF_VAL);
  data.append('new_password',     np);
  data.append('current_password', document.getElementById('currentPass').value);
  fetch(PR_URL, { method: 'POST', body: data })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.success) {
        showToast(d.message, 'success');
        document.getElementById('currentPass').value = '';
        document.getElementById('newPass').value = '';
        document.getElementById('confirmPass').value = '';
      } else {
        showToast(d.error || 'Error', 'error');
      }
    }).catch(function(){ showToast('Cannot reach server. Check XAMPP is running.', 'error'); });
}
";
?>
