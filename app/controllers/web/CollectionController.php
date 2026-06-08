<?php

namespace App\Controllers\Web;

use App\Models\EngagementModel;
use App\Models\ReadingListModel;
use App\Models\ThreadModel;

class CollectionController
{
    public function index(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        $userId = (int)auth_user()['id'];
        $tabRaw = (string)($_GET['tab'] ?? 'favorites');
        $tab = in_array($tabRaw, ['favorites','later'], true) ? $tabRaw : 'favorites';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 12;
        $offset = ($page - 1) * $limit;
        $threads = [];
        $total = 0;
        $favoriteCount = 0;
        $laterCount = 0;
        try {
            $engagement = new EngagementModel();
            $reading = new ReadingListModel();
            $favoriteCount = $engagement->countFavoritesByUser($userId);
            $laterCount = $reading->countByUser($userId, 'later');
            if ($tab === 'later') {
                $threads = $reading->listByUser($userId, 'later', $limit, $offset);
                $total = $laterCount;
            } else {
                $threads = $engagement->favoritesByUser($userId, $limit, $offset);
                $total = $favoriteCount;
            }
        } catch (\Throwable $e) {}
        $totalPages = $total > 0 ? (int)ceil($total / $limit) : 1;
        require theme_view('web/user/collections.php');
    }

    public function toggleLater(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        $threadId = (int)($_POST['thread_id'] ?? 0);
        $userId = (int)auth_user()['id'];
        $thread = $threadId > 0 ? (new ThreadModel())->find($threadId) : null;
        if (!$thread || (string)($thread['status'] ?? '') !== 'published') {
            http_response_code(404);
            exit('帖子不存在');
        }
        (new ReadingListModel())->toggle($userId, $threadId, 'later');
        $return = trim((string)($_POST['return_to'] ?? ''));
        if ($return === '' || !str_starts_with($return, '/index.php')) $return = '/index.php?path=thread&id=' . $threadId;
        redirect_or_ajax($return);
    }
}
