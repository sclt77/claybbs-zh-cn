<?php

namespace App\Core;

use App\Core\Database;
use App\Models\SettingModel;
use App\Models\MarketExtensionModel;
use App\Services\MarketLicenseService;



abstract class ExtensionManager
{
    protected string $dir;
    protected string $type; 

    public function __construct(string $dir, string $type)
    {
        $this->dir  = $dir;
        $this->type = $type;
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }
    }

    

    
    abstract protected function manifestFile(): string;

    
    abstract public function all(): array;

    

    public function remove(string $slug): void
    {
        $safe = $this->safeSlug($slug);
        $dir  = $this->dir . '/' . $safe;
        $this->rrmdir($dir);
        if (is_dir($dir)) {
            throw new \RuntimeException('目录删除失败，请检查权限：' . $dir);
        }
    }

    

    protected function installMarketPackage(string $zipData, string $expectType): string
    {
        $root = dirname(__DIR__, 2);
        $tmp  = $root . '/storage/updates/market_' . bin2hex(random_bytes(6)) . '.zip';
        @mkdir(dirname($tmp), 0755, true);
        file_put_contents($tmp, $zipData);
        $packageHash = hash('sha256', $zipData);

        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            throw new \RuntimeException('市场包打开失败');
        }

        $manifestInfo = $this->findMarketManifest($zip);
        $manifestRaw = $manifestInfo['raw'];
        $stripPrefix = $manifestInfo['prefix'];
        $manifest = json_decode((string)$manifestRaw, true);
        if (!is_array($manifest) || ($manifest['type'] ?? '') !== $expectType) {
            $zip->close();
            @unlink($tmp);
            throw new \RuntimeException('市场包类型不匹配');
        }

        $this->validateMarketManifest($manifest, $expectType);

        $slug = $this->safeSlug((string)($manifest['slug'] ?? ''));
        if ($slug === '') {
            $zip->close();
            @unlink($tmp);
            throw new \RuntimeException('市场包缺少 slug');
        }

        $target = $this->dir . '/' . $slug;
        if (is_dir($target)) {
            $this->backupDir($target, $slug);
        }

        @mkdir($target, 0755, true);
        $this->extractZipSafe($zip, $target, $stripPrefix);
        $zip->close();
        @unlink($tmp);

        
        $mf = $target . '/' . $this->manifestFile();
        if (!is_file($mf)) {
            file_put_contents($mf, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        
        $installed = json_decode((string)file_get_contents($mf), true) ?: [];
        $installedSlug = $this->safeSlug((string)($installed['slug'] ?? ''));
        if ($installedSlug !== $slug) {
            throw new \RuntimeException('市场包 ' . $this->manifestFile() . ' 与 manifest slug 不一致');
        }

        
        $this->recordMarketExtension($this->type, $slug, $installed, $packageHash);

        if (!MarketLicenseService::valid($this->type, $slug) && MarketLicenseService::requiresLicense($this->type, $slug, $installed)) {
            throw new \RuntimeException('授权校验失败：请确认应用已购买并绑定当前域名');
        }

        
        $this->afterInstall($slug, $target, $manifest);

        return $slug;
    }

    
    protected function afterInstall(string $slug, string $target, array $manifest): void
    {
        
    }

    protected function recordMarketExtension(string $type, string $slug, array $manifest, string $packageHash = ''): void
    {
        try {
            (new MarketExtensionModel())->recordInstall($type, $slug, $manifest, $packageHash);
        } catch (\Throwable $e) {
            $this->logError($slug, 'market_record', $e->getMessage(), $e->getTraceAsString());
        }
    }

    
    
    protected function backupDir(string $sourceDir, string $slug): string
    {
        $root   = dirname(__DIR__, 2);
        $backup = $root . '/storage/backups/' . $this->type . 's/' . $slug . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
        @mkdir(dirname($backup), 0755, true);
        if (!@rename($sourceDir, $backup)) {
            $this->copyDir($sourceDir, $backup);
            $this->rrmdir($sourceDir);
        }
        return $backup;
    }

    
    public function backups(string $slug = ''): array
    {
        $root = dirname(__DIR__, 2);
        $base = $root . '/storage/backups/' . $this->type . 's';
        if (!is_dir($base)) return [];

        $items = [];
        foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $path) {
            $manifest = [];
            if (is_file($path . '/' . $this->manifestFile())) {
                $manifest = json_decode((string)file_get_contents($path . '/' . $this->manifestFile()), true) ?: [];
            }
            $itemSlug = $this->safeSlug((string)($manifest['slug'] ?? ''))
                ?: preg_replace('/_[0-9]{14}_.+$/', '', basename($path));
            if ($slug !== '' && $itemSlug !== $slug) continue;

            $items[] = [
                'id'         => basename($path),
                'path'       => $path,
                'slug'       => $itemSlug,
                'name'       => (string)($manifest['name'] ?? $itemSlug),
                'version'    => (string)($manifest['version'] ?? ''),
                'created_at' => date('Y-m-d H:i:s', @filemtime($path) ?: time()),
            ];
        }
        usort($items, static fn($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']));
        return $items;
    }

    
    public function rollback(string $backupId): string
    {
        $backupId = basename($this->safeSlug($backupId) ?: '');
        if ($backupId === '') throw new \RuntimeException('缺少备份 ID');

        $root   = dirname(__DIR__, 2);
        $backup = $root . '/storage/backups/' . $this->type . 's/' . $backupId;
        if (!is_dir($backup) || !is_file($backup . '/' . $this->manifestFile())) {
            throw new \RuntimeException('备份不存在');
        }

        $manifest = json_decode((string)file_get_contents($backup . '/' . $this->manifestFile()), true) ?: [];
        $slug = $this->safeSlug((string)($manifest['slug'] ?? ''))
            ?: preg_replace('/_[0-9]{14}_.+$/', '', $backupId);
        if ($slug === '') throw new \RuntimeException('备份缺少 slug');

        $target = $this->dir . '/' . $slug;
        if (is_dir($target)) {
            $this->backupDir($target, $slug . '_before_rollback');
        }

        $this->copyDir($backup, $target, $slug);
        if (!is_dir($target) || !is_file($target . '/' . $this->manifestFile())) {
            throw new \RuntimeException('回滚恢复失败');
        }
        return $slug;
    }

    

    public function recentErrors(int $limit = 50): array
    {
        try {
            $this->ensureErrorTable();
            $stmt = Database::connection()->query(
                'SELECT * FROM extension_error_logs WHERE extension_type=' . Database::connection()->quote($this->type)
                . ' ORDER BY id DESC LIMIT ' . max(1, min(200, $limit))
            );
            return $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $e) { return []; }
    }

    protected function logError(string $slug, string $phase, string $message, string $trace = ''): void
    {
        try {
            $this->ensureErrorTable();
            Database::connection()->prepare(
                'INSERT INTO extension_error_logs (extension_type,slug,phase,message,trace,created_at) VALUES (:type,:slug,:phase,:message,:trace,NOW())'
            )->execute([
                ':type'   => $this->type,
                ':slug'   => mb_substr($slug, 0, 100),
                ':phase'  => mb_substr($phase, 0, 40),
                ':message' => mb_substr($message, 0, 20000),
                ':trace'  => $trace !== '' ? mb_substr($trace, 0, 60000) : null,
            ]);
        } catch (\Throwable $e) {}
    }

    protected function lastError(string $slug): ?array
    {
        try {
            $this->ensureErrorTable();
            $stmt = Database::connection()->prepare(
                'SELECT * FROM extension_error_logs WHERE extension_type=:type AND slug=:slug ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([':type' => $this->type, ':slug' => $slug]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) { return null; }
    }

    private function ensureErrorTable(): void
    {
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS extension_error_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            extension_type VARCHAR(20) NOT NULL DEFAULT 'plugin',
            slug VARCHAR(100) NOT NULL,
            phase VARCHAR(40) NOT NULL DEFAULT 'boot',
            message TEXT DEFAULT NULL,
            trace MEDIUMTEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            KEY idx_ext_err_type_slug (extension_type, slug, created_at),
            KEY idx_ext_err_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    

    protected function apiStatus(array $manifest): array
    {
        $messages = [];
        $need = trim((string)($manifest['api_version'] ?? $manifest['extension_api'] ?? ''));
        $current = \App\Extension\ExtensionContract::API_VERSION;
        if ($need !== '' && version_compare($current, $need, '<')) {
            $messages[] = '需要 API >= ' . $need . '，当前 ' . $current;
        }
        return ['ok' => empty($messages), 'messages' => $messages, 'current' => $current, 'required' => $need];
    }

    

    protected function manifest(string $slug): array
    {
        $slug = $this->safeSlug($slug);
        $file = $this->dir . '/' . $slug . '/' . $this->manifestFile();
        if (!is_file($file)) return [];
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    

    protected function validateMarketManifest(array $manifest, string $expectType): void
    {
        $slugRaw = (string)($manifest['slug'] ?? '');
        $slug    = $this->safeSlug($slugRaw);
        if ($slug === '' || $slug !== $slugRaw || strlen($slug) > 80) {
            throw new \RuntimeException('市场包 slug 无效');
        }
        if (($manifest['type'] ?? '') !== $expectType) {
            throw new \RuntimeException('市场包类型不匹配');
        }
        foreach (['name', 'version', 'author'] as $field) {
            if (isset($manifest[$field]) && !is_scalar($manifest[$field])) {
                throw new \RuntimeException('市场包 manifest 字段无效：' . $field);
            }
        }
        $version = trim((string)($manifest['version'] ?? ''));
        if ($version !== '' && !preg_match('/^[0-9A-Za-z_.+\-]{1,40}$/', $version)) {
            throw new \RuntimeException('市场包版本号无效');
        }
    }

    

    protected function findMarketManifest(\ZipArchive $zip): array
    {
        foreach (['market.json', 'manifest.json'] as $name) {
            $raw = $zip->getFromName($name);
            if ($raw !== false) {
                return ['raw' => (string)$raw, 'prefix' => ''];
            }
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name)) {
                throw new \RuntimeException('市场包包含非法路径：' . $name);
            }
            if (substr_count(trim($name, '/'), '/') === 1 && preg_match('#/market\.json$#', $name)) {
                $raw = $zip->getFromName($name);
                if ($raw !== false) {
                    return ['raw' => (string)$raw, 'prefix' => substr($name, 0, strrpos($name, '/') + 1)];
                }
            }
        }
        return ['raw' => '', 'prefix' => ''];
    }

    protected function extractZipSafe(\ZipArchive $zip, string $target, string $stripPrefix = ''): void
    {
        $targetRoot = realpath($target) ?: $target;
        $stripPrefix = str_replace('\\', '/', $stripPrefix);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name)) {
                throw new \RuntimeException('市场包包含非法路径：' . $name);
            }
            $sourceName = $name;
            if ($stripPrefix !== '') {
                if ($name === $stripPrefix) continue;
                if (!str_starts_with($name, $stripPrefix)) continue;
                $name = substr($name, strlen($stripPrefix));
                if ($name === '') continue;
            }
            $dest    = $target . '/' . $name;
            $destDir = str_ends_with($name, '/') ? rtrim($dest, '/\\') : dirname($dest);
            if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                throw new \RuntimeException('市场包目录创建失败：' . $name);
            }
            $realDir = realpath($destDir);
            if (!$realDir || strpos($realDir, $targetRoot) !== 0) {
                throw new \RuntimeException('市场包解压路径越界：' . $name);
            }
            if (str_ends_with($name, '/')) continue;
            if (is_link($dest)) {
                throw new \RuntimeException('市场包解压目标为符号链接：' . $name);
            }
            $in  = $zip->getStream($sourceName);
            if (!$in) throw new \RuntimeException('市场包文件读取失败：' . $name);
            $out = fopen($dest, 'wb');
            if (!$out) { fclose($in); throw new \RuntimeException('市场包文件写入失败：' . $name); }
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        }
    }

    

    protected function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        @chmod($dir, 0775);
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $path = $file->getPathname();
            if ($file->isLink()) { @unlink($path); continue; }
            @chmod($path, $file->isDir() ? 0775 : 0664);
            $file->isDir() ? @rmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    protected function copyDir(string $from, string $to, string $expectedSlug = ''): void
    {
        if (!is_dir($from)) return;
        @mkdir($to, 0755, true);
        $root = realpath($to) ?: $to;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            if ($file->isLink()) continue;
            $rel = str_replace('\\', '/', $it->getSubPathName());
            if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/') || preg_match('#^[A-Za-z]:#', $rel)) {
                throw new \RuntimeException('扩展包包含非法路径：' . $rel);
            }
            if ($expectedSlug !== '' && $rel === $this->manifestFile()) {
                $mf = json_decode((string)file_get_contents($file->getPathname()), true) ?: [];
                $s  = $this->safeSlug((string)($mf['slug'] ?? ''));
                if ($s !== $expectedSlug) throw new \RuntimeException('扩展包 slug 不匹配');
            }
            $target    = $to . '/' . $rel;
            $targetDir = $file->isDir() ? $target : dirname($target);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new \RuntimeException('扩展包目录创建失败：' . $rel);
            }
            $realDir = realpath($targetDir);
            if (!$realDir || strpos($realDir, $root) !== 0) {
                throw new \RuntimeException('扩展包恢复路径越界：' . $rel);
            }
            if (!$file->isDir()) {
                if (is_link($target)) throw new \RuntimeException('扩展包目标为符号链接：' . $rel);
                if (!@copy($file->getPathname(), $target)) throw new \RuntimeException('扩展包文件复制失败：' . $rel);
            }
        }
    }

    

    protected function runUninstallFiles(string $slug, string $target): void
    {
        $files = [];
        foreach (['uninstall.sql', 'database/uninstall.sql'] as $rel) {
            $path = $target . '/' . $rel;
            if (is_file($path)) $files[$rel] = $path;
        }
        foreach (glob($target . '/uninstall/*.sql') ?: [] as $path) {
            $files['uninstall/' . basename($path)] = $path;
        }
        ksort($files, SORT_NATURAL);
        if (!$files) return;

        $db = Database::connection();
        try {
            $db->beginTransaction();
            foreach ($files as $rel => $path) {
                $sql = trim((string)file_get_contents($path));
                if ($sql === '') continue;
                foreach ($this->splitSql($sql) as $statement) {
                    $this->assertSafeUninstallSql($statement, $rel, $slug);
                    $db->exec($statement);
                }
            }
            if ($this->tableExists('extension_migrations')) {
                $stmt = $db->prepare('DELETE FROM extension_migrations WHERE extension_type=:type AND slug=:slug');
                $stmt->execute([':type' => $this->type, ':slug' => $slug]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw new \RuntimeException('插件卸载数据库清理失败：' . $e->getMessage(), 0, $e);
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = Database::connection()->prepare('SHOW TABLES LIKE :table');
            $stmt->execute([':table' => $table]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }


    protected function runDatabaseFiles(string $slug, string $target): void
    {
        $files = [];
        foreach (['install.sql', 'database/install.sql'] as $rel) {
            $path = $target . '/' . $rel;
            if (is_file($path)) $files[$rel] = $path;
        }
        foreach (glob($target . '/migrations/*.sql') ?: [] as $path) {
            $files['migrations/' . basename($path)] = $path;
        }
        ksort($files, SORT_NATURAL);
        if (!$files) return;

        $db = Database::connection();
        $db->exec("CREATE TABLE IF NOT EXISTS extension_migrations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            extension_type VARCHAR(20) NOT NULL DEFAULT 'plugin',
            slug VARCHAR(100) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_hash VARCHAR(128) NOT NULL,
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY uk_ext_migration (extension_type, slug, file_path, file_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        foreach ($files as $rel => $path) {
            $sql = trim((string)file_get_contents($path));
            if ($sql === '') continue;
            $hash = hash('sha256', $sql);
            $stmt = $db->prepare('SELECT COUNT(*) FROM extension_migrations WHERE extension_type=:type AND slug=:slug AND file_path=:file AND file_hash=:hash');
            $stmt->execute([':type' => $this->type, ':slug' => $slug, ':file' => $rel, ':hash' => $hash]);
            if ((int)$stmt->fetchColumn() > 0) continue;

            foreach ($this->splitSql($sql) as $statement) {
                $this->assertSafeSql($statement, $rel);
                $db->exec($statement);
            }
            $db->prepare('INSERT INTO extension_migrations (extension_type,slug,file_path,file_hash,executed_at) VALUES (:type,:slug,:file,:hash,NOW())')
                ->execute([':type' => $this->type, ':slug' => $slug, ':file' => $rel, ':hash' => $hash]);
        }
    }

    private function assertSafeUninstallSql(string $statement, string $file, string $slug): void
    {
        $this->assertSafeSql($statement, $file);
        $normalized = strtolower(preg_replace('/\s+/', ' ', trim($statement)) ?? $statement);
        $allowed = false;
        if (preg_match('/^drop\s+table\s+(if\s+exists\s+)?`?plugin_' . preg_quote(strtolower($slug), '/') . '[a-z0-9_]*`?\b/i', $normalized)) {
            $allowed = true;
        } elseif (preg_match('/^delete\s+from\s+`?(settings|extension_migrations|extension_error_logs)`?\b/i', $normalized)) {
            $allowed = true;
        } elseif (preg_match('/^delete\s+from\s+`?[a-z0-9_]+`?\s+where\s+/i', $normalized)) {
            $allowed = true;
        } elseif (preg_match('/^alter\s+table\s+`?[a-z0-9_]+`?\s+drop\s+(column|index|key)\b/i', $normalized)) {
            $allowed = true;
        }
        if (!$allowed) {
            throw new \RuntimeException('卸载 SQL 只允许 DROP 插件表、带 WHERE 的 DELETE 或 DROP COLUMN/INDEX：' . $file);
        }
        if (preg_match('/^delete\s+from\s+`?(settings|extension_migrations|extension_error_logs)`?\b/i', $normalized)
            && !preg_match('/\bwhere\b/', $normalized)) {
            throw new \RuntimeException('卸载 SQL 删除系统表必须带 WHERE：' . $file);
        }
    }

    private function assertSafeSql(string $statement, string $file): void
    {
        $normalized = strtolower(preg_replace('/\s+/', ' ', trim($statement)) ?? $statement);
        $forbidden = [
            '/\b(drop|create|alter)\s+database\b/',
            '/\b(create|alter|drop)\s+user\b/',
            '/\bgrant\b/', '/\brevoke\b/',
            '/\bload\s+data\b/',
            '/\binto\s+(out|dump)file\b/',
            '/\binstall\s+plugin\b/',
            '/\buninstall\s+plugin\b/',
            '/\bset\s+global\b/',
            '/\bshutdown\b/',
        ];
        foreach ($forbidden as $pattern) {
            if (preg_match($pattern, $normalized)) {
                throw new \RuntimeException('SQL 包含禁止语句：' . $file);
            }
        }
    }

    private function splitSql(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $statements = [];
        $buffer = '';
        $quote = null;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch  = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';
            if ($quote === null && $ch === '-' && $next === '-') { while ($i < $len && $sql[$i] !== "\n") $i++; continue; }
            if ($quote === null && $ch === '#') { while ($i < $len && $sql[$i] !== "\n") $i++; continue; }
            if ($quote === null && $ch === '/' && $next === '*') { $i += 2; while ($i < $len - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) $i++; $i++; continue; }
            if (($ch === "'" || $ch === '"') && ($i === 0 || $sql[$i - 1] !== '\\')) {
                if ($quote === null) $quote = $ch;
                elseif ($quote === $ch) $quote = null;
            }
            if ($quote === null && $ch === ';') { $stmt = trim($buffer); if ($stmt !== '') $statements[] = $stmt; $buffer = ''; continue; }
            $buffer .= $ch;
        }
        $stmt = trim($buffer);
        if ($stmt !== '') $statements[] = $stmt;
        return $statements;
    }

    

    protected function safeSlug(string $slug): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
    }
}
