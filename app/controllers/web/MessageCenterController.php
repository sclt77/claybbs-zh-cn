<?php

namespace App\Controllers\Web;

use App\Models\SystemMessageModel;
use App\Models\FollowModel;

class MessageCenterController
{
    public function index(): void
    {
        if (empty($_SESSION['auth_user'])) {
            header('Location: /index.php?path=login');
            exit;
        }

        $userId = (int) $_SESSION['auth_user']['id'];
        $model = new SystemMessageModel();
        $type = (string)($_GET['type'] ?? 'all');
        if (!in_array($type, ['all','fans','reply','like','favorite','private','review','finance','system'], true)) {
            $type = 'all';
        }
        $queryCategory = $type === 'all' ? '' : $type;
        $boxRaw = (string)($_GET['box'] ?? 'unread');
        $box = in_array($boxRaw, ['unread', 'history'], true) ? $boxRaw : 'unread';
        $keyword = '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = $box === 'history' ? 10 : 20;
        $offset = ($page - 1) * $pageSize;
        $messages = [];
        $followers = [];
        $transactions = [];
        $total = 0;
        $totalPages = 1;
        $categoryUnreadCounts = ['all' => 0, 'fans' => 0, 'reply' => 0, 'like' => 0, 'favorite' => 0, 'private' => 0, 'review' => 0, 'finance' => 0, 'system' => 0];

        try {
            $categoryUnreadCounts = $model->unreadCountsByCategory($userId);
            $categoryUnreadCounts['all'] = array_sum(array_diff_key($categoryUnreadCounts, ['all' => true]));
            if ($type === 'fans') {
                $model->markAllRead($userId, 'fans');
                $followers = $box === 'history' ? (new FollowModel())->recentFollowerMessages($userId, $pageSize) : [];
                $total = count($followers);
                $categoryUnreadCounts = $model->unreadCountsByCategory($userId);
                $categoryUnreadCounts['all'] = array_sum($categoryUnreadCounts);
            } else {
                $messages = $model->listForUserByCategory($userId, $queryCategory, $box, $pageSize, $offset, $keyword);
                $total = $model->countForUserByCategory($userId, $queryCategory, $box, $keyword);
                $totalPages = max(1, (int)ceil($total / $pageSize));
            }
        } catch (\Throwable $e) {
            $messages = [];
        }

        require theme_view('web/messages/index.php');
    }

    public function clearHistory(): void
    {
        if (empty($_SESSION['auth_user'])) {
            header('Location: /index.php?path=login');
            exit;
        }
        csrf_verify();
        $type = (string)($_POST['type'] ?? '');
        if (!in_array($type, ['fans','reply','like','favorite','private','review','finance','system'], true)) {
            $type = '';
        }
        (new SystemMessageModel())->clearHistory((int)$_SESSION['auth_user']['id'], $type);
        redirect_or_ajax('/index.php?path=messages&type=' . urlencode($type ?: 'system') . '&box=unread');
    }
}
