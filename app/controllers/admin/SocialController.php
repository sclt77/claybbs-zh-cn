<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminSocialModel;

class SocialController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.social');
    }

    public function index(): void
    {
        $kw = trim((string)($_GET['kw'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $model = new AdminSocialModel();
        $follows = [];
        $total = 0;
        try {
            $follows = $model->follows($kw, $page, 30);
            $total = $model->countFollows($kw);
        } catch (\Throwable $e) {}
        $totalPages = max(1, (int)ceil($total / 30));
        require dirname(__DIR__, 2) . '/views/admin/content/social.php';
    }

    public function delete(): void
    {
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) (new AdminSocialModel())->deleteFollow($id);
        redirect_or_ajax('/admin.php?path=social');
    }
}
