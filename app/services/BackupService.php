<?php

namespace App\Services;

use App\Core\Database;
use ZipArchive;

class BackupService
{
    private string $dir;
    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?: dirname(__DIR__, 2);
        $this->dir = $this->root . '/storage/backups/manual';
        @mkdir($this->dir, 0755, true);
    }

    public function list(): array
    {
        $items = [];
        foreach (glob($this->dir . '/*.zip') ?: [] as $file) {
            $items[] = ['file'=>$file, 'name'=>basename($file), 'size'=>filesize($file) ?: 0, 'created_at'=>date('Y-m-d H:i:s', filemtime($file) ?: time())];
        }
        rsort($items);
        return $items;
    }

    public function create(): string
    {
        if (!class_exists(ZipArchive::class)) throw new \RuntimeException('PHP ZipArchive 不可用');
        $name = 'manual_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.zip';
        $file = $this->dir . '/' . $name;
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE) !== true) throw new \RuntimeException('备份文件创建失败');
        $zip->addFromString('manifest.json', json_encode(['created_at'=>date('c'), 'type'=>'manual'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        $zip->addFromString('database.sql', $this->dumpDatabase());
        foreach (['uploads'] as $rel) {
            $base = $this->root . '/storage/' . $rel;
            if (!is_dir($base)) continue;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) if ($f->isFile()) $zip->addFile($f->getPathname(), 'storage/' . $rel . '/' . $it->getSubPathName());
        }
        $zip->close();
        return $file;
    }

    public function restoreUploads(string $name): void
    {
        $file = $this->dir . '/' . basename($name);
        if (!is_file($file)) throw new \RuntimeException('备份不存在');
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) throw new \RuntimeException('备份打开失败');
        $uploadsRoot = realpath($this->root . '/storage/uploads') ?: ($this->root . '/storage/uploads');
        if (!is_dir($uploadsRoot)) @mkdir($uploadsRoot, 0755, true);
        for ($i=0;$i<$zip->numFiles;$i++) {
            $stat = $zip->statIndex($i); $n = str_replace('\\', '/', (string)($stat['name'] ?? ''));
            if (!str_starts_with($n, 'storage/uploads/')) continue;
            if (str_ends_with($n, '/')) continue;
            if ($n === '' || str_contains($n, '..') || str_starts_with($n, '/') || preg_match('#^[A-Za-z]:#', $n) || $this->isUnsafeUploadEntry($n)) {
                $zip->close();
                throw new \RuntimeException('备份包含非法路径：' . $n);
            }
            $target = $this->root . '/' . $n;
            $targetDir = dirname($target);
            @mkdir($targetDir, 0755, true);
            $realDir = realpath($targetDir);
            if (!$realDir || strpos($realDir, $uploadsRoot) !== 0) {
                $zip->close();
                throw new \RuntimeException('备份恢复路径越界：' . $n);
            }
            $in = $zip->getStream($n);
            if (!$in) {
                $zip->close();
                throw new \RuntimeException('备份文件读取失败：' . $n);
            }
            if (is_link($target)) {
                fclose($in);
                $zip->close();
                throw new \RuntimeException('备份恢复目标为符号链接：' . $n);
            }
            $out = fopen($target, 'wb');
            if (!$out) {
                fclose($in);
                $zip->close();
                throw new \RuntimeException('备份文件写入失败：' . $n);
            }
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        }
        $zip->close();
    }

    private function isUnsafeUploadEntry(string $name): bool
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $blocked = ['php','php3','php4','php5','php7','php8','phtml','phar','cgi','pl','py','jsp','asp','aspx','sh','bash','zsh','fish','exe','dll','so','dylib','com','bat','cmd','msi','jar'];
        return in_array($ext, $blocked, true);
    }

    private function dumpDatabase(): string
    {
        $db = Database::connection();
        $out = "-- ClayBBS backup " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n";
        $tables = $db->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        foreach ($tables as $table) {
            $row = $db->query('SHOW CREATE TABLE `' . str_replace('`','``',(string)$table) . '`')->fetch(\PDO::FETCH_ASSOC);
            $create = (string)($row['Create Table'] ?? array_values($row ?: [''])[1] ?? '');
            $out .= "\nDROP TABLE IF EXISTS `{$table}`;\n{$create};\n";
            $stmt = $db->query('SELECT * FROM `' . str_replace('`','``',(string)$table) . '`');
            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $cols = array_map(static fn($c) => '`' . str_replace('`','``',$c) . '`', array_keys($r));
                $vals = array_map(static fn($v) => $v === null ? 'NULL' : Database::connection()->quote((string)$v), array_values($r));
                $out .= 'INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n";
            }
        }
        return $out . "SET FOREIGN_KEY_CHECKS=1;\n";
    }
}
