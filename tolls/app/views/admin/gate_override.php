<?php $title = 'Gate Override'; ?>

<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px"><i class="fa-solid fa-sliders"></i> Manual Gate Override</h2>
    <p class="text-muted text-sm">Send commands to ESP32 toll barriers. Only available for <strong style="color:var(--accent-green)">online</strong> gates.</p>
  </div>
  <div class="d-flex gap-8">
    <div class="live-indicator"><span class="live-dot"></span> Auto-refresh 10s</div>
  </div>
</div>

<!-- OFFLINE WARNING BANNER (hidden by default, shown via JS) -->
<div id="offlineBanner" class="alert alert-danger" style="display:none;margin-bottom:16px">
  <strong><i class="fa-solid fa-triangle-exclamation"></i> Gate Offline:</strong> <span id="offlineBannerMsg"></span>
</div>

<!-- GATE STATUS GRID -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px;margin-bottom:24px" id="gateGrid">
  <?php foreach ($devices as $d):
    $isOnline = $d['status'] === 'online';
    $barrierIcon = $d['barrier_status'] === 'open' ? '<i class="fa-solid fa-circle" style="color:#00ff9d"></i>' : ($d['barrier_status'] === 'closed' ? '<i class="fa-solid fa-circle" style="color:#ff3d5a"></i>' : '<i class="fa-solid fa-circle" style="color:#555"></i>');
    $since = $d['last_heartbeat'] ? date('H:i:s', strtotime($d['last_heartbeat'])) : 'Never';
  ?>
  <div class="card gate-card" id="gate-card-<?= $d['id'] ?>" data-device-id="<?= $d['id'] ?>" data-status="<?= $d['status'] ?>">
    <!-- Gate Header -->
    <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div class="d-flex align-center gap-8">
        <span class="status-dot <?= $d['status'] ?>"></span>
        <div>
          <div style="font-weight:700;color:var(--text-primary);font-size:14px"><?= htmlspecialchars($d['device_name']) ?></div>
          <div class="mono text-xs text-muted"><?= htmlspecialchars($d['device_code']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($d['location']) ?></div>
        </div>
      </div>
      <span class="badge badge-<?= $isOnline ? 'success' : ($d['status']==='maintenance'?'warning':'danger') ?>" id="gate-status-badge-<?= $d['id'] ?>">
        <?= strtoupper($d['status']) ?>
      </span>
    </div>

    <!-- Gate Info Row -->
    <div style="padding:12px 16px;background:var(--bg-panel);display:grid;grid-template-columns:repeat(4,1fr);gap:8px;font-size:11px;border-bottom:1px solid var(--border)">
      <div>
        <div class="text-muted" style="font-size:9px;margin-bottom:2px">BARRIER</div>
        <div class="mono" id="gate-barrier-<?= $d['id'] ?>"><?= $barrierIcon ?> <?= strtoupper($d['barrier_status'] ?? 'UNK') ?></div>
      </div>
      <div>
        <div class="text-muted" style="font-size:9px;margin-bottom:2px">FIRMWARE</div>
        <div class="mono">v<?= htmlspecialchars($d['firmware_version']) ?></div>
      </div>
      <div>
        <div class="text-muted" style="font-size:9px;margin-bottom:2px">IP</div>
        <div class="mono"><?= htmlspecialchars($d['ip_address'] ?: 'N/A') ?></div>
      </div>
      <div>
        <div class="text-muted" style="font-size:9px;margin-bottom:2px">LAST HB</div>
        <div class="mono" id="gate-hb-<?= $d['id'] ?>"><?= $since ?></div>
      </div>
    </div>

    <!-- OFFLINE OVERLAY -->
    <?php if (!$isOnline): ?>
    <div id="gate-offline-overlay-<?= $d['id'] ?>" style="background:rgba(255,61,90,0.06);border:1px solid rgba(255,61,90,0.2);border-radius:6px;margin:12px 16px;padding:10px 14px;display:flex;align-items:center;gap:8px">
      <span style="font-size:18px"><i class="fa-solid fa-power-off"></i></span>
      <div>
        <div style="font-size:12px;font-weight:600;color:var(--accent-red)">Gate Offline</div>
        <div class="text-xs text-muted">Commands are disabled. Gate must be online to accept commands.</div>
      </div>
    </div>
    <?php else: ?>
    <div id="gate-offline-overlay-<?= $d['id'] ?>" style="display:none"></div>
    <?php endif; ?>

    <!-- COMMAND BUTTONS -->
    <div style="padding:14px 16px">
      <div style="font-family:var(--font-mono);font-size:9px;color:var(--text-muted);letter-spacing:1.5px;margin-bottom:10px">BARRIER CONTROL</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px">
        <button class="btn btn-success cmd-btn" data-cmd="open_gate" data-device="<?= $d['id'] ?>" <?= !$isOnline ? 'disabled' : '' ?>>
          <i class="fa-solid fa-lock-open"></i> Open Gate
        </button>
        <button class="btn btn-danger cmd-btn" data-cmd="close_gate" data-device="<?= $d['id'] ?>" <?= !$isOnline ? 'disabled' : '' ?>>
          <i class="fa-solid fa-lock"></i> Close Gate
        </button>
      </div>

      <div style="font-family:var(--font-mono);font-size:9px;color:var(--text-muted);letter-spacing:1.5px;margin-bottom:10px">DIAGNOSTICS</div>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px">
        <button class="btn btn-outline btn-sm cmd-btn" data-cmd="test_led_green" data-device="<?= $d['id'] ?>" <?= !$isOnline ? 'disabled' : '' ?> title="Flash green LED 3×">
          <i class="fa-solid fa-circle" style="color:#00ff9d"></i> LED
        </button>
        <button class="btn btn-outline btn-sm cmd-btn" data-cmd="test_led_red" data-device="<?= $d['id'] ?>" <?= !$isOnline ? 'disabled' : '' ?> title="Flash red LED 3×">
          <i class="fa-solid fa-circle" style="color:#ff3d5a"></i> LED
        </button>
        <button class="btn btn-outline btn-sm cmd-btn" data-cmd="test_buzzer" data-device="<?= $d['id'] ?>" <?= !$isOnline ? 'disabled' : '' ?> title="Trigger buzzer 3 beeps">
          <i class="fa-solid fa-volume-high"></i> Buzz
        </button>
        <button class="btn btn-warning btn-sm cmd-btn" data-cmd="reboot" data-device="<?= $d['id'] ?>" <?= !$isOnline ? 'disabled' : '' ?> title="Reboot ESP32"
          onclick="return confirmReboot(this)">
          <i class="fa-solid fa-arrows-rotate"></i>️ Reboot
        </button>
      </div>
    </div>

    <!-- COMMAND FEEDBACK -->
    <div id="gate-feedback-<?= $d['id'] ?>" style="margin:0 16px 12px;display:none;font-size:12px;padding:8px 12px;border-radius:var(--radius)"></div>
  </div>
  <?php endforeach; ?>

  <?php if (empty($devices)): ?>
  <div class="card" style="grid-column:1/-1">
    <div class="card-body" style="text-align:center;padding:60px;color:var(--text-muted)">
      <div style="font-size:48px;margin-bottom:12px"><i class="fa-solid fa-satellite-dish"></i></div>
      <p>No devices registered. <a href="<?= url('admin/devices') ?>" style="color:var(--accent-cyan)">Add a device &rarr;</a></p>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- COMMAND LOG -->
<div class="card">
  <div class="card-header">
    <span style="color:var(--accent-amber)"><i class="fa-solid fa-clipboard-list"></i></span>
    <span class="card-title">Command History (Last 50)</span>
    <button class="btn btn-ghost btn-sm" onclick="location.reload()">↺ Refresh</button>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Time</th><th>Gate</th><th>Command</th><th>Status</th><th>Result</th><th>Issued By</th><th>Executed</th></tr>
      </thead>
      <tbody>
        <?php foreach ($cmdLog as $cmd):
          $cmdLabels = [
            'open_gate'      => '<i class="fa-solid fa-lock-open"></i> Open Gate',
            'close_gate'     => '<i class="fa-solid fa-lock"></i> Close Gate',
            'test_led_green' => '<i class="fa-solid fa-circle" style="color:#00ff9d"></i> Test LED Green',
            'test_led_red'   => '<i class="fa-solid fa-circle" style="color:#ff3d5a"></i> Test LED Red',
            'test_buzzer'    => '<i class="fa-solid fa-volume-high"></i> Test Buzzer',
            'reboot'         => '<i class="fa-solid fa-arrows-rotate"></i>️ Reboot',
          ];
          $label = $cmdLabels[$cmd['command']] ?? strtoupper($cmd['command']);
        ?>
        <tr>
          <td class="mono text-xs text-muted"><?= date('M d H:i:s', strtotime($cmd['created_at'])) ?></td>
          <td>
            <div style="font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($cmd['device_name'] ?? '—') ?></div>
            <div class="mono text-xs text-muted"><?= htmlspecialchars($cmd['device_code'] ?? '') ?></div>
          </td>
          <td><span style="font-size:13px"><?= $label ?></span></td>
          <td>
            <span class="badge badge-<?= $cmd['status']==='executed'?'success':($cmd['status']==='pending'?'warning':($cmd['status']==='failed'?'danger':'muted')) ?>">
              <?= strtoupper($cmd['status']) ?>
            </span>
          </td>
          <td class="text-muted text-xs"><?= htmlspecialchars($cmd['result'] ?: '—') ?></td>
          <td class="text-muted text-xs"><?= htmlspecialchars($cmd['issued_by_name'] ?? 'System') ?></td>
          <td class="mono text-xs text-muted"><?= $cmd['executed_at'] ? date('H:i:s', strtotime($cmd['executed_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($cmdLog)): ?>
        <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">No commands issued yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>


<?php
$csrf      = Security::csrfToken();
$csrfName  = CSRF_TOKEN_NAME;
$apiUrl    = url('admin/gate-override/command');
$statusUrl = url('admin/api/gate-status');

$pageScript = "
const CSRF     = '" . addslashes($csrf) . "';
const CSRF_KEY = '" . addslashes($csrfName) . "';
const CMD_URL  = '" . addslashes($apiUrl) . "';
const STAT_URL = '" . addslashes($statusUrl) . "';

function confirmReboot(btn) {
  return confirm('Are you sure you want to REBOOT ' + btn.closest('.gate-card').querySelector('[style*=\"font-weight:700\"]').textContent.trim() + '?');
}

document.querySelectorAll('.cmd-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    if (this.disabled) return;
    var deviceId = this.getAttribute('data-device');
    var command  = this.getAttribute('data-cmd');
    var feedback = document.getElementById('gate-feedback-' + deviceId);
    var gateCard = document.getElementById('gate-card-' + deviceId);

    gateCard.querySelectorAll('.cmd-btn').forEach(function(b) { b.disabled = true; });
    feedback.style.display = 'none';

    var fd = new FormData();
    fd.append('device_id', deviceId);
    fd.append('command',   command);
    fd.append(CSRF_KEY,    CSRF);

    fetch(CMD_URL, { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          feedback.style.cssText = 'display:block;background:rgba(0,255,157,.08);border:1px solid rgba(0,255,157,.2);color:var(--accent-green);padding:8px 12px;border-radius:var(--radius);font-size:12px;margin:0 16px 12px';
          feedback.innerHTML = '&#10003; ' + data.message;
          showToast(data.message, 'success');
        } else if (data.error === 'gate_offline') {
          feedback.style.cssText = 'display:block;background:rgba(255,61,90,.08);border:1px solid rgba(255,61,90,.2);color:var(--accent-red);padding:8px 12px;border-radius:var(--radius);font-size:12px;margin:0 16px 12px';
          feedback.innerHTML = 'Gate Offline: ' + data.message;
          showToast('Gate is offline - command not sent', 'error');
        } else {
          feedback.style.cssText = 'display:block;background:rgba(255,61,90,.08);border:1px solid rgba(255,61,90,.2);color:var(--accent-red);padding:8px 12px;border-radius:var(--radius);font-size:12px;margin:0 16px 12px';
          feedback.innerHTML = 'Error: ' + (data.error || data.message || 'Unknown error');
          showToast(data.error || 'Error sending command', 'error');
        }
        var cardStatus = gateCard.getAttribute('data-status');
        if (cardStatus === 'online') {
          gateCard.querySelectorAll('.cmd-btn').forEach(function(b) { b.disabled = false; });
        }
        setTimeout(function() { feedback.style.display = 'none'; }, 6000);
      })
      .catch(function() {
        showToast('Network error - check your connection', 'error');
        if (gateCard.getAttribute('data-status') === 'online') {
          gateCard.querySelectorAll('.cmd-btn').forEach(function(b) { b.disabled = false; });
        }
      });
  });
});

// Gate status polling handled by app.js

";
?>
