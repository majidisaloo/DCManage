<?php

declare(strict_types=1);

namespace DCManage\Support;

use Illuminate\Database\Capsule\Manager as Capsule;

final class Logger
{
    public static function info(string $source, string $message, array $context = []): void
    {
        self::write('info', $source, $message, $context);
    }

    public static function warning(string $source, string $message, array $context = []): void
    {
        self::write('warning', $source, $message, $context);
    }

    public static function error(string $source, string $message, array $context = []): void
    {
        self::write('error', $source, $message, $context);
    }

    private static function write(string $level, string $source, string $message, array $context): void
    {
        Capsule::table('mod_dcmanage_logs')->insert([
            'level' => $level,
            'source' => $source,
            'service_id' => isset($context['service_id']) ? (int) $context['service_id'] : null,
            'dc_id' => isset($context['dc_id']) ? (int) $context['dc_id'] : null,
            'message' => $message,
            'context_json' => $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
