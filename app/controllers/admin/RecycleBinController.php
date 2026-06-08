<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminAuditLogModel;
use App\Models\RecycleBinModel;

class RecycleBinController
{
    public function __construct()
    {
        AdminAuth::check();
        if (!Permission::can('thread.delete_any') && !Permission::can('post.delete_any')) Permission::require('thread.delete_any');
    }

    public function index(): void
    {
        $model = new RecycleBinModel();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $id = (int)($_POST['id'] ?? 0);
            $action = (string)($_POST['_action'] ?? '');
            $row = $model->find($id);
            if ($row && empty($row['restored_at']) && empty($row['purged_at']) && $this->canOperateRecycleRow($row)) {
                if ($action === 'restore') $this->restore($row);
                if ($action === 'purge') $model->markPurged($id);
                (new AdminAuditLogModel())->record('recycle.' . $action, (string)$row['target_type'], (int)$row['target_id'], ['recycle_id'=>$id]);
            }
            redirect_or_ajax('/admin.php?path=recycle-bin');
        }
        $type = trim((string)($_GET['type'] ?? ''));
        $items = $model->list($type);
        require dirname(__DIR__, 2) . '/views/admin/content/recycle_bin.php';
    }

    private function canOperateRecycleRow(array $row): bool
    {
        $type = (string)($row['target_type'] ?? '');
        $snapshot = json_decode((string)($row['snapshot'] ?? '{}'), true) ?: [];
        $sectionId = (int)($snapshot['section_id'] ?? 0);
        if ($sectionId <= 0 && $type === 'post') {
            $post = (new \App\Models\AdminPostModel())->find((int)($row['target_id'] ?? 0));
            $sectionId = $post ? (int)($post['section_id'] ?? 0) : 0;
        }
        if ($type === 'thread') {
            return Permission::can('thread.delete_any', 'global')
                || Permission::can('thread.delete_any', 'section', $sectionId)
                || Permission::can('thread.delete_any', 'category', $this->categoryIdBySection($sectionId));
        }
        if ($type === 'post') {
            return Permission::can('post.delete_any', 'global')
                || Permission::can('post.delete_any', 'section', $sectionId)
                || Permission::can('post.delete_any', 'category', $this->categoryIdBySection($sectionId));
        }
        return false;
    }

    private function categoryIdBySection(int $sectionId): ?int
    {
        if ($sectionId <= 0) return null;
        try {
            $section = (new \App\Models\SectionModel())->findById($sectionId);
            return $section ? (int)($section['category_id'] ?? 0) : null;
        } catch (\Throwable $e) { return null; }
    }

    private function restore(array $row): void
    {
        $snapshot = json_decode((string)($row['snapshot'] ?? '{}'), true) ?: [];
        $type = (string)$row['target_type'];
        $id = (int)$row['target_id'];
        if ($type === 'thread') {
            Database::connection()->prepare("UPDATE threads SET status=:status WHERE id=:id")->execute([':status'=>(string)($snapshot['status'] ?? 'published'), ':id'=>$id]);
        } elseif ($type === 'post') {
            Database::connection()->prepare("UPDATE posts SET status=:status WHERE id=:id")->execute([':status'=>(string)($snapshot['status'] ?? 'published'), ':id'=>$id]);
        }
        (new RecycleBinModel())->markRestored((int)$row['id']);
    }
}
