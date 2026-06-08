<?php

namespace App\Controllers\Api;

use App\Core\Database;
use App\Middleware\Permission;

class UserSearchController
{
    public function search(): void
    {
        header('Content-Type: application/json');

        if (empty($_SESSION['auth_user'])) {
            echo json_encode(['users' => []]);
            return;
        }

        $q = trim((string) ($_GET['q'] ?? ''));

        $db = Database::connection();
        if ($q === '') {
            $stmt = $db->prepare(
                "SELECT id, username, nickname, email FROM users
                 WHERE status = 'active'
                 ORDER BY id DESC LIMIT 20"
            );
            $stmt->execute();
        } else {
            $stmt = $db->prepare(
                "SELECT id, username, nickname, email FROM users
                 WHERE status = 'active'
                 AND (username LIKE :q OR nickname LIKE :q OR email LIKE :q)
                 ORDER BY id DESC LIMIT 20"
            );
            $stmt->execute([':q' => "%{$q}%"]);
        }
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $users = array_map(function ($row) {
            return [
                'id'    => (int) $row['id'],
                'username' => $row['username'] ?: '',
                'nickname' => $row['nickname'] ?: '',
                'name'  => $row['nickname'] ?: $row['username'],
                'email' => Permission::can('admin.message') ? ($row['email'] ?: '') : '',
            ];
        }, $rows);

        echo json_encode(['users' => $users]);
    }
}
