<?php

namespace App\Controllers\Api;

use App\Models\SystemMessageModel;

class MessageController
{
    public function unread(): void
    {
        header('Content-Type: application/json');

        if (empty($_SESSION['auth_user'])) {
            echo json_encode(['count' => 0]);
            return;
        }

        $userId = (int) $_SESSION['auth_user']['id'];
        $model = new SystemMessageModel();

        try {
            $count = $model->unreadCountSimple($userId);
            echo json_encode(['count' => $count]);
        } catch (\Throwable $e) {
            echo json_encode(['count' => 0]);
        }
    }

    public function list(): void
    {
        header('Content-Type: application/json');

        if (empty($_SESSION['auth_user'])) {
            echo json_encode(['messages' => []]);
            return;
        }

        $userId = (int) $_SESSION['auth_user']['id'];
        $model = new SystemMessageModel();

        try {
            $messages = $model->listForUser($userId);
            echo json_encode(['messages' => $messages]);
        } catch (\Throwable $e) {
            echo json_encode(['messages' => []]);
        }
    }

    public function markRead(): void
    {
        header('Content-Type: application/json');

        if (empty($_SESSION['auth_user'])) {
            echo json_encode(['ok' => false]);
            return;
        }

        csrf_verify();

        $userId = (int) $_SESSION['auth_user']['id'];
        $messageId = (int) ($_POST['message_id'] ?? 0);
        $all = (bool) ($_POST['all'] ?? false);
        $categoryRaw = (string)($_POST['category'] ?? '');
        $category = in_array($categoryRaw, ['fans','reply','like','favorite','private','review','finance','system'], true) ? $categoryRaw : '';

        $model = new SystemMessageModel();
        try {
            if ($all) {
                $model->markAllRead($userId, $category);
            } elseif ($messageId > 0) {
                $model->markRead($userId, $messageId);
            }
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
