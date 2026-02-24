(function () {
  function apiUrl(base, endpoint, params) {
    var url = String(base || '');
    var encodedEndpoint = encodeURIComponent(String(endpoint || ''));
    if (url.indexOf('endpoint=') !== -1) {
      url = url.replace(/endpoint=[^&]*/, 'endpoint=' + encodedEndpoint);
    } else {
      url += (url.indexOf('?') === -1 ? '?' : '&') + 'endpoint=' + encodedEndpoint;
    }
    if (params) {
      Object.keys(params).forEach(function (k) {
        url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
      });
    }
    return url;
  }

  function getJson(url) {
    return fetch(url, { credentials: 'same-origin' }).then(function (r) {
      return r.text();
    }).then(function (raw) {
      if (!raw) {
        throw new Error('Empty API response');
      }
      raw = String(raw).replace(/^\uFEFF/, '').trim();
      try {
        return JSON.parse(raw);
      } catch (e) {
        var start = raw.indexOf('DCMANAGE_JSON_START');
        var end = raw.indexOf('DCMANAGE_JSON_END');
        if (start !== -1 && end !== -1 && end > start) {
          var body = raw.substring(start + 'DCMANAGE_JSON_START'.length, end).trim();
          return JSON.parse(body);
        }

        var first = raw.indexOf('{');
        var last = raw.lastIndexOf('}');
        if (first !== -1 && last > first) {
          try {
            return JSON.parse(raw.substring(first, last + 1));
          } catch (ignored) {
          }
        }

        throw new Error('Invalid API response');
      }
    });
  }

  function toGb(bytes) {
    return (bytes / 1073741824).toFixed(2);
  }

  function safeText(v) {
    if (v === null || v === undefined) {
      return '';
    }
    return String(v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  var shell = document.querySelector('.dcmanage-shell');
  var shellLang = shell ? (shell.getAttribute('data-lang') || '') : '';
  var isFa = shellLang.toLowerCase() === 'fa';
  var T = isFa ? {
    dc: 'دیتاسنتر',
    racks: 'رک',
    switches: 'سوییچ',
    servers: 'سرور',
    ports: 'پورت',
    breaches: 'تخلف',
    queue: 'صف',
    testMode: 'حالت تست',
    testModeOn: 'فعال',
    testModeOff: 'غیرفعال',
    cronHealth: 'وضعیت کرون',
    ok: 'سالم',
    warn: 'هشدار',
    fail: 'خراب',
    versionCenter: 'مرکز نسخه و آپدیت',
    currentVersion: 'نسخه فعلی',
    latestRelease: 'آخرین ریلیز',
    checkUpdate: 'بررسی آپدیت',
    applyUpdate: 'اعمال آپدیت',
    cancelUpdate: 'لغو آپدیت',
    autoUpdate: 'آپدیت خودکار',
    openCron: 'تنظیمات کرون',
    updateStateOutdated: 'آپدیت نشده',
    updateStateAvailable: 'آپدیت داریم',
    updateStateUpdated: 'آپدیت شد',
    updateStateRemoteError: 'خطا در بررسی نسخه',
    checking: 'در حال بررسی نسخه...',
    applying: 'در حال اعمال آپدیت...',
    queued: 'آپدیت در صف قرار گرفت...',
    canceling: 'در حال ارسال لغو...',
    updateStatus: 'وضعیت',
    checkError: 'خطا در بررسی نسخه',
    applyError: 'خطا در آپدیت',
    cancelError: 'خطا در لغو آپدیت',
    toggleError: 'خطا در تغییر وضعیت آپدیت خودکار',
    autoEnabled: 'آپدیت خودکار فعال شد.',
    autoDisabled: 'آپدیت خودکار غیرفعال شد.'
  } : {
    dc: 'Datacenters',
    racks: 'Racks',
    switches: 'Switches',
    servers: 'Servers',
    ports: 'Ports',
    breaches: 'Breaches',
    queue: 'Queue',
    testMode: 'Test Mode',
    testModeOn: 'ON',
    testModeOff: 'OFF',
    cronHealth: 'Cron Health',
    ok: 'OK',
    warn: 'Warning',
    fail: 'Fail',
    versionCenter: 'Version & Update Center',
    currentVersion: 'Current Version',
    latestRelease: 'Latest Release',
    checkUpdate: 'Check Update',
    applyUpdate: 'Apply Update',
    cancelUpdate: 'Cancel Update',
    autoUpdate: 'Auto Update',
    openCron: 'Open Cron Settings',
    updateStateOutdated: 'Not Updated',
    updateStateAvailable: 'Update Available',
    updateStateUpdated: 'Updated',
    updateStateRemoteError: 'Version Check Error',
    checking: 'Checking latest release...',
    applying: 'Applying update...',
    queued: 'Update queued...',
    canceling: 'Sending cancel request...',
    updateStatus: 'Status',
    checkError: 'Failed to check update',
    applyError: 'Failed to apply update',
    cancelError: 'Failed to cancel update',
    toggleError: 'Failed to change auto update state',
    autoEnabled: 'Auto update enabled.',
    autoDisabled: 'Auto update disabled.'
  };

  function iconSvg(name) {
    var icons = {
      datacenters: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v4H4V5zm0 5h16v4H4v-4zm0 5h16v4H4v-4z"/></svg>',
      racks: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v18H7V3zm2 2v2h6V5H9zm0 4v2h6V9H9zm0 4v2h6v-2H9z"/></svg>',
      switches: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h18v10H3V7zm3 2h2v2H6V9zm3 0h2v2H9V9zm3 0h2v2h-2V9zm3 0h2v2h-2V9z"/></svg>',
      servers: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v6H4V4zm0 10h16v6H4v-6zm3-8h2v2H7V6zm0 10h2v2H7v-2z"/></svg>',
      ports: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v4h-2v5h4v4h-4v5h-6v-5H5v-4h4V7H7V3z"/></svg>',
      usage_breaches_today: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l10 18H2L12 3zm-1 6v5h2V9h-2zm0 7v2h2v-2h-2z"/></svg>',
      jobs_pending: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v4H4V4zm0 6h16v10H4V10zm3 2h10v2H7v-2zm0 4h7v2H7v-2z"/></svg>',
      version: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l7 3v6c0 5-3.4 9.4-7 11-3.6-1.6-7-6-7-11V5l7-3zm-1 6v6l5-3-5-3z"/></svg>',
      current: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h18v8H3v-8zm2 2v4h14v-4H5zm2-10h10l2 5H5l2-5z"/></svg>',
      latest: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3 6h6l-4.5 4.2L18 19l-6-3.2L6 19l1.5-6.8L3 8h6l3-6z"/></svg>',
      state: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 3h2v10h-2V3zm0 14h2v4h-2v-4z"/></svg>'
    };
    return icons[name] || icons.state;
  }

  function updateStateMeta(data) {
    if (data && data.remote_ok === false) {
      return { cls: 'state-warning', text: T.updateStateRemoteError };
    }

    var current = String(data.current_version || '');
    var latest = String(data.latest_version || '');
    var hasUpdate = !!data.has_update;

    if (hasUpdate) {
      return { cls: 'state-warning', text: T.updateStateAvailable };
    }
    if (current !== '' && latest !== '' && current === latest) {
      return { cls: 'state-success', text: T.updateStateUpdated };
    }
    return { cls: 'state-danger', text: T.updateStateOutdated };
  }

  function setUpdateMsg(node, message, kind) {
    if (!node) {
      return;
    }

    var text = String(message || '').trim();
    if (text === '') {
      node.className = 'dcmanage-update-msg is-info';
      node.innerHTML = '';
      return;
    }

    var cls = 'is-info';
    if (kind === 'success') {
      cls = 'is-success';
    } else if (kind === 'warning') {
      cls = 'is-warning';
    } else if (kind === 'danger') {
      cls = 'is-danger';
    }

    node.className = 'dcmanage-update-msg is-visible ' + cls;
    node.innerHTML = safeText(text);
  }

  function renderDashboard(base, moduleLink) {
    var dashboard = document.getElementById('dcmanage-dashboard');
    if (!dashboard) {
      return;
    }

    getJson(apiUrl(base, 'dashboard/health')).then(function (res) {
      if (!res.ok) {
        dashboard.innerHTML = '<div class="alert alert-danger">' + safeText(res.error || 'API error') + '</div>';
        return;
      }

      var c = res.data.counts || {};
      var cards = [
        { key: 'datacenters', label: T.dc, tab: 'datacenters' },
        { key: 'racks', label: T.racks, tab: 'datacenters' },
        { key: 'switches', label: T.switches, tab: 'switches' },
        { key: 'servers', label: T.servers, tab: 'servers' },
        { key: 'usage_breaches_today', label: T.breaches, tab: 'traffic' },
        { key: 'jobs_pending', label: T.queue, tab: 'queue' }
      ];
      var testMode = !!((res.data.flags || {}).test_mode);

      var html = '<div class="dcmanage-hero-band">' +
        '<div class="dcmanage-hero-title">' + safeText(isFa ? 'نمای کلی زیرساخت' : 'Infrastructure Snapshot') + '</div>' +
        '<div class="dcmanage-hero-art" aria-hidden="true">' +
        '<svg viewBox="0 0 220 70"><rect x="4" y="20" width="64" height="42" rx="8"/><rect x="78" y="10" width="64" height="52" rx="8"/><rect x="152" y="26" width="64" height="36" rx="8"/><circle cx="24" cy="35" r="4"/><circle cx="98" cy="29" r="4"/><circle cx="172" cy="39" r="4"/></svg>' +
        '</div>' +
        '</div>';
      html += '<div class="dcmanage-dashboard-flags">';
      html += '<span class="dcmanage-flag-pill ' + (testMode ? 'is-warning' : 'is-success') + '">' + safeText(T.testMode + ': ' + (testMode ? T.testModeOn : T.testModeOff)) + '</span>';
      html += '</div>';
      html += '<div class="row dcmanage-kpi">';
      cards.forEach(function (k) {
        var cardValue = c[k.key] || 0;
        var keyClass = 'is-' + String(k.key).replace(/_/g, '-');
        html += '' +
          '<div class="col-md-3 col-6 mb-3">' +
          '<a href="' + moduleLink + '&tab=' + encodeURIComponent(k.tab) + '" class="text-decoration-none">' +
          '<div class="card dcmanage-click-card dcmanage-kpi-card ' + keyClass + '">' +
          '<div class="card-body">' +
          '<span class="dcmanage-kpi-watermark" aria-hidden="true">' + iconSvg(k.key) + '</span>' +
          '<div class="dcmanage-kpi-head">' +
          '<span class="dcmanage-kpi-icon">' + iconSvg(k.key) + '</span>' +
          '<span class="dcmanage-kpi-label">' + safeText(k.label) + '</span>' +
          '</div>' +
          '<div class="dcmanage-kpi-value">' + safeText(cardValue) + '</div>' +
          '</div>' +
          '</div>' +
          '</a>' +
          '</div>';
      });
      html += '</div>';
      dashboard.innerHTML = html;
    }).catch(function (e) {
      dashboard.innerHTML = '<div class="alert alert-danger">' + safeText(e && e.message ? e.message : 'Dashboard error') + '</div>';
    });
  }

  function renderVersion(base) {
    var versionBox = document.getElementById('dcmanage-version');
    if (!versionBox) {
      return;
    }

    getJson(apiUrl(base, 'dashboard/version')).then(function (res) {
      if (!res.ok) {
        versionBox.innerHTML = '<div class="alert alert-danger">' + safeText(res.error || 'Version API error') + '</div>';
        return;
      }

      var d = res.data || {};
      var state = updateStateMeta(d);
      var autoChecked = d.auto_update ? ' checked' : '';

      versionBox.innerHTML = '' +
        '<div class="p-3 dcmanage-version-card">' +
        '<div class="dcmanage-version-head">' +
        '<div class="dcmanage-version-title">' +
        '<span class="dcmanage-panel-icon">' + iconSvg('version') + '</span>' +
        '<h5 class="mb-0">' + safeText(T.versionCenter) + '</h5>' +
        '</div>' +
        '<div class="dcmanage-update-state ' + state.cls + '">' + safeText(state.text) + '</div>' +
        '</div>' +
        '<div class="dcmanage-version-metrics">' +
        '<div class="dcmanage-version-metric">' +
        '<div class="metric-label"><span class="metric-icon">' + iconSvg('current') + '</span>' + safeText(T.currentVersion) + '</div>' +
        '<div class="metric-value">' + safeText(d.current_version || '-') + '</div>' +
        '</div>' +
        '<div class="dcmanage-version-metric">' +
        '<div class="metric-label"><span class="metric-icon">' + iconSvg('latest') + '</span>' + safeText(T.latestRelease) + '</div>' +
        '<div class="metric-value">' + safeText(d.latest_tag || '-') + ' <small>(' + safeText(d.latest_version || '-') + ')</small></div>' +
        '</div>' +
        '<div class="dcmanage-version-metric">' +
        '<div class="metric-label"><span class="metric-icon">' + iconSvg('state') + '</span>' + safeText(T.updateStatus) + '</div>' +
        '<div class="metric-value">' + safeText(state.text) + '</div>' +
        '</div>' +
        '</div>' +
        '<div class="dcmanage-update-actions-row">' +
        '<label class="dcmanage-check-inline" for="dcmanage-auto-update">' +
        '<input type="checkbox" id="dcmanage-auto-update"' + autoChecked + '>' +
        '<span>' + safeText(T.autoUpdate) + '</span>' +
        '</label>' +
        '<button type="button" class="btn btn-outline-primary btn-sm dcmanage-check-btn" id="dcmanage-check-update">' + safeText(T.checkUpdate) + '</button>' +
        '<button type="button" class="btn btn-primary btn-sm" id="dcmanage-apply-update">' + safeText(T.applyUpdate) + '</button>' +
        '<button type="button" class="btn btn-outline-danger btn-sm" id="dcmanage-cancel-update">' + safeText(T.cancelUpdate) + '</button>' +
        '</div>' +
        '<div id="dcmanage-update-msg" class="dcmanage-update-msg is-info"></div>' +
        '</div>';

      bindVersionActions(base);
    }).catch(function (e) {
      versionBox.innerHTML = '<div class="alert alert-danger">' + safeText(e && e.message ? e.message : 'Version error') + '</div>';
    });
  }

  function renderCron(base, moduleLink) {
    var box = document.getElementById('dcmanage-cron');
    if (!box) {
      return;
    }

    getJson(apiUrl(base, 'dashboard/cron')).then(function (res) {
      if (!res.ok) {
        box.innerHTML = '<div class="alert alert-danger">' + safeText(res.error || 'Cron API error') + '</div>';
        return;
      }

      var d = res.data || {};
      var label = d.overall === 'ok' ? T.ok : (d.overall === 'fail' ? T.fail : T.warn);
      var rows = d.items || [];
      var html = '<div class="p-3 dcmanage-version-card">';
      html += '<div class="dcmanage-cron-head">';
      html += '<h5 class="mb-0">' + safeText(T.cronHealth) + '</h5>';
      html += '<span class="dcmanage-status-pill ' + (d.overall === 'ok' ? 'is-up' : (d.overall === 'fail' ? 'is-down' : 'is-unknown')) + '">' + safeText(label) + '</span>';
      html += '</div>';
      html += '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Task</th><th>Status</th><th>Last Run</th><th>Next Run</th></tr></thead><tbody>';
      rows.forEach(function (r) {
        var slbl = r.status === 'ok' ? T.ok : (r.status === 'fail' ? T.fail : T.warn);
        html += '<tr><td>' + safeText(r.task) + '</td><td><span class="dcmanage-status-pill ' + (r.status === 'ok' ? 'is-up' : (r.status === 'fail' ? 'is-down' : 'is-unknown')) + '">' + safeText(slbl) + '</span></td><td>' + safeText(r.last_run || '-') + '</td><td>' + safeText(r.next_run || '-') + '</td></tr>';
      });
      html += '</tbody></table></div>';
      html += '<div class="mt-2"><a class="btn btn-sm btn-outline-secondary" href="' + moduleLink + '&tab=settings">' + safeText(T.openCron) + '</a></div>';
      html += '</div>';
      box.innerHTML = html;
    }).catch(function (e) {
      box.innerHTML = '<div class="alert alert-danger">' + safeText(e && e.message ? e.message : 'Cron error') + '</div>';
    });
  }

  function bindVersionActions(base) {
    var checkBtn = document.getElementById('dcmanage-check-update');
    var applyBtn = document.getElementById('dcmanage-apply-update');
    var cancelBtn = document.getElementById('dcmanage-cancel-update');
    var autoToggle = document.getElementById('dcmanage-auto-update');
    var msg = document.getElementById('dcmanage-update-msg');
    var statusTimer = null;

    function stopStatusPoll() {
      if (statusTimer) {
        clearInterval(statusTimer);
        statusTimer = null;
      }
    }

    function startStatusPoll() {
      stopStatusPoll();
      statusTimer = setInterval(function () {
        getJson(apiUrl(base, 'update/status')).then(function (res) {
          if (!res.ok) {
            return;
          }
          var data = res.data || {};
          var state = data.state || {};
          var active = data.active_job || null;
          var status = String(state.status || '');
          var message = String(state.message || '');

          if (message) {
            var kind = 'info';
            if (status === 'updated' || status === 'up-to-date') {
              kind = 'success';
            } else if (status === 'failed') {
              kind = 'danger';
            } else if (status === 'cancel-requested' || status === 'canceled') {
              kind = 'warning';
            }
            if (active || status === 'running' || status === 'failed') {
              setUpdateMsg(msg, message, kind);
            }
          }

          if (!active && (status === 'updated' || status === 'up-to-date' || status === 'failed' || status === 'canceled')) {
            stopStatusPoll();
            renderVersion(base);
          }
        }).catch(function () {
        });
      }, 1000);
    }

    if (checkBtn) {
      checkBtn.addEventListener('click', function () {
        setUpdateMsg(msg, T.checking, 'info');
        getJson(apiUrl(base, 'update/check')).then(function (res) {
          if (!res.ok) {
            setUpdateMsg(msg, res.error || T.checkError, 'danger');
            return;
          }

          var d = res.data || {};
          var checkMessage = (T.currentVersion + ': ' + (d.current_version || '-') + ' | ' + T.latestRelease + ': ' + (d.latest_tag || '-'));
          setUpdateMsg(msg, checkMessage, d.has_update ? 'warning' : 'success');
          renderVersion(base);
        }).catch(function (e) {
          setUpdateMsg(msg, (e && e.message ? e.message : T.checkError), 'danger');
        });
      });
    }

    if (applyBtn) {
      applyBtn.addEventListener('click', function () {
        setUpdateMsg(msg, T.applying, 'warning');
        getJson(apiUrl(base, 'update/apply', { force: 0 })).then(function (res) {
          if (!res.ok) {
            setUpdateMsg(msg, res.error || T.applyError, 'danger');
            return;
          }

          var d = res.data || {};
          var state = String(d.status || '');
          var kind = state === 'updated' || state === 'up-to-date' ? 'success' : 'warning';
          if (state === 'queued' || state === 'already-running') {
            setUpdateMsg(msg, T.queued, 'warning');
            startStatusPoll();
          } else {
            setUpdateMsg(msg, (T.updateStatus + ': ' + (state || 'done')), kind);
            renderVersion(base);
          }
        }).catch(function (e) {
          setUpdateMsg(msg, (e && e.message ? e.message : T.applyError), 'danger');
        });
      });
    }

    if (cancelBtn) {
      cancelBtn.addEventListener('click', function () {
        setUpdateMsg(msg, T.canceling, 'warning');
        getJson(apiUrl(base, 'update/cancel')).then(function (res) {
          if (!res.ok) {
            setUpdateMsg(msg, res.error || T.cancelError, 'danger');
            return;
          }

          var d = res.data || {};
          var cancelState = String(d.status || 'cancel-requested');
          var cancelKind = cancelState === 'no-active-job' ? 'info' : 'warning';
          setUpdateMsg(msg, (T.updateStatus + ': ' + cancelState), cancelKind);
          startStatusPoll();
        }).catch(function (e) {
          setUpdateMsg(msg, (e && e.message ? e.message : T.cancelError), 'danger');
        });
      });
    }

    if (autoToggle) {
      autoToggle.addEventListener('change', function () {
        var enabled = autoToggle.checked ? 1 : 0;
        getJson(apiUrl(base, 'update/set-auto', { enabled: enabled })).then(function (res) {
          if (!res.ok) {
            setUpdateMsg(msg, res.error || T.toggleError, 'danger');
            return;
          }

          setUpdateMsg(msg, autoToggle.checked ? T.autoEnabled : T.autoDisabled, 'success');
        }).catch(function (e) {
          setUpdateMsg(msg, (e && e.message ? e.message : T.toggleError), 'danger');
        });
      });
    }

    getJson(apiUrl(base, 'update/status')).then(function (res) {
      if (!res.ok) {
        return;
      }
      var data = res.data || {};
      var state = data.state || {};
      var active = data.active_job || null;
      var status = String(state.status || '');
      var message = String(state.message || '');
      if (message) {
        var kind = 'info';
        if (status === 'updated' || status === 'up-to-date') {
          kind = 'success';
        } else if (status === 'failed') {
          kind = 'danger';
        } else if (status === 'cancel-requested' || status === 'canceled') {
          kind = 'warning';
        }
        if (active || status === 'running' || status === 'failed') {
          setUpdateMsg(msg, message, kind);
        }
      }
      if (active) {
        setUpdateMsg(msg, message || T.applying, 'warning');
        startStatusPoll();
      } else if (status === 'idle' || status === 'updated' || status === 'up-to-date' || status === 'canceled') {
        setUpdateMsg(msg, '', 'info');
      }
    }).catch(function () {
    });
  }

  try {
    var dashboard = document.getElementById('dcmanage-dashboard');
    if (dashboard) {
      var base = dashboard.getAttribute('data-api-base') || '';
      var moduleLink = dashboard.getAttribute('data-module-link') || 'addonmodules.php?module=dcmanage';
      renderDashboard(base, moduleLink);
      renderVersion(base);

      // Delay cron health slightly so dashboard metrics render first without jumping
      setTimeout(function () {
        renderCron(base, moduleLink);
      }, 250);
    }

    var traffic = document.getElementById('dcmanage-traffic');
    if (traffic) {
      var baseTraffic = traffic.getAttribute('data-api-base') || '';
      getJson(apiUrl(baseTraffic, 'traffic/list')).then(function (res) {
        if (!res.ok) {
          traffic.innerHTML = '<div class="alert alert-danger">' + safeText(res.error || 'API error') + '</div>';
          return;
        }

        var rows = res.data || [];
        var blockedCount = rows.filter(function (r) { return String(r.status || '').toLowerCase() === 'blocked'; }).length;
        var overusedCount = rows.filter(function (r) { return Number(r.remaining_bytes || 0) < 0; }).length;
        var normalCount = rows.length - blockedCount;
        var html = '<div class="row dcmanage-kpi mb-2">' +
          '<div class="col-md-4 col-6 mb-3"><div class="card dcmanage-kpi-card"><div class="card-body"><div class="dcmanage-kpi-label">Services</div><div class="dcmanage-kpi-value">' + safeText(rows.length) + '</div></div></div></div>' +
          '<div class="col-md-4 col-6 mb-3"><div class="card dcmanage-kpi-card"><div class="card-body"><div class="dcmanage-kpi-label">Blocked</div><div class="dcmanage-kpi-value text-danger">' + safeText(blockedCount) + '</div></div></div></div>' +
          '<div class="col-md-4 col-12 mb-3"><div class="card dcmanage-kpi-card"><div class="card-body"><div class="dcmanage-kpi-label">Overused</div><div class="dcmanage-kpi-value text-warning">' + safeText(overusedCount) + '</div></div></div></div>' +
          '</div>';
        html += '<div class="dcmanage-form-card mb-3"><div class="form-row align-items-end">' +
          '<div class="form-group col-md-4 mb-2"><label>Status Filter</label><select id="dcmanage-traffic-filter" class="form-control dcmanage-input"><option value="all">All</option><option value="blocked">Blocked</option><option value="overused">Overused</option><option value="normal">Normal</option></select></div>' +
          '<div class="form-group col-md-4 mb-2"><label>Search Service</label><input id="dcmanage-traffic-search" class="form-control dcmanage-input" placeholder="service id / status"></div>' +
          '<div class="form-group col-md-4 mb-2"><label>Sort By</label><select id="dcmanage-traffic-sort" class="form-control dcmanage-input"><option value="service_asc">Service name A→Z</option><option value="remaining_asc">Remaining low→high</option><option value="remaining_desc">Remaining high→low</option><option value="download_desc">Download high→low</option><option value="upload_desc">Upload high→low</option><option value="total_desc">Total Used high→low</option></select></div>' +
          '</div></div>';
        html += '<div class="table-responsive dcmanage-table-wrap"><table class="table table-sm table-striped" id="dcmanage-traffic-table">' +
          '<thead><tr><th>Service</th><th>Domain</th><th>Status</th><th>Download (GB)</th><th>Upload (GB)</th><th>Total Used (GB)</th><th>Allowed (GB)</th><th>Remaining (GB)</th><th>Cycle End</th><th>Last Sample</th></tr></thead><tbody>';

        rows.forEach(function (r) {
          var st = String(r.status || '').toLowerCase();
          var cls = st === 'blocked' ? 'is-down' : (st === 'limited' ? 'is-unknown' : 'is-up');
          html += '<tr data-service="' + safeText(r.service_id) + '" data-status="' + safeText(st) + '" data-remaining="' + safeText(r.remaining_bytes) + '" data-download="' + safeText(r.download_bytes) + '" data-upload="' + safeText(r.upload_bytes) + '" data-total="' + safeText(r.used_bytes) + '">' +
            '<td>' + safeText(r.service_id) + '</td>' +
            '<td>' + safeText(r.domain_status || '-') + '</td>' +
            '<td><span class="dcmanage-status-pill ' + cls + '">' + safeText(st || '-') + '</span></td>' +
            '<td>' + toGb(r.download_bytes) + '</td>' +
            '<td>' + toGb(r.upload_bytes) + '</td>' +
            '<td>' + toGb(r.used_bytes) + '</td>' +
            '<td>' + toGb(r.allowed_bytes) + '</td>' +
            '<td>' + toGb(r.remaining_bytes) + '</td>' +
            '<td>' + safeText(r.cycle_end || '-') + '</td>' +
            '<td>' + safeText(r.last_sample_at || '-') + '</td>' +
            '</tr>';
        });

        html += '</tbody></table></div>';
        traffic.innerHTML = html;
        (function bindTrafficFilters() {
          var filter = document.getElementById('dcmanage-traffic-filter');
          var search = document.getElementById('dcmanage-traffic-search');
          var sort = document.getElementById('dcmanage-traffic-sort');
          var table = document.getElementById('dcmanage-traffic-table');
          if (!table) {
            return;
          }
          function apply() {
            var mode = filter ? String(filter.value || 'all') : 'all';
            var q = String(search ? search.value || '' : '').toLowerCase().trim();
            var body = table.querySelector('tbody');
            var rowsNode = Array.prototype.slice.call(body.querySelectorAll('tr'));
            rowsNode.forEach(function (row) {
              var st = String(row.getAttribute('data-status') || '');
              var rem = Number(row.getAttribute('data-remaining') || 0);
              var service = String(row.getAttribute('data-service') || '');
              var vis = true;
              if (mode === 'blocked') {
                vis = st === 'blocked';
              } else if (mode === 'overused') {
                vis = rem < 0;
              } else if (mode === 'normal') {
                vis = st !== 'blocked';
              }
              if (vis && q !== '') {
                vis = (service + ' ' + st).indexOf(q) !== -1;
              }
              row.style.display = vis ? '' : 'none';
            });
            var ordered = rowsNode.slice(0);
            var modeSort = sort ? String(sort.value || 'service_asc') : 'service_asc';
            ordered.sort(function (a, b) {
              if (modeSort === 'remaining_asc') {
                return Number(a.getAttribute('data-remaining') || 0) - Number(b.getAttribute('data-remaining') || 0);
              }
              if (modeSort === 'remaining_desc') {
                return Number(b.getAttribute('data-remaining') || 0) - Number(a.getAttribute('data-remaining') || 0);
              }
              if (modeSort === 'download_desc') {
                return Number(b.getAttribute('data-download') || 0) - Number(a.getAttribute('data-download') || 0);
              }
              if (modeSort === 'upload_desc') {
                return Number(b.getAttribute('data-upload') || 0) - Number(a.getAttribute('data-upload') || 0);
              }
              if (modeSort === 'total_desc') {
                return Number(b.getAttribute('data-total') || 0) - Number(a.getAttribute('data-total') || 0);
              }
              return Number(a.getAttribute('data-service') || 0) - Number(b.getAttribute('data-service') || 0);
            });
            ordered.forEach(function (row) {
              body.appendChild(row);
            });
          }
          if (filter) { filter.addEventListener('change', apply); }
          if (search) { search.addEventListener('input', apply); }
          if (sort) { sort.addEventListener('change', apply); }
          apply();
        })();

        if (rows.length > 0) {
          renderSampleChart(baseTraffic, rows[0].service_id);
        }
      }).catch(function (e) {
        traffic.innerHTML = '<div class="alert alert-danger">' + safeText(e && e.message ? e.message : 'Traffic error') + '</div>';
      });
    }
  } catch (e) {
    console.error(e);
  }

  function renderSampleChart(baseApi, serviceId) {
    var canvas = document.getElementById('dcmanage-traffic-chart');
    if (!canvas || typeof Chart === 'undefined') {
      return;
    }

    getJson(apiUrl(baseApi, 'graphs/get', { service_id: serviceId, from: '-24h', to: 'now', avg: 300 })).then(function (res) {
      if (!res.ok) {
        return;
      }

      var hist = ((res.data || {}).payload || {}).histdata || [];
      var labels = [];
      var values = [];

      hist.forEach(function (item) {
        labels.push(item.datetime || '');
        values.push(Number(item.value_raw || 0));
      });

      new Chart(canvas, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Traffic',
            data: values,
            borderColor: '#2f6fed',
            backgroundColor: 'rgba(47,111,237,0.12)',
            tension: 0.25,
            pointRadius: 0,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            x: { display: true },
            y: { display: true },
          },
        },
      });
    }).catch(function () {
    });
  }

  (function initServerGraph() {
    var graphContainers = document.querySelectorAll('.dcmanage-graph-container canvas');
    if (graphContainers.length === 0 || typeof Chart === 'undefined') {
      return;
    }

    var baseApi = document.getElementById('dcmanage-api-base');
    if (!baseApi) { return; }
    baseApi = baseApi.getAttribute('data-url');

    var currentChart = null;

    function renderServerGraph(serverId, canvasId, fromRange, toRange) {
      toRange = toRange || 'now';
      var canvas = document.getElementById(canvasId);
      var loader = canvas ? canvas.nextElementSibling : null;
      if (!canvas) { return; }

      if (loader) { loader.style.display = 'block'; }
      if (currentChart) { currentChart.destroy(); }

      getJson(apiUrl(baseApi, 'graphs/get', { service_id: serverId, from: fromRange, to: toRange, avg: 300 })).then(function (res) {
        if (loader) { loader.style.display = 'none'; }
        if (!res.ok) { return; }

        var hist = ((res.data || {}).payload || {}).histdata || [];
        var labels = [];
        var values = [];

        hist.forEach(function (item) {
          labels.push(item.datetime || '');
          values.push(Number(item.value_raw || 0));
        });

        currentChart = new Chart(canvas, {
          type: 'line',
          data: {
            labels: labels,
            datasets: [{
              label: 'Traffic',
              data: values,
              borderColor: '#2f6fed',
              backgroundColor: 'rgba(47,111,237,0.12)',
              tension: 0.25,
              pointRadius: 0,
              fill: true,
            }],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
              x: { display: true },
              y: { display: true, beginAtZero: true },
            },
          },
        });
      }).catch(function () {
        if (loader) { loader.style.display = 'none'; }
      });
    }

    var btnsContainers = document.querySelectorAll('.dcmanage-graph-range-btns');
    btnsContainers.forEach(function (container) {
      var serverId = container.getAttribute('data-server-id');
      var canvasId = 'dcmanage-server-graph-' + serverId;
      var buttons = container.querySelectorAll('.dcmanage-graph-range');
      var customToggle = container.querySelector('.dcmanage-graph-custom-toggle');
      var customRange = container.closest('.dcmanage-form-card').querySelector('.dcmanage-graph-custom-range');
      var fromInput = customRange ? customRange.querySelector('.dcmanage-graph-from') : null;
      var toInput = customRange ? customRange.querySelector('.dcmanage-graph-to') : null;
      var applyBtn = customRange ? customRange.querySelector('.dcmanage-graph-apply') : null;

      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          buttons.forEach(function (b) { b.classList.remove('active'); });
          if (customToggle) customToggle.classList.remove('active');
          this.classList.add('active');
          if (customRange) customRange.style.display = 'none';
          var range = this.getAttribute('data-range');
          renderServerGraph(serverId, canvasId, range);
        });
      });

      if (customToggle && customRange && fromInput && toInput && applyBtn) {
        customToggle.addEventListener('click', function () {
          buttons.forEach(function (b) { b.classList.remove('active'); });
          this.classList.add('active');
          customRange.style.display = 'flex';
        });

        var nowBtn = customRange.querySelector('.dcmanage-graph-now');
        if (nowBtn) {
          nowBtn.addEventListener('click', function () {
            var now = new Date();
            var tzOff = now.getTimezoneOffset() * 60000;
            var localISOTime = (new Date(now - tzOff)).toISOString().slice(0, 16);
            toInput.value = localISOTime;
          });
        }

        applyBtn.addEventListener('click', function () {
          var fromVal = fromInput.value;
          var toVal = toInput.value;
          if (!fromVal) return;
          if (!toVal) toVal = 'now';
          else toVal = toVal.replace('T', ' '); // Make it look nicer for PHP strtotime

          fromVal = fromVal.replace('T', ' ');
          renderServerGraph(serverId, canvasId, fromVal, toVal);
        });
      }

      // Init first active
      var activeBtn = container.querySelector('.dcmanage-graph-range.active') || buttons[0];
      if (activeBtn) {
        renderServerGraph(serverId, canvasId, activeBtn.getAttribute('data-range') || '-2h');
      }
    });
  })();

  // --- Map Select2 to dcmanage-server-map ---
  (function initServerMapSelect2() {
    var serverMaps = document.querySelectorAll('.dcmanage-server-map');
    serverMaps.forEach(function (form) {
      if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
        // Find existing selects and apply Select2
        var prtgSelects = form.querySelectorAll('.dcmanage-monitor-prtg');
        var sensorSelects = form.querySelectorAll('.dcmanage-monitor-sensor');

        prtgSelects.forEach(function (sel) {
          jQuery(sel).select2({ theme: 'bootstrap4', width: '100%' });
          jQuery(sel).on('select2:select', function () { sel.dispatchEvent(new Event('change')); });
        });

        sensorSelects.forEach(function (sel) {
          jQuery(sel).select2({ theme: 'bootstrap4', width: '100%' });
          jQuery(sel).on('select2:select', function () { sel.dispatchEvent(new Event('change')); });
        });
      }
    });

    // Handle dynamically added rows for Select2
    document.addEventListener('dcmanage:rowAdded', function (e) {
      if (window.jQuery && jQuery.fn && jQuery.fn.select2 && e.detail && e.detail.row) {
        var row = e.detail.row;
        var prtgs = row.querySelectorAll('.dcmanage-monitor-prtg');
        var sensors = row.querySelectorAll('.dcmanage-monitor-sensor');

        prtgs.forEach(function (sel) {
          jQuery(sel).select2({ theme: 'bootstrap4', width: '100%' });
          jQuery(sel).on('select2:select', function () { sel.dispatchEvent(new Event('change')); });
        });

        sensors.forEach(function (sel) {
          jQuery(sel).select2({ theme: 'bootstrap4', width: '100%' });
          jQuery(sel).on('select2:select', function () { sel.dispatchEvent(new Event('change')); });
        });
      }
    });
  })();

})();
