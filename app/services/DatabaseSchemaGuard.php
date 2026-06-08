<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

class DatabaseSchemaGuard
{
    private string $root;
    private string $installSql;
    private string $cacheFile;
    private string $logFile;
    private int $ttl = 3600;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?: dirname(__DIR__, 2);
        $this->installSql = $this->root . '/database/install.sql';
        $this->cacheFile = $this->root . '/storage/schema-guard-cache.json';
        $this->logFile = $this->root . '/storage/logs/schema-guard.log';
    }

    /**
     * 后台兜底：管理员进入后台时检查核心 install.sql 中定义的表、字段、索引，缺失则自动补齐。
     * 默认一小时最多完整扫描一次，避免每个后台请求都扫 INFORMATION_SCHEMA。
     */
    public function checkAndRepair(bool $force = false): array
    {
        if (!$force && $this->cacheFresh()) {
            return ['ok' => true, 'cached' => true, 'repairs' => []];
        }

        $repairs = [];
        try {
            if (!is_file($this->installSql)) {
                throw new \RuntimeException('install.sql 不存在：' . $this->installSql);
            }
            $tables = $this->parseInstallSql((string)file_get_contents($this->installSql));
            $db = Database::connection();
            foreach ($tables as $table => $def) {
                if (!$this->tableExists($db, $table)) {
                    $db->exec($def['create']);
                    $repairs[] = "create_table:{$table}";
                    continue;
                }
                $repairs = array_merge($repairs, $this->repairColumns($db, $table, $def['columns']));
                $repairs = array_merge($repairs, $this->repairIndexes($db, $table, $def['indexes']));
            }
            $this->writeCache(true, $repairs, '');
            if ($repairs) $this->writeLog('REPAIRED ' . implode(', ', $repairs));
            return ['ok' => true, 'cached' => false, 'repairs' => $repairs];
        } catch (\Throwable $e) {
            $this->writeCache(false, $repairs, $e->getMessage());
            $this->writeLog('ERROR ' . $e->getMessage());
            // 后台不能因为兜底检测失败整体白屏；具体错误写日志。
            return ['ok' => false, 'cached' => false, 'repairs' => $repairs, 'error' => $e->getMessage()];
        }
    }

    private function cacheFresh(): bool
    {
        if (!is_file($this->cacheFile)) return false;
        $data = json_decode((string)file_get_contents($this->cacheFile), true);
        if (!is_array($data)) return false;
        return !empty($data['ok']) && (time() - (int)($data['checked_at'] ?? 0)) < $this->ttl;
    }

    private function writeCache(bool $ok, array $repairs, string $error): void
    {
        @mkdir(dirname($this->cacheFile), 0755, true);
        @file_put_contents($this->cacheFile, json_encode([
            'ok' => $ok,
            'checked_at' => time(),
            'repairs' => $repairs,
            'error' => $error,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function writeLog(string $line): void
    {
        @mkdir(dirname($this->logFile), 0755, true);
        @file_put_contents($this->logFile, '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
    }

    private function parseInstallSql(string $sql): array
    {
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        $tables = [];
        if (!preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`([^`]+)`\s*\((.*?)\)\s*ENGINE\s*=.*?;/is', $sql, $matches, PREG_SET_ORDER)) {
            return $tables;
        }
        foreach ($matches as $m) {
            $table = $m[1];
            $body = $m[2];
            $create = $m[0];
            $columns = [];
            $indexes = [];
            $prevColumn = null;
            foreach (explode("\n", $body) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $line = rtrim($line, ',');
                if (preg_match('/^`([^`]+)`\s+(.+)$/s', $line, $cm)) {
                    $name = $cm[1];
                    $columns[$name] = ['sql' => '`' . $name . '` ' . trim($cm[2]), 'after' => $prevColumn];
                    $prevColumn = $name;
                    continue;
                }
                $index = $this->parseIndexLine($line);
                if ($index !== null) $indexes[$index['name']] = $index['sql'];
            }
            $tables[$table] = ['create' => $create, 'columns' => $columns, 'indexes' => $indexes];
        }
        return $tables;
    }

    private function parseIndexLine(string $line): ?array
    {
        $upper = strtoupper($line);
        if (str_starts_with($upper, 'PRIMARY KEY')) {
            return ['name' => 'PRIMARY', 'sql' => $line];
        }
        if (preg_match('/^(UNIQUE\s+KEY|KEY|INDEX)\s+`([^`]+)`\s*(\(.+\))$/is', $line, $m)) {
            return ['name' => $m[2], 'sql' => $line];
        }
        return null;
    }

    private function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t');
        $stmt->execute([':t' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function repairColumns(PDO $db, string $table, array $columns): array
    {
        $repairs = [];
        $stmt = $db->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t');
        $stmt->execute([':t' => $table]);
        $existing = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        foreach ($columns as $name => $def) {
            if (isset($existing[$name])) continue;
            $after = $def['after'] ?? null;
            $afterSql = ($after && isset($existing[$after])) ? ' AFTER `' . str_replace('`', '``', $after) . '`' : ' FIRST';
            $db->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN ' . $def['sql'] . $afterSql);
            $existing[$name] = true;
            $repairs[] = "add_column:{$table}.{$name}";
        }
        return $repairs;
    }

    private function repairIndexes(PDO $db, string $table, array $indexes): array
    {
        $repairs = [];
        if (!$indexes) return $repairs;
        $stmt = $db->prepare('SELECT DISTINCT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t');
        $stmt->execute([':t' => $table]);
        $existing = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        foreach ($indexes as $name => $sql) {
            if (isset($existing[$name])) continue;
            // 只补普通/唯一索引；PRIMARY 在旧表中异常缺失时不自动补，避免破坏已有脏数据。
            if ($name === 'PRIMARY') continue;
            $db->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD ' . $sql);
            $existing[$name] = true;
            $repairs[] = "add_index:{$table}.{$name}";
        }
        return $repairs;
    }
}
