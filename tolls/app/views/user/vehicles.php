<?php $title = 'My Vehicles'; ?>

<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">My Vehicles</h2>
    <p class="text-muted text-sm">Manage your registered vehicles and RFID cards</p>
  </div>
</div>

<?php if (empty($vehicles)): ?>
<div class="card">
  <div class="card-body" style="text-align:center;padding:60px">
    <div style="font-size:48px;margin-bottom:12px"><i class="fa-solid fa-car"></i></div>
    <h3 style="color:var(--text-primary);margin-bottom:8px">No Vehicles Registered</h3>
    <p class="text-muted">You have no vehicles registered under your account. Contact the administrator to register a vehicle.</p>
  </div>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
  <?php foreach ($vehicles as $v): ?>
  <div class="card">
    <div style="padding:20px">
      <div class="d-flex align-center justify-between" style="margin-bottom:14px">
        <div>
          <div style="font-family:var(--font-mono);font-size:22px;font-weight:700;color:var(--text-primary);letter-spacing:2px">
            <?= htmlspecialchars($v['plate_number']) ?>
          </div>
          <div class="text-muted text-sm"><?= htmlspecialchars($v['year'].' '.$v['make'].' '.$v['model']) ?></div>
        </div>
        <span class="badge badge-info" style="font-size:12px"><?= strtoupper($v['vehicle_type']) ?></span>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
        <div style="background:var(--bg-panel);padding:10px;border-radius:var(--radius);border:1px solid var(--border)">
          <div class="text-xs text-muted" style="margin-bottom:3px">COLOR</div>
          <div class="text-sm" style="color:var(--text-primary)"><?= htmlspecialchars($v['color'] ?: 'N/A') ?></div>
        </div>
        <div style="background:var(--bg-panel);padding:10px;border-radius:var(--radius);border:1px solid var(--border)">
          <div class="text-xs text-muted" style="margin-bottom:3px">STATUS</div>
          <span class="badge badge-<?= $v['status']==='active'?'success':'danger' ?>"><?= strtoupper($v['status']) ?></span>
        </div>
      </div>

      <div style="background:var(--bg-panel);padding:12px;border-radius:var(--radius);border:1px solid var(--border)">
        <div class="text-xs text-muted" style="margin-bottom:4px;font-family:var(--font-mono)">RFID CARD</div>
        <?php if ($v['card_uid']): ?>
        <div class="d-flex align-center gap-8">
          <span class="status-dot online"></span>
          <span class="mono" style="font-size:13px;color:var(--accent-cyan)"><?= htmlspecialchars($v['card_uid']) ?></span>
          <span class="badge badge-<?= $v['card_status']==='active'?'success':'danger' ?>"><?= strtoupper($v['card_status']??'') ?></span>
        </div>
        <?php else: ?>
        <div class="d-flex align-center gap-8">
          <span class="status-dot offline"></span>
          <span class="text-muted text-sm">No RFID card assigned</span>
        </div>
        <?php endif; ?>
      </div>

      <div class="text-xs text-muted" style="margin-top:10px">
        Registered: <?= date('M d, Y', strtotime($v['registered_at'])) ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
