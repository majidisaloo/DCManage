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

    public function __construct(string $baseUrl, string $user, string $passhash, bool $verifySsl = true)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->user = $user;
        $this->passhash = $passhash;
        $this->verifySsl = $verifySsl;
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
            (bool) $row->verify_ssl
        );
    }

    public function testConnection(): array
    {
        return $this->get('/api/getstatus.json', []);
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

    private function get(string $path, array $query): array
    {
        $query = array_merge($query, [
            'username' => $this->user,
            'passhash' => $this->passhash,
        ]);

        $url = $this->baseUrl . $path . '?' . http_build_query($query);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('PRTG request failed: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new \RuntimeException('PRTG HTTP ' . $statusCode);
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new \RuntimeException('PRTG returned invalid JSON');
        }

        return $json;
    }
}
