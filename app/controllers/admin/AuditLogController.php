<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminAuditLogModel;

class AuditLogController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.audit_log');
    }

    public function index(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $logs = (new AdminAuditLogModel())->list($q, 200);
        require dirname(__DIR__, 2) . '/views/admin/content/audit_logs.php';
    }
}
