<?php $title = 'Vehicles & RFID'; ?>

<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">Vehicles &amp; RFID Management</h2>
    <p class="text-muted text-sm">Register, manage, and deregister vehicles and RFID cards</p>
  </div>
  <button class="btn btn-primary" onclick="openVehicleModal()">+ Register Vehicle</button>
</div>

<div class="filter-bar">
  <form method="GET" action="<?= url('admin/vehicles') ?>" class="d-flex gap-8 flex-wrap" style="width:100%">
    <input type="text" name="search" class="form-control" placeholder="Search plate, owner..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px">
    <select name="type" class="form-control" style="width:140px">
      <option value="">All Types</option>
      <?php foreach (array('motorcycle','car','suv','truck','bus') as $vt): ?>
      <option value="<?= $vt ?>" <?= $type===$vt?'selected':'' ?>><?= ucfirst($vt) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Filter</button>
    <a href="<?= url('admin/vehicles') ?>" class="btn btn-ghost">Reset</a>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Vehicles (<?= number_format($total) ?>)</span>
    <button class="btn btn-ghost btn-sm" onclick="exportTable('vehiclesTable','vehicles')"><i class="fa-solid fa-download"></i> Export CSV</button>
  </div>
  <div class="table-responsive">
    <table class="data-table" id="vehiclesTable">
      <thead>
        <tr><th>#</th><th>Plate</th><th>Owner</th><th>Type</th><th>Details</th><th>RFID Tag</th><th>Status</th><th>Registered</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($vehicles as $v): ?>
        <tr id="vehicle-row-<?= $v['id'] ?>">
          <td class="mono"><?= $v['id'] ?></td>
          <td><span class="mono" style="color:var(--text-primary);font-weight:700"><?= htmlspecialchars($v['plate_number']) ?></span></td>
          <td>
            <div><?= htmlspecialchars($v['full_name']) ?></div>
            <div class="text-xs text-muted">@<?= htmlspecialchars($v['username']) ?></div>
          </td>
          <td><span class="badge badge-info"><?= strtoupper($v['vehicle_type']) ?></span></td>
          <td class="text-muted text-xs"><?= htmlspecialchars(trim($v['year'].' '.$v['make'].' '.$v['model'].' '.$v['color'])) ?></td>
          <td>
            <?php if ($v['rfid_tag']): ?>
            <span class="mono text-xs text-cyan"><?= htmlspecialchars($v['rfid_tag']) ?></span>
            <?php else: ?><span class="text-muted text-xs">Not assigned</span><?php endif; ?>
          </td>
          <td><span class="badge badge-<?= $v['status']==='active'?'success':'danger' ?>"><?= strtoupper($v['status']) ?></span></td>
          <td class="text-muted text-xs"><?= date('M d Y', strtotime($v['registered_at'])) ?></td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <button class="btn btn-outline btn-xs" onclick="editVehicle(<?= htmlspecialchars(json_encode(array(
              'id'           => $v['id'],
              'user_id'      => $v['user_id'],
              'plate_number' => $v['plate_number'],
              'vehicle_type' => $v['vehicle_type'],
              'make'         => $v['make'],
              'model'        => $v['model'],
              'year'         => $v['year'],
              'color'        => $v['color'],
              'status'       => $v['status'],
              'rfid_tag'     => $v['rfid_tag'],
            )), ENT_QUOTES) ?>)">Edit</button>
            <button class="btn btn-danger btn-xs" onclick="confirmDeregister(<?= $v['id'] ?>, '<?= htmlspecialchars(addslashes($v['plate_number'])) ?>', '<?= htmlspecialchars(addslashes($v['full_name'])) ?>')">Deregister</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($vehicles)): ?>
        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">No vehicles found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex justify-between align-center">
    <span class="text-muted text-sm"><?= count($vehicles) ?> of <?= $total ?></span>
    <?php $pages = ceil($total/$limit); if ($pages>1): ?>
    <div class="pagination">
      <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= $type ?>" class="page-link <?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>


<!-- ═══════════════════════════════════════════════
     VEHICLE REGISTER / EDIT MODAL
════════════════════════════════════════════════ -->
<div class="modal-overlay" id="vehicleModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="vehicleModalTitle">Register Vehicle</span>
      <button class="modal-close" onclick="closeModal('vehicleModal')">&#x2715;</button>
    </div>
    <div class="modal-body">
      <form id="vehicleForm">
        <input type="hidden" name="id"      id="vehicleId">
        <input type="hidden" name="user_id" id="vehicleUserId">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= Security::csrfToken() ?>">

        <div class="form-group" id="ownerGroup">
          <label class="form-label">Owner *</label>
          <select name="user_id_new" id="vehicleOwnerSelect" class="form-control">
            <option value="">Select owner...</option>
            <?php foreach ($userList as $u): ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (@<?= htmlspecialchars($u['username']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Plate Number *</label>
            <input type="text" name="plate_number" id="vehiclePlate" class="form-control" placeholder="e.g. ABC-1234" required>
          </div>
          <div class="form-group">
            <label class="form-label">Vehicle Type *</label>
            <select name="vehicle_type" id="vehicleType" class="form-control">
              <?php foreach (array('motorcycle','car','suv','truck','bus') as $vt): ?>
              <option value="<?= $vt ?>"><?= ucfirst($vt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row cols-3">
          <div class="form-group">
            <label class="form-label">Make</label>
            <input type="text" name="make" id="vehicleMake" class="form-control" placeholder="Toyota">
          </div>
          <div class="form-group">
            <label class="form-label">Model</label>
            <input type="text" name="model" id="vehicleModel" class="form-control" placeholder="Vios">
          </div>
          <div class="form-group">
            <label class="form-label">Year</label>
            <input type="number" name="year" id="vehicleYear" class="form-control" placeholder="2024">
          </div>
        </div>
        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Color</label>
            <input type="text" name="color" id="vehicleColor" class="form-control" placeholder="Silver">
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="vehicleStatus" class="form-control">
              <option value="active">Active</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">RFID Tag (UID)</label>
          <input type="text" name="rfid_tag" id="vehicleRfid" class="form-control" placeholder="e.g. AA:BB:CC:DD">
          <div class="form-hint">Enter the RFID card UID in uppercase with colons (AA:BB:CC:DD)</div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('vehicleModal')">Cancel</button>
      <button class="btn btn-primary" id="saveVehicleBtn" onclick="saveVehicle()">Save Vehicle</button>
    </div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════
     DEREGISTER CONFIRMATION MODAL
════════════════════════════════════════════════ -->
<div class="modal-overlay" id="deregisterModal">
  <div class="modal" style="max-width:460px">
    <div class="modal-header" style="border-bottom:1px solid var(--border-color)">
      <span class="modal-title" style="color:var(--danger,#e74c3c)">&#9888; Deregister Vehicle</span>
      <button class="modal-close" onclick="closeModal('deregisterModal')">&#x2715;</button>
    </div>
    <div class="modal-body">

      <!-- Warning banner -->
      <div style="background:rgba(231,76,60,0.08);border:1px solid rgba(231,76,60,0.3);border-radius:8px;padding:14px 16px;margin-bottom:18px">
        <p style="margin:0 0 6px;font-weight:600;color:var(--danger,#e74c3c)">This action is permanent and cannot be undone.</p>
        <p style="margin:0;font-size:13px;color:var(--text-muted)">All records linked to this vehicle will be permanently deleted from the database:</p>
        <ul style="margin:8px 0 0;padding-left:18px;font-size:13px;color:var(--text-muted);line-height:1.8">
          <li>Vehicle registration details</li>
          <li>RFID tag assignment</li>
          <li>All toll transaction history</li>
          <li>All top-up / wallet records tied to this vehicle</li>
          <li>All access / activity logs</li>
        </ul>
      </div>

      <!-- Vehicle summary -->
      <div style="background:var(--bg-secondary,#1e2533);border-radius:8px;padding:14px 16px;margin-bottom:18px">
        <div class="d-flex justify-between text-sm" style="margin-bottom:6px">
          <span class="text-muted">Vehicle ID</span>
          <span class="mono" id="dr_id" style="font-weight:700"></span>
        </div>
        <div class="d-flex justify-between text-sm" style="margin-bottom:6px">
          <span class="text-muted">Plate Number</span>
          <span class="mono" id="dr_plate" style="font-weight:700;color:var(--text-primary)"></span>
        </div>
        <div class="d-flex justify-between text-sm">
          <span class="text-muted">Registered Owner</span>
          <span id="dr_owner"></span>
        </div>
      </div>

      <!-- Type-to-confirm -->
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label" style="font-size:13px">
          Type <strong id="dr_plate_confirm" style="color:var(--danger,#e74c3c)"></strong> to confirm deletion:
        </label>
        <input type="text" id="deregisterConfirmInput" class="form-control" placeholder="Type plate number here..." oninput="checkDeregisterInput()">
        <div class="form-hint" style="color:var(--danger,#e74c3c);display:none" id="deregisterMismatch">Plate number does not match.</div>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('deregisterModal')">Cancel</button>
      <button class="btn btn-danger" id="confirmDeregisterBtn" disabled onclick="executeDeregister()">
        <i class="fa-solid fa-trash"></i> Permanently Delete
      </button>
    </div>
  </div>
</div>


<?php
$saveUrl      = url('admin/vehicles/save');
$deleteUrl    = url('admin/vehicles/delete');
$csrfName     = CSRF_TOKEN_NAME;
$csrfVal      = Security::csrfToken();
$pageScript   = "
var V_URL         = '" . addslashes($saveUrl)   . "';
var V_DELETE_URL  = '" . addslashes($deleteUrl)  . "';
var V_CSRF_KEY    = '" . addslashes($csrfName)   . "';
var V_CSRF_VAL    = '" . addslashes($csrfVal)    . "';
var isEditing     = false;

/* ── Shared error helpers ─────────────────────── */
function showVehicleError(msg) {
  var el = document.getElementById('vehicleFormError');
  if (!el) {
    el = document.createElement('div');
    el.id        = 'vehicleFormError';
    el.className = 'alert alert-danger';
    el.style.marginBottom = '14px';
    var form = document.getElementById('vehicleForm');
    form.insertBefore(el, form.firstChild);
  }
  el.style.display = 'block';
  el.textContent   = msg;
}
function clearVehicleError() {
  var el = document.getElementById('vehicleFormError');
  if (el) el.style.display = 'none';
}

/* ── Register modal ───────────────────────────── */
function openVehicleModal() {
  isEditing = false;
  clearVehicleError();
  document.getElementById('vehicleId').value     = '';
  document.getElementById('vehicleUserId').value = '';
  document.getElementById('vehicleForm').reset();
  document.getElementById('ownerGroup').style.display      = 'block';
  document.getElementById('vehicleOwnerSelect').value      = '';
  document.getElementById('vehicleModalTitle').textContent = 'Register Vehicle';
  document.getElementById('saveVehicleBtn').textContent    = 'Register Vehicle';
  openModal('vehicleModal');
}

/* ── Edit modal ───────────────────────────────── */
function editVehicle(v) {
  isEditing = true;
  clearVehicleError();
  document.getElementById('vehicleId').value     = v.id;
  document.getElementById('vehicleUserId').value = v.user_id;
  document.getElementById('vehiclePlate').value  = v.plate_number  || '';
  document.getElementById('vehicleType').value   = v.vehicle_type  || 'car';
  document.getElementById('vehicleMake').value   = v.make          || '';
  document.getElementById('vehicleModel').value  = v.model         || '';
  document.getElementById('vehicleYear').value   = v.year          || '';
  document.getElementById('vehicleColor').value  = v.color         || '';
  document.getElementById('vehicleStatus').value = v.status        || 'active';
  document.getElementById('vehicleRfid').value   = v.rfid_tag      || '';
  document.getElementById('ownerGroup').style.display      = 'none';
  document.getElementById('vehicleModalTitle').textContent = 'Edit Vehicle';
  document.getElementById('saveVehicleBtn').textContent    = 'Save Changes';
  openModal('vehicleModal');
}

/* ── Save (register / edit) ───────────────────── */
function saveVehicle() {
  clearVehicleError();
  var data = new FormData(document.getElementById('vehicleForm'));
  data.set(V_CSRF_KEY, V_CSRF_VAL);

  if (!isEditing) {
    var ownerSel = document.getElementById('vehicleOwnerSelect');
    if (!ownerSel.value) { showVehicleError('Please select an owner'); return; }
    data.set('user_id', ownerSel.value);
    data.delete('user_id_new');
  } else {
    data.delete('user_id_new');
  }

  var plate = document.getElementById('vehiclePlate').value.trim();
  if (!plate) { showVehicleError('Plate number is required'); return; }

  var btn = document.getElementById('saveVehicleBtn');
  btn.disabled    = true;
  btn.textContent = 'Saving...';

  safeFetch(
    V_URL,
    { method: 'POST', body: data },
    function(d) {
      btn.disabled    = false;
      btn.textContent = isEditing ? 'Save Changes' : 'Register Vehicle';
      showToast(d.message, 'success');
      closeModal('vehicleModal');
      setTimeout(function(){ location.reload(); }, 1000);
    },
    function(msg) {
      btn.disabled    = false;
      btn.textContent = isEditing ? 'Save Changes' : 'Register Vehicle';
      showVehicleError(msg);
      showToast(msg, 'error');
    }
  );
}

/* ── Deregister ───────────────────────────────── */
var _dr_id    = null;
var _dr_plate = '';

function confirmDeregister(id, plate, owner) {
  _dr_id    = id;
  _dr_plate = plate;

  document.getElementById('dr_id').textContent            = '#' + id;
  document.getElementById('dr_plate').textContent         = plate;
  document.getElementById('dr_owner').textContent         = owner;
  document.getElementById('dr_plate_confirm').textContent = plate;
  document.getElementById('deregisterConfirmInput').value = '';
  document.getElementById('deregisterMismatch').style.display  = 'none';
  document.getElementById('confirmDeregisterBtn').disabled     = true;
  openModal('deregisterModal');
}

function checkDeregisterInput() {
  var val     = document.getElementById('deregisterConfirmInput').value.trim();
  var matched = (val === _dr_plate);
  document.getElementById('confirmDeregisterBtn').disabled         = !matched;
  document.getElementById('deregisterMismatch').style.display      = (!matched && val.length > 0) ? 'block' : 'none';
}

function executeDeregister() {
  var btn = document.getElementById('confirmDeregisterBtn');
  btn.disabled    = true;
  btn.textContent = 'Deleting...';

  var data = new FormData();
  data.append('vehicle_id',    _dr_id);
  data.append(V_CSRF_KEY,      V_CSRF_VAL);

  safeFetch(
    V_DELETE_URL,
    { method: 'POST', body: data },
    function(d) {
      showToast(d.message || 'Vehicle permanently deleted.', 'success');
      closeModal('deregisterModal');
      /* Remove the row from the DOM immediately */
      var row = document.getElementById('vehicle-row-' + _dr_id);
      if (row) {
        row.style.transition = 'opacity 0.3s';
        row.style.opacity    = '0';
        setTimeout(function(){ row.remove(); }, 320);
      }
      /* Reload after brief delay to refresh counters */
      setTimeout(function(){ location.reload(); }, 1200);
    },
    function(msg) {
      btn.disabled    = false;
      btn.innerHTML   = '<i class=\"fa-solid fa-trash\"></i> Permanently Delete';
      showToast(msg, 'error');
    }
  );
}
";
?>