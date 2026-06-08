<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminUserModel;
use App\Models\RoleModel;

class RoleController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('user.assign_role');
    }

    public function index(): void
    {
        $keyword  = trim($_GET['kw'] ?? '');
        $model    = new AdminUserModel();
        $roleModel = new RoleModel();
        $users    = [];
        $roles    = [];
        $permissions = [];
        $rolePermissions = [];
        $error    = '';
        $success  = '';

        try {
            $users = $model->list($keyword);
            $roles = $roleModel->all();
            $permissions = $roleModel->allPermissions();
            $rolePermissions = $roleModel->allRolePermissions();
        } catch (\Throwable $e) {
            $error = '加载数据失败：' . $e->getMessage();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = $_POST['_action'] ?? '';

            try {
                if ($action === 'assign') {
                    $this->handleAssign($roleModel);
                    $success = '角色分配成功';
                } elseif ($action === 'revoke') {
                    $this->handleRevoke($roleModel);
                    $success = '角色已撤销';
                } elseif ($action === 'save_role_permissions') {
                    AdminAuth::requireSuperAdmin();
                    $roleModel->saveRolePermissions((int) ($_POST['role_id'] ?? 0), $_POST['permission_ids'] ?? []);
                    $success = '角色权限已保存';
                }
                
                $users = $model->list($keyword);
                $permissions = $roleModel->allPermissions();
                $rolePermissions = $roleModel->allRolePermissions();
            } catch (\Throwable $e) {
                $error = '操作失败：' . $e->getMessage();
            }
        }

        require dirname(__DIR__, 2) . '/views/admin/content/roles.php';
    }

    public function userRoles(): void
    {
        AdminAuth::check();
        $userId    = (int) ($_GET['user_id'] ?? 0);
        $roleModel = new RoleModel();
        $userRoles = [];
        $allRoles  = [];

        try {
            $userRoles = $roleModel->byUserId($userId);
            $allRoles  = $roleModel->all();
        } catch (\Throwable $e) {
        }

        header('Content-Type: application/json');
        echo json_encode(['userRoles' => $userRoles, 'allRoles' => $allRoles]);
    }

    private function handleAssign(RoleModel $roleModel): void
    {
        $userId   = (int) ($_POST['user_id'] ?? 0);
        $roleId   = (int) ($_POST['role_id'] ?? 0);
        $scope    = $_POST['scope'] ?? 'global';
        $scopeId  = ($_POST['scope_id'] ?? '') !== '' ? (int) $_POST['scope_id'] : null;
        $expires  = trim($_POST['expires_at'] ?? '') ?: null;
        $operator = (int) ($_SESSION['auth_user']['id'] ?? 0);

        if ($userId <= 0 || $roleId <= 0) {
            throw new \InvalidArgumentException('参数不完整');
        }
        if (!in_array($scope, ['global', 'category', 'section'], true)) {
            throw new \InvalidArgumentException('无效的作用域');
        }

        $this->assertCanAssignRole($operator, $userId, $roleId);
        $roleModel->assign($userId, $roleId, $scope, $scopeId, $operator, $expires);
    }

    private function handleRevoke(RoleModel $roleModel): void
    {
        $userRoleId = (int)($_POST['user_role_id'] ?? 0);
        if ($userRoleId <= 0) {
            throw new \InvalidArgumentException('无效角色绑定');
        }
        $stmt = Database::connection()->prepare('SELECT ur.user_id, r.id AS role_id FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.id=:id LIMIT 1');
        $stmt->execute([':id' => $userRoleId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!$row) {
            throw new \RuntimeException('角色绑定不存在');
        }
        $operator = (int)($_SESSION['auth_user']['id'] ?? 0);
        $this->assertCanAssignRole($operator, (int)$row['user_id'], (int)$row['role_id']);
        $roleModel->revoke($userRoleId);
    }

    private function assertCanAssignRole(int $operatorId, int $targetUserId, int $roleId): void
    {
        if ($operatorId <= 0 || $targetUserId <= 0 || $roleId <= 0) {
            throw new \InvalidArgumentException('参数不完整');
        }
        $isSuperAdmin = (string)($_SESSION['auth_user']['role'] ?? '') === 'superadmin';
        foreach (Permission::getUserRoles($operatorId) as $role) {
            if (($role['slug'] ?? '') === 'superadmin' && ($role['scope'] ?? '') === 'global') {
                $isSuperAdmin = true;
                break;
            }
        }
        if ($isSuperAdmin) {
            return;
        }
        $stmt = Database::connection()->prepare('SELECT slug, level FROM roles WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $roleId]);
        $targetRole = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!$targetRole) {
            throw new \RuntimeException('角色不存在');
        }
        $operatorLevel = Permission::getMaxLevel($operatorId);
        $targetUserLevel = Permission::getMaxLevel($targetUserId);
        $roleLevel = (int)($targetRole['level'] ?? 0);
        if (in_array((string)($targetRole['slug'] ?? ''), ['admin', 'superadmin'], true) || $roleLevel >= $operatorLevel || $targetUserLevel >= $operatorLevel) {
            throw new \RuntimeException('不能分配或撤销同级/更高级用户或角色');
        }
    }
}
