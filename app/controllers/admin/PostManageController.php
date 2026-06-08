<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminPostModel;
use App\Models\RecycleBinModel;
use App\Models\AdminAuditLogModel;

class PostManageController
{
    public function __construct()
    {
        AdminAuth::check();
        if (!Permission::can('post.delete_any') && !Permission::can('review.post')) {
            Permission::require('post.delete_any');
        }
    }

    public function index(): void
    {
        $keyword = trim((string)($_GET['kw'] ?? ''));
        $filters = [
            'status' => trim((string)($_GET['status'] ?? '')),
            'thread_id' => (int)($_GET['thread_id'] ?? 0),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = 20;
        $posts = [];
        $total = 0;
        $model = new AdminPostModel();
        try {
            $posts = $model->list($keyword, $filters, $page, $pageSize);
            $total = $model->count($keyword, $filters);
        } catch (\Throwable $e) {
            $posts = [];
        }
        $totalPages = max(1, (int)ceil($total / $pageSize));
        require dirname(__DIR__, 2) . '/views/admin/content/posts.php';
    }

    public function action(): void
    {
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if ($id > 0) {
            $model = new AdminPostModel();
            if ($status === 'delete') {
                Permission::require('post.delete_any');
                $oldPost = (new \App\Models\PostModel())->find($id);
                $model->delete($id);
                (new AdminAuditLogModel())->record('post.delete', 'post', $id, ['hard_delete' => true, 'thread_id' => (int)($oldPost['thread_id'] ?? 0)]);
            } elseif (in_array($status, ['published', 'pending', 'hidden', 'deleted'], true)) {
                if ($status === 'published' || $status === 'pending') {
                    Permission::require('review.post');
                } else {
                    Permission::require('post.delete_any');
                }
                $oldPost = (new \App\Models\PostModel())->find($id);
                if ($status === 'deleted' && $oldPost) (new RecycleBinModel())->add('post', $id, mb_substr(strip_tags((string)($oldPost['content'] ?? '')), 0, 80), $oldPost);
                $model->updateStatus($id, $status);
                (new AdminAuditLogModel())->record($status === 'deleted' ? 'post.recycle' : 'post.status', 'post', $id, ['status'=>$status]);
                if ($status === 'published' && $oldPost && ($oldPost['status'] ?? '') !== 'published') { try { (new \App\Services\TaskService())->recordAction((int)$oldPost['user_id'], 'post_publish', 'post', $id); } catch (\Throwable $e) {} }
            }
        }
        redirect_or_ajax('/admin.php?path=posts');
    }

    public function edit(): void
    {
        Permission::require('post.delete_any');
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $post = $id > 0 ? (new AdminPostModel())->find($id) : null;
        $error = '';
        if (!$post) {
            redirect_or_ajax('/admin.php?path=posts');
        }
        require dirname(__DIR__, 2) . '/views/admin/content/post_edit.php';
    }

    public function update(): void
    {
        Permission::require('post.delete_any');
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        $model = new AdminPostModel();
        $post = $id > 0 ? $model->find($id) : null;
        $error = '';
        if (!$post) {
            redirect_or_ajax('/admin.php?path=posts');
        }
        $content = trim((string)($_POST['content'] ?? ''));
        $plainContent = trim(strip_tags($content));
        if ($content === '' || mb_strlen($plainContent) < 2 || mb_strlen($plainContent) > 5000) {
            $error = '回复内容长度需在 2-5000 字之间';
            require dirname(__DIR__, 2) . '/views/admin/content/post_edit.php';
            return;
        }
        $model->updateContent($id, $content);
        (new AdminAuditLogModel())->record('post.update', 'post', $id);
        redirect_or_ajax('/admin.php?path=posts');
    }
}
