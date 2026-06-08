<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminUserModel;

class UserManageController
{
    private const ROLE_LEVELS = ['user' => 10, 'reviewer' => 30, 'moderator' => 40, 'admin' => 90, 'superadmin' => 100];

    public function __construct()
    {
        AdminAuth::check();
        Permission::require('user.ban');
    }

    public function index(): void
    {
        $keyword = trim($_GET['kw'] ?? '');
        $filters = [
            'status' => trim((string)($_GET['status'] ?? '')),
            'role' => trim((string)($_GET['role'] ?? '')),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = 20;
        $model   = new AdminUserModel();
        $users   = [];
        $total = 0;

        try {
            $users = $model->list($keyword, $filters, $page, $pageSize);
            $total = $model->count($keyword, $filters);
        } catch (\Throwable $e) {
            $users = [];
        }
        $totalPages = max(1, (int)ceil($total / $pageSize));

        require dirname(__DIR__, 2) . '/views/admin/content/users.php';
    }

    public function action(): void
    {
        csrf_verify();
        $id     = (int) ($_POST['id'] ?? 0);
        $act    = $_POST['act'] ?? '';

        if ($id > 0) {
            $model = new AdminUserModel();
            $target = $model->find($id);
            if (!$target || !$this->canOperateTarget($target)) {
                redirect_or_ajax('/admin.php?path=users');
            }

            if ($act === 'delete') {
                
                if ($id !== (int)($_SESSION['auth_user']['id'] ?? 0)) {
                    $model->delete($id);
                }
            } elseif ($act === 'ban_until') {
                $days = (int)($_POST['days'] ?? 0);
                if ($days > 0) {
                    $until = date('Y-m-d H:i:s', strtotime("+{$days} days"));
                    $model->updateBan($id, $until);
                }
            } elseif ($act === 'permanent') {
                $model->updateBan($id, '9999-12-31 23:59:59');
            } elseif ($act === 'unban') {
                $model->updateBan($id, null);
            } elseif (in_array($act, ['active', 'banned'], true)) {
                
                $model->updateStatus($id, $act);
            } else {
                
                $status = $_POST['status'] ?? '';
                if (in_array($status, ['active', 'banned'], true)) {
                    $model->updateStatus($id, $status);
                }
            }
        }

        redirect_or_ajax('/admin.php?path=users');
    }

    public function delete(): void
    {
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && $id !== (int)($_SESSION['auth_user']['id'] ?? 0)) {
            $model = new AdminUserModel();
            $target = $model->find($id);
            if ($target && $this->canOperateTarget($target)) {
                $model->delete($id);
            }
        }
        redirect_or_ajax('/admin.php?path=users');
    }

    public function edit(): void
    {
        $id    = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $model = new AdminUserModel();
        $user  = $model->find($id);
        $error = $success = '';

        if (!$user) {
            redirect_or_ajax('/admin.php?path=users');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $publicId      = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim((string)($_POST['public_id'] ?? ''))));
            $nickname      = trim($_POST['nickname'] ?? '');
            $username      = trim($_POST['username'] ?? '');
            $email         = trim($_POST['email'] ?? '');
            $canAssignRole = Permission::can('user.assign_role') && $this->canOperateTarget($user);
            $role          = $canAssignRole ? trim($_POST['role'] ?? ($user['role'] ?? 'user')) : (string)($user['role'] ?? 'user');
            $status        = $this->canOperateTarget($user) ? trim($_POST['status'] ?? 'active') : (string)($user['status'] ?? 'active');
            $emailVerified = $canAssignRole ? (int) ($_POST['email_verified'] ?? 0) : (int)($user['email_verified'] ?? 0);
            $password      = trim($_POST['password'] ?? '');

            if ($publicId === '' || $username === '' || $email === '') {
                $error = '账号ID、用户名和邮箱不能为空';
            } elseif (!preg_match('/^[A-Z0-9]{2,32}$/', $publicId)) {
                $error = '账号ID只能包含大写字母和数字，长度2-32位';
            } elseif (!preg_match('/^[A-Za-z0-9_]{2,30}$/', $username)) {
                $error = '用户名只能包含字母、数字、下划线，长度2-30位';
            } elseif (!in_array($role, ['user', 'reviewer', 'moderator', 'admin', 'superadmin'], true)) {
                $error = '角色无效';
            } elseif ($role !== (string)($user['role'] ?? 'user') && !$this->canAssignRoleTo($role)) {
                $error = '不能分配同级或更高级角色';
            } elseif (!in_array($status, ['active', 'banned'], true)) {
                $error = '状态无效';
            } elseif ($model->existsByPublicId($id, $publicId)) {
                $error = '该账号ID已被其他用户使用';
            } elseif ($model->existsByUsername($id, $username)) {
                $error = '该用户名已被其他用户使用';
            } elseif ($model->existsByEmail($id, $email)) {
                $error = '该邮箱已被其他用户使用';
            } else {
                $data = [
                    'public_id'      => $publicId,
                    'username'       => $username,
                    'nickname'       => $nickname,
                    'email'          => $email,
                    'role'           => $role,
                    'status'         => $status,
                    'email_verified' => $emailVerified,
                ];
                if ($password !== '') {
                    if (strlen($password) < 6) {
                        $error = '密码至少6位';
                    } else {
                        $data['password'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                }
                if ($error === '') {
                    try {
                        $model->update($id, $data);
                        $user    = $model->find($id);
                        $success = '用户信息已更新';
                    } catch (\Throwable $e) {
                        $error = '更新失败：' . $e->getMessage();
                    }
                }
            }
        }

        require dirname(__DIR__, 2) . '/views/admin/content/user_edit.php';
    }

    public function update(): void
    {
        $this->edit();
    }

    private function currentRoleLevel(): int
    {
        $role = (string)($_SESSION['auth_user']['role'] ?? 'user');
        return self::ROLE_LEVELS[$role] ?? 0;
    }

    private function roleLevel(string $role): int
    {
        return self::ROLE_LEVELS[$role] ?? 0;
    }

    private function canOperateTarget(array $target): bool
    {
        $currentId = (int)($_SESSION['auth_user']['id'] ?? 0);
        $targetId = (int)($target['id'] ?? 0);
        if ($currentId > 0 && $targetId === $currentId) return true;
        return $this->currentRoleLevel() > $this->roleLevel((string)($target['role'] ?? 'user'));
    }

    private function canAssignRoleTo(string $role): bool
    {
        return $this->currentRoleLevel() > $this->roleLevel($role);
    }
}
