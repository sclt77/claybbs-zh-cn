<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\SystemMessageModel;
use App\Models\RoleModel;

class MessageController
{
    private SystemMessageModel $model;

    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.message');
        $this->model = new SystemMessageModel();
    }

    public function index(): void
    {
        $messages = $this->model->all();
        require dirname(__DIR__, 2) . '/views/admin/message/index.php';
    }

    public function create(): void
    {
        Permission::require('admin.message.publish');
        $roleModel = new RoleModel();
        $roles = $roleModel->all();
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            try {
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $priority = (int) ($_POST['priority'] ?? 0);
                $targetType = $this->normalizeTargetType(trim($_POST['target_type'] ?? 'all'));
                $status = trim($_POST['status'] ?? 'active');

                if ($title === '') throw new \RuntimeException('请输入标题');
                if ($content === '') throw new \RuntimeException('请输入内容');

                $targetRoles = $targetType === 'role' ? $this->parseTargetRoles($_POST['target_roles'] ?? []) : null;
                $targetUsers = $targetType === 'user' ? $this->parseTargetUsers($_POST['target_users'] ?? '') : null;

                if ($targetType === 'role' && empty($targetRoles)) {
                    throw new \RuntimeException('请选择目标角色');
                }
                if ($targetType === 'user' && empty($targetUsers)) {
                    throw new \RuntimeException('请选择目标用户');
                }

                $this->model->create([
                    'title'        => $title,
                    'content'      => $content,
                    'priority'     => $priority,
                    'target_type'  => $targetType,
                    'target_roles' => $targetRoles,
                    'target_users' => $targetUsers,
                    'status'       => $status,
                    'created_by'   => $_SESSION['auth_user']['id'] ?? 0,
                ]);
                $success = '消息发布成功';
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        require dirname(__DIR__, 2) . '/views/admin/message/create.php';
    }

    public function edit(): void
    {
        Permission::require('admin.message.publish');
        $id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));
        $msg = $this->model->find($id);
        $editMsg = $this->prepareMessageForForm($msg);
        if (!$msg) {
            http_response_code(404);
            echo '消息不存在';
            return;
        }
        $roleModel = new RoleModel();
        $roles = $roleModel->all();
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            try {
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $priority = (int) ($_POST['priority'] ?? 0);
                $targetType = $this->normalizeTargetType(trim($_POST['target_type'] ?? 'all'));
                $status = trim($_POST['status'] ?? 'active');

                if ($title === '') throw new \RuntimeException('请输入标题');
                if ($content === '') throw new \RuntimeException('请输入内容');

                $targetRoles = $targetType === 'role' ? $this->parseTargetRoles($_POST['target_roles'] ?? []) : null;
                $targetUsers = $targetType === 'user' ? $this->parseTargetUsers($_POST['target_users'] ?? '') : null;

                if ($targetType === 'role' && empty($targetRoles)) {
                    throw new \RuntimeException('请选择目标角色');
                }
                if ($targetType === 'user' && empty($targetUsers)) {
                    throw new \RuntimeException('请选择目标用户');
                }

                $this->model->update($id, [
                    'title'        => $title,
                    'content'      => $content,
                    'priority'     => $priority,
                    'target_type'  => $targetType,
                    'target_roles' => $targetRoles,
                    'target_users' => $targetUsers,
                    'status'       => $status,
                ]);
                $success = '消息更新成功';
                $msg = $this->model->find($id);
                $editMsg = $this->prepareMessageForForm($msg);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        require dirname(__DIR__, 2) . '/views/admin/message/edit.php';
    }


    private function normalizeTargetType(string $targetType): string
    {
        return in_array($targetType, ['all', 'role', 'user'], true) ? $targetType : 'all';
    }

    private function parseTargetRoles(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        return array_values(array_unique(array_filter(array_map('intval', (array) $value), fn (int $id) => $id > 0)));
    }

    private function parseTargetUsers(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        return array_values(array_unique(array_filter(array_map('intval', (array) $value), fn (int $id) => $id > 0)));
    }

    private function prepareMessageForForm(?array $message): ?array
    {
        if (!$message) {
            return null;
        }

        $roles = json_decode((string)($message['target_roles'] ?? '[]'), true);
        $users = json_decode((string)($message['target_users'] ?? '[]'), true);
        $message['target_role_ids'] = is_array($roles) ? array_values(array_filter(array_map('intval', $roles))) : [];
        $message['target_user_ids'] = is_array($users) ? array_values(array_filter(array_map('intval', $users))) : [];
        $message['target_users_str'] = implode(',', $message['target_user_ids']);
        return $message;
    }

    public function delete(): void
    {
        csrf_verify();
        $id = (int) ($_POST['id'] ?? 0);
        $this->model->delete($id);
        redirect_or_ajax('/admin.php?path=messages');
    }
}
