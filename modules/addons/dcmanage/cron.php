<?php

declare(strict_types=1);

use DCManage\Domain\UsageEngine;
use DCManage\Jobs\JobQueue;
use DCManage\Support\LockManager;
use DCManage\Support\Logger;
use Illuminate\Database\Capsule\Manager as Capsule;

$whmcsRoot = dirname(__DIR__, 3);
require_once $whmcsRoot . '/init.php';
require_once __DIR__ . '/lib/Bootstrap.php';

$task = $argv[1] ?? '';
if ($task === '') {
    fwrite(STDERR, "Usage: php cron.php [poll_usage|enforce_queue|graph_warm|cleanup]\n");
    exit(1);
}

try {
    switch ($task) {
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
        default:
            throw new RuntimeException('Unknown task: ' . $task);
    }

    Logger::info('cron', 'task:' . $task . ' completed');
    echo "OK: {$task}\n";
} catch (Throwable $e) {
    Logger::error('cron', 'task:' . $task . ' failed', ['error' => $e->getMessage()]);
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
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
        // Intentionally lightweight in v1.0.0: cleanup stale short cache only.
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
