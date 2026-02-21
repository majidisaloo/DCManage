<?php

declare(strict_types=1);

namespace DCManage\Support;

use DCManage\Version;
use Illuminate\Database\Capsule\Manager as Capsule;
use ZipArchive;

final class UpdateManager
{
    private const GITHUB_REPO = 'majidisaloo/DCManage';
    private const GITHUB_BRANCH = 'main';
    private const UPDATE_LOCK_KEY = 'update:apply';
    private const UPDATE_STATE_META_KEY = 'update.state';
    private const UPDATE_CANCEL_META_KEY = 'update.cancel_requested';
    private const UPDATE_LATEST_CACHE_META_KEY = 'update.latest_release_cache';
    private const CONNECT_TIMEOUT = 10;
    private const REQUEST_TIMEOUT = 120;
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_SECONDS = 1;

    public static function autoUpdateIfNeeded(): array
    {
        if (!self::isAutoEnabled()) {
            return ['status' => 'skipped', 'reason' => 'auto-update disabled'];
        }

        return self::queueApplyLatest(false, true);
    }

    public static function checkLatestStatus(): array
    {
        $currentVersion = self::readInstalledModuleVersion();
        $latestVersion = '';
        $latestTag = '';
        $releaseUrl = '';
        $publishedAt = '';
        $remoteError = '';

        try {
            $latest = self::fetchLatestRelease();
            $latestVersion = self::normalizeVersion((string) ($latest['tag_name'] ?? ''));
            $latestTag = (string) ($latest['tag_name'] ?? '');
            $releaseUrl = (string) ($latest['html_url'] ?? '');
            $publishedAt = (string) ($latest['published_at'] ?? '');
        } catch (\Throwable $e) {
            $remoteError = $e->getMessage();
            Logger::warning('update', 'Latest release check failed', ['error' => $remoteError]);
            $cached = self::getCachedLatestRelease();
            if (is_array($cached)) {
                $latestVersion = self::normalizeVersion((string) ($cached['tag_name'] ?? ''));
                $latestTag = (string) ($cached['tag_name'] ?? '');
                $releaseUrl = (string) ($cached['html_url'] ?? '');
                $publishedAt = (string) ($cached['published_at'] ?? '');
            }
        }

        if ($latestVersion === '') {
            $latestVersion = $currentVersion;
            if ($latestTag === '') {
                $latestTag = 'v' . $currentVersion;
            }
        }

        $result = [
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'latest_tag' => $latestTag,
            'has_update' => version_compare($latestVersion, $currentVersion, '>'),
            'auto_update' => self::isAutoEnabled(),
            'repo' => self::GITHUB_REPO,
            'branch' => self::GITHUB_BRANCH,
            'release_url' => $releaseUrl,
            'published_at' => $publishedAt,
            'update_state' => self::getUpdateState(),
            'remote_ok' => $remoteError === '',
        ];

        if ($remoteError !== '') {
            $result['remote_error'] = $remoteError;
        }

        return $result;
    }

    public static function queueApplyLatest(bool $force, bool $auto = false): array
    {
        self::cleanupStaleUpdateJobs();

        $existing = Capsule::table('mod_dcmanage_jobs')
            ->where('type', 'update_apply')
            ->whereIn('status', ['pending', 'running'])
            ->orderBy('id', 'desc')
            ->first(['id', 'status', 'created_at', 'started_at']);

        if ($existing !== null) {
            self::setUpdateState([
                'status' => (string) $existing->status,
                'message' => 'Update task already in progress',
                'job_id' => (int) $existing->id,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'status' => 'already-running',
                'job_id' => (int) $existing->id,
                'job_status' => (string) $existing->status,
            ];
        }

        self::clearCancelRequest();
        $jobId = (int) Capsule::table('mod_dcmanage_jobs')->insertGetId([
            'type' => 'update_apply',
            'payload_json' => json_encode(['force' => $force ? 1 : 0, 'auto' => $auto ? 1 : 0], JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'attempts' => 0,
            'run_after' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        self::setUpdateState([
            'status' => 'queued',
            'message' => 'Update queued',
            'job_id' => $jobId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Logger::info('update', 'Update queued', ['job_id' => $jobId, 'force' => $force, 'auto' => $auto]);

        return ['status' => 'queued', 'job_id' => $jobId];
    }

    public static function cancelQueuedUpdate(): array
    {
        self::cleanupStaleUpdateJobs();

        Capsule::table('mod_dcmanage_meta')->updateOrInsert(
            ['meta_key' => self::UPDATE_CANCEL_META_KEY],
            ['meta_value' => '1', 'updated_at' => date('Y-m-d H:i:s')]
        );

        $affected = Capsule::table('mod_dcmanage_jobs')
            ->where('type', 'update_apply')
            ->where('status', 'pending')
            ->update([
                'status' => 'canceled',
                'finished_at' => date('Y-m-d H:i:s'),
                'last_error' => 'Canceled by admin',
            ]);

        $running = Capsule::table('mod_dcmanage_jobs')
            ->where('type', 'update_apply')
            ->where('status', 'running')
            ->count();

        if ((int) $affected === 0 && (int) $running === 0) {
            self::clearCancelRequest();
            self::setUpdateState([
                'status' => 'idle',
                'message' => 'No running update task to cancel',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return ['status' => 'no-active-job', 'canceled_pending' => 0];
        }

        self::setUpdateState([
            'status' => 'cancel-requested',
            'message' => 'Cancel requested',
            'canceled_pending' => (int) $affected,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Logger::warning('update', 'Cancel requested', ['canceled_pending' => (int) $affected]);

        return ['status' => 'cancel-requested', 'canceled_pending' => (int) $affected];
    }

    public static function getUpdateRuntimeStatus(): array
    {
        self::cleanupStaleUpdateJobs();

        $active = Capsule::table('mod_dcmanage_jobs')
            ->where('type', 'update_apply')
            ->whereIn('status', ['pending', 'running'])
            ->orderBy('id', 'desc')
            ->first(['id', 'status', 'attempts', 'created_at', 'started_at', 'run_after', 'last_error']);

        $last = Capsule::table('mod_dcmanage_jobs')
            ->where('type', 'update_apply')
            ->orderBy('id', 'desc')
            ->first(['id', 'status', 'attempts', 'created_at', 'started_at', 'finished_at', 'last_error']);

        if ($active === null && self::isCancelRequested()) {
            self::clearCancelRequest();
        }

        return [
            'state' => self::getUpdateState(),
            'active_job' => $active ? (array) $active : null,
            'last_job' => $last ? (array) $last : null,
            'cancel_requested' => self::isCancelRequested(),
        ];
    }

    public static function runQueuedApply(array $payload): array
    {
        if (self::isCancelRequested()) {
            self::setUpdateState([
                'status' => 'canceled',
                'message' => 'Update canceled before start',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            self::clearCancelRequest();
            Logger::warning('update', 'Update canceled before execution');
            return ['status' => 'canceled'];
        }

        $force = (int) ($payload['force'] ?? 0) === 1;
        return self::applyLatestIfNewer($force);
    }

    public static function applyLatestIfNewer(bool $force): array
    {
        if (!LockManager::acquire(self::UPDATE_LOCK_KEY, 3600)) {
            self::setUpdateState([
                'status' => 'running',
                'message' => 'Another update task is running',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return ['status' => 'already-running'];
        }

        self::setUpdateState(['status' => 'running', 'message' => 'Checking latest release', 'updated_at' => date('Y-m-d H:i:s')]);
        Logger::info('update', 'Update started', ['force' => $force]);

        try {
            $latest = self::fetchLatestRelease();
            $latestVersion = self::normalizeVersion((string) ($latest['tag_name'] ?? ''));
            $latestTag = (string) ($latest['tag_name'] ?? '');
            $installedBefore = self::readInstalledModuleVersion();

            if (!$force && ($latestVersion === '' || version_compare($latestVersion, $installedBefore, '<='))) {
                self::setUpdateState([
                    'status' => 'up-to-date',
                    'message' => 'Already up to date',
                    'from' => $installedBefore,
                    'to' => $latestVersion,
                    'tag' => $latestTag,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                Logger::info('update', 'No update required', ['current' => $installedBefore, 'latest' => $latestVersion, 'tag' => $latestTag]);
                self::clearCancelRequest();

                return [
                    'status' => 'up-to-date',
                    'current' => $installedBefore,
                    'latest' => $latestVersion,
                    'tag' => $latestTag,
                ];
            }

            self::checkCanceled('Canceled before download');
            $zipUrl = self::resolveReleaseZipUrl($latest);
            if ($zipUrl === '') {
                throw new \RuntimeException('Release zip URL not found');
            }

            self::setUpdateState(['status' => 'running', 'message' => 'Downloading release package', 'tag' => $latestTag, 'updated_at' => date('Y-m-d H:i:s')]);
            self::applyZipUpdate($zipUrl, $latestTag, $latestVersion);
            $installedAfter = self::readInstalledModuleVersion();

            if ($latestVersion !== '' && version_compare($installedAfter, $latestVersion, '<')) {
                throw new \RuntimeException('Version validation failed after update. Expected >= ' . $latestVersion . ', got ' . $installedAfter);
            }

            self::setUpdateState([
                'status' => 'updated',
                'message' => 'Update applied successfully',
                'from' => $installedBefore,
                'to' => $installedAfter,
                'tag' => $latestTag,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            Logger::info('update', 'Update applied', ['from' => $installedBefore, 'to' => $installedAfter, 'tag' => $latestTag]);
            self::clearCancelRequest();

            return [
                'status' => 'updated',
                'from' => $installedBefore,
                'to' => $installedAfter,
                'tag' => $latestTag,
            ];
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'canceled') !== false) {
                self::setUpdateState([
                    'status' => 'canceled',
                    'message' => $e->getMessage(),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                self::clearCancelRequest();
                Logger::warning('update', 'Update canceled', ['error' => $e->getMessage()]);
                throw $e;
            }

            self::setUpdateState([
                'status' => 'failed',
                'message' => $e->getMessage(),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            Logger::error('update', 'Update failed', ['error' => $e->getMessage()]);
            throw $e;
        } finally {
            LockManager::release(self::UPDATE_LOCK_KEY);
        }
    }

    public static function isAutoEnabled(): bool
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

    public static function setAutoEnabled(bool $enabled): void
    {
        Capsule::table('tbladdonmodules')->updateOrInsert(
            ['module' => 'dcmanage', 'setting' => 'update_auto'],
            ['value' => $enabled ? 'on' : 'off']
        );
    }

    private static function fetchLatestRelease(): array
    {
        [$owner, $name] = explode('/', self::GITHUB_REPO, 2);
        $url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($name) . '/releases/latest';

        $json = self::httpGetJson($url);
        if (!isset($json['tag_name'])) {
            throw new \RuntimeException('Latest release tag not found for ' . self::GITHUB_REPO);
        }

        self::cacheLatestRelease($json);

        return $json;
    }

    private static function normalizeVersion(string $tag): string
    {
        $tag = trim($tag);
        if ($tag === '') {
            return '';
        }

        return ltrim($tag, 'vV');
    }

    private static function applyZipUpdate(string $url, string $tag, string $expectedVersion): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive extension is required for auto-update');
        }

        $tmpBase = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dcmanage_upd_' . time() . '_' . bin2hex(random_bytes(4));
        $zipFile = $tmpBase . '.zip';
        $extractDir = $tmpBase . '_x';

        $download = self::httpDownload($url);
        $zipData = $download['body'];
        $downloadBytes = (int) $download['size'];
        $expectedBytes = (int) $download['content_length'];
        if ($expectedBytes > 0 && $downloadBytes !== $expectedBytes) {
            throw new \RuntimeException('Incomplete release download: expected ' . $expectedBytes . ' bytes, got ' . $downloadBytes);
        }

        self::checkCanceled('Canceled during download');
        if (file_put_contents($zipFile, $zipData) === false) {
            throw new \RuntimeException('Failed to write update zip file');
        }
        if (!is_file($zipFile) || filesize($zipFile) <= 0) {
            throw new \RuntimeException('Downloaded update file is empty');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new \RuntimeException('Failed to open update zip');
        }
        if ($zip->numFiles <= 0) {
            $zip->close();
            throw new \RuntimeException('Update zip has no files');
        }

        if (!mkdir($extractDir, 0775, true) && !is_dir($extractDir)) {
            throw new \RuntimeException('Failed to create extract directory');
        }

        self::setUpdateState(['status' => 'running', 'message' => 'Extracting release package', 'tag' => $tag, 'updated_at' => date('Y-m-d H:i:s')]);
        $zip->extractTo($extractDir);
        $zip->close();
        self::checkCanceled('Canceled after extraction');

        $repoRoot = self::findRepoRoot($extractDir);
        $sourceModule = $repoRoot . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'dcmanage';
        if (!is_dir($sourceModule)) {
            self::cleanupTemp($zipFile, $extractDir);
            throw new \RuntimeException('Update archive missing modules/addons/dcmanage');
        }

        self::validateExtractedModule($sourceModule, $expectedVersion);

        $targetModule = dirname(__DIR__, 2);
        self::setUpdateState(['status' => 'running', 'message' => 'Applying files', 'tag' => $tag, 'updated_at' => date('Y-m-d H:i:s')]);
        self::copyRecursive($sourceModule, $targetModule, true);
        self::checkCanceled('Canceled while applying files');

        Logger::info('update', 'Auto-update files applied', ['tag' => $tag, 'bytes' => $downloadBytes, 'content_length' => $expectedBytes]);
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

    private static function copyRecursive(string $src, string $dst, bool $root = false): void
    {
        self::checkCanceled('Canceled while copying files');

        if (!is_dir($dst) && !mkdir($dst, 0775, true) && !is_dir($dst)) {
            throw new \RuntimeException('Failed to create destination: ' . $dst);
        }

        $items = scandir($src);
        if (!is_array($items)) {
            throw new \RuntimeException('Failed to scan source directory: ' . $src);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.git' || $item === '.DS_Store') {
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

        if ($root) {
            $required = ['dcmanage.php', 'cron.php', 'hooks.php'];
            foreach ($required as $file) {
                if (!is_file($dst . DIRECTORY_SEPARATOR . $file)) {
                    throw new \RuntimeException('Update validation failed after copy: missing ' . $file);
                }
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
        $res = self::httpDownload($url);
        return (string) $res['body'];
    }

    private static function httpDownload(string $url): array
    {
        $lastError = '';

        for ($i = 1; $i <= self::MAX_RETRIES; $i++) {
            self::checkCanceled('Canceled before HTTP request');
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: DCManage-Updater',
                'Accept: application/vnd.github+json',
            ]);

            $body = curl_exec($ch);
            if ($body === false) {
                $lastError = (string) curl_error($ch);
                Logger::warning('update', 'Updater request failed, retrying', ['try' => $i, 'error' => $lastError, 'url' => $url]);
                curl_close($ch);
                sleep(self::RETRY_DELAY_SECONDS);
                continue;
            }

            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentLength = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            curl_close($ch);

            if ($status >= 400) {
                $lastError = 'HTTP ' . $status;
                Logger::warning('update', 'Updater HTTP error, retrying', ['try' => $i, 'status' => $status, 'url' => $url]);
                sleep(self::RETRY_DELAY_SECONDS);
                continue;
            }

            return [
                'body' => (string) $body,
                'size' => strlen((string) $body),
                'http_status' => $status,
                'content_length' => $contentLength,
            ];
        }

        throw new \RuntimeException('Updater request failed: ' . ($lastError !== '' ? $lastError : 'unknown error'));
    }

    private static function cleanupStaleUpdateJobs(): void
    {
        $staleRunningBefore = date('Y-m-d H:i:s', time() - 1800);
        $stalePendingBefore = date('Y-m-d H:i:s', time() - 3600);

        $staleRunningIds = Capsule::table('mod_dcmanage_jobs')
            ->where('type', 'update_apply')
            ->where('status', 'running')
            ->where('started_at', '<', $staleRunningBefore)
            ->pluck('id')
            ->toArray();

        if (!empty($staleRunningIds)) {
            Capsule::table('mod_dcmanage_jobs')
                ->whereIn('id', $staleRunningIds)
                ->update([
                    'status' => 'failed',
                    'finished_at' => date('Y-m-d H:i:s'),
                    'last_error' => 'Marked as stale running job',
                ]);
        }

        $stalePendingIds = Capsule::table('mod_dcmanage_jobs')
            ->where('type', 'update_apply')
            ->where('status', 'pending')
            ->where('created_at', '<', $stalePendingBefore)
            ->pluck('id')
            ->toArray();

        if (!empty($stalePendingIds)) {
            Capsule::table('mod_dcmanage_jobs')
                ->whereIn('id', $stalePendingIds)
                ->update([
                    'status' => 'canceled',
                    'finished_at' => date('Y-m-d H:i:s'),
                    'last_error' => 'Canceled stale pending job',
                ]);
        }
    }

    private static function cacheLatestRelease(array $release): void
    {
        Capsule::table('mod_dcmanage_meta')->updateOrInsert(
            ['meta_key' => self::UPDATE_LATEST_CACHE_META_KEY],
            ['meta_value' => json_encode($release, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')]
        );
    }

    private static function getCachedLatestRelease(): ?array
    {
        $raw = Capsule::table('mod_dcmanage_meta')
            ->where('meta_key', self::UPDATE_LATEST_CACHE_META_KEY)
            ->value('meta_value');
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private static function resolveReleaseZipUrl(array $latest): string
    {
        $tag = trim((string) ($latest['tag_name'] ?? ''));
        $assets = $latest['assets'] ?? [];
        if (is_array($assets)) {
            foreach ($assets as $asset) {
                if (!is_array($asset)) {
                    continue;
                }
                $name = (string) ($asset['name'] ?? '');
                if ($name !== '' && stripos($name, 'DCManage-') === 0 && substr($name, -4) === '.zip') {
                    $url = (string) ($asset['browser_download_url'] ?? '');
                    if ($url !== '') {
                        Logger::info('update', 'Using release asset zip', ['asset' => $name, 'tag' => $tag]);
                        return $url;
                    }
                }
            }
        }

        return (string) ($latest['zipball_url'] ?? '');
    }

    private static function validateExtractedModule(string $sourceModule, string $expectedVersion): void
    {
        $required = ['dcmanage.php', 'cron.php', 'hooks.php'];
        foreach ($required as $file) {
            if (!is_file($sourceModule . DIRECTORY_SEPARATOR . $file)) {
                throw new \RuntimeException('Release package is incomplete: missing ' . $file);
            }
        }

        if ($expectedVersion !== '') {
            $pkgVersion = self::readVersionFromModulePath($sourceModule);
            if ($pkgVersion === '') {
                throw new \RuntimeException('Release package version not found in module');
            }
            if (version_compare($pkgVersion, $expectedVersion, '<')) {
                throw new \RuntimeException('Release package version mismatch. Expected ' . $expectedVersion . ', got ' . $pkgVersion);
            }
        }
    }

    private static function readInstalledModuleVersion(): string
    {
        $moduleRoot = dirname(__DIR__, 2);
        $v = self::readVersionFromModulePath($moduleRoot);
        return $v !== '' ? $v : Version::CURRENT;
    }

    private static function readVersionFromModulePath(string $moduleRoot): string
    {
        $versionPhp = $moduleRoot . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Version.php';
        if (!is_file($versionPhp)) {
            return '';
        }
        $raw = (string) @file_get_contents($versionPhp);
        if ($raw === '') {
            return '';
        }
        if (preg_match("/CURRENT\\s*=\\s*'([^']+)'/", $raw, $m) === 1) {
            return trim((string) $m[1]);
        }
        return '';
    }

    private static function setUpdateState(array $state): void
    {
        Capsule::table('mod_dcmanage_meta')->updateOrInsert(
            ['meta_key' => self::UPDATE_STATE_META_KEY],
            ['meta_value' => json_encode($state, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')]
        );
    }

    private static function getUpdateState(): array
    {
        $raw = Capsule::table('mod_dcmanage_meta')
            ->where('meta_key', self::UPDATE_STATE_META_KEY)
            ->value('meta_value');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private static function isCancelRequested(): bool
    {
        $v = Capsule::table('mod_dcmanage_meta')
            ->where('meta_key', self::UPDATE_CANCEL_META_KEY)
            ->value('meta_value');

        return trim((string) $v) === '1';
    }

    private static function clearCancelRequest(): void
    {
        Capsule::table('mod_dcmanage_meta')->updateOrInsert(
            ['meta_key' => self::UPDATE_CANCEL_META_KEY],
            ['meta_value' => '0', 'updated_at' => date('Y-m-d H:i:s')]
        );
    }

    private static function checkCanceled(string $message): void
    {
        if (self::isCancelRequested()) {
            self::setUpdateState([
                'status' => 'canceled',
                'message' => $message,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            Logger::warning('update', 'Update canceled', ['at' => $message]);
            throw new \RuntimeException($message);
        }
    }
}
