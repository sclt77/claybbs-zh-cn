<?php
namespace App\Controllers\Web;

use App\Models\GroupChatModel;

class GroupChatController
{
    public function settings(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        $userId = (int)auth_user()['id'];
        $groupId = (int)($_GET['id'] ?? 0);
        $model = new GroupChatModel();
        $group = $groupId > 0 ? $model->groupForUser($groupId, $userId) : null;
        if (!$group) { http_response_code(404); exit('群聊不存在或无权访问'); }
        $members = $model->members($groupId, $userId);
        require theme_view('web/chat/group_settings.php');
    }
}
