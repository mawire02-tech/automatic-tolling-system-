<?php $title = 'System Settings'; ?>

<div class="d-flex align-center justify-between mb-20">
  <div>
    <h2 style="font-family:var(--font-mono);font-size:18px">System Settings</h2>
    <p class="text-muted text-sm">Configure toll fees, barriers, security, and interface preferences</p>
  </div>
  <button class="btn btn-primary" id="saveBtn" onclick="saveAllSettings()">
    <i class="fa-solid fa-floppy-disk"></i> Save All Settings
  </button>
</div>

<form id="settingsForm">
  <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" id="settingsCsrf" value="<?= Security::csrfToken() ?>">

<?php
// FIX: removed duplicate 'api' key — the second entry was silently
//      overwriting the first so 'API & Devices' never rendered.
$groupMeta = array(
  'toll_fees' => array('icon'=>'<i class="fa-solid fa-dollar-sign"></i>',    'label'=>'Toll Fees',          'color'=>'var(--accent-green)'),
  'barrier'   => array('icon'=>'<i class="fa-solid fa-road-barrier"></i>',   'label'=>'Barrier Control',    'color'=>'var(--accent-amber)'),
  'wallet'    => array('icon'=>'<i class="fa-solid fa-wallet"></i>',         'label'=>'Wallet',             'color'=>'var(--accent-cyan)'),
  'general'   => array('icon'=>'<i class="fa-solid fa-gear"></i>',           'label'=>'General',            'color'=>'var(--text-secondary)'),
  'security'  => array('icon'=>'<i class="fa-solid fa-lock"></i>',           'label'=>'Security',           'color'=>'var(--accent-red)'),
  'api'       => array('icon'=>'<i class="fa-solid fa-plug"></i>',           'label'=>'API & Integrations', 'color'=>'var(--accent-cyan)'),
  'device'    => array('icon'=>'<i class="fa-solid fa-satellite-dish"></i>', 'label'=>'Device',             'color'=>'var(--accent-cyan)'),
  'ui'        => array('icon'=>'<i class="fa-solid fa-palette"></i>',        'label'=>'Interface',          'color'=>'var(--accent-purple)'),
);

// Keys that must never be rendered in the form UI
$hiddenKeys = array('offline_sync_enabled');

foreach ($settings as $group => $items):
  // Strip hidden keys before rendering
  $visibleItems = array_filter($items, function($s) use ($hiddenKeys) {
    return !in_array($s['setting_key'], $hiddenKeys);
  });

  // Skip the entire card if no visible settings remain in this group
  if (empty($visibleItems)) continue;

  $meta = isset($groupMeta[$group])
    ? $groupMeta[$group]
    : array('icon'=>'<i class="fa-solid fa-gear"></i>', 'label'=>ucfirst($group), 'color'=>'var(--text-secondary)');
?>
<div class="card mb-16">
  <div class="card-header">
    <span style="color:<?= $meta['color'] ?>"><?= $meta['icon'] ?></span>
    <span class="card-title"><?= $meta['label'] ?></span>
    <span class="badge badge-muted"><?= count($visibleItems) ?> settings</span>
  </div>
  <div class="card-body">
    <div class="form-row cols-2">
      <?php foreach ($visibleItems as $s): ?>
      <div class="form-group">
        <label class="form-label">
          <?= htmlspecialchars(str_replace('_', ' ', $s['setting_key'])) ?>
          <?php if ($s['description']): ?>
            <span class="text-muted" style="font-weight:400;text-transform:none;letter-spacing:0">
              &mdash; <?= htmlspecialchars($s['description']) ?>
            </span>
          <?php endif; ?>
        </label>

        <?php
          $k = $s['setting_key'];
          $v = $s['setting_value'];
          $isBool = ($v === '0' || $v === '1')
            && !preg_match('/fee|amount|limit|duration|interval|timeout|threshold|time|delay/', $k);
        ?>

        <?php if ($isBool): ?>
          <select name="settings[<?= htmlspecialchars($k) ?>]" class="form-control">
            <option value="1" <?= $v === '1' ? 'selected' : '' ?>>Enabled</option>
            <option value="0" <?= $v === '0' ? 'selected' : '' ?>>Disabled</option>
          </select>

        <?php elseif ($k === 'theme'): ?>
          <select name="settings[<?= htmlspecialchars($k) ?>]" class="form-control">
            <option value="dark"  <?= $v === 'dark'  ? 'selected' : '' ?>>Dark</option>
            <option value="light" <?= $v === 'light' ? 'selected' : '' ?>>Light</option>
          </select>

        <?php else: ?>
          <input type="text"
                 name="settings[<?= htmlspecialchars($k) ?>]"
                 class="form-control"
                 value="<?= htmlspecialchars($v) ?>">
        <?php endif; ?>

      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</form>

<div class="d-flex justify-between align-center" style="margin-top:8px">
  <p class="text-muted text-sm">Changes take effect immediately for new transactions and device configurations.</p>
  <button class="btn btn-primary" id="saveBtn2" onclick="saveAllSettings()">
    <i class="fa-solid fa-floppy-disk"></i> Save All Settings
  </button>
</div>

<?php
$saveUrl  = url('admin/settings/save');
$csrfName = CSRF_TOKEN_NAME;
$csrfVal  = Security::csrfToken();

$pageScript = <<<JS
var S_SAVE_URL = '{$saveUrl}';
var S_CSRF_KEY = '{$csrfName}';
var S_CSRF_VAL = '{$csrfVal}';

// ── Validation rule sets ──────────────────────────────────────────────────────

// Positive decimal fields
var NUMERIC_FIELDS = [
  'toll_fee_motorcycle', 'toll_fee_car', 'toll_fee_suv',
  'toll_fee_truck', 'toll_fee_bus', 'low_balance_alert'
];

// Positive whole-integer fields
var INTEGER_FIELDS = [
  'barrier_open_time', 'barrier_close_delay',
  'max_login_attempts', 'lockout_duration', 'session_timeout',
  'api_rate_limit', 'heartbeat_interval'
];

// Min / max bounds per field  [min, max]
var RANGE = {
  'toll_fee_motorcycle': [1,    9999],
  'toll_fee_car':        [1,    9999],
  'toll_fee_suv':        [1,    9999],
  'toll_fee_truck':      [1,    9999],
  'toll_fee_bus':        [1,    9999],
  'low_balance_alert':   [1,    9999],
  'barrier_open_time':   [500,  30000],
  'barrier_close_delay': [500,  30000],
  'max_login_attempts':  [1,    20],
  'lockout_duration':    [1,    1440],
  'session_timeout':     [5,    1440],
  'api_rate_limit':      [1,    1000],
  'heartbeat_interval':  [5,    3600]
};

// Non-blank text fields
var REQUIRED_FIELDS = [
  'system_name', 'system_timezone', 'currency_symbol', 'date_format'
];

// Max character lengths (currency_symbol handled separately below)
var MAXLEN = {
  'system_name':     100,
  'system_timezone':  80,
  'date_format':      30
};

// Currency symbol whitelist regex — accepts 1-3 chars from recognised symbols.
// Unicode escapes used so the heredoc does not need to embed raw multibyte chars.
// Covered: $ \u00a3(pound) \u20ac(euro) \u00a5(yen) \u20b9(rupee)
//          \u20a6(naira) \u20a9(won) \u20aa(shekel) \u20ab(dong)
//          \u20ad(kip) \u20ae(tugrik) \u20b1(peso) \u20b2(guarani)
//          \u20b4(hryvnia) \u20b8(tenge) \u20ba(lira) \u20bc(manat)
//          \u20bd(ruble) \u20be(lari) \u00a2(cent) \u00a4(generic)
//          \u0e3f(baht)  plus letter-based: R Fr Z (covers ZiG, ZWL etc.)
var CURRENCY_REGEX = /^[\$\u00a3\u20ac\u00a5\u20b9\u20a6\u20a9\u20aa\u20ab\u20ad\u20ae\u20b1\u20b2\u20b4\u20b8\u20ba\u20bc\u20bd\u20be\u00a2\u00a4\u0e3fRFrZz]{1,3}$/;

// ── Helpers ───────────────────────────────────────────────────────────────────

function labelFor(key) {
  return key.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
}

function inArray(arr, val) {
  for (var i = 0; i < arr.length; i++) { if (arr[i] === val) return true; }
  return false;
}

// ── Error display ─────────────────────────────────────────────────────────────

function clearFieldErrors() {
  var invalids = document.querySelectorAll('#settingsForm .form-control.is-invalid');
  for (var i = 0; i < invalids.length; i++) {
    invalids[i].classList.remove('is-invalid');
    invalids[i].style.borderColor = '';
    invalids[i].style.boxShadow   = '';
  }
  var msgs = document.querySelectorAll('#settingsForm .settings-field-error');
  for (var j = 0; j < msgs.length; j++) msgs[j].remove();
}

function attachError(input, message) {
  input.classList.add('is-invalid');
  input.style.borderColor = 'var(--accent-red)';
  input.style.boxShadow   = '0 0 0 3px rgba(255,61,90,0.15)';

  var prev = input.parentElement.querySelector('.settings-field-error');
  if (prev) prev.remove();

  var el = document.createElement('p');
  el.className   = 'form-error settings-field-error';
  el.textContent = message;
  input.insertAdjacentElement('afterend', el);
}

// ── Core validation ───────────────────────────────────────────────────────────

function collectAndValidate() {
  clearFieldErrors();
  var errors = [];
  var inputs = document.querySelectorAll('#settingsForm [name^="settings["]');

  for (var i = 0; i < inputs.length; i++) {
    var input = inputs[i];

    // Selects always have a valid option — skip
    if (input.tagName === 'SELECT') continue;

    var match = input.name.match(/^settings\[(.+)\]$/);
    if (!match) continue;

    var key   = match[1];
    var raw   = input.value.trim();
    var label = labelFor(key);
    var error = null;

    // 1. Required
    if (inArray(REQUIRED_FIELDS, key) && raw === '') {
      error = label + ' is required.';
    }

    // 2. Currency symbol — recognised symbol chars only, 1-3 chars
    if (!error && key === 'currency_symbol') {
      if (raw === '') {
        error = 'Currency Symbol is required.';
      } else if (!CURRENCY_REGEX.test(raw)) {
        error = 'Invalid currency symbol. Use a recognised symbol such as $, \u00a3, \u20ac, \u00a5, \u20b9, R, Fr, or ZiG (1\u20133 characters).';
      }
    }

    // 3. Numeric — positive decimal
    if (!error && inArray(NUMERIC_FIELDS, key)) {
      if (raw === '' || isNaN(Number(raw)) || Number(raw) < 0) {
        error = label + ' must be a positive number (e.g. 35.00).';
      }
    }

    // 4. Integer — positive whole number
    if (!error && inArray(INTEGER_FIELDS, key)) {
      var parsed = Number(raw);
      if (raw === '' || !Number.isInteger(parsed) || parsed < 0) {
        error = label + ' must be a whole number (e.g. 3000).';
      }
    }

    // 5. Range bounds
    if (!error && RANGE[key] !== undefined) {
      var num = Number(raw);
      var mn  = RANGE[key][0];
      var mx  = RANGE[key][1];
      if (num < mn || num > mx) {
        error = label + ' must be between ' + mn + ' and ' + mx + '.';
      }
    }

    // 6. Max length
    if (!error && MAXLEN[key] !== undefined) {
      if (raw.length > MAXLEN[key]) {
        error = label + ' must not exceed ' + MAXLEN[key] + ' characters.';
      }
    }

    if (error) {
      attachError(input, error);
      errors.push({ input: input, message: error });
    }
  }

  return errors;
}

// ── Button state ──────────────────────────────────────────────────────────────

function setBtnLoading(loading) {
  var ids = ['saveBtn', 'saveBtn2'];
  for (var i = 0; i < ids.length; i++) {
    var btn = document.getElementById(ids[i]);
    if (!btn) continue;
    btn.disabled  = loading;
    btn.innerHTML = loading
      ? '<i class="fa-solid fa-spinner fa-spin"></i> Saving\u2026'
      : '<i class="fa-solid fa-floppy-disk"></i> Save All Settings';
  }
}

// ── Save handler ──────────────────────────────────────────────────────────────

function saveAllSettings() {
  var errors = collectAndValidate();

  if (errors.length > 0) {
    errors[0].input.scrollIntoView({ behavior: 'smooth', block: 'center' });
    showToast(
      errors.length === 1
        ? errors[0].message
        : errors.length + ' fields need attention \u2014 please review the highlighted fields.',
      'error',
      5000
    );
    return; // block fetch entirely
  }

  setBtnLoading(true);

  var form = document.getElementById('settingsForm');
  var data = new FormData(form);
  data.set(S_CSRF_KEY, S_CSRF_VAL);

  safeFetch(
    S_SAVE_URL,
    { method: 'POST', body: data },
    function(d) {
      showToast(d.message || 'Settings saved successfully.', 'success');
      // Apply theme change immediately
      var themeEl = document.querySelector('[name="settings[theme]"]');
      if (themeEl) {
        document.documentElement.setAttribute('data-theme', themeEl.value);
        localStorage.setItem('theme', themeEl.value);
      }
      setBtnLoading(false);
    },
    function(msg) {
      showToast(msg || 'Error saving settings.', 'error');
      setBtnLoading(false);
    }
  );
}

// ── Live correction: clear a field error as the user types a fix ──────────────

document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('settingsForm').addEventListener('input', function(e) {
    var input = e.target;
    if (!input.classList.contains('is-invalid')) return;

    // Re-run full validation; re-attach errors for other still-invalid fields
    var remaining = collectAndValidate();
    for (var i = 0; i < remaining.length; i++) {
      if (remaining[i].input !== input) {
        attachError(remaining[i].input, remaining[i].message);
      }
    }
  });
});
JS;
?>