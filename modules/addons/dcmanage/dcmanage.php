<?php

declare(strict_types=1);

use DCManage\Api\Router;
use DCManage\Database\Schema;
use DCManage\Integrations\PrtgClient;
use DCManage\Support\Crypto;
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
    if ($activeTab === 'ports') {
        $activeTab = 'switches';
    }
    if ($activeTab === 'ilos') {
        $activeTab = 'servers';
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
        'monitoring' => I18n::t('tab_monitoring', $lang),
        'packages' => I18n::t('tab_packages', $lang),
        'scope' => I18n::t('tab_scope', $lang),
        'traffic' => I18n::t('tab_traffic', $lang),
        'queue' => I18n::t('tab_queue', $lang),
        'settings' => I18n::t('tab_settings', $lang),
        'logs' => I18n::t('tab_logs', $lang),
    ];

    echo '<ul class="nav nav-tabs dcmanage-tabs" role="tablist">';
    foreach ($tabs as $key => $label) {
        $active = $activeTab === $key ? ' active' : '';
        echo '<li class="nav-item">';
        echo '<a class="nav-link' . $active . '" href="' . $moduleLink . '&tab=' . urlencode($key) . '">';
        echo '<span class="dcmanage-tab-icon" aria-hidden="true">' . dcmanage_tab_icon_svg($key) . '</span>';
        echo '<span class="dcmanage-tab-label">' . htmlspecialchars($label) . '</span>';
        echo '</a>';
        echo '</li>';
    }
    echo '</ul>';

    echo '<div class="card mt-3 border-0 shadow-sm dcmanage-page-card"><div class="card-body dcmanage-page-body">';

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
    } elseif ($activeTab === 'queue') {
        dcmanage_render_queue($lang);
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

    $action = (string) ($_POST['dcmanage_action_btn'] ?? ($_POST['dcmanage_action'] ?? ''));

    try {
        if ($action === 'settings_ilo_proxy_test') {
            $type = strtolower(trim((string) ($_POST['ilo_proxy_type'] ?? 'http')));
            $host = trim((string) ($_POST['ilo_proxy_host'] ?? ''));
            $port = max(1, (int) ($_POST['ilo_proxy_port'] ?? 0));
            $user = trim((string) ($_POST['ilo_proxy_user'] ?? ''));
            $pass = (string) ($_POST['ilo_proxy_pass'] ?? '');
            if ($pass === '') {
                $storedEnc = (string) Capsule::table('mod_dcmanage_meta')->where('meta_key', 'settings.ilo_proxy_pass_enc')->value('meta_value');
                if ($storedEnc !== '') {
                    $pass = Crypto::decrypt($storedEnc);
                }
            }

            $result = dcmanage_test_proxy_connection($type, $host, $port, $user, $pass);
            return '<div class="alert alert-' . ($result['ok'] ? 'success' : 'danger') . '">' . htmlspecialchars((string) $result['message']) . '</div>';
        }

        if ($action === 'settings_save') {
            dcmanage_handle_settings_save();
            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
        }

        if ($action === 'logs_clear') {
            $scope = strtolower(trim((string) ($_POST['scope'] ?? 'system')));
            if ($scope === 'purchase') {
                Capsule::table('mod_dcmanage_purchases')->delete();
            } elseif ($scope === 'all') {
                Capsule::table('mod_dcmanage_logs')->delete();
                Capsule::table('mod_dcmanage_purchases')->delete();
            } else {
                Capsule::table('mod_dcmanage_logs')->delete();
            }

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
        }

        if ($action === 'queue_cancel_job') {
            $jobId = (int) ($_POST['job_id'] ?? 0);
            if ($jobId <= 0) {
                throw new RuntimeException('Invalid job');
            }

            $updated = Capsule::table('mod_dcmanage_jobs')
                ->where('id', $jobId)
                ->whereIn('status', ['pending', 'running'])
                ->update([
                    'status' => 'canceled',
                    'finished_at' => date('Y-m-d H:i:s'),
                    'last_error' => 'Canceled by admin from queue',
                ]);
            if ($updated === 0) {
                throw new RuntimeException('Job is not cancelable');
            }

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
        }

        if ($action === 'queue_retry_job') {
            $jobId = (int) ($_POST['job_id'] ?? 0);
            if ($jobId <= 0) {
                throw new RuntimeException('Invalid job');
            }

            $updated = Capsule::table('mod_dcmanage_jobs')
                ->where('id', $jobId)
                ->whereIn('status', ['failed', 'canceled'])
                ->update([
                    'status' => 'pending',
                    'attempts' => 0,
                    'run_after' => null,
                    'started_at' => null,
                    'finished_at' => null,
                    'last_error' => null,
                ]);
            if ($updated === 0) {
                throw new RuntimeException('Job is not retryable');
            }

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
        }

        if ($action === 'queue_clear_done') {
            Capsule::table('mod_dcmanage_jobs')->whereIn('status', ['done', 'failed', 'canceled'])->delete();
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

        if ($action === 'prtg_instance_create') {
            $name = trim((string) ($_POST['prtg_name'] ?? ''));
            $baseUrl = rtrim(trim((string) ($_POST['prtg_base_url'] ?? '')), '/');
            $user = trim((string) ($_POST['prtg_user'] ?? ''));
            $passhash = trim((string) ($_POST['prtg_passhash'] ?? ''));
            $verifySsl = (int) ($_POST['prtg_verify_ssl'] ?? 0) === 1 ? 1 : 0;

            if ($name === '' || $baseUrl === '' || $user === '' || $passhash === '') {
                throw new RuntimeException('PRTG name, URL, user and passhash are required');
            }

            Capsule::table('mod_dcmanage_prtg_instances')->insert([
                'name' => $name,
                'base_url' => $baseUrl,
                'user' => $user,
                'passhash_enc' => Crypto::encrypt($passhash),
                'timezone' => null,
                'verify_ssl' => $verifySsl,
                'proxy_json' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('created', $lang)) . '</div>';
        }

        if ($action === 'prtg_instance_delete') {
            $id = (int) ($_POST['prtg_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid PRTG instance');
            }
            Capsule::table('mod_dcmanage_prtg_instances')->where('id', $id)->delete();
            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
        }

        if ($action === 'prtg_instance_test') {
            $id = (int) ($_POST['prtg_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid PRTG instance');
            }

            $client = PrtgClient::fromDb($id);
            $result = $client->testConnection();
            $ok = isset($result['version']) || isset($result['treesize']) || isset($result['status']);
            return '<div class="alert alert-' . ($ok ? 'success' : 'warning') . '">' . htmlspecialchars($ok ? 'PRTG connection OK' : 'PRTG test returned a non-standard payload') . '</div>';
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

            if (!empty($result['ok'])) {
                $discover = dcmanage_discover_switch_ports((string) $switch->mgmt_ip, (string) ($switch->snmp_community ?? 'public'), (int) ($switch->snmp_port ?? 161));
                if (!empty($discover['ok'])) {
                    $saved = dcmanage_store_discovered_switch_ports($id, is_array($discover['ports'] ?? null) ? $discover['ports'] : []);
                    $message = (string) $result['message'];
                    if ($saved > 0) {
                        $message .= ' | ' . I18n::t('switch_discovery_saved', $lang) . ' (' . $saved . ')';
                    }
                    return '<div class="alert alert-success">' . htmlspecialchars($message) . '</div>';
                }
            }

            return '<div class="alert alert-' . ($result['ok'] ? 'success' : 'danger') . '">' . htmlspecialchars($result['message']) . '</div>';
        }

        if ($action === 'switch_ports_discover') {
            $id = (int) ($_POST['switch_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid switch');
            }

            $switch = Capsule::table('mod_dcmanage_switches')->where('id', $id)->first();
            if ($switch === null) {
                throw new RuntimeException('Switch not found');
            }

            $discover = dcmanage_discover_switch_ports((string) $switch->mgmt_ip, (string) ($switch->snmp_community ?? 'public'), (int) ($switch->snmp_port ?? 161));
            if (empty($discover['ok'])) {
                return '<div class="alert alert-danger">' . htmlspecialchars((string) ($discover['message'] ?? 'SNMP discovery failed')) . '</div>';
            }

            $saved = dcmanage_store_discovered_switch_ports($id, is_array($discover['ports'] ?? null) ? $discover['ports'] : []);

            $msg = $saved > 0
                ? I18n::t('switch_discovery_saved', $lang) . ' (' . $saved . ')'
                : I18n::t('switch_discovery_none', $lang);
            return '<div class="alert alert-' . ($saved > 0 ? 'success' : 'info') . '">' . htmlspecialchars($msg) . '</div>';
        }

        if ($action === 'switch_port_upsert') {
            $id = (int) ($_POST['port_id'] ?? 0);
            $switchId = (int) ($_POST['switch_id'] ?? 0);
            if ($switchId <= 0) {
                throw new RuntimeException('Invalid switch');
            }
            $payload = [
                'switch_id' => $switchId,
                'if_name' => dcmanage_normalize_if_name((string) ($_POST['if_name'] ?? '')),
                'if_desc' => trim((string) ($_POST['if_desc'] ?? '')),
                'vlan' => trim((string) ($_POST['vlan'] ?? '')),
                'admin_status' => trim((string) ($_POST['admin_status'] ?? 'up')),
                'oper_status' => trim((string) ($_POST['oper_status'] ?? 'unknown')),
                'last_seen' => date('Y-m-d H:i:s'),
            ];
            if ($payload['if_name'] === '') {
                $switch = Capsule::table('mod_dcmanage_switches')->where('id', $switchId)->first();
                if ($switch === null) {
                    throw new RuntimeException('Port interface name is required');
                }

                $discover = dcmanage_discover_switch_ports((string) $switch->mgmt_ip, (string) ($switch->snmp_community ?? 'public'), (int) ($switch->snmp_port ?? 161));
                if (empty($discover['ok'])) {
                    throw new RuntimeException((string) ($discover['message'] ?? 'Port interface name is required'));
                }

                $saved = dcmanage_store_discovered_switch_ports($switchId, is_array($discover['ports'] ?? null) ? $discover['ports'] : []);
                $msg = $saved > 0
                    ? I18n::t('switch_discovery_saved', $lang) . ' (' . $saved . ')'
                    : I18n::t('switch_discovery_none', $lang);
                return '<div class="alert alert-' . ($saved > 0 ? 'success' : 'info') . '">' . htmlspecialchars($msg) . '</div>';
            }
            if ($id > 0) {
                Capsule::table('mod_dcmanage_switch_ports')->where('id', $id)->update($payload);
            } else {
                Capsule::table('mod_dcmanage_switch_ports')->insert($payload);
            }
            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
        }

        if ($action === 'switch_port_shut' || $action === 'switch_port_noshut') {
            $id = (int) ($_POST['port_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid port');
            }

            $adminStatus = $action === 'switch_port_shut' ? 'down' : 'up';
            $operStatus = $action === 'switch_port_shut' ? 'down' : 'unknown';

            Capsule::table('mod_dcmanage_switch_ports')
                ->where('id', $id)
                ->update([
                    'admin_status' => $adminStatus,
                    'oper_status' => $operStatus,
                    'last_seen' => date('Y-m-d H:i:s'),
                ]);

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('switch_port_updated', $lang)) . '</div>';
        }

        if ($action === 'switch_port_check') {
            $id = (int) ($_POST['port_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid port');
            }

            $portRow = Capsule::table('mod_dcmanage_switch_ports as p')
                ->leftJoin('mod_dcmanage_switches as s', 's.id', '=', 'p.switch_id')
                ->where('p.id', $id)
                ->first([
                    'p.id',
                    'p.switch_id',
                    'p.if_index',
                    'p.if_name',
                    's.mgmt_ip',
                    's.snmp_community',
                    's.snmp_port',
                ]);
            if ($portRow === null) {
                throw new RuntimeException('Port not found');
            }

            $ifIndex = (int) ($portRow->if_index ?? 0);
            if ($ifIndex <= 0) {
                $ifIndex = dcmanage_resolve_ifindex_by_name(
                    (string) ($portRow->mgmt_ip ?? ''),
                    (string) ($portRow->snmp_community ?? 'public'),
                    (int) ($portRow->snmp_port ?? 161),
                    (string) ($portRow->if_name ?? '')
                );
            }
            if ($ifIndex <= 0) {
                throw new RuntimeException('ifIndex not found for selected port');
            }

            $probe = dcmanage_probe_single_switch_port(
                (string) ($portRow->mgmt_ip ?? ''),
                (string) ($portRow->snmp_community ?? 'public'),
                (int) ($portRow->snmp_port ?? 161),
                $ifIndex
            );

            if (empty($probe['ok'])) {
                throw new RuntimeException((string) ($probe['message'] ?? 'SNMP port check failed'));
            }

            $payload = [
                'if_index' => $ifIndex,
                'if_name' => dcmanage_normalize_if_name((string) ($probe['if_name'] ?? (string) $portRow->if_name)),
                'if_desc' => trim((string) ($probe['if_desc'] ?? '')) ?: null,
                'vlan' => trim((string) ($probe['vlan'] ?? '')),
                'speed_mbps' => isset($probe['speed_mbps']) && (int) $probe['speed_mbps'] > 0 ? (int) $probe['speed_mbps'] : null,
                'speed_mode' => trim((string) ($probe['speed_mode'] ?? '')) ?: null,
                'admin_status' => trim((string) ($probe['admin_status'] ?? 'unknown')),
                'oper_status' => trim((string) ($probe['oper_status'] ?? 'unknown')),
                'last_seen' => date('Y-m-d H:i:s'),
            ];

            Capsule::table('mod_dcmanage_switch_ports')->where('id', $id)->update($payload);

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('switch_port_checked', $lang)) . '</div>';
        }

        if ($action === 'server_create_bulk') {
            $dcId = (int) ($_POST['bulk_dc_id'] ?? 0);
            $rangeStart = trim((string) ($_POST['bulk_hostname_start'] ?? ''));
            $rangeEnd = trim((string) ($_POST['bulk_hostname_end'] ?? ''));
            $uHeight = max(1, (int) ($_POST['bulk_u_height'] ?? 1));
            $notes = trim((string) ($_POST['bulk_notes'] ?? ''));

            if ($dcId <= 0) {
                throw new RuntimeException('Datacenter is required');
            }

            $range = dcmanage_parse_hostname_range($rangeStart, $rangeEnd);
            $rangeSize = $range['end'] - $range['start'] + 1;
            if ($rangeSize > 1000) {
                throw new RuntimeException('Bulk range is too large (max 1000)');
            }

            $hostnames = [];
            for ($i = $range['start']; $i <= $range['end']; $i++) {
                $hostnames[] = $range['prefix'] . str_pad((string) $i, $range['pad'], '0', STR_PAD_LEFT);
            }

            $existingSet = [];
            $existingRows = Capsule::table('mod_dcmanage_servers')
                ->whereIn('hostname', $hostnames)
                ->pluck('hostname');
            foreach ($existingRows as $existingHostname) {
                $existingSet[(string) $existingHostname] = true;
            }

            $now = date('Y-m-d H:i:s');
            $insertRows = [];
            foreach ($hostnames as $hostname) {
                if (isset($existingSet[$hostname])) {
                    continue;
                }
                $insertRows[] = [
                    'dc_id' => $dcId,
                    'rack_id' => null,
                    'hostname' => $hostname,
                    'asset_tag' => '',
                    'serial' => '',
                    'u_start' => null,
                    'u_height' => $uHeight,
                    'notes' => $notes,
                    'created_at' => $now,
                ];
            }

            if ($insertRows !== []) {
                Capsule::table('mod_dcmanage_servers')->insert($insertRows);
            }

            $createdCount = count($insertRows);
            $skippedCount = count($hostnames) - $createdCount;
            $message = sprintf(I18n::t('server_bulk_result', $lang), $createdCount, $skippedCount);
            $class = $createdCount > 0 ? 'success' : 'warning';

            return '<div class="alert alert-' . $class . '">' . htmlspecialchars($message) . '</div>';
        }

        if ($action === 'server_create') {
            $dcId = (int) ($_POST['dc_id'] ?? 0);
            $rackId = (int) ($_POST['rack_id'] ?? 0);
            $hostname = trim((string) ($_POST['hostname'] ?? ''));
            if ($dcId <= 0 || $hostname === '') {
                throw new RuntimeException('Datacenter and hostname are required');
            }

            $switchId = (int) ($_POST['switch_id'] ?? 0);
            $switchPortId = (int) ($_POST['switch_port_id'] ?? 0);

            $serverId = (int) Capsule::table('mod_dcmanage_servers')->insertGetId([
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

            $iloId = dcmanage_upsert_server_ilo(
                $serverId,
                $dcId,
                $hostname,
                trim((string) ($_POST['ilo_host'] ?? '')),
                trim((string) ($_POST['ilo_user'] ?? '')),
                (string) ($_POST['ilo_pass'] ?? ''),
                trim((string) ($_POST['ilo_type'] ?? 'ilo5'))
            );
            if ($iloId !== null) {
                Capsule::table('mod_dcmanage_servers')->where('id', $serverId)->update(['ilo_id' => $iloId]);
            }

            $serverPortId = 0;
            $switchIf = null;
            if ($switchPortId > 0) {
                $switchPort = Capsule::table('mod_dcmanage_switch_ports')
                    ->where('id', $switchPortId)
                    ->first(['id', 'switch_id', 'if_name']);
                if ($switchPort === null) {
                    throw new RuntimeException('Selected switch port not found');
                }

                $switchId = $switchId > 0 ? $switchId : (int) $switchPort->switch_id;
                if ((int) $switchPort->switch_id !== $switchId) {
                    throw new RuntimeException('Selected switch/port mismatch');
                }
                $switchIf = (string) $switchPort->if_name;
            }

            if ($switchId > 0 || $switchIf !== null) {
                $serverPortId = (int) Capsule::table('mod_dcmanage_server_ports')->insertGetId([
                    'server_id' => $serverId,
                    'port_no' => 1,
                    'network_id' => null,
                    'switch_id' => $switchId > 0 ? $switchId : null,
                    'switch_if' => $switchIf,
                    'prtg_sensor_id' => null,
                    'prtg_channel_in' => null,
                    'prtg_channel_out' => null,
                    'enforce_enabled' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('created', $lang)) . '</div>';
        }

        if ($action === 'server_link_update') {
            $serverId = (int) ($_POST['server_id'] ?? 0);
            if ($serverId <= 0) {
                throw new RuntimeException('server_id is required');
            }

            $server = Capsule::table('mod_dcmanage_servers')->where('id', $serverId)->first(['id', 'dc_id']);
            if ($server === null) {
                throw new RuntimeException('Server not found');
            }

            $switchId = (int) ($_POST['switch_id'] ?? 0);
            $switchPortId = (int) ($_POST['switch_port_id'] ?? 0);
            $prtgId = (int) ($_POST['prtg_id'] ?? 0);
            $sensorIds = dcmanage_parse_prtg_sensor_ids(
                $_POST['prtg_sensor_ids'] ?? [],
                (string) ($_POST['prtg_sensor_ids_manual'] ?? '')
            );

            if ($switchId > 0) {
                $switchExists = Capsule::table('mod_dcmanage_switches')
                    ->where('id', $switchId)
                    ->where('dc_id', (int) $server->dc_id)
                    ->exists();
                if (!$switchExists) {
                    throw new RuntimeException('Switch must belong to server datacenter');
                }
            }

            $switchIf = null;
            if ($switchPortId > 0) {
                $switchPort = Capsule::table('mod_dcmanage_switch_ports')
                    ->where('id', $switchPortId)
                    ->first(['id', 'switch_id', 'if_name']);
                if ($switchPort === null) {
                    throw new RuntimeException('Selected switch port not found');
                }
                if ($switchId > 0 && (int) $switchPort->switch_id !== $switchId) {
                    throw new RuntimeException('Selected switch/port mismatch');
                }
                $switchId = (int) $switchPort->switch_id;
                $switchIf = (string) $switchPort->if_name;
            }

            if ($switchId > 0 || $switchIf !== null) {
                Capsule::table('mod_dcmanage_server_ports')->updateOrInsert(
                    [
                        'server_id' => $serverId,
                        'port_no' => 1,
                    ],
                    [
                        'network_id' => null,
                        'switch_id' => $switchId > 0 ? $switchId : null,
                        'switch_if' => $switchIf,
                        'prtg_sensor_id' => isset($sensorIds[0]) ? (string) $sensorIds[0] : null,
                        'prtg_channel_in' => null,
                        'prtg_channel_out' => null,
                        'enforce_enabled' => 0,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]
                );
            } else {
                Capsule::table('mod_dcmanage_server_ports')
                    ->where('server_id', $serverId)
                    ->where('port_no', 1)
                    ->delete();
            }

            if (Capsule::schema()->hasTable('mod_dcmanage_server_traffic_sensors')) {
                Capsule::table('mod_dcmanage_server_traffic_sensors')
                    ->where('server_id', $serverId)
                    ->delete();

                foreach ($sensorIds as $sensorId) {
                    Capsule::table('mod_dcmanage_server_traffic_sensors')->insert([
                        'server_id' => $serverId,
                        'prtg_id' => $prtgId > 0 ? $prtgId : null,
                        'sensor_id' => $sensorId,
                        'sensor_name' => null,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $iloHost = trim((string) ($_POST['ilo_host'] ?? ''));
            $iloUser = trim((string) ($_POST['ilo_user'] ?? ''));
            $iloPass = (string) ($_POST['ilo_pass'] ?? '');
            $iloType = trim((string) ($_POST['ilo_type'] ?? 'ilo5'));
            $iloId = dcmanage_upsert_server_ilo(
                $serverId,
                (int) $server->dc_id,
                'srv-' . $serverId,
                $iloHost,
                $iloUser,
                $iloPass,
                $iloType
            );
            Capsule::table('mod_dcmanage_servers')->where('id', $serverId)->update(['ilo_id' => $iloId]);

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
        }

        if ($action === 'server_delete') {
            $serverId = (int) ($_POST['server_id'] ?? 0);
            if ($serverId <= 0) {
                throw new RuntimeException('Invalid server');
            }
            $server = Capsule::table('mod_dcmanage_servers')->where('id', $serverId)->first(['id', 'ilo_id']);
            if ($server === null) {
                throw new RuntimeException('Server not found');
            }

            Capsule::table('mod_dcmanage_server_ports')->where('server_id', $serverId)->delete();
            if (Capsule::schema()->hasTable('mod_dcmanage_server_traffic_sensors')) {
                Capsule::table('mod_dcmanage_server_traffic_sensors')->where('server_id', $serverId)->delete();
            }
            Capsule::table('mod_dcmanage_servers')->where('id', $serverId)->delete();

            $iloId = (int) ($server->ilo_id ?? 0);
            if ($iloId > 0) {
                $inUse = Capsule::table('mod_dcmanage_servers')->where('ilo_id', $iloId)->exists();
                if (!$inUse) {
                    Capsule::table('mod_dcmanage_ilos')->where('id', $iloId)->delete();
                }
            }

            return '<div class="alert alert-success">' . htmlspecialchars(I18n::t('saved', $lang)) . '</div>';
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
        'switch_discovery_minutes' => '30',
        'graph_cache_ttl_minutes' => '30',
        'log_retention_days' => '90',
        'dashboard_refresh_seconds' => '30',
        'ilo_proxy_type' => 'http',
        'ilo_proxy_host' => '',
        'ilo_proxy_port' => '',
        'ilo_proxy_user' => '',
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
        if ($key === 'ilo_proxy_type') {
            $value = strtolower($value);
            if (!in_array($value, ['http', 'https', 'socks5'], true)) {
                $value = 'http';
            }
        }
        if (in_array($key, ['traffic_poll_minutes', 'switch_discovery_minutes', 'graph_cache_ttl_minutes', 'log_retention_days', 'dashboard_refresh_seconds'], true)) {
            $value = (string) max(1, (int) $value);
        }

        Capsule::table('mod_dcmanage_meta')->updateOrInsert(
            ['meta_key' => 'settings.' . $key],
            ['meta_value' => $value, 'updated_at' => date('Y-m-d H:i:s')]
        );
    }

    $proxyPass = trim((string) ($_POST['ilo_proxy_pass'] ?? ''));
    if ($proxyPass !== '') {
        Capsule::table('mod_dcmanage_meta')->updateOrInsert(
            ['meta_key' => 'settings.ilo_proxy_pass_enc'],
            ['meta_value' => Crypto::encrypt($proxyPass), 'updated_at' => date('Y-m-d H:i:s')]
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
    $singleCron = '* * * * * ' . $phpBin . ' -q ' . $cronScriptArg . ' dispatcher';

    return [
        ['task' => 'poll_usage', 'interval' => 300, 'cron' => $singleCron],
        ['task' => 'enforce_queue', 'interval' => 60, 'cron' => I18n::t('cron_managed_internally')],
        ['task' => 'graph_warm', 'interval' => 1800, 'cron' => I18n::t('cron_managed_internally')],
        ['task' => 'cleanup', 'interval' => 86400, 'cron' => I18n::t('cron_managed_internally')],
        ['task' => 'switch_discovery', 'interval' => 300, 'cron' => I18n::t('cron_managed_internally')],
        ['task' => 'self_update', 'interval' => 86400, 'cron' => I18n::t('cron_managed_internally')],
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
        try {
            $quoted = call_user_func('escapeshellarg', $value);
            if (is_string($quoted) && $quoted !== '') {
                return $quoted;
            }
        } catch (\Throwable $e) {
            // Fall back to internal quoting when escapeshellarg is disabled/restricted.
        }
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
        $patterns = dcmanage_cron_success_patterns((string) $def['task']);
        $last = Capsule::table('mod_dcmanage_logs')
            ->where('source', 'cron')
            ->where(static function ($q) use ($patterns): void {
                foreach ($patterns as $idx => $pattern) {
                    if ($idx === 0) {
                        $q->where('message', 'like', $pattern);
                    } else {
                        $q->orWhere('message', 'like', $pattern);
                    }
                }
            })
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
                if ($age <= ((int) $def['interval'] * 2)) {
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

function dcmanage_cron_success_patterns(string $task): array
{
    $patterns = ['task:' . $task . ' completed%'];
    $legacy = [
        'poll_usage' => ['poll_usage processed%'],
        'enforce_queue' => ['enforce_queue executed%'],
        'graph_warm' => ['graph_warm executed%'],
        'cleanup' => ['cleanup executed%'],
        'switch_discovery' => ['switch_discovery executed%'],
        'self_update' => ['self_update executed%'],
    ];

    if (isset($legacy[$task])) {
        foreach ($legacy[$task] as $pattern) {
            $patterns[] = $pattern;
        }
    }

    return $patterns;
}

function dcmanage_render_settings_form(string $lang): void
{
    $settings = dcmanage_system_settings();
    $cron = dcmanage_cron_status();

    echo '<h5 class="mb-3">' . htmlspecialchars(I18n::t('system_settings', $lang)) . '</h5>';
    echo '<form method="post" action="" class="dcmanage-form-card">';
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
    echo '<div class="col-md-4 mb-3"><label>' . htmlspecialchars(I18n::t('settings_discovery_minutes', $lang)) . '</label><input class="form-control dcmanage-input" name="switch_discovery_minutes" value="' . htmlspecialchars($settings['switch_discovery_minutes']) . '"></div>';
    echo '<div class="col-md-4 mb-3"><label>Graph Cache TTL (Minutes)</label><input class="form-control dcmanage-input" name="graph_cache_ttl_minutes" value="' . htmlspecialchars($settings['graph_cache_ttl_minutes']) . '"></div>';
    echo '<div class="col-md-4 mb-3"><label>Log Retention (Days)</label><input class="form-control dcmanage-input" name="log_retention_days" value="' . htmlspecialchars($settings['log_retention_days']) . '"></div>';
    echo '<div class="col-md-4 mb-3"><label>Dashboard Refresh (Seconds)</label><input class="form-control dcmanage-input" name="dashboard_refresh_seconds" value="' . htmlspecialchars($settings['dashboard_refresh_seconds']) . '"></div>';
    echo '<div class="col-md-12"><hr class="my-2"></div>';
    echo '<div class="col-md-3 mb-3"><label>' . htmlspecialchars(I18n::t('ilo_proxy_type', $lang)) . '</label><select class="form-control dcmanage-input" name="ilo_proxy_type">';
    $proxyTypes = ['http' => 'HTTP', 'https' => 'HTTPS', 'socks5' => 'SOCKS5'];
    foreach ($proxyTypes as $k => $label) {
        $selected = (string) ($settings['ilo_proxy_type'] ?? 'http') === $k ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($k) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="col-md-3 mb-3"><label>' . htmlspecialchars(I18n::t('ilo_proxy_host', $lang)) . '</label><input class="form-control dcmanage-input" name="ilo_proxy_host" value="' . htmlspecialchars((string) ($settings['ilo_proxy_host'] ?? '')) . '"></div>';
    echo '<div class="col-md-2 mb-3"><label>' . htmlspecialchars(I18n::t('ilo_proxy_port', $lang)) . '</label><input class="form-control dcmanage-input" name="ilo_proxy_port" value="' . htmlspecialchars((string) ($settings['ilo_proxy_port'] ?? '')) . '"></div>';
    echo '<div class="col-md-2 mb-3"><label>' . htmlspecialchars(I18n::t('ilo_proxy_user', $lang)) . '</label><input class="form-control dcmanage-input" name="ilo_proxy_user" value="' . htmlspecialchars((string) ($settings['ilo_proxy_user'] ?? '')) . '"></div>';
    echo '<div class="col-md-2 mb-3"><label>' . htmlspecialchars(I18n::t('ilo_proxy_pass', $lang)) . '</label><input type="password" class="form-control dcmanage-input" name="ilo_proxy_pass" value=""></div>';

    echo '</div>';
    echo '<div class="dcmanage-form-actions d-flex flex-wrap">';
    echo '<button type="submit" class="btn btn-primary" name="dcmanage_action_btn" value="settings_save">' . htmlspecialchars(I18n::t('save_settings', $lang)) . '</button>';
    echo '<button type="submit" class="btn btn-outline-primary" name="dcmanage_action_btn" value="settings_ilo_proxy_test">' . htmlspecialchars(I18n::t('ilo_proxy_test', $lang)) . '</button>';
    echo '</div>';
    echo '</form>';

    $overallClass = $cron['overall'] === 'ok' ? 'success' : ($cron['overall'] === 'fail' ? 'danger' : 'warning');
    echo '<hr class="my-4">';
    if (!empty($cron['items'][0]['cron'])) {
        echo '<div class="alert alert-info text-break mb-3"><strong>' . htmlspecialchars(I18n::t('cron_single_entry', $lang)) . ':</strong> <code>' . htmlspecialchars((string) $cron['items'][0]['cron']) . '</code></div>';
    }
    echo '<h5 class="mb-3">' . htmlspecialchars(I18n::t('cron_monitor', $lang)) . ' <span class="badge badge-' . $overallClass . '">' . htmlspecialchars(I18n::t('cron_overall', $lang)) . '</span></h5>';
    echo '<div class="table-responsive dcmanage-table-wrap">';
    echo '<table class="table table-sm table-bordered">';
    echo '<thead><tr><th>' . htmlspecialchars(I18n::t('cron_task', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('cron_status', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('cron_last_run', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('cron_next_run', $lang)) . '</th></tr></thead><tbody>';

    foreach ($cron['items'] as $item) {
        $cls = $item['status'] === 'ok' ? 'success' : ($item['status'] === 'fail' ? 'danger' : 'warning');
        $label = $item['status'] === 'ok' ? I18n::t('status_ok', $lang) : ($item['status'] === 'fail' ? I18n::t('status_fail', $lang) : I18n::t('status_warning', $lang));
        echo '<tr>';
        echo '<td>' . htmlspecialchars($item['task']) . '</td>';
        echo '<td><span class="badge badge-' . $cls . '">' . htmlspecialchars($label) . '</span></td>';
        echo '<td>' . htmlspecialchars($item['last']) . '</td>';
        echo '<td>' . htmlspecialchars($item['next']) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function dcmanage_render_monitoring(string $lang): void
{
    $provider = Capsule::table('mod_dcmanage_meta')->where('meta_key', 'monitoring.provider')->value('meta_value');
    $provider = strtolower(trim((string) ($provider ?: 'prtg')));
    $instances = Capsule::table('mod_dcmanage_prtg_instances')->orderBy('id', 'desc')->get(['id', 'name', 'base_url', 'user', 'verify_ssl', 'created_at']);

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

    if ($provider !== 'prtg') {
        return;
    }

    echo '<div class="row mt-4">';
    echo '<div class="col-lg-5 mb-4">';
    echo '<h6 class="mb-3">' . htmlspecialchars(I18n::t('prtg_add_instance', $lang)) . '</h6>';
    echo '<form method="post" action="" class="dcmanage-form-card">';
    echo '<input type="hidden" name="dcmanage_action" value="prtg_instance_create">';
    echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('prtg_name', $lang)) . '</label><input name="prtg_name" class="form-control dcmanage-input" required></div>';
    echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('prtg_base_url', $lang)) . '</label><input name="prtg_base_url" class="form-control dcmanage-input" placeholder="https://prtg.example.com" required></div>';
    echo '<div class="form-row">';
    echo '<div class="form-group col-md-6"><label>' . htmlspecialchars(I18n::t('prtg_user', $lang)) . '</label><input name="prtg_user" class="form-control dcmanage-input" required></div>';
    echo '<div class="form-group col-md-6"><label>' . htmlspecialchars(I18n::t('prtg_passhash', $lang)) . '</label><input name="prtg_passhash" class="form-control dcmanage-input" required></div>';
    echo '</div>';
    echo '<div class="form-group"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="dcmanage-prtg-verify-ssl" name="prtg_verify_ssl" value="1" checked><label class="custom-control-label" for="dcmanage-prtg-verify-ssl">' . htmlspecialchars(I18n::t('prtg_verify_ssl', $lang)) . '</label></div></div>';
    echo '<button class="btn btn-primary" type="submit">' . htmlspecialchars(I18n::t('prtg_create', $lang)) . '</button>';
    echo '</form>';
    echo '</div>';

    echo '<div class="col-lg-7">';
    echo '<h6 class="mb-3">' . htmlspecialchars(I18n::t('prtg_instances', $lang)) . '</h6>';
    echo '<div class="table-responsive dcmanage-table-wrap"><table class="table table-sm table-striped">';
    echo '<thead><tr><th>ID</th><th>' . htmlspecialchars(I18n::t('prtg_name', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('prtg_base_url', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('prtg_user', $lang)) . '</th><th>SSL</th><th>' . htmlspecialchars(I18n::t('label_actions', $lang)) . '</th></tr></thead><tbody>';
    foreach ($instances as $instance) {
        echo '<tr>';
        echo '<td>' . (int) $instance->id . '</td>';
        echo '<td>' . htmlspecialchars((string) $instance->name) . '</td>';
        echo '<td>' . htmlspecialchars((string) $instance->base_url) . '</td>';
        echo '<td>' . htmlspecialchars((string) $instance->user) . '</td>';
        echo '<td>' . ((int) $instance->verify_ssl === 1 ? 'on' : 'off') . '</td>';
        echo '<td class="dcmanage-action-buttons">';
        echo '<form method="post" style="display:inline"><input type="hidden" name="dcmanage_action" value="prtg_instance_test"><input type="hidden" name="prtg_id" value="' . (int) $instance->id . '"><button type="submit" class="btn btn-sm dcmanage-btn-soft-success">' . htmlspecialchars(I18n::t('prtg_test', $lang)) . '</button></form>';
        echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Delete PRTG instance?\')"><input type="hidden" name="dcmanage_action" value="prtg_instance_delete"><input type="hidden" name="prtg_id" value="' . (int) $instance->id . '"><button type="submit" class="btn btn-sm dcmanage-btn-soft-danger">' . htmlspecialchars(I18n::t('action_delete', $lang)) . '</button></form>';
        echo '</td>';
        echo '</tr>';
    }
    if (count($instances) === 0) {
        echo '<tr><td colspan="6">-</td></tr>';
    }
    echo '</tbody></table></div>';
    echo '</div>';
    echo '</div>';
}

function dcmanage_parse_prtg_sensor_ids($selected, string $manual): array
{
    $items = [];
    $push = static function (string $candidate) use (&$items): void {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return;
        }
        if (preg_match('/^\d+$/', $candidate) !== 1) {
            return;
        }
        $items[$candidate] = $candidate;
    };

    if (is_array($selected)) {
        foreach ($selected as $value) {
            $push((string) $value);
        }
    } elseif (is_string($selected)) {
        foreach (preg_split('/[\s,;]+/', $selected) ?: [] as $value) {
            $push((string) $value);
        }
    }

    foreach (preg_split('/[\s,;]+/', $manual) ?: [] as $value) {
        $push((string) $value);
    }

    return array_values($items);
}

function dcmanage_upsert_server_ilo(
    int $serverId,
    int $dcId,
    string $serverHostname,
    string $host,
    string $user,
    string $pass,
    string $type
): ?int {
    $host = trim($host);
    $user = trim($user);
    $type = strtolower(trim($type));
    if (!in_array($type, ['ilo4', 'ilo5'], true)) {
        $type = 'ilo5';
    }

    if ($host === '') {
        return null;
    }
    if ($user === '') {
        throw new RuntimeException('iLO username is required when iLO host is set');
    }

    $existingIloId = (int) Capsule::table('mod_dcmanage_servers')->where('id', $serverId)->value('ilo_id');
    if ($existingIloId > 0) {
        $payload = [
            'dc_id' => $dcId,
            'name' => $serverHostname . '-iLO',
            'host' => $host,
            'user' => $user,
            'type' => $type,
        ];
        if (trim($pass) !== '') {
            $payload['pass_enc'] = Crypto::encrypt($pass);
        }
        Capsule::table('mod_dcmanage_ilos')->where('id', $existingIloId)->update($payload);
        return $existingIloId;
    }

    if (trim($pass) === '') {
        throw new RuntimeException('iLO password is required when attaching a new iLO');
    }

    return (int) Capsule::table('mod_dcmanage_ilos')->insertGetId([
        'dc_id' => $dcId,
        'name' => $serverHostname . '-iLO',
        'host' => $host,
        'user' => $user,
        'pass_enc' => Crypto::encrypt($pass),
        'type' => $type,
        'notes' => null,
    ]);
}

function dcmanage_test_proxy_connection(string $type, string $host, int $port, string $user = '', string $pass = ''): array
{
    $type = strtolower(trim($type));
    if (!in_array($type, ['http', 'https', 'socks5'], true)) {
        return ['ok' => false, 'message' => 'Unsupported proxy type'];
    }
    $host = trim($host);
    if ($host === '' || $port <= 0) {
        return ['ok' => false, 'message' => 'Proxy host and port are required'];
    }

    $socket = @fsockopen($host, $port, $errno, $errstr, 4.0);
    if ($socket) {
        fclose($socket);
    } else {
        return ['ok' => false, 'message' => 'Proxy TCP connection failed: ' . $errstr . ' (' . (int) $errno . ')'];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => true, 'message' => 'Proxy TCP connection is reachable'];
    }

    $ch = curl_init('https://example.com/');
    if ($ch === false) {
        return ['ok' => true, 'message' => 'Proxy TCP connection is reachable'];
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_PROXY, $host);
    curl_setopt($ch, CURLOPT_PROXYPORT, $port);
    if ($type === 'socks5') {
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
    } elseif ($type === 'https') {
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTPS);
    } else {
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
    }
    if (trim($user) !== '') {
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $user . ':' . $pass);
    }
    curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($err !== '') {
        return ['ok' => false, 'message' => 'Proxy check failed: ' . $err];
    }
    if ($code <= 0) {
        return ['ok' => true, 'message' => 'Proxy reachable (no HTTP response code)'];
    }

    return ['ok' => true, 'message' => 'Proxy is reachable and responding'];
}

function dcmanage_parse_hostname_range(string $startHostname, string $endHostname): array
{
    $startHostname = trim($startHostname);
    $endHostname = trim($endHostname);
    if ($startHostname === '' || $endHostname === '') {
        throw new RuntimeException('Start and end hostname are required');
    }

    if (preg_match('/^(.*?)(\d+)$/', $startHostname, $startMatch) !== 1
        || preg_match('/^(.*?)(\d+)$/', $endHostname, $endMatch) !== 1) {
        throw new RuntimeException('Hostname range must end with numeric value');
    }

    $prefix = (string) $startMatch[1];
    if ($prefix !== (string) $endMatch[1]) {
        throw new RuntimeException('Hostname prefix mismatch in bulk range');
    }

    $start = (int) $startMatch[2];
    $end = (int) $endMatch[2];
    if ($end < $start) {
        throw new RuntimeException('Range end must be greater than or equal to range start');
    }

    return [
        'prefix' => $prefix,
        'start' => $start,
        'end' => $end,
        'pad' => max(strlen((string) $startMatch[2]), strlen((string) $endMatch[2])),
    ];
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

    echo '<div class="d-flex justify-content-end align-items-center mb-4 dcmanage-dc-topbar">';
    echo '<button class="btn btn-primary btn-sm" type="button" data-toggle="collapse" data-target="#dcmanage-dc-add">' . htmlspecialchars(I18n::t('datacenter_add', $lang)) . '</button>';
    echo '</div>';

    echo '<div class="collapse mb-4 dcmanage-dc-add-wrap" id="dcmanage-dc-add">';
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

    echo '<div class="dcmanage-dc-table-wrap mb-4"><div class="table-responsive"><table class="table table-sm table-striped dcmanage-dc-table">';
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
    echo '</tbody></table></div></div>';
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

    $timeoutMicros = 1000000;
    $retries = 0;
    $target = $host . ':' . max(1, $port);
    $value = dcmanage_snmp_get($target, $community === '' ? 'public' : $community, '.1.3.6.1.2.1.1.1.0', $timeoutMicros, $retries);
    if ($value !== false) {
        return ['ok' => true, 'message' => 'SNMP connected'];
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

function dcmanage_snmp_get(string $target, string $community, string $oid, int $timeoutMicros, int $retries)
{
    if (function_exists('snmp2_get')) {
        return @snmp2_get($target, $community, $oid, $timeoutMicros, $retries);
    }
    if (function_exists('snmpget')) {
        return @snmpget($target, $community, $oid, $timeoutMicros, $retries);
    }
    return false;
}

function dcmanage_snmp_real_walk_any(string $target, string $community, string $oid, int $timeoutMicros, int $retries): array
{
    if (function_exists('snmp2_real_walk')) {
        $out = @snmp2_real_walk($target, $community, $oid, $timeoutMicros, $retries);
        if (is_array($out) && count($out) > 0) {
            return $out;
        }
    }
    if (function_exists('snmprealwalk')) {
        $out = @snmprealwalk($target, $community, $oid, $timeoutMicros, $retries);
        if (is_array($out) && count($out) > 0) {
            return $out;
        }
    }
    return [];
}

function dcmanage_snmp_walk_list_any(string $target, string $community, string $oid, int $timeoutMicros, int $retries): array
{
    if (function_exists('snmp2_walk')) {
        $out = @snmp2_walk($target, $community, $oid, $timeoutMicros, $retries);
        if (is_array($out) && count($out) > 0) {
            return array_values($out);
        }
    }
    if (function_exists('snmpwalk')) {
        $out = @snmpwalk($target, $community, $oid, $timeoutMicros, $retries);
        if (is_array($out) && count($out) > 0) {
            return array_values($out);
        }
    }
    return [];
}

function dcmanage_normalize_if_name(string $ifName): string
{
    $ifName = trim($ifName);
    if ($ifName === '') {
        return '';
    }
    $ifName = preg_replace('/\s+/', '', $ifName);
    if (!is_string($ifName)) {
        return '';
    }
    return trim($ifName);
}

function dcmanage_snmp_parse_typed_value(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    $parts = explode(':', $raw, 2);
    if (count($parts) === 2) {
        $raw = trim($parts[1]);
    }

    $raw = trim($raw, "\" \t\n\r\0\x0B");
    return $raw;
}

function dcmanage_snmp_walk_to_index_map(array $walk): array
{
    $indexed = [];
    foreach ($walk as $oid => $raw) {
        $oid = (string) $oid;
        if (!preg_match('/\.(\d+)$/', $oid, $m)) {
            continue;
        }
        $indexed[(int) $m[1]] = (string) $raw;
    }

    return $indexed;
}

function dcmanage_snmp_parse_vlan(string $raw): string
{
    $value = dcmanage_snmp_parse_typed_value($raw);
    if ($value === '') {
        return '';
    }

    if (preg_match('/\((\d+)\)/', $value, $m) === 1) {
        return (string) ((int) $m[1]);
    }

    if (preg_match('/^\d+$/', $value) === 1) {
        return (string) ((int) $value);
    }

    // Hex-style VLAN values like "00 0A" or "0x000A".
    if (preg_match('/^0x([0-9a-fA-F]+)$/', $value, $m) === 1) {
        return (string) hexdec($m[1]);
    }
    if (preg_match('/^(?:[0-9a-fA-F]{2}\s+)+[0-9a-fA-F]{2}$/', $value) === 1) {
        $hex = str_replace(' ', '', $value);
        return (string) hexdec($hex);
    }

    if (preg_match('/\d+/', $value, $m) === 1) {
        return (string) ((int) $m[0]);
    }

    return '';
}

function dcmanage_vlan_fallback_for_interface(string $ifName, string $ifDesc = ''): string
{
    $ifName = trim($ifName);
    $ifDesc = trim($ifDesc);

    // Common SVI naming: Vlan10 / vlan10 / Vlanif10
    if (preg_match('/^(?:vlan|vlanif)\s*[-_\/]?\s*(\d{1,4})$/i', $ifName, $m) === 1) {
        return (string) ((int) $m[1]);
    }
    if (preg_match('/\bvlan\s*[-_\/]?\s*(\d{1,4})\b/i', $ifName, $m) === 1) {
        return (string) ((int) $m[1]);
    }

    // Secondary fallback from description if it explicitly includes VLAN id.
    if (preg_match('/\bvlan\s*[-_\/]?\s*(\d{1,4})\b/i', $ifDesc, $m) === 1) {
        return (string) ((int) $m[1]);
    }

    return '';
}

function dcmanage_snmp_parse_int(string $raw): int
{
    $value = dcmanage_snmp_parse_typed_value($raw);
    if ($value === '') {
        return 0;
    }
    if (preg_match('/\d+/', $value, $m)) {
        return (int) $m[0];
    }

    return 0;
}

function dcmanage_snmp_speed_mbps_from_raw(string $highSpeedRaw, string $ifSpeedRaw): ?int
{
    $high = dcmanage_snmp_parse_int($highSpeedRaw);
    if ($high > 0) {
        return dcmanage_normalize_speed_mbps($high);
    }

    $ifSpeedBps = dcmanage_snmp_parse_int($ifSpeedRaw);
    if ($ifSpeedBps > 0) {
        return dcmanage_normalize_speed_mbps((int) round($ifSpeedBps / 1000000));
    }

    return null;
}

function dcmanage_normalize_speed_mbps(int $speedMbps): ?int
{
    if ($speedMbps <= 0) {
        return null;
    }

    // Some devices report non-Mbps units; normalize oversized values heuristically.
    if ($speedMbps > 1000000 && $speedMbps < 1000000000) {
        $speedMbps = (int) round($speedMbps / 1000);
    } elseif ($speedMbps >= 1000000000) {
        $speedMbps = (int) round($speedMbps / 1000000);
    }

    $known = [10, 100, 1000, 2500, 5000, 10000, 25000, 40000, 50000, 100000, 200000, 400000, 800000];
    $closest = $speedMbps;
    $closestDiff = PHP_INT_MAX;
    foreach ($known as $candidate) {
        $diff = abs($candidate - $speedMbps);
        if ($diff < $closestDiff) {
            $closestDiff = $diff;
            $closest = $candidate;
        }
    }

    // Snap to nearest common Ethernet speed if reasonably close.
    if ($closest > 0 && ($closestDiff / $closest) <= 0.30) {
        return $closest;
    }

    return $speedMbps;
}

function dcmanage_snmp_autoneg_mode_from_raw(string $raw): string
{
    $value = strtolower(dcmanage_snmp_parse_typed_value($raw));
    if ($value === '') {
        return 'unknown';
    }

    if (strpos($value, '(1)') !== false || strpos($value, 'enabled') !== false || $value === '1') {
        return 'auto';
    }
    if (strpos($value, '(2)') !== false || strpos($value, 'disabled') !== false || $value === '2') {
        return 'fixed';
    }

    return 'unknown';
}

function dcmanage_port_speed_label(?int $speedMbps, ?string $speedMode, string $lang): string
{
    if ($speedMbps === null || $speedMbps <= 0) {
        return '-';
    }

    $speedMbps = dcmanage_normalize_speed_mbps($speedMbps);
    if ($speedMbps === null || $speedMbps <= 0) {
        return '-';
    }

    $speedLabel = $speedMbps . 'M';
    if ($speedMbps >= 1000) {
        if ($speedMbps % 1000 === 0) {
            $speedLabel = ((string) ($speedMbps / 1000)) . 'G';
        } else {
            $speedLabel = rtrim(rtrim(number_format($speedMbps / 1000, 1, '.', ''), '0'), '.') . 'G';
        }
    }

    $mode = strtolower(trim((string) $speedMode));
    if ($mode === 'auto') {
        return I18n::t('label_auto', $lang) . ' ' . $speedLabel;
    }

    return $speedLabel;
}

function dcmanage_snmp_status_from_raw(string $raw, string $kind = 'generic'): string
{
    $value = strtolower(dcmanage_snmp_parse_typed_value($raw));
    if ($value === '') {
        return 'unknown';
    }

    $kind = strtolower(trim($kind));
    if ($kind === 'oper') {
        $hasAbsentWord = strpos($value, 'absent') !== false || strpos($value, 'abcent') !== false;
        if (strpos($value, '(1)') !== false || $value === '1' || strpos($value, 'up') !== false) {
            return 'up';
        }
        if (
            strpos($value, '(6)') !== false
            || $value === '6'
            || strpos($value, 'notpresent') !== false
            || strpos($value, 'not present') !== false
            || $hasAbsentWord
            || ((strpos($value, 'sfp') !== false || strpos($value, 'ospf') !== false) && $hasAbsentWord)
        ) {
            return 'absent';
        }
        if (
            strpos($value, '(2)') !== false
            || strpos($value, '(5)') !== false
            || strpos($value, '(7)') !== false
            || $value === '2'
            || $value === '5'
            || $value === '7'
            || strpos($value, 'down') !== false
            || strpos($value, 'dormant') !== false
            || strpos($value, 'lowerlayerdown') !== false
            || strpos($value, 'notconnect') !== false
            || strpos($value, 'not connected') !== false
        ) {
            return 'down';
        }

        return 'unknown';
    }

    if (strpos($value, '(1)') !== false || $value === '1' || strpos($value, 'up') !== false) {
        return 'up';
    }
    if (strpos($value, '(2)') !== false || $value === '2' || strpos($value, 'down') !== false) {
        return 'down';
    }

    return 'unknown';
}

function dcmanage_resolve_ifindex_by_name(string $host, string $community, int $port, string $ifName): int
{
    $ifName = dcmanage_normalize_if_name($ifName);
    if ($ifName === '' || trim($host) === '') {
        return 0;
    }

    $target = trim($host) . ':' . max(1, $port);
    $community = trim($community) === '' ? 'public' : $community;
    $map = dcmanage_snmp_walk_to_index_map(dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.31.1.1.1.1', 1000000, 0));
    if ($map === []) {
        $map = dcmanage_snmp_walk_to_index_map(dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.2.2.1.2', 1000000, 0));
    }

    foreach ($map as $idx => $raw) {
        $candidate = dcmanage_normalize_if_name(dcmanage_snmp_parse_typed_value((string) $raw));
        if ($candidate !== '' && strcasecmp($candidate, $ifName) === 0) {
            return (int) $idx;
        }
    }

    return 0;
}

function dcmanage_probe_single_switch_port(string $host, string $community, int $port, int $ifIndex): array
{
    $host = trim($host);
    if ($host === '' || $ifIndex <= 0) {
        return ['ok' => false, 'message' => 'Invalid target'];
    }

    $target = $host . ':' . max(1, $port);
    $community = trim($community) === '' ? 'public' : $community;
    $timeout = 1000000;
    $retries = 0;

    $ifNameRaw = (string) dcmanage_snmp_get($target, $community, '.1.3.6.1.2.1.31.1.1.1.1.' . $ifIndex, $timeout, $retries);
    if ($ifNameRaw === '' || strtolower($ifNameRaw) === 'false') {
        $ifNameRaw = (string) dcmanage_snmp_get($target, $community, '.1.3.6.1.2.1.2.2.1.2.' . $ifIndex, $timeout, $retries);
    }
    $ifDescRaw = (string) dcmanage_snmp_get($target, $community, '.1.3.6.1.2.1.31.1.1.1.18.' . $ifIndex, $timeout, $retries);
    if ($ifDescRaw === '' || strtolower($ifDescRaw) === 'false') {
        $ifDescRaw = $ifNameRaw;
    }
    $adminRaw = (string) dcmanage_snmp_get($target, $community, '.1.3.6.1.2.1.2.2.1.7.' . $ifIndex, $timeout, $retries);
    $operRaw = (string) dcmanage_snmp_get($target, $community, '.1.3.6.1.2.1.2.2.1.8.' . $ifIndex, $timeout, $retries);
    $ifHighSpeedRaw = (string) dcmanage_snmp_get($target, $community, '.1.3.6.1.2.1.31.1.1.1.15.' . $ifIndex, $timeout, $retries);
    $ifSpeedRaw = (string) dcmanage_snmp_get($target, $community, '.1.3.6.1.2.1.2.2.1.5.' . $ifIndex, $timeout, $retries);
    $autoNegRaw = (string) dcmanage_snmp_get($target, $community, '.1.3.6.1.2.1.26.5.1.1.1.' . $ifIndex, $timeout, $retries);
    $pvidRaw = (string) dcmanage_snmp_get($target, $community, '.1.3.6.1.2.1.17.7.1.4.5.1.1.' . $ifIndex, $timeout, $retries);
    $vlan = dcmanage_snmp_parse_vlan($pvidRaw);
    if ($vlan === '') {
        $vlan = dcmanage_snmp_resolve_vlan_by_ifindex($target, $community, $ifIndex, $timeout, $retries);
    }

    $ifName = dcmanage_normalize_if_name(dcmanage_snmp_parse_typed_value($ifNameRaw));
    $ifDesc = dcmanage_snmp_parse_typed_value($ifDescRaw);
    if ($ifName === '') {
        return ['ok' => false, 'message' => 'Interface not found'];
    }
    if ($vlan === '') {
        $vlan = dcmanage_vlan_fallback_for_interface($ifName, $ifDesc);
    }

    return [
        'ok' => true,
        'message' => 'Port checked',
        'if_name' => $ifName,
        'if_desc' => $ifDesc,
        'vlan' => $vlan,
        'speed_mbps' => dcmanage_snmp_speed_mbps_from_raw($ifHighSpeedRaw, $ifSpeedRaw),
        'speed_mode' => dcmanage_snmp_autoneg_mode_from_raw($autoNegRaw),
        'admin_status' => dcmanage_snmp_status_from_raw($adminRaw, 'admin'),
        'oper_status' => dcmanage_snmp_status_from_raw($operRaw, 'oper'),
    ];
}

function dcmanage_snmp_resolve_vlan_by_ifindex(string $target, string $community, int $ifIndex, int $timeoutMicros, int $retries): string
{
    if ($ifIndex <= 0) {
        return '';
    }

    $directRaw = (string) dcmanage_snmp_get($target, $community, '.1.3.6.1.2.1.17.7.1.4.5.1.1.' . $ifIndex, $timeoutMicros, $retries);
    $direct = dcmanage_snmp_parse_vlan($directRaw);
    if ($direct !== '') {
        return $direct;
    }

    $bridgeIfIndexMap = dcmanage_snmp_walk_to_index_map(dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.17.1.4.1.2', $timeoutMicros, $retries));
    $pvidRawMap = dcmanage_snmp_walk_to_index_map(dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.17.7.1.4.5.1.1', $timeoutMicros, $retries));

    if ($bridgeIfIndexMap === [] || $pvidRawMap === []) {
        return '';
    }

    foreach ($bridgeIfIndexMap as $bridgePort => $rawIfIndex) {
        $mappedIfIndex = (int) preg_replace('/[^0-9]/', '', dcmanage_snmp_parse_typed_value((string) $rawIfIndex));
        if ($mappedIfIndex !== $ifIndex) {
            continue;
        }
        $candidateRaw = (string) ($pvidRawMap[(int) $bridgePort] ?? '');
        $candidate = dcmanage_snmp_parse_vlan($candidateRaw);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function dcmanage_discover_switch_ports(string $host, string $community, int $port = 161): array
{
    $host = trim($host);
    if ($host === '') {
        return ['ok' => false, 'message' => 'Management IP is empty', 'ports' => []];
    }

    $hasRealWalk = function_exists('snmp2_real_walk') || function_exists('snmprealwalk');
    $hasWalkList = function_exists('snmp2_walk') || function_exists('snmpwalk');
    if (!$hasRealWalk && !$hasWalkList) {
        return ['ok' => false, 'message' => 'PHP SNMP functions are not available on this server', 'ports' => []];
    }

    $target = $host . ':' . max(1, $port);
    $community = $community === '' ? 'public' : $community;
    $timeoutMicros = 1000000;
    $retries = 0;

    if (defined('SNMP_OID_OUTPUT_NUMERIC') && function_exists('snmp_set_oid_output_format')) {
        @snmp_set_oid_output_format((int) SNMP_OID_OUTPUT_NUMERIC);
    }
    if (function_exists('snmp_set_quick_print')) {
        @snmp_set_quick_print(false);
    }

    $ports = [];
    $ifNameWalk = dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.31.1.1.1.1', $timeoutMicros, $retries);
    if (count($ifNameWalk) === 0) {
        $ifNameWalk = dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.2.2.1.2', $timeoutMicros, $retries);
    }

    if (count($ifNameWalk) > 0) {
        $ifNameMap = dcmanage_snmp_walk_to_index_map($ifNameWalk);
        $ifDescMap = dcmanage_snmp_walk_to_index_map(dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.31.1.1.1.18', $timeoutMicros, $retries));
        if (count($ifDescMap) === 0) {
            $ifDescMap = dcmanage_snmp_walk_to_index_map(dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.2.2.1.2', $timeoutMicros, $retries));
        }
        $adminMap = dcmanage_snmp_walk_to_index_map(dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.2.2.1.7', $timeoutMicros, $retries));
        $operMap = dcmanage_snmp_walk_to_index_map(dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.2.2.1.8', $timeoutMicros, $retries));
        $ifHighSpeedMap = dcmanage_snmp_walk_to_index_map(dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.31.1.1.1.15', $timeoutMicros, $retries));
        $ifSpeedMap = dcmanage_snmp_walk_to_index_map(dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.2.2.1.5', $timeoutMicros, $retries));
        $autoNegMap = dcmanage_snmp_walk_to_index_map(dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.26.5.1.1.1', $timeoutMicros, $retries));

        // dot1dBasePortIfIndex => maps bridge-port index to ifIndex for reliable VLAN mapping.
        $bridgeIfIndexRaw = dcmanage_snmp_walk_to_index_map(dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.17.1.4.1.2', $timeoutMicros, $retries));
        $bridgeToIfIndex = [];
        foreach ($bridgeIfIndexRaw as $bridgePort => $ifIndexRaw) {
            $ifIndex = (int) preg_replace('/[^0-9]/', '', dcmanage_snmp_parse_typed_value($ifIndexRaw));
            if ($bridgePort > 0 && $ifIndex > 0) {
                $bridgeToIfIndex[(int) $bridgePort] = $ifIndex;
            }
        }

        $pvidRawMap = dcmanage_snmp_walk_to_index_map(dcmanage_snmp_real_walk_any($target, $community, '.1.3.6.1.2.1.17.7.1.4.5.1.1', $timeoutMicros, $retries));
        $pvidByIfIndex = [];
        foreach ($pvidRawMap as $bridgeOrIfIndex => $vlanRaw) {
            $vlan = dcmanage_snmp_parse_vlan($vlanRaw);
            if ($vlan === '') {
                continue;
            }
            if (isset($bridgeToIfIndex[(int) $bridgeOrIfIndex])) {
                $pvidByIfIndex[$bridgeToIfIndex[(int) $bridgeOrIfIndex]] = $vlan;
            } else {
                // Some devices expose ifIndex directly on this OID.
                $pvidByIfIndex[(int) $bridgeOrIfIndex] = $vlan;
            }
        }

        foreach ($ifNameMap as $index => $rawName) {
            if ($index <= 0) {
                continue;
            }

            $ifName = dcmanage_normalize_if_name(dcmanage_snmp_parse_typed_value((string) $rawName));
            if ($ifName === '') {
                continue;
            }

            $adminRaw = isset($adminMap[$index]) ? (string) $adminMap[$index] : '';
            $operRaw = isset($operMap[$index]) ? (string) $operMap[$index] : '';
            $ifDesc = dcmanage_snmp_parse_typed_value((string) ($ifDescMap[$index] ?? ''));
            $vlan = (string) ($pvidByIfIndex[$index] ?? '');
            if ($vlan === '') {
                $vlan = dcmanage_vlan_fallback_for_interface($ifName, $ifDesc);
            }
            $speedMbps = dcmanage_snmp_speed_mbps_from_raw((string) ($ifHighSpeedMap[$index] ?? ''), (string) ($ifSpeedMap[$index] ?? ''));
            $speedMode = dcmanage_snmp_autoneg_mode_from_raw((string) ($autoNegMap[$index] ?? ''));

            $ports[] = [
                'if_name' => $ifName,
                'if_desc' => $ifDesc,
                'if_index' => $index,
                'vlan' => $vlan,
                'speed_mbps' => $speedMbps,
                'speed_mode' => $speedMode,
                'admin_status' => dcmanage_snmp_status_from_raw($adminRaw, 'admin'),
                'oper_status' => dcmanage_snmp_status_from_raw($operRaw, 'oper'),
            ];
        }
    } else {
        $ifNameList = dcmanage_snmp_walk_list_any($target, $community, '.1.3.6.1.2.1.31.1.1.1.1', $timeoutMicros, $retries);
        if (count($ifNameList) === 0) {
            $ifNameList = dcmanage_snmp_walk_list_any($target, $community, '.1.3.6.1.2.1.2.2.1.2', $timeoutMicros, $retries);
        }
        if (count($ifNameList) === 0) {
            return ['ok' => false, 'message' => 'No interfaces received from SNMP walk', 'ports' => []];
        }

        $adminList = dcmanage_snmp_walk_list_any($target, $community, '.1.3.6.1.2.1.2.2.1.7', $timeoutMicros, $retries);
        $operList = dcmanage_snmp_walk_list_any($target, $community, '.1.3.6.1.2.1.2.2.1.8', $timeoutMicros, $retries);
        $ifHighSpeedList = dcmanage_snmp_walk_list_any($target, $community, '.1.3.6.1.2.1.31.1.1.1.15', $timeoutMicros, $retries);
        $ifSpeedList = dcmanage_snmp_walk_list_any($target, $community, '.1.3.6.1.2.1.2.2.1.5', $timeoutMicros, $retries);
        $autoNegList = dcmanage_snmp_walk_list_any($target, $community, '.1.3.6.1.2.1.26.5.1.1.1', $timeoutMicros, $retries);
        $ifDescList = dcmanage_snmp_walk_list_any($target, $community, '.1.3.6.1.2.1.31.1.1.1.18', $timeoutMicros, $retries);
        if (count($ifDescList) === 0) {
            $ifDescList = dcmanage_snmp_walk_list_any($target, $community, '.1.3.6.1.2.1.2.2.1.2', $timeoutMicros, $retries);
        }

        $bridgeIfIndexList = dcmanage_snmp_walk_list_any($target, $community, '.1.3.6.1.2.1.17.1.4.1.2', $timeoutMicros, $retries);
        $pvidList = dcmanage_snmp_walk_list_any($target, $community, '.1.3.6.1.2.1.17.7.1.4.5.1.1', $timeoutMicros, $retries);

        $bridgeToIfIndex = [];
        foreach ($bridgeIfIndexList as $i => $rawIfIndex) {
            $ifIndex = (int) preg_replace('/[^0-9]/', '', dcmanage_snmp_parse_typed_value((string) $rawIfIndex));
            if ($ifIndex > 0) {
                $bridgeToIfIndex[$i + 1] = $ifIndex;
            }
        }

        $pvidByIfIndex = [];
        foreach ($pvidList as $i => $rawPvid) {
            $vlan = dcmanage_snmp_parse_vlan((string) $rawPvid);
            if ($vlan === '') {
                continue;
            }
            $bridgePort = $i + 1;
            if (isset($bridgeToIfIndex[$bridgePort])) {
                $pvidByIfIndex[$bridgeToIfIndex[$bridgePort]] = $vlan;
            } else {
                $pvidByIfIndex[$bridgePort] = $vlan;
            }
        }

        foreach ($ifNameList as $i => $rawName) {
            $ifName = dcmanage_normalize_if_name(dcmanage_snmp_parse_typed_value((string) $rawName));
            if ($ifName === '') {
                continue;
            }

            $adminRaw = isset($adminList[$i]) ? (string) $adminList[$i] : '';
            $operRaw = isset($operList[$i]) ? (string) $operList[$i] : '';
            $ifDesc = dcmanage_snmp_parse_typed_value((string) ($ifDescList[$i] ?? ''));
            $ifIndex = $i + 1;
            $vlan = (string) ($pvidByIfIndex[$ifIndex] ?? '');
            if ($vlan === '') {
                $vlan = dcmanage_vlan_fallback_for_interface($ifName, $ifDesc);
            }
            $speedMbps = dcmanage_snmp_speed_mbps_from_raw((string) ($ifHighSpeedList[$i] ?? ''), (string) ($ifSpeedList[$i] ?? ''));
            $speedMode = dcmanage_snmp_autoneg_mode_from_raw((string) ($autoNegList[$i] ?? ''));
            $ports[] = [
                'if_name' => $ifName,
                'if_desc' => $ifDesc,
                'if_index' => $ifIndex,
                'vlan' => $vlan,
                'speed_mbps' => $speedMbps,
                'speed_mode' => $speedMode,
                'admin_status' => dcmanage_snmp_status_from_raw($adminRaw, 'admin'),
                'oper_status' => dcmanage_snmp_status_from_raw($operRaw, 'oper'),
            ];
        }
    }

    usort($ports, static function (array $a, array $b): int {
        return strnatcasecmp((string) ($a['if_name'] ?? ''), (string) ($b['if_name'] ?? ''));
    });

    return [
        'ok' => true,
        'message' => 'SNMP ports discovered',
        'ports' => $ports,
    ];
}

function dcmanage_store_discovered_switch_ports(int $switchId, array $ports): int
{
    $saved = 0;

    foreach ($ports as $port) {
        if (!is_array($port)) {
            continue;
        }

        $ifName = dcmanage_normalize_if_name((string) ($port['if_name'] ?? ''));
        if ($ifName === '') {
            continue;
        }

        $payload = [
            'switch_id' => $switchId,
            'if_index' => isset($port['if_index']) && (int) $port['if_index'] > 0 ? (int) $port['if_index'] : null,
            'if_name' => $ifName,
            'if_desc' => trim((string) ($port['if_desc'] ?? '')) ?: null,
            'vlan' => '',
            'speed_mbps' => isset($port['speed_mbps']) && (int) $port['speed_mbps'] > 0 ? (int) $port['speed_mbps'] : null,
            'speed_mode' => trim((string) ($port['speed_mode'] ?? '')) ?: null,
            'admin_status' => trim((string) ($port['admin_status'] ?? 'unknown')),
            'oper_status' => trim((string) ($port['oper_status'] ?? 'unknown')),
            'last_seen' => date('Y-m-d H:i:s'),
        ];
        $payload['vlan'] = trim((string) ($port['vlan'] ?? ''));
        if ($payload['vlan'] === '') {
            $payload['vlan'] = dcmanage_vlan_fallback_for_interface(
                $ifName,
                (string) ($payload['if_desc'] ?? '')
            );
        }

        $existing = Capsule::table('mod_dcmanage_switch_ports')
            ->where('switch_id', $switchId)
            ->where('if_name', $ifName)
            ->first(['id']);

        if ($existing !== null) {
            Capsule::table('mod_dcmanage_switch_ports')->where('id', (int) $existing->id)->update($payload);
        } else {
            Capsule::table('mod_dcmanage_switch_ports')->insert($payload);
        }
        $saved++;
    }

    return $saved;
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

function dcmanage_render_switch_status_pill(string $status, string $lang, bool $tested = true): string
{
    if (!$tested) {
        return '<span class="dcmanage-status-pill is-untested">' . htmlspecialchars(I18n::t('switch_status_untested', $lang)) . '</span>';
    }

    $status = strtolower(trim($status));
    if ($status === 'ok') {
        $status = 'up';
    }

    if ($status === 'up') {
        return '<span class="dcmanage-status-pill is-up">' . htmlspecialchars(I18n::t('switch_status_up', $lang)) . '</span>';
    }
    if ($status === 'down' || $status === 'fail') {
        return '<span class="dcmanage-status-pill is-down">' . htmlspecialchars(I18n::t('switch_status_down', $lang)) . '</span>';
    }

    return '<span class="dcmanage-status-pill is-unknown">' . htmlspecialchars(I18n::t('switch_status_unknown', $lang)) . '</span>';
}

function dcmanage_render_port_admin_pill(string $status, string $lang): string
{
    $status = strtolower(trim($status));
    if ($status === 'up') {
        return '<span class="dcmanage-status-pill is-up">' . htmlspecialchars(I18n::t('port_mode_noshut', $lang)) . '</span>';
    }
    if ($status === 'down') {
        return '<span class="dcmanage-status-pill is-down">' . htmlspecialchars(I18n::t('port_mode_shut', $lang)) . '</span>';
    }

    return '<span class="dcmanage-status-pill is-unknown">' . htmlspecialchars(I18n::t('switch_status_unknown', $lang)) . '</span>';
}

function dcmanage_render_port_oper_pill(string $status, string $lang): string
{
    $status = strtolower(trim($status));
    if ($status === 'up') {
        return '<span class="dcmanage-status-pill is-up">' . htmlspecialchars(I18n::t('port_link_connected', $lang)) . '</span>';
    }
    if ($status === 'down') {
        return '<span class="dcmanage-status-pill is-down">' . htmlspecialchars(I18n::t('port_link_not_connected', $lang)) . '</span>';
    }
    if ($status === 'absent') {
        return '<span class="dcmanage-status-pill is-absent">' . htmlspecialchars(I18n::t('port_link_absent', $lang)) . '</span>';
    }

    return '<span class="dcmanage-status-pill is-unknown">' . htmlspecialchars(I18n::t('switch_status_unknown', $lang)) . '</span>';
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

    echo '<div class="d-flex justify-content-end align-items-center mb-3 dcmanage-section-toolbar">';
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
    echo '<button class="btn btn-primary" type="submit" name="dcmanage_action_btn" value="switch_create">' . htmlspecialchars(I18n::t('create_switch', $lang)) . '</button>';
    echo '</form>';
    echo '</div>';

    echo '<div class="table-responsive dcmanage-table-wrap"><table class="table table-sm table-striped dcmanage-dc-table dcmanage-switch-table">';
    echo '<thead><tr><th>ID</th><th>' . htmlspecialchars(I18n::t('switch_name', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('tab_datacenters', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('select_rack', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('switch_vendor', $lang)) . '</th><th>SNMP</th><th>' . htmlspecialchars(I18n::t('label_actions', $lang)) . '</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $snmp = dcmanage_switch_snmp_state((int) $row->id);
        $snmpStatus = !$snmp['tested'] ? 'unknown' : ($snmp['ok'] ? 'up' : 'down');
        echo '<tr>';
        echo '<td>' . (int) $row->id . '</td>';
        echo '<td>' . htmlspecialchars((string) $row->name) . '<br><small class="text-muted">' . htmlspecialchars((string) $row->mgmt_ip) . '</small></td>';
        echo '<td>' . htmlspecialchars((string) $row->dc_name) . '</td>';
        echo '<td>' . htmlspecialchars((string) $row->rack_name) . '</td>';
        echo '<td>' . htmlspecialchars((string) $row->vendor) . ' ' . htmlspecialchars((string) $row->model) . '</td>';
        echo '<td>' . dcmanage_render_switch_status_pill($snmpStatus, $lang, (bool) $snmp['tested']) . '<br><small class="text-muted">' . htmlspecialchars((string) $snmp['message']) . '</small></td>';
        echo '<td><div class="dcmanage-action-buttons">';
        echo '<button class="btn btn-sm dcmanage-btn-soft-info" type="button" data-toggle="collapse" data-target="#sw-ports-' . (int) $row->id . '">Ports</button>';
        echo '<button class="btn btn-sm dcmanage-btn-soft-warning" type="button" data-toggle="collapse" data-target="#sw-edit-' . (int) $row->id . '">' . htmlspecialchars(I18n::t('action_edit', $lang)) . '</button>';
        echo '<form method="post" style="display:inline"><input type="hidden" name="dcmanage_action" value="switch_snmp_test"><input type="hidden" name="switch_id" value="' . (int) $row->id . '"><button class="btn btn-sm dcmanage-btn-soft-success" type="submit" name="dcmanage_action_btn" value="switch_snmp_test">' . htmlspecialchars(I18n::t('switch_snmp_test', $lang)) . '</button></form>';
        echo '<form method="post" style="display:inline"><input type="hidden" name="dcmanage_action" value="switch_ports_discover"><input type="hidden" name="switch_id" value="' . (int) $row->id . '"><button class="btn btn-sm dcmanage-btn-soft-primary" type="submit" name="dcmanage_action_btn" value="switch_ports_discover">' . htmlspecialchars(I18n::t('switch_discover_ports', $lang)) . '</button></form>';
        echo '<form method="post" style="display:inline" onsubmit="return confirm(\'' . htmlspecialchars(I18n::t('delete_confirm_switch', $lang), ENT_QUOTES, 'UTF-8') . '\')"><input type="hidden" name="dcmanage_action" value="switch_delete"><input type="hidden" name="switch_id" value="' . (int) $row->id . '"><button class="btn btn-sm dcmanage-btn-soft-danger" type="submit" name="dcmanage_action_btn" value="switch_delete">' . htmlspecialchars(I18n::t('action_delete', $lang)) . '</button></form>';
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
        echo '<button class="btn btn-primary btn-sm" type="submit" name="dcmanage_action_btn" value="switch_update">' . htmlspecialchars(I18n::t('save_settings', $lang)) . '</button>';
        echo '</form>';
        echo '</td></tr>';

        $ports = Capsule::table('mod_dcmanage_switch_ports')->where('switch_id', (int) $row->id)->orderBy('if_index')->orderBy('if_name')->get();
        echo '<tr class="collapse" id="sw-ports-' . (int) $row->id . '"><td colspan="7">';
        echo '<div class="dcmanage-form-card dcmanage-switch-ports-card">';
        echo '<h6 class="mb-2">' . htmlspecialchars(I18n::t('switch_ports_vlans', $lang)) . '</h6>';
        echo '<div class="form-group mb-2"><input type="text" class="form-control form-control-sm dcmanage-input dcmanage-port-search" data-target-table="dcmanage-port-table-' . (int) $row->id . '" placeholder="' . htmlspecialchars(I18n::t('switch_port_search_placeholder', $lang)) . '"></div>';
        echo '<div class="table-responsive"><table id="dcmanage-port-table-' . (int) $row->id . '" class="table table-sm dcmanage-port-table"><thead><tr><th>' . htmlspecialchars(I18n::t('switch_if_name', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('switch_if_desc', $lang)) . '</th><th>VLAN</th><th>' . htmlspecialchars(I18n::t('switch_if_speed', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('switch_admin_status', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('switch_oper_status', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('label_actions', $lang)) . '</th></tr></thead><tbody>';
        foreach ($ports as $p) {
            $adminStatus = strtolower(trim((string) $p->admin_status));
            $canShut = $adminStatus !== 'down';
            $canNoShut = $adminStatus !== 'up';

            $speedLabel = dcmanage_port_speed_label(isset($p->speed_mbps) ? (int) $p->speed_mbps : null, (string) ($p->speed_mode ?? ''), $lang);
            $searchText = strtolower(trim((string) $p->if_name . ' ' . (string) ($p->if_desc ?? '') . ' ' . (string) ($p->vlan ?? '') . ' ' . (string) ($p->admin_status ?? '') . ' ' . (string) ($p->oper_status ?? '') . ' ' . $speedLabel));
            $ifDesc = (string) ($p->if_desc ?? '');
            echo '<tr data-search="' . htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') . '"><td class="font-weight-bold">' . htmlspecialchars((string) $p->if_name) . '</td><td class="dcmanage-port-desc" title="' . htmlspecialchars($ifDesc, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($ifDesc) . '</td><td>' . htmlspecialchars((string) $p->vlan) . '</td><td>' . htmlspecialchars($speedLabel) . '</td><td>' . dcmanage_render_port_admin_pill((string) $p->admin_status, $lang) . '</td><td>' . dcmanage_render_port_oper_pill((string) $p->oper_status, $lang) . '</td><td class="dcmanage-action-buttons">';
            echo '<form method="post" style="display:inline"><input type="hidden" name="dcmanage_action" value="switch_port_check"><input type="hidden" name="port_id" value="' . (int) $p->id . '"><button class="btn btn-sm dcmanage-btn-soft-info" type="submit" name="dcmanage_action_btn" value="switch_port_check">' . htmlspecialchars(I18n::t('switch_port_check', $lang)) . '</button></form>';
            echo '<form method="post" style="display:inline"><input type="hidden" name="dcmanage_action" value="switch_port_shut"><input type="hidden" name="port_id" value="' . (int) $p->id . '"><button class="btn btn-sm dcmanage-btn-soft-danger" type="submit" name="dcmanage_action_btn" value="switch_port_shut"' . ($canShut ? '' : ' disabled') . '>' . htmlspecialchars(I18n::t('switch_shut', $lang)) . '</button></form>';
            echo '<form method="post" style="display:inline"><input type="hidden" name="dcmanage_action" value="switch_port_noshut"><input type="hidden" name="port_id" value="' . (int) $p->id . '"><button class="btn btn-sm dcmanage-btn-soft-success" type="submit" name="dcmanage_action_btn" value="switch_port_noshut"' . ($canNoShut ? '' : ' disabled') . '>' . htmlspecialchars(I18n::t('switch_no_shut', $lang)) . '</button></form>';
            echo '</td></tr>';
        }
        if (count($ports) === 0) {
            echo '<tr><td colspan="7">-</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="dcmanage-table-pager mt-2" data-target-table="dcmanage-port-table-' . (int) $row->id . '" data-page-size="15">';
        echo '<label class="dcmanage-page-size-wrap mb-0"><span class="dcmanage-page-size-label">' . htmlspecialchars(I18n::t('pagination_per_page', $lang)) . '</span>';
        echo '<select class="form-control form-control-sm dcmanage-input dcmanage-page-size"><option value="10">10</option><option value="15" selected>15</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label>';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary dcmanage-page-prev">' . htmlspecialchars(I18n::t('pagination_prev', $lang)) . '</button>';
        echo '<span class="dcmanage-page-info">1/1</span>';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary dcmanage-page-next">' . htmlspecialchars(I18n::t('pagination_next', $lang)) . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';

    echo '<script>';
    echo '(function(){var dc=document.getElementById("dcmanage-switch-dc");var rack=document.getElementById("dcmanage-switch-rack");';
    echo 'if(dc&&rack){function filter(){var v=dc.value;for(var i=0;i<rack.options.length;i++){var o=rack.options[i];if(!o.value){o.hidden=false;continue;}o.hidden=(v!==""&&o.getAttribute("data-dc-id")!==v);}if(rack.selectedIndex>0&&rack.options[rack.selectedIndex].hidden){rack.selectedIndex=0;}}dc.addEventListener("change",filter);filter();}';
    echo 'function normalizeSearchText(v){var s=String(v||"").toLowerCase();s=s.replace(/[\\u0660-\\u0669]/g,function(ch){return String.fromCharCode(ch.charCodeAt(0)-1632+48);});s=s.replace(/[\\u06f0-\\u06f9]/g,function(ch){return String.fromCharCode(ch.charCodeAt(0)-1776+48);});s=s.replace(/\\u064a/g,"\\u06cc").replace(/\\u0643/g,"\\u06a9").replace(/\\u0629/g,"\\u0647");return s.trim();}';
    echo 'function applyPager(tableId){var table=document.getElementById(tableId);if(!table){return;}var pager=document.querySelector(".dcmanage-table-pager[data-target-table=\'"+tableId+"\']");if(!pager){return;}var sizeSel=pager.querySelector(".dcmanage-page-size");var pageSize=parseInt((sizeSel?sizeSel.value:pager.getAttribute("data-page-size"))||"15",10);if(!pageSize||pageSize<1){pageSize=15;}pager.setAttribute("data-page-size",String(pageSize));if(!pager._page){pager._page=1;}var rows=Array.prototype.slice.call(table.querySelectorAll("tbody tr"));var visible=[];for(var i=0;i<rows.length;i++){if(rows[i].dataset && rows[i].dataset.filtered==="1"){continue;}visible.push(rows[i]);}var pages=Math.max(1,Math.ceil(visible.length/pageSize));if(pager._page>pages){pager._page=pages;}if(pager._page<1){pager._page=1;}for(var x=0;x<rows.length;x++){rows[x].style.display=(rows[x].dataset&&rows[x].dataset.filtered==="1")?"none":"";}var start=(pager._page-1)*pageSize;var end=start+pageSize;for(var y=0;y<visible.length;y++){visible[y].style.display=(y>=start&&y<end)?"":"none";}var info=pager.querySelector(".dcmanage-page-info");if(info){info.textContent=String(pager._page)+"/"+String(pages);}var prev=pager.querySelector(".dcmanage-page-prev");var next=pager.querySelector(".dcmanage-page-next");if(prev){prev.disabled=pager._page<=1;}if(next){next.disabled=pager._page>=pages;}}';
    echo 'var pagers=document.querySelectorAll(".dcmanage-table-pager");for(var p=0;p<pagers.length;p++){(function(pg){var t=pg.getAttribute("data-target-table")||"";var prev=pg.querySelector(".dcmanage-page-prev");var next=pg.querySelector(".dcmanage-page-next");var sizeSel=pg.querySelector(".dcmanage-page-size");if(prev){prev.addEventListener("click",function(){pg._page=(pg._page||1)-1;applyPager(t);});}if(next){next.addEventListener("click",function(){pg._page=(pg._page||1)+1;applyPager(t);});}if(sizeSel){sizeSel.addEventListener("change",function(){pg._page=1;applyPager(t);});}applyPager(t);})(pagers[p]);}';
    echo 'var inputs=document.querySelectorAll(".dcmanage-port-search");for(var s=0;s<inputs.length;s++){inputs[s].addEventListener("input",function(){var q=normalizeSearchText(this.value||"");var tableId=this.getAttribute("data-target-table")||"";if(!tableId){return;}var table=document.getElementById(tableId);if(!table){return;}var rows=table.querySelectorAll("tbody tr");for(var r=0;r<rows.length;r++){var row=rows[r];var hay=normalizeSearchText(row.getAttribute("data-search")||"");row.dataset.filtered=(q!==""&&hay.indexOf(q)===-1)?"1":"0";}var pager=document.querySelector(".dcmanage-table-pager[data-target-table=\'"+tableId+"\']");if(pager){pager._page=1;}applyPager(tableId);});}';
    echo '})();';
    echo '</script>';
}

function dcmanage_render_servers(string $lang): void
{
    $dcs = Capsule::table('mod_dcmanage_datacenters')->orderBy('name')->get(['id', 'name']);
    $racks = Capsule::table('mod_dcmanage_racks')->orderBy('name')->get(['id', 'dc_id', 'name']);
    $switches = Capsule::table('mod_dcmanage_switches')->orderBy('name')->get(['id', 'dc_id', 'name']);
    $prtgInstances = Capsule::table('mod_dcmanage_prtg_instances')->orderBy('name')->get(['id', 'name']);

    $rows = Capsule::table('mod_dcmanage_servers as s')
        ->leftJoin('mod_dcmanage_datacenters as d', 'd.id', '=', 's.dc_id')
        ->leftJoin('mod_dcmanage_racks as r', 'r.id', '=', 's.rack_id')
        ->leftJoin('mod_dcmanage_ilos as il', 'il.id', '=', 's.ilo_id')
        ->orderBy('s.id', 'desc')
        ->get([
            's.id', 's.dc_id', 's.hostname', 's.asset_tag', 's.serial', 's.u_start', 's.u_height', 's.ilo_id',
            'd.name as dc_name', 'r.name as rack_name',
            'il.host as ilo_host', 'il.user as ilo_user', 'il.type as ilo_type'
        ]);

    $serverIds = [];
    foreach ($rows as $row) {
        $serverIds[] = (int) $row->id;
    }

    $serverPortMap = [];
    $serverLinkDefaults = [];
    $sensorCountMap = [];
    $sensorPreviewMap = [];
    $serverSensorDefaults = [];
    $serverSensorPrtgMap = [];

    if ($serverIds !== []) {
        $linkedPorts = Capsule::table('mod_dcmanage_server_ports as sp')
            ->leftJoin('mod_dcmanage_switches as sw', 'sw.id', '=', 'sp.switch_id')
            ->leftJoin('mod_dcmanage_switch_ports as swp', function ($join): void {
                $join->on('swp.switch_id', '=', 'sp.switch_id');
                $join->on('swp.if_name', '=', 'sp.switch_if');
            })
            ->whereIn('sp.server_id', $serverIds)
            ->orderBy('sp.server_id')
            ->orderBy('sp.port_no')
            ->get(['sp.server_id', 'sp.switch_id', 'sp.switch_if', 'sw.name as switch_name', 'swp.id as switch_port_id', 'swp.if_desc', 'swp.oper_status']);
        foreach ($linkedPorts as $port) {
            $serverId = (int) $port->server_id;
            if (!isset($serverPortMap[$serverId])) {
                $serverPortMap[$serverId] = [];
            }
            $portLabel = (string) ($port->switch_if ?? '-');
            if (trim((string) ($port->if_desc ?? '')) !== '') {
                $portLabel .= ' | ' . (string) $port->if_desc;
            }
            $linkStatus = strtolower(trim((string) ($port->oper_status ?? 'unknown')));
            if ($linkStatus === 'up') {
                $portLabel .= ' | ' . I18n::t('port_link_connected', $lang);
            } elseif ($linkStatus === 'down') {
                $portLabel .= ' | ' . I18n::t('port_link_not_connected', $lang);
            } elseif ($linkStatus === 'absent') {
                $portLabel .= ' | ' . I18n::t('port_link_absent', $lang);
            }
            $serverPortMap[$serverId][] = trim((string) ($port->switch_name ?? '-') . ' / ' . $portLabel);

            if (!isset($serverLinkDefaults[$serverId])) {
                $serverLinkDefaults[$serverId] = [
                    'switch_id' => (int) ($port->switch_id ?? 0),
                    'switch_port_id' => (int) ($port->switch_port_id ?? 0),
                ];
            }
        }

        if (Capsule::schema()->hasTable('mod_dcmanage_server_traffic_sensors')) {
            $sensorRows = Capsule::table('mod_dcmanage_server_traffic_sensors')
                ->whereIn('server_id', $serverIds)
                ->orderBy('id')
                ->get(['server_id', 'prtg_id', 'sensor_id']);
            foreach ($sensorRows as $sensorRow) {
                $serverId = (int) $sensorRow->server_id;
                if (!isset($sensorCountMap[$serverId])) {
                    $sensorCountMap[$serverId] = 0;
                }
                $sensorCountMap[$serverId]++;

                if (!isset($sensorPreviewMap[$serverId])) {
                    $sensorPreviewMap[$serverId] = [];
                }
                if (count($sensorPreviewMap[$serverId]) < 3) {
                    $sensorPreviewMap[$serverId][] = (string) $sensorRow->sensor_id;
                }
                if (!isset($serverSensorDefaults[$serverId])) {
                    $serverSensorDefaults[$serverId] = [];
                }
                $serverSensorDefaults[$serverId][] = (string) $sensorRow->sensor_id;
                if (!isset($serverSensorPrtgMap[$serverId]) && (int) ($sensorRow->prtg_id ?? 0) > 0) {
                    $serverSensorPrtgMap[$serverId] = (int) $sensorRow->prtg_id;
                }
            }
        }
    }

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

    echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('select_rack', $lang)) . '</label><select name="rack_id" id="dcmanage-server-rack" class="form-control dcmanage-input" disabled>';
    echo '<option value="">--</option>';
    foreach ($racks as $rack) {
        echo '<option data-dc-id="' . (int) $rack->dc_id . '" value="' . (int) $rack->id . '">' . htmlspecialchars((string) $rack->name) . '</option>';
    }
    echo '</select></div>';

    echo '<div class="form-row">';
    echo '<div class="form-group col-md-6"><label>' . htmlspecialchars(I18n::t('select_switch', $lang)) . '</label><select name="switch_id" id="dcmanage-server-switch" class="form-control dcmanage-input" disabled>';
    echo '<option value="">' . htmlspecialchars(I18n::t('select_switch', $lang)) . '</option>';
    foreach ($switches as $switch) {
        echo '<option data-dc-id="' . (int) $switch->dc_id . '" value="' . (int) $switch->id . '">' . htmlspecialchars((string) $switch->name) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group col-md-6"><label>' . htmlspecialchars(I18n::t('select_switch_port', $lang)) . '</label><select name="switch_port_id" id="dcmanage-server-switch-port" class="form-control dcmanage-input" disabled>';
    echo '<option value="">' . htmlspecialchars(I18n::t('select_switch_port', $lang)) . '</option>';
    echo '</select></div>';
    echo '</div>';

    echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('server_hostname', $lang)) . '</label><input required name="hostname" class="form-control dcmanage-input"></div>';
    echo '<div class="form-row">';
    echo '<div class="form-group col-md-6"><label>Asset Tag</label><input name="asset_tag" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-6"><label>Serial</label><input name="serial" class="form-control dcmanage-input"></div>';
    echo '</div>';
    echo '<div class="form-row">';
    echo '<div class="form-group col-md-6"><label>U Start</label><input type="number" min="0" name="u_start" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-6"><label>U Height</label><input type="number" min="1" name="u_height" value="1" class="form-control dcmanage-input"></div>';
    echo '</div>';
    echo '<div class="form-row">';
    echo '<div class="form-group col-md-5"><label>' . htmlspecialchars(I18n::t('server_ilo_host', $lang)) . '</label><input name="ilo_host" class="form-control dcmanage-input" placeholder="192.0.2.10"></div>';
    echo '<div class="form-group col-md-3"><label>' . htmlspecialchars(I18n::t('server_ilo_user', $lang)) . '</label><input name="ilo_user" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-2"><label>' . htmlspecialchars(I18n::t('server_ilo_type', $lang)) . '</label><select name="ilo_type" class="form-control dcmanage-input"><option value="ilo5">iLO5</option><option value="ilo4">iLO4</option></select></div>';
    echo '<div class="form-group col-md-2"><label>' . htmlspecialchars(I18n::t('server_ilo_pass', $lang)) . '</label><input type="password" name="ilo_pass" class="form-control dcmanage-input"></div>';
    echo '</div>';
    echo '<div class="form-group"><label>Notes</label><textarea name="notes" class="form-control dcmanage-input" rows="3"></textarea></div>';
    echo '<button class="btn btn-primary" type="submit">' . htmlspecialchars(I18n::t('create_server', $lang)) . '</button>';
    echo '</form>';

    echo '<h5 class="mt-4">' . htmlspecialchars(I18n::t('server_bulk_add', $lang)) . '</h5>';
    echo '<form method="post" action="" class="dcmanage-form-card">';
    echo '<input type="hidden" name="dcmanage_action" value="server_create_bulk">';
    echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('select_datacenter', $lang)) . '</label><select name="bulk_dc_id" id="dcmanage-server-bulk-dc" required class="form-control dcmanage-input">';
    echo '<option value="">--</option>';
    foreach ($dcs as $dc) {
        echo '<option value="' . (int) $dc->id . '">' . htmlspecialchars((string) $dc->name) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-row">';
    echo '<div class="form-group col-md-6"><label>' . htmlspecialchars(I18n::t('server_range_start', $lang)) . '</label><input required name="bulk_hostname_start" class="form-control dcmanage-input" placeholder="MDP-301"></div>';
    echo '<div class="form-group col-md-6"><label>' . htmlspecialchars(I18n::t('server_range_end', $lang)) . '</label><input required name="bulk_hostname_end" class="form-control dcmanage-input" placeholder="MDP-399"></div>';
    echo '</div>';
    echo '<div class="form-row">';
    echo '<div class="form-group col-md-4"><label>' . htmlspecialchars(I18n::t('server_bulk_u_height', $lang)) . '</label><input type="number" min="1" name="bulk_u_height" value="1" class="form-control dcmanage-input"></div>';
    echo '<div class="form-group col-md-8"><label>' . htmlspecialchars(I18n::t('server_bulk_notes', $lang)) . '</label><input name="bulk_notes" class="form-control dcmanage-input"></div>';
    echo '</div>';
    echo '<small class="form-text text-muted dcmanage-bulk-hint">' . htmlspecialchars(I18n::t('server_bulk_hint', $lang)) . '</small>';
    echo '<div class="dcmanage-bulk-submit"><button class="btn btn-outline-primary" type="submit">' . htmlspecialchars(I18n::t('create_servers_bulk', $lang)) . '</button></div>';
    echo '</form>';
    echo '</div>';

    echo '<div class="col-lg-7">';
    echo '<div class="form-group mb-2"><input type="text" id="dcmanage-server-table-search" class="form-control form-control-sm dcmanage-input" placeholder="' . htmlspecialchars(I18n::t('table_search', $lang)) . ': ID / Hostname / DC / Rack / iLO / Port / Sensor / U"></div>';
    echo '<div class="table-responsive dcmanage-table-wrap"><table id="dcmanage-server-table" class="table table-sm table-striped">';
    echo '<thead><tr><th>ID</th><th>' . htmlspecialchars(I18n::t('server_hostname', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('tab_datacenters', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('select_rack', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('server_ilo', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('table_switch_port', $lang)) . '</th><th>' . htmlspecialchars(I18n::t('table_sensor_count', $lang)) . '</th><th>U</th><th>' . htmlspecialchars(I18n::t('label_actions', $lang)) . '</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $serverId = (int) $row->id;
        $u = ($row->u_start !== null ? (string) $row->u_start : '-') . '/' . (string) $row->u_height;
        $ports = isset($serverPortMap[$serverId]) ? array_slice($serverPortMap[$serverId], 0, 2) : [];
        $portLabel = $ports === [] ? '-' : implode('<br>', array_map(static function (string $item): string {
            return htmlspecialchars($item);
        }, $ports));

        $sensorCount = (int) ($sensorCountMap[$serverId] ?? 0);
        $sensorPreview = isset($sensorPreviewMap[$serverId]) ? implode(',', $sensorPreviewMap[$serverId]) : '';
        $sensorLabel = $sensorCount > 0
            ? htmlspecialchars((string) $sensorCount . ' [' . $sensorPreview . ($sensorCount > 3 ? ',…' : '') . ']')
            : '-';
        $linkDefaults = $serverLinkDefaults[$serverId] ?? ['switch_id' => 0, 'switch_port_id' => 0];
        $sensorDefaults = $serverSensorDefaults[$serverId] ?? [];
        $sensorDefaultCsv = implode(',', $sensorDefaults);
        $sensorPrtgId = (int) ($serverSensorPrtgMap[$serverId] ?? 0);
        $mapFormId = 'dcmanage-server-map-' . $serverId;

        $iloLabel = trim((string) ($row->ilo_host ?? '')) !== ''
            ? htmlspecialchars((string) $row->ilo_host) . '<br><small class="text-muted">' . htmlspecialchars((string) ($row->ilo_type ?? 'iLO')) . '</small>'
            : '-';
        $searchText = strtolower(trim((string) $serverId . ' ' . (string) $row->hostname . ' ' . (string) $row->dc_name . ' ' . (string) $row->rack_name . ' ' . strip_tags($iloLabel) . ' ' . strip_tags($portLabel) . ' ' . strip_tags($sensorLabel) . ' ' . $u));
        echo '<tr class="dcmanage-server-item" data-server-id="' . $serverId . '" data-search="' . htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') . '"><td>' . $serverId . '</td><td>' . htmlspecialchars((string) $row->hostname) . '</td><td>' . htmlspecialchars((string) $row->dc_name) . '</td><td>' . htmlspecialchars((string) $row->rack_name) . '</td><td>' . $iloLabel . '</td><td>' . $portLabel . '</td><td>' . $sensorLabel . '</td><td>' . htmlspecialchars($u) . '</td><td class="dcmanage-action-buttons"><button type="button" class="btn btn-sm dcmanage-btn-soft-primary dcmanage-server-map-toggle" data-target="' . htmlspecialchars($mapFormId) . '">' . htmlspecialchars(I18n::t('action_edit', $lang)) . '</button><form method="post" style="display:inline" onsubmit="return confirm(\'Delete server?\')"><input type="hidden" name="dcmanage_action" value="server_delete"><input type="hidden" name="server_id" value="' . $serverId . '"><button class="btn btn-sm dcmanage-btn-soft-danger" type="submit" name="dcmanage_action_btn" value="server_delete">' . htmlspecialchars(I18n::t('action_delete', $lang)) . '</button></form></td></tr>';
        echo '<tr id="' . htmlspecialchars($mapFormId) . '" class="dcmanage-server-map-row" data-server-id="' . $serverId . '" style="display:none;"><td colspan="9">';
        echo '<form method="post" class="dcmanage-form-card dcmanage-server-map" data-dc-id="' . (int) $row->dc_id . '">';
        echo '<input type="hidden" name="dcmanage_action" value="server_link_update">';
        echo '<input type="hidden" name="server_id" value="' . $serverId . '">';
        echo '<div class="form-row">';
        echo '<div class="form-group col-md-6"><label>' . htmlspecialchars(I18n::t('select_switch', $lang)) . '</label><select name="switch_id" class="form-control dcmanage-input dcmanage-map-switch">';
        echo '<option value="">' . htmlspecialchars(I18n::t('select_switch', $lang)) . '</option>';
        foreach ($switches as $switch) {
            $selected = (int) $switch->id === (int) $linkDefaults['switch_id'] ? ' selected' : '';
            echo '<option data-dc-id="' . (int) $switch->dc_id . '" value="' . (int) $switch->id . '"' . $selected . '>' . htmlspecialchars((string) $switch->name) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="form-group col-md-6"><label>' . htmlspecialchars(I18n::t('select_switch_port', $lang)) . '</label><select name="switch_port_id" class="form-control dcmanage-input dcmanage-map-port" data-selected="' . (int) $linkDefaults['switch_port_id'] . '"><option value="">' . htmlspecialchars(I18n::t('select_switch_port', $lang)) . '</option></select></div>';
        echo '</div>';
        echo '<div class="form-row">';
        echo '<div class="form-group col-md-6"><label>' . htmlspecialchars(I18n::t('select_prtg_instance', $lang)) . '</label><select name="prtg_id" class="form-control dcmanage-input dcmanage-map-prtg">';
        echo '<option value="">' . htmlspecialchars(I18n::t('select_prtg_instance', $lang)) . '</option>';
        foreach ($prtgInstances as $instance) {
            $selected = (int) $instance->id === $sensorPrtgId ? ' selected' : '';
            echo '<option value="' . (int) $instance->id . '"' . $selected . '>' . htmlspecialchars((string) $instance->name) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="form-group col-md-6"><label>' . htmlspecialchars(I18n::t('server_sensor_search', $lang)) . '</label><input type="text" class="form-control dcmanage-input dcmanage-map-sensor-search" placeholder="sensor name / id"></div>';
        echo '</div>';
        echo '<div class="form-row">';
        echo '<div class="form-group col-md-5"><label>' . htmlspecialchars(I18n::t('server_ilo_host', $lang)) . '</label><input name="ilo_host" class="form-control dcmanage-input" value="' . htmlspecialchars((string) ($row->ilo_host ?? '')) . '"></div>';
        echo '<div class="form-group col-md-3"><label>' . htmlspecialchars(I18n::t('server_ilo_user', $lang)) . '</label><input name="ilo_user" class="form-control dcmanage-input" value="' . htmlspecialchars((string) ($row->ilo_user ?? '')) . '"></div>';
        echo '<div class="form-group col-md-2"><label>' . htmlspecialchars(I18n::t('server_ilo_type', $lang)) . '</label><select name="ilo_type" class="form-control dcmanage-input"><option value="ilo5"' . ((string) ($row->ilo_type ?? 'ilo5') === 'ilo5' ? ' selected' : '') . '>iLO5</option><option value="ilo4"' . ((string) ($row->ilo_type ?? '') === 'ilo4' ? ' selected' : '') . '>iLO4</option></select></div>';
        echo '<div class="form-group col-md-2"><label>' . htmlspecialchars(I18n::t('server_ilo_pass', $lang)) . '</label><input type="password" name="ilo_pass" class="form-control dcmanage-input" placeholder="••••••"></div>';
        echo '</div>';
        echo '<div class="form-group"><button type="button" class="btn btn-sm dcmanage-btn-soft-primary dcmanage-map-load-sensors">' . htmlspecialchars(I18n::t('server_sensors_load', $lang)) . '</button></div>';
        echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('server_traffic_sensors', $lang)) . '</label><select multiple name="prtg_sensor_ids[]" class="form-control dcmanage-input dcmanage-map-sensor-select" size="6">';
        foreach ($sensorDefaults as $sensorId) {
            echo '<option selected value="' . htmlspecialchars($sensorId) . '">' . htmlspecialchars($sensorId) . '</option>';
        }
        echo '</select><input type="hidden" class="dcmanage-map-default-sensors" value="' . htmlspecialchars($sensorDefaultCsv) . '"><small class="form-text text-muted dcmanage-map-sensor-status">' . htmlspecialchars(I18n::t('server_sensor_status', $lang)) . '</small></div>';
        echo '<div class="form-group"><label>' . htmlspecialchars(I18n::t('server_sensor_manual', $lang)) . '</label><input name="prtg_sensor_ids_manual" class="form-control dcmanage-input" placeholder="12345,12346" value="' . htmlspecialchars($sensorDefaultCsv) . '"></div>';
        echo '<button class="btn btn-primary" type="submit">' . htmlspecialchars(I18n::t('save_settings', $lang)) . '</button>';
        echo '</form>';
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
    echo '<div class="dcmanage-table-pager mt-2" id="dcmanage-server-table-pager" data-target-table="dcmanage-server-table" data-page-size="15">';
    echo '<label class="dcmanage-page-size-wrap mb-0"><span class="dcmanage-page-size-label">' . htmlspecialchars(I18n::t('pagination_per_page', $lang)) . '</span>';
    echo '<select class="form-control form-control-sm dcmanage-input dcmanage-page-size"><option value="10">10</option><option value="15" selected>15</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label>';
    echo '<button type="button" class="btn btn-sm btn-outline-secondary dcmanage-page-prev">' . htmlspecialchars(I18n::t('pagination_prev', $lang)) . '</button>';
    echo '<span class="dcmanage-page-info">1/1</span>';
    echo '<button type="button" class="btn btn-sm btn-outline-secondary dcmanage-page-next">' . htmlspecialchars(I18n::t('pagination_next', $lang)) . '</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<script>';
    echo '(function(){';
    echo 'var dc=document.getElementById("dcmanage-server-dc");var rack=document.getElementById("dcmanage-server-rack");var sw=document.getElementById("dcmanage-server-switch");var swp=document.getElementById("dcmanage-server-switch-port");';
    echo 'if(!dc||!rack||!sw||!swp){return;}';
    echo 'function filterByDc(select,v){for(var i=0;i<select.options.length;i++){var o=select.options[i];if(!o.value){o.hidden=false;continue;}var d=o.getAttribute("data-dc-id");if(v===""){o.hidden=true;}else{o.hidden=(d!==null&&d!==v);}}if(select.selectedIndex>0&&select.options[select.selectedIndex].hidden){select.selectedIndex=0;}}';
    echo 'function parsePayload(raw){raw=String(raw||"").replace(/^\\uFEFF/,"").trim();try{return JSON.parse(raw);}catch(e){var s=raw.indexOf("DCMANAGE_JSON_START");var t=raw.indexOf("DCMANAGE_JSON_END");if(s!==-1&&t!==-1&&t>s){return JSON.parse(raw.substring(s+"DCMANAGE_JSON_START".length,t).trim());}throw e;}}';
    echo 'function normalizeSearchText(v){var s=String(v||"").toLowerCase();s=s.replace(/[\\u0660-\\u0669]/g,function(ch){return String.fromCharCode(ch.charCodeAt(0)-1632+48);});s=s.replace(/[\\u06f0-\\u06f9]/g,function(ch){return String.fromCharCode(ch.charCodeAt(0)-1776+48);});s=s.replace(/\\u064a/g,"\\u06cc").replace(/\\u0643/g,"\\u06a9").replace(/\\u0629/g,"\\u0647");return s.trim();}';
    echo 'function apiUrl(endpoint,params){var u="addonmodules.php?module=dcmanage&dcmanage_api=1&endpoint="+encodeURIComponent(endpoint);if(params){for(var k in params){if(Object.prototype.hasOwnProperty.call(params,k)){u+="&"+encodeURIComponent(k)+"="+encodeURIComponent(params[k]);}}}return u;}';
    echo 'function portOperLabel(status){var s=String(status||"").toLowerCase();if(s==="up"){return "' . addslashes(I18n::t('port_link_connected', $lang)) . '";}if(s==="down"){return "' . addslashes(I18n::t('port_link_not_connected', $lang)) . '";}if(s==="absent"){return "' . addslashes(I18n::t('port_link_absent', $lang)) . '";}return "' . addslashes(I18n::t('switch_status_unknown', $lang)) . '";}';
    echo 'function clearSwitchPorts(selectEl){if(!selectEl){return;}selectEl.innerHTML="";var first=document.createElement("option");first.value="";first.textContent="' . addslashes(I18n::t('select_switch_port', $lang)) . '";selectEl.appendChild(first);selectEl.disabled=true;}';
    echo 'function loadSwitchPorts(selectEl,switchId,dcId,selectedId){clearSwitchPorts(selectEl);if(!selectEl||!switchId||!dcId){return;}fetch(apiUrl("switch/ports",{switch_id:switchId,dc_id:dcId}),{credentials:"same-origin"}).then(function(r){return r.text();}).then(function(raw){var res=parsePayload(raw);if(!res.ok){throw new Error(res.error||"API error");}var items=(res.data&&res.data.items)?res.data.items:[];for(var i=0;i<items.length;i++){var it=items[i]||{};var opt=document.createElement("option");opt.value=String(it.id||"");var label=String(it.if_name||"-");if(String(it.if_desc||"").trim()!==""){label+=" | "+String(it.if_desc);}label+=" | "+portOperLabel(it.oper_status||"");opt.textContent=label;if(String(selectedId||"")!==""&&String(selectedId)===String(opt.value)){opt.selected=true;}selectEl.appendChild(opt);}selectEl.disabled=false;}).catch(function(){clearSwitchPorts(selectEl);});}';
    echo 'function syncDcState(){var hasDc=dc.value!=="";rack.disabled=!hasDc;sw.disabled=!hasDc;if(!hasDc){rack.value="";sw.value="";}filterByDc(rack,dc.value);filterByDc(sw,dc.value);clearSwitchPorts(swp);if(hasDc&&sw.value!==""){loadSwitchPorts(swp,sw.value,dc.value,"");}}';
    echo 'dc.addEventListener("change",syncDcState);';
    echo 'sw.addEventListener("change",function(){loadSwitchPorts(swp,sw.value,dc.value,"");});';
    echo 'function applyServerPager(){var table=document.getElementById("dcmanage-server-table");var pager=document.getElementById("dcmanage-server-table-pager");if(!table||!pager){return;}var sizeSel=pager.querySelector(".dcmanage-page-size");var pageSize=parseInt((sizeSel?sizeSel.value:pager.getAttribute("data-page-size"))||"15",10);if(!pageSize||pageSize<1){pageSize=15;}pager.setAttribute("data-page-size",String(pageSize));if(!pager._page){pager._page=1;}var rows=Array.prototype.slice.call(table.querySelectorAll("tbody tr.dcmanage-server-item"));var visible=[];for(var i=0;i<rows.length;i++){if(rows[i].dataset&&rows[i].dataset.filtered==="1"){continue;}visible.push(rows[i]);}var pages=Math.max(1,Math.ceil(visible.length/pageSize));if(pager._page>pages){pager._page=pages;}if(pager._page<1){pager._page=1;}for(var r=0;r<rows.length;r++){var row=rows[r];var sid=row.getAttribute("data-server-id")||"";var detail=document.querySelector("tr.dcmanage-server-map-row[data-server-id=\'"+sid+"\']");if(row.dataset&&row.dataset.filtered==="1"){row.style.display="none";if(detail){detail.style.display="none";}}else{row.style.display="";}}var start=(pager._page-1)*pageSize;var end=start+pageSize;for(var x=0;x<visible.length;x++){var mainRow=visible[x];var sid2=mainRow.getAttribute("data-server-id")||"";var detailRow=document.querySelector("tr.dcmanage-server-map-row[data-server-id=\'"+sid2+"\']");var show=(x>=start&&x<end);mainRow.style.display=show?\"\":\"none\";if(!show&&detailRow){detailRow.style.display=\"none\";}}var info=pager.querySelector(".dcmanage-page-info");if(info){info.textContent=String(pager._page)+\"/\"+String(pages);}var prev=pager.querySelector(\".dcmanage-page-prev\");var next=pager.querySelector(\".dcmanage-page-next\");if(prev){prev.disabled=pager._page<=1;}if(next){next.disabled=pager._page>=pages;}}';
    echo 'var serverSearch=document.getElementById("dcmanage-server-table-search");if(serverSearch){serverSearch.addEventListener("input",function(){var q=normalizeSearchText(this.value||"");var rows=document.querySelectorAll("#dcmanage-server-table tbody tr.dcmanage-server-item");for(var i=0;i<rows.length;i++){var hay=normalizeSearchText(rows[i].getAttribute("data-search")||"");rows[i].dataset.filtered=(q!==""&&hay.indexOf(q)===-1)?"1":"0";}var pager=document.getElementById("dcmanage-server-table-pager");if(pager){pager._page=1;}applyServerPager();});}';
    echo 'var serverPager=document.getElementById("dcmanage-server-table-pager");if(serverPager){var p=serverPager.querySelector(".dcmanage-page-prev");var n=serverPager.querySelector(".dcmanage-page-next");var sz=serverPager.querySelector(".dcmanage-page-size");if(p){p.addEventListener("click",function(){serverPager._page=(serverPager._page||1)-1;applyServerPager();});}if(n){n.addEventListener("click",function(){serverPager._page=(serverPager._page||1)+1;applyServerPager();});}if(sz){sz.addEventListener("change",function(){serverPager._page=1;applyServerPager();});}}';
    echo 'var mapRows=document.querySelectorAll(".dcmanage-server-map");';
    echo 'for(var m=0;m<mapRows.length;m++){(function(form){';
    echo 'var dcId=form.getAttribute("data-dc-id")||"";var swSel=form.querySelector(".dcmanage-map-switch");var portSel=form.querySelector(".dcmanage-map-port");var prtgSel=form.querySelector(".dcmanage-map-prtg");var sensorSearch=form.querySelector(".dcmanage-map-sensor-search");var loadBtn=form.querySelector(".dcmanage-map-load-sensors");var sensorSel=form.querySelector(".dcmanage-map-sensor-select");var sensorStatus=form.querySelector(".dcmanage-map-sensor-status");var sensorDefault=form.querySelector(".dcmanage-map-default-sensors");';
    echo 'function setStatus(msg){if(sensorStatus){sensorStatus.textContent=msg;}}';
    echo 'function filterSwitches(){if(!swSel){return;}for(var i=0;i<swSel.options.length;i++){var o=swSel.options[i];if(!o.value){o.hidden=false;continue;}var d=o.getAttribute("data-dc-id");o.hidden=(dcId!==""&&d!==dcId);}if(swSel.selectedIndex>0&&swSel.options[swSel.selectedIndex].hidden){swSel.selectedIndex=0;}}';
    echo 'function loadSensors(){if(!prtgSel||!sensorSel){return;}var prtgId=prtgSel.value;if(!prtgId){sensorSel.innerHTML="";setStatus("' . addslashes(I18n::t('no_sensors_loaded', $lang)) . '");return;}';
    echo 'setStatus("' . addslashes(I18n::t('loading', $lang)) . '");';
    echo 'fetch(apiUrl("prtg/sensors",{prtg_id:prtgId,q:(sensorSearch?sensorSearch.value:""),limit:250}),{credentials:"same-origin"}).then(function(r){return r.text();}).then(function(raw){var res=parsePayload(raw);if(!res.ok){throw new Error(res.error||"API error");}var items=(res.data&&res.data.items)?res.data.items:[];var defaults=String(sensorDefault?sensorDefault.value:"").split(",").map(function(v){return v.trim();}).filter(function(v){return v!=="";});sensorSel.innerHTML="";for(var x=0;x<items.length;x++){var it=items[x]||{};var opt=document.createElement("option");opt.value=String(it.id||"");var label=String(it.id||"")+" | "+String(it.name||"");if(it.device){label+=" ["+String(it.device)+"]";}opt.textContent=label;if(defaults.indexOf(opt.value)!==-1){opt.selected=true;}sensorSel.appendChild(opt);}setStatus(items.length+" ' . addslashes(I18n::t('server_traffic_sensors', $lang)) . '");}).catch(function(){setStatus("' . addslashes(I18n::t('no_sensors_loaded', $lang)) . '");});}';
    echo 'filterSwitches();if(portSel){loadSwitchPorts(portSel,swSel?swSel.value:"",dcId,portSel.getAttribute("data-selected")||"");}if(swSel){swSel.addEventListener("change",function(){loadSwitchPorts(portSel,swSel.value,dcId,"");});}if(loadBtn){loadBtn.addEventListener("click",loadSensors);}if(prtgSel){prtgSel.addEventListener("change",loadSensors);}})(mapRows[m]);}';
    echo 'var toggles=document.querySelectorAll(".dcmanage-server-map-toggle");for(var t=0;t<toggles.length;t++){toggles[t].addEventListener("click",function(){var target=document.getElementById(this.getAttribute("data-target"));if(!target){return;}var main=target.previousElementSibling;if(main&&main.style.display==="none"){return;}target.style.display=(target.style.display==="none"||target.style.display==="")?"table-row":"none";});}';
    echo 'syncDcState();';
    echo 'applyServerPager();';
    echo '})();';
    echo '</script>';
}

function dcmanage_render_simple_pagination(string $baseUrl, int $page, int $perPage, int $total, string $lang, string $pageParam): string
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    if ($pages <= 1) {
        return '';
    }

    $page = max(1, min($page, $pages));
    $prevPage = max(1, $page - 1);
    $nextPage = min($pages, $page + 1);

    $html = '<div class="dcmanage-table-pager mt-2">';
    $html .= '<a class="btn btn-sm btn-outline-secondary' . ($page <= 1 ? ' disabled' : '') . '" href="' . htmlspecialchars($baseUrl . '&' . $pageParam . '=' . $prevPage) . '">' . htmlspecialchars(I18n::t('pagination_prev', $lang)) . '</a>';
    $html .= '<span class="dcmanage-page-info">' . $page . '/' . $pages . '</span>';
    $html .= '<a class="btn btn-sm btn-outline-secondary' . ($page >= $pages ? ' disabled' : '') . '" href="' . htmlspecialchars($baseUrl . '&' . $pageParam . '=' . $nextPage) . '">' . htmlspecialchars(I18n::t('pagination_next', $lang)) . '</a>';
    $html .= '</div>';

    return $html;
}

function dcmanage_render_logs(string $lang): void
{
    $q = trim((string) ($_GET['log_q'] ?? ''));
    $level = strtolower(trim((string) ($_GET['log_level'] ?? '')));
    $source = trim((string) ($_GET['log_source'] ?? ''));
    $sort = strtolower(trim((string) ($_GET['log_sort'] ?? 'id_desc')));

    $purchasePage = max(1, (int) ($_GET['purchase_page'] ?? 1));
    $logPage = max(1, (int) ($_GET['log_page'] ?? 1));
    $perPagePurchase = 25;
    $perPageLogs = 50;

    $purchaseQuery = Capsule::table('mod_dcmanage_purchases as p')
        ->leftJoin('mod_dcmanage_packages as pk', 'pk.id', '=', 'p.package_id');
    $purchaseTotal = (int) $purchaseQuery->count();
    $purchaseRows = $purchaseQuery
        ->orderBy('p.id', 'desc')
        ->offset(($purchasePage - 1) * $perPagePurchase)
        ->limit($perPagePurchase)
        ->get(['p.id', 'p.whmcs_serviceid', 'p.userid', 'p.size_gb', 'p.price', 'p.invoiceid', 'p.created_at', 'pk.name as package_name']);

    $query = Capsule::table('mod_dcmanage_logs');
    if ($q !== '') {
        $query->where(static function ($w) use ($q): void {
            $w->where('message', 'like', '%' . $q . '%')
                ->orWhere('source', 'like', '%' . $q . '%')
                ->orWhere('level', 'like', '%' . $q . '%');
        });
    }
    if (in_array($level, ['info', 'warning', 'error'], true)) {
        $query->where('level', $level);
    }
    if ($source !== '') {
        $query->where('source', $source);
    }

    if ($sort === 'level_asc') {
        $query->orderByRaw("FIELD(level,'error','warning','info') ASC")->orderBy('id', 'desc');
    } elseif ($sort === 'level_desc') {
        $query->orderByRaw("FIELD(level,'error','warning','info') DESC")->orderBy('id', 'desc');
    } elseif ($sort === 'date_asc') {
        $query->orderBy('created_at', 'asc');
    } elseif ($sort === 'id_asc') {
        $query->orderBy('id', 'asc');
    } else {
        $query->orderBy('id', 'desc');
    }

    $totalLogs = (int) $query->count();
    $sources = Capsule::table('mod_dcmanage_logs')->select('source')->distinct()->orderBy('source')->pluck('source')->toArray();
    $logRows = $query->offset(($logPage - 1) * $perPageLogs)->limit($perPageLogs)->get(['id', 'level', 'source', 'message', 'created_at']);

    echo '<h5 class="mb-3">' . htmlspecialchars(I18n::t('logs_system', $lang)) . '</h5>';
    echo '<form method="get" class="dcmanage-form-card mb-3">';
    echo '<input type="hidden" name="module" value="dcmanage"><input type="hidden" name="tab" value="logs">';
    echo '<div class="form-row">';
    echo '<div class="form-group col-md-4"><label>' . htmlspecialchars(I18n::t('logs_search', $lang)) . '</label><input type="text" class="form-control dcmanage-input" name="log_q" value="' . htmlspecialchars($q) . '"></div>';
    echo '<div class="form-group col-md-2"><label>' . htmlspecialchars(I18n::t('logs_level', $lang)) . '</label><select class="form-control dcmanage-input" name="log_level"><option value="">All</option><option value="error"' . ($level === 'error' ? ' selected' : '') . '>error</option><option value="warning"' . ($level === 'warning' ? ' selected' : '') . '>warning</option><option value="info"' . ($level === 'info' ? ' selected' : '') . '>info</option></select></div>';
    echo '<div class="form-group col-md-3"><label>' . htmlspecialchars(I18n::t('logs_source', $lang)) . '</label><select class="form-control dcmanage-input" name="log_source"><option value="">All</option>';
    foreach ($sources as $s) {
        $sv = (string) $s;
        echo '<option value="' . htmlspecialchars($sv) . '"' . ($source === $sv ? ' selected' : '') . '>' . htmlspecialchars($sv) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group col-md-3"><label>' . htmlspecialchars(I18n::t('logs_sort', $lang)) . '</label><select class="form-control dcmanage-input" name="log_sort">';
    $sortOptions = ['id_desc' => 'Newest', 'id_asc' => 'Oldest', 'level_asc' => 'Level (high->low)', 'level_desc' => 'Level (low->high)', 'date_asc' => 'Date asc'];
    foreach ($sortOptions as $k => $v) {
        echo '<option value="' . htmlspecialchars($k) . '"' . ($sort === $k ? ' selected' : '') . '>' . htmlspecialchars($v) . '</option>';
    }
    echo '</select></div>';
    echo '</div>';
    echo '<div class="dcmanage-form-actions d-flex flex-wrap">';
    echo '<button class="btn btn-primary btn-sm" type="submit">' . htmlspecialchars(I18n::t('logs_apply_filter', $lang)) . '</button>';
    echo '<a class="btn btn-outline-secondary btn-sm" href="addonmodules.php?module=dcmanage&tab=logs">' . htmlspecialchars(I18n::t('logs_reset_filter', $lang)) . '</a>';
    echo '</div>';
    echo '</form>';

    echo '<div class="dcmanage-form-actions d-flex flex-wrap mb-3">';
    echo '<form method="post" style="display:inline"><input type="hidden" name="dcmanage_action" value="logs_clear"><input type="hidden" name="scope" value="system"><button class="btn btn-outline-danger btn-sm" type="submit" name="dcmanage_action_btn" value="logs_clear">' . htmlspecialchars(I18n::t('logs_clear_system', $lang)) . '</button></form>';
    echo '<form method="post" style="display:inline"><input type="hidden" name="dcmanage_action" value="logs_clear"><input type="hidden" name="scope" value="purchase"><button class="btn btn-outline-warning btn-sm" type="submit" name="dcmanage_action_btn" value="logs_clear">' . htmlspecialchars(I18n::t('logs_clear_purchase', $lang)) . '</button></form>';
    echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Clear all logs?\')"><input type="hidden" name="dcmanage_action" value="logs_clear"><input type="hidden" name="scope" value="all"><button class="btn btn-danger btn-sm" type="submit" name="dcmanage_action_btn" value="logs_clear">' . htmlspecialchars(I18n::t('logs_clear_all', $lang)) . '</button></form>';
    echo '</div>';

    echo '<div class="table-responsive dcmanage-table-wrap"><table class="table table-sm table-striped">';
    echo '<thead><tr><th>ID</th><th>Level</th><th>Source</th><th>Message</th><th>Date</th></tr></thead><tbody>';
    foreach ($logRows as $row) {
        $lv = strtolower((string) $row->level);
        $badge = $lv === 'error' ? 'danger' : ($lv === 'warning' ? 'warning' : 'info');
        echo '<tr><td>' . (int) $row->id . '</td><td><span class="badge badge-' . $badge . '">' . htmlspecialchars((string) $row->level) . '</span></td><td>' . htmlspecialchars((string) $row->source) . '</td><td>' . htmlspecialchars((string) $row->message) . '</td><td>' . htmlspecialchars((string) $row->created_at) . '</td></tr>';
    }
    if (count($logRows) === 0) {
        echo '<tr><td colspan="5">-</td></tr>';
    }
    echo '</tbody></table></div>';
    echo dcmanage_render_simple_pagination('addonmodules.php?module=dcmanage&tab=logs&log_q=' . urlencode($q) . '&log_level=' . urlencode($level) . '&log_source=' . urlencode($source) . '&log_sort=' . urlencode($sort) . '&purchase_page=' . $purchasePage, $logPage, $perPageLogs, $totalLogs, $lang, 'log_page');

    echo '<h5 class="mt-4 mb-3">' . htmlspecialchars(I18n::t('purchase_logs', $lang)) . '</h5>';
    echo '<div class="table-responsive mb-2 dcmanage-table-wrap"><table class="table table-sm table-striped">';
    echo '<thead><tr><th>ID</th><th>Service</th><th>User</th><th>Package</th><th>GB</th><th>Price</th><th>Invoice</th><th>Date</th></tr></thead><tbody>';
    foreach ($purchaseRows as $row) {
        echo '<tr><td>' . (int) $row->id . '</td><td>' . (int) $row->whmcs_serviceid . '</td><td>' . (int) $row->userid . '</td><td>' . htmlspecialchars((string) $row->package_name) . '</td><td>' . htmlspecialchars((string) $row->size_gb) . '</td><td>' . htmlspecialchars((string) $row->price) . '</td><td>' . htmlspecialchars((string) $row->invoiceid) . '</td><td>' . htmlspecialchars((string) $row->created_at) . '</td></tr>';
    }
    if (count($purchaseRows) === 0) {
        echo '<tr><td colspan="8">-</td></tr>';
    }
    echo '</tbody></table></div>';
    echo dcmanage_render_simple_pagination('addonmodules.php?module=dcmanage&tab=logs&log_q=' . urlencode($q) . '&log_level=' . urlencode($level) . '&log_source=' . urlencode($source) . '&log_sort=' . urlencode($sort) . '&log_page=' . $logPage, $purchasePage, $perPagePurchase, $purchaseTotal, $lang, 'purchase_page');
}

function dcmanage_render_queue(string $lang): void
{
    $statusFilter = strtolower(trim((string) ($_GET['queue_status'] ?? '')));
    $q = trim((string) ($_GET['queue_q'] ?? ''));
    $page = max(1, (int) ($_GET['queue_page'] ?? 1));
    $perPage = 50;

    $query = Capsule::table('mod_dcmanage_jobs');
    if (in_array($statusFilter, ['pending', 'running', 'done', 'failed', 'canceled'], true)) {
        $query->where('status', $statusFilter);
    }
    if ($q !== '') {
        $query->where(static function ($w) use ($q): void {
            $w->where('type', 'like', '%' . $q . '%')
                ->orWhere('payload_json', 'like', '%' . $q . '%')
                ->orWhere('last_error', 'like', '%' . $q . '%');
        });
    }

    $total = (int) $query->count();
    $rows = $query->orderBy('id', 'desc')
        ->offset(($page - 1) * $perPage)
        ->limit($perPage)
        ->get(['id', 'type', 'status', 'attempts', 'run_after', 'created_at', 'started_at', 'finished_at', 'last_error']);

    $counts = [
        'pending' => (int) Capsule::table('mod_dcmanage_jobs')->where('status', 'pending')->count(),
        'running' => (int) Capsule::table('mod_dcmanage_jobs')->where('status', 'running')->count(),
        'failed' => (int) Capsule::table('mod_dcmanage_jobs')->where('status', 'failed')->count(),
    ];

    echo '<h5 class="mb-3">Job Queue</h5>';
    echo '<div class="dcmanage-form-card mb-3">';
    echo '<div class="row">';
    echo '<div class="col-md-4 mb-2"><div class="alert alert-info mb-0">Pending: <strong>' . $counts['pending'] . '</strong></div></div>';
    echo '<div class="col-md-4 mb-2"><div class="alert alert-warning mb-0">Running: <strong>' . $counts['running'] . '</strong></div></div>';
    echo '<div class="col-md-4 mb-2"><div class="alert alert-danger mb-0">Failed: <strong>' . $counts['failed'] . '</strong></div></div>';
    echo '</div>';
    echo '</div>';

    echo '<form method="get" class="dcmanage-form-card mb-3">';
    echo '<input type="hidden" name="module" value="dcmanage"><input type="hidden" name="tab" value="queue">';
    echo '<div class="form-row">';
    echo '<div class="form-group col-md-4"><label>' . htmlspecialchars(I18n::t('logs_search', $lang)) . '</label><input class="form-control dcmanage-input" name="queue_q" value="' . htmlspecialchars($q) . '"></div>';
    echo '<div class="form-group col-md-3"><label>' . htmlspecialchars(I18n::t('cron_status', $lang)) . '</label><select class="form-control dcmanage-input" name="queue_status">';
    echo '<option value="">All</option>';
    foreach (['pending', 'running', 'done', 'failed', 'canceled'] as $st) {
        $selected = $statusFilter === $st ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($st) . '"' . $selected . '>' . htmlspecialchars($st) . '</option>';
    }
    echo '</select></div>';
    echo '</div>';
    echo '<div class="dcmanage-form-actions d-flex flex-wrap">';
    echo '<button class="btn btn-primary btn-sm" type="submit">' . htmlspecialchars(I18n::t('logs_apply_filter', $lang)) . '</button>';
    echo '<a class="btn btn-outline-secondary btn-sm" href="addonmodules.php?module=dcmanage&tab=queue">' . htmlspecialchars(I18n::t('logs_reset_filter', $lang)) . '</a>';
    echo '</div>';
    echo '</form>';
    echo '<form method="post" class="mb-3" onsubmit="return confirm(\'Clear completed/failed/canceled jobs?\')"><input type="hidden" name="dcmanage_action" value="queue_clear_done"><button class="btn btn-outline-danger btn-sm" type="submit" name="dcmanage_action_btn" value="queue_clear_done">Clear Done/Failed</button></form>';

    echo '<div class="table-responsive dcmanage-table-wrap"><table class="table table-sm table-striped">';
    echo '<thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Attempts</th><th>Created</th><th>Started</th><th>Finished</th><th>Run After</th><th>Error</th><th>' . htmlspecialchars(I18n::t('label_actions', $lang)) . '</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $status = strtolower((string) $row->status);
        $badge = $status === 'done' ? 'success' : ($status === 'failed' ? 'danger' : ($status === 'running' ? 'warning' : ($status === 'canceled' ? 'secondary' : 'info')));
        echo '<tr>';
        echo '<td>' . (int) $row->id . '</td>';
        echo '<td>' . htmlspecialchars((string) $row->type) . '</td>';
        echo '<td><span class="badge badge-' . $badge . '">' . htmlspecialchars((string) $row->status) . '</span></td>';
        echo '<td>' . (int) $row->attempts . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row->created_at ?? '-')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row->started_at ?? '-')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row->finished_at ?? '-')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row->run_after ?? '-')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row->last_error ?? '-')) . '</td>';
        echo '<td class="dcmanage-action-buttons">';
        if (in_array($status, ['pending', 'running'], true)) {
            echo '<form method="post" style="display:inline"><input type="hidden" name="dcmanage_action" value="queue_cancel_job"><input type="hidden" name="job_id" value="' . (int) $row->id . '"><button type="submit" class="btn btn-sm dcmanage-btn-soft-warning" name="dcmanage_action_btn" value="queue_cancel_job">Cancel</button></form>';
        }
        if (in_array($status, ['failed', 'canceled'], true)) {
            echo '<form method="post" style="display:inline"><input type="hidden" name="dcmanage_action" value="queue_retry_job"><input type="hidden" name="job_id" value="' . (int) $row->id . '"><button type="submit" class="btn btn-sm dcmanage-btn-soft-success" name="dcmanage_action_btn" value="queue_retry_job">Retry</button></form>';
        }
        echo '</td>';
        echo '</tr>';
    }
    if (count($rows) === 0) {
        echo '<tr><td colspan="10">-</td></tr>';
    }
    echo '</tbody></table></div>';
    echo dcmanage_render_simple_pagination(
        'addonmodules.php?module=dcmanage&tab=queue&queue_q=' . urlencode($q) . '&queue_status=' . urlencode($statusFilter),
        $page,
        $perPage,
        $total,
        $lang,
        'queue_page'
    );
}

function dcmanage_tab_icon_svg(string $tab): string
{
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24"><path d="M3 13h8v8H3v-8zm10-10h8v18h-8V3zM3 3h8v8H3V3z"/></svg>',
        'datacenters' => '<svg viewBox="0 0 24 24"><path d="M4 5h16v4H4V5zm0 5h16v4H4v-4zm0 5h16v4H4v-4z"/></svg>',
        'switches' => '<svg viewBox="0 0 24 24"><path d="M3 8h18v8H3V8zm3 2h2v2H6v-2zm4 0h2v2h-2v-2zm4 0h2v2h-2v-2z"/></svg>',
        'servers' => '<svg viewBox="0 0 24 24"><path d="M4 4h16v6H4V4zm0 10h16v6H4v-6zm2-8h3v2H6V6zm0 10h3v2H6v-2z"/></svg>',
        'monitoring' => '<svg viewBox="0 0 24 24"><path d="M4 18h16v2H4v-2zM6 16l3-4 3 2 4-6 2 1-5 7-3-2-2 3-2-1z"/></svg>',
        'packages' => '<svg viewBox="0 0 24 24"><path d="M3 8l9-5 9 5v10l-9 5-9-5V8zm9-2.7L7 8l5 2.7L17 8l-5-2.7z"/></svg>',
        'scope' => '<svg viewBox="0 0 24 24"><path d="M11 2v2.1a8 8 0 107.9 7.9H21A10 10 0 1111 2zm2 0a10 10 0 018.9 8H13V2z"/></svg>',
        'traffic' => '<svg viewBox="0 0 24 24"><path d="M4 19h16v2H4v-2zM6 17V9h2v8H6zm5 0V5h2v12h-2zm5 0v-6h2v6h-2z"/></svg>',
        'queue' => '<svg viewBox="0 0 24 24"><path d="M4 4h16v4H4V4zm0 6h16v10H4V10zm3 2h10v2H7v-2zm0 4h7v2H7v-2z"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24"><path d="M19.4 13a7.8 7.8 0 000-2l2.1-1.6-2-3.5-2.5 1a7.7 7.7 0 00-1.7-1L15 3h-4l-.3 2.9a7.7 7.7 0 00-1.7 1l-2.5-1-2 3.5L6.6 11a7.8 7.8 0 000 2l-2.1 1.6 2 3.5 2.5-1a7.7 7.7 0 001.7 1L11 21h4l.3-2.9a7.7 7.7 0 001.7-1l2.5 1 2-3.5L19.4 13zM13 15h-2v-2h2v2zm0-4h-2V9h2v2z"/></svg>',
        'logs' => '<svg viewBox="0 0 24 24"><path d="M6 3h9l5 5v13H6V3zm8 1.5V9h4.5L14 4.5zM8 12h8v2H8v-2zm0 4h8v2H8v-2z"/></svg>',
    ];

    return $icons[$tab] ?? '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg>';
}
