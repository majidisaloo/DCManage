<?php

declare(strict_types=1);

namespace DCManage\Support;

use Illuminate\Database\Capsule\Manager as Capsule;

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
            'tab_monitoring' => 'Monitoring',
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
            'settings_language' => 'Language',
            'settings_language_default' => 'Default (WHMCS admin language)',
            'settings_language_fa' => 'Persian',
            'settings_language_en' => 'English',
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
            'monitoring_type' => 'Monitoring Type',
            'monitoring_select' => 'Select monitoring platform',
            'create_switch' => 'Create Switch',
            'action_racks' => 'Racks',
            'action_networks' => 'Networks',
            'action_servers' => 'Servers',
            'action_edit' => 'Edit',
            'action_delete' => 'Delete',
            'label_actions' => 'Actions',
            'label_notes' => 'Notes',
            'label_name' => 'Name',
            'label_location' => 'Location',
            'label_hostname' => 'Hostname',
            'label_rack' => 'Rack',
            'label_u' => 'U',
            'label_vlan' => 'VLAN',
            'label_purpose' => 'Purpose',
            'unit_blank' => 'Blank',
            'unit_reserved' => 'Reserved',
            'unit_cable' => 'Cable Mgmt',
            'unit_air' => 'Airflow',
            'set_unit' => 'Set Unit',
            'rack_front_view' => 'Front View',
            'rack_rear_view' => 'Rear View',
            'rack_legend' => 'Legend',
            'switch_snmp_test' => 'SNMP Test',
            'switch_discover_ports' => 'Discover Ports',
            'switch_ports_vlans' => 'Ports & VLANs',
            'switch_add_update_port' => 'Add/Update Port',
            'switch_if_name' => 'Interface',
            'switch_if_desc' => 'Description',
            'switch_if_speed' => 'Speed',
            'switch_admin_status' => 'Admin',
            'switch_oper_status' => 'Oper',
            'switch_status_up' => 'Up',
            'switch_status_down' => 'Down',
            'switch_status_unknown' => 'Unknown',
            'switch_status_untested' => 'Untested',
            'switch_discovery_saved' => 'Ports discovered and saved',
            'switch_discovery_none' => 'No ports were discovered',
            'switch_shut' => 'Shut',
            'switch_no_shut' => 'No Shut',
            'switch_port_updated' => 'Port updated successfully.',
            'settings_discovery_minutes' => 'Switch Discovery Interval (Minutes)',
            'label_auto' => 'Auto',
            'delete_confirm_switch' => 'Delete switch?',
            'delete_confirm_datacenter' => 'Delete datacenter?',
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
            'tab_monitoring' => 'مانیتورینگ',
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
            'settings_language' => 'زبان',
            'settings_language_default' => 'پیش‌فرض (زبان ادمین WHMCS)',
            'settings_language_fa' => 'فارسی',
            'settings_language_en' => 'انگلیسی',
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
            'monitoring_type' => 'نوع مانیتورینگ',
            'monitoring_select' => 'پلتفرم مانیتورینگ را انتخاب کنید',
            'create_switch' => 'ایجاد سوییچ',
            'action_racks' => 'رک‌ها',
            'action_networks' => 'شبکه‌ها',
            'action_servers' => 'سرورها',
            'action_edit' => 'ویرایش',
            'action_delete' => 'حذف',
            'label_actions' => 'عملیات',
            'label_notes' => 'یادداشت',
            'label_name' => 'نام',
            'label_location' => 'موقعیت',
            'label_hostname' => 'Hostname',
            'label_rack' => 'رک',
            'label_u' => 'یونیت',
            'label_vlan' => 'VLAN',
            'label_purpose' => 'کاربری',
            'unit_blank' => 'خالی',
            'unit_reserved' => 'رزرو',
            'unit_cable' => 'مدیریت کابل',
            'unit_air' => 'هوا',
            'set_unit' => 'ثبت یونیت',
            'rack_front_view' => 'نمای جلو',
            'rack_rear_view' => 'نمای پشت',
            'rack_legend' => 'راهنما',
            'switch_snmp_test' => 'تست SNMP',
            'switch_discover_ports' => 'شناسایی پورت‌ها',
            'switch_ports_vlans' => 'پورت‌ها و VLAN',
            'switch_add_update_port' => 'افزودن/ویرایش پورت',
            'switch_if_name' => 'اینترفیس',
            'switch_if_desc' => 'توضیحات',
            'switch_if_speed' => 'سرعت',
            'switch_admin_status' => 'ادمین',
            'switch_oper_status' => 'عملیاتی',
            'switch_status_up' => 'فعال',
            'switch_status_down' => 'قطع',
            'switch_status_unknown' => 'نامشخص',
            'switch_status_untested' => 'تست نشده',
            'switch_discovery_saved' => 'پورت‌ها شناسایی و ذخیره شدند',
            'switch_discovery_none' => 'پورتی شناسایی نشد',
            'switch_shut' => 'شات',
            'switch_no_shut' => 'نو شات',
            'switch_port_updated' => 'پورت با موفقیت به‌روزرسانی شد.',
            'settings_discovery_minutes' => 'بازه زمانی دیسکاوری سوییچ (دقیقه)',
            'label_auto' => 'اتو',
            'delete_confirm_switch' => 'سوییچ حذف شود؟',
            'delete_confirm_datacenter' => 'دیتاسنتر حذف شود؟',
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
        } elseif (class_exists(Capsule::class) && Capsule::schema()->hasTable('mod_dcmanage_meta')) {
            $stored = Capsule::table('mod_dcmanage_meta')->where('meta_key', 'settings.locale')->value('meta_value');
            $stored = strtolower(trim((string) $stored));
            if ($stored !== '' && $stored !== 'default') {
                $candidate = (string) $stored;
            }
        }

        if ($candidate === '' && !empty($_SESSION['Language'])) {
            $candidate = (string) $_SESSION['Language'];
        }
        if ($candidate === '' && !empty($_SESSION['adminlang'])) {
            $candidate = (string) $_SESSION['adminlang'];
        }
        if ($candidate === '' && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $candidate = substr((string) $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
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
