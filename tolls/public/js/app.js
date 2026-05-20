// app.js — SmartToll System UI v2.3

// ============================================================
// TOAST NOTIFICATIONS
// ============================================================
function showToast(message, type, duration) {
  type     = type     || 'info';
  duration = duration || 4000;
  var container = document.getElementById('toastContainer');
  if (!container) return;
  var icons = { success: '&#10003;', error: '&#10007;', warning: '&#9888;', info: '&#9432;' };
  var toast = document.createElement('div');
  toast.className = 'toast ' + type;
  toast.innerHTML =
    '<span style="font-size:15px">' + (icons[type] || icons.info) + '</span>' +
    '<span style="flex:1">' + message + '</span>' +
    '<button onclick="this.parentElement.remove()" ' +
      'style="background:none;border:none;color:inherit;cursor:pointer;font-size:16px;padding:0 0 0 8px">&#215;</button>';
  container.appendChild(toast);
  setTimeout(function() {
    try {
      toast.style.animation = 'slideOut 0.3s ease forwards';
      setTimeout(function() { if (toast.parentNode) toast.remove(); }, 300);
    } catch(e) {}
  }, duration);
}

// ============================================================
// MODALS
// ============================================================
function openModal(id) {
  var el = document.getElementById(id);
  if (el) el.classList.add('active');
}

function closeModal(id) {
  var el = document.getElementById(id);
  if (el) el.classList.remove('active');
}

document.addEventListener('click', function(e) {
  if (e.target && e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('active');
  }
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    var modals = document.querySelectorAll('.modal-overlay.active');
    for (var i = 0; i < modals.length; i++) {
      modals[i].classList.remove('active');
    }
  }
});

// ============================================================
// CSV EXPORT
// ============================================================
function exportTable(tableId, filename) {
  var tbl = document.getElementById(tableId);
  if (!tbl) return;
  var csv  = [];
  var rows = tbl.querySelectorAll('tr');
  for (var i = 0; i < rows.length; i++) {
    var cells = rows[i].querySelectorAll('th,td');
    var row   = [];
    for (var j = 0; j < cells.length; j++) {
      row.push('"' + cells[j].innerText.replace(/"/g, '""') + '"');
    }
    csv.push(row.join(','));
  }
  var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
  var a    = document.createElement('a');
  a.href   = URL.createObjectURL(blob);
  a.download = (filename || 'export') + '_' + new Date().toISOString().slice(0, 10) + '.csv';
  a.click();
}

function exportTableCSV(tableId, filename) {
  exportTable(tableId, filename);
}
// ============================================================
// SAFE FETCH — handles non-JSON, HTTP errors, and shows field errors
// ============================================================
function safeFetch(url, options, onSuccess, onError) {
  fetch(url, options)
    .then(function(r) {
      var status = r.status;
      return r.text().then(function(text) {
        var data;
        try {
          data = JSON.parse(text);
        } catch(e) {
          // Server returned non-JSON (PHP error, HTML page etc.)
          data = { error: 'Server error (HTTP ' + status + '). Check PHP logs.' };
        }
        if (data.success) {
          onSuccess(data);
        } else {
          var msg = data.error || data.message || 'An error occurred';
          onError(msg, data);
        }
      });
    })
    .catch(function(e) {
      onError('Cannot reach server. Check your connection and that XAMPP is running.');
    });
}


// ============================================================
// COPY TO CLIPBOARD
// ============================================================
function copyText(text) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text)
      .then(function() { showToast('Copied!', 'success'); })
      .catch(function() { showToast('Copy failed', 'error'); });
  }
}

// ============================================================
// CSRF HELPER
// ============================================================
function getCsrf() {
  var m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.content : '';
}

// ============================================================
// THEME INIT
// ============================================================
(function() {
  var saved = localStorage.getItem('theme') || 'dark';
  document.documentElement.setAttribute('data-theme', saved);
})();

// ============================================================
// DOM READY — sidebar, theme toggle, auto-refresh
// ============================================================
document.addEventListener('DOMContentLoaded', function() {

  // ---- Sidebar toggle ----
  var sidebarBtn = document.getElementById('sidebarToggle');
  var sidebar    = document.getElementById('sidebar');
  if (sidebarBtn && sidebar) {
    // Create overlay
    var overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.id = 'sidebarOverlay';
    document.body.appendChild(overlay);
    overlay.addEventListener('click', function() {
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
    });

    sidebarBtn.addEventListener('click', function() {
      var isOpen = sidebar.classList.toggle('open');
      overlay.classList.toggle('active', isOpen);
    });
  }

  // ---- Theme toggle ----
  var themeBtn = document.getElementById('themeToggle');
  if (themeBtn) {
    themeBtn.addEventListener('click', function() {
      var html = document.documentElement;
      var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', next);
      localStorage.setItem('theme', next);
    });
  }

  // ---- Auto-refresh live counters ----
  var BP = (document.querySelector('meta[name="base-path"]') || {}).content || '';
  startAutoRefresh(BP);
});

// ============================================================
// AUTO-REFRESH ENGINE
// ============================================================
function startAutoRefresh(BP) {
  var path = window.location.pathname;

  // ---- Stats refresh (all admin pages) ----
  if (path.indexOf('/admin/') !== -1) {
    setInterval(function() {
      fetch(BP + '/admin/api/stats')
        .then(function(r) { return r.json(); })
        .then(function(data) {
          var els = document.querySelectorAll('[data-stat]');
          for (var i = 0; i < els.length; i++) {
            var key = els[i].getAttribute('data-stat');
            if (data[key] !== undefined) {
              var isCurrency = els[i].getAttribute('data-format') === 'currency';
              els[i].textContent = isCurrency
                ? (document.querySelector('meta[name="currency"]') ? document.querySelector('meta[name="currency"]').content : '$') + parseFloat(data[key]).toFixed(2)
                : parseInt(data[key]).toLocaleString();
            }
          }
        }).catch(function() {});
    }, 15000);
  }

  // ---- Page-specific auto-refresh ----
  var pagePath = window.location.pathname;

  // Dashboard: refresh active gates strip every 10s
  if (pagePath.indexOf('/admin/dashboard') !== -1) {
    setInterval(function() {
      refreshActiveGates(BP);
    }, 10000);
  }

  // Transactions: refresh table badge every 20s (soft)
  if (pagePath.indexOf('/admin/transactions') !== -1) {
    startTableRefresh(20000);
  }

  // Top-ups: refresh pending count badge every 15s
  if (pagePath.indexOf('/admin/topups') !== -1) {
    startTableRefresh(15000);
  }

  // Gate override: refresh gate status every 5s
  if (pagePath.indexOf('/admin/gate-override') !== -1) {
    setInterval(function() {
      pollGateStatusGlobal(BP);
    }, 5000);
  }

  // Devices: refresh status dots every 10s
  if (pagePath.indexOf('/admin/devices') !== -1) {
    setInterval(function() {
      pollGateStatusGlobal(BP);
    }, 10000);
  }
}

// ---- Refresh active gates on dashboard ----
function refreshActiveGates(BP) {
  fetch(BP + '/admin/api/gate-status')
    .then(function(r) { return r.json(); })
    .then(function(devices) {
      // Update status dots and badges
      for (var i = 0; i < devices.length; i++) {
        var d    = devices[i];
        var dot  = document.querySelector('.sdot-' + d.id + ', [data-device-dot="' + d.id + '"]');
        var badge= document.querySelector('[data-device-badge="' + d.id + '"]');
        if (dot)   dot.className   = 'status-dot ' + d.status;
        if (badge) badge.textContent = d.status.toUpperCase();
      }
    }).catch(function() {});
}

// ---- Poll gate status globally (for gate-override and devices pages) ----
function pollGateStatusGlobal(BP) {
  fetch(BP + '/admin/api/gate-status')
    .then(function(r) { return r.json(); })
    .then(function(devices) {
      for (var i = 0; i < devices.length; i++) {
        var d       = devices[i];
        var card    = document.getElementById('gate-card-'   + d.id);
        var badge   = document.getElementById('gate-status-badge-' + d.id);
        var barrier = document.getElementById('gate-barrier-' + d.id);
        var hb      = document.getElementById('gate-hb-'     + d.id);
        var overlay = document.getElementById('gate-offline-overlay-' + d.id);

        if (!card) continue;
        var isOnline = (d.status === 'online');
        card.setAttribute('data-status', d.status);

        if (badge) {
          badge.textContent = d.status.toUpperCase();
          badge.className   = 'badge badge-' + (isOnline ? 'success' : (d.status === 'maintenance' ? 'warning' : 'danger'));
        }
        if (barrier) {
          var bText = (d.barrier_status || 'unknown').toUpperCase();
          barrier.textContent = bText;
        }
        if (hb && d.last_heartbeat) {
          try {
            var t = new Date(d.last_heartbeat.replace(' ', 'T'));
            hb.textContent = t.toLocaleTimeString();
          } catch(e) {}
        }
        if (overlay) {
          if (!isOnline) {
            overlay.style.display = 'flex';
            overlay.innerHTML =
              '<span style="font-size:18px">&#128244;</span>' +
              '<div><div style="font-size:12px;font-weight:600;color:var(--accent-red)">Gate Offline</div>' +
              '<div class="text-xs text-muted">Commands disabled. Gate must be online.</div></div>';
            var btns = card.querySelectorAll('.cmd-btn');
            for (var b = 0; b < btns.length; b++) btns[b].disabled = true;
          } else {
            overlay.style.display = 'none';
            var btns2 = card.querySelectorAll('.cmd-btn');
            for (var b2 = 0; b2 < btns2.length; b2++) btns2[b2].disabled = false;
          }
        }
      }
    }).catch(function() {});
}

// ---- Soft table refresh (show "New data available" banner) ----
function startTableRefresh(intervalMs) {
  var refreshTimer = setInterval(function() {
    var banner = document.getElementById('refreshBanner');
    if (!banner) {
      banner = document.createElement('div');
      banner.id = 'refreshBanner';
      banner.style.cssText =
        'position:fixed;bottom:20px;right:20px;background:var(--accent-cyan);color:#000;' +
        'padding:10px 18px;border-radius:var(--radius);font-size:12px;font-weight:600;' +
        'cursor:pointer;z-index:999;box-shadow:var(--shadow-hover);font-family:var(--font-mono)';
      banner.innerHTML = '&#8635; New data available — Click to refresh';
      banner.addEventListener('click', function() { location.reload(); });
      document.body.appendChild(banner);
    }
  }, intervalMs);
}
