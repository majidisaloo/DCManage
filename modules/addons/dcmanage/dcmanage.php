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
    $activeTab = $_GET['tab'] ?? 'dashboard';

    if ($activeTab === 'settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        dcmanage_handle_settings_save();
        echo '<div class="alert alert-success">' . htmlspecialchars(I18n::t('settings_saved', $lang)) . '</div>';
    }

    echo '<div class="container-fluid dcmanage-shell">';
    echo '<div class="row mb-3"><div class="col-12 text-right">';
    echo '<h2 class="mb-1">' . htmlspecialchars(I18n::t('title', $lang)) . '</h2>';
    echo '<p class="text-muted mb-0">' . htmlspecialchars(I18n::t('subtitle', $lang)) . ' - Version ' . htmlspecialchars(DCManage\Version::CURRENT) . '</p>';
    echo '</div></div>';

    echo '<ul class="nav nav-tabs dcmanage-tabs" role="tablist">';
    $tabs = [
        'dashboard' => I18n::t('tab_dashboard', $lang),
        'datacenters' => I18n::t('tab_datacenters', $lang),
        'racks' => I18n::t('tab_racks', $lang),
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
        'logs' => I18n::t('tab_logs', $lang),
        'settings' => I18n::t('tab_settings', $lang),
    ];

    foreach ($tabs as $key => $label) {
        $active = $activeTab === $key ? ' active' : '';
        echo '<li class="nav-item">';
        echo '<a class="nav-link' . $active . '" href="' . $moduleLink . '&tab=' . urlencode($key) . '">' . htmlspecialchars($label) . '</a>';
        echo '</li>';
    }
    echo '</ul>';

    echo '<div class="card mt-3 border-0 shadow-sm"><div class="card-body">';

    if ($activeTab === 'dashboard') {
        echo '<div id="dcmanage-dashboard" data-api-base="' . $moduleLink . '&dcmanage_api=1&endpoint="></div>';
        echo '<div id="dcmanage-version" class="mt-3" data-api-base="' . $moduleLink . '&dcmanage_api=1&endpoint="></div>';
        echo '<div class="alert alert-info mt-3 mb-0">' . htmlspecialchars(I18n::t('dashboard_info', $lang)) . '</div>';
    } elseif ($activeTab === 'traffic') {
        echo '<div id="dcmanage-traffic" data-api-base="' . $moduleLink . '&dcmanage_api=1&endpoint="></div>';
        echo '<div style="height:340px"><canvas id="dcmanage-traffic-chart" height="120"></canvas></div>';
    } elseif ($activeTab === 'settings') {
        dcmanage_render_settings_form($lang);
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

function dcmanage_system_defaults(): array
{
    return [
        'timezone' => 'Asia/Tehran',
        'locale' => 'fa',
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

function dcmanage_render_settings_form(string $lang): void
{
    $settings = dcmanage_system_settings();

    echo '<h5 class="mb-3">' . htmlspecialchars(I18n::t('system_settings', $lang)) . '</h5>';
    echo '<form method="post" action="">';
    echo '<div class="row">';

    echo '<div class="col-md-4 mb-3"><label>Timezone</label><input class="form-control" name="timezone" value="' . htmlspecialchars($settings['timezone']) . '"></div>';
    echo '<div class="col-md-4 mb-3"><label>Locale</label><select class="form-control" name="locale">';
    $locales = ['fa' => 'Persian', 'en' => 'English'];
    foreach ($locales as $k => $v) {
        $selected = $settings['locale'] === $k ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($k) . '"' . $selected . '>' . htmlspecialchars($v) . '</option>';
    }
    echo '</select></div>';

    echo '<div class="col-md-4 mb-3"><label>Traffic Poll Minutes</label><input class="form-control" name="traffic_poll_minutes" value="' . htmlspecialchars($settings['traffic_poll_minutes']) . '"></div>';
    echo '<div class="col-md-4 mb-3"><label>Graph Cache TTL (Minutes)</label><input class="form-control" name="graph_cache_ttl_minutes" value="' . htmlspecialchars($settings['graph_cache_ttl_minutes']) . '"></div>';
    echo '<div class="col-md-4 mb-3"><label>Log Retention (Days)</label><input class="form-control" name="log_retention_days" value="' . htmlspecialchars($settings['log_retention_days']) . '"></div>';
    echo '<div class="col-md-4 mb-3"><label>Dashboard Refresh (Seconds)</label><input class="form-control" name="dashboard_refresh_seconds" value="' . htmlspecialchars($settings['dashboard_refresh_seconds']) . '"></div>';

    echo '</div>';
    echo '<button type="submit" class="btn btn-primary">' . htmlspecialchars(I18n::t('save_settings', $lang)) . '</button>';
    echo '</form>';
}
