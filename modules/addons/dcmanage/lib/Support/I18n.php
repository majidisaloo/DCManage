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
            'tab_networks' => 'Networks',
            'tab_switches' => 'Switches',
            'tab_servers' => 'Servers',
            'tab_ports' => 'Ports',
            'tab_packages' => 'Traffic Packages',
            'tab_scope' => 'WHMCS Scope',
            'tab_traffic' => 'Traffic',
            'tab_automation' => 'Automation / Cron',
            'tab_logs' => 'Logs',
            'tab_settings' => 'Settings',
            'dashboard_info' => 'Dashboard health and counters load from internal API.',
            'crud_placeholder_prefix' => 'CRUD UI for',
            'crud_placeholder_suffix' => 'will be completed in next sprint, API layer is ready.',
            'section' => 'Section',
            'system_settings' => 'System Settings',
            'save_settings' => 'Save Settings',
            'settings_saved' => 'Settings saved successfully.',
            'datacenter_add' => 'Add Datacenter',
            'datacenter_name' => 'Datacenter Name',
            'datacenter_code' => 'Code',
            'datacenter_location' => 'Location',
            'datacenter_rack_count' => 'Rack Count',
            'datacenter_rack_units' => 'Rack Units (U)',
            'create_datacenter' => 'Create Datacenter',
            'switch_add' => 'Add Switch',
            'switch_name' => 'Switch Name',
            'switch_vendor' => 'Vendor',
            'switch_model' => 'Model',
            'switch_mgmt_ip' => 'Management IP',
            'server_add' => 'Add Server',
            'server_hostname' => 'Hostname',
            'create_server' => 'Create Server',
            'select_datacenter' => 'Select Datacenter',
            'select_rack' => 'Select Rack',
            'create_switch' => 'Create Switch',
            'cron_monitor' => 'Cron Monitor',
            'cron_task' => 'Task',
            'cron_status' => 'Status',
            'cron_last_run' => 'Last Run',
            'cron_next_run' => 'Next Run',
            'cron_command' => 'Command',
            'cron_overall' => 'Overall Cron Health',
            'cron_check_now' => 'Check Now',
            'purchase_logs' => 'Package Purchase Logs',
            'status_ok' => 'OK',
            'status_warning' => 'Warning',
            'status_fail' => 'Fail',
            'version_center' => 'Version & Update Center',
            'check_update' => 'Check Update',
            'apply_update' => 'Apply Update',
            'auto_update' => 'Auto Update',
            'latest_release' => 'Latest Release',
            'current_version' => 'Current Version',
            'logs_system' => 'System Logs',
            'saved' => 'Saved successfully.',
            'created' => 'Created successfully.',
        ],
        'fa' => [
            'title' => 'مدیریت دیتاسنتر',
            'subtitle' => 'هسته API-محور دیتاسنتر',
            'tab_dashboard' => 'داشبورد',
            'tab_datacenters' => 'دیتاسنترها',
            'tab_networks' => 'شبکه‌ها',
            'tab_switches' => 'سوییچ‌ها',
            'tab_servers' => 'سرورها',
            'tab_ports' => 'پورت‌ها',
            'tab_packages' => 'پکیج‌های ترافیک',
            'tab_scope' => 'دامنه WHMCS',
            'tab_traffic' => 'ترافیک',
            'tab_automation' => 'اتوماسیون / کرون',
            'tab_logs' => 'لاگ‌ها',
            'tab_settings' => 'تنظیمات',
            'dashboard_info' => 'سلامت سیستم و شمارنده‌ها از API داخلی بارگذاری می‌شوند.',
            'crud_placeholder_prefix' => 'فرم‌های CRUD برای',
            'crud_placeholder_suffix' => 'در اسپرینت بعد تکمیل می‌شود. لایه API آماده است.',
            'section' => 'بخش',
            'system_settings' => 'تنظیمات کلی سیستم',
            'save_settings' => 'ذخیره تنظیمات',
            'settings_saved' => 'تنظیمات با موفقیت ذخیره شد.',
            'datacenter_add' => 'افزودن دیتاسنتر',
            'datacenter_name' => 'نام دیتاسنتر',
            'datacenter_code' => 'کد',
            'datacenter_location' => 'موقعیت',
            'datacenter_rack_count' => 'تعداد رک',
            'datacenter_rack_units' => 'یونیت هر رک (U)',
            'create_datacenter' => 'ایجاد دیتاسنتر',
            'switch_add' => 'افزودن سوییچ',
            'switch_name' => 'نام سوییچ',
            'switch_vendor' => 'برند',
            'switch_model' => 'مدل',
            'switch_mgmt_ip' => 'IP مدیریت',
            'server_add' => 'افزودن سرور',
            'server_hostname' => 'نام میزبان',
            'create_server' => 'ایجاد سرور',
            'select_datacenter' => 'انتخاب دیتاسنتر',
            'select_rack' => 'انتخاب رک',
            'create_switch' => 'ایجاد سوییچ',
            'cron_monitor' => 'مانیتور کرون',
            'cron_task' => 'تسک',
            'cron_status' => 'وضعیت',
            'cron_last_run' => 'آخرین اجرا',
            'cron_next_run' => 'اجرای بعدی',
            'cron_command' => 'دستور',
            'cron_overall' => 'وضعیت کلی کرون',
            'cron_check_now' => 'بررسی الآن',
            'purchase_logs' => 'لاگ خرید پکیج',
            'status_ok' => 'سالم',
            'status_warning' => 'هشدار',
            'status_fail' => 'خراب',
            'version_center' => 'مرکز نسخه و آپدیت',
            'check_update' => 'بررسی آپدیت',
            'apply_update' => 'اعمال آپدیت',
            'auto_update' => 'آپدیت خودکار',
            'latest_release' => 'آخرین ریلیز',
            'current_version' => 'نسخه فعلی',
            'logs_system' => 'لاگ سیستم',
            'saved' => 'با موفقیت ذخیره شد.',
            'created' => 'با موفقیت ایجاد شد.',
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
