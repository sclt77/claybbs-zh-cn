<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminAnnouncementModel;

require_once dirname(__DIR__, 2) . '/helpers/upload.php';

class AnnouncementController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.announcement');
    }

    public function index(): void
    {
        $model = new AdminAnnouncementModel();
        $announcements = [];
        $error = '';

        try {
            $announcements = $model->list();
        } catch (\Throwable $e) {
            $announcements = [];
            $error = '加载公告失败：' . $e->getMessage();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = $_POST['_action'] ?? '';

            if ($action === 'create') {
                $title = trim($_POST['title'] ?? '');
                if ($title === '') {
                    $error = '请填写公告标题';
                } else {
                    try {
                        $image = safe_url(trim($_POST['image'] ?? ''));
                        $uploaded = upload_image($_FILES['image_file'] ?? [], 'announcements');
                        if ($uploaded !== '') {
                            $image = $uploaded;
                        }

                        $model->create([
                            'title'      => $title,
                            'content'    => trim($_POST['content'] ?? ''),
                            'image'      => $image,
                            'url'        => safe_url(trim($_POST['url'] ?? '')),
                            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                            'is_pinned'  => !empty($_POST['is_pinned']),
                            'popup_enabled' => !empty($_POST['popup_enabled']),
                            'popup_once' => !empty($_POST['popup_once']),
                        ]);
                        redirect_or_ajax('/admin.php?path=announcements');
                    } catch (\Throwable $e) {
                        $error = '创建公告失败：' . $e->getMessage();
                    }
                }
            } elseif ($action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                if ($id > 0 && $title !== '') {
                    try {
                        $image = safe_url(trim($_POST['image'] ?? ''));
                        $uploaded = upload_image($_FILES['image_file'] ?? [], 'announcements');
                        if ($uploaded !== '') {
                            $image = $uploaded;
                        }

                        $model->update($id, [
                            'title'      => $title,
                            'content'    => trim($_POST['content'] ?? ''),
                            'image'      => $image,
                            'url'        => safe_url(trim($_POST['url'] ?? '')),
                            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                            'is_pinned'  => !empty($_POST['is_pinned']),
                            'popup_enabled' => !empty($_POST['popup_enabled']),
                            'popup_once' => !empty($_POST['popup_once']),
                        ]);
                    } catch (\Throwable $e) {
                        $error = '更新公告失败：' . $e->getMessage();
                    }
                }
                redirect_or_ajax('/admin.php?path=announcements');
            } elseif ($action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $model->delete($id);
                }
                redirect_or_ajax('/admin.php?path=announcements');
            } elseif ($action === 'status') {
                $id = (int) ($_POST['id'] ?? 0);
                $status = $_POST['status'] ?? '';
                if ($id > 0 && in_array($status, ['active', 'inactive'], true)) {
                    $model->updateStatus($id, $status);
                }
                redirect_or_ajax('/admin.php?path=announcements');
            }
        }

        require dirname(__DIR__, 2) . '/views/admin/content/announcements.php';
    }
}
