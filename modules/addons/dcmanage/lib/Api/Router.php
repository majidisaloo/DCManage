<?php

declare(strict_types=1);

namespace DCManage\Api;

use DCManage\Integrations\PrtgClient;
use DCManage\Support\UpdateManager;
use Illuminate\Database\Capsule\Manager as Capsule;

final class Router
{
    private const MARKER_START = 'DCMANAGE_JSON_START';
    private const MARKER_END = 'DCMANAGE_JSON_END';

    public static function dispatch(string $endpoint): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $endpoint = trim($endpoint, '/');

            switch ($endpoint) {
                case 'dashboard/health':
                    $data = self::dashboardHealth();
                    break;
                case 'dashboard/version':
                    $data = self::dashboardVersion();
                    break;
                case 'dashboard/cron':
                    $data = self::dashboardCron();
                    break;
                case 'update/check':
                    $data = self::updateCheck();
                    break;
                case 'update/apply':
                    $data = self::updateApply();
                    break;
                case 'update/set-auto':
                    $data = self::updateSetAuto();
                    break;
                case 'datacenters/list':
                    $data = self::datacenterList();
                    break;
                case 'traffic/list':
                    $data = self::trafficList();
                    break;
                case 'graphs/get':
                    $data = self::graphGet();
                    break;
                case 'prtg/sensors':
                    $data = self::prtgSensors();
                    break;
                case 'switch/ports':
                    $data = self::switchPorts();
                    break;
                default:
                    throw new \RuntimeException('Endpoint not found: ' . $endpoint);
            }

            self::emit(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            http_response_code(400);
            self::emit(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private static function emit(array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '{"ok":false,"error":"json_encode_failed"}';
        }

        echo self::MARKER_START . "\n" . $json . "\n" . self::MARKER_END;
    }

    private static function dashboardHealth(): array
    {
        return [
            'counts' => [
                'datacenters' => Capsule::table('mod_dcmanage_datacenters')->count(),
                'racks' => Capsule::table('mod_dcmanage_racks')->count(),
                'switches' => Capsule::table('mod_dcmanage_switches')->count(),
                'servers' => Capsule::table('mod_dcmanage_servers')->count(),
                'ports' => self::totalPortCount(),
                'jobs_pending' => Capsule::table('mod_dcmanage_jobs')->where('status', 'pending')->count(),
                'usage_breaches_today' => Capsule::table('mod_dcmanage_usage_state')->where('last_status', 'blocked')->count(),
            ],
            'integration_health' => [
                'prtg_instances' => Capsule::table('mod_dcmanage_prtg_instances')->count(),
                'ilos' => Capsule::table('mod_dcmanage_ilos')->count(),
            ],
            'last_cron_runs' => self::lastCronRuns(),
        ];
    }

    private static function dashboardVersion(): array
    {
        return UpdateManager::checkLatestStatus();
    }

    private static function dashboardCron(): array
    {
        return self::cronStatusData();
    }

    private static function updateCheck(): array
    {
        return UpdateManager::checkLatestStatus();
    }

    private static function updateApply(): array
    {
        $force = (int) ($_GET['force'] ?? 0) === 1;
        return UpdateManager::applyLatestIfNewer($force);
    }

    private static function updateSetAuto(): array
    {
        $enabled = (int) ($_GET['enabled'] ?? 0) === 1;
        UpdateManager::setAutoEnabled($enabled);

        return ['auto_update' => UpdateManager::isAutoEnabled()];
    }

    private static function datacenterList(): array
    {
        return Capsule::table('mod_dcmanage_datacenters')
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get(['id', 'name', 'code', 'location', 'created_at'])
            ->toArray();
    }

    private static function trafficList(): array
    {
        return Capsule::table('mod_dcmanage_usage_state as us')
            ->leftJoin('tblhosting as h', 'h.id', '=', 'us.whmcs_serviceid')
            ->select([
                'us.whmcs_serviceid',
                'h.domainstatus',
                'us.used_bytes',
                'us.base_quota_gb',
                'us.extra_quota_gb',
                'us.cycle_start',
                'us.cycle_end',
                'us.last_status',
                'us.last_sample_at',
            ])
            ->orderBy('us.updated_at', 'desc')
            ->limit(200)
            ->get()
            ->map(static function ($row) {
                $allowedBytes = (int) round(((float) $row->base_quota_gb + (float) $row->extra_quota_gb) * 1073741824);
                $remaining = $allowedBytes - (int) $row->used_bytes;

                return [
                    'service_id' => (int) $row->whmcs_serviceid,
                    'status' => (string) $row->last_status,
                    'domain_status' => (string) ($row->domainstatus ?? 'unknown'),
                    'used_bytes' => (int) $row->used_bytes,
                    'allowed_bytes' => $allowedBytes,
                    'remaining_bytes' => $remaining,
                    'cycle_start' => (string) $row->cycle_start,
                    'cycle_end' => (string) $row->cycle_end,
                    'last_sample_at' => (string) $row->last_sample_at,
                ];
            })
            ->toArray();
    }

    private static function graphGet(): array
    {
        $serviceId = (int) ($_GET['service_id'] ?? 0);
        if ($serviceId <= 0) {
            throw new \InvalidArgumentException('service_id is required');
        }

        $from = (string) ($_GET['from'] ?? '-7d');
        $to = (string) ($_GET['to'] ?? 'now');
        $avg = (string) ($_GET['avg'] ?? '300');

        $cacheKey = hash('sha256', $serviceId . '|' . $from . '|' . $to . '|' . $avg);
        $cached = Capsule::table('mod_dcmanage_graph_cache')
            ->where('whmcs_serviceid', $serviceId)
            ->where('payload_hash', $cacheKey)
            ->where('expires_at', '>=', date('Y-m-d H:i:s'))
            ->orderBy('id', 'desc')
            ->first();

        if ($cached !== null) {
            return [
                'cached' => true,
                'payload' => json_decode((string) $cached->json_data, true),
            ];
        }

        $link = Capsule::table('mod_dcmanage_service_link')->where('whmcs_serviceid', $serviceId)->first();
        if ($link === null || empty($link->prtg_id) || empty($link->prtg_sensor_id)) {
            throw new \RuntimeException('Service has no PRTG mapping');
        }

        $client = PrtgClient::fromDb((int) $link->prtg_id);
        $payload = $client->getHistoricData((string) $link->prtg_sensor_id, $avg, $from, $to);

        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + 1800);

        Capsule::table('mod_dcmanage_graph_cache')->insert([
            'whmcs_serviceid' => $serviceId,
            'range_start' => date('Y-m-d H:i:s', strtotime($from, time())),
            'range_end' => date('Y-m-d H:i:s', strtotime($to, time())),
            'source' => 'prtg',
            'payload_hash' => $cacheKey,
            'json_data' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'cached_at' => $now,
            'expires_at' => $expires,
        ]);

        return [
            'cached' => false,
            'payload' => $payload,
        ];
    }

    private static function prtgSensors(): array
    {
        $prtgId = (int) ($_GET['prtg_id'] ?? 0);
        if ($prtgId <= 0) {
            throw new \InvalidArgumentException('prtg_id is required');
        }

        $limit = max(20, min(500, (int) ($_GET['limit'] ?? 200)));
        $query = trim((string) ($_GET['q'] ?? ''));

        $client = PrtgClient::fromDb($prtgId);
        $items = $client->listSensors($limit, $query);

        return [
            'items' => $items,
            'count' => count($items),
        ];
    }

    private static function switchPorts(): array
    {
        $switchId = (int) ($_GET['switch_id'] ?? 0);
        if ($switchId <= 0) {
            throw new \InvalidArgumentException('switch_id is required');
        }

        $dcId = (int) ($_GET['dc_id'] ?? 0);
        $switchQuery = Capsule::table('mod_dcmanage_switches')->where('id', $switchId);
        if ($dcId > 0) {
            $switchQuery->where('dc_id', $dcId);
        }
        $switch = $switchQuery->first(['id', 'dc_id']);
        if ($switch === null) {
            throw new \RuntimeException('Switch not found for selected datacenter');
        }

        $rows = Capsule::table('mod_dcmanage_switch_ports')
            ->where('switch_id', $switchId)
            ->orderBy('if_name')
            ->get(['id', 'if_name', 'if_desc', 'vlan', 'admin_status', 'oper_status'])
            ->toArray();

        return [
            'items' => $rows,
            'count' => count($rows),
        ];
    }

    private static function cronStatusData(): array
    {
        $tasks = [
            ['task' => 'poll_usage', 'interval' => 300],
            ['task' => 'enforce_queue', 'interval' => 60],
            ['task' => 'graph_warm', 'interval' => 1800],
            ['task' => 'cleanup', 'interval' => 86400],
            ['task' => 'switch_discovery', 'interval' => 300],
            ['task' => 'self_update', 'interval' => 86400],
        ];

        $result = [];
        $ok = 0;
        $fail = 0;

        foreach ($tasks as $item) {
            $task = $item['task'];
            $interval = $item['interval'];
            $last = Capsule::table('mod_dcmanage_logs')
                ->where('source', 'cron')
                ->where('message', 'like', 'task:' . $task . ' %')
                ->orderBy('id', 'desc')
                ->first(['message', 'created_at']);

            $status = 'fail';
            $lastAt = null;
            $nextAt = null;

            if ($last !== null) {
                $lastAt = (string) $last->created_at;
                $lastTs = strtotime($lastAt) ?: null;
                if ($lastTs !== null) {
                    $nextAt = date('Y-m-d H:i:s', $lastTs + $interval);
                    $age = time() - $lastTs;
                    if (strpos((string) $last->message, 'completed') !== false && $age <= ($interval * 2)) {
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

            $result[] = [
                'task' => $task,
                'status' => $status,
                'last_run' => $lastAt,
                'next_run' => $nextAt,
            ];
        }

        $overall = 'warning';
        if ($ok === count($tasks)) {
            $overall = 'ok';
        } elseif ($fail === count($tasks)) {
            $overall = 'fail';
        }

        return [
            'overall' => $overall,
            'items' => $result,
        ];
    }

    private static function lastCronRuns(): array
    {
        $rows = Capsule::table('mod_dcmanage_logs')
            ->where('source', 'cron')
            ->where('message', 'like', 'task:%')
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get(['message', 'created_at']);

        $out = [];
        foreach ($rows as $row) {
            $out[] = ['message' => (string) $row->message, 'at' => (string) $row->created_at];
        }

        return $out;
    }

    private static function totalPortCount(): int
    {
        $total = 0;

        try {
            if (Capsule::schema()->hasTable('mod_dcmanage_server_ports')) {
                $total += (int) Capsule::table('mod_dcmanage_server_ports')->count();
            }
            if (Capsule::schema()->hasTable('mod_dcmanage_switch_ports')) {
                $total += (int) Capsule::table('mod_dcmanage_switch_ports')->count();
            }
        } catch (\Throwable $e) {
            return $total;
        }

        return $total;
    }
}
