<?php $title = 'Device Management'; ?>

<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">Device Management</h2>
    <p class="text-muted text-sm">Monitor ESP32 toll booths, their status and API credentials</p>
  </div>
  <button class="btn btn-primary" onclick="openDeviceModal()">+ Add Device</button>
</div>

<?php
  $onlineCount = count(array_filter($devices, function($d){ return $d['status']==='online'; }));
  $offlineCount= count(array_filter($devices, function($d){ return $d['status']==='offline'; }));
  $maintCount  = count(array_filter($devices, function($d){ return $d['status']==='maintenance'; }));
  $totalRev    = array_sum(array_column($devices,'total_revenue'));
?>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <div class="stat-card" style="--accent-color:var(--accent-green);--icon-bg:rgba(0,255,157,.1)">
    <div class="stat-icon"><i class="fa-solid fa-satellite-dish"></i></div><div class="stat-body"><div class="stat-value"><?= $onlineCount ?></div><div class="stat-label">Online</div></div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-red);--icon-bg:rgba(255,61,90,.1)">
    <div class="stat-icon"><i class="fa-solid fa-power-off"></i></div><div class="stat-body"><div class="stat-value"><?= $offlineCount ?></div><div class="stat-label">Offline</div></div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-amber);--icon-bg:rgba(255,179,0,.1)">
    <div class="stat-icon"><i class="fa-solid fa-wrench"></i></div><div class="stat-body"><div class="stat-value"><?= $maintCount ?></div><div class="stat-label">Maintenance</div></div>
  </div>
  <div class="stat-card" style="--accent-color:var(--accent-cyan);--icon-bg:rgba(0,212,255,.1)">
    <div class="stat-icon">$</div><div class="stat-body"><div class="stat-value" style="font-size:18px">$<?= number_format($totalRev,2) ?></div><div class="stat-label">Total Revenue</div></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;margin-bottom:24px">
  <?php foreach ($devices as $d): ?>
  <div class="card">
    <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div class="d-flex align-center gap-8">
        <span class="status-dot <?= $d['status'] ?>"></span>
        <div>
          <div style="font-weight:700;color:var(--text-primary)"><?= htmlspecialchars($d['device_name']) ?></div>
          <div class="mono text-xs text-muted"><?= htmlspecialchars($d['device_code']) ?></div>
        </div>
      </div>
      <span class="badge badge-<?= $d['status']==='online'?'success':($d['status']==='maintenance'?'warning':'danger') ?>"><?= strtoupper($d['status']) ?></span>
    </div>
    <div style="padding:12px 16px;display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:11px">
      <div><div class="text-muted" style="font-size:9px">LOCATION</div><div><?= htmlspecialchars($d['location']) ?></div></div>
      <div><div class="text-muted" style="font-size:9px">IP</div><div class="mono"><?= htmlspecialchars($d['ip_address']?:'N/A') ?></div></div>
      <div><div class="text-muted" style="font-size:9px">FIRMWARE</div><div class="mono">v<?= htmlspecialchars($d['firmware_version']) ?></div></div>
      <div><div class="text-muted" style="font-size:9px">BARRIER</div><span class="badge badge-<?= $d['barrier_status']==='open'?'success':'info' ?>"><?= strtoupper($d['barrier_status']??'UNK') ?></span></div>
      <div><div class="text-muted" style="font-size:9px">TRANSACTIONS</div><div class="mono text-cyan"><?= number_format($d['total_transactions']) ?></div></div>
      <div><div class="text-muted" style="font-size:9px">REVENUE</div><div class="mono text-green">$<?= number_format($d['total_revenue'],2) ?></div></div>
    </div>
    <div style="padding:10px 14px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <span class="text-xs text-muted">Last HB: <span class="mono"><?= $d['last_heartbeat'] ? date('H:i:s',strtotime($d['last_heartbeat'])) : 'Never' ?></span></span>
      <div class="d-flex gap-8">
        <button class="btn btn-ghost btn-xs" onclick="showApiKey('<?= htmlspecialchars($d['device_name'], ENT_QUOTES) ?>','<?= htmlspecialchars($d['api_key'], ENT_QUOTES) ?>')">API Key</button>
        <button class="btn btn-outline btn-xs" onclick="editDevice(<?= htmlspecialchars(json_encode($d), ENT_QUOTES) ?>)">Edit</button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header"><span class="card-title">All Devices (<?= count($devices) ?>)</span></div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Code</th><th>Name</th><th>Status</th><th>IP</th><th>FW</th><th>Transactions</th><th>Revenue</th><th>Last Heartbeat</th></tr></thead>
      <tbody>
        <?php foreach ($devices as $d): ?>
        <tr>
          <td class="mono text-xs"><?= htmlspecialchars($d['device_code']) ?></td>
          <td><?= htmlspecialchars($d['device_name']) ?></td>
          <td><span class="badge badge-<?= $d['status']==='online'?'success':($d['status']==='maintenance'?'warning':'danger') ?>"><?= strtoupper($d['status']) ?></span></td>
          <td class="mono text-xs"><?= htmlspecialchars($d['ip_address']?:'N/A') ?></td>
          <td class="mono text-xs">v<?= htmlspecialchars($d['firmware_version']) ?></td>
          <td class="text-cyan mono"><?= number_format($d['total_transactions']) ?></td>
          <td class="text-green mono">$<?= number_format($d['total_revenue'],2) ?></td>
          <td class="text-muted text-xs"><?= $d['last_heartbeat'] ? date('M d H:i',strtotime($d['last_heartbeat'])) : 'Never' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Device Modal -->
<div class="modal-overlay" id="deviceModal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <span class="modal-title" id="deviceModalTitle">Add Device</span>
      <button class="modal-close" onclick="closeModal('deviceModal')">&#x2715;</button>
    </div>
    <div class="modal-body">
      <form id="deviceForm">
        <input type="hidden" name="id" id="deviceId">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= Security::csrfToken() ?>">
        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Device Code *</label>
            <input type="text" name="device_code" id="dCode" class="form-control" placeholder="BOOTH-004" required>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="dStatus" class="form-control">
              <option value="offline">Offline</option>
              <option value="online">Online</option>
              <option value="maintenance">Maintenance</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Device Name *</label>
          <input type="text" name="device_name" id="dName" class="form-control" placeholder="Main Entrance Booth" required>
        </div>
        <div class="form-group">
          <label class="form-label">Location *</label>
          <input type="text" name="location" id="dLocation" class="form-control" placeholder="North Gate - Main Highway" required>
        </div>
        <div id="apiKeyDisplay" style="display:none" class="alert alert-info" style="margin-top:10px">
          API Key will be auto-generated on creation.
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('deviceModal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveDevice()">Save Device</button>
    </div>
  </div>
</div>

<!-- API Key Modal -->
<div class="modal-overlay" id="apiKeyModal">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <span class="modal-title">API Key — <span id="apiDeviceName"></span></span>
      <button class="modal-close" onclick="closeModal('apiKeyModal')">&#x2715;</button>
    </div>
    <div class="modal-body">
      <div class="alert alert-warning" style="margin-bottom:14px">
        Keep this key secure. Use it in the ESP32 firmware as the X-API-Key header.
      </div>
      <div style="background:var(--bg-panel);padding:14px;border-radius:var(--radius);border:1px solid var(--border)">
        <div class="text-xs text-muted" style="margin-bottom:6px">API KEY</div>
        <div class="mono" id="apiKeyValue" style="word-break:break-all;color:var(--accent-cyan);font-size:13px;user-select:all"></div>
      </div>
      <button class="btn btn-outline btn-sm" style="margin-top:10px" onclick="copyText(document.getElementById('apiKeyValue').textContent)">Copy Key</button>
    </div>
  </div>
</div>

<?php
$saveUrl  = url('admin/devices/save');
$csrfName = CSRF_TOKEN_NAME;
$csrfVal  = Security::csrfToken();
$pageScript = "
var D_SAVE_URL = '" . addslashes($saveUrl)  . "';
var D_CSRF_KEY = '" . addslashes($csrfName) . "';
var D_CSRF_VAL = '" . addslashes($csrfVal)  . "';

function showDeviceError(msg) {
  var el = document.getElementById('deviceFormError');
  if (!el) {
    el = document.createElement('div');
    el.id = 'deviceFormError';
    el.className = 'alert alert-danger';
    el.style.marginBottom = '14px';
    var form = document.getElementById('deviceForm');
    form.insertBefore(el, form.firstChild);
  }
  el.style.display = 'block';
  el.textContent = msg;
}
function clearDeviceError() {
  var el = document.getElementById('deviceFormError');
  if (el) el.style.display = 'none';
}

function openDeviceModal() {
  clearDeviceError();
  document.getElementById('deviceId').value  = '';
  document.getElementById('deviceForm').reset();
  document.getElementById('dCode').disabled  = false;
  document.getElementById('deviceModalTitle').textContent = 'Add Device';
  document.getElementById('apiKeyDisplay').style.display  = 'block';
  openModal('deviceModal');
}

function editDevice(d) {
  clearDeviceError();
  document.getElementById('deviceId').value  = d.id;
  document.getElementById('dCode').value     = d.device_code;
  document.getElementById('dCode').disabled  = true;
  document.getElementById('dName').value     = d.device_name;
  document.getElementById('dLocation').value = d.location;
  document.getElementById('dStatus').value   = d.status;
  document.getElementById('apiKeyDisplay').style.display  = 'none';
  document.getElementById('deviceModalTitle').textContent = 'Edit Device';
  openModal('deviceModal');
}

function saveDevice() {
  clearDeviceError();
  var data = new FormData(document.getElementById('deviceForm'));
  var dc   = document.getElementById('dCode');
  if (dc.disabled) data.delete('device_code');
  data.set(D_CSRF_KEY, D_CSRF_VAL);

  var btn = document.querySelector('[onclick=\"saveDevice()\"]');
  if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }

  safeFetch(
    D_SAVE_URL,
    { method: 'POST', body: data },
    function(d) {
      if (btn) { btn.disabled = false; btn.textContent = 'Save Device'; }
      showToast(d.message, 'success');
      closeModal('deviceModal');
      if (d.api_key) {
        setTimeout(function(){
          showApiKey(document.getElementById('dName').value, d.api_key);
        }, 400);
      }
      setTimeout(function(){ location.reload(); }, 3000);
    },
    function(msg) {
      if (btn) { btn.disabled = false; btn.textContent = 'Save Device'; }
      showDeviceError(msg);
      showToast(msg, 'error');
    }
  );
}

function showApiKey(name, key) {
  document.getElementById('apiDeviceName').textContent = name;
  document.getElementById('apiKeyValue').textContent   = key;
  openModal('apiKeyModal');
}
";
?>
