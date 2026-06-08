<?php

namespace App\Core;

use App\Models\SettingModel;
use App\Services\MarketLicenseService;



class ThemeManager extends ExtensionManager
{
    public function __construct(?string $dir = null)
    {
        parent::__construct($dir ?: dirname(__DIR__, 2) . '/themes', 'theme');
    }

    protected function manifestFile(): string
    {
        return 'theme.json';
    }

    

    public function active(): string
    {
        try {
            $active = (string)((new SettingModel())->all()['theme_active'] ?? 'default');
            if ($active !== 'default' && !MarketLicenseService::valid('theme', $active)) return 'default';
            return $active;
        } catch (\Throwable $e) { return 'default'; }
    }

    public function setActive(string $slug): void
    {
        if ($slug !== 'default' && !is_file($this->dir . '/' . $this->safeSlug($slug) . '/theme.json')) {
            throw new \RuntimeException('主题不存在');
        }
        if ($slug !== 'default' && !MarketLicenseService::valid('theme', $slug)) {
            throw new \RuntimeException('主题未授权或授权域名不匹配');
        }
        if ($slug !== 'default') {
            $api = $this->apiStatus($this->manifest($slug));
            if (!$api['ok']) throw new \RuntimeException('主题 API 版本不兼容：' . implode('；', $api['messages']));
        }
        (new SettingModel())->set('theme_active', $slug);
    }

    

    public function all(): array
    {
        $items = [[
            'slug'             => 'default',
            'name'             => '默认主题',
            'version'          => '1.0.0',
            'description'      => '系统内置默认主题',
            'author'           => 'ClayBBS',
            'active'           => $this->active() === 'default',
            'license_required' => false,
            'license_valid'    => true,
            'license_domain'   => '',
            'api_version'      => '1.0.0',
            'api_status'       => $this->apiStatus(['api_version' => '1.0.0']),
        ]];

        foreach (glob($this->dir . '/*/theme.json') ?: [] as $manifestPath) {
            $slug = basename(dirname($manifestPath));
            $data = json_decode((string)file_get_contents($manifestPath), true) ?: [];
            $license = MarketLicenseService::status('theme', $slug);
            $items[] = [
                'slug'             => $slug,
                'name'             => (string)($data['name'] ?? $slug),
                'version'          => (string)($data['version'] ?? '1.0.0'),
                'description'      => (string)($data['description'] ?? ''),
                'author'           => (string)($data['author'] ?? ''),
                'active'           => $this->active() === $slug,
                'license_required' => !empty($license['required']),
                'license_valid'    => !empty($license['valid']),
                'license_domain'   => (string)($license['domain'] ?? ''),
                'api_version'      => (string)($data['api_version'] ?? $data['extension_api'] ?? ''),
                'api_status'       => $this->apiStatus($data),
            ];
        }
        return $items;
    }

    

    public function remove(string $slug): void
    {
        if ($slug === 'default') throw new \RuntimeException('默认主题不能卸载');
        $safe = $this->safeSlug($slug);
        if ($this->active() === $safe) $this->setActive('default');
        parent::remove($safe);
    }

    

    public function installPackage(string $zipData): string
    {
        return $this->installMarketPackage($zipData, 'theme');
    }

    

    public function resolveView(string $view): string
    {
        $view  = ltrim(str_replace('..', '', $view), '/');
        $active = $this->active();
        if ($active !== 'default') {
            $themeView = $this->dir . '/' . $active . '/views/' . $view;
            if (is_file($themeView)) return $themeView;
        }
        return dirname(__DIR__) . '/views/' . $view;
    }
}
