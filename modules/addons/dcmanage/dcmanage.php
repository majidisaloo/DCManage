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

    Schema::migrate();

    $moduleLink = 'addonmodules.php?module=dcmanage';
    $lang = I18n::resolveCurrentLanguage();
    $isRtl = $lang === 'fa';
    $activeTab = $_GET['tab'] ?? 'dashboard';
    if ($activeTab === 'automation') {
        $activeTab = 'settings';
    }

    $flash = dcmanage_handle_actions($lang);

    echo '<div class="container-fluid dcmanage-shell ' . ($isRtl ? 'dcmanage-rtl' : 'dcmanage-ltr') . '" dir="' . ($isRtl ? 'rtl' : 'ltr') . '" data-lang="' . htmlspecialchars($lang) . '">';

    if ($flash !== '') {
        echo $flash;
    }

    $tabs = [
        'dashboard' => I18n::t('tab_dashboard', $lang),
        'datacenters' => I18n::t('tab_datacenters', $lang),
        'switches' => I18n::t('tab_switches', $lang),
        'servers' => I18n::t('tab_servers', $lang),
        'ports' => I18n::t('tab_ports', $lang),
        'ilos' => 'iLOs',
        'monitoring' => I18n::t('tab_monitoring', $lang),
        'packages' => I18n::t('tab_packages', $lang),
        'scope' => I18n::t('tab_scope', $lang),
        'traffic' => I18n::t('tab_traffic', $lang),
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
        echo '<div class="dcmanage-header mb-3"><h2 class="mb-1">' . htmlspecialchars(I18n::t('title', $lang)) . '</h2><p class="text-muted mb-0">' . htmlspecialchars(I18n::t('subtitle', $lang)) . ' - v' . htmlspecialchars(DCManage\Version::CURRENT) . '</p></div>';
        echo '<div id="dcmanage-dashboard" data-module-link="' . $moduleLink . '" data-api-base="' . $moduleLink . '&dcmanage_api=1"></div>';
        echo '<div id="dcmanage-version" class="mt-3" data-api-base="' . $moduleLink . '&dcmanage_api=1"></div>';
        echo '<div id="dcmanage-cron" class="mt-3" data-api-base="' . $moduleLink . '&dcmanage_api=1"></div>';
    } elseif ($activeTab === 'traffic') {
        echo '<div id="dcmanage-traffic" data-api-base="' . $moduleLink . '&dcmanage_api=1"></div>';
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
    } elseif ($activeTab === 'monitoring') {
        dcmanage_render_monitoring($lang);
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

        if ($action === 'monitoring_save') {
            $provider = strtolower(trim((string) ($_POST['monitoring_provider'] ?? 'prtg')));
            if ($provider === '') {
                $provider = 'prtg';
            }
            Capsule::table('mod_dcmanage_meta')->updateOrInsert(
                ['meta_key' => 'monitoring.provider'],
                ['meta_value' => $provider, 'updated_at' => date('Y-m-d H:i:s')]
            );

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

        if ($action === 'datacenter_update') {
            $id = (int) ($_POST['dc_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid datacenter');
            }

            Capsule::table('mod_dcmanage_datacenters')->where('id', $id)->update([
                'name' => trim((string) ($_POST['name'] ?? '')),
                'location' => trim((string) ($_POST['location'] ?? '')),
                'notes' => trim((string) ($_POST['notes'] ?? '')),
            ]);

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
        }

        if ($action === 'datacenter_delete') {
            $id = (int) ($_POST['dc_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid datacenter');
            }

            $rackIds = Capsule::table('mod_dcmanage_racks')->where('dc_id', $id)->pluck('id')->toArray();
            if ($rackIds !== []) {
                Capsule::table('mod_dcmanage_rack_units')->whereIn('rack_id', $rackIds)->delete();
                Capsule::table('mod_dcmanage_servers')->whereIn('rack_id', $rackIds)->update(['rack_id' => null]);
                Capsule::table('mod_dcmanage_switches')->whereIn('rack_id', $rackIds)->update(['rack_id' => null]);
                Capsule::table('mod_dcmanage_racks')->whereIn('id', $rackIds)->delete();
            }

            Capsule::table('mod_dcmanage_networks')->where('dc_id', $id)->delete();
            Capsule::table('mod_dcmanage_switches')->where('dc_id', $id)->delete();
            Capsule::table('mod_dcmanage_servers')->where('dc_id', $id)->delete();
            Capsule::table('mod_dcmanage_datacenters')->where('id', $id)->delete();

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
        }

        if ($action === 'rack_unit_set') {
            $rackId = (int) ($_POST['rack_id'] ?? 0);
            $uNo = (int) ($_POST['u_no'] ?? 0);
            $type = trim((string) ($_POST['unit_type'] ?? 'blank'));
            $label = trim((string) ($_POST['label'] ?? ''));

            if ($rackId <= 0 || $uNo <= 0) {
                throw new RuntimeException('Invalid rack unit selection');
            }

            if ($type === 'blank') {
                Capsule::table('mod_dcmanage_rack_units')->where('rack_id', $rackId)->where('u_no', $uNo)->delete();
            } else {
                Capsule::table('mod_dcmanage_rack_units')->updateOrInsert(
                    ['rack_id' => $rackId, 'u_no' => $uNo],
                    ['unit_type' => $type, 'label' => $label === '' ? null : $label, 'updated_at' => date('Y-m-d H:i:s')]
                );
            }

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
        }

        if ($action === 'rack_update') {
            $rackId = (int) ($_POST['rack_id'] ?? 0);
            if ($rackId <= 0) {
                throw new RuntimeException('Invalid rack');
            }
            $name = trim((string) ($_POST['rack_name'] ?? ''));
            $totalU = max(1, (int) ($_POST['rack_total_u'] ?? 42));
            if ($name === '') {
                throw new RuntimeException('Rack name is required');
            }

            Capsule::table('mod_dcmanage_racks')->where('id', $rackId)->update([
                'name' => $name,
                'total_u' => $totalU,
            ]);

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
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
                'vendor' => trim((string) ($_POST['vendor'] ?? 'Cisco')),
                'model' => trim((string) ($_POST['model'] ?? '')),
                'mgmt_ip' => trim((string) ($_POST['mgmt_ip'] ?? '')),
                'snmp_version' => trim((string) ($_POST['snmp_version'] ?? '2c')),
                'snmp_port' => max(1, (int) ($_POST['snmp_port'] ?? 161)),
                'snmp_community' => trim((string) ($_POST['snmp_community'] ?? 'public')),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('created', $lang)) . '</div>';
        }

        if ($action === 'switch_update') {
            $id = (int) ($_POST['switch_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid switch');
            }

            Capsule::table('mod_dcmanage_switches')->where('id', $id)->update([
                'dc_id' => max(1, (int) ($_POST['dc_id'] ?? 1)),
                'rack_id' => (int) ($_POST['rack_id'] ?? 0) ?: null,
                'u_start' => (int) ($_POST['u_start'] ?? 0) ?: null,
                'u_height' => max(1, (int) ($_POST['u_height'] ?? 1)),
                'name' => trim((string) ($_POST['name'] ?? '')),
                'vendor' => trim((string) ($_POST['vendor'] ?? 'Cisco')),
                'model' => trim((string) ($_POST['model'] ?? '')),
                'mgmt_ip' => trim((string) ($_POST['mgmt_ip'] ?? '')),
                'snmp_version' => trim((string) ($_POST['snmp_version'] ?? '2c')),
                'snmp_port' => max(1, (int) ($_POST['snmp_port'] ?? 161)),
                'snmp_community' => trim((string) ($_POST['snmp_community'] ?? 'public')),
            ]);

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
        }

        if ($action === 'switch_delete') {
            $id = (int) ($_POST['switch_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid switch');
            }
            Capsule::table('mod_dcmanage_switch_ports')->where('switch_id', $id)->delete();
            Capsule::table('mod_dcmanage_switches')->where('id', $id)->delete();
            Capsule::table('mod_dcmanage_meta')->where('meta_key', 'switch.snmp.' . $id)->delete();
            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
        }

        if ($action === 'switch_snmp_test') {
            $id = (int) ($_POST['switch_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid switch');
            }
            $switch = Capsule::table('mod_dcmanage_switches')->where('id', $id)->first();
            if ($switch === null) {
                throw new RuntimeException('Switch not found');
            }
            $result = dcmanage_test_snmp((string) $switch->mgmt_ip, (string) ($switch->snmp_community ?? 'public'), (int) ($switch->snmp_port ?? 161));
            Capsule::table('mod_dcmanage_meta')->updateOrInsert(
                ['meta_key' => 'switch.snmp.' . $id],
                ['meta_value' => json_encode($result, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')]
            );
            return '<div class="alert alert-' . ($result['ok'] ? 'success' : 'danger') . '">' . htmlspecialchars($result['message']) . '</div>';
        }

        if ($action === 'switch_port_upsert') {
            $id = (int) ($_POST['port_id'] ?? 0);
            $switchId = (int) ($_POST['switch_id'] ?? 0);
            if ($switchId <= 0) {
                throw new RuntimeException('Invalid switch');
            }
            $payload = [
                'switch_id' => $switchId,
                'if_name' => trim((string) ($_POST['if_name'] ?? '')),
                'vlan' => trim((string) ($_POST['vlan'] ?? '')),
                'admin_status' => trim((string) ($_POST['admin_status'] ?? 'up')),
                'oper_status' => trim((string) ($_POST['oper_status'] ?? 'unknown')),
                'last_seen' => date('Y-m-d H:i:s'),
            ];
            if ($payload['if_name'] === '') {
                throw new RuntimeException('Port interface name is required');
            }
            if ($id > 0) {
                Capsule::table('mod_dcmanage_switch_ports')->where('id', $id)->update($payload);
            } else {
                Capsule::table('mod_dcmanage_switch_ports')->insert($payload);
            }
            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
        }

        if ($action === 'switch_port_delete') {
            $id = (int) ($_POST['port_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid port');
            }
            Capsule::table('mod_dcmanage_switch_ports')->where('id', $id)->delete();
            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
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
        'locale' => 'default',
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
        if ($key === 'locale') {
            $value = strtolower($value);
            if (!in_array($value, ['default', 'fa', 'en'], true)) {
                $value = 'default';
            }
        }

        Capsule::table('mod_dcmanage_meta')->updateOrInsert(
            ['meta_key' => 'settings.' . $key],
            ['meta_value' => $value, 'updated_at' => date('Y-m-d H:i:s')]
        );
    }
}

function dcmanage_cron_defs(): array
{
    $cronFile = realpath(__DIR__ . '/cron.php');
    if ($cronFile === false) {
        $cronFile = __DIR__ . '/cron.php';
    }
    $phpBin = dcmanage_detect_php_binary();
    $cronScriptArg = dcmanage_shell_quote($cronFile);

    return [
        ['task' => 'poll_usage', 'interval' => 300, 'cron' => '*/5 * * * * ' . $phpBin . ' -q ' . $cronScriptArg . ' poll_usage'],
        ['task' => 'enforce_queue', 'interval' => 60, 'cron' => '* * * * * ' . $phpBin . ' -q ' . $cronScriptArg . ' enforce_queue'],
        ['task' => 'graph_warm', 'interval' => 1800, 'cron' => '*/30 * * * * ' . $phpBin . ' -q ' . $cronScriptArg . ' graph_warm'],
        ['task' => 'cleanup', 'interval' => 86400, 'cron' => '12 2 * * * ' . $phpBin . ' -q ' . $cronScriptArg . ' cleanup'],
        ['task' => 'self_update', 'interval' => 86400, 'cron' => '30 3 * * * ' . $phpBin . ' -q ' . $cronScriptArg . ' self_update'],
    ];
}

function dcmanage_detect_php_binary(): string
{
    $candidates = [];

    if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
        $candidates[] = PHP_BINARY;
    }

    $candidates = array_merge($candidates, [
        '/usr/local/bin/php',
        '/usr/bin/php',
        '/opt/cpanel/ea-php83/root/usr/bin/php',
        '/opt/cpanel/ea-php82/root/usr/bin/php',
        '/opt/cpanel/ea-php81/root/usr/bin/php',
        '/opt/cpanel/ea-php80/root/usr/bin/php',
        '/opt/plesk/php/8.3/bin/php',
        '/opt/plesk/php/8.2/bin/php',
        '/opt/plesk/php/8.1/bin/php',
        '/opt/plesk/php/8.0/bin/php',
    ]);

    foreach ($candidates as $candidate) {
        if ($candidate === '' || stripos($candidate, 'php-cgi') !== false || stripos($candidate, 'php-fpm') !== false) {
            continue;
        }
        if (is_file($candidate) && is_executable($candidate)) {
            return dcmanage_shell_quote($candidate);
        }
    }

    return 'php';
}

function dcmanage_shell_quote(string $value): string
{
    if (function_exists('escapeshellarg')) {
        return escapeshellarg($value);
    }

    if ($value === '') {
        return "''";
    }

    if (preg_match('/^[a-zA-Z0-9_\\.\\-\\/:]+$/', $value) === 1) {
        return $value;
    }

    return "'" . str_replace("'", "'\"'\"'", $value) . "'";
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
                if (strpos((string) $last->message, 'completed') !== false && $age <= ((int) $def['interval'] * 2)) {
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
    echo '<div class="col-md-4 mb-3"><label>' . htmlspecialchars(I18n::t('settings_language', $lang)) . '</label><select class="form-control dcmanage-input" name="locale">';
    $locales = [
        'default' => I18n::t('settings_language_default', $lang),
        'fa' => I18n::t('settings_language_fa', $lang),
        'en' => I18n::t('settings_language_en', $lang),
    ];
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

function dcmanage_render_monitoring(string $lang): void
{
    $provider = Capsule::table('mod_dcmanage_meta')->where('meta_key', 'monitoring.provider')->value('meta_value');
    $provider = strtolower(trim((string) ($provider ?: 'prtg')));

    echo '<h5 class="mb-3">' . htmlspecialchars(I18n::t('tab_monitoring', $lang)) . '</h5>';
    echo '<form method="post" action="" class="dcmanage-form-card">';
    echo '<input type="hidden" name="dcmanage_action" value="monitoring_save">';
    echo '<div class="form-group mb-3"><label>' . htmlspecialchars(I18n::t('monitoring_type', $lang)) . '</label>';
    echo '<select name="monitoring_provider" class="form-control dcmanage-input">';
    echo '<option value="">' . htmlspecialchars(I18n::t('monitoring_select', $lang)) . '</option>';
    $opts = ['prtg' => 'PRTG', 'cacti' => 'Cacti', 'solarwinds' => 'SolarWinds'];
    foreach ($opts as $value => $label) {
        $selected = $provider === $value ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select></div>';
    echo '<button class="btn btn-primary" type="submit">' . htmlspecialchars(I18n::t('save_settings', $lang)) . '</button>';
    echo '</form>';
}

function dcmanage_render_datacenters(string $lang): void
{
    $rows = Capsule::table('mod_dcmanage_datacenters as d')
        ->leftJoin('mod_dcmanage_racks as r', 'r.dc_id', '=', 'd.id')
        ->groupBy('d.id', 'd.name', 'd.location', 'd.notes', 'd.created_at')
        ->orderBy('d.id', 'desc')
        ->get([
            'd.id', 'd.name', 'd.location', 'd.notes', 'd.created_at',
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
    echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('label_notes', $lang)) . '</label><textarea name="notes" class="form-control dcmanage-input" rows="2"></textarea></div>';
    echo '<button class="btn btn-primary" type="submit">' . htmlspecialchars(I18n::t('create_datacenter', $lang)) . '</button>';
    echo '</form>';
    echo '</div>';

    echo '<div class="table-responsive mb-4"><table class="table table-sm table-striped dcmanage-dc-table">';
    echo '<thead><tr><th>ID</th><th>' . htmlspecialchars(I18n::t('datacenter_name', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('datacenter_location', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('datacenter_rack_count', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('label_actions', $lang)) . '</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . (int) $row->id . '</td>';
        echo '<td>' . htmlspecialchars((string) $row->name) . '</td>';
        echo '<td>' . htmlspecialchars((string) $row->location) . '</td>';
        echo '<td>' . (int) $row->rack_count . '</td>';
        echo '<td>';
        echo '<div class="dcmanage-action-buttons">';
        echo '<button class="btn btn-sm btn-outline-info" type="button" data-toggle="collapse" data-target="#dc-racks-' . (int) $row->id . '">' . htmlspecialchars(I18n::t('action_racks', $lang)) . '</button>';
        echo '<button class="btn btn-sm btn-outline-primary" type="button" data-toggle="collapse" data-target="#dc-servers-' . (int) $row->id . '">' . htmlspecialchars(I18n::t('action_servers', $lang)) . '</button>';
        echo '<button class="btn btn-sm btn-outline-warning" type="button" data-toggle="collapse" data-target="#dc-edit-' . (int) $row->id . '">' . htmlspecialchars(I18n::t('action_edit', $lang)) . '</button>';
        echo '<form method="post" style="display:inline" onsubmit="return confirm(\'' . htmlspecialchars(I18n::t('delete_confirm_datacenter', $lang), ENT_QUOTES, 'UTF-8') . '\')">';
        echo '<input type="hidden" name="dcmanage_action" value="datacenter_delete"><input type="hidden" name="dc_id" value="' . (int) $row->id . '">';
        echo '<button class="btn btn-sm btn-outline-danger" type="submit">' . htmlspecialchars(I18n::t('action_delete', $lang)) . '</button>';
        echo '</form>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        echo '<tr class="collapse" id="dc-edit-' . (int) $row->id . '"><td colspan="5">';
        echo '<form method="post" class="dcmanage-form-card">';
        echo '<input type="hidden" name="dcmanage_action" value="datacenter_update"><input type="hidden" name="dc_id" value="' . (int) $row->id . '">';
        echo '<div class="form-row">';
        echo '<div class="form-group col-md-4"><label>' . htmlspecialchars(I18n::t('label_name', $lang)) . '</label><input name="name" class="form-control dcmanage-input" value="' . htmlspecialchars((string) $row->name) . '"></div>';
        echo '<div class="form-group col-md-4"><label>' . htmlspecialchars(I18n::t('label_location', $lang)) . '</label><input name="location" class="form-control dcmanage-input" value="' . htmlspecialchars((string) $row->location) . '"></div>';
        echo '<div class="form-group col-md-4"><label>' . htmlspecialchars(I18n::t('label_notes', $lang)) . '</label><input name="notes" class="form-control dcmanage-input" value="' . htmlspecialchars((string) $row->notes) . '"></div>';
        echo '</div><button class="btn btn-primary btn-sm" type="submit">' . htmlspecialchars(I18n::t('save_settings', $lang)) . '</button></form>';
        echo '</td></tr>';

        $servers = Capsule::table('mod_dcmanage_servers as s')
            ->leftJoin('mod_dcmanage_racks as r', 'r.id', '=', 's.rack_id')
            ->where('s.dc_id', (int) $row->id)
            ->get(['s.hostname', 'r.name as rack_name', 's.u_start', 's.u_height']);
        echo '<tr class="collapse" id="dc-servers-' . (int) $row->id . '"><td colspan="5"><div class="table-responsive"><table class="table table-sm"><thead><tr><th>' . htmlspecialchars(I18n::t('label_hostname', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('label_rack', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('label_u', $lang)) . '</th></tr></thead><tbody>';
        foreach ($servers as $s) {
            echo '<tr><td>' . htmlspecialchars((string) $s->hostname) . '</td><td>' . htmlspecialchars((string) ($s->rack_name ?? '-')) . '</td><td>' . htmlspecialchars(((string) ($s->u_start ?? '-')) . '/' . (string) ($s->u_height ?? 1)) . '</td></tr>';
        }
        if (count($servers) === 0) {
            echo '<tr><td colspan="3">-</td></tr>';
        }
        echo '</tbody></table></div></td></tr>';

        echo '<tr class="collapse" id="dc-racks-' . (int) $row->id . '"><td colspan="5">';
        dcmanage_render_rack_cards((int) $row->id, $lang);
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function dcmanage_render_rack_cards(int $dcId, string $lang): void
{
    $racks = Capsule::table('mod_dcmanage_racks')->where('dc_id', $dcId)->orderBy('name')->get();
    echo '<div class="row dcmanage-rack-layout">';
    foreach ($racks as $rack) {
        $rackId = (int) $rack->id;
        $units = max(1, (int) $rack->total_u);
        $usage = dcmanage_rack_usage($rackId, $units);
        echo '<div class="col-12 col-xl-6 mb-4">';
        echo '<div class="card dcmanage-rack-card"><div class="card-body">';
        echo '<div class="d-flex justify-content-between align-items-center mb-3">';
        echo '<h6 class="mb-0">' . htmlspecialchars((string) $rack->name) . '</h6>';
        echo '<span class="badge badge-pill badge-primary">' . $units . 'U</span>';
        echo '</div>';

        echo '<div class="dcmanage-rack-shell">';
        echo '<div class="dcmanage-rack-visual">';
        echo '<div class="dcmanage-rack-legend mb-2">';
        echo '<strong class="mr-2">' . htmlspecialchars(I18n::t('rack_legend', $lang)) . ':</strong>';
        echo '<span class="dcmanage-dot server"></span>SRV';
        echo '<span class="dcmanage-dot switch ml-2"></span>SW';
        echo '<span class="dcmanage-dot reserved ml-2"></span>' . htmlspecialchars(I18n::t('unit_reserved', $lang));
        echo '<span class="dcmanage-dot cable ml-2"></span>' . htmlspecialchars(I18n::t('unit_cable', $lang));
        echo '<span class="dcmanage-dot air ml-2"></span>' . htmlspecialchars(I18n::t('unit_air', $lang));
        echo '</div>';

        echo '<div class="dcmanage-rack-frame front mb-2">';
        echo '<div class="dcmanage-rack-title">' . htmlspecialchars(I18n::t('rack_front_view', $lang)) . '</div>';
        echo '<div class="dcmanage-rack-grid">';
        for ($u = $units; $u >= 1; $u--) {
            $cell = $usage[$u] ?? ['kind' => 'blank', 'label' => '-'];
            $kind = (string) $cell['kind'];
            $label = (string) $cell['label'];
            if ($label === '') {
                $label = '-';
            }
            echo '<div class="dcmanage-rack-u ' . htmlspecialchars($kind) . '" data-rack-id="' . $rackId . '" data-u="' . $u . '">';
            echo '<span class="u-num">U' . $u . '</span>';
            echo '<span class="u-slot"><span class="u-face"></span><span class="u-item">' . htmlspecialchars($label) . '</span></span>';
            echo '<span class="u-wire"></span>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="dcmanage-rack-controls">';
        echo '<form method="post" class="dcmanage-rack-form mb-3">';
        echo '<input type="hidden" name="dcmanage_action" value="rack_update">';
        echo '<input type="hidden" name="rack_id" value="' . $rackId . '">';
        echo '<div class="form-row align-items-end">';
        echo '<div class="col-8"><label class="small mb-1">' . htmlspecialchars(I18n::t('label_name', $lang)) . '</label><input name="rack_name" class="form-control form-control-sm dcmanage-input" value="' . htmlspecialchars((string) $rack->name) . '"></div>';
        echo '<div class="col-4"><label class="small mb-1">U</label><input type="number" min="1" max="60" name="rack_total_u" class="form-control form-control-sm dcmanage-input" value="' . $units . '"></div>';
        echo '</div>';
        echo '<button class="btn btn-sm btn-outline-primary mt-2 btn-block" type="submit">' . htmlspecialchars(I18n::t('save_settings', $lang)) . '</button>';
        echo '</form>';

        echo '<form method="post" class="dcmanage-rack-form">';
        echo '<input type="hidden" name="dcmanage_action" value="rack_unit_set">';
        echo '<input type="hidden" name="rack_id" value="' . $rackId . '">';
        echo '<div class="form-row">';
        echo '<div class="col-12 mb-2"><label class="small mb-1">U</label><input type="number" min="1" max="' . $units . '" id="dcmanage-u-no-' . $rackId . '" name="u_no" class="form-control form-control-sm dcmanage-input" placeholder="U"></div>';
        echo '<div class="col-12 mb-2"><label class="small mb-1">' . htmlspecialchars(I18n::t('unit_blank', $lang)) . '/' . htmlspecialchars(I18n::t('unit_reserved', $lang)) . '/' . htmlspecialchars(I18n::t('unit_cable', $lang)) . '/' . htmlspecialchars(I18n::t('unit_air', $lang)) . '</label><select name="unit_type" class="form-control form-control-sm dcmanage-input"><option value="blank">' . htmlspecialchars(I18n::t('unit_blank', $lang)) . '</option><option value="reserved">' . htmlspecialchars(I18n::t('unit_reserved', $lang)) . '</option><option value="cable">' . htmlspecialchars(I18n::t('unit_cable', $lang)) . '</option><option value="air">' . htmlspecialchars(I18n::t('unit_air', $lang)) . '</option></select></div>';
        echo '<div class="col-12"><label class="small mb-1">' . htmlspecialchars(I18n::t('label_name', $lang)) . '</label><input name="label" class="form-control form-control-sm dcmanage-input" placeholder="' . htmlspecialchars(I18n::t('label_name', $lang)) . '"></div>';
        echo '</div>';
        echo '<button class="btn btn-sm btn-primary mt-2 btn-block" type="submit">' . htmlspecialchars(I18n::t('set_unit', $lang)) . '</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';

        echo '</div></div></div>';
    }
    echo '</div>';
    echo '<script>(function(){var units=document.querySelectorAll(".dcmanage-rack-u[data-rack-id]");for(var i=0;i<units.length;i++){units[i].addEventListener("click",function(){var rid=this.getAttribute("data-rack-id");var u=this.getAttribute("data-u");var input=document.getElementById("dcmanage-u-no-"+rid);if(input){input.value=u;}var list=document.querySelectorAll(".dcmanage-rack-u[data-rack-id=\'"+rid+"\']");for(var j=0;j<list.length;j++){list[j].classList.remove("selected");}this.classList.add("selected");});}})();</script>';
}

function dcmanage_rack_usage(int $rackId, int $totalU): array
{
    $usage = [];

    $units = Capsule::table('mod_dcmanage_rack_units')->where('rack_id', $rackId)->get(['u_no', 'unit_type', 'label']);
    foreach ($units as $u) {
        $label = trim((string) ($u->label ?? ''));
        if ($label === '') {
            $label = strtoupper((string) $u->unit_type);
        }
        $usage[(int) $u->u_no] = ['kind' => (string) $u->unit_type, 'label' => $label];
    }

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
                $usage[$u] = ['kind' => 'server', 'label' => 'SRV:' . (string) $s->hostname];
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
                $usage[$u] = ['kind' => 'switch', 'label' => 'SW:' . (string) $s->name];
            }
        }
    }

    return $usage;
}

function dcmanage_test_snmp(string $host, string $community, int $port = 161): array
{
    $host = trim($host);
    if ($host === '') {
        return ['ok' => false, 'message' => 'Management IP is empty'];
    }

    if (function_exists('snmp2_get')) {
        $timeoutMicros = 1000000;
        $retries = 0;
        $target = $host . ':' . max(1, $port);
        $value = @snmp2_get($target, $community === '' ? 'public' : $community, '.1.3.6.1.2.1.1.1.0', $timeoutMicros, $retries);
        if ($value !== false) {
            return ['ok' => true, 'message' => 'SNMP connected'];
        }
    }

    $errno = 0;
    $errstr = '';
    $sock = @fsockopen('udp://' . $host, max(1, $port), $errno, $errstr, 2.0);
    if (is_resource($sock)) {
        fclose($sock);
        return ['ok' => true, 'message' => 'UDP port reachable'];
    }

    return ['ok' => false, 'message' => 'SNMP connection failed'];
}

function dcmanage_switch_snmp_state(int $switchId): array
{
    $raw = Capsule::table('mod_dcmanage_meta')->where('meta_key', 'switch.snmp.' . $switchId)->value('meta_value');
    if ($raw === null || $raw === '') {
        return ['ok' => false, 'message' => '-', 'tested' => false];
    }
    $parsed = json_decode((string) $raw, true);
    if (!is_array($parsed)) {
        return ['ok' => false, 'message' => '-', 'tested' => false];
    }
    return [
        'ok' => !empty($parsed['ok']),
        'message' => (string) ($parsed['message'] ?? '-'),
        'tested' => true,
    ];
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
        ->get(['s.id', 's.name', 's.vendor', 's.model', 's.mgmt_ip', 's.snmp_community', 's.snmp_version', 's.snmp_port', 's.u_start', 's.u_height', 'd.name as dc_name', 'r.name as rack_name', 's.dc_id', 's.rack_id']);

    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
    echo '<h5 class="mb-0">' . htmlspecialchars(I18n::t('tab_switches', $lang)) . '</h5>';
    echo '<button class="btn btn-primary btn-sm" type="button" data-toggle="collapse" data-target="#dcmanage-switch-add">' . htmlspecialchars(I18n::t('switch_add', $lang)) . '</button>';
    echo '</div>';

    echo '<div class="collapse mb-4" id="dcmanage-switch-add">';
    echo '<form method="post" action="" class="dcmanage-form-card">';
    echo '<input type="hidden" name="dcmanage_action" value="switch_create">';
    echo '<div class="form-row">';
    echo '<div class="form-group col-md-4"><label>' . htmlspecialchars(I18n::t('select_datacenter', $lang)) . '</label><select name="dc_id" id="dcmanage-switch-dc" required class="form-control dcmanage-input">';
    echo '<option value="">--</option>';
    foreach ($dcs as $dc) {
        echo '<option value="' . (int) $dc->id . '">' . htmlspecialchars((string) $dc->name) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group col-md-4"><label>' . htmlspecialchars(I18n::t('select_rack', $lang)) . '</label><select name="rack_id" id="dcmanage-switch-rack" class="form-control dcmanage-input">';
    echo '<option value="">--</option>';
    foreach ($racks as $rack) {
        echo '<option data-dc-id="' . (int) $rack->dc_id . '" value="' . (int) $rack->id . '">' . htmlspecialchars((string) $rack->name) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group col-md-4"><label>' . htmlspecialchars(I18n::t('switch_name', $lang)) . '</label><input required name="name" class="form-control dcmanage-input"></div>';
    echo '</div>';

    echo '<div class="form-row">';
    echo '<div class="form-group col-md-3"><label>' . htmlspecialchars(I18n::t('switch_vendor', $lang)) . '</label><select name="vendor" class="form-control dcmanage-input"><option>Cisco</option><option>Nexus</option><option>MikroTik</option></select></div>';
    echo '<div class="form-group col-md-3"><label>' . htmlspecialchars(I18n::t('switch_model', $lang)) . '</label><input name="model" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-2"><label>U Start</label><input type="number" min="0" name="u_start" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-2"><label>U Height</label><input type="number" min="1" name="u_height" value="1" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-2"><label>SNMP Port</label><input type="number" min="1" name="snmp_port" value="161" class="form-control dcmanage-input"></div>';
    echo '</div>';

    echo '<div class="form-row">';
    echo '<div class="form-group col-md-4"><label>' . htmlspecialchars(I18n::t('switch_mgmt_ip', $lang)) . '</label><input name="mgmt_ip" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-4"><label>SNMP Version</label><select name="snmp_version" class="form-control dcmanage-input"><option value="2c">2c</option><option value="3">3</option></select></div>';
    echo '<div class="form-group col-md-4"><label>SNMP Community</label><input name="snmp_community" value="public" class="form-control dcmanage-input"></div>';
    echo '</div>';
    echo '<button class="btn btn-primary" type="submit">' . htmlspecialchars(I18n::t('create_switch', $lang)) . '</button>';
    echo '</form>';
    echo '</div>';

    echo '<div class="table-responsive"><table class="table table-sm table-striped dcmanage-dc-table">';
    echo '<thead><tr><th>ID</th><th>' . htmlspecialchars(I18n::t('switch_name', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('tab_datacenters', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('select_rack', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('switch_vendor', $lang)) . '</th><th>SNMP</th><th>' . htmlspecialchars(I18n::t('label_actions', $lang)) . '</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $snmp = dcmanage_switch_snmp_state((int) $row->id);
        $snmpCls = $snmp['tested'] ? ($snmp['ok'] ? 'success' : 'danger') : 'secondary';
        $snmpLabel = $snmp['tested'] ? ($snmp['ok'] ? 'UP' : 'DOWN') : '-';
        echo '<tr>';
        echo '<td>' . (int) $row->id . '</td>';
        echo '<td>' . htmlspecialchars((string) $row->name) . '<br><small class="text-muted">' . htmlspecialchars((string) $row->mgmt_ip) . '</small></td>';
        echo '<td>' . htmlspecialchars((string) $row->dc_name) . '</td>';
        echo '<td>' . htmlspecialchars((string) $row->rack_name) . '</td>';
        echo '<td>' . htmlspecialchars((string) $row->vendor) . ' ' . htmlspecialchars((string) $row->model) . '</td>';
        echo '<td><span class="badge badge-' . $snmpCls . '">' . $snmpLabel . '</span><br><small class="text-muted">' . htmlspecialchars((string) $snmp['message']) . '</small></td>';
        echo '<td><div class="dcmanage-action-buttons">';
        echo '<button class="btn btn-sm btn-outline-info" type="button" data-toggle="collapse" data-target="#sw-ports-' . (int) $row->id . '">Ports</button>';
        echo '<button class="btn btn-sm btn-outline-warning" type="button" data-toggle="collapse" data-target="#sw-edit-' . (int) $row->id . '">' . htmlspecialchars(I18n::t('action_edit', $lang)) . '</button>';
        echo '<form method="post" style="display:inline"><input type="hidden" name="dcmanage_action" value="switch_snmp_test"><input type="hidden" name="switch_id" value="' . (int) $row->id . '"><button class="btn btn-sm btn-outline-success" type="submit">' . htmlspecialchars(I18n::t('switch_snmp_test', $lang)) . '</button></form>';
        echo '<form method="post" style="display:inline" onsubmit="return confirm(\'' . htmlspecialchars(I18n::t('delete_confirm_switch', $lang), ENT_QUOTES, 'UTF-8') . '\')"><input type="hidden" name="dcmanage_action" value="switch_delete"><input type="hidden" name="switch_id" value="' . (int) $row->id . '"><button class="btn btn-sm btn-outline-danger" type="submit">' . htmlspecialchars(I18n::t('action_delete', $lang)) . '</button></form>';
        echo '</div></td>';
        echo '</tr>';

        echo '<tr class="collapse" id="sw-edit-' . (int) $row->id . '"><td colspan="7">';
        echo '<form method="post" class="dcmanage-form-card">';
        echo '<input type="hidden" name="dcmanage_action" value="switch_update"><input type="hidden" name="switch_id" value="' . (int) $row->id . '">';
        echo '<div class="form-row">';
        echo '<div class="form-group col-md-3"><label>' . htmlspecialchars(I18n::t('select_datacenter', $lang)) . '</label><select name="dc_id" class="form-control dcmanage-input">';
        foreach ($dcs as $dc) {
            $sel = (int) $row->dc_id === (int) $dc->id ? ' selected' : '';
            echo '<option value="' . (int) $dc->id . '"' . $sel . '>' . htmlspecialchars((string) $dc->name) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="form-group col-md-3"><label>' . htmlspecialchars(I18n::t('select_rack', $lang)) . '</label><select name="rack_id" class="form-control dcmanage-input"><option value="">--</option>';
        foreach ($racks as $rack) {
            $sel = (int) $row->rack_id === (int) $rack->id ? ' selected' : '';
            echo '<option value="' . (int) $rack->id . '"' . $sel . '>' . htmlspecialchars((string) $rack->name) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="form-group col-md-3"><label>' . htmlspecialchars(I18n::t('switch_name', $lang)) . '</label><input name="name" class="form-control dcmanage-input" value="' . htmlspecialchars((string) $row->name) . '"></div>';
        echo '<div class="form-group col-md-3"><label>' . htmlspecialchars(I18n::t('switch_vendor', $lang)) . '</label><select name="vendor" class="form-control dcmanage-input">';
        foreach (['Cisco', 'Nexus', 'MikroTik'] as $v) {
            $sel = strcasecmp((string) $row->vendor, $v) === 0 ? ' selected' : '';
            echo '<option' . $sel . '>' . htmlspecialchars($v) . '</option>';
        }
        echo '</select></div>';
        echo '</div>';
        echo '<div class="form-row">';
        echo '<div class="form-group col-md-3"><label>' . htmlspecialchars(I18n::t('switch_model', $lang)) . '</label><input name="model" class="form-control dcmanage-input" value="' . htmlspecialchars((string) $row->model) . '"></div>';
        echo '<div class="form-group col-md-3"><label>' . htmlspecialchars(I18n::t('switch_mgmt_ip', $lang)) . '</label><input name="mgmt_ip" class="form-control dcmanage-input" value="' . htmlspecialchars((string) $row->mgmt_ip) . '"></div>';
        echo '<div class="form-group col-md-2"><label>SNMP Port</label><input type="number" min="1" name="snmp_port" class="form-control dcmanage-input" value="' . (int) ($row->snmp_port ?? 161) . '"></div>';
        echo '<div class="form-group col-md-2"><label>SNMP Version</label><select name="snmp_version" class="form-control dcmanage-input"><option value="2c"' . ((string) $row->snmp_version === '2c' ? ' selected' : '') . '>2c</option><option value="3"' . ((string) $row->snmp_version === '3' ? ' selected' : '') . '>3</option></select></div>';
        echo '<div class="form-group col-md-2"><label>Community</label><input name="snmp_community" class="form-control dcmanage-input" value="' . htmlspecialchars((string) $row->snmp_community) . '"></div>';
        echo '</div>';
        echo '<div class="form-row"><div class="form-group col-md-2"><label>U Start</label><input type="number" min="0" name="u_start" class="form-control dcmanage-input" value="' . htmlspecialchars((string) ($row->u_start ?? '')) . '"></div><div class="form-group col-md-2"><label>U Height</label><input type="number" min="1" name="u_height" class="form-control dcmanage-input" value="' . htmlspecialchars((string) ($row->u_height ?? 1)) . '"></div></div>';
        echo '<button class="btn btn-primary btn-sm" type="submit">' . htmlspecialchars(I18n::t('save_settings', $lang)) . '</button>';
        echo '</form>';
        echo '</td></tr>';

        $ports = Capsule::table('mod_dcmanage_switch_ports')->where('switch_id', (int) $row->id)->orderBy('if_name')->get();
        echo '<tr class="collapse" id="sw-ports-' . (int) $row->id . '"><td colspan="7">';
        echo '<div class="dcmanage-form-card">';
        echo '<h6 class="mb-2">' . htmlspecialchars(I18n::t('switch_ports_vlans', $lang)) . '</h6>';
        echo '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Interface</th><th>VLAN</th><th>Admin</th><th>Oper</th><th>' . htmlspecialchars(I18n::t('label_actions', $lang)) . '</th></tr></thead><tbody>';
        foreach ($ports as $p) {
            echo '<tr><td>' . htmlspecialchars((string) $p->if_name) . '</td><td>' . htmlspecialchars((string) $p->vlan) . '</td><td>' . htmlspecialchars((string) $p->admin_status) . '</td><td>' . htmlspecialchars((string) $p->oper_status) . '</td><td>';
            echo '<form method="post" style="display:inline"><input type="hidden" name="dcmanage_action" value="switch_port_delete"><input type="hidden" name="port_id" value="' . (int) $p->id . '"><button class="btn btn-sm btn-outline-danger" type="submit">' . htmlspecialchars(I18n::t('action_delete', $lang)) . '</button></form>';
            echo '</td></tr>';
        }
        if (count($ports) === 0) {
            echo '<tr><td colspan="5">-</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<form method="post" class="mt-2"><input type="hidden" name="dcmanage_action" value="switch_port_upsert"><input type="hidden" name="switch_id" value="' . (int) $row->id . '"><div class="form-row"><div class="col-md-3 mb-2"><input name="if_name" class="form-control form-control-sm dcmanage-input" placeholder="Ethernet1/1"></div><div class="col-md-2 mb-2"><input name="vlan" class="form-control form-control-sm dcmanage-input" placeholder="10"></div><div class="col-md-2 mb-2"><select name="admin_status" class="form-control form-control-sm dcmanage-input"><option>up</option><option>down</option></select></div><div class="col-md-2 mb-2"><select name="oper_status" class="form-control form-control-sm dcmanage-input"><option>up</option><option>down</option><option>unknown</option></select></div><div class="col-md-3 mb-2"><button class="btn btn-sm btn-outline-primary" type="submit">' . htmlspecialchars(I18n::t('switch_add_update_port', $lang)) . '</button></div></div></form>';
        echo '</div>';
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';

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
