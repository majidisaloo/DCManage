<?php

declare(strict_types=1);

namespace DCManage\Support;

use DCManage\Version;
use Illuminate\Database\Capsule\Manager as Capsule;
use ZipArchive;

final class UpdateManager
{
    public static function autoUpdateIfNeeded(): array
    {
        if (!self::isEnabled()) {
            return ['status' => 'skipped', 'reason' => 'auto-update disabled'];
        }

        $latest = self::fetchLatestRelease();
        $latestVersion = self::normalizeVersion((string) ($latest['tag_name'] ?? ''));
        if ($latestVersion === '' || version_compare($latestVersion, Version::CURRENT, '<=')) {
            return ['status' => 'up-to-date', 'current' => Version::CURRENT, 'latest' => $latestVersion];
        }

        $zipUrl = (string) ($latest['zipball_url'] ?? '');
        if ($zipUrl === '') {
            throw new \RuntimeException('Release zip URL not found');
        }

        self::applyZipUpdate($zipUrl, (string) ($latest['tag_name'] ?? 'unknown'));

        return [
            'status' => 'updated',
            'from' => Version::CURRENT,
            'to' => $latestVersion,
            'tag' => (string) ($latest['tag_name'] ?? ''),
        ];
    }

    private static function isEnabled(): bool
    {
        $value = Capsule::table('tbladdonmodules')
            ->where('module', 'dcmanage')
            ->where('setting', 'update_auto')
            ->value('value');

        if ($value === null) {
            return true;
        }

        $value = strtolower(trim((string) $value));
        return $value === 'on' || $value === '1' || $value === 'true' || $value === 'yes';
    }

    private static function fetchLatestRelease(): array
    {
        $repo = self::repo();
        [$owner, $name] = explode('/', $repo, 2);
        $url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($name) . '/releases/latest';

        $json = self::httpGetJson($url);
        if (!isset($json['tag_name'])) {
            throw new \RuntimeException('Latest release tag not found for ' . $repo);
        }

        return $json;
    }

    private static function repo(): string
    {
        $value = Capsule::table('tbladdonmodules')
            ->where('module', 'dcmanage')
            ->where('setting', 'update_repo')
            ->value('value');

        $repo = trim((string) ($value ?: 'majidisaloo/DCManage'));
        if ($repo === '' || strpos($repo, '/') === false) {
            return 'majidisaloo/DCManage';
        }

        return $repo;
    }

    private static function normalizeVersion(string $tag): string
    {
        $tag = trim($tag);
        if ($tag === '') {
            return '';
        }

        return ltrim($tag, 'vV');
    }

    private static function applyZipUpdate(string $url, string $tag): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive extension is required for auto-update');
        }

        $tmpBase = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dcmanage_upd_' . time() . '_' . bin2hex(random_bytes(4));
        $zipFile = $tmpBase . '.zip';
        $extractDir = $tmpBase . '_x';

        $zipData = self::httpGetRaw($url);
        if (file_put_contents($zipFile, $zipData) === false) {
            throw new \RuntimeException('Failed to write update zip file');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new \RuntimeException('Failed to open update zip');
        }

        if (!mkdir($extractDir, 0775, true) && !is_dir($extractDir)) {
            throw new \RuntimeException('Failed to create extract directory');
        }

        $zip->extractTo($extractDir);
        $zip->close();

        $repoRoot = self::findRepoRoot($extractDir);
        $sourceModule = $repoRoot . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'dcmanage';
        if (!is_dir($sourceModule)) {
            self::cleanupTemp($zipFile, $extractDir);
            throw new \RuntimeException('Update archive missing modules/addons/dcmanage');
        }

        $targetModule = dirname(__DIR__, 2);
        self::copyRecursive($sourceModule, $targetModule);

        Logger::info('update', 'Auto-update applied', ['tag' => $tag]);
        self::cleanupTemp($zipFile, $extractDir);
    }

    private static function findRepoRoot(string $extractDir): string
    {
        $entries = scandir($extractDir);
        if (!is_array($entries)) {
            throw new \RuntimeException('Cannot read extracted update directory');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $extractDir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('Repository root not found in extracted update');
    }

    private static function copyRecursive(string $src, string $dst): void
    {
        if (!is_dir($dst) && !mkdir($dst, 0775, true) && !is_dir($dst)) {
            throw new \RuntimeException('Failed to create destination: ' . $dst);
        }

        $items = scandir($src);
        if (!is_array($items)) {
            throw new \RuntimeException('Failed to scan source directory: ' . $src);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.git') {
                continue;
            }

            $sourcePath = $src . DIRECTORY_SEPARATOR . $item;
            $targetPath = $dst . DIRECTORY_SEPARATOR . $item;

            if (is_dir($sourcePath)) {
                self::copyRecursive($sourcePath, $targetPath);
                continue;
            }

            if (!copy($sourcePath, $targetPath)) {
                throw new \RuntimeException('Failed to copy file: ' . $item);
            }
        }
    }

    private static function cleanupTemp(string $zipFile, string $extractDir): void
    {
        if (is_file($zipFile)) {
            @unlink($zipFile);
        }
        self::removeDirRecursive($extractDir);
    }

    private static function removeDirRecursive(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                self::removeDirRecursive($full);
            } else {
                @unlink($full);
            }
        }

        @rmdir($path);
    }

    private static function httpGetJson(string $url): array
    {
        $raw = self::httpGetRaw($url);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from updater endpoint');
        }

        return $data;
    }

    private static function httpGetRaw(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: DCManage-Updater',
            'Accept: application/vnd.github+json',
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Updater request failed: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            throw new \RuntimeException('Updater HTTP error: ' . $status);
        }

        return (string) $body;
    }
}
