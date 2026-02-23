<?php

declare(strict_types=1);

namespace DCManage\Api;

use DCManage\Integrations\PrtgClient;
use DCManage\Jobs\JobQueue;
use DCManage\Support\Crypto;
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
                case 'update/status':
                    $data = self::updateStatus();
                    break;
                case 'update/cancel':
                    $data = self::updateCancel();
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
                case 'prtg/probes':
                    $data = self::prtgProbes();
                    break;
                case 'prtg/groups':
                    $data = self::prtgGroups();
                    break;
                case 'prtg/devices':
                    $data = self::prtgDevices();
                    break;
                case 'prtg/device-sensors':
                    $data = self::prtgDeviceSensors();
                    break;
                case 'monitoring/discover':
                    $data = self::monitoringDiscover();
                    break;
                case 'ilo/test':
                    $data = self::iloTest();
                    break;
                case 'switch/ports':
                    $data = self::switchPorts();
                    break;
                case 'switch/port-action':
                    $data = self::switchPortAction();
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
        $result = UpdateManager::queueApplyLatest($force);
        if (($result['status'] ?? '') === 'queued') {
            try {
                JobQueue::runBatch(1);
            } catch (\Throwable $e) {
                // Keep queued status for cron fallback when immediate execution fails.
            }
        }

        return $result;
    }

    private static function updateStatus(): array
    {
        return UpdateManager::getUpdateRuntimeStatus();
    }

    private static function updateCancel(): array
    {
        return UpdateManager::cancelQueuedUpdate();
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

        $link = Capsule::table('mod_dcmanage_service_link')->where('whmcs_serviceid', $serviceId)->first();
        if ($link === null || empty($link->prtg_id) || empty($link->prtg_sensor_id)) {
            throw new \RuntimeException('Service has no PRTG mapping');
        }

        $client = PrtgClient::fromDb((int) $link->prtg_id);
        $payload = $client->getHistoricData((string) $link->prtg_sensor_id, $avg, $from, $to);

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

    private static function prtgProbes(): array
    {
        $prtgId = (int) ($_GET['prtg_id'] ?? 0);
        if ($prtgId <= 0) {
            throw new \InvalidArgumentException('prtg_id is required');
        }

        $client = PrtgClient::fromDb($prtgId);
        $items = $client->listProbes();

        return ['items' => $items, 'count' => count($items)];
    }

    private static function prtgGroups(): array
    {
        $prtgId = (int) ($_GET['prtg_id'] ?? 0);
        $parentId = (int) ($_GET['parent_id'] ?? 0);
        if ($prtgId <= 0 || $parentId <= 0) {
            throw new \InvalidArgumentException('prtg_id and parent_id are required');
        }

        $client = PrtgClient::fromDb($prtgId);
        $items = $client->listGroups($parentId);

        return ['items' => $items, 'count' => count($items)];
    }

    private static function prtgDevices(): array
    {
        $prtgId = (int) ($_GET['prtg_id'] ?? 0);
        $parentId = (int) ($_GET['parent_id'] ?? 0);
        if ($prtgId <= 0 || $parentId <= 0) {
            throw new \InvalidArgumentException('prtg_id and parent_id are required');
        }

        $client = PrtgClient::fromDb($prtgId);
        $items = $client->listDevices($parentId);

        return ['items' => $items, 'count' => count($items)];
    }

    private static function prtgDeviceSensors(): array
    {
        $prtgId = (int) ($_GET['prtg_id'] ?? 0);
        $deviceId = (int) ($_GET['device_id'] ?? 0);
        $query = trim((string) ($_GET['q'] ?? ''));
        $limit = max(20, min(500, (int) ($_GET['limit'] ?? 250)));

        if ($prtgId <= 0 || $deviceId <= 0) {
            throw new \InvalidArgumentException('prtg_id and device_id are required');
        }

        $client = PrtgClient::fromDb($prtgId);
        $items = $client->listDeviceSensors($deviceId, $limit, $query);

        return ['items' => $items, 'count' => count($items)];
    }

    private static function monitoringDiscover(): array
    {
        $host = trim((string) ($_GET['host'] ?? ''));
        if ($host === '') {
            throw new \InvalidArgumentException('host is required');
        }

        $portsInput = trim((string) ($_GET['ports'] ?? '22,80,443,3389,8080,8443'));
        $ports = [];
        foreach (preg_split('/[\s,;]+/', $portsInput) ?: [] as $port) {
            $value = (int) $port;
            if ($value > 0 && $value <= 65535) {
                $ports[$value] = $value;
            }
        }
        if ($ports === []) {
            $ports = [22 => 22, 80 => 80, 443 => 443];
        }

        $resolved = gethostbyname($host);
        $resolvedIp = $resolved !== $host ? $resolved : '';
        $results = [];
        foreach ($ports as $port) {
            $start = microtime(true);
            $conn = @fsockopen($host, $port, $errno, $errstr, 2.0);
            $latency = (int) round((microtime(true) - $start) * 1000);
            $open = is_resource($conn);
            if ($open) {
                fclose($conn);
            }
            $results[] = [
                'port' => $port,
                'open' => $open,
                'latency_ms' => $latency,
                'error' => $open ? '' : ($errstr !== '' ? $errstr : ('errno:' . $errno)),
            ];
        }

        return [
            'host' => $host,
            'resolved_ip' => $resolvedIp,
            'checked_ports' => count($results),
            'ports' => $results,
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

    private static function switchPortAction(): array
    {
        $portId = (int) ($_GET['port_id'] ?? 0);
        $action = strtolower(trim((string) ($_GET['action'] ?? 'check')));
        if ($portId <= 0) {
            throw new \InvalidArgumentException('port_id is required');
        }
        if (!in_array($action, ['check', 'shut', 'noshut'], true)) {
            throw new \InvalidArgumentException('Invalid action');
        }

        $port = Capsule::table('mod_dcmanage_switch_ports')->where('id', $portId)->first();
        if ($port === null) {
            throw new \RuntimeException('Port not found');
        }

        $payload = [];
        if ($action === 'check') {
            $payload = [
                'oper_status' => strtolower(trim((string) ($port->oper_status ?? 'unknown'))) === 'up' ? 'up' : 'down',
                'last_seen' => date('Y-m-d H:i:s'),
            ];
        } elseif ($action === 'shut') {
            $payload = [
                'admin_status' => 'down',
                'oper_status' => 'down',
                'last_seen' => date('Y-m-d H:i:s'),
            ];
        } elseif ($action === 'noshut') {
            $payload = [
                'admin_status' => 'up',
                'oper_status' => strtolower(trim((string) ($port->oper_status ?? ''))) === 'down' ? 'unknown' : (string) ($port->oper_status ?? 'unknown'),
                'last_seen' => date('Y-m-d H:i:s'),
            ];
        }

        Capsule::table('mod_dcmanage_switch_ports')->where('id', $portId)->update($payload);
        $updated = Capsule::table('mod_dcmanage_switch_ports')->where('id', $portId)->first(['id', 'admin_status', 'oper_status', 'last_seen']);

        return [
            'id' => (int) $updated->id,
            'admin_status' => (string) ($updated->admin_status ?? 'unknown'),
            'oper_status' => (string) ($updated->oper_status ?? 'unknown'),
            'last_seen' => (string) ($updated->last_seen ?? ''),
            'action' => $action,
        ];
    }

    private static function iloTest(): array
    {
        $serverId = (int) ($_GET['server_id'] ?? 0);
        $host = trim((string) ($_GET['host'] ?? ''));
        $user = trim((string) ($_GET['user'] ?? ''));
        $pass = (string) ($_GET['pass'] ?? '');
        $verify = (int) ($_GET['verify_ssl'] ?? 0) === 1;

        if ($serverId > 0 && ($host === '' || $user === '' || $pass === '')) {
            $server = Capsule::table('mod_dcmanage_servers as s')
                ->leftJoin('mod_dcmanage_ilos as il', 'il.id', '=', 's.ilo_id')
                ->where('s.id', $serverId)
                ->first(['il.host as ilo_host', 'il.user as ilo_user', 'il.pass_enc as ilo_pass_enc']);
            if ($server !== null) {
                if ($host === '') {
                    $host = trim((string) ($server->ilo_host ?? ''));
                }
                if ($user === '') {
                    $user = trim((string) ($server->ilo_user ?? ''));
                }
                if ($pass === '' && trim((string) ($server->ilo_pass_enc ?? '')) !== '') {
                    $pass = Crypto::decrypt((string) $server->ilo_pass_enc);
                }
            }
        }

        if ($host === '' || $user === '' || $pass === '') {
            throw new \RuntimeException('iLO host, user and password are required');
        }

        $base = preg_match('#^https?://#i', $host) === 1 ? rtrim($host, '/') : ('https://' . rtrim($host, '/'));
        $root = self::iloCurlJson($base . '/redfish/v1/', $user, $pass, $verify);

        $members = [];
        if (isset($root['data']['Systems']['@odata.id'])) {
            $systems = self::iloCurlJson($base . (string) $root['data']['Systems']['@odata.id'], $user, $pass, $verify);
            $members = $systems['data']['Members'] ?? [];
        }

        $power = '';
        if (is_array($members) && isset($members[0]['@odata.id'])) {
            $systemInfo = self::iloCurlJson($base . (string) $members[0]['@odata.id'], $user, $pass, $verify);
            $power = trim((string) ($systemInfo['data']['PowerState'] ?? ''));
        }

        return [
            'ok' => true,
            'status_code' => (int) $root['status_code'],
            'name' => (string) ($root['data']['Name'] ?? ''),
            'redfish' => (string) ($root['data']['RedfishVersion'] ?? ''),
            'power_state' => $power,
        ];
    }

    private static function iloCurlJson(string $url, string $user, string $pass, bool $verifySsl): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        $proxyHost = trim((string) Capsule::table('mod_dcmanage_meta')->where('meta_key', 'settings.ilo_proxy_host')->value('meta_value'));
        $proxyPort = (int) Capsule::table('mod_dcmanage_meta')->where('meta_key', 'settings.ilo_proxy_port')->value('meta_value');
        $proxyType = strtolower(trim((string) Capsule::table('mod_dcmanage_meta')->where('meta_key', 'settings.ilo_proxy_type')->value('meta_value')));
        $proxyUser = trim((string) Capsule::table('mod_dcmanage_meta')->where('meta_key', 'settings.ilo_proxy_user')->value('meta_value'));
        $proxyPassEnc = trim((string) Capsule::table('mod_dcmanage_meta')->where('meta_key', 'settings.ilo_proxy_pass_enc')->value('meta_value'));
        $proxyPass = $proxyPassEnc !== '' ? Crypto::decrypt($proxyPassEnc) : '';

        if ($proxyHost !== '' && $proxyPort > 0) {
            curl_setopt($ch, CURLOPT_PROXY, $proxyHost . ':' . $proxyPort);
            if ($proxyType === 'socks5') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
            } else {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            }
            if ($proxyUser !== '') {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyUser . ':' . $proxyPass);
            }
        }

        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('iLO request failed: ' . $err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            throw new \RuntimeException('iLO HTTP ' . $status);
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('iLO returned invalid JSON');
        }

        return [
            'status_code' => $status,
            'data' => $data,
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
            $patterns = self::cronSuccessPatterns($task);
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
            $lastAt = null;
            $nextAt = null;

            if ($last !== null) {
                $lastAt = (string) $last->created_at;
                $lastTs = strtotime($lastAt) ?: null;
                if ($lastTs !== null) {
                    $nextAt = date('Y-m-d H:i:s', $lastTs + $interval);
                    $age = time() - $lastTs;
                    if ($age <= ($interval * 2)) {
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

    private static function cronSuccessPatterns(string $task): array
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
}
