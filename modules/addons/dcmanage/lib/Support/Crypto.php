<?php

declare(strict_types=1);

namespace DCManage\Support;

final class Crypto
{
    public static function encrypt(string $plain): string
    {
        if (function_exists('localAPI')) {
            $result = localAPI('EncryptPassword', ['password2' => $plain]);
            if (($result['result'] ?? '') === 'success' && !empty($result['password'])) {
                return (string) $result['password'];
            }
        }

        $key = hash('sha256', (string) (defined('CC_ENCRYPTION_HASH') ? CC_ENCRYPTION_HASH : 'dcmanage-fallback'), true);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $encrypted): string
    {
        if (function_exists('localAPI')) {
            $result = localAPI('DecryptPassword', ['password2' => $encrypted]);
            if (($result['result'] ?? '') === 'success' && isset($result['password'])) {
                return (string) $result['password'];
            }
        }

        $raw = base64_decode($encrypted, true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }

        $key = hash('sha256', (string) (defined('CC_ENCRYPTION_HASH') ? CC_ENCRYPTION_HASH : 'dcmanage-fallback'), true);
        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);
        $plain = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $plain === false ? '' : $plain;
    }
}
