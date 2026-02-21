(function () {
  function getJson(url) {
    return fetch(url, { credentials: 'same-origin' }).then(function (r) {
      return r.json();
    });
  }

  function toGb(bytes) {
    return (bytes / 1073741824).toFixed(2);
  }

  var dashboard = document.getElementById('dcmanage-dashboard');
  if (dashboard) {
    var base = dashboard.getAttribute('data-api-base') || '';
    getJson(base + 'dashboard/health').then(function (res) {
      if (!res.ok) {
        dashboard.innerHTML = '<div class="alert alert-danger">' + (res.error || 'API error') + '</div>';
        return;
      }

      var c = res.data.counts || {};
      dashboard.innerHTML = '' +
        '<div class="row">' +
        '<div class="col-md-2 col-6 mb-3"><div class="card"><div class="card-body"><small>DC</small><h4>' + (c.datacenters || 0) + '</h4></div></div></div>' +
        '<div class="col-md-2 col-6 mb-3"><div class="card"><div class="card-body"><small>Racks</small><h4>' + (c.racks || 0) + '</h4></div></div></div>' +
        '<div class="col-md-2 col-6 mb-3"><div class="card"><div class="card-body"><small>Servers</small><h4>' + (c.servers || 0) + '</h4></div></div></div>' +
        '<div class="col-md-2 col-6 mb-3"><div class="card"><div class="card-body"><small>Ports</small><h4>' + (c.ports || 0) + '</h4></div></div></div>' +
        '<div class="col-md-2 col-6 mb-3"><div class="card"><div class="card-body"><small>Breaches</small><h4>' + (c.usage_breaches_today || 0) + '</h4></div></div></div>' +
        '<div class="col-md-2 col-6 mb-3"><div class="card"><div class="card-body"><small>Queue</small><h4>' + (c.jobs_pending || 0) + '</h4></div></div></div>' +
        '</div>';
    });
  }

  var traffic = document.getElementById('dcmanage-traffic');
  if (traffic) {
    var baseTraffic = traffic.getAttribute('data-api-base') || '';
    getJson(baseTraffic + 'traffic/list').then(function (res) {
      if (!res.ok) {
        traffic.innerHTML = '<div class="alert alert-danger">' + (res.error || 'API error') + '</div>';
        return;
      }

      var rows = res.data || [];
      var html = '<div class="table-responsive"><table class="table table-sm table-striped">' +
        '<thead><tr><th>Service</th><th>Status</th><th>Used (GB)</th><th>Allowed (GB)</th><th>Remaining (GB)</th><th>Cycle End</th></tr></thead><tbody>';

      rows.forEach(function (r) {
        html += '<tr>' +
          '<td>' + r.service_id + '</td>' +
          '<td>' + r.status + '</td>' +
          '<td>' + toGb(r.used_bytes) + '</td>' +
          '<td>' + toGb(r.allowed_bytes) + '</td>' +
          '<td>' + toGb(r.remaining_bytes) + '</td>' +
          '<td>' + (r.cycle_end || '-') + '</td>' +
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
