<?php

declare(strict_types=1);

namespace DCManage\Integrations;

use DCManage\Support\Crypto;
use Illuminate\Database\Capsule\Manager as Capsule;

final class PrtgClient
{
    private string $baseUrl;
    private string $user;
    private string $passhash;
    private bool $verifySsl;
    private string $authMode;

    public function __construct(string $baseUrl, string $user, string $passhash, bool $verifySsl = true, string $authMode = 'passhash')
    {
        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
        $this->user = $user;
        $this->passhash = $this->normalizeSecret($passhash);
        $this->verifySsl = $verifySsl;
        $this->authMode = in_array($authMode, ['passhash', 'api_token'], true) ? $authMode : 'passhash';
    }

    public static function fromDb(int $prtgId): self
    {
        $row = Capsule::table('mod_dcmanage_prtg_instances')->where('id', $prtgId)->first();
        if ($row === null) {
            throw new \RuntimeException('PRTG instance not found: ' . $prtgId);
        }

        return new self(
            (string) $row->base_url,
            (string) $row->user,
            Crypto::decrypt((string) $row->passhash_enc),
            (bool) $row->verify_ssl,
            (string) ($row->auth_mode ?? 'passhash')
        );
    }

    public function testConnection(): array
    {
        $json = $this->get('/api/table.json', [
            'content' => 'probes',
            'output' => 'json',
            'columns' => 'objid,probe',
            'count' => 1,
        ]);

        return [
            'ok' => true,
            'probes_count' => isset($json['probes']) && is_array($json['probes']) ? count($json['probes']) : 0,
            'raw' => $json,
        ];
    }

    public function getTrafficCounters(string $sensorId): array
    {
        $json = $this->get('/api/getsensordetails.json', ['id' => $sensorId]);

        $lastValue = (string) ($json['lastvalue_raw'] ?? '');
        if ($lastValue === '') {
            return ['in' => 0, 'out' => 0];
        }

        $parts = array_map('trim', explode('|', $lastValue));
        return [
            'in' => isset($parts[0]) ? (int) $parts[0] : 0,
            'out' => isset($parts[1]) ? (int) $parts[1] : 0,
        ];
    }

    public function getHistoricData(string $sensorId, string $avg = '300', string $from = '-7d', string $to = 'now'): array
    {
        return $this->get('/api/historicdata.json', [
            'id' => $sensorId,
            'avg' => $avg,
            'sdate' => $from,
            'edate' => $to,
        ]);
    }

    public function listSensors(int $limit = 200, string $query = ''): array
    {
        $limit = max(20, min(1000, $limit));
        $json = $this->get('/api/table.json', [
            'content' => 'sensors',
            'output' => 'json',
            'columns' => 'objid,sensor,device,group,status,lastvalue',
            'count' => $limit,
        ]);

        $rows = $json['sensors'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $q = strtolower(trim($query));
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = trim((string) ($row['objid'] ?? $row['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $name = trim((string) ($row['sensor'] ?? $row['name'] ?? $id));
            $device = trim((string) ($row['device'] ?? ''));
            $group = trim((string) ($row['group'] ?? ''));
            $status = trim((string) ($row['status'] ?? ''));
            $lastValue = trim((string) ($row['lastvalue'] ?? ''));

            if ($q !== '') {
                $haystack = strtolower($id . ' ' . $name . ' ' . $device . ' ' . $group . ' ' . $status);
                if (strpos($haystack, $q) === false) {
                    continue;
                }
            }

            $items[] = [
                'id' => $id,
                'name' => $name,
                'device' => $device,
                'group' => $group,
                'status' => $status,
                'lastvalue' => $lastValue,
            ];
        }

        return $items;
    }

    public function listProbes(int $limit = 200): array
    {
        $json = $this->get('/api/table.json', [
            'content' => 'probes',
            'output' => 'json',
            'columns' => 'objid,probe,status',
            'count' => max(20, min(1000, $limit)),
        ]);

        return $this->normalizeTableRows($json['probes'] ?? [], 'probe');
    }

    public function listGroups(int $parentId, int $limit = 300): array
    {
        $json = $this->get('/api/table.json', [
            'content' => 'groups',
            'output' => 'json',
            'columns' => 'objid,group,parentid,status',
            'count' => max(20, min(1000, $limit)),
            'filter_parentid' => $parentId,
        ]);

        return $this->normalizeTableRows($json['groups'] ?? [], 'group');
    }

    public function listDevices(int $parentId, int $limit = 300): array
    {
        $json = $this->get('/api/table.json', [
            'content' => 'devices',
            'output' => 'json',
            'columns' => 'objid,device,group,parentid,status,host',
            'count' => max(20, min(1000, $limit)),
            'filter_parentid' => $parentId,
        ]);

        return $this->normalizeTableRows($json['devices'] ?? [], 'device', 'host');
    }

    public function listDeviceSensors(int $deviceId, int $limit = 400, string $query = ''): array
    {
        $limit = max(20, min(1000, $limit));
        $json = $this->get('/api/table.json', [
            'content' => 'sensors',
            'output' => 'json',
            'columns' => 'objid,sensor,device,group,status,lastvalue,parentid',
            'count' => $limit,
            'filter_parentid' => $deviceId,
        ]);

        $rows = $json['sensors'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $q = strtolower(trim($query));
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = trim((string) ($row['objid'] ?? $row['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $name = trim((string) ($row['sensor'] ?? $row['name'] ?? $id));
            $device = trim((string) ($row['device'] ?? ''));
            $group = trim((string) ($row['group'] ?? ''));
            $status = trim((string) ($row['status'] ?? ''));
            $lastValue = trim((string) ($row['lastvalue'] ?? ''));

            if ($q !== '') {
                $haystack = strtolower($id . ' ' . $name . ' ' . $device . ' ' . $group . ' ' . $status);
                if (strpos($haystack, $q) === false) {
                    continue;
                }
            }

            $items[] = [
                'id' => $id,
                'name' => $name,
                'device' => $device,
                'group' => $group,
                'status' => $status,
                'lastvalue' => $lastValue,
            ];
        }

        return $items;
    }

    private function get(string $path, array $query): array
    {
        $errors = [];
        foreach ($this->authCandidates() as $auth) {
            $fullQuery = array_merge($query, $auth);
            $url = $this->baseUrl . $path . '?' . http_build_query($fullQuery);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 45);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);

            $body = curl_exec($ch);
            if ($body === false) {
                $errors[] = 'PRTG request failed: ' . curl_error($ch);
                curl_close($ch);
                continue;
            }

            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($statusCode >= 400) {
                $errors[] = 'PRTG HTTP ' . $statusCode;
                continue;
            }

            $json = json_decode($body, true);
            if (!is_array($json)) {
                $errors[] = 'PRTG returned invalid JSON';
                continue;
            }

            return $json;
        }

        throw new \RuntimeException($errors !== [] ? (string) end($errors) : 'PRTG request failed');
    }

    private function authCandidates(): array
    {
        $secret = trim($this->passhash);
        $user = trim($this->user);

        if ($this->authMode === 'api_token') {
            return [
                array_filter(['apitoken' => $secret, 'username' => $user], static fn($v) => $v !== ''),
                array_filter(['username' => $user, 'passhash' => $secret], static fn($v) => $v !== ''),
                array_filter(['username' => $user, 'password' => $secret], static fn($v) => $v !== ''),
                ['apitoken' => $secret],
            ];
        }

        return [
            array_filter(['username' => $user, 'passhash' => $secret], static fn($v) => $v !== ''),
            array_filter(['username' => $user, 'password' => $secret], static fn($v) => $v !== ''),
            array_filter(['username' => $user, 'apitoken' => $secret], static fn($v) => $v !== ''),
            ['apitoken' => $secret],
        ];
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            return '';
        }

        $baseUrl = preg_replace('#/index\.htm(l)?$#i', '', $baseUrl) ?? $baseUrl;
        $baseUrl = preg_replace('#/home$#i', '', $baseUrl) ?? $baseUrl;
        $baseUrl = rtrim($baseUrl, '/');

        return $baseUrl;
    }

    private function normalizeSecret(string $secret): string
    {
        $secret = trim($secret);
        if ($secret === '') {
            return '';
        }

        if (stripos($secret, 'apitoken=') === 0) {
            return trim(substr($secret, strlen('apitoken=')));
        }
        if (stripos($secret, 'passhash=') === 0) {
            return trim(substr($secret, strlen('passhash=')));
        }

        return $secret;
    }

    /**
     * @param mixed $rows
     */
    private function normalizeTableRows($rows, string $nameKey, ?string $extraKey = null): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['objid'] ?? $row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $items[] = [
                'id' => $id,
                'name' => trim((string) ($row[$nameKey] ?? $row['name'] ?? $id)),
                'status' => trim((string) ($row['status'] ?? '')),
                'parent_id' => (int) ($row['parentid'] ?? 0),
                'extra' => $extraKey !== null ? trim((string) ($row[$extraKey] ?? '')) : '',
            ];
        }

        return $items;
    }
}
