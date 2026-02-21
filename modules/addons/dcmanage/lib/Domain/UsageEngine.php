<?php

declare(strict_types=1);

namespace DCManage\Domain;

use DCManage\Integrations\PrtgClient;
use DCManage\Jobs\JobQueue;
use DCManage\Support\Logger;
use Illuminate\Database\Capsule\Manager as Capsule;

final class UsageEngine
{
    private const GB = 1073741824;

    public function pollActiveServices(): int
    {
        $rows = Capsule::table('mod_dcmanage_service_link as sl')
            ->join('tblhosting as h', 'h.id', '=', 'sl.whmcs_serviceid')
            ->leftJoin('mod_dcmanage_usage_state as us', 'us.whmcs_serviceid', '=', 'sl.whmcs_serviceid')
            ->select([
                'sl.whmcs_serviceid',
                'sl.prtg_id',
                'sl.prtg_sensor_id',
                'h.userid',
                'h.nextduedate',
                'h.billingcycle',
                'h.packageid',
                'us.mode',
                'us.action',
                'us.base_quota_gb',
                'us.extra_quota_gb',
                'us.used_bytes',
                'us.last_in_octets',
                'us.last_out_octets',
                'us.cycle_start',
                'us.cycle_end',
            ])
            ->where('h.domainstatus', 'Active')
            ->get();

        $processed = 0;

        foreach ($rows as $row) {
            if (empty($row->prtg_id) || empty($row->prtg_sensor_id)) {
                continue;
            }
            $this->pollService((array) $row);
            $processed++;
        }

        return $processed;
    }

    private function pollService(array $service): void
    {
        $serviceId = (int) $service['whmcs_serviceid'];

        $state = Capsule::table('mod_dcmanage_usage_state')->where('whmcs_serviceid', $serviceId)->first();
        if ($state === null) {
            Capsule::table('mod_dcmanage_usage_state')->insert([
                'whmcs_serviceid' => $serviceId,
                'mode' => 'TOTAL',
                'action' => 'BLOCK',
                'base_quota_gb' => 0,
                'extra_quota_gb' => 0,
                'used_bytes' => 0,
                'last_status' => 'ok',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $state = Capsule::table('mod_dcmanage_usage_state')->where('whmcs_serviceid', $serviceId)->first();
        }

        [$cycleStart, $cycleEnd] = CycleResolver::resolve((string) $service['nextduedate'], (string) $service['billingcycle']);
        $stateCycleEnd = isset($state->cycle_end) ? strtotime((string) $state->cycle_end) : null;

        $usedBytes = (int) ($state->used_bytes ?? 0);
        $lastIn = isset($state->last_in_octets) ? (int) $state->last_in_octets : null;
        $lastOut = isset($state->last_out_octets) ? (int) $state->last_out_octets : null;

        if ($stateCycleEnd === null || $stateCycleEnd !== $cycleEnd->getTimestamp()) {
            $usedBytes = 0;
            $lastIn = null;
            $lastOut = null;
            Logger::info('usage', 'Cycle reset detected', ['service_id' => $serviceId]);
        }

        $client = PrtgClient::fromDb((int) $service['prtg_id']);
        $counters = $client->getTrafficCounters((string) $service['prtg_sensor_id']);

        $currentIn = (int) ($counters['in'] ?? 0);
        $currentOut = (int) ($counters['out'] ?? 0);

        $deltaIn = $this->computeDelta($lastIn, $currentIn);
        $deltaOut = $this->computeDelta($lastOut, $currentOut);

        $mode = strtoupper((string) ($state->mode ?? 'TOTAL'));
        if ($mode === 'IN') {
            $delta = $deltaIn;
        } elseif ($mode === 'OUT') {
            $delta = $deltaOut;
        } else {
            $delta = $deltaIn + $deltaOut;
        }

        $usedBytes += $delta;

        $allowedBytes = $this->allowedBytes($serviceId, (float) ($state->base_quota_gb ?? 0), $cycleStart->format('Y-m-d H:i:s'), $cycleEnd->format('Y-m-d H:i:s'));
        $remaining = $allowedBytes - $usedBytes;
        $status = $remaining <= 0 ? 'blocked' : 'ok';

        Capsule::table('mod_dcmanage_usage_state')->where('whmcs_serviceid', $serviceId)->update([
            'cycle_start' => $cycleStart->format('Y-m-d H:i:s'),
            'cycle_end' => $cycleEnd->format('Y-m-d H:i:s'),
            'used_bytes' => max(0, $usedBytes),
            'last_in_octets' => $currentIn,
            'last_out_octets' => $currentOut,
            'last_sample_at' => date('Y-m-d H:i:s'),
            'last_status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($status === 'blocked') {
            JobQueue::enqueue('enforce', ['service_id' => $serviceId, 'reason' => 'quota_breach']);
        }
    }

    private function computeDelta(?int $last, int $current): int
    {
        if ($last === null) {
            return 0;
        }

        if ($current < $last) {
            return 0;
        }

        return $current - $last;
    }

    private function allowedBytes(int $serviceId, float $baseQuotaGb, string $cycleStart, string $cycleEnd): int
    {
        $extraGb = (float) Capsule::table('mod_dcmanage_purchases')
            ->where('whmcs_serviceid', $serviceId)
            ->where('cycle_start', '>=', $cycleStart)
            ->where('cycle_end', '<=', $cycleEnd)
            ->sum('size_gb');

        $totalGb = max(0, $baseQuotaGb + $extraGb);
        return (int) round($totalGb * self::GB);
    }
}
