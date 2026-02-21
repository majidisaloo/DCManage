<?php

declare(strict_types=1);

namespace DCManage\Support;

use Illuminate\Database\Capsule\Manager as Capsule;

final class LockManager
{
    public static function acquire(string $key, int $ttlSeconds, ?string $owner = null): bool
    {
        $owner = $owner ?: gethostname() . ':' . getmypid();
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);

        Capsule::table('mod_dcmanage_locks')->where('lock_key', $key)->where('expires_at', '<', $now)->delete();

        try {
            Capsule::table('mod_dcmanage_locks')->insert([
                'lock_key' => $key,
                'owner' => $owner,
                'acquired_at' => $now,
                'expires_at' => $expires,
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function release(string $key): void
    {
        Capsule::table('mod_dcmanage_locks')->where('lock_key', $key)->delete();
    }

    public static function cleanupExpired(): void
    {
        Capsule::table('mod_dcmanage_locks')->where('expires_at', '<', date('Y-m-d H:i:s'))->delete();
    }
}
