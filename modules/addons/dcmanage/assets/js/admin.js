(function () {
  function getJson(url) {
    return fetch(url, { credentials: 'same-origin' }).then(function (r) {
      return r.json();
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

  var dashboard = document.getElementById('dcmanage-dashboard');
  if (dashboard) {
    var base = dashboard.getAttribute('data-api-base') || '';

    loadDashboardHealth(base);
    loadVersionPanel(base);
  }

  function loadDashboardHealth(base) {
    getJson(base + 'dashboard/health').then(function (res) {
      if (!res.ok) {
        dashboard.innerHTML = '<div class="alert alert-danger">' + safeText(res.error || 'API error') + '</div>';
        return;
      }

      var c = res.data.counts || {};
      dashboard.innerHTML = '' +
        '<div class="row dcmanage-kpi">' +
        '<div class="col-md-2 col-6 mb-3"><div class="card"><div class="card-body"><small>DC</small><h4>' + (c.datacenters || 0) + '</h4></div></div></div>' +
        '<div class="col-md-2 col-6 mb-3"><div class="card"><div class="card-body"><small>Racks</small><h4>' + (c.racks || 0) + '</h4></div></div></div>' +
        '<div class="col-md-2 col-6 mb-3"><div class="card"><div class="card-body"><small>Servers</small><h4>' + (c.servers || 0) + '</h4></div></div></div>' +
        '<div class="col-md-2 col-6 mb-3"><div class="card"><div class="card-body"><small>Ports</small><h4>' + (c.ports || 0) + '</h4></div></div></div>' +
        '<div class="col-md-2 col-6 mb-3"><div class="card"><div class="card-body"><small>Breaches</small><h4>' + (c.usage_breaches_today || 0) + '</h4></div></div></div>' +
        '<div class="col-md-2 col-6 mb-3"><div class="card"><div class="card-body"><small>Queue</small><h4>' + (c.jobs_pending || 0) + '</h4></div></div></div>' +
        '</div>';
    });
  }

  function loadVersionPanel(base) {
    var versionBox = document.getElementById('dcmanage-version');
    if (!versionBox) {
      return;
    }

    getJson(base + 'dashboard/version').then(function (res) {
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
        '<h5 class="mb-2">Version & Update</h5>' +
        '<div>Current Version: <strong>' + safeText(d.current_version || '-') + '</strong></div>' +
        '<div>Latest Release: <strong>' + safeText(d.latest_tag || '-') + '</strong> (' + safeText(d.latest_version || '-') + ')</div>' +
        '<div class="mt-1">' + hasUpdate + '</div>' +
        '<div class="small text-muted mt-1">Source: ' + safeText(d.repo || '-') + ' / ' + safeText(d.branch || '-') + '</div>' +
        '</div>' +
        '<div class="col-md-5 text-md-right">' +
        '<div class="custom-control custom-switch mb-2">' +
        '<input type="checkbox" class="custom-control-input" id="dcmanage-auto-update"' + autoChecked + '>' +
        '<label class="custom-control-label" for="dcmanage-auto-update">Auto Update</label>' +
        '</div>' +
        '<button type="button" class="btn btn-outline-primary btn-sm mr-2" id="dcmanage-check-update">Check Update</button>' +
        '<button type="button" class="btn btn-primary btn-sm" id="dcmanage-apply-update">Apply Update</button>' +
        '</div>' +
        '</div>' +
        '<div id="dcmanage-update-msg" class="mt-2 small"></div>' +
        '</div>';

      bindVersionActions(base);
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
        getJson(base + 'update/check').then(function (res) {
          if (!res.ok) {
            msg.innerHTML = '<span class="text-danger">' + safeText(res.error || 'check failed') + '</span>';
            return;
          }

          var d = res.data || {};
          msg.innerHTML = '<span class="text-success">Current: ' + safeText(d.current_version || '-') + ' | Latest: ' + safeText(d.latest_tag || '-') + '</span>';
          loadVersionPanel(base);
        });
      });
    }

    if (applyBtn) {
      applyBtn.addEventListener('click', function () {
        msg.innerHTML = 'Applying update...';
        getJson(base + 'update/apply&force=0').then(function (res) {
          if (!res.ok) {
            msg.innerHTML = '<span class="text-danger">' + safeText(res.error || 'update failed') + '</span>';
            return;
          }

          var d = res.data || {};
          msg.innerHTML = '<span class="text-success">Update status: ' + safeText(d.status || 'done') + '</span>';
          loadVersionPanel(base);
        });
      });
    }

    if (autoToggle) {
      autoToggle.addEventListener('change', function () {
        var enabled = autoToggle.checked ? 1 : 0;
        getJson(base + 'update/set-auto&enabled=' + enabled).then(function (res) {
          if (!res.ok) {
            msg.innerHTML = '<span class="text-danger">' + safeText(res.error || 'toggle failed') + '</span>';
            return;
          }

          msg.innerHTML = '<span class="text-success">Auto Update ' + (autoToggle.checked ? 'enabled' : 'disabled') + '.</span>';
        });
      });
    }
  }

  var traffic = document.getElementById('dcmanage-traffic');
  if (traffic) {
    var baseTraffic = traffic.getAttribute('data-api-base') || '';
    getJson(baseTraffic + 'traffic/list').then(function (res) {
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
    });
  }

  function renderSampleChart(baseApi, serviceId) {
    var canvas = document.getElementById('dcmanage-traffic-chart');
    if (!canvas || typeof Chart === 'undefined') {
      return;
    }

    getJson(baseApi + 'graphs/get&service_id=' + encodeURIComponent(serviceId) + '&from=-24h&to=now&avg=300').then(function (res) {
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
    });
  }
})();
