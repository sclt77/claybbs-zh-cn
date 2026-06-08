<?php

namespace App\Services;

class UpdateInstaller
{
    private string $storageDir;
    private string $backupDir;
    private string $projectRoot;

    public function __construct()
    {
        $this->projectRoot = dirname(__DIR__, 2);
        $this->storageDir = $this->projectRoot . '/storage/updates';
        $this->backupDir = $this->projectRoot . '/storage/backups';
        if (!is_dir($this->storageDir)) @mkdir($this->storageDir, 0755, true);
        if (!is_dir($this->backupDir)) @mkdir($this->backupDir, 0755, true);
    }

    public function savePackage(string $binary, string $filename): string
    {
        $path = $this->storageDir . '/' . $filename;
        file_put_contents($path, $binary);
        return $path;
    }

    public function verifyHash(string $packagePath, string $expectedHash): bool
    {
        $expectedHash = strtolower(trim($expectedHash));
        return $expectedHash !== '' && is_file($packagePath) && hash_equals($expectedHash, strtolower(hash_file('sha256', $packagePath)));
    }

    public function verifySignature(string $packagePath, string $signatureB64, string $publicKeyPem): bool
    {
        $data = file_get_contents($packagePath);
        $signature = base64_decode($signatureB64);
        $pubKey = openssl_pkey_get_public($publicKeyPem);
        if (!$pubKey) return false;
        $ok = openssl_verify($data, $signature, $pubKey, OPENSSL_ALGO_SHA256);
        return $ok === 1;
    }

    public function extract(string $packagePath): string
    {
        $token = bin2hex(random_bytes(8));
        $dir = $this->storageDir . '/extract_' . $token;
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $extractRoot = realpath($dir) ?: $dir;
        $zip = new \ZipArchive();
        if ($zip->open($packagePath) !== true) {
            throw new \RuntimeException('无法打开更新包');
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            $normalized = str_replace('\\', '/', $name);
            if (!$this->isSafeArchiveRel($normalized)) {
                $zip->close();
                throw new \RuntimeException('非法路径：' . $name);
            }
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            $normalized = str_replace('\\', '/', $name);
            $target = $dir . '/' . $normalized;
            $targetDir = str_ends_with($normalized, '/') ? rtrim($target, '/\\') : dirname($target);
            if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                $zip->close();
                throw new \RuntimeException('创建目录失败：' . $name);
            }
            $realDir = realpath($targetDir);
            if (!$realDir || !$this->pathInside($realDir, $extractRoot)) {
                $zip->close();
                throw new \RuntimeException('解压路径越界：' . $name);
            }
            if (str_ends_with($normalized, '/')) {
                continue;
            }
            if (is_link($target)) {
                $zip->close();
                throw new \RuntimeException('解压目标为符号链接：' . $name);
            }
            $content = $zip->getFromIndex($i);
            if ($content === false) {
                $zip->close();
                throw new \RuntimeException('读取压缩文件失败：' . $name);
            }
            if (@file_put_contents($target, $content) === false) {
                $zip->close();
                throw new \RuntimeException('写入压缩文件失败：' . $name);
            }
        }
        $zip->close();
        return $dir;
    }

    public function preflight(array $pkgInfo = [], string $kind = 'package'): array
    {
        $checks = [];
        $add = static function (string $name, bool $ok, string $message) use (&$checks): void {
            $checks[] = ['name' => $name, 'ok' => $ok, 'message' => $message];
        };
        $add('PHP 版本', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION);
        foreach (['zip' => 'ZipArchive', 'openssl' => 'OpenSSL', 'pdo_mysql' => 'PDO MySQL'] as $ext => $label) {
            $add($label, extension_loaded($ext), extension_loaded($ext) ? '已启用' : '未启用');
        }
        try { \App\Core\Database::connection()->query('SELECT 1'); $add('数据库连接', true, '正常'); } catch (\Throwable $e) { $add('数据库连接', false, $e->getMessage()); }
        foreach (['app', 'routes', 'assets', 'database', 'storage/updates', 'storage/backups'] as $path) {
            $full = $this->projectRoot . '/' . $path;
            if (!is_dir($full)) @mkdir($full, 0755, true);
            $add('目录可写：' . $path, is_writable($full), is_writable($full) ? '可写' : '不可写');
        }
        $from = trim((string)($pkgInfo['from_version'] ?? ''));
        $current = trim((string)($pkgInfo['current_version'] ?? ''));
        if ($kind === 'package' && $from !== '' && $current !== '') {
            $add('版本基线', $from === $current, '要求 ' . $from . '，当前 ' . $current);
        }
        return $checks;
    }

    public function preflightOk(array $checks): bool
    {
        foreach ($checks as $check) {
            if (empty($check['ok'])) return false;
        }
        return true;
    }

    public function healthCheck(string $expectedVersion = ''): array
    {
        $checks = [];
        $add = static function (string $name, bool $ok, string $message) use (&$checks): void {
            $checks[] = ['name' => $name, 'ok' => $ok, 'message' => $message];
        };
        try { \App\Core\Database::connection()->query('SELECT 1'); $add('数据库连接', true, '正常'); } catch (\Throwable $e) { $add('数据库连接', false, $e->getMessage()); }
        foreach (['index.php', 'admin.php', 'api.php', 'app/core/bootstrap.php'] as $file) {
            $add('关键文件：' . $file, is_file($this->projectRoot . '/' . $file), is_file($this->projectRoot . '/' . $file) ? '存在' : '缺失');
        }
        if ($expectedVersion !== '') {
            $manifest = $this->projectRoot . '/manifest.json';
            $actual = '';
            if (is_file($manifest)) {
                $data = json_decode((string)file_get_contents($manifest), true);
                $actual = is_array($data) ? (string)($data['version'] ?? '') : '';
            }
            $add('版本写入', $actual === '' || $actual === $expectedVersion, $actual === '' ? '未找到 manifest 版本，使用配置版本' : $actual);
        }
        return $checks;
    }

    public function checksOk(array $checks): bool
    {
        return $this->preflightOk($checks);
    }

    public function createSnapshot(array $paths = [], array $meta = []): string
    {
        $time = date('Ymd_His');
        $snap = $this->backupDir . '/' . $time;
        @mkdir($snap, 0755, true);
        if (empty($paths)) {
            $paths = ['app', 'routes', 'views', 'public', 'config', 'database'];
        }
        foreach ($paths as $p) {
            $src = $this->projectRoot . '/' . $p;
            if (is_dir($src) || is_file($src)) {
                $this->copy($src, $snap . '/' . $p, trim((string)$p, '/'));
            }
        }
        $meta['created_at'] = date('c');
        file_put_contents($snap . '/snapshot.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $snap;
    }

    public function validateManifest(string $extractDir, array $pkgInfo, string $kind, string $currentVersion): array
    {
        $manifestPath = $extractDir . '/manifest.json';
        if (!is_file($manifestPath)) {
            throw new \RuntimeException('更新包缺少 manifest.json');
        }
        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            throw new \RuntimeException('manifest.json 格式无效');
        }
        $to = (string)($manifest['version'] ?? $manifest['to_version'] ?? '');
        if ($kind === 'package') {
            $expectedTo = (string)($pkgInfo['to_version'] ?? '');
            if ($expectedTo !== '' && $to !== '' && $to !== $expectedTo) {
                throw new \RuntimeException('manifest 版本与官方返回版本不一致');
            }
            $from = (string)($manifest['from_version'] ?? $pkgInfo['from_version'] ?? '');
            if ($from !== '' && $from !== $currentVersion) {
                throw new \RuntimeException('更新包要求从 ' . $from . ' 升级，当前版本为 ' . $currentVersion);
            }
        }
        $hashes = $manifest['hashes'] ?? [];
        if (is_array($hashes)) {
            foreach ($hashes as $rel => $hash) {
                $rel = ltrim(str_replace('\\', '/', (string)$rel), '/');
                if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/')) {
                    throw new \RuntimeException('manifest 包含非法路径：' . $rel);
                }
                $path = $extractDir . '/' . $rel;
                if (!is_file($path)) {
                    throw new \RuntimeException('manifest 声明文件缺失：' . $rel);
                }
                if (!hash_equals(strtolower((string)$hash), strtolower(hash_file('sha256', $path)))) {
                    throw new \RuntimeException('manifest 文件哈希不匹配：' . $rel);
                }
            }
        }
        return $manifest;
    }

    public function ensureMigrationTable(): void
    {
        \App\Core\Database::connection()->exec("CREATE TABLE IF NOT EXISTS update_migrations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            version VARCHAR(50) NOT NULL,
            migration_file VARCHAR(255) NOT NULL,
            checksum VARCHAR(64) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            error_message TEXT DEFAULT NULL,
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_update_migration (version, migration_file)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function applyUpdate(string $extractDir, ?callable $onMeta = null): array
    {
        $log = [];
        $codeDir = $extractDir . '/code';
        $migrationsDir = $extractDir . '/migrations';
        $rollbackDir = $extractDir . '/rollback';

        $manifest = $extractDir . '/manifest.json';
        $meta = [];
        if (file_exists($manifest)) {
            $meta = json_decode(file_get_contents($manifest), true);
            if (is_array($meta) && $onMeta) {
                $onMeta($meta);
            }
        }
        $version = (string)($meta['version'] ?? $meta['to_version'] ?? '');

        if (is_dir($codeDir)) {
            $copyErrors = $this->copy($codeDir, $this->projectRoot);
            $this->applyDeleteList($codeDir . '/_delete.list');
            if (empty($copyErrors)) {
                $log[] = '\u4ee3\u7801\u66f4\u65b0\u5b8c\u6210';
            } else {
                $log[] = '\u4ee3\u7801\u66f4\u65b0\u5b8c\u6210\uff08\u90e8\u5206\u6587\u4ef6\u5931\u8d25\uff1a' . implode(', ', array_slice($copyErrors, 0, 5)) . '\uff09';
            }
        }

        if (is_dir($migrationsDir)) {
            $files = glob($migrationsDir . '/*.sql') ?: [];
            sort($files);
            foreach ($files as $file) {
                if (basename($file) === 'install.sql') continue;
                if ($this->migrationAlreadyExecuted($version, basename($file), hash_file('sha256', $file))) {
                    $log[] = '跳过已执行迁移：' . basename($file);
                    continue;
                }
                $this->runSqlFile($file, $version);
                $log[] = '\u6267\u884c\u8fc1\u79fb\uff1a' . basename($file);
            }
            $install = $migrationsDir . '/install.sql';
            if (file_exists($install)) {
                $this->runSqlFile($install, $version);
                $log[] = '\u6267\u884c\u6570\u636e\u5e93\u521d\u59cb\u5316\uff1ainstall.sql';
            }
        }

        if (is_dir($rollbackDir)) {
            $this->saveRollbackDir($rollbackDir);
            $log[] = '\u5df2\u4fdd\u5b58\u56de\u6eda\u5305';
        }

        if (function_exists('opcache_reset')) {
            opcache_reset();
            $log[] = 'OPcache \u5df2\u6e05\u9664';
        }

        return $log;
    }

    public function rollbackFromSnapshot(string $snapshotDir): void
    {
        $base = realpath($this->backupDir);
        $snapshot = realpath($snapshotDir);
        if (!$base || !$snapshot || !str_starts_with($snapshot, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) || !is_file($snapshot . '/snapshot.json')) {
            throw new \RuntimeException('快照路径无效');
        }
        $this->copy($snapshot, $this->projectRoot);
    }

    public function rollbackFromPackage(): void
    {
        $dir = $this->storageDir . '/rollback_last';
        if (!is_dir($dir)) {
            throw new \RuntimeException('\u672a\u627e\u5230\u56de\u6eda\u5305');
        }
        $this->copy($dir, $this->projectRoot);
    }

    private function migrationAlreadyExecuted(string $version, string $file, string $checksum): bool
    {
        if ($version === '') return false;
        $this->ensureMigrationTable();
        $stmt = \App\Core\Database::connection()->prepare("SELECT checksum FROM update_migrations WHERE version=:version AND migration_file=:file AND status='success' LIMIT 1");
        $stmt->execute([':version' => $version, ':file' => $file]);
        $old = $stmt->fetchColumn();
        return $old !== false && hash_equals((string)$old, $checksum);
    }

    private function recordMigration(string $version, string $file, string $checksum, string $status, string $error = ''): void
    {
        if ($version === '') return;
        $this->ensureMigrationTable();
        $stmt = \App\Core\Database::connection()->prepare("INSERT INTO update_migrations (version, migration_file, checksum, status, error_message, executed_at) VALUES (:version,:file,:checksum,:status,:error,NOW()) ON DUPLICATE KEY UPDATE checksum=VALUES(checksum), status=VALUES(status), error_message=VALUES(error_message), executed_at=NOW()");
        $stmt->execute([':version'=>$version, ':file'=>$file, ':checksum'=>$checksum, ':status'=>$status, ':error'=>$error !== '' ? $error : null]);
    }

    private function runSqlFile(string $path, string $version = ''): void
    {
        $db = \App\Core\Database::connection();
        $checksum = hash_file('sha256', $path);
        $migrationName = basename($path);
        $sql = (string) file_get_contents($path);
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        $sql = trim($sql);
        if ($sql === '') return;

        $clean = [];
        foreach (explode("\n", $sql) as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) continue;
            $clean[] = $line;
        }
        $sql = trim(implode("\n", $clean));
        if ($sql === '') return;

        $statements = [];
        $current = '';
        $delimiter = ';';
        foreach (explode("\n", $sql) as $line) {
            $trimmed = rtrim($line);
            if (str_starts_with($trimmed, 'DELIMITER')) {
                $parts = preg_split('/\s+/', $trimmed);
                if (isset($parts[1])) $delimiter = $parts[1];
                continue;
            }
            $current .= $line . "\n";
            if (str_ends_with($trimmed, $delimiter)) {
                $statements[] = trim($current);
                $current = '';
            }
        }
        if (trim($current) !== '') $statements[] = trim($current);

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || $stmt === $delimiter) continue;
            try {
                $this->assertSafeSqlStatement($stmt, basename($path));
                $st = $db->prepare($stmt);
                $st->execute();
                do {
                    $st->fetchAll();
                } while (@$st->nextRowset());
            } catch (\PDOException $e) {
                $this->recordMigration($version, $migrationName, $checksum, 'failed', $e->getMessage());
                throw new \RuntimeException('\u6267\u884c SQL \u5931\u8d25\uff08' . basename($path) . '\uff09\uff1a' . $e->getMessage(), 0, $e);
            }
        }
        $this->recordMigration($version, $migrationName, $checksum, 'success');
    }

    private function copy(string $src, string $dst, string $baseRel = ''): array
    {
        $errors = [];
        
        if (!is_dir($src)) {
            return $errors;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                $errors[] = 'skip-symlink:' . $iterator->getSubPathName();
                continue;
            }
            $target = $dst . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            $relPath = strtr($iterator->getSubPathName(), '\\', '/');
            if (!$this->isSafeArchiveRel($relPath)) {
                $errors[] = 'unsafe-path:' . $relPath;
                continue;
            }
            $checkRel = trim($baseRel !== '' ? ($baseRel . '/' . $relPath) : $relPath, '/');
            if ($this->isProtectedRel($checkRel)) {
                continue;
            }
            if ($item->isDir()) {
                if (!is_dir($target) && !@mkdir($target, 0755, true)) {
                    $errors[] = 'mkdir:' . $iterator->getSubPathName();
                }
            } else {
                $targetDir = dirname($target);
                if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
                    $errors[] = 'mkdir:' . dirname($relPath);
                    continue;
                }
                $realDir = realpath($targetDir);
                $dstRoot = realpath($dst) ?: $dst;
                if (!$realDir || !$this->pathInside($realDir, $dstRoot) || is_link($target)) {
                    $errors[] = 'outside:' . $relPath;
                    continue;
                }
                if (!@copy($item->getPathname(), $target)) {
                    $errors[] = 'copy:' . $relPath;
                }
            }
        }
        return $errors;
    }

    private function applyDeleteList(string $path): void
    {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $rel) {
            $rel = ltrim(str_replace('\\', '/', trim($rel)), '/');
            if (!$this->isSafeArchiveRel($rel)) continue;
            if ($this->isProtectedRel($rel)) continue;
            $target = $this->projectRoot . '/' . $rel;
            $realTarget = realpath($target);
            $root = realpath($this->projectRoot) ?: $this->projectRoot;
            if ($realTarget && $this->pathInside($realTarget, $root) && is_file($realTarget) && !is_link($realTarget)) @unlink($realTarget);
        }
    }

    private function assertSafeSqlStatement(string $stmt, string $file): void
    {
        $normalized = strtolower(preg_replace('/\s+/', ' ', trim($stmt)) ?? $stmt);
        $forbidden = [
            '/\b(drop|create|alter)\s+database\b/',
            '/\b(create|alter|drop)\s+user\b/',
            '/\bgrant\s+(all|select|insert|update|delete|create|alter|drop|execute|index|references|trigger|usage|proxy|file|reload|process|super|replication)\b/',
            '/\brevoke\s+(all|select|insert|update|delete|create|alter|drop|execute|index|references|trigger|usage|proxy|file|reload|process|super|replication)\b/',
            '/\bload\s+data\b/',
            '/\binto\s+(out|dump)file\b/',
            '/\binstall\s+plugin\b/',
            '/\buninstall\s+plugin\b/',
            '/\bset\s+global\b/',
            '/\bshutdown\b/',
        ];
        foreach ($forbidden as $pattern) {
            if (preg_match($pattern, $normalized)) {
                throw new \RuntimeException('迁移 SQL 包含禁止语句（' . $file . '）');
            }
        }
    }

    private function isSafeArchiveRel(string $rel): bool
    {
        $rel = str_replace('\\', '/', $rel);
        if ($rel === '' || str_starts_with($rel, '/') || preg_match('#^[A-Za-z]:#', $rel)) return false;
        foreach (explode('/', trim($rel, '/')) as $part) {
            if ($part === '' || $part === '.' || $part === '..') return false;
            if (preg_match('/[\x00-\x1F\x7F]/', $part)) return false;
        }
        return true;
    }

    private function pathInside(string $path, string $root): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $root = rtrim(str_replace('\\', '/', $root), '/');
        return $path === $root || str_starts_with($path, $root . '/');
    }

    private function isProtectedRel(string $rel): bool
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        $protected = ['config/database.php', 'config/update-center.php', '.env', 'install.lock', 'snapshot.json'];
        $prefixes = ['storage/uploads/', 'storage/certs/', 'storage/keys/', 'storage/logs/', 'storage/packages/', 'storage/updates/', 'storage/backups/', '.well-known/', 'ClayBE/', 'vendor/', '.git/', 'node_modules/'];
        $suffixes = ['.key', '.pem', '.crt', '.p12'];
        if (in_array($rel, $protected, true)) return true;
        foreach ($prefixes as $prefix) {
            if (str_starts_with($rel, $prefix)) return true;
        }
        foreach ($suffixes as $suffix) {
            if (str_ends_with(strtolower($rel), $suffix)) return true;
        }
        return false;
    }

    private function saveRollbackDir(string $rollbackDir): void
    {
        $target = $this->storageDir . '/rollback_last';
        if (is_dir($target)) {
            $this->rrmdir($target);
        }
        $this->copy($rollbackDir, $target);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
