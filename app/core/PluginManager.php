<?php

namespace App\Core;

use App\Models\SettingModel;
use App\Services\MarketLicenseService;



class PluginManager extends ExtensionManager
{
    private array $enabled;

    public function __construct(?string $dir = null)
    {
        parent::__construct($dir ?: dirname(__DIR__, 2) . '/plugins', 'plugin');
        $this->enabled = $this->loadEnabled();
    }

    protected function manifestFile(): string
    {
        return 'plugin.json';
    }

    

    public function all(): array
    {
        $items = [];
        foreach (glob($this->dir . '/*/plugin.json') ?: [] as $manifestPath) {
            $slug = basename(dirname($manifestPath));
            $data = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $license = MarketLicenseService::status('plugin', $slug);
            $items[] = [
                'slug'             => $slug,
                'name'             => (string)($data['name'] ?? $slug),
                'version'          => (string)($data['version'] ?? '1.0.0'),
                'description'      => (string)($data['description'] ?? ''),
                'author'           => (string)($data['author'] ?? ''),
                'enabled'          => in_array($slug, $this->enabled, true),
                'path'             => dirname($manifestPath),
                'license_required' => !empty($license['required']),
                'license_valid'    => !empty($license['valid']),
                'license_domain'   => (string)($license['domain'] ?? ''),
                'dependencies'     => $this->normalizeDependencies($data),
                'dependency_status'=> $this->dependencyStatus($slug, $data),
                'last_error'       => $this->lastError($slug),
                'api_version'      => (string)($data['api_version'] ?? $data['extension_api'] ?? ''),
                'api_status'       => $this->apiStatus($data),
            ];
        }
        return $items;
    }

    

    public function enable(string $slug): void
    {
        $slug = $this->safeSlug($slug);
        if ($slug === '') throw new \RuntimeException('插件不存在');
        $mf = $this->manifest($slug);
        if (!$mf) throw new \RuntimeException('插件不存在');

        $dep = $this->dependencyStatus($slug, $mf);
        if (!$dep['ok']) throw new \RuntimeException('插件依赖未满足：' . implode('；', $dep['messages']));

        if (!MarketLicenseService::valid('plugin', $slug)) {
            throw new \RuntimeException('插件未授权或授权域名不匹配');
        }

        $api = $this->apiStatus($mf);
        if (!$api['ok']) throw new \RuntimeException('插件 API 版本不兼容：' . implode('；', $api['messages']));

        if (!in_array($slug, $this->enabled, true)) {
            $this->enabled[] = $slug;
        }
        $this->saveEnabled();
    }

    public function disable(string $slug): void
    {
        $this->enabled = array_values(array_filter($this->enabled, fn($s) => $s !== $slug));
        $this->saveEnabled();
    }

    public function remove(string $slug): void
    {
        $safe = $this->safeSlug($slug);
        if ($safe === '') throw new \RuntimeException('插件不存在');
        $target = $this->dir . '/' . $safe;
        if (is_dir($target)) {
            $this->runUninstallFiles($safe, $target);
        }
        $this->disable($safe);
        parent::remove($safe);
    }

    

    public function installPackage(string $zipData): string
    {
        return $this->installMarketPackage($zipData, 'plugin');
    }

    protected function afterInstall(string $slug, string $target, array $manifest): void
    {
        $this->runDatabaseFiles($slug, $target);
    }

    

    public function boot(): void
    {
        foreach ($this->enabled as $slug) {
            try {
                $mf = $this->manifest($slug);
                if (!$mf) continue;
                $api = $this->apiStatus($mf);
                if (!$api['ok']) { $this->logError($slug, 'api', implode('；', $api['messages'])); continue; }
                $dep = $this->dependencyStatus($slug, $mf);
                if (!$dep['ok']) { $this->logError($slug, 'dependency', implode('；', $dep['messages'])); continue; }
                if (!MarketLicenseService::valid('plugin', $slug)) continue;

                $file = $this->dir . '/' . $slug . '/bootstrap.php';
                if (is_file($file)) require_once $file;
            } catch (\Throwable $e) {
                $this->logError((string)$slug, 'boot', $e->getMessage(), $e->getTraceAsString());
                continue;
            }
        }
    }

    

    
    public function pluginBackups(string $slug = ''): array
    {
        return $this->backups($slug);
    }

    
    public function rollbackPluginBackup(string $backupId): string
    {
        return $this->rollback($backupId);
    }

    

    private function normalizeDependencies(array $manifest): array
    {
        $raw = $manifest['dependencies'] ?? ($manifest['requires'] ?? []);
        if (!is_array($raw)) return [];
        $deps = [];
        foreach ($raw as $key => $value) {
            if (is_int($key)) { $deps[] = ['slug' => (string)$value, 'version' => '']; }
            elseif (is_array($value)) { $deps[] = ['slug' => (string)$key, 'version' => (string)($value['version'] ?? $value['min_version'] ?? '')]; }
            else { $deps[] = ['slug' => (string)$key, 'version' => (string)$value]; }
        }
        return array_values(array_filter($deps, static fn($d) => preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($d['slug'] ?? '')) !== ''));
    }

    private function dependencyStatus(string $slug, array $manifest): array
    {
        $messages = [];
        foreach ($this->normalizeDependencies($manifest) as $dep) {
            $depSlug = $this->safeSlug((string)$dep['slug']);
            $need = trim((string)($dep['version'] ?? ''));
            if ($depSlug === '') continue;

            if ($depSlug === 'php') {
                if ($need !== '' && !$this->versionSatisfies(PHP_VERSION, $need)) {
                    $messages[] = 'PHP 版本需 ' . $need . '，当前 ' . PHP_VERSION;
                }
                continue;
            }

            if (in_array($depSlug, ['claybbs', 'core'], true)) {
                $current = \App\Extension\ExtensionContract::MIN_CORE_VERSION;
                if ($need !== '' && !$this->versionSatisfies($current, $need)) {
                    $messages[] = 'ClayBBS 版本需 ' . $need . '，当前 ' . $current;
                }
                continue;
            }

            $depManifest = $this->manifest($depSlug);
            if (!$depManifest) { $messages[] = $depSlug . ' 未安装'; continue; }
            if (!in_array($depSlug, $this->enabled, true)) { $messages[] = $depSlug . ' 未启用'; continue; }
            if ($need !== '' && !$this->versionSatisfies((string)($depManifest['version'] ?? '0.0.0'), $need)) {
                $messages[] = $depSlug . ' 版本需 ' . $need;
            }
        }
        return ['ok' => empty($messages), 'messages' => $messages];
    }

    private function versionSatisfies(string $current, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '') return true;
        if (preg_match('/^(>=|<=|>|<|=|==|!=)\s*([0-9A-Za-z_.+\-]+)$/', $constraint, $m)) {
            $op = $m[1] === '=' ? '==' : $m[1];
            return version_compare($current, $m[2], $op);
        }
        return version_compare($current, $constraint, '>=');
    }

    

    private function loadEnabled(): array
    {
        try {
            $arr = json_decode((new SettingModel())->all()['plugins_enabled'] ?? '[]', true);
            return is_array($arr) ? array_values(array_filter(array_map('strval', $arr))) : [];
        } catch (\Throwable $e) { return []; }
    }

    private function saveEnabled(): void
    {
        (new SettingModel())->set('plugins_enabled', json_encode(array_values($this->enabled), JSON_UNESCAPED_UNICODE));
    }
}
