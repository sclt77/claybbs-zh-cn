<?php

namespace App\Controllers\Web;

use App\Models\EngagementModel;
use App\Models\PostModel;
use App\Models\ThreadModel;

class ReportController
{
    public function index(): void
    {
        if (!auth_check()) {
            header('Location: /index.php?path=login');
            exit;
        }

        $type = (string)($_GET['type'] ?? $_POST['target_type'] ?? 'thread');
        $targetId = (int)($_GET['id'] ?? $_POST['target_id'] ?? 0);
        if (!in_array($type, ['thread', 'post'], true) || $targetId <= 0) {
            http_response_code(404);
            exit('举报对象不存在');
        }

        $target = $this->loadTarget($type, $targetId);
        if (!$target) {
            http_response_code(404);
            exit('举报对象不存在');
        }

        $error = '';
        $success = '';
        $viewerId = (int)(auth_user()['id'] ?? 0);
        $engagement = new EngagementModel();
        $alreadyReported = $engagement->hasReported($viewerId, $type, $targetId);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $reasonType = trim((string)($_POST['reason_type'] ?? ''));
            $detail = trim((string)($_POST['detail'] ?? ''));
            $reasonMap = [
                'spam' => '垃圾广告',
                'illegal' => '违规内容',
                'attack' => '人身攻击',
                'copyright' => '涉嫌侵权',
                'privacy' => '隐私泄露',
                'other' => '其他',
            ];
            if ($alreadyReported) {
                $success = '你已经举报过该内容，我们会继续处理。';
            } elseif (!isset($reasonMap[$reasonType])) {
                $error = '请选择举报原因';
            } elseif ($reasonType === 'other' && $detail === '') {
                $error = '选择其他时请填写补充说明';
            } else {
                $reason = $reasonMap[$reasonType] . ($detail !== '' ? '：' . $detail : '');
                if ($engagement->report($viewerId, $type, $targetId, $reason)) {
                    $success = '举报已提交，我们会尽快处理。';
                    $alreadyReported = true;
                    $_POST = [];
                } else {
                    $success = '你已经举报过该内容，我们会继续处理。';
                    $alreadyReported = true;
                }
            }
        }

        require theme_view('web/report/index.php');
    }

    private function loadTarget(string $type, int $targetId): ?array
    {
        if ($type === 'thread') {
            $thread = (new ThreadModel())->find($targetId);
            if (!$thread || ($thread['status'] ?? '') === 'deleted') return null;
            return [
                'type' => 'thread',
                'id' => $targetId,
                'thread_id' => $targetId,
                'title' => (string)($thread['title'] ?? ''),
                'author' => (string)($thread['author_name'] ?? '匿名'),
                'content' => trim(strip_tags((string)($thread['content'] ?? ''))),
                'url' => '/index.php?path=thread&id=' . $targetId,
            ];
        }
        $post = (new PostModel())->find($targetId);
        if (!$post || ($post['status'] ?? '') !== 'published') return null;
        $thread = (new ThreadModel())->find((int)$post['thread_id']);
        return [
            'type' => 'post',
            'id' => $targetId,
            'thread_id' => (int)($post['thread_id'] ?? 0),
            'title' => (string)($thread['title'] ?? '帖子'),
            'author' => user_display_name($post, '用户'),
            'content' => trim(strip_tags((string)($post['content'] ?? ''))),
            'url' => '/index.php?path=thread&id=' . (int)($post['thread_id'] ?? 0) . '#post-' . $targetId,
        ];
    }
}
