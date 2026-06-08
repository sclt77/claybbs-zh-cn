<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminSectionModel;
use App\Models\AdminUserModel;
use App\Models\RoleModel;

require_once dirname(__DIR__, 2) . '/helpers/upload.php';

class SectionManageController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('section.manage');
    }

    public function index(): void
    {
        $model = new AdminSectionModel();
        $sections = [];
        $categories = [];
        $error = '';
        $roles = [];
        $users = [];
        $sectionRoles = [];
        $roleModel = new RoleModel();
        $userModel = new AdminUserModel();

        try {
            $this->ensureSectionColumns();
            $sections = $model->sections();
            $categories = $model->categories();
            $roles = $this->sectionAssignableRoles($roleModel);
            $users = $userModel->list('', [], 1, 500);
            $sectionRoles = $this->loadSectionRoles($roleModel, $users);
        } catch (\Throwable $e) {
            $error = '加载分区/板块失败：' . $e->getMessage();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = $_POST['_action'] ?? '';

            try {
                if ($action === 'create_category') {
                    $name = trim($_POST['name'] ?? '');
                    $slug = trim($_POST['slug'] ?? '');
                    if ($name === '' || $slug === '') {
                        throw new \InvalidArgumentException('请填写完整分区名称和 slug');
                    }
                    $model->createCategory([
                        'name' => $name,
                        'slug' => $slug,
                        'description' => trim($_POST['description'] ?? ''),
                        'sort_order' => (int) ($_POST['sort'] ?? 0),
                    ]);
                    redirect_or_ajax('/admin.php?path=sections');
                }

                if ($action === 'update_category') {
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new \InvalidArgumentException('无效分区 ID');
                    }
                    $model->updateCategory($id, [
                        'name' => trim($_POST['name'] ?? ''),
                        'slug' => trim($_POST['slug'] ?? ''),
                        'description' => trim($_POST['description'] ?? ''),
                        'sort_order' => (int) ($_POST['sort'] ?? 0),
                        'status' => $_POST['status'] ?? 'active',
                    ]);
                    redirect_or_ajax('/admin.php?path=sections');
                }

                if ($action === 'delete_category') {
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id > 0) {
                        $model->deleteCategory($id);
                    }
                    redirect_or_ajax('/admin.php?path=sections');
                }

                if ($action === 'create_section') {
                    $name = trim($_POST['name'] ?? '');
                    $slug = trim($_POST['slug'] ?? '');
                    $categoryId = (int) ($_POST['category_id'] ?? 0);
                    if ($name === '' || $slug === '' || $categoryId <= 0) {
                        throw new \InvalidArgumentException('请填写完整板块名称、slug 并选择分区');
                    }
                    $icon = trim($_POST['icon'] ?? '');
                    $uploadedIcon = upload_image($_FILES['icon_file'] ?? [], 'section-icons');
                    if ($uploadedIcon !== '') { $icon = $uploadedIcon; }
                    $model->createSection([
                        'category_id' => $categoryId,
                        'name' => $name,
                        'slug' => $slug,
                        'icon' => $icon,
                        'post_permission' => $this->normalizePostPermission((string)($_POST['post_permission'] ?? 'login')),
                        'is_question' => !empty($_POST['is_question']),
                        'description' => trim($_POST['description'] ?? ''),
                        'sort_order' => (int) ($_POST['sort'] ?? 0),
                    ]);
                    redirect_or_ajax('/admin.php?path=sections');
                }

                if ($action === 'update_section') {
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new \InvalidArgumentException('无效板块 ID');
                    }
                    $icon = trim($_POST['icon'] ?? '');
                    $uploadedIcon = upload_image($_FILES['icon_file'] ?? [], 'section-icons');
                    if ($uploadedIcon !== '') { $icon = $uploadedIcon; }
                    $model->updateSection($id, [
                        'category_id' => (int) ($_POST['category_id'] ?? 0),
                        'name' => trim($_POST['name'] ?? ''),
                        'slug' => trim($_POST['slug'] ?? ''),
                        'icon' => $icon,
                        'post_permission' => $this->normalizePostPermission((string)($_POST['post_permission'] ?? 'login')),
                        'is_question' => !empty($_POST['is_question']),
                        'description' => trim($_POST['description'] ?? ''),
                        'sort_order' => (int) ($_POST['sort'] ?? 0),
                        'status' => $_POST['status'] ?? 'active',
                    ]);
                    redirect_or_ajax('/admin.php?path=sections');
                }

                if ($action === 'assign_section_role') {
                    $sectionId = (int) ($_POST['section_id'] ?? 0);
                    $userId = (int) ($_POST['user_id'] ?? 0);
                    $roleId = (int) ($_POST['role_id'] ?? 0);
                    if ($sectionId <= 0 || $userId <= 0 || $roleId <= 0) {
                        throw new \InvalidArgumentException('请选择用户、职位和板块');
                    }
                    $allowedRoleIds = array_map('intval', array_column($this->sectionAssignableRoles($roleModel), 'id'));
                    if (!in_array($roleId, $allowedRoleIds, true)) {
                        throw new \InvalidArgumentException('板块职位只能选择版主或审核员');
                    }
                    $roleModel->assign($userId, $roleId, 'section', $sectionId, (int)($_SESSION['auth_user']['id'] ?? 0), null);
                    redirect_or_ajax('/admin.php?path=sections');
                }

                if ($action === 'revoke_section_role') {
                    $sectionId = (int) ($_POST['section_id'] ?? 0);
                    $roleModel->revokeScoped((int) ($_POST['user_role_id'] ?? 0), 'section', $sectionId > 0 ? $sectionId : null);
                    redirect_or_ajax('/admin.php?path=sections');
                }

                if ($action === 'delete_section') {
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id > 0) {
                        $model->deleteSection($id);
                    }
                    redirect_or_ajax('/admin.php?path=sections');
                }
            } catch (\Throwable $e) {
                $error = '保存失败：' . $e->getMessage();
                try {
                    $sections = $model->sections();
                    $categories = $model->categories();
                    $roles = $this->sectionAssignableRoles($roleModel);
                    $users = $userModel->list('', [], 1, 500);
                    $sectionRoles = $this->loadSectionRoles($roleModel, $users);
                } catch (\Throwable $e2) {
                }
            }
        }

        require dirname(__DIR__, 2) . '/views/admin/content/sections.php';
    }



    private function ensureSectionColumns(): void
    {
        
    }

    private function normalizePostPermission(string $value): string
    {
        return in_array($value, ['login', 'role', 'section_role', 'admin'], true) ? $value : 'login';
    }

    private function sectionAssignableRoles(RoleModel $roleModel): array
    {
        return array_values(array_filter($roleModel->all(), static function (array $role): bool {
            return in_array((string)($role['slug'] ?? ''), ['moderator', 'reviewer'], true);
        }));
    }

    private function loadSectionRoles(RoleModel $roleModel, array $users): array
    {
        $map = [];
        foreach ($users as $user) {
            foreach ($roleModel->byUserId((int)$user['id']) as $row) {
                if (($row['scope'] ?? '') !== 'section' || empty($row['scope_id'])) {
                    continue;
                }
                $sid = (int)$row['scope_id'];
                $row['user_role_id'] = (int)($row['user_role_id'] ?? $row['id'] ?? 0);
                $row['user_id'] = (int)$user['id'];
                $row['username'] = (string)($user['username'] ?? '');
                $row['nickname'] = (string)($user['nickname'] ?? '');
                $map[$sid][] = $row;
            }
        }
        return $map;
    }

    public function action(): void
    {
        redirect_or_ajax('/admin.php?path=sections');
    }
}
