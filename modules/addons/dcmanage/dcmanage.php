<?php

declare(strict_types=1);

use DCManage\Api\Router;
use DCManage\Database\Schema;
use DCManage\Support\I18n;

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

    echo '<div class="container-fluid">';
    echo '<div class="row mb-3"><div class="col-12">';
    echo '<h2>' . htmlspecialchars(I18n::t('title', $lang)) . '</h2>';
    echo '<p class="text-muted">' . htmlspecialchars(I18n::t('subtitle', $lang)) . ' - Version ' . htmlspecialchars(DCManage\Version::CURRENT) . '</p>';
    echo '</div></div>';

    echo '<ul class="nav nav-tabs" role="tablist">';
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
    ];
    $activeTab = $_GET['tab'] ?? 'dashboard';
    foreach ($tabs as $key => $label) {
        $active = $activeTab === $key ? ' active' : '';
        echo '<li class="nav-item">';
        echo '<a class="nav-link' . $active . '" href="' . $moduleLink . '&tab=' . urlencode($key) . '">' . htmlspecialchars($label) . '</a>';
        echo '</li>';
    }
    echo '</ul>';

    echo '<div class="card mt-3"><div class="card-body">';

    if ($activeTab === 'dashboard') {
        echo '<div id="dcmanage-dashboard" data-api-base="' . $moduleLink . '&dcmanage_api=1&endpoint="></div>';
        echo '<div class="alert alert-info">' . htmlspecialchars(I18n::t('dashboard_info', $lang)) . '</div>';
    } elseif ($activeTab === 'traffic') {
        echo '<div id="dcmanage-traffic" data-api-base="' . $moduleLink . '&dcmanage_api=1&endpoint="></div>';
        echo '<canvas id="dcmanage-traffic-chart" height="120"></canvas>';
    } else {
        echo '<div class="alert alert-secondary mb-0">';
        echo htmlspecialchars(I18n::t('crud_placeholder_prefix', $lang)) . ' <strong>' . htmlspecialchars($tabs[$activeTab] ?? I18n::t('section', $lang)) . '</strong> ' . htmlspecialchars(I18n::t('crud_placeholder_suffix', $lang));
        echo '</div>';
    }

    echo '</div></div>';
    echo '</div>';

    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
    echo '<script src="../modules/addons/dcmanage/assets/js/admin.js?v=' . rawurlencode(DCManage\Version::CURRENT) . '"></script>';
}
