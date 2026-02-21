<?php

declare(strict_types=1);

use DCManage\Domain\UsageEngine;
use DCManage\Jobs\JobQueue;
use DCManage\Support\LockManager;
use DCManage\Support\Logger;
use DCManage\Support\UpdateManager;
use Illuminate\Database\Capsule\Manager as Capsule;

$whmcsRoot = dirname(__DIR__, 3);
require_once $whmcsRoot . '/init.php';
require_once __DIR__ . '/lib/Bootstrap.php';
require_once __DIR__ . '/dcmanage.php';

$task = strtolower(trim((string) ($argv[1] ?? 'dispatcher')));
if ($task === '') {
    $task = 'dispatcher';
}

try {
    switch ($task) {
        case 'dispatcher':
            runDispatcher();
            break;
        case 'poll_usage':
            runPollUsage();
            break;
        case 'enforce_queue':
            runEnforceQueue();
            break;
        case 'graph_warm':
            runGraphWarm();
            break;
        case 'cleanup':
            runCleanup();
            break;
        case 'switch_discovery':
            runSwitchDiscovery();
            break;
        case 'self_update':
            runSelfUpdate();
            break;
        default:
            throw new RuntimeException('Unknown task: ' . $task . '. Allowed: dispatcher, poll_usage, enforce_queue, graph_warm, cleanup, switch_discovery, self_update');
    }

    Logger::info('cron', 'task:' . $task . ' completed');
    echo "OK: {$task}\n";
} catch (Throwable $e) {
    Logger::error('cron', 'task:' . $task . ' failed', ['error' => $e->getMessage()]);
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

function runDispatcher(): void
{
    if (!LockManager::acquire('cron:dispatcher', 55)) {
        return;
    }

    $tasks = [
        ['task' => 'poll_usage', 'interval' => 300],
        ['task' => 'enforce_queue', 'interval' => 60],
        ['task' => 'graph_warm', 'interval' => 1800],
        ['task' => 'cleanup', 'interval' => 86400],
        ['task' => 'switch_discovery', 'interval' => 300],
        ['task' => 'self_update', 'interval' => 86400],
    ];

    try {
        foreach ($tasks as $item) {
            $name = (string) $item['task'];
            $interval = (int) $item['interval'];
            if (!dcmanage_should_run_task($name, $interval)) {
                continue;
            }

            switch ($name) {
                case 'poll_usage':
                    runPollUsage();
                    break;
                case 'enforce_queue':
                    runEnforceQueue();
                    break;
                case 'graph_warm':
                    runGraphWarm();
                    break;
                case 'cleanup':
                    runCleanup();
                    break;
                case 'switch_discovery':
                    runSwitchDiscovery();
                    break;
                case 'self_update':
                    runSelfUpdate();
                    break;
            }
        }
    } finally {
        LockManager::release('cron:dispatcher');
    }
}

function dcmanage_should_run_task(string $task, int $interval): bool
{
    $lastOk = Capsule::table('mod_dcmanage_logs')
        ->where('source', 'cron')
        ->where('message', 'task:' . $task . ' completed')
        ->orderBy('id', 'desc')
        ->value('created_at');

    if (empty($lastOk)) {
        return true;
    }

    $lastTs = strtotime((string) $lastOk);
    if ($lastTs === false) {
        return true;
    }

    return (time() - $lastTs) >= max(60, $interval);
}

function runPollUsage(): void
{
    if (!LockManager::acquire('cron:poll_usage', 240)) {
        return;
    }

    try {
        $engine = new UsageEngine();
        $count = $engine->pollActiveServices();
        Logger::info('cron', 'poll_usage processed', ['count' => $count]);
    } finally {
        LockManager::release('cron:poll_usage');
    }
}

function runEnforceQueue(): void
{
    if (!LockManager::acquire('cron:enforce_queue', 55)) {
        return;
    }

    try {
        $done = JobQueue::runBatch(50);
        Logger::info('cron', 'enforce_queue executed', ['count' => $done]);
    } finally {
        LockManager::release('cron:enforce_queue');
    }
}

function runGraphWarm(): void
{
    if (!LockManager::acquire('cron:graph_warm', 1700)) {
        return;
    }

    try {
        // Intentionally lightweight in v0.1.0: cleanup stale short cache only.
        Capsule::table('mod_dcmanage_graph_cache')->where('expires_at', '<', date('Y-m-d H:i:s'))->delete();
        Logger::info('cron', 'graph_warm executed');
    } finally {
        LockManager::release('cron:graph_warm');
    }
}

function runCleanup(): void
{
    if (!LockManager::acquire('cron:cleanup', 1800)) {
        return;
    }

    try {
        $sixtyDaysAgo = date('Y-m-d H:i:s', time() - 60 * 86400);
        $ninetyDaysAgo = date('Y-m-d H:i:s', time() - 90 * 86400);

        Capsule::table('mod_dcmanage_graph_cache')->where('cached_at', '<', $sixtyDaysAgo)->delete();
        Capsule::table('mod_dcmanage_logs')->where('created_at', '<', $ninetyDaysAgo)->delete();
        LockManager::cleanupExpired();

        Logger::info('cron', 'cleanup executed');
    } finally {
        LockManager::release('cron:cleanup');
    }
}

function runSelfUpdate(): void
{
    if (!LockManager::acquire('cron:self_update', 3500)) {
        return;
    }

    try {
        $result = UpdateManager::autoUpdateIfNeeded();
        Logger::info('cron', 'self_update executed', $result);
    } finally {
        LockManager::release('cron:self_update');
    }
}

function runSwitchDiscovery(): void
{
    if (!LockManager::acquire('cron:switch_discovery', 290)) {
        return;
    }

    try {
        $intervalRaw = Capsule::table('mod_dcmanage_meta')->where('meta_key', 'settings.switch_discovery_minutes')->value('meta_value');
        $intervalMinutes = max(1, min(1440, (int) ($intervalRaw ?: 30)));
        $intervalSeconds = $intervalMinutes * 60;

        $lastRunRaw = Capsule::table('mod_dcmanage_meta')->where('meta_key', 'switch_discovery.last_run_at')->value('meta_value');
        $lastRunTs = $lastRunRaw ? strtotime((string) $lastRunRaw) : false;
        if ($lastRunTs !== false && (time() - $lastRunTs) < $intervalSeconds) {
            Logger::info('cron', 'switch_discovery skipped', ['interval_minutes' => $intervalMinutes]);
            return;
        }

        $switches = Capsule::table('mod_dcmanage_switches')
            ->whereNotNull('mgmt_ip')
            ->where('mgmt_ip', '!=', '')
            ->get(['id', 'mgmt_ip', 'snmp_community', 'snmp_port']);

        $ok = 0;
        $failed = 0;
        $saved = 0;

        foreach ($switches as $switch) {
            $switchId = (int) $switch->id;
            $discover = dcmanage_discover_switch_ports(
                (string) $switch->mgmt_ip,
                (string) ($switch->snmp_community ?? 'public'),
                (int) ($switch->snmp_port ?? 161)
            );

            $statePayload = [
                'ok' => !empty($discover['ok']),
                'message' => (string) ($discover['message'] ?? ''),
                'checked_at' => date('Y-m-d H:i:s'),
            ];

            Capsule::table('mod_dcmanage_meta')->updateOrInsert(
                ['meta_key' => 'switch.snmp.' . $switchId],
                ['meta_value' => json_encode($statePayload, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')]
            );

            if (empty($discover['ok'])) {
                $failed++;
                continue;
            }

            $saved += dcmanage_store_discovered_switch_ports(
                $switchId,
                is_array($discover['ports'] ?? null) ? $discover['ports'] : []
            );
            $ok++;
        }

        Capsule::table('mod_dcmanage_meta')->updateOrInsert(
            ['meta_key' => 'switch_discovery.last_run_at'],
            ['meta_value' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        );

        Logger::info('cron', 'switch_discovery executed', [
            'switches' => count($switches),
            'ok' => $ok,
            'failed' => $failed,
            'saved' => $saved,
            'interval_minutes' => $intervalMinutes,
        ]);
    } finally {
        LockManager::release('cron:switch_discovery');
    }
}
