<div class="auth-card" style="max-width:480px">
  <div class="auth-logo">
    <div class="auth-logo-icon"><i class="fa-solid fa-traffic-light"></i></div>
    <h1>Create Account</h1>
    <p>Register to access the SmartToll portal</p>
  </div>

  <?php if (!empty($error)): ?>
  <div class="alert alert-danger">&#9888; <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $field => $msgs): ?>
      <?php foreach ($msgs as $m): ?><div>&#8226; <?= htmlspecialchars($m) ?></div><?php endforeach; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="<?= url('register') ?>">
    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrf ?>">

    <div class="form-group">
      <label class="form-label">I am registering as *</label>
      <select name="role" id="roleSelect" class="form-control" onchange="toggleRoleNote()">
        <option value="user"     <?= (($old['role'] ?? '') === 'user'     ? 'selected' : '') ?>>User (Toll Card Holder)</option>
        <option value="operator" <?= (($old['role'] ?? '') === 'operator' ? 'selected' : '') ?>>Operator (Toll Booth Staff)</option>
      </select>
    </div>

    <div id="operatorNote" class="alert alert-warning" style="display:none;margin-bottom:12px;font-size:12px">
      &#9888; <strong>Operator accounts require admin approval</strong> before you can log in.
      You will be notified once approved.
    </div>

    <div class="form-row cols-2">
      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input type="text" name="full_name" class="form-control" required
               value="<?= htmlspecialchars($old['full_name'] ?? '') ?>" placeholder="John Doe">
      </div>
      <div class="form-group">
        <label class="form-label">Username *</label>
        <input type="text" name="username" class="form-control" required
               value="<?= htmlspecialchars($old['username'] ?? '') ?>" placeholder="john123">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Email Address *</label>
      <input type="email" name="email" class="form-control" required
             value="<?= htmlspecialchars($old['email'] ?? '') ?>" placeholder="john@example.com">
    </div>

    <div class="form-group">
      <label class="form-label">Phone Number</label>
      <input type="text" name="phone" class="form-control"
             value="<?= htmlspecialchars($old['phone'] ?? '') ?>" placeholder="+263 77 123 4567">
    </div>

    <div class="form-row cols-2">
      <div class="form-group">
        <label class="form-label">Password *</label>
        <input type="password" name="password" id="regPass" class="form-control" required placeholder="Min. 8 chars">
      </div>
      <div class="form-group">
        <label class="form-label">Confirm Password *</label>
        <input type="password" id="confirmPass" class="form-control" required placeholder="Repeat password">
        <div class="form-error" id="passError" style="display:none">Passwords do not match</div>
      </div>
    </div>

    <div class="form-hint" style="margin-bottom:12px">
      Password must be at least 8 characters, include 1 uppercase and 1 number.
    </div>

    <button type="submit" id="registerBtn" class="btn btn-primary w-100" style="justify-content:center;padding:11px">
      &#10003; Create Account
    </button>
  </form>

  <div style="text-align:center;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
    <p class="text-muted text-sm">Already have an account?
      <a href="<?= url('login') ?>" style="color:var(--accent-cyan);text-decoration:none">Sign in here</a>
    </p>
  </div>
</div>

<script>
function toggleRoleNote() {
  var role = document.getElementById('roleSelect').value;
  document.getElementById('operatorNote').style.display = (role === 'operator') ? 'block' : 'none';
}
// Run on page load in case of old value
toggleRoleNote();

document.getElementById('registerBtn').addEventListener('click', function(e) {
  var p  = document.getElementById('regPass').value;
  var c  = document.getElementById('confirmPass').value;
  var er = document.getElementById('passError');
  if (p && p !== c) { er.style.display = 'block'; e.preventDefault(); }
  else er.style.display = 'none';
});
</script>
