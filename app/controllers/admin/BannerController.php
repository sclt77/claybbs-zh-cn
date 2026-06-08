<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminBannerModel;

require_once dirname(__DIR__, 2) . '/helpers/upload.php';

class BannerController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.banner');
    }

    public function index(): void
    {
        $model = new AdminBannerModel();
        $banners = [];
        $error = '';

        $tabRaw = (string)($_GET['tab'] ?? 'home');
        $tab = in_array($tabRaw, ['home', 'section'], true) ? $tabRaw : 'home';
        try {
            $banners = $model->list($tab);
        } catch (\Throwable $e) {
            $banners = [];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = $_POST['_action'] ?? '';

            if ($action === 'create') {
                $title = trim($_POST['title'] ?? '');
                if ($title === '') {
                    $error = '请填写标题';
                } else {
                    try {
                        $image = safe_url(trim($_POST['image'] ?? ''));
                        $uploaded = upload_image($_FILES['image_file'] ?? [], 'banners');
                        if ($uploaded !== '') {
                            $image = $uploaded;
                        }

                        $model->create([
                            'title'       => $title,
                            'description' => trim($_POST['description'] ?? ''),
                            'image'       => $image,
                            'url'         => safe_url(trim($_POST['url'] ?? '')),
                            'sort_order'  => (int) ($_POST['sort_order'] ?? 0),
                            'thread_id'   => (int)($_POST['thread_id'] ?? 0),
                            'placement'   => $tab,
                        ]);
                        redirect_or_ajax('/admin.php?path=banners&tab=' . $tab);
                    } catch (\Throwable $e) {
                        $error = '创建失败：' . $e->getMessage();
                    }
                }
            } elseif ($action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                if ($id > 0 && $title !== '') {
                    try {
                        $image = safe_url(trim($_POST['image'] ?? ''));
                        $uploaded = upload_image($_FILES['image_file'] ?? [], 'banners');
                        if ($uploaded !== '') {
                            $image = $uploaded;
                        }

                        $model->update($id, [
                            'title'       => $title,
                            'description' => trim($_POST['description'] ?? ''),
                            'image'       => $image,
                            'url'         => safe_url(trim($_POST['url'] ?? '')),
                            'sort_order'  => (int) ($_POST['sort_order'] ?? 0),
                            'thread_id'   => (int)($_POST['thread_id'] ?? 0),
                        ]);
                    } catch (\Throwable $e) {
                        $error = '更新失败：' . $e->getMessage();
                    }
                }
                redirect_or_ajax('/admin.php?path=banners&tab=' . $tab);
            } elseif ($action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $model->delete($id);
                }
                redirect_or_ajax('/admin.php?path=banners&tab=' . $tab);
            } elseif ($action === 'status') {
                $id = (int) ($_POST['id'] ?? 0);
                $status = $_POST['status'] ?? '';
                if ($id > 0 && in_array($status, ['active', 'inactive'], true)) {
                    $model->updateStatus($id, $status);
                }
                redirect_or_ajax('/admin.php?path=banners&tab=' . $tab);
            }
        }

        require dirname(__DIR__, 2) . '/views/admin/content/banners.php';
    }
}
