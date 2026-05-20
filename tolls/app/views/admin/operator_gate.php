<?php $title = 'Gate Control'; ?>

<?php if (empty($devices)): ?>
<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">
      <i class="fa-solid fa-sliders" style="color:var(--accent-cyan);margin-right:8px"></i>Gate Control
    </h2>
  </div>
</div>
<div class="card">
  <div class="card-body" style="text-align:center;padding:60px 20px">
    <i class="fa-solid fa-satellite-dish fa-3x" style="color:var(--text-muted);display:block;margin-bottom:16px;opacity:.4"></i>
    <h3 style="color:var(--text-muted);margin-bottom:8px">No Gate Assigned</h3>
    <p class="text-muted text-sm">You have not been assigned to a gate yet.<br>Please contact your administrator.</p>
  </div>
</div>

<?php else: ?>

<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">
      <i class="fa-solid fa-sliders" style="color:var(--accent-cyan);margin-right:8px"></i>Gate Control
    </h2>
    <p class="text-muted text-sm">Your assigned gate — real-time control</p>
  </div>
  <div class="live-indicator"><span class="live-dot"></span> LIVE</div>
</div>

<?php foreach ($devices as $d): ?>
<div class="card mb-16" id="gate-card-<?= $d['id'] ?>" data-status="<?= $d['status'] ?>">

  <!-- Gate Header -->
  <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
    <div class="d-flex align-center gap-12">
      <div style="width:44px;height:44px;background:rgba(0,212,255,.1);border-radius:50%;display:flex;align-items:center;justify-content:center">
        <i class="fa-solid fa-satellite-dish" style="color:var(--accent-cyan);font-size:18px"></i>
      </div>
      <div>
        <div style="font-weight:700;font-size:16px;color:var(--text-primary)"><?= htmlspecialchars($d['device_name']) ?></div>
        <div class="mono text-xs text-muted"><?= htmlspecialchars($d['device_code']) ?> &middot; <?= htmlspecialchars($d['location']??'') ?></div>
      </div>
    </div>
    <div class="d-flex gap-8 align-center">
      <span class="badge badge-<?= $d['status']==='online'?'success':($d['status']==='maintenance'?'warning':'danger') ?>"
            id="gate-status-badge-<?= $d['id'] ?>">
        <?= strtoupper($d['status']) ?>
      </span>
      <span style="font-size:12px;color:var(--text-muted)">
        Barrier: <strong id="gate-barrier-<?= $d['id'] ?>" style="color:<?= ($d['barrier_status']??'closed')==='open'?'var(--accent-green)':'var(--text-muted)' ?>">
          <?= strtoupper($d['barrier_status']??'CLOSED') ?>
        </strong>
      </span>
      <span class="text-xs text-muted">
        HB: <span id="gate-hb-<?= $d['id'] ?>" class="mono">
          <?= $d['last_heartbeat'] ? date('H:i:s', strtotime($d['last_heartbeat'])) : 'Never' ?>
        </span>
      </span>
    </div>
  </div>

  <!-- Offline overlay -->
  <?php if ($d['status'] !== 'online'): ?>
  <div id="gate-offline-overlay-<?= $d['id'] ?>" class="d-flex align-center gap-12"
       style="margin:16px;padding:14px;background:rgba(255,61,90,.06);border:1px solid rgba(255,61,90,.2);border-radius:var(--radius)">
    <i class="fa-solid fa-power-off" style="color:var(--accent-red);font-size:20px"></i>
    <div>
      <div style="font-weight:600;color:var(--accent-red);font-size:13px">Gate Offline</div>
      <div class="text-xs text-muted">Commands are disabled. Gate must be online to receive commands.</div>
    </div>
  </div>
  <?php else: ?>
  <div id="gate-offline-overlay-<?= $d['id'] ?>" style="display:none"></div>
  <?php endif; ?>

  <!-- Feedback -->
  <div id="gate-feedback-<?= $d['id'] ?>" style="display:none;margin:0 16px 4px"></div>

  <!-- Command Buttons -->
  <div style="padding:16px 20px">
    <div class="text-xs text-muted mb-12" style="letter-spacing:.5px">GATE COMMANDS</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px">

      <button class="cmd-btn btn btn-success" data-device="<?= $d['id'] ?>" data-cmd="open_gate"
              <?= $d['status']!=='online'?'disabled':'' ?>>
        <i class="fa-solid fa-lock-open"></i> Open Gate
      </button>

      <button class="cmd-btn btn btn-danger" data-device="<?= $d['id'] ?>" data-cmd="close_gate"
              <?= $d['status']!=='online'?'disabled':'' ?>>
        <i class="fa-solid fa-lock"></i> Close Gate
      </button>

      <button class="cmd-btn btn btn-outline" data-device="<?= $d['id'] ?>" data-cmd="test_led_green"
              <?= $d['status']!=='online'?'disabled':'' ?>>
        <i class="fa-solid fa-circle" style="color:var(--accent-green)"></i> Test Green
      </button>

      <button class="cmd-btn btn btn-outline" data-device="<?= $d['id'] ?>" data-cmd="test_led_red"
              <?= $d['status']!=='online'?'disabled':'' ?>>
        <i class="fa-solid fa-circle" style="color:var(--accent-red)"></i> Test Red
      </button>

      <button class="cmd-btn btn btn-outline" data-device="<?= $d['id'] ?>" data-cmd="test_buzzer"
              <?= $d['status']!=='online'?'disabled':'' ?>>
        <i class="fa-solid fa-volume-high"></i> Test Buzzer
      </button>

      <button class="cmd-btn btn btn-warning" data-device="<?= $d['id'] ?>" data-cmd="reboot"
              <?= $d['status']!=='online'?'disabled':'' ?>
              onclick="return confirm('Reboot this gate? It will be offline for ~30 seconds.')">
        <i class="fa-solid fa-rotate-right"></i> Reboot
      </button>

    </div>
  </div>

  <!-- Today stats (no revenue) -->
  <div style="padding:12px 20px;border-top:1px solid var(--border);display:flex;gap:24px;flex-wrap:wrap">
    <div>
      <div class="text-xs text-muted">Transactions Today</div>
      <div class="mono" style="font-weight:700;font-size:18px;color:var(--accent-cyan)">
        <?= number_format($d['total_transactions']??0) ?>
      </div>
    </div>
    <div>
      <div class="text-xs text-muted">Firmware</div>
      <div class="mono text-xs">v<?= htmlspecialchars($d['firmware_version']??'—') ?></div>
    </div>
    <div>
      <div class="text-xs text-muted">IP Address</div>
      <div class="mono text-xs"><?= htmlspecialchars($d['ip_address']??'Unknown') ?></div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<!-- Command log -->
<?php if (!empty($cmdLog)): ?>
<div class="card">
  <div class="card-header">
    <i class="fa-solid fa-clock-rotate-left" style="color:var(--accent-cyan)"></i>
    <span class="card-title">Recent Commands</span>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Time</th><th>Command</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach (array_slice($cmdLog,0,10) as $cmd): ?>
        <tr>
          <td class="mono text-xs text-muted"><?= date('H:i:s', strtotime($cmd['created_at'])) ?></td>
          <td class="mono text-xs"><?= strtoupper(str_replace('_',' ',$cmd['command'])) ?></td>
          <td><span class="badge badge-<?= $cmd['status']==='executed'?'success':($cmd['status']==='pending'?'warning':'muted') ?>"><?= strtoupper($cmd['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php endif; // end if devices not empty ?>

<?php
$cmdUrl   = url('admin/gate-override/command');
$statUrl  = url('admin/api/gate-status');
$csrfName = CSRF_TOKEN_NAME;
$csrfVal  = Security::csrfToken();
$pageScript = "
var CMD_URL  = '" . addslashes($cmdUrl)   . "';
var STAT_URL = '" . addslashes($statUrl)  . "';
var CSRF_K   = '" . addslashes($csrfName) . "';
var CSRF_V   = '" . addslashes($csrfVal)  . "';

document.querySelectorAll('.cmd-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    if (this.disabled) return;
    var devId   = this.getAttribute('data-device');
    var command = this.getAttribute('data-cmd');
    var fb      = document.getElementById('gate-feedback-' + devId);
    var card    = document.getElementById('gate-card-'    + devId);
    card.querySelectorAll('.cmd-btn').forEach(function(b){ b.disabled=true; });
    if (fb) fb.style.display='none';
    var fd=new FormData();
    fd.append('device_id',devId); fd.append('command',command); fd.append(CSRF_K,CSRF_V);
    safeFetch(CMD_URL, {method:'POST',body:fd},
      function(d){
        if(fb){
          fb.style.cssText='display:block;background:rgba(0,255,157,.08);border:1px solid rgba(0,255,157,.2);color:var(--accent-green);padding:8px 12px;border-radius:var(--radius);font-size:12px;margin:0 16px 8px';
          fb.innerHTML='<i class=\"fa-solid fa-check\"></i> '+d.message;
        }
        showToast(d.message,'success');
        if(card.getAttribute('data-status')==='online') card.querySelectorAll('.cmd-btn').forEach(function(b){b.disabled=false;});
        setTimeout(function(){ if(fb) fb.style.display='none'; },6000);
      },
      function(msg){
        if(fb){
          fb.style.cssText='display:block;background:rgba(255,61,90,.08);border:1px solid rgba(255,61,90,.2);color:var(--accent-red);padding:8px 12px;border-radius:var(--radius);font-size:12px;margin:0 16px 8px';
          fb.innerHTML='<i class=\"fa-solid fa-xmark\"></i> '+msg;
        }
        showToast(msg,'error');
        if(card.getAttribute('data-status')==='online') card.querySelectorAll('.cmd-btn').forEach(function(b){b.disabled=false;});
      }
    );
  });
});

setInterval(function() {
  fetch(STAT_URL)
    .then(function(r){return r.json();})
    .then(function(devices){
      devices.forEach(function(d){
        var card=document.getElementById('gate-card-'+d.id);
        if(!card) return;
        var badge=document.getElementById('gate-status-badge-'+d.id);
        var barrier=document.getElementById('gate-barrier-'+d.id);
        var hb=document.getElementById('gate-hb-'+d.id);
        var overlay=document.getElementById('gate-offline-overlay-'+d.id);
        var isOnline=d.status==='online';
        card.setAttribute('data-status',d.status);
        if(badge){ badge.textContent=d.status.toUpperCase(); badge.className='badge badge-'+(isOnline?'success':(d.status==='maintenance'?'warning':'danger')); }
        if(barrier){ barrier.textContent=(d.barrier_status||'closed').toUpperCase(); barrier.style.color=d.barrier_status==='open'?'var(--accent-green)':'var(--text-muted)'; }
        if(hb&&d.last_heartbeat){ try{ hb.textContent=new Date(d.last_heartbeat.replace(' ','T')).toLocaleTimeString(); }catch(e){} }
        if(overlay){
          if(!isOnline){
            overlay.style.display='flex';
            overlay.innerHTML='<i class=\"fa-solid fa-power-off\" style=\"color:var(--accent-red);font-size:20px\"></i><div><div style=\"font-weight:600;color:var(--accent-red);font-size:13px\">Gate Offline</div><div class=\"text-xs text-muted\">Commands disabled.</div></div>';
            card.querySelectorAll('.cmd-btn').forEach(function(b){b.disabled=true;});
          } else {
            overlay.style.display='none';
            card.querySelectorAll('.cmd-btn').forEach(function(b){b.disabled=false;});
          }
        }
      });
    }).catch(function(){});
}, 5000);
";
?>
