<?php

declare(strict_types=1);

namespace DCManage\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

final class Schema
{
    private const SCHEMA_VERSION = 16;

    public static function migrate(): void
    {
        self::ensureMeta();
        $current = self::getCurrentVersion();

        if ($current < 1) {
            self::migrationV1();
            self::setCurrentVersion(1);
        }

        if ($current < 2) {
            self::migrationV2();
            self::setCurrentVersion(2);
        }

        if ($current < 3) {
            self::migrationV3();
            self::setCurrentVersion(3);
        }

        if ($current < 4) {
            self::migrationV4();
            self::setCurrentVersion(4);
        }

        if ($current < 5) {
            self::migrationV5();
            self::setCurrentVersion(5);
        }

        if ($current < 6) {
            self::migrationV6();
            self::setCurrentVersion(6);
        }

        if ($current < 7) {
            self::migrationV7();
            self::setCurrentVersion(7);
        }

        if ($current < 8) {
            self::migrationV8();
            self::setCurrentVersion(8);
        }

        if ($current < 9) {
            self::migrationV9();
            self::setCurrentVersion(9);
        }

        if ($current < 10) {
            self::migrationV10();
            self::setCurrentVersion(10);
        }

        if ($current < 11) {
            self::migrationV11();
            self::setCurrentVersion(11);
        }

        if ($current < 12) {
            self::migrationV12();
            self::setCurrentVersion(12);
        }

        if ($current < 13) {
            self::migrationV13();
            self::setCurrentVersion(13);
        }

        if ($current < 14) {
            self::migrationV14();
            self::setCurrentVersion(14);
        }

        if ($current < 15) {
            self::migrationV15();
            self::setCurrentVersion(15);
        }

        if ($current < 16) {
            self::migrationV16();
            self::setCurrentVersion(16);
        }

        if ($current < 17) {
            self::migrationV17();
            self::setCurrentVersion(17);
        }

        if ($current < 18) {
            self::migrationV18();
            self::setCurrentVersion(18);
        }

        if ($current < 19) {
            self::migrationV19();
            self::setCurrentVersion(19);
        }
    }

    private static function ensureMeta(): void
    {
        if (!Capsule::schema()->hasTable('mod_dcmanage_meta')) {
            Capsule::schema()->create('mod_dcmanage_meta', static function (Blueprint $table): void {
                $table->string('meta_key', 64)->primary();
                $table->text('meta_value')->nullable();
                $table->timestamp('updated_at')->nullable();
            });

            Capsule::table('mod_dcmanage_meta')->insert([
                'meta_key' => 'schema_version',
                'meta_value' => '0',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private static function getCurrentVersion(): int
    {
        $value = Capsule::table('mod_dcmanage_meta')->where('meta_key', 'schema_version')->value('meta_value');
        return (int) ($value ?? 0);
    }

    private static function setCurrentVersion(int $version): void
    {
        Capsule::table('mod_dcmanage_meta')->updateOrInsert(
            ['meta_key' => 'schema_version'],
            ['meta_value' => (string) $version, 'updated_at' => date('Y-m-d H:i:s')]
        );
    }

    private static function migrationV1(): void
    {
        self::createDatacenterTables();
        self::createPrtgTables();
        self::createWhmcsScopeTables();
        self::createUsageTables();
        self::createOpsTables();
    }

    private static function createDatacenterTables(): void
    {
        if (!Capsule::schema()->hasTable('mod_dcmanage_datacenters')) {
            Capsule::schema()->create('mod_dcmanage_datacenters', static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name', 191);
                $table->string('code', 64)->nullable();
                $table->string('location', 191)->nullable();
                $table->string('traffic_calc_mode', 16)->default('TOTAL');
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable('mod_dcmanage_racks')) {
            Capsule::schema()->create('mod_dcmanage_racks', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('dc_id')->index();
                $table->string('name', 191);
                $table->string('row', 64)->nullable();
                $table->string('rack', 64)->nullable();
                $table->unsignedSmallInteger('total_u')->default(42);
                $table->text('notes')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable('mod_dcmanage_networks')) {
            Capsule::schema()->create('mod_dcmanage_networks', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('dc_id')->index();
                $table->string('name', 191);
                $table->string('vlan', 64)->nullable();
                $table->string('cidr', 128)->nullable();
                $table->string('purpose', 32)->default('public')->index();
                $table->text('notes')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable('mod_dcmanage_switches')) {
            Capsule::schema()->create('mod_dcmanage_switches', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('dc_id')->index();
                $table->string('name', 191);
                $table->string('vendor', 64)->nullable();
                $table->string('model', 64)->nullable();
                $table->string('mgmt_ip', 191)->nullable();
                $table->string('snmp_version', 16)->default('2c');
                $table->unsignedSmallInteger('snmp_port')->default(161);
                $table->string('snmp_community', 191)->nullable();
                $table->boolean('nxapi_enabled')->default(false);
                $table->string('nxapi_url', 255)->nullable();
                $table->string('nxapi_user', 191)->nullable();
                $table->text('nxapi_pass_enc')->nullable();
                $table->boolean('ssh_enabled')->default(false);
                $table->string('ssh_user', 191)->nullable();
                $table->text('ssh_pass_enc')->nullable();
                $table->unsignedSmallInteger('ssh_port')->default(22);
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable('mod_dcmanage_ilos')) {
            Capsule::schema()->create('mod_dcmanage_ilos', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('dc_id')->index();
                $table->string('name', 191);
                $table->string('host', 191);
                $table->string('user', 191);
                $table->text('pass_enc');
                $table->string('type', 16)->default('ilo5');
                $table->text('notes')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable('mod_dcmanage_servers')) {
            Capsule::schema()->create('mod_dcmanage_servers', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('dc_id')->index();
                $table->unsignedInteger('rack_id')->nullable()->index();
                $table->string('hostname', 191)->index();
                $table->string('asset_tag', 191)->nullable();
                $table->string('serial', 191)->nullable();
                $table->unsignedSmallInteger('u_start')->nullable();
                $table->unsignedSmallInteger('u_height')->default(1);
                $table->unsignedInteger('ilo_id')->nullable()->index();
                $table->string('ilo_host', 191)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable('mod_dcmanage_server_ports')) {
            Capsule::schema()->create('mod_dcmanage_server_ports', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('server_id')->index();
                $table->unsignedSmallInteger('port_no');
                $table->unsignedInteger('network_id')->nullable()->index();
                $table->unsignedInteger('switch_id')->nullable()->index();
                $table->string('switch_if', 64)->nullable();
                $table->string('prtg_sensor_id', 64)->nullable()->index();
                $table->string('prtg_channel_in', 64)->nullable();
                $table->string('prtg_channel_out', 64)->nullable();
                $table->boolean('enforce_enabled')->default(false);
                $table->timestamp('created_at')->nullable();
                $table->unique(['server_id', 'port_no'], 'mod_dcmanage_server_port_uniq');
            });
        }
    }

    private static function createPrtgTables(): void
    {
        if (!Capsule::schema()->hasTable('mod_dcmanage_prtg_instances')) {
            Capsule::schema()->create('mod_dcmanage_prtg_instances', static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name', 191);
                $table->string('type', 32)->default('prtg')->index();
                $table->string('base_url', 255);
                $table->string('user', 191);
                $table->string('auth_mode', 32)->default('passhash');
                $table->text('passhash_enc');
                $table->string('timezone', 64)->nullable();
                $table->boolean('verify_ssl')->default(true);
                $table->text('proxy_json')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable('mod_dcmanage_prtg_map')) {
            Capsule::schema()->create('mod_dcmanage_prtg_map', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('prtg_id')->index();
                $table->unsignedInteger('dc_id')->index();
                $table->string('prtg_group_id', 64)->nullable();
                $table->string('prtg_device_id', 64)->nullable();
                $table->string('purpose', 32)->default('traffic')->index();
                $table->text('notes')->nullable();
            });
        }
    }

    private static function createWhmcsScopeTables(): void
    {
        if (!Capsule::schema()->hasTable('mod_dcmanage_scope')) {
            Capsule::schema()->create('mod_dcmanage_scope', static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('type', 8)->index();
                $table->unsignedInteger('ref_id')->index();
                $table->boolean('enabled')->default(true);
                $table->decimal('default_quota_gb', 12, 3)->default(0);
                $table->string('default_mode', 16)->default('TOTAL');
                $table->string('default_action', 16)->default('BLOCK');
                $table->decimal('down_limit_gb', 12, 3)->nullable();
                $table->decimal('up_limit_gb', 12, 3)->nullable();
                $table->decimal('total_limit_gb', 12, 3)->nullable();
                $table->boolean('down_unlimited')->default(false);
                $table->boolean('up_unlimited')->default(false);
                $table->boolean('total_unlimited')->default(false);
                $table->unsignedInteger('dc_id')->nullable()->index();
                $table->decimal('autobuy_threshold_gb', 12, 3)->nullable();
                $table->unsignedInteger('autobuy_package_id')->nullable();
                $table->unsignedSmallInteger('autobuy_max_buys')->default(0);
                $table->unique(['type', 'ref_id'], 'mod_dcmanage_scope_uniq');
            });
        }

        if (!Capsule::schema()->hasTable('mod_dcmanage_service_link')) {
            Capsule::schema()->create('mod_dcmanage_service_link', static function (Blueprint $table): void {
                $table->unsignedInteger('whmcs_serviceid')->primary();
                $table->unsignedInteger('userid')->index();
                $table->unsignedInteger('dc_id')->nullable()->index();
                $table->unsignedInteger('server_id')->nullable()->index();
                $table->text('port_ids_json')->nullable();
                $table->unsignedInteger('prtg_id')->nullable()->index();
                $table->string('prtg_sensor_id', 64)->nullable();
                $table->string('traffic_source', 16)->default('prtg');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    private static function createUsageTables(): void
    {
        if (!Capsule::schema()->hasTable('mod_dcmanage_packages')) {
            Capsule::schema()->create('mod_dcmanage_packages', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('dc_id')->nullable()->index();
                $table->string('name', 191);
                $table->decimal('size_gb', 12, 3);
                $table->decimal('price', 12, 2);
                $table->boolean('taxed')->default(false);
                $table->boolean('active')->default(true);
            });
        }

        if (!Capsule::schema()->hasTable('mod_dcmanage_purchases')) {
            Capsule::schema()->create('mod_dcmanage_purchases', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('whmcs_serviceid')->index();
                $table->unsignedInteger('userid')->index();
                $table->unsignedInteger('package_id')->index();
                $table->decimal('size_gb', 12, 3);
                $table->decimal('price', 12, 2);
                $table->unsignedInteger('invoiceid')->nullable()->index();
                $table->timestamp('cycle_start')->nullable()->index();
                $table->timestamp('cycle_end')->nullable()->index();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable('mod_dcmanage_usage_state')) {
            Capsule::schema()->create('mod_dcmanage_usage_state', static function (Blueprint $table): void {
                $table->unsignedInteger('whmcs_serviceid')->primary();
                $table->timestamp('cycle_start')->nullable()->index();
                $table->timestamp('cycle_end')->nullable()->index();
                $table->string('mode', 16)->default('TOTAL');
                $table->string('action', 16)->default('BLOCK');
                $table->decimal('base_quota_gb', 12, 3)->default(0);
                $table->decimal('extra_quota_gb', 12, 3)->default(0);
                $table->unsignedBigInteger('used_bytes')->default(0);
                $table->unsignedBigInteger('download_bytes')->default(0);
                $table->unsignedBigInteger('upload_bytes')->default(0);
                $table->unsignedBigInteger('last_in_octets')->nullable();
                $table->unsignedBigInteger('last_out_octets')->nullable();
                $table->timestamp('last_sample_at')->nullable();
                $table->string('last_status', 16)->default('ok')->index();
                $table->timestamp('last_enforce_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable('mod_dcmanage_graph_cache')) {
            Capsule::schema()->create('mod_dcmanage_graph_cache', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('whmcs_serviceid')->index();
                $table->timestamp('range_start')->index();
                $table->timestamp('range_end')->index();
                $table->string('source', 16)->default('prtg');
                $table->string('payload_hash', 64)->index();
                $table->longText('json_data');
                $table->timestamp('cached_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->index(['whmcs_serviceid', 'source'], 'mod_dcmanage_graph_cache_lookup');
            });
        }
    }

    private static function createOpsTables(): void
    {
        if (!Capsule::schema()->hasTable('mod_dcmanage_logs')) {
            Capsule::schema()->create('mod_dcmanage_logs', static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('level', 16)->index();
                $table->string('source', 64)->index();
                $table->unsignedInteger('service_id')->nullable()->index();
                $table->unsignedInteger('dc_id')->nullable()->index();
                $table->text('message');
                $table->longText('context_json')->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        if (!Capsule::schema()->hasTable('mod_dcmanage_locks')) {
            Capsule::schema()->create('mod_dcmanage_locks', static function (Blueprint $table): void {
                $table->string('lock_key', 191)->primary();
                $table->string('owner', 191);
                $table->timestamp('acquired_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
            });
        }

        if (!Capsule::schema()->hasTable('mod_dcmanage_jobs')) {
            Capsule::schema()->create('mod_dcmanage_jobs', static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('type', 32)->index();
                $table->longText('payload_json');
                $table->string('status', 16)->default('pending')->index();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('run_after')->nullable()->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }
    }

    private static function migrationV2(): void
    {
        if (Capsule::schema()->hasTable('mod_dcmanage_switches') && !Capsule::schema()->hasColumn('mod_dcmanage_switches', 'rack_id')) {
            Capsule::schema()->table('mod_dcmanage_switches', static function (Blueprint $table): void {
                $table->unsignedInteger('rack_id')->nullable()->after('dc_id')->index();
            });
        }
    }

    private static function migrationV3(): void
    {
        if (Capsule::schema()->hasTable('mod_dcmanage_switches') && !Capsule::schema()->hasColumn('mod_dcmanage_switches', 'u_start')) {
            Capsule::schema()->table('mod_dcmanage_switches', static function (Blueprint $table): void {
                $table->unsignedSmallInteger('u_start')->nullable()->after('rack_id');
                $table->unsignedSmallInteger('u_height')->default(1)->after('u_start');
            });
        }
    }

    private static function migrationV4(): void
    {
        if (!Capsule::schema()->hasTable('mod_dcmanage_rack_units')) {
            Capsule::schema()->create('mod_dcmanage_rack_units', static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('rack_id')->index();
                $table->unsignedSmallInteger('u_no')->index();
                $table->string('unit_type', 32)->default('blank')->index();
                $table->string('label', 191)->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['rack_id', 'u_no'], 'mod_dcmanage_rack_units_unique');
            });
        }
    }

    private static function migrationV5(): void
    {
        if (!Capsule::schema()->hasTable('mod_dcmanage_switch_ports')) {
            Capsule::schema()->create('mod_dcmanage_switch_ports', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('switch_id')->index();
                $table->string('if_name', 64)->index();
                $table->string('vlan', 64)->nullable();
                $table->string('admin_status', 16)->nullable();
                $table->string('oper_status', 16)->nullable();
                $table->timestamp('last_seen')->nullable();
            });
        }
    }

    private static function migrationV6(): void
    {
        if (Capsule::schema()->hasTable('mod_dcmanage_switch_ports') && !Capsule::schema()->hasColumn('mod_dcmanage_switch_ports', 'if_desc')) {
            Capsule::schema()->table('mod_dcmanage_switch_ports', static function (Blueprint $table): void {
                $table->string('if_desc', 191)->nullable()->after('if_name');
            });
        }

        if (Capsule::schema()->hasTable('mod_dcmanage_switch_ports') && !Capsule::schema()->hasColumn('mod_dcmanage_switch_ports', 'if_index')) {
            Capsule::schema()->table('mod_dcmanage_switch_ports', static function (Blueprint $table): void {
                $table->unsignedInteger('if_index')->nullable()->after('switch_id')->index();
            });
        }
    }

    private static function migrationV7(): void
    {
        if (Capsule::schema()->hasTable('mod_dcmanage_switch_ports') && !Capsule::schema()->hasColumn('mod_dcmanage_switch_ports', 'speed_mbps')) {
            Capsule::schema()->table('mod_dcmanage_switch_ports', static function (Blueprint $table): void {
                $table->unsignedInteger('speed_mbps')->nullable()->after('vlan');
            });
        }

        if (Capsule::schema()->hasTable('mod_dcmanage_switch_ports') && !Capsule::schema()->hasColumn('mod_dcmanage_switch_ports', 'speed_mode')) {
            Capsule::schema()->table('mod_dcmanage_switch_ports', static function (Blueprint $table): void {
                $table->string('speed_mode', 16)->nullable()->after('speed_mbps');
            });
        }
    }

    private static function migrationV8(): void
    {
        if (!Capsule::schema()->hasTable('mod_dcmanage_server_traffic_sensors')) {
            Capsule::schema()->create('mod_dcmanage_server_traffic_sensors', static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('server_id')->index();
                $table->unsignedInteger('prtg_id')->nullable()->index();
                $table->string('sensor_id', 64)->index();
                $table->string('sensor_name', 191)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->unique(['server_id', 'sensor_id'], 'mod_dcmanage_server_sensor_unique');
            });
        }
    }

    private static function migrationV9(): void
    {
        if (Capsule::schema()->hasTable('mod_dcmanage_server_traffic_sensors') && !Capsule::schema()->hasColumn('mod_dcmanage_server_traffic_sensors', 'alert_action')) {
            Capsule::schema()->table('mod_dcmanage_server_traffic_sensors', static function (Blueprint $table): void {
                $table->string('alert_action', 16)->default('none')->after('sensor_name');
            });
        }
    }

    private static function migrationV10(): void
    {
        if (Capsule::schema()->hasTable('mod_dcmanage_prtg_instances') && !Capsule::schema()->hasColumn('mod_dcmanage_prtg_instances', 'type')) {
            Capsule::schema()->table('mod_dcmanage_prtg_instances', static function (Blueprint $table): void {
                $table->string('type', 32)->default('prtg')->after('name')->index();
            });
        }

        if (Capsule::schema()->hasTable('mod_dcmanage_prtg_instances') && !Capsule::schema()->hasColumn('mod_dcmanage_prtg_instances', 'auth_mode')) {
            Capsule::schema()->table('mod_dcmanage_prtg_instances', static function (Blueprint $table): void {
                $table->string('auth_mode', 32)->default('passhash')->after('user');
            });
        }
    }

    private static function migrationV11(): void
    {
        if (Capsule::schema()->hasTable('mod_dcmanage_datacenters') && !Capsule::schema()->hasColumn('mod_dcmanage_datacenters', 'traffic_calc_mode')) {
            Capsule::schema()->table('mod_dcmanage_datacenters', static function (Blueprint $table): void {
                $table->string('traffic_calc_mode', 16)->default('TOTAL')->after('location');
            });
        }

        if (Capsule::schema()->hasTable('mod_dcmanage_scope') && !Capsule::schema()->hasColumn('mod_dcmanage_scope', 'down_limit_gb')) {
            Capsule::schema()->table('mod_dcmanage_scope', static function (Blueprint $table): void {
                $table->decimal('down_limit_gb', 12, 3)->nullable()->after('default_action');
                $table->decimal('up_limit_gb', 12, 3)->nullable()->after('down_limit_gb');
                $table->decimal('total_limit_gb', 12, 3)->nullable()->after('up_limit_gb');
                $table->boolean('down_unlimited')->default(false)->after('total_limit_gb');
                $table->boolean('up_unlimited')->default(false)->after('down_unlimited');
                $table->boolean('total_unlimited')->default(false)->after('up_unlimited');
            });
        }
    }

    private static function migrationV12(): void
    {
        if (!Capsule::schema()->hasTable('mod_dcmanage_monitoring_group_map')) {
            Capsule::schema()->create('mod_dcmanage_monitoring_group_map', static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('prtg_id')->index();
                $table->unsignedInteger('switch_id')->nullable()->index();
                $table->string('purpose', 32)->default('traffic')->index();
                $table->string('probe_id', 64)->nullable();
                $table->string('group_id', 64)->nullable();
                $table->string('subgroup_id', 64)->nullable();
                $table->string('device_id', 64)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }
    }

    private static function migrationV13(): void
    {
        if (!Capsule::schema()->hasTable('mod_dcmanage_server_monitoring_links')) {
            Capsule::schema()->create('mod_dcmanage_server_monitoring_links', static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('server_id')->index();
                $table->unsignedBigInteger('map_id')->index();
                $table->string('purpose', 32)->index();
                $table->timestamp('created_at')->nullable()->index();
                $table->unique(['server_id', 'map_id'], 'mod_dcmanage_server_map_unique');
            });
        }

        if (Capsule::schema()->hasTable('mod_dcmanage_servers') && !Capsule::schema()->hasColumn('mod_dcmanage_servers', 'action_switch_id')) {
            Capsule::schema()->table('mod_dcmanage_servers', static function (Blueprint $table): void {
                $table->unsignedInteger('action_switch_id')->nullable()->after('rack_id')->index();
            });
        }

        if (Capsule::schema()->hasTable('mod_dcmanage_servers') && !Capsule::schema()->hasColumn('mod_dcmanage_servers', 'action_port_id')) {
            Capsule::schema()->table('mod_dcmanage_servers', static function (Blueprint $table): void {
                $table->unsignedInteger('action_port_id')->nullable()->after('action_switch_id')->index();
            });
        }
    }

    private static function migrationV14(): void
    {
        if (Capsule::schema()->hasTable('mod_dcmanage_server_traffic_sensors') && !Capsule::schema()->hasColumn('mod_dcmanage_server_traffic_sensors', 'sensor_type')) {
            Capsule::schema()->table('mod_dcmanage_server_traffic_sensors', static function (Blueprint $table): void {
                $table->string('sensor_type', 32)->default('traffic')->after('sensor_id')->index();
            });
        }
    }

    private static function migrationV15(): void
    {
        if (Capsule::schema()->hasTable('mod_dcmanage_usage_state')) {
            if (!Capsule::schema()->hasColumn('mod_dcmanage_usage_state', 'download_bytes')) {
                Capsule::schema()->table('mod_dcmanage_usage_state', static function (Blueprint $table): void {
                    $table->unsignedBigInteger('download_bytes')->default(0)->after('used_bytes');
                });
            }
            if (!Capsule::schema()->hasColumn('mod_dcmanage_usage_state', 'upload_bytes')) {
                Capsule::schema()->table('mod_dcmanage_usage_state', static function (Blueprint $table): void {
                    $table->unsignedBigInteger('upload_bytes')->default(0)->after('download_bytes');
                });
            }
        }
    }

    private static function migrationV16(): void
    {
        if (Capsule::schema()->hasTable('mod_dcmanage_servers') && !Capsule::schema()->hasColumn('mod_dcmanage_servers', 'start_date')) {
            Capsule::schema()->table('mod_dcmanage_servers', static function (Blueprint $table): void {
                $table->date('start_date')->nullable()->after('notes')->index();
            });
        }

        if (Capsule::schema()->hasTable('mod_dcmanage_monitoring_group_map')) {
            if (!Capsule::schema()->hasColumn('mod_dcmanage_monitoring_group_map', 'probe_name')) {
                Capsule::schema()->table('mod_dcmanage_monitoring_group_map', static function (Blueprint $table): void {
                    $table->string('probe_name', 191)->nullable()->after('probe_id');
                });
            }
            if (!Capsule::schema()->hasColumn('mod_dcmanage_monitoring_group_map', 'group_name')) {
                Capsule::schema()->table('mod_dcmanage_monitoring_group_map', static function (Blueprint $table): void {
                    $table->string('group_name', 191)->nullable()->after('group_id');
                });
            }
            if (!Capsule::schema()->hasColumn('mod_dcmanage_monitoring_group_map', 'subgroup_name')) {
                Capsule::schema()->table('mod_dcmanage_monitoring_group_map', static function (Blueprint $table): void {
                    $table->string('subgroup_name', 191)->nullable()->after('subgroup_id');
                });
            }
            if (!Capsule::schema()->hasColumn('mod_dcmanage_monitoring_group_map', 'device_name')) {
                Capsule::schema()->table('mod_dcmanage_monitoring_group_map', static function (Blueprint $table): void {
                    $table->string('device_name', 191)->nullable()->after('device_id');
                });
            }
        }
    }

    private static function migrationV17(): void
    {
        if (Capsule::schema()->hasTable('mod_dcmanage_monitoring_group_map')) {
            if (!Capsule::schema()->hasColumn('mod_dcmanage_monitoring_group_map', 'subgroup2_id')) {
                Capsule::schema()->table('mod_dcmanage_monitoring_group_map', static function (Blueprint $table): void {
                    $table->string('subgroup2_id', 64)->nullable()->after('subgroup_name');
                    $table->string('subgroup2_name', 191)->nullable()->after('subgroup2_id');
                });
            }
        }
    }

    private static function migrationV18(): void
    {
        if (Capsule::schema()->hasTable('mod_dcmanage_switch_ports')) {
            if (!Capsule::schema()->hasColumn('mod_dcmanage_switch_ports', 'is_locked')) {
                Capsule::schema()->table('mod_dcmanage_switch_ports', static function (Blueprint $table): void {
                    $table->boolean('is_locked')->default(0)->after('notes');
                });
            }
        }
    }
    private static function migrationV19(): void
    {
        if (Capsule::schema()->hasTable('mod_dcmanage_servers')) {
            if (!Capsule::schema()->hasColumn('mod_dcmanage_servers', 'ilo_host')) {
                Capsule::schema()->table('mod_dcmanage_servers', static function (Blueprint $table): void {
                    $table->string('ilo_host', 191)->nullable()->after('ilo_id');
                });
            }
        }
    }
}
