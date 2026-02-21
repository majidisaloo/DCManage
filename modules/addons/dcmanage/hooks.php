<?php

declare(strict_types=1);

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Bootstrap.php';

add_hook('DailyCronJob', 1, static function (): void {
    Capsule::table('mod_dcmanage_logs')->insert([
        'level' => 'info',
        'source' => 'hook',
        'message' => 'DailyCronJob heartbeat',
        'created_at' => date('Y-m-d H:i:s'),
    ]);
});
