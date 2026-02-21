<?php

declare(strict_types=1);

namespace DCManage\Support;

final class I18n
{
    private const DEFAULT_LANG = 'en';

    private const DICT = [
        'en' => [
            'title' => 'DCManage',
            'subtitle' => 'API-first Datacenter Core',
            'tab_dashboard' => 'Dashboard',
            'tab_datacenters' => 'Datacenters',
            'tab_racks' => 'Racks',
            'tab_networks' => 'Networks',
            'tab_switches' => 'Switches',
            'tab_servers' => 'Servers',
            'tab_ports' => 'Ports',
            'tab_packages' => 'Traffic Packages',
            'tab_scope' => 'WHMCS Scope',
            'tab_traffic' => 'Traffic',
            'tab_automation' => 'Automation / Cron',
            'tab_logs' => 'Logs',
            'dashboard_info' => 'Dashboard health and counters load from internal API.',
            'crud_placeholder_prefix' => 'CRUD UI for',
            'crud_placeholder_suffix' => 'will be completed in next sprint, API layer is ready.',
            'section' => 'Section',
        ],
        'fa' => [
            'title' => 'مدیریت دیتاسنتر',
            'subtitle' => 'هسته API-محور دیتاسنتر',
            'tab_dashboard' => 'داشبورد',
            'tab_datacenters' => 'دیتاسنترها',
            'tab_racks' => 'رک‌ها',
            'tab_networks' => 'شبکه‌ها',
            'tab_switches' => 'سوییچ‌ها',
            'tab_servers' => 'سرورها',
            'tab_ports' => 'پورت‌ها',
            'tab_packages' => 'پکیج‌های ترافیک',
            'tab_scope' => 'دامنه WHMCS',
            'tab_traffic' => 'ترافیک',
            'tab_automation' => 'اتوماسیون / کران',
            'tab_logs' => 'لاگ‌ها',
            'dashboard_info' => 'سلامت سیستم و شمارنده‌ها از API داخلی بارگذاری می‌شوند.',
            'crud_placeholder_prefix' => 'فرم‌های CRUD برای',
            'crud_placeholder_suffix' => 'در اسپرینت بعد تکمیل می‌شود. لایه API آماده است.',
            'section' => 'بخش',
        ],
    ];

    public static function resolveCurrentLanguage(): string
    {
        $candidate = '';

        if (!empty($_GET['lang'])) {
            $candidate = (string) $_GET['lang'];
        } elseif (!empty($_SESSION['Language'])) {
            $candidate = (string) $_SESSION['Language'];
        } elseif (!empty($_SESSION['adminlang'])) {
            $candidate = (string) $_SESSION['adminlang'];
        }

        $candidate = strtolower(trim($candidate));
        if ($candidate === 'persian' || $candidate === 'farsi' || $candidate === 'fa_ir') {
            $candidate = 'fa';
        }

        if ($candidate !== 'fa' && $candidate !== 'en') {
            $candidate = self::DEFAULT_LANG;
        }

        return $candidate;
    }

    public static function t(string $key, ?string $lang = null): string
    {
        $lang = $lang ?? self::resolveCurrentLanguage();
        if (isset(self::DICT[$lang][$key])) {
            return self::DICT[$lang][$key];
        }

        return self::DICT[self::DEFAULT_LANG][$key] ?? $key;
    }
}
