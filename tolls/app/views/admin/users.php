<?php
$title  = 'Users Management';
$limit  = isset($limit)  ? max(1,(int)$limit)  : 20;
$total  = isset($total)  ? (int)$total          : 0;
$page   = isset($page)   ? max(1,(int)$page)    : 1;
$search = $search ?? '';
$role   = $role   ?? '';
$status = $status ?? '';
$users  = $users  ?? array();
$pages  = ($limit > 0 && $total > 0) ? (int)ceil($total / $limit) : 1;

// Load pending operators for approval banner
$pendingOps = Database::getInstance()->fetchAll(
    "SELECT id, full_name, username, email, created_at FROM users WHERE role='operator' AND status='pending' ORDER BY created_at DESC"
);
?>

<?php if (!empty($pendingOps)): ?>
<div class="card mb-20" style="border-color:rgba(255,179,0,.35);border-left:4px solid var(--accent-amber)">
  <div class="card-header" style="background:rgba(255,179,0,.06)">
    <span style="color:var(--accent-amber)">&#9888;</span>
    <span class="card-title">Pending Operator Approvals (<?= count($pendingOps) ?>)</span>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Requested</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($pendingOps as $op): ?>
        <tr id="oprow-<?= $op['id'] ?>">
          <td style="font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($op['full_name']) ?></td>
          <td class="mono text-xs">@<?= htmlspecialchars($op['username']) ?></td>
          <td><?= htmlspecialchars($op['email']) ?></td>
          <td class="text-muted text-xs"><?= date('M d Y H:i', strtotime($op['created_at'])) ?></td>
          <td>
            <div class="d-flex gap-8">
              <button class="btn btn-success btn-xs"
                onclick="processOperator(<?= (int)$op['id'] ?>, 'approve', '<?= htmlspecialchars(addslashes($op['full_name']), ENT_QUOTES) ?>')">
                Approve
              </button>
              <button class="btn btn-danger btn-xs"
                onclick="processOperator(<?= (int)$op['id'] ?>, 'reject', '<?= htmlspecialchars(addslashes($op['full_name']), ENT_QUOTES) ?>')">
                Reject
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">Users Management</h2>
    <p class="text-muted text-sm">Manage all registered users and their accounts</p>
  </div>
  <button class="btn btn-primary" onclick="openUserModal()">+ Add User</button>
</div>

<div class="filter-bar">
  <form method="GET" action="<?= url('admin/users') ?>" class="d-flex gap-8 align-center flex-wrap" style="width:100%">
    <input type="text" name="search" class="form-control" placeholder="Search name, email, username..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px">
    <select name="role" class="form-control" style="width:140px">
      <option value="">All Roles</option>
      <option value="admin"    <?= $role==='admin'    ?'selected':'' ?>>Admin</option>
      <option value="operator" <?= $role==='operator' ?'selected':'' ?>>Operator</option>
      <option value="user"     <?= $role==='user'     ?'selected':'' ?>>User</option>
    </select>
    <select name="status" class="form-control" style="width:140px">
      <option value="">All Status</option>
      <option value="active"    <?= $status==='active'   ?'selected':'' ?>>Active</option>
      <option value="suspended" <?= $status==='suspended'?'selected':'' ?>>Suspended</option>
      <option value="pending"   <?= $status==='pending'  ?'selected':'' ?>>Pending</option>
    </select>
    <button type="submit" class="btn btn-outline">Filter</button>
    <a href="<?= url('admin/users') ?>" class="btn btn-ghost">Reset</a>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Users (<?= number_format($total) ?>)</span>
    <button class="btn btn-ghost btn-sm" onclick="exportTable('usersTable','users')">&#8595; Export CSV</button>
  </div>
  <div class="table-responsive">
    <table class="data-table" id="usersTable">
      <thead>
        <tr><th>#</th><th>User</th><th>Contact</th><th>Role</th><th>Gate</th><th>Status</th><th>Balance</th><th>Last Login</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">No users found</td></tr>
        <?php else: foreach ($users as $u):
          $cur = Security::currency();
        ?>
        <tr>
          <td class="mono"><?= (int)$u['id'] ?></td>
          <td>
            <div class="d-flex align-center gap-8">
              <div class="user-avatar" style="width:30px;height:30px;font-size:11px"><?= strtoupper(substr($u['full_name'],0,1)) ?></div>
              <div>
                <div style="font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($u['full_name']) ?></div>
                <div class="text-xs text-muted mono">@<?= htmlspecialchars($u['username']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <div style="color:var(--accent-amber);font-size:9px"><?= htmlspecialchars($u['email']) ?></div>
            <div class="text-xs text-muted"><?= htmlspecialchars($u['phone'] ?: '—') ?></div>
          </td>
          <td><span class="badge badge-<?= $u['role']==='admin'?'danger':($u['role']==='operator'?'info':'muted') ?>"><?= strtoupper($u['role']) ?></span></td>
          <td class="text-xs text-muted">
            <?php if ($u['role']==='operator' && !empty($u['assigned_device_id'])): ?>
            <span class="badge badge-info" style="font-size:5px"><i class="fa-solid fa-satellite-dish"></i> <?= htmlspecialchars($u['device_name'] ?? 'Gate #'.$u['assigned_device_id']) ?></span>
            <?php elseif ($u['role']==='operator'): ?>
            <span style="color:var(--accent-amber);font-size:10px"><i class="fa-solid fa-triangle-exclamation"></i> Unassigned</span>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-<?= $u['status']==='active'?'success':($u['status']==='pending'?'warning':'danger') ?>"><?= strtoupper($u['status']) ?></span></td>
          <td><span class="text-green mono"><?= $cur . number_format((float)$u['wallet_balance'],2) ?></span></td>
          <td class="text-muted text-xs"><?= $u['last_login'] ? date('M d H:i', strtotime($u['last_login'])) : 'Never' ?></td>
          <td>
            <div class="d-flex gap-8">
              <button class="btn btn-outline btn-xs" onclick="editUser(<?= htmlspecialchars(json_encode(array('id'=>$u['id'],'full_name'=>$u['full_name'],'username'=>$u['username'],'email'=>$u['email'],'phone'=>$u['phone'],'role'=>$u['role'],'status'=>$u['status'])), ENT_QUOTES) ?>)">Edit</button>
              <?php if ($u['role'] !== 'admin'): ?>
              <button class="btn btn-<?= $u['status']==='active'?'danger':'success' ?> btn-xs"
                onclick="toggleUserStatus(<?= (int)$u['id'] ?>, '<?= $u['status'] ?>')">
                <?= $u['status']==='active' ? 'Suspend' : 'Activate' ?>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex justify-between align-center">
    <span class="text-muted text-sm">Showing <?= count($users) ?> of <?= number_format($total) ?></span>
    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role) ?>&status=<?= urlencode($status) ?>"
         class="page-link <?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- USER MODAL -->
<div class="modal-overlay" id="userModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="userModalTitle">Add User</span>
      <button class="modal-close" onclick="closeModal('userModal')">&#x2715;</button>
    </div>
    <div class="modal-body">
      <form id="userForm">
        <input type="hidden" name="id" id="userId">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= Security::csrfToken() ?>">
        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" id="uFullName" class="form-control" required >
          </div>
          <div class="form-group">
            <label class="form-label">Username *</label>
            <input type="text" name="username" id="uUsername" class="form-control">
          </div>
        </div>
        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input type="email" name="email" id="uEmail" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" id="uPhone" class="form-control">
          </div>
        </div>
        <div class="form-row cols-3">
          <div class="form-group">
            <label class="form-label">Role *</label>
            <select name="role" id="uRole" class="form-control" onchange="toggleGateField()">
              <option value="user">User</option>
              <option value="operator">Operator</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="uStatus" class="form-control">
              <option value="active">Active</option>
              <option value="suspended">Suspended</option>
              <option value="pending">Pending</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" id="uPassword" class="form-control" placeholder="Leave blank to keep">
          </div>
        </div>
        <div class="form-group" id="gateAssignGroup" style="display:none">
          <label class="form-label"><i class="fa-solid fa-satellite-dish"></i> Assign Gate (Operator only)</label>
          <select name="assigned_device_id" id="uDeviceId" class="form-control">
            <option value="">No specific gate</option>
            <?php foreach ($devices ?? array() as $dev): ?>
            <option value="<?= $dev['id'] ?>"><?= htmlspecialchars($dev['device_name']) ?> (<?= htmlspecialchars($dev['device_code']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <div class="form-hint">Operator will only see this gate's data on their dashboard.</div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('userModal')">Cancel</button>
      <button class="btn btn-primary" id="saveUserBtn" onclick="saveUser()">Save User</button>
    </div>
  </div>
</div>

<?php
$saveUrl     = url('admin/users/save');
$operatorUrl = url('admin/users/operator');
$csrfName    = CSRF_TOKEN_NAME;
$csrfVal     = Security::csrfToken();

$pageScript = "
var SAVE_URL     = '" . addslashes($saveUrl)     . "';
var OPERATOR_URL = '" . addslashes($operatorUrl) . "';
var CSRF_KEY     = '" . addslashes($csrfName)    . "';
var CSRF_VAL     = '" . addslashes($csrfVal)     . "';

// ── Open modal for new user ──────────────────────────────────
function openUserModal() {
  document.getElementById('userForm').reset();
  document.getElementById('userId').value       = '';
  document.getElementById('uUsername').disabled = false;
  document.getElementById('userModalTitle').textContent = 'Add User';
  document.getElementById('saveUserBtn').textContent    = 'Save User';
  var errEl = document.getElementById('userFormError');
  if (errEl) errEl.style.display = 'none';
  openModal('userModal');
}

// ── Open modal to edit existing user ───────────────────────
function editUser(u) {
  document.getElementById('userId').value       = u.id;
  document.getElementById('uFullName').value    = u.full_name  || '';
  document.getElementById('uUsername').value    = u.username   || '';
  document.getElementById('uUsername').disabled = true;
  document.getElementById('uEmail').value       = u.email      || '';
  document.getElementById('uPhone').value       = u.phone      || '';
  document.getElementById('uRole').value        = u.role       || 'user';
  document.getElementById('uStatus').value      = u.status     || 'active';
  document.getElementById('uPassword').value    = '';
  document.getElementById('userModalTitle').textContent = 'Edit User';
  document.getElementById('saveUserBtn').textContent    = 'Save Changes';
  var errEl = document.getElementById('userFormError');
  if (errEl) errEl.style.display = 'none';
  openModal('userModal');
}

// ── Save user (create or update) ────────────────────────────
function saveUser() {
  var form = document.getElementById('userForm');
  var data = new FormData(form);
  var un   = document.getElementById('uUsername');
  if (un.disabled) data.delete('username');
  data.set(CSRF_KEY, CSRF_VAL);

  var btn = document.getElementById('saveUserBtn');
  btn.disabled    = true;
  btn.textContent = 'Saving...';

  // Show error inline
  function showErr(msg) {
    var el = document.getElementById('userFormError');
    if (!el) {
      el = document.createElement('div');
      el.id = 'userFormError';
      el.className = 'alert alert-danger';
      el.style.marginBottom = '12px';
      form.insertBefore(el, form.firstChild);
    }
    el.textContent   = msg;
    el.style.display = 'block';
  }

  safeFetch(
    SAVE_URL,
    { method: 'POST', body: data },
    function(d) {
      btn.disabled    = false;
      btn.textContent = 'Save User';
      showToast(d.message, 'success');
      closeModal('userModal');
      setTimeout(function(){ location.reload(); }, 900);
    },
    function(msg) {
      btn.disabled    = false;
      btn.textContent = 'Save User';
      showErr(msg);
      showToast(msg, 'error');
    }
  );
}

// ── Toggle user active / suspended ──────────────────────────
function toggleUserStatus(id, current) {
  var newStatus = current === 'active' ? 'suspended' : 'active';
  if (!confirm('Change user status to ' + newStatus + '?')) return;
  var row = document.getElementById('usersTable').querySelector('tr:has([onclick*=\"toggleUserStatus(' + id + '\"])');
  var fd  = new FormData();
  fd.append('id',        id);
  fd.append('status',    newStatus);
  fd.append(CSRF_KEY,    CSRF_VAL);
  // Grab current name + email from row cells
  var cells = document.querySelector('[onclick*=\"toggleUserStatus(' + id + '\"]').closest('tr').cells;
  fd.append('full_name', cells[1].querySelector('[style*=font-weight]') ? cells[1].querySelector('[style*=font-weight]').textContent.trim() : '');
  fd.append('email',     cells[2].firstElementChild ? cells[2].firstElementChild.textContent.trim() : '');
  fd.append('role',      cells[3].querySelector('.badge') ? cells[3].querySelector('.badge').textContent.trim().toLowerCase() : 'user');
  safeFetch(
    SAVE_URL,
    { method: 'POST', body: fd },
    function(){ showToast('User status updated', 'success'); setTimeout(function(){ location.reload(); }, 600); },
    function(msg){ showToast(msg, 'error'); }
  );
}

// ── Approve / Reject pending operator ────────────────────────
function processOperator(id, action, name) {
  var msg = (action === 'approve')
    ? 'Approve operator account for: ' + name + '?'
    : 'Reject operator account for: '  + name + '?';
  if (!confirm(msg)) return;

  var fd = new FormData();
  fd.append('id',     id);
  fd.append('action', action);
  fd.append(CSRF_KEY, CSRF_VAL);

  safeFetch(
    OPERATOR_URL,
    { method: 'POST', body: fd },
    function(d) {
      showToast(d.message, 'success');
      // Hide the row immediately
      var row = document.getElementById('oprow-' + id);
      if (row) row.style.display = 'none';
      setTimeout(function(){ location.reload(); }, 1200);
    },
    function(msg) {
      showToast(msg || 'Error processing operator', 'error');
    }
  );
}
";
?>
