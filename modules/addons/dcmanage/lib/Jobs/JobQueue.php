<?php

declare(strict_types=1);

namespace DCManage\Jobs;

use DCManage\Support\Logger;
use DCManage\Support\UpdateManager;
use Illuminate\Database\Capsule\Manager as Capsule;

final class JobQueue
{
    public static function enqueue(string $type, array $payload, ?string $runAfter = null): int
    {
        return (int) Capsule::table('mod_dcmanage_jobs')->insertGetId([
            'type' => $type,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'attempts' => 0,
            'run_after' => $runAfter,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function runBatch(int $limit = 20): int
    {
        $jobs = Capsule::table('mod_dcmanage_jobs')
            ->where('status', 'pending')
            ->where(function ($q): void {
                $q->whereNull('run_after')->orWhere('run_after', '<=', date('Y-m-d H:i:s'));
            })
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        $done = 0;

        foreach ($jobs as $job) {
            $updated = Capsule::table('mod_dcmanage_jobs')
                ->where('id', $job->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'running',
                    'started_at' => date('Y-m-d H:i:s'),
                ]);

            if ($updated === 0) {
                continue;
            }

            try {
                self::execute((string) $job->type, json_decode((string) $job->payload_json, true) ?: []);
                Capsule::table('mod_dcmanage_jobs')->where('id', $job->id)->update([
                    'status' => 'done',
                    'finished_at' => date('Y-m-d H:i:s'),
                ]);
                $done++;
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), 'canceled') !== false) {
                    Capsule::table('mod_dcmanage_jobs')->where('id', $job->id)->update([
                        'status' => 'canceled',
                        'attempts' => ((int) $job->attempts) + 1,
                        'last_error' => $e->getMessage(),
                        'finished_at' => date('Y-m-d H:i:s'),
                    ]);
                    Logger::warning('job_queue', 'Job canceled', ['job_id' => (int) $job->id, 'error' => $e->getMessage()]);
                    continue;
                }

                if ((string) $job->type === 'update_apply') {
                    Capsule::table('mod_dcmanage_jobs')->where('id', $job->id)->update([
                        'status' => 'failed',
                        'attempts' => ((int) $job->attempts) + 1,
                        'last_error' => $e->getMessage(),
                        'finished_at' => date('Y-m-d H:i:s'),
                    ]);
                    Logger::error('job_queue', 'Update job failed (no auto-retry)', ['job_id' => (int) $job->id, 'error' => $e->getMessage()]);
                    continue;
                }

                $attempts = ((int) $job->attempts) + 1;
                $nextRun = date('Y-m-d H:i:s', time() + min(300, 20 * $attempts));

                Capsule::table('mod_dcmanage_jobs')->where('id', $job->id)->update([
                    'status' => $attempts >= 5 ? 'failed' : 'pending',
                    'attempts' => $attempts,
                    'last_error' => $e->getMessage(),
                    'run_after' => $attempts >= 5 ? null : $nextRun,
                    'finished_at' => date('Y-m-d H:i:s'),
                ]);

                Logger::error('job_queue', 'Job failed: ' . $e->getMessage(), ['job_id' => (int) $job->id]);
            }
        }

        return $done;
    }

    private static function execute(string $type, array $payload): void
    {
        if ($type === 'update_apply') {
            UpdateManager::runQueuedApply($payload);
            return;
        }

        if ($type === 'enforce' || $type === 'unlock' || $type === 'powercycle') {
            Logger::info('job_queue', 'Job executed (placeholder)', ['type' => $type, 'payload' => $payload]);
            return;
        }

        throw new \RuntimeException('Unsupported job type: ' . $type);
    }
}
