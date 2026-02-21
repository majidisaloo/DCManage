<?php

declare(strict_types=1);

use DCManage\Api\Router;
use DCManage\Database\Schema;
use DCManage\Support\I18n;
use Illuminate\Database\Capsule\Manager as Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Bootstrap.php';

function dcmanage_config(): array
{
    return [
        'name' => 'DCManage',
        'description' => 'Datacenter Management Core (API-first) for WHMCS',
        'version' => DCManage\Version::CURRENT,
        'author' => 'MAJID ISALOO',
        'language' => 'english',
        'fields' => [
            'update_auto' => [
                'FriendlyName' => 'Enable Auto Update',
                'Type' => 'yesno',
                'Description' => 'Automatically check and apply new GitHub releases',
                'Default' => 'on',
            ],
        ],
    ];
}

function dcmanage_activate(): array
{
    try {
        Schema::migrate();

        return [
            'status' => 'success',
            'description' => 'DCManage activated successfully.',
        ];
    } catch (Throwable $e) {
        return [
            'status' => 'error',
            'description' => 'Activation failed: ' . $e->getMessage(),
        ];
    }
}

function dcmanage_deactivate(): array
{
    return [
        'status' => 'success',
        'description' => 'DCManage deactivated. Data is preserved.',
    ];
}

function dcmanage_upgrade(array $vars): void
{
    Schema::migrate();
}

function dcmanage_output(array $vars): void
{
    if ((int) ($_GET['dcmanage_api'] ?? 0) === 1) {
        Router::dispatch($_GET['endpoint'] ?? 'dashboard/health');
        return;
    }

    $moduleLink = 'addonmodules.php?module=dcmanage';
    $lang = I18n::resolveCurrentLanguage();
    $isRtl = $lang === 'fa';
    $activeTab = $_GET['tab'] ?? 'dashboard';

    $flash = dcmanage_handle_actions($lang);

    echo '<div class="container-fluid dcmanage-shell ' . ($isRtl ? 'dcmanage-rtl' : 'dcmanage-ltr') . '" dir="' . ($isRtl ? 'rtl' : 'ltr') . '" data-lang="' . htmlspecialchars($lang) . '">';

    if ($flash !== '') {
        echo $flash;
    }

    $tabs = [
        'dashboard' => I18n::t('tab_dashboard', $lang),
        'datacenters' => I18n::t('tab_datacenters', $lang),
        'networks' => I18n::t('tab_networks', $lang),
        'switches' => I18n::t('tab_switches', $lang),
        'servers' => I18n::t('tab_servers', $lang),
        'ports' => I18n::t('tab_ports', $lang),
        'ilos' => 'iLOs',
        'prtg' => 'PRTG',
        'packages' => I18n::t('tab_packages', $lang),
        'scope' => I18n::t('tab_scope', $lang),
        'traffic' => I18n::t('tab_traffic', $lang),
        'automation' => I18n::t('tab_automation', $lang),
        'settings' => I18n::t('tab_settings', $lang),
        'logs' => I18n::t('tab_logs', $lang),
    ];

    echo '<ul class="nav nav-tabs dcmanage-tabs" role="tablist">';
    foreach ($tabs as $key => $label) {
        $active = $activeTab === $key ? ' active' : '';
        echo '<li class="nav-item">';
        echo '<a class="nav-link' . $active . '" href="' . $moduleLink . '&tab=' . urlencode($key) . '">' . htmlspecialchars($label) . '</a>';
        echo '</li>';
    }
    echo '</ul>';

    echo '<div class="card mt-3 border-0 shadow-sm"><div class="card-body">';

    if ($activeTab === 'dashboard') {
        echo '<div id="dcmanage-dashboard" data-module-link="' . $moduleLink . '" data-api-base="' . $moduleLink . '&dcmanage_api=1&endpoint="></div>';
        echo '<div id="dcmanage-version" class="mt-3" data-api-base="' . $moduleLink . '&dcmanage_api=1&endpoint="></div>';
        echo '<div id="dcmanage-cron" class="mt-3" data-api-base="' . $moduleLink . '&dcmanage_api=1&endpoint="></div>';
        echo '<div class="alert alert-info mt-3 mb-0">' . htmlspecialchars(I18n::t('dashboard_info', $lang)) . '</div>';
    } elseif ($activeTab === 'traffic') {
        echo '<div id="dcmanage-traffic" data-api-base="' . $moduleLink . '&dcmanage_api=1&endpoint="></div>';
        echo '<div style="height:340px"><canvas id="dcmanage-traffic-chart" height="120"></canvas></div>';
    } elseif ($activeTab === 'settings') {
        dcmanage_render_settings_form($lang);
    } elseif ($activeTab === 'datacenters') {
        dcmanage_render_datacenters($lang);
    } elseif ($activeTab === 'switches') {
        dcmanage_render_switches($lang);
    } elseif ($activeTab === 'servers') {
        dcmanage_render_servers($lang);
    } elseif ($activeTab === 'logs') {
        dcmanage_render_logs($lang);
    } else {
        echo '<div class="alert alert-secondary mb-0">';
        echo htmlspecialchars(I18n::t('crud_placeholder_prefix', $lang)) . ' <strong>' . htmlspecialchars($tabs[$activeTab] ?? I18n::t('section', $lang)) . '</strong> ' . htmlspecialchars(I18n::t('crud_placeholder_suffix', $lang));
        echo '</div>';
    }

    echo '</div></div>';
    echo '</div>';

    echo '<link rel="stylesheet" href="../modules/addons/dcmanage/assets/css/admin.css?v=' . rawurlencode(DCManage\Version::CURRENT) . '">';
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
    echo '<script src="../modules/addons/dcmanage/assets/js/admin.js?v=' . rawurlencode(DCManage\Version::CURRENT) . '"></script>';
}

function dcmanage_handle_actions(string $lang): string
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return '';
    }

    $action = (string) ($_POST['dcmanage_action'] ?? '');

    try {
        if ($action === 'settings_save') {
            dcmanage_handle_settings_save();
            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
        }

        if ($action === 'datacenter_create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Datacenter name is required');
            }

            $rackCount = max(0, (int) ($_POST['rack_count'] ?? 0));
            $rackUnits = max(1, (int) ($_POST['rack_units'] ?? 42));

            $dcId = (int) Capsule::table('mod_dcmanage_datacenters')->insertGetId([
                'name' => $name,
                'code' => null,
                'location' => trim((string) ($_POST['location'] ?? '')),
                'notes' => trim((string) ($_POST['notes'] ?? '')),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            for ($i = 1; $i <= $rackCount; $i++) {
                Capsule::table('mod_dcmanage_racks')->insert([
                    'dc_id' => $dcId,
                    'name' => $name . '-R' . $i,
                    'total_u' => $rackUnits,
                ]);
            }

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('created', $lang)) . '</div>';
        }

        if ($action === 'switch_create') {
            $dcId = (int) ($_POST['dc_id'] ?? 0);
            $rackId = (int) ($_POST['rack_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));

            if ($dcId <= 0 || $name === '') {
                throw new RuntimeException('Datacenter and switch name are required');
            }

            Capsule::table('mod_dcmanage_switches')->insert([
                'dc_id' => $dcId,
                'rack_id' => $rackId > 0 ? $rackId : null,
                'u_start' => (int) ($_POST['u_start'] ?? 0) ?: null,
                'u_height' => max(1, (int) ($_POST['u_height'] ?? 1)),
                'name' => $name,
                'vendor' => trim((string) ($_POST['vendor'] ?? '')),
                'model' => trim((string) ($_POST['model'] ?? '')),
                'mgmt_ip' => trim((string) ($_POST['mgmt_ip'] ?? '')),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('created', $lang)) . '</div>';
        }

        if ($action === 'server_create') {
            $dcId = (int) ($_POST['dc_id'] ?? 0);
            $rackId = (int) ($_POST['rack_id'] ?? 0);
            $hostname = trim((string) ($_POST['hostname'] ?? ''));
            if ($dcId <= 0 || $hostname === '') {
                throw new RuntimeException('Datacenter and hostname are required');
            }

            Capsule::table('mod_dcmanage_servers')->insert([
                'dc_id' => $dcId,
                'rack_id' => $rackId > 0 ? $rackId : null,
                'hostname' => $hostname,
                'asset_tag' => trim((string) ($_POST['asset_tag'] ?? '')),
                'serial' => trim((string) ($_POST['serial'] ?? '')),
                'u_start' => (int) ($_POST['u_start'] ?? 0) ?: null,
                'u_height' => max(1, (int) ($_POST['u_height'] ?? 1)),
                'notes' => trim((string) ($_POST['notes'] ?? '')),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('created', $lang)) . '</div>';
        }
    } catch (Throwable $e) {
        return '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
    }

    return '';
}

function dcmanage_system_defaults(): array
{
    return [
        'timezone' => 'Asia/Tehran',
        'locale' => I18n::resolveCurrentLanguage(),
        'traffic_poll_minutes' => '5',
        'graph_cache_ttl_minutes' => '30',
        'log_retention_days' => '90',
        'dashboard_refresh_seconds' => '30',
    ];
}

function dcmanage_system_settings(): array
{
    $defaults = dcmanage_system_defaults();
    $settings = $defaults;

    foreach (array_keys($defaults) as $key) {
        $row = Capsule::table('mod_dcmanage_meta')->where('meta_key', 'settings.' . $key)->value('meta_value');
        if ($row !== null && $row !== '') {
            $settings[$key] = (string) $row;
        }
    }

    return $settings;
}

function dcmanage_handle_settings_save(): void
{
    $defaults = dcmanage_system_defaults();
    foreach (array_keys($defaults) as $key) {
        $value = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
        if ($value === '') {
            $value = $defaults[$key];
        }

        Capsule::table('mod_dcmanage_meta')->updateOrInsert(
            ['meta_key' => 'settings.' . $key],
            ['meta_value' => $value, 'updated_at' => date('Y-m-d H:i:s')]
        );
    }
}

function dcmanage_cron_defs(): array
{
    return [
        ['task' => 'poll_usage', 'interval' => 300, 'cron' => '*/5 * * * * php -q /path/to/whmcs/modules/addons/dcmanage/cron.php poll_usage'],
        ['task' => 'enforce_queue', 'interval' => 60, 'cron' => '* * * * * php -q /path/to/whmcs/modules/addons/dcmanage/cron.php enforce_queue'],
        ['task' => 'graph_warm', 'interval' => 1800, 'cron' => '*/30 * * * * php -q /path/to/whmcs/modules/addons/dcmanage/cron.php graph_warm'],
        ['task' => 'cleanup', 'interval' => 86400, 'cron' => '12 2 * * * php -q /path/to/whmcs/modules/addons/dcmanage/cron.php cleanup'],
        ['task' => 'self_update', 'interval' => 86400, 'cron' => '30 3 * * * php -q /path/to/whmcs/modules/addons/dcmanage/cron.php self_update'],
    ];
}

function dcmanage_cron_status(): array
{
    $defs = dcmanage_cron_defs();
    $items = [];
    $ok = 0;
    $fail = 0;

    foreach ($defs as $def) {
        $last = Capsule::table('mod_dcmanage_logs')
            ->where('source', 'cron')
            ->where('message', 'like', 'task:' . $def['task'] . ' %')
            ->orderBy('id', 'desc')
            ->first(['message', 'created_at']);

        $status = 'fail';
        $lastAt = '-';
        $nextAt = '-';

        if ($last !== null) {
            $lastAt = (string) $last->created_at;
            $ts = strtotime($lastAt) ?: null;
            if ($ts !== null) {
                $nextAt = date('Y-m-d H:i:s', $ts + (int) $def['interval']);
                $age = time() - $ts;
                if (str_contains((string) $last->message, 'completed') && $age <= ((int) $def['interval'] * 2)) {
                    $status = 'ok';
                } else {
                    $status = 'warning';
                }
            }
        }

        if ($status === 'ok') {
            $ok++;
        }
        if ($status === 'fail') {
            $fail++;
        }

        $items[] = [
            'task' => $def['task'],
            'status' => $status,
            'last' => $lastAt,
            'next' => $nextAt,
            'cron' => $def['cron'],
        ];
    }

    $overall = 'warning';
    if ($ok === count($defs)) {
        $overall = 'ok';
    } elseif ($fail === count($defs)) {
        $overall = 'fail';
    }

    return ['overall' => $overall, 'items' => $items];
}

function dcmanage_render_settings_form(string $lang): void
{
    $settings = dcmanage_system_settings();
    $cron = dcmanage_cron_status();

    echo '<h5 class="mb-3">' . htmlspecialchars(I18n::t('system_settings', $lang)) . '</h5>';
    echo '<form method="post" action="">';
    echo '<input type="hidden" name="dcmanage_action" value="settings_save">';
    echo '<div class="row">';

    echo '<div class="col-md-4 mb-3"><label>Timezone</label><input class="form-control dcmanage-input" name="timezone" value="' . htmlspecialchars($settings['timezone']) . '"></div>';
    echo '<div class="col-md-4 mb-3"><label>Locale</label><select class="form-control dcmanage-input" name="locale">';
    $locales = ['fa' => 'Persian', 'en' => 'English'];
    foreach ($locales as $k => $v) {
        $selected = $settings['locale'] === $k ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($k) . '"' . $selected . '>' . htmlspecialchars($v) . '</option>';
    }
    echo '</select></div>';

    echo '<div class="col-md-4 mb-3"><label>Traffic Poll Minutes</label><input class="form-control dcmanage-input" name="traffic_poll_minutes" value="' . htmlspecialchars($settings['traffic_poll_minutes']) . '"></div>';
    echo '<div class="col-md-4 mb-3"><label>Graph Cache TTL (Minutes)</label><input class="form-control dcmanage-input" name="graph_cache_ttl_minutes" value="' . htmlspecialchars($settings['graph_cache_ttl_minutes']) . '"></div>';
    echo '<div class="col-md-4 mb-3"><label>Log Retention (Days)</label><input class="form-control dcmanage-input" name="log_retention_days" value="' . htmlspecialchars($settings['log_retention_days']) . '"></div>';
    echo '<div class="col-md-4 mb-3"><label>Dashboard Refresh (Seconds)</label><input class="form-control dcmanage-input" name="dashboard_refresh_seconds" value="' . htmlspecialchars($settings['dashboard_refresh_seconds']) . '"></div>';

    echo '</div>';
    echo '<button type="submit" class="btn btn-primary">' . htmlspecialchars(I18n::t('save_settings', $lang)) . '</button>';
    echo '</form>';

    $overallClass = $cron['overall'] === 'ok' ? 'success' : ($cron['overall'] === 'fail' ? 'danger' : 'warning');
    echo '<hr class="my-4">';
    echo '<h5 class="mb-3">' . htmlspecialchars(I18n::t('cron_monitor', $lang)) . ' <span class="badge badge-' . $overallClass . '">' . htmlspecialchars(I18n::t('cron_overall', $lang)) . '</span></h5>';
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm table-bordered">';
    echo '<thead><tr><th>' . htmlspecialchars(I18n::t('cron_task', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('cron_status', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('cron_last_run', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('cron_next_run', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('cron_command', $lang)) . '</th></tr></thead><tbody>';

    foreach ($cron['items'] as $item) {
        $cls = $item['status'] === 'ok' ? 'success' : ($item['status'] === 'fail' ? 'danger' : 'warning');
        $label = $item['status'] === 'ok' ? I18n::t('status_ok', $lang) : ($item['status'] === 'fail' ? I18n::t('status_fail', $lang) : I18n::t('status_warning', $lang));
        echo '<tr>';
        echo '<td>' . htmlspecialchars($item['task']) . '</td>';
        echo '<td><span class="badge badge-' . $cls . '">' . htmlspecialchars($label) . '</span></td>';
        echo '<td>' . htmlspecialchars($item['last']) . '</td>';
        echo '<td>' . htmlspecialchars($item['next']) . '</td>';
        echo '<td><code>' . htmlspecialchars($item['cron']) . '</code></td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function dcmanage_render_datacenters(string $lang): void
{
    $rows = Capsule::table('mod_dcmanage_datacenters as d')
        ->leftJoin('mod_dcmanage_racks as r', 'r.dc_id', '=', 'd.id')
        ->groupBy('d.id', 'd.name', 'd.code', 'd.location', 'd.created_at')
        ->orderBy('d.id', 'desc')
        ->get([
            'd.id', 'd.name', 'd.code', 'd.location', 'd.created_at',
            Capsule::raw('COUNT(r.id) as rack_count'),
        ]);

    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
    echo '<h5 class="mb-0">' . htmlspecialchars(I18n::t('tab_datacenters', $lang)) . '</h5>';
    echo '<button class="btn btn-primary btn-sm" type="button" data-toggle="collapse" data-target="#dcmanage-dc-add">' . htmlspecialchars(I18n::t('datacenter_add', $lang)) . '</button>';
    echo '</div>';

    echo '<div class="collapse mb-4" id="dcmanage-dc-add">';
    echo '<form method="post" action="" class="dcmanage-form-card">';
    echo '<input type="hidden" name="dcmanage_action" value="datacenter_create">';
    echo '<div class="form-row">';
    echo '<div class="form-group col-md-4"><label>' . htmlspecialchars(I18n::t('datacenter_name', $lang)) . '</label><input required name="name" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-4"><label>' . htmlspecialchars(I18n::t('datacenter_location', $lang)) . '</label><input name="location" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-2"><label>' . htmlspecialchars(I18n::t('datacenter_rack_count', $lang)) . '</label><input type="number" min="0" name="rack_count" value="0" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-2"><label>' . htmlspecialchars(I18n::t('datacenter_rack_units', $lang)) . '</label><input type="number" min="1" name="rack_units" value="42" class="form-control dcmanage-input"></div>';
    echo '</div>';
    echo '<div class="form-group"><label>Notes</label><textarea name="notes" class="form-control dcmanage-input" rows="2"></textarea></div>';
    echo '<button class="btn btn-primary" type="submit">' . htmlspecialchars(I18n::t('create_datacenter', $lang)) . '</button>';
    echo '</form>';
    echo '</div>';

    echo '<div class="table-responsive mb-4"><table class="table table-sm table-striped">';
    echo '<thead><tr><th>ID</th><th>' . htmlspecialchars(I18n::t('datacenter_name', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('datacenter_location', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('datacenter_rack_count', $lang)) . '</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . (int) $row->id . '</td><td>' . htmlspecialchars((string) $row->name) . '</td><td>' . htmlspecialchars((string) $row->location) . '</td><td>' . (int) $row->rack_count . '</td></tr>';
    }
    echo '</tbody></table></div>';

    foreach ($rows as $row) {
        $racks = Capsule::table('mod_dcmanage_racks')->where('dc_id', (int) $row->id)->orderBy('name')->get();
        echo '<div class="dcmanage-rack-block mb-4">';
        echo '<h6>' . htmlspecialchars((string) $row->name) . ' - ' . htmlspecialchars(I18n::t('datacenter_rack_count', $lang)) . ': ' . (int) $row->rack_count . '</h6>';
        echo '<div class="row">';
        foreach ($racks as $rack) {
            $units = max(1, (int) $rack->total_u);
            $usage = dcmanage_rack_usage((int) $rack->id, $units);
            echo '<div class="col-lg-4 col-md-6 mb-3">';
            echo '<div class="card"><div class="card-body">';
            echo '<h6 class="mb-2">' . htmlspecialchars((string) $rack->name) . ' (' . $units . 'U)</h6>';
            echo '<div class="dcmanage-rack-grid">';
            for ($u = $units; $u >= 1; $u--) {
                $cell = $usage[$u] ?? '';
                $cls = $cell === '' ? 'blank' : (strpos($cell, 'SW:') === 0 ? 'switch' : 'server');
                echo '<div class="dcmanage-rack-u ' . $cls . '"><span class="u-num">U' . $u . '</span><span class="u-item">' . htmlspecialchars($cell === '' ? '-' : $cell) . '</span></div>';
            }
            echo '</div>';
            echo '</div></div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }
}

function dcmanage_rack_usage(int $rackId, int $totalU): array
{
    $usage = [];
    $servers = Capsule::table('mod_dcmanage_servers')->where('rack_id', $rackId)->get(['hostname', 'u_start', 'u_height']);
    foreach ($servers as $s) {
        $start = (int) ($s->u_start ?? 0);
        $height = max(1, (int) ($s->u_height ?? 1));
        if ($start <= 0) {
            continue;
        }
        for ($i = 0; $i < $height; $i++) {
            $u = $start + $i;
            if ($u >= 1 && $u <= $totalU) {
                $usage[$u] = 'SRV:' . (string) $s->hostname;
            }
        }
    }

    $switches = Capsule::table('mod_dcmanage_switches')->where('rack_id', $rackId)->get(['name', 'u_start', 'u_height']);
    foreach ($switches as $s) {
        $start = (int) ($s->u_start ?? 0);
        $height = max(1, (int) ($s->u_height ?? 1));
        if ($start <= 0) {
            continue;
        }
        for ($i = 0; $i < $height; $i++) {
            $u = $start + $i;
            if ($u >= 1 && $u <= $totalU) {
                $usage[$u] = 'SW:' . (string) $s->name;
            }
        }
    }

    return $usage;
}

function dcmanage_render_switches(string $lang): void
{
    $dcs = Capsule::table('mod_dcmanage_datacenters')->orderBy('name')->get(['id', 'name']);
    $racks = Capsule::table('mod_dcmanage_racks')->orderBy('name')->get(['id', 'dc_id', 'name']);

    $rows = Capsule::table('mod_dcmanage_switches as s')
        ->leftJoin('mod_dcmanage_datacenters as d', 'd.id', '=', 's.dc_id')
        ->leftJoin('mod_dcmanage_racks as r', 'r.id', '=', 's.rack_id')
        ->orderBy('s.id', 'desc')
        ->limit(200)
        ->get(['s.id', 's.name', 's.vendor', 's.model', 's.mgmt_ip', 's.u_start', 's.u_height', 'd.name as dc_name', 'r.name as rack_name']);

    echo '<div class="row">';
    echo '<div class="col-lg-5 mb-4">';
    echo '<h5>' . htmlspecialchars(I18n::t('switch_add', $lang)) . '</h5>';
    echo '<form method="post" action="" class="dcmanage-form-card">';
    echo '<input type="hidden" name="dcmanage_action" value="switch_create">';

    echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('select_datacenter', $lang)) . '</label><select name="dc_id" id="dcmanage-switch-dc" required class="form-control dcmanage-input">';
    echo '<option value="">--</option>';
    foreach ($dcs as $dc) {
        echo '<option value="' . (int) $dc->id . '">' . htmlspecialchars((string) $dc->name) . '</option>';
    }
    echo '</select></div>';

    echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('select_rack', $lang)) . '</label><select name="rack_id" id="dcmanage-switch-rack" class="form-control dcmanage-input">';
    echo '<option value="">--</option>';
    foreach ($racks as $rack) {
        echo '<option data-dc-id="' . (int) $rack->dc_id . '" value="' . (int) $rack->id . '">' . htmlspecialchars((string) $rack->name) . '</option>';
    }
    echo '</select></div>';

    echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('switch_name', $lang)) . '</label><input required name="name" class="form-control dcmanage-input"></div>';
    echo '<div class="form-row">';
    echo '<div class="form-group col-md-6"><label>' . htmlspecialchars(I18n::t('switch_vendor', $lang)) . '</label><input name="vendor" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-6"><label>' . htmlspecialchars(I18n::t('switch_model', $lang)) . '</label><input name="model" class="form-control dcmanage-input"></div>';
    echo '</div>';
    echo '<div class="form-row">';
    echo '<div class="form-group col-md-6"><label>U Start</label><input type="number" min="0" name="u_start" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-6"><label>U Height</label><input type="number" min="1" name="u_height" value="1" class="form-control dcmanage-input"></div>';
    echo '</div>';
    echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('switch_mgmt_ip', $lang)) . '</label><input name="mgmt_ip" class="form-control dcmanage-input"></div>';
    echo '<button class="btn btn-primary" type="submit">' . htmlspecialchars(I18n::t('create_switch', $lang)) . '</button>';
    echo '</form>';
    echo '</div>';

    echo '<div class="col-lg-7">';
    echo '<h5 class="mb-3">' . htmlspecialchars(I18n::t('tab_switches', $lang)) . '</h5>';
    echo '<div class="table-responsive"><table class="table table-sm table-striped">';
    echo '<thead><tr><th>ID</th><th>' . htmlspecialchars(I18n::t('switch_name', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('tab_datacenters', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('select_rack', $lang)) . '</th><th>U</th><th>' . htmlspecialchars(I18n::t('switch_mgmt_ip', $lang)) . '</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $u = ($row->u_start !== null ? (string) $row->u_start : '-') . '/' . (string) ($row->u_height ?? 1);
        echo '<tr><td>' . (int) $row->id . '</td><td>' . htmlspecialchars((string) $row->name) . '</td><td>' . htmlspecialchars((string) $row->dc_name) . '</td><td>' . htmlspecialchars((string) $row->rack_name) . '</td><td>' . htmlspecialchars($u) . '</td><td>' . htmlspecialchars((string) $row->mgmt_ip) . '</td></tr>';
    }
    echo '</tbody></table></div>';
    echo '</div>';
    echo '</div>';

    echo '<script>';
    echo '(function(){var dc=document.getElementById("dcmanage-switch-dc");var rack=document.getElementById("dcmanage-switch-rack");if(!dc||!rack){return;}';
    echo 'function filter(){var v=dc.value;for(var i=0;i<rack.options.length;i++){var o=rack.options[i];if(!o.value){o.hidden=false;continue;}o.hidden=(v!==""&&o.getAttribute("data-dc-id")!==v);}if(rack.selectedIndex>0&&rack.options[rack.selectedIndex].hidden){rack.selectedIndex=0;}}';
    echo 'dc.addEventListener("change",filter);filter();})();';
    echo '</script>';
}

function dcmanage_render_servers(string $lang): void
{
    $dcs = Capsule::table('mod_dcmanage_datacenters')->orderBy('name')->get(['id', 'name']);
    $racks = Capsule::table('mod_dcmanage_racks')->orderBy('name')->get(['id', 'dc_id', 'name']);

    $rows = Capsule::table('mod_dcmanage_servers as s')
        ->leftJoin('mod_dcmanage_datacenters as d', 'd.id', '=', 's.dc_id')
        ->leftJoin('mod_dcmanage_racks as r', 'r.id', '=', 's.rack_id')
        ->orderBy('s.id', 'desc')
        ->limit(200)
        ->get(['s.id', 's.hostname', 's.asset_tag', 's.serial', 's.u_start', 's.u_height', 'd.name as dc_name', 'r.name as rack_name']);

    echo '<div class="row">';
    echo '<div class="col-lg-5 mb-4">';
    echo '<h5>' . htmlspecialchars(I18n::t('server_add', $lang)) . '</h5>';
    echo '<form method="post" action="" class="dcmanage-form-card">';
    echo '<input type="hidden" name="dcmanage_action" value="server_create">';

    echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('select_datacenter', $lang)) . '</label><select name="dc_id" id="dcmanage-server-dc" required class="form-control dcmanage-input">';
    echo '<option value="">--</option>';
    foreach ($dcs as $dc) {
        echo '<option value="' . (int) $dc->id . '">' . htmlspecialchars((string) $dc->name) . '</option>';
    }
    echo '</select></div>';

    echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('select_rack', $lang)) . '</label><select name="rack_id" id="dcmanage-server-rack" class="form-control dcmanage-input">';
    echo '<option value="">--</option>';
    foreach ($racks as $rack) {
        echo '<option data-dc-id="' . (int) $rack->dc_id . '" value="' . (int) $rack->id . '">' . htmlspecialchars((string) $rack->name) . '</option>';
    }
    echo '</select></div>';

    echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('server_hostname', $lang)) . '</label><input required name="hostname" class="form-control dcmanage-input"></div>';
    echo '<div class="form-row">';
    echo '<div class="form-group col-md-6"><label>Asset Tag</label><input name="asset_tag" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-6"><label>Serial</label><input name="serial" class="form-control dcmanage-input"></div>';
    echo '</div>';
    echo '<div class="form-row">';
    echo '<div class="form-group col-md-6"><label>U Start</label><input type="number" min="0" name="u_start" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-6"><label>U Height</label><input type="number" min="1" name="u_height" value="1" class="form-control dcmanage-input"></div>';
    echo '</div>';
    echo '<div class="form-group"><label>Notes</label><textarea name="notes" class="form-control dcmanage-input" rows="3"></textarea></div>';
    echo '<button class="btn btn-primary" type="submit">' . htmlspecialchars(I18n::t('create_server', $lang)) . '</button>';
    echo '</form>';
    echo '</div>';

    echo '<div class="col-lg-7">';
    echo '<h5 class="mb-3">' . htmlspecialchars(I18n::t('tab_servers', $lang)) . '</h5>';
    echo '<div class="table-responsive"><table class="table table-sm table-striped">';
    echo '<thead><tr><th>ID</th><th>' . htmlspecialchars(I18n::t('server_hostname', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('tab_datacenters', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('select_rack', $lang)) . '</th><th>U</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $u = ($row->u_start !== null ? (string) $row->u_start : '-') . '/' . (string) $row->u_height;
        echo '<tr><td>' . (int) $row->id . '</td><td>' . htmlspecialchars((string) $row->hostname) . '</td><td>' . htmlspecialchars((string) $row->dc_name) . '</td><td>' . htmlspecialchars((string) $row->rack_name) . '</td><td>' . htmlspecialchars($u) . '</td></tr>';
    }
    echo '</tbody></table></div>';
    echo '</div>';
    echo '</div>';

    echo '<script>';
    echo '(function(){var dc=document.getElementById("dcmanage-server-dc");var rack=document.getElementById("dcmanage-server-rack");if(!dc||!rack){return;}';
    echo 'function filter(){var v=dc.value;for(var i=0;i<rack.options.length;i++){var o=rack.options[i];if(!o.value){o.hidden=false;continue;}o.hidden=(v!==""&&o.getAttribute("data-dc-id")!==v);}if(rack.selectedIndex>0&&rack.options[rack.selectedIndex].hidden){rack.selectedIndex=0;}}';
    echo 'dc.addEventListener("change",filter);filter();})();';
    echo '</script>';
}

function dcmanage_render_logs(string $lang): void
{
    $purchaseRows = Capsule::table('mod_dcmanage_purchases as p')
        ->leftJoin('mod_dcmanage_packages as pk', 'pk.id', '=', 'p.package_id')
        ->orderBy('p.id', 'desc')
        ->limit(200)
        ->get(['p.id', 'p.whmcs_serviceid', 'p.userid', 'p.size_gb', 'p.price', 'p.invoiceid', 'p.created_at', 'pk.name as package_name']);

    $logRows = Capsule::table('mod_dcmanage_logs')->orderBy('id', 'desc')->limit(200)->get(['id', 'level', 'source', 'message', 'created_at']);

    echo '<h5 class="mb-3">' . htmlspecialchars(I18n::t('purchase_logs', $lang)) . '</h5>';
    echo '<div class="table-responsive mb-4"><table class="table table-sm table-striped">';
    echo '<thead><tr><th>ID</th><th>Service</th><th>User</th><th>Package</th><th>GB</th><th>Price</th><th>Invoice</th><th>Date</th></tr></thead><tbody>';
    foreach ($purchaseRows as $row) {
        echo '<tr><td>' . (int) $row->id . '</td><td>' . (int) $row->whmcs_serviceid . '</td><td>' . (int) $row->userid . '</td><td>' . htmlspecialchars((string) $row->package_name) . '</td><td>' . htmlspecialchars((string) $row->size_gb) . '</td><td>' . htmlspecialchars((string) $row->price) . '</td><td>' . htmlspecialchars((string) $row->invoiceid) . '</td><td>' . htmlspecialchars((string) $row->created_at) . '</td></tr>';
    }
    echo '</tbody></table></div>';

    echo '<h5 class="mb-3">' . htmlspecialchars(I18n::t('logs_system', $lang)) . '</h5>';
    echo '<div class="table-responsive"><table class="table table-sm table-striped">';
    echo '<thead><tr><th>ID</th><th>Level</th><th>Source</th><th>Message</th><th>Date</th></tr></thead><tbody>';
    foreach ($logRows as $row) {
        echo '<tr><td>' . (int) $row->id . '</td><td>' . htmlspecialchars((string) $row->level) . '</td><td>' . htmlspecialchars((string) $row->source) . '</td><td>' . htmlspecialchars((string) $row->message) . '</td><td>' . htmlspecialchars((string) $row->created_at) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
