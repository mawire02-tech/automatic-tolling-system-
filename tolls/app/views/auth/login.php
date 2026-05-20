<div class="auth-card">
  <div class="auth-logo">
    <div class="auth-logo-icon"><i class="fa-solid fa-traffic-light"></i></div>
    <h1>SmartToll System</h1>
    <p>Automated Toll Collection &amp; Barrier Management</p>
  </div>

  <?php if (!empty($error)):   ?><div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (!empty($success)): ?><div class="alert alert-success">&#10003; <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="POST" action="<?= url('login') ?>">
    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrf ?>">
    <div class="form-group">
      <label class="form-label">Username or Email</label>
      <input type="text" name="username" class="form-control" required autofocus
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username">
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-primary w-100" style="justify-content:center;padding:11px;margin-top:8px">
      <i class="fa-solid fa-key"></i> Sign In
    </button>
  </form>

  <div style="text-align:center;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
    <p class="text-muted text-sm">No account?
      <a href="<?= url('register') ?>" style="color:var(--accent-cyan);text-decoration:none">Register here</a>
    </p>
  </div>

  
</div>
<script>
function fillCreds(u,p){
  document.querySelector('[name=username]').value=u;
  document.querySelector('[name=password]').value=p;
}
</script>
