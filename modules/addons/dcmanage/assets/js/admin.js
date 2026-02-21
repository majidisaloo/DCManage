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
    cronHealth: 'وضعیت کرون',
    ok: 'سالم',
    warn: 'هشدار',
    fail: 'خراب',
    versionCenter: 'مرکز نسخه و آپدیت',
    currentVersion: 'نسخه فعلی',
    latestRelease: 'آخرین ریلیز',
    checkUpdate: 'بررسی آپدیت',
    applyUpdate: 'اعمال آپدیت',
    autoUpdate: 'آپدیت خودکار',
    openCron: 'تنظیمات کرون'
  } : {
    dc: 'Datacenters',
    racks: 'Racks',
    switches: 'Switches',
    servers: 'Servers',
    ports: 'Ports',
    breaches: 'Breaches',
    queue: 'Queue',
    cronHealth: 'Cron Health',
    ok: 'OK',
    warn: 'Warning',
    fail: 'Fail',
    versionCenter: 'Version & Update Center',
    currentVersion: 'Current Version',
    latestRelease: 'Latest Release',
    checkUpdate: 'Check Update',
    applyUpdate: 'Apply Update',
    autoUpdate: 'Auto Update',
    openCron: 'Open Cron Settings'
  };

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
        { key: 'ports', label: T.ports, tab: 'ports' },
        { key: 'usage_breaches_today', label: T.breaches, tab: 'traffic' },
        { key: 'jobs_pending', label: T.queue, tab: 'automation' }
      ];

      var html = '<div class="row dcmanage-kpi">';
      cards.forEach(function (k) {
        html += '' +
          '<div class="col-md-3 col-6 mb-3">' +
          '<a href="' + moduleLink + '&tab=' + encodeURIComponent(k.tab) + '" class="text-decoration-none">' +
          '<div class="card dcmanage-click-card"><div class="card-body"><div class="dcmanage-kpi-label">' + safeText(k.label) + '</div><div class="dcmanage-kpi-value">' + (c[k.key] || 0) + '</div></div></div>' +
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
      var hasUpdate = d.has_update ? '<span class="badge badge-warning">Update Available</span>' : '<span class="badge badge-success">Up To Date</span>';
      var autoChecked = d.auto_update ? ' checked' : '';

      versionBox.innerHTML = '' +
        '<div class="p-3 dcmanage-version-card">' +
        '<div class="row align-items-center">' +
        '<div class="col-md-7 mb-2 mb-md-0">' +
        '<h5 class="mb-2">' + safeText(T.versionCenter) + '</h5>' +
        '<div>' + safeText(T.currentVersion) + ': <strong>' + safeText(d.current_version || '-') + '</strong></div>' +
        '<div>' + safeText(T.latestRelease) + ': <strong>' + safeText(d.latest_tag || '-') + '</strong> (' + safeText(d.latest_version || '-') + ')</div>' +
        '<div class="mt-1">' + hasUpdate + '</div>' +
        '</div>' +
        '<div class="col-md-5 text-md-right">' +
        '<div class="dcmanage-update-actions">' +
        '<div class="custom-control custom-switch mb-2">' +
        '<input type="checkbox" class="custom-control-input" id="dcmanage-auto-update"' + autoChecked + '>' +
        '<label class="custom-control-label" for="dcmanage-auto-update">' + safeText(T.autoUpdate) + '</label>' +
        '</div>' +
        '<button type="button" class="btn btn-outline-primary btn-sm mr-2" id="dcmanage-check-update">' + safeText(T.checkUpdate) + '</button>' +
        '<button type="button" class="btn btn-primary btn-sm" id="dcmanage-apply-update">' + safeText(T.applyUpdate) + '</button>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '<div id="dcmanage-update-msg" class="mt-2 small dcmanage-update-msg"></div>' +
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
      var cls = d.overall === 'ok' ? 'success' : (d.overall === 'fail' ? 'danger' : 'warning');
      var label = d.overall === 'ok' ? T.ok : (d.overall === 'fail' ? T.fail : T.warn);
      var rows = d.items || [];
      var html = '<div class="p-3 dcmanage-version-card">';
      html += '<div class="d-flex justify-content-between align-items-center mb-2">';
      html += '<h5 class="mb-0">' + safeText(T.cronHealth) + '</h5>';
      html += '<span class="badge badge-' + cls + '">' + safeText(label) + '</span>';
      html += '</div>';
      html += '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Task</th><th>Status</th><th>Last Run</th><th>Next Run</th></tr></thead><tbody>';
      rows.forEach(function (r) {
        var scls = r.status === 'ok' ? 'success' : (r.status === 'fail' ? 'danger' : 'warning');
        var slbl = r.status === 'ok' ? T.ok : (r.status === 'fail' ? T.fail : T.warn);
        html += '<tr><td>' + safeText(r.task) + '</td><td><span class="badge badge-' + scls + '">' + safeText(slbl) + '</span></td><td>' + safeText(r.last_run || '-') + '</td><td>' + safeText(r.next_run || '-') + '</td></tr>';
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
    var autoToggle = document.getElementById('dcmanage-auto-update');
    var msg = document.getElementById('dcmanage-update-msg');

    if (checkBtn) {
      checkBtn.addEventListener('click', function () {
        msg.innerHTML = 'Checking latest release...';
        getJson(apiUrl(base, 'update/check')).then(function (res) {
          if (!res.ok) {
            msg.innerHTML = '<span class="text-danger">' + safeText(res.error || 'check failed') + '</span>';
            return;
          }

          var d = res.data || {};
          msg.innerHTML = '<span class="text-success">Current: ' + safeText(d.current_version || '-') + ' | Latest: ' + safeText(d.latest_tag || '-') + '</span>';
          renderVersion(base);
        }).catch(function (e) {
          msg.innerHTML = '<span class="text-danger">' + safeText(e && e.message ? e.message : 'check failed') + '</span>';
        });
      });
    }

    if (applyBtn) {
      applyBtn.addEventListener('click', function () {
        msg.innerHTML = 'Applying update...';
        getJson(apiUrl(base, 'update/apply', { force: 0 })).then(function (res) {
          if (!res.ok) {
            msg.innerHTML = '<span class="text-danger">' + safeText(res.error || 'update failed') + '</span>';
            return;
          }

          var d = res.data || {};
          msg.innerHTML = '<span class="text-success">Update status: ' + safeText(d.status || 'done') + '</span>';
          renderVersion(base);
        }).catch(function (e) {
          msg.innerHTML = '<span class="text-danger">' + safeText(e && e.message ? e.message : 'update failed') + '</span>';
        });
      });
    }

    if (autoToggle) {
      autoToggle.addEventListener('change', function () {
        var enabled = autoToggle.checked ? 1 : 0;
        getJson(apiUrl(base, 'update/set-auto', { enabled: enabled })).then(function (res) {
          if (!res.ok) {
            msg.innerHTML = '<span class="text-danger">' + safeText(res.error || 'toggle failed') + '</span>';
            return;
          }

          msg.innerHTML = '<span class="text-success">Auto update ' + (autoToggle.checked ? 'enabled' : 'disabled') + '.</span>';
        }).catch(function (e) {
          msg.innerHTML = '<span class="text-danger">' + safeText(e && e.message ? e.message : 'toggle failed') + '</span>';
        });
      });
    }
  }

  try {
    var dashboard = document.getElementById('dcmanage-dashboard');
    if (dashboard) {
      var base = dashboard.getAttribute('data-api-base') || '';
      var moduleLink = dashboard.getAttribute('data-module-link') || 'addonmodules.php?module=dcmanage';
      renderDashboard(base, moduleLink);
      renderVersion(base);
      renderCron(base, moduleLink);
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
        var html = '<div class="table-responsive"><table class="table table-sm table-striped">' +
          '<thead><tr><th>Service</th><th>Status</th><th>Used (GB)</th><th>Allowed (GB)</th><th>Remaining (GB)</th><th>Cycle End</th></tr></thead><tbody>';

        rows.forEach(function (r) {
          html += '<tr>' +
            '<td>' + safeText(r.service_id) + '</td>' +
            '<td>' + safeText(r.status) + '</td>' +
            '<td>' + toGb(r.used_bytes) + '</td>' +
            '<td>' + toGb(r.allowed_bytes) + '</td>' +
            '<td>' + toGb(r.remaining_bytes) + '</td>' +
            '<td>' + safeText(r.cycle_end || '-') + '</td>' +
            '</tr>';
        });

        html += '</tbody></table></div>';
        traffic.innerHTML = html;

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
})();
