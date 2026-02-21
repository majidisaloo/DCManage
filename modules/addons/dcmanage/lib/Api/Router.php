<?php

declare(strict_types=1);

namespace DCManage\Api;

use DCManage\Integrations\PrtgClient;
use Illuminate\Database\Capsule\Manager as Capsule;

final class Router
{
    public static function dispatch(string $endpoint): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $endpoint = trim($endpoint, '/');

            $data = match ($endpoint) {
                'dashboard/health' => self::dashboardHealth(),
                'datacenters/list' => self::datacenterList(),
                'traffic/list' => self::trafficList(),
                'graphs/get' => self::graphGet(),
                default => throw new \RuntimeException('Endpoint not found: ' . $endpoint),
            };

            echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    private static function dashboardHealth(): array
    {
        return [
            'counts' => [
                'datacenters' => Capsule::table('mod_dcmanage_datacenters')->count(),
                'racks' => Capsule::table('mod_dcmanage_racks')->count(),
                'servers' => Capsule::table('mod_dcmanage_servers')->count(),
                'ports' => Capsule::table('mod_dcmanage_server_ports')->count(),
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
        $rows = Capsule::table('mod_dcmanage_usage_state as us')
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

        return $rows;
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
}
