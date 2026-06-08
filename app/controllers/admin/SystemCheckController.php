<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;

class SystemCheckController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.system_check');
    }

    public function index(): void
    {
        $root = dirname(__DIR__, 3);
        $installed = is_file($root . '/install.lock') && is_file($root . '/config/database.php');
        $checks = [];

        $checks[] = $this->check('安装状态', $installed, $installed ? '已安装' : '未安装：分发包首次部署时这是正常状态，完成安装后会生成 config/database.php 与 install.lock', $installed ? 'ok' : 'warn');
        $checks[] = $this->check('数据库配置', is_file($root . '/config/database.php'), $root . '/config/database.php', is_file($root . '/config/database.php') ? 'ok' : 'warn');
        $checks[] = $this->check('安装锁', is_file($root . '/install.lock'), $root . '/install.lock', is_file($root . '/install.lock') ? 'ok' : 'warn');

        foreach (['storage', 'storage/uploads', 'storage/logs', 'storage/updates', 'storage/backups', 'storage/keys', 'plugins', 'themes'] as $dir) {
            $path = $root . '/' . $dir;
            $checks[] = $this->check($dir . ' 可写', is_dir($path) && is_writable($path), $path, 'error');
        }

        foreach (['pdo_mysql', 'json', 'mbstring', 'fileinfo', 'curl', 'openssl', 'zip'] as $ext) {
            $loaded = extension_loaded($ext);
            $checks[] = $this->check('PHP 扩展 ' . $ext, $loaded, $loaded ? '已加载' : '未加载', $ext === 'zip' ? 'warn' : 'error');
        }

        $this->checkInstallSql($root, $checks);
        $this->checkUpdateCenterTemplate($root, $checks);
        $this->checkRuntimeSafety($root, $checks);
        $this->checkPluginRuntime($root, $checks);
        if ($installed) {
            $this->checkDatabase($checks);
        } else {
            $checks[] = $this->check('数据库结构', true, '未安装状态跳过数据库连接检查；完成安装后会检查数据表与关键字段', 'info');
        }

        require dirname(__DIR__, 2) . '/views/admin/content/system_check.php';
    }


    private function checkRuntimeSafety(string $root, array &$checks): void
    {
        $checks[] = $this->check('插件目录可删除权限', is_writable($root . '/plugins'), $root . '/plugins', 'warn');
        $checks[] = $this->check('主题目录可写权限', is_writable($root . '/themes') || !is_dir($root . '/themes'), $root . '/themes', 'warn');
        $checks[] = $this->check('函数 exec 未禁用', !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true), '用于部分系统检测；禁用时不影响基础运行', 'info');
        $checks[] = $this->check('上传大小限制', $this->bytes(ini_get('upload_max_filesize')) >= 2 * 1024 * 1024, 'upload_max_filesize=' . ini_get('upload_max_filesize'), 'warn');
        $checks[] = $this->check('POST 大小限制', $this->bytes(ini_get('post_max_size')) >= 4 * 1024 * 1024, 'post_max_size=' . ini_get('post_max_size'), 'warn');
        $checks[] = $this->check('PHP 错误日志', is_writable(dirname((string)ini_get('error_log'))) || (string)ini_get('error_log') === '', 'error_log=' . ((string)ini_get('error_log') ?: '未单独配置'), 'info');
        $checks[] = $this->check('open_basedir 限制', (string)ini_get('open_basedir') === '', (string)ini_get('open_basedir') ?: '未启用', 'info');
    }

    private function bytes(string|false $val): int
    {
        $val = trim((string)$val);
        if ($val === '') return 0;
        $unit = strtolower(substr($val, -1));
        $num = (float)$val;
        return (int)match($unit){'g'=>$num*1024*1024*1024,'m'=>$num*1024*1024,'k'=>$num*1024,default=>$num};
    }

    private function checkInstallSql(string $root, array &$checks): void
    {
        $sqlFile = $root . '/database/install.sql';
        $checks[] = $this->check('安装 SQL', is_file($sqlFile), $sqlFile, 'error');
        if (!is_file($sqlFile)) {
            return;
        }
        $sql = (string) file_get_contents($sqlFile);
        $requiredTables = [
            'users','categories','sections','threads','posts','thread_drafts','reply_drafts','ai_providers','ai_review_logs',
            'attachments','mentions','user_follows','verification_types','verification_requests','user_verifications',
            'thread_favorites','content_likes','content_reports','user_blocks','thread_rewards','currencies','wallets',
            'wallet_transactions','levels','user_growth_stats','user_exp_logs','tasks','user_task_progress','user_task_refs',
            'task_submissions','user_notification_settings','banners','announcements','roles','permissions','role_permissions',
            'user_roles','settings','system_messages','user_message_reads','admin_audit_logs','recycle_bin',
        ];
        foreach ($requiredTables as $table) {
            $ok = preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`' . preg_quote($table, '/') . '`/i', $sql) === 1;
            $checks[] = $this->check('安装 SQL 数据表 ' . $table, $ok, $ok ? '已包含' : '缺失 CREATE TABLE', 'error');
        }
        foreach ([
            'system_messages.category',
            'wallet_transactions.operator_id',
            'wallet_transactions.reversal_of',
            'wallet_transactions.ref_type',
            'wallet_transactions.ref_id',
            'currencies.icon',
            'currencies.exchange_rate',
            'users.email_verified',
            'threads.reply_count',
            'posts.parent_id',
        ] as $field) {
            [$table, $column] = explode('.', $field, 2);
            $ok = preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`' . preg_quote($table, '/') . '`[\s\S]*?`' . preg_quote($column, '/') . '`/i', $sql) === 1;
            $checks[] = $this->check('安装 SQL 字段 ' . $field, $ok, $ok ? '已包含' : '缺失字段', 'error');
        }
    }

    private function checkUpdateCenterTemplate(string $root, array &$checks): void
    {
        $path = $root . '/config/update-center.php';
        $exists = is_file($path);
        $checks[] = $this->check('更新中心模板', $exists, $path, 'warn');
        if (!$exists) {
            return;
        }
        $cfg = include $path;
        $ok = is_array($cfg) && !empty($cfg['url']) && array_key_exists('public_key', $cfg) && array_key_exists('license_key', $cfg);
        $checks[] = $this->check('更新中心模板字段', $ok, $ok ? '字段完整' : '字段缺失', 'warn');
    }

    private function checkDatabase(array &$checks): void
    {
        try {
            $db = \App\Core\Database::connection();
            $checks[] = $this->check('数据库连接', true, '连接成功', 'error');
            foreach (['users','threads','posts','system_messages','user_follows','wallets','wallet_transactions','user_notification_settings','levels','tasks','user_growth_stats','verification_types','user_verifications','thread_rewards','admin_audit_logs','recycle_bin','plugin_error_logs','thread_read_progress','content_reports'] as $table) {
                $stmt = $db->prepare('SHOW TABLES LIKE :t');
                $stmt->execute([':t' => $table]);
                $checks[] = $this->check('数据表 ' . $table, (bool)$stmt->fetchColumn(), $table, 'error');
            }
            foreach ([['system_messages','category'],['wallet_transactions','operator_id'],['wallet_transactions','reversal_of'],['wallet_transactions','ref_type'],['wallet_transactions','ref_id'],['currencies','icon'],['currencies','exchange_rate'],['users','email_verified'],['levels','level'],['tasks','reward_exp'],['threads','report_count'],['threads','read_complete_count'],['thread_read_progress','last_post_id']] as $pair) {
                [$table, $col] = $pair;
                $stmt = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :c");
                $stmt->execute([':c' => $col]);
                $checks[] = $this->check("字段 {$table}.{$col}", (bool)$stmt->fetchColumn(), $col, 'error');
            }
            foreach ([['threads','ft_threads_title_content'],['posts','ft_posts_content']] as $idx) {
                [$table, $key] = $idx;
                $stmt = $db->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name=:k");
                $stmt->execute([':k'=>$key]);
                $checks[] = $this->check("搜索索引 {$table}.{$key}", (bool)$stmt->fetchColumn(), $key, 'warn');
            }
        } catch (\Throwable $e) {
            $checks[] = $this->check('数据库连接', false, $e->getMessage(), 'error');
        }
    }

    private function checkPluginRuntime(string $root, array &$checks): void
    {
        try {
            $pm = new \App\Core\PluginManager();
            $plugins = $pm->all();
            $checks[] = $this->check('插件依赖状态', empty(array_filter($plugins, static fn($p) => empty($p['dependency_status']['ok']))), '已检查 ' . count($plugins) . ' 个插件', 'warn');
            $errors = $pm->recentErrors(10);
            $checks[] = $this->check('插件运行错误隔离', true, '插件启动异常会记录并跳过，不中断论坛启动', 'ok');
            $checks[] = $this->check('近期插件错误', empty($errors), empty($errors) ? '暂无近期插件错误' : ('发现 ' . count($errors) . ' 条近期错误，请到插件主题查看'), 'warn');
            $backupDir = $root . '/storage/backups/plugins';
            $checks[] = $this->check('插件回滚备份目录', is_dir($backupDir) || is_writable(dirname($backupDir)), $backupDir, 'warn');
        } catch (\Throwable $e) {
            $checks[] = $this->check('插件运行状态', false, $e->getMessage(), 'warn');
        }
    }

    private function check(string $name, bool $ok, string $detail, string $level = 'error'): array
    {
        return ['name' => $name, 'ok' => $ok, 'detail' => $detail, 'level' => $ok ? 'ok' : $level];
    }
}
