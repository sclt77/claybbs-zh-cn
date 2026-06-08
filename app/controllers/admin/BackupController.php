<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminAuditLogModel;
use App\Services\BackupService;

class BackupController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.backup');
    }

    public function index(): void
    {
        $svc = new BackupService();
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = (string)($_POST['_action'] ?? '');
            try {
                if ($action === 'create') {
                    $file = $svc->create();
                    (new AdminAuditLogModel())->record('backup.create', 'backup', 0, ['file'=>basename($file)]);
                    redirect_or_ajax('/admin.php?path=backups');
                }
                if ($action === 'restore_uploads') {
                    if ((string)($_POST['confirm_text'] ?? '') !== 'RESTORE') throw new \RuntimeException('请输入 RESTORE 确认恢复');
                    $svc->restoreUploads((string)($_POST['name'] ?? ''));
                    (new AdminAuditLogModel())->record('backup.restore_uploads', 'backup', 0, ['file'=>(string)($_POST['name'] ?? '')]);
                    redirect_or_ajax('/admin.php?path=backups');
                }
            } catch (\Throwable $e) { $error = $e->getMessage(); if (is_ajax_request()) ajax_error('操作失败：'.$error); }
        }
        $items = $svc->list();
        require dirname(__DIR__, 2) . '/views/admin/content/backups.php';
    }
}
