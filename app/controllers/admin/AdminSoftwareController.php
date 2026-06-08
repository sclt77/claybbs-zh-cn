<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Hook;
use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\SoftwareModel;
use App\Models\SoftwareCategoryModel;
use App\Models\SoftwareScreenshotModel;
use App\Models\SoftwareDownloadModel;
use App\Models\SoftwareRatingModel;
use App\Models\SoftwareReviewModel;
use App\Models\SoftwareTypeModel;
use App\Models\NotificationModel;
use App\Models\SettingModel;
use App\Models\SoftwareVersionModel;

class AdminSoftwareController
{
    private SoftwareModel $softwareModel;
    private SoftwareCategoryModel $categoryModel;
    private SoftwareScreenshotModel $screenshotModel;

    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.software');
        $this->softwareModel   = new SoftwareModel();
        $this->categoryModel   = new SoftwareCategoryModel();
        $this->screenshotModel = new SoftwareScreenshotModel();
    }

    public function index(): void
    {
        $tab = (string)($_GET['tab'] ?? 'list');
        if (!in_array($tab, ['list', 'categories', 'types', 'recommendations', 'settings', 'pending', 'version_pending', 'stats'], true)) {
            $tab = 'list';
        }

        
        $stats = [
            'total'      => $this->softwareModel->count([]),
            'published'  => $this->softwareModel->count(['status' => 'published']),
            'pending'    => $this->softwareModel->count(['status' => 'pending']),
            'draft'      => $this->softwareModel->count(['status' => 'draft']),
            'rejected'   => $this->softwareModel->count(['status' => 'rejected']),
            'removed'    => $this->softwareModel->count(['status' => 'removed']),
            'recommended'=> $this->softwareModel->countRecommended(),
            'categories' => count($this->categoryModel->all()),
            'downloads'  => $this->softwareModel->totalDownloads(),
            'version_pending' => (new SoftwareVersionModel())->countPending(),
        ];

        
        $softwares = [];
        $total = 0;
        if ($tab === 'list') {
            $status = (string)($_GET['status'] ?? '');
            $q = trim((string)($_GET['q'] ?? ''));
            $page = max(1, (int)($_GET['page'] ?? 1));

            $filters = [];
            if ($status && in_array($status, ['draft','pending','published','rejected','removed'], true)) {
                $filters['status'] = $status;
            }
            if ($q) $filters['q'] = $q;

            $softwares = $this->softwareModel->all($filters, $page, 30);
            $total = $this->softwareModel->count($filters);
        }

        
        $pendingSoftwares = [];
        if ($tab === 'pending') {
            $pendingSoftwares = $this->softwareModel->all(['status' => 'pending'], 1, 50);
        }

        $pendingVersions = [];
        if ($tab === 'version_pending') {
            $pendingVersions = (new SoftwareVersionModel())->pending(1, 50);
        }

        
        $recommendedSoftwares = [];
        if ($tab === 'recommendations') {
            $recommendedSoftwares = $this->softwareModel->all(['status' => 'published', 'is_recommended' => 1], 1, 50, 's.recommended_at DESC, s.created_at DESC');
        }

        
        $categories = [];
        if ($tab === 'categories') {
            $categories = $this->categoryModel->all();
        }

        
        $softwareTypes = (new SoftwareTypeModel())->all(false);
        $typeMap = (new SoftwareTypeModel())->map(false);
        $settingModel = new SettingModel();
        $softwareSettings = $settingModel->all();

        require dirname(__DIR__, 2) . '/views/admin/software/index.php';
    }


    public function saveSettings(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $enabled = ($_POST['software_store_enabled'] ?? '0') === '1' ? '1' : '0';
        $siteMode = in_array(($_POST['site_mode'] ?? 'forum'), ['forum', 'store'], true) ? (string)$_POST['site_mode'] : 'forum';
        if ($enabled !== '1' && $siteMode === 'store') {
            $siteMode = 'forum';
        }
        try {
            (new SettingModel())->saveMany([
                'software_store_enabled' => $enabled,
                'site_mode' => $siteMode,
            ]);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => '保存失败：' . $e->getMessage()]);
        }
    }

    public function review(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $note = trim((string)($_POST['note'] ?? ''));

        $software = $this->softwareModel->find($id);
        if (!$software) {
            echo json_encode(['ok' => false, 'error' => '软件不存在']);
            return;
        }

        if ($action === 'approve') {
            $this->softwareModel->update($id, ['status' => 'published', 'admin_note' => $note]);
            (new NotificationModel())->create((int)$software['uploader_id'], 'software_approved', '软件审核通过', '您的软件《' . $software['name'] . '》已通过审核并上架。');
        } elseif ($action === 'reject') {
            $this->softwareModel->update($id, ['status' => 'rejected', 'admin_note' => $note]);
            (new NotificationModel())->create((int)$software['uploader_id'], 'software_rejected', '软件审核未通过', '您的软件《' . $software['name'] . '》未通过审核。原因：' . $note);
        } else {
            echo json_encode(['ok' => false, 'error' => '无效操作']);
            return;
        }

        echo json_encode(['ok' => true]);
    }

    public function reviewVersion(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $note = trim((string)($_POST['note'] ?? ''));
        $versionModel = new SoftwareVersionModel();
        $version = $versionModel->find($id);
        if (!$version || ($version['status'] ?? '') !== 'pending') {
            echo json_encode(['ok' => false, 'error' => '新版本投稿不存在或已处理']);
            return;
        }
        $software = $this->softwareModel->find((int)$version['software_id']);
        if (!$software) {
            echo json_encode(['ok' => false, 'error' => '软件不存在']);
            return;
        }
        $reviewerId = (int)($_SESSION['auth_user']['id'] ?? 0);
        if ($action === 'approve') {
            $update = [
                'version' => $version['version'],
                'size' => $version['size'],
                'download_url' => $version['download_url'],
                'file_path' => $version['file_path'],
            ];
            if (!empty($version['icon'])) {
                $update['icon'] = $version['icon'];
            }
            $this->softwareModel->update((int)$software['id'], $update);
            $versionModel->approve($id, $reviewerId, $note);
            (new NotificationModel())->create((int)$version['uploader_id'], 'software_version_approved', '软件新版本审核通过', '您的软件《' . $software['name'] . '》新版本 v' . $version['version'] . ' 已通过审核并上线。');
        } elseif ($action === 'reject') {
            $versionModel->reject($id, $reviewerId, $note);
            (new NotificationModel())->create((int)$version['uploader_id'], 'software_version_rejected', '软件新版本审核未通过', '您的软件《' . $software['name'] . '》新版本 v' . $version['version'] . ' 未通过审核。原因：' . $note);
        } else {
            echo json_encode(['ok' => false, 'error' => '无效操作']);
            return;
        }
        echo json_encode(['ok' => true]);
    }

    public function remove(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        $software = $this->softwareModel->find($id);
        if (!$software) {
            echo json_encode(['ok' => false, 'error' => '软件不存在']);
            return;
        }

        $this->softwareModel->update($id, ['status' => 'removed', 'admin_note' => $reason]);
        (new NotificationModel())->create((int)$software['uploader_id'], 'software_removed', '软件已被删除', '您的软件《' . $software['name'] . '》已被管理员删除。原因：' . $reason);
        echo json_encode(['ok' => true]);
    }

    public function unpublish(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        $software = $this->softwareModel->find($id);
        if (!$software) {
            echo json_encode(['ok' => false, 'error' => '软件不存在']);
            return;
        }

        $this->softwareModel->update($id, ['status' => 'removed', 'admin_note' => $reason]);
        (new NotificationModel())->create((int)$software['uploader_id'], 'software_unpublished', '软件已被下架', '您的软件《' . $software['name'] . '》已被管理员下架。原因：' . $reason);
        echo json_encode(['ok' => true]);
    }

    public function setRecommended(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        $recommended = ($_POST['recommended'] ?? '1') === '1';
        $software = $this->softwareModel->find($id);
        if (!$software) {
            echo json_encode(['ok' => false, 'error' => '软件不存在']);
            return;
        }
        if ($recommended && ($software['status'] ?? '') !== 'published') {
            echo json_encode(['ok' => false, 'error' => '只有已上架软件可以推荐']);
            return;
        }
        $this->softwareModel->setRecommended($id, $recommended);
        echo json_encode(['ok' => true]);
    }

    public function saveCategory(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower((string)($_POST['slug'] ?? '')));
        $icon = trim((string)($_POST['icon'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($name === '' || $slug === '') {
            echo json_encode(['ok' => false, 'error' => '名称和标识不能为空']);
            return;
        }

        if ($id > 0) {
            $this->categoryModel->update($id, ['name' => $name, 'slug' => $slug, 'icon' => $icon, 'sort_order' => $sortOrder]);
        } else {
            $this->categoryModel->create(['name' => $name, 'slug' => $slug, 'icon' => $icon, 'sort_order' => $sortOrder]);
        }
        echo json_encode(['ok' => true]);
    }

    public function deleteCategory(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        $this->categoryModel->delete($id);
        echo json_encode(['ok' => true]);
    }

    public function saveType(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower((string)($_POST['slug'] ?? '')));
        $color = trim((string)($_POST['color'] ?? '#3cc9a4'));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = (string)($_POST['status'] ?? 'active');
        $selectableScope = (string)($_POST['selectable_scope'] ?? 'user');
        if (!in_array($status, ['active','disabled'], true)) $status = 'active';
        if (!in_array($selectableScope, ['user','admin'], true)) $selectableScope = 'user';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#3cc9a4';

        if ($name === '' || $slug === '') {
            echo json_encode(['ok' => false, 'error' => '名称和标识不能为空']);
            return;
        }

        $model = new SoftwareTypeModel();
        try {
            if ($id > 0) {
                $model->update($id, ['name' => $name, 'slug' => $slug, 'color' => $color, 'sort_order' => $sortOrder, 'status' => $status, 'selectable_scope' => $selectableScope]);
            } else {
                $model->create(['name' => $name, 'slug' => $slug, 'color' => $color, 'sort_order' => $sortOrder, 'status' => $status, 'selectable_scope' => $selectableScope]);
            }
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => '保存失败，标识可能已存在']);
        }
    }

    public function deleteType(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => '无效ID']);
            return;
        }
        (new SoftwareTypeModel())->delete($id);
        echo json_encode(['ok' => true]);
    }
}
