<?php

namespace App\Controllers\Admin;

use App\Core\PluginManager;
use App\Core\ThemeManager;
use App\Extension\ExtensionContract;
use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Services\UpdateCenterClient;
use App\Models\AdminAuditLogModel;

class ExtensionController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.extension');
    }

    public function index(): void
    {
        $pluginManager = new PluginManager();
        $themeManager = new ThemeManager();
        $error = '';
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = (string)($_POST['_action'] ?? '');
            try {
                if ($action === 'install_by_key') {
                    $key = trim((string)($_POST['license_key'] ?? ''));
                    if ($key === '') throw new \RuntimeException('请填写授权 Key');
                    $cfg = $this->config();
                    $zip = $this->client()->marketDownloadByKey($key, (string)($cfg['domain'] ?? ''));
                    $type = $this->detectPackageType($zip);
                    if ($type === 'plugin') $pluginManager->installPackage($zip);
                    elseif ($type === 'theme') $themeManager->installPackage($zip);
                    else throw new \RuntimeException('未知应用包类型');
                    redirect_or_ajax('/admin.php?path=extensions');
                }
                if ($action === 'update_plugin') {
                    $itemId = (int)($_POST['item_id'] ?? 0);
                    if ($itemId <= 0) throw new \RuntimeException('缺少应用 ID');
                    $zip = $this->client()->marketDownload($itemId);
                    $slugInstalled = $pluginManager->installPackage($zip);
                    (new AdminAuditLogModel())->record('plugin.install_by_key', 'plugin', 0, ['slug'=>$slugInstalled]);
                    redirect_or_ajax('/admin.php?path=extensions');
                }
                if ($action === 'update_theme') {
                    $itemId = (int)($_POST['item_id'] ?? 0);
                    if ($itemId <= 0) throw new \RuntimeException('缺少应用 ID');
                    $zip = $this->client()->marketDownload($itemId);
                    $themeSlug = $themeManager->installPackage($zip);
                    (new AdminAuditLogModel())->record('theme.update', 'theme', 0, ['slug'=>$themeSlug, 'item_id'=>$itemId]);
                    redirect_or_ajax('/admin.php?path=extensions');
                }
                if ($action === 'enable_plugin') { $slug=(string)($_POST['slug'] ?? ''); $pluginManager->enable($slug); (new AdminAuditLogModel())->record('plugin.enable', 'plugin', 0, ['slug'=>$slug]); redirect_or_ajax('/admin.php?path=extensions'); }
                if ($action === 'disable_plugin') { $slug=(string)($_POST['slug'] ?? ''); $pluginManager->disable($slug); (new AdminAuditLogModel())->record('plugin.disable', 'plugin', 0, ['slug'=>$slug]); redirect_or_ajax('/admin.php?path=extensions'); }
                if ($action === 'remove_plugin') { $slug=(string)($_POST['slug'] ?? ''); $pluginManager->remove($slug); (new AdminAuditLogModel())->record('plugin.remove', 'plugin', 0, ['slug'=>$slug]); redirect_or_ajax('/admin.php?path=extensions'); }
                if ($action === 'rollback_plugin') { if ((string)($_POST['confirm_text'] ?? '') !== 'ROLLBACK') throw new \RuntimeException('请输入 ROLLBACK 确认回滚'); $backupId=(string)($_POST['backup_id'] ?? ''); $slug=$pluginManager->rollbackPluginBackup($backupId); (new AdminAuditLogModel())->record('plugin.rollback', 'plugin', 0, ['slug'=>$slug, 'backup_id'=>$backupId]); redirect_or_ajax('/admin.php?path=extensions'); }
                if ($action === 'activate_theme') { $slug=(string)($_POST['slug'] ?? 'default'); $themeManager->setActive($slug); (new AdminAuditLogModel())->record('theme.activate', 'theme', 0, ['slug'=>$slug]); redirect_or_ajax('/admin.php?path=extensions'); }
                if ($action === 'remove_theme') { $slug=(string)($_POST['slug'] ?? ''); $themeManager->remove($slug); (new AdminAuditLogModel())->record('theme.remove', 'theme', 0, ['slug'=>$slug]); redirect_or_ajax('/admin.php?path=extensions'); }
            } catch (\Throwable $e) { if (is_ajax_request()) ajax_error('操作失败：' . $e->getMessage()); $error = '操作失败：' . $e->getMessage(); }
        }

        $plugins = $this->attachMarketUpdates($pluginManager->all(), 'plugin');
        $themes = $this->attachMarketUpdates($themeManager->all(), 'theme');
        $pluginBackups = $pluginManager->pluginBackups();
        $pluginErrors = $pluginManager->recentErrors(30);
        $extensionContract = [
            'api_version' => ExtensionContract::API_VERSION,
            'min_core_version' => ExtensionContract::MIN_CORE_VERSION,
            'encryption_exclude' => ExtensionContract::ENCRYPTION_EXCLUDE,
        ];
        require dirname(__DIR__, 2) . '/views/admin/content/extensions.php';
    }


    private function attachMarketUpdates(array $plugins, string $type): array
    {
        $market = [];
        try {
            $res = $this->client()->marketList($type);
            foreach (($res['items'] ?? []) as $item) {
                if (($item['type'] ?? '') !== $type) continue;
                $slug = (string)($item['slug'] ?? '');
                if ($slug !== '') $market[$slug] = $item;
            }
        } catch (\Throwable $e) {
            foreach ($plugins as &$plugin) {
                $plugin['market_error'] = $e->getMessage();
            }
            unset($plugin);
            return $plugins;
        }
        foreach ($plugins as &$plugin) {
            $slug = (string)($plugin['slug'] ?? '');
            $item = $market[$slug] ?? null;
            $plugin['market_item'] = $item;
            $plugin['market_item_id'] = $item ? (int)($item['id'] ?? 0) : 0;
            $plugin['latest_version'] = $item ? (string)($item['version'] ?? '') : '';
            $plugin['update_available'] = $item && version_compare((string)($item['version'] ?? '0.0.0'), (string)($plugin['version'] ?? '0.0.0'), '>');
            $plugin['market_acquired'] = $item ? !empty($item['acquired']) : false;
        }
        unset($plugin);
        return $plugins;
    }

    private function config(): array
    {
        $path = dirname(__DIR__, 3) . '/config/update-center.php';
        if (!is_file($path)) {
            return [];
        }
        $cfg = include $path;
        return is_array($cfg) ? $cfg : [];
    }

    private function client(): UpdateCenterClient
    {
        return new UpdateCenterClient($this->config());
    }

    private function detectPackageType(string $zipData): string
    {
        $tmp = dirname(__DIR__, 3) . '/storage/updates/inspect_' . bin2hex(random_bytes(6)) . '.zip';
        @mkdir(dirname($tmp), 0755, true);
        file_put_contents($tmp, $zipData);
        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) { @unlink($tmp); throw new \RuntimeException('应用包打开失败'); }
        $raw = $zip->getFromName('market.json') ?: $zip->getFromName('manifest.json');
        if ($raw === false || $raw === '') {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
                if (substr_count(trim($name, '/'), '/') === 1 && preg_match('#/market\\.json$#', $name)) {
                    $raw = $zip->getFromName($name);
                    break;
                }
            }
        }
        $zip->close(); @unlink($tmp);
        $manifest = json_decode((string)$raw, true);
        return is_array($manifest) ? (string)($manifest['type'] ?? '') : '';
    }
}
