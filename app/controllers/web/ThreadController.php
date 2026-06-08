<?php

namespace App\Controllers\Web;

use App\Models\PostModel;
use App\Models\ThreadModel;
use App\Models\ThreadEditLogModel;
use App\Models\ThreadRevisionModel;
use App\Models\MentionModel;
use App\Models\AdminBannerModel;
use App\Services\AiReviewService;
use App\Services\ThreadPermission;
use App\Services\ReviewNotificationService;
use App\Middleware\Permission;

class ThreadController
{
    public function show(): void
    {
        $threadId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $thread = null;
        $posts = [];
        $error = '';
        $viewer = auth_user();
        $followedUserMap = [];
        $likedPostMap = [];
        $blockedUserMap = [];
        $threadLiked = false;
        $threadFavorited = false;
        $threadSavedLater = false;
        $rewardCurrencies = [];
        $rewardSupporters = [];
        $rewardSupporterCount = 0;
        $rewardError = '';
        $replyPage = max(1, (int)($_GET['page'] ?? 1));
        $replyLimit = 20;
        $replyTotal = 0;
        $replyTotalPages = 1;
        $replyDraft = null;
        $threadBroadcastActive = false;
        $readProgress = null;
        $acceptedAnswer = null;
        $answerScoreMap = [];
        $paidVisibleAllowed = true;
        $sections = [];
        $sectionFollowed = false;
        $sectionFollowerCount = 0;
        $moderationLogs = [];
        $onlyAuthor = false;
        $lastReadPostId = 0;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!auth_check()) {
                header('Location: /index.php?path=login');
                exit;
            }
            csrf_verify();

            $content = trim($_POST['content'] ?? '');
            $content = safe_html($content);
            $threadId = (int) ($_POST['thread_id'] ?? 0);
            $parentId = (int)($_POST['parent_id'] ?? 0);
            $replyThread = $threadId > 0 ? (new ThreadModel())->find($threadId) : null;
            $parentPost = $parentId > 0 ? (new PostModel())->find($parentId) : null;

            $plainContent = trim(strip_tags($content));
            if ($threadId <= 0 || $content === '') {
                $error = '回复内容不能为空';
            } elseif (mb_strlen($plainContent) < 2 || mb_strlen($plainContent) > 5000) {
                $error = '回复内容长度需在 2-5000 字之间';
            } elseif (!$replyThread || !$this->canViewThread($replyThread)) {
                $error = '帖子不存在或暂不可回复';
            } elseif (!$this->canReplyInThread($replyThread)) {
                $error = '你没有权限回复该帖子';
            } elseif ($parentId > 0 && (!$parentPost || (int)$parentPost['thread_id'] !== $threadId || ($parentPost['status'] ?? '') !== 'published')) {
                $error = '引用的回复不存在或不可回复';
            } elseif ($replyThread && in_array((string)($replyThread['question_status'] ?? ''), ['resolved','closed','reviewing_close'], true) && !$this->canManageThread($replyThread)) {
                $error = '该悬赏帖已解决、关闭或正在审核，暂不能回复';
            } elseif ($replyThread && !empty($replyThread['is_locked']) && !$this->canManageThread($replyThread)) {
                $error = '该帖子已锁定，暂不能回复';
            } else {
                try {
                    $userId = (int)auth_user()['id'];
                    $settingModel = new \App\Models\SettingModel();
                    $reviewRequired = $settingModel->getBool('post_review_required', false) && !Permission::can('review.post');
                    $ai = new AiReviewService();
                    $aiIntervened = false;
                    if ($ai->enabledFor('post') && !Permission::can('review.post')) {
                        $aiIntervened = true;
                        $aiResult = $ai->review('post', $userId, (string)($replyThread['title'] ?? '回复'), $content);
                        if (($aiResult['status'] ?? '') === 'rejected') {
                            (new \App\Models\ReplyDraftModel())->saveRejected($userId, $threadId, $parentId > 0 ? $parentId : null, $content, $aiResult);
                            $error = 'AI 审核未通过，回复已退回草稿箱，请修改后重新提交。';
                            throw new \RuntimeException('__AI_REJECTED__');
                        }
                        if (($aiResult['status'] ?? '') === 'error') {
                            $reviewRequired = true;
                        }
                    }
                    $postId = (new PostModel())->create([
                        'thread_id' => $threadId,
                        'user_id' => $userId,
                        'parent_id' => $parentId > 0 ? $parentId : null,
                        'reply_user_id' => $parentPost ? (int)$parentPost['user_id'] : null,
                        'content' => $content,
                        'status' => $reviewRequired ? 'pending' : 'published',
                    ]);
                    if (!$reviewRequired) {
                        $mentionModel = new MentionModel();
                        $mentionModel->notifyMentioned($content, $userId, $threadId, $postId, (string)($replyThread['title'] ?? ''));
                        if ($parentPost) {
                            $mentionModel->notifyReply((int)$parentPost['user_id'], $userId, $threadId, $postId, (string)($replyThread['title'] ?? ''));
                        } elseif ((int)($replyThread['user_id'] ?? 0) !== $userId) {
                            $mentionModel->notifyReply((int)$replyThread['user_id'], $userId, $threadId, $postId, (string)($replyThread['title'] ?? ''));
                        }
                        (new ThreadModel())->incrementReplyCount($threadId);
                        try { (new \App\Models\QuestionBountyModel())->scorePost($threadId, $postId); } catch (\Throwable $e) {}
                        try { (new \App\Services\TaskService())->recordAction($userId, 'post_publish', 'post', $postId); } catch (\Throwable $e) {}
                    }
                    if ($reviewRequired) {
                        if (!$aiIntervened) {
                            try { (new ReviewNotificationService())->notifyPostPending((int)($replyThread['section_id'] ?? 0), $threadId, $postId, (string)($replyThread['title'] ?? '')); } catch (\Throwable $e) {}
                        }
                        $error = '回复已提交，等待审核通过后展示';
                    } else {
                        header('Location: /index.php?path=thread&id=' . $threadId . '#post-' . $postId);
                        exit;
                    }
                } catch (\Throwable $e) {
                    if ($e->getMessage() !== '__AI_REJECTED__') {
                        $error = '回复发布失败，请检查数据库配置';
                    }
                }
            }
        }

        if ($threadId > 0) {
            try {
                $threadModel = new ThreadModel();
                $postModel = new PostModel();
                $thread = $threadModel->find($threadId);
                if ($thread && !$this->canViewThread($thread)) {
                    $thread = null;
                    $posts = [];
                    http_response_code(404);
                }
                if ($thread && ($thread['status'] ?? '') === 'published') {
                    $threadModel->incrementViewCount($threadId);
                    $thread['view_count'] = (int)($thread['view_count'] ?? 0) + 1;
                    $paidVisibleAllowed = (new \App\Models\ThreadPaywallModel())->canView($thread, (int)($viewer['id'] ?? 0));
                }
                $onlyAuthor = (($_GET['only'] ?? '') === 'author');
                $onlyUserId = ($onlyAuthor && $thread) ? (int)($thread['user_id'] ?? 0) : 0;
                $replyTotal = $postModel->countByThreadId($threadId, $onlyUserId);
                $replyTotalPages = max(1, (int)ceil($replyTotal / $replyLimit));
                if ($replyPage > $replyTotalPages) $replyPage = $replyTotalPages;
                $posts = $postModel->byThreadId($threadId, $replyLimit, ($replyPage - 1) * $replyLimit, $onlyUserId);
                $acceptedAnswer = $postModel->acceptedForThread($threadId);
                try { foreach ((new \App\Models\QuestionBountyModel())->scoresForThread($threadId) as $scoreRow) { $answerScoreMap[(int)$scoreRow['post_id']] = $scoreRow; } } catch (\Throwable $e) { $answerScoreMap = []; }
                if ($viewer) {
                    $ids = array_map(static fn($post) => (int)($post['user_id'] ?? 0), $posts);
                    $ids[] = (int)($thread['user_id'] ?? 0);
                    $followedUserMap = (new \App\Models\FollowModel())->followingMap((int)$viewer['id'], $ids);
                    $likedPostMap = (new \App\Models\EngagementModel())->likedMap((int)$viewer['id'], 'post', array_map(static fn($post) => (int)$post['id'], $posts));
                    $blockedUserMap = (new \App\Models\BlockModel())->blockedMap((int)$viewer['id'], $ids);
                    $threadLiked = (new \App\Models\EngagementModel())->isLiked((int)$viewer['id'], 'thread', $threadId);
                    $threadFavorited = (new \App\Models\EngagementModel())->isFavorited((int)$viewer['id'], $threadId);
                    $threadSavedLater = (new \App\Models\ReadingListModel())->isSaved((int)$viewer['id'], $threadId, 'later');
                    $rewardCurrencies = (new \App\Models\ThreadRewardModel())->currenciesForUser((int)$viewer['id']);
                    $readProgress = (new \App\Models\ThreadReadProgressModel())->get((int)$viewer['id'], $threadId);
                    $lastReadPostId = (int)($readProgress['last_post_id'] ?? 0);
                    $replyDraftId = (int)($_GET['reply_draft_id'] ?? 0);
                    if ($replyDraftId > 0) {
                        $replyDraft = (new \App\Models\ReplyDraftModel())->findForUser($replyDraftId, (int)$viewer['id']);
                        if ($replyDraft && (int)($replyDraft['thread_id'] ?? 0) !== $threadId) $replyDraft = null;
                    }
                }
                if ($thread) {
                    try { $sections = (new \App\Models\SectionModel())->list(); } catch (\Throwable $e) { $sections = []; }
                    try {
                        $sectionFollowModel = new \App\Models\SectionFollowModel();
                        $sectionFollowerCount = $sectionFollowModel->countBySection((int)($thread['section_id'] ?? 0));
                        $sectionFollowed = $viewer ? $sectionFollowModel->isFollowing((int)($viewer['id'] ?? 0), (int)($thread['section_id'] ?? 0)) : false;
                    } catch (\Throwable $e) {}
                    try {
                        $moderationLogs = (new \App\Models\AdminAuditLogModel())->latestForTarget('thread', $threadId, ['thread.featured','thread.recommended','thread.locked','thread.top','thread.status','thread.move'], 8);
                    } catch (\Throwable $e) { $moderationLogs = []; }
                    $threadBroadcastActive = (new AdminBannerModel())->isThreadBroadcastActive($threadId);
                    $rewardModel = new \App\Models\ThreadRewardModel();
                    $rewardSupporters = $rewardModel->topSupporters($threadId, 10, 0);
                    $rewardSupporterCount = $rewardModel->supporterCount($threadId);
                }
                $rewardError = (string)($_SESSION['flash_error'] ?? '');
                unset($_SESSION['flash_error']);
            } catch (\Throwable $e) {
                $thread = null;
                $posts = [];
            }
        }

        require theme_view('web/thread/show.php');
    }

    public function edit(): void
    {
        if (!auth_check()) {
            header('Location: /index.php?path=login');
            exit;
        }

        $threadId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $model = new ThreadModel();
        $thread = $threadId > 0 ? $model->find($threadId) : null;
        $error = '';
        try {
            $currencies = (new \App\Models\WalletModel())->currencies();
        } catch (\Throwable $e) {
            $currencies = [];
        }

        if (!$thread) {
            http_response_code(404);
            exit('帖子不存在');
        }

        if (!$this->canEditThread($thread)) {
            http_response_code(403);
            exit('你没有权限编辑该帖子');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $title = trim((string)($_POST['title'] ?? ''));
            $content = trim((string)($_POST['content'] ?? ''));
            $content = safe_html($content);
            $plainContent = trim(strip_tags($content));
            $paidEnabled = !empty($_POST['paid_visible_enabled']);
            $paidPrice = (float)($_POST['paid_visible_price'] ?? 0);
            $paidCurrency = function_exists('currency_resolve_code') ? currency_resolve_code((string)($_POST['paid_visible_currency'] ?? '')) : strtoupper(trim((string)($_POST['paid_visible_currency'] ?? '')));
            $currencyCodes = array_map(static fn($currency) => strtoupper((string)($currency['code'] ?? '')), $currencies);
            if ($title === '' || $content === '') {
                $error = '请填写标题和内容';
            } elseif (mb_strlen($title) < 2 || mb_strlen($title) > 100) {
                $error = '标题长度需在 2-100 字之间';
            } elseif (mb_strlen($plainContent) < 5 || mb_strlen($plainContent) > 20000) {
                $error = '正文长度需在 5-20000 字之间';
            } elseif ($paidEnabled && ($paidPrice <= 0 || $paidCurrency === '' || ($currencyCodes && !in_array($paidCurrency, $currencyCodes, true)))) {
                $error = '请填写付费查看价格并选择有效货币';
            } elseif ($paidEnabled) {
                try {
                    $paidPrice = currency_validate_amount($_POST['paid_visible_price'] ?? 0, $paidCurrency, '付费查看价格');
                } catch (\RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }

            if ($error === '') {
                $oldThread = $thread;
                $viewerId = (int)auth_user()['id'];
                $summary = mb_substr(strip_tags($content), 0, 120);
                $needsReview = !Permission::can('review.thread') && !Permission::can('thread.edit_any');
                if ($needsReview) {
                    $ai = new AiReviewService();
                    $aiIntervened = false;
                    if ($ai->enabledFor('thread')) {
                        $aiIntervened = true;
                        $aiResult = $ai->review('thread', $viewerId, $title, $content);
                        if (($aiResult['status'] ?? '') === 'passed') {
                            $model->updateOwned($threadId, (int)$thread['user_id'], [
                                'title' => $title,
                                'content' => $content,
                                'summary' => $summary,
                                'status' => (string)($oldThread['status'] ?? 'published'),
                                'paid_visible_enabled' => $paidEnabled,
                                'paid_visible_price' => $paidPrice,
                                'paid_visible_currency' => $paidCurrency,
                            ]);
                            (new ThreadEditLogModel())->create($threadId, $viewerId, 'user', $oldThread, [
                                'title' => $title,
                                'content' => $content,
                                'section_id' => $oldThread['section_id'] ?? null,
                                'status' => $oldThread['status'] ?? 'published',
                            ]);
                            $_SESSION['flash_success'] = '帖子修改已通过 AI 审核并更新。';
                            header('Location: /index.php?path=thread&id=' . $threadId);
                            exit;
                        }
                        if (($aiResult['status'] ?? '') === 'rejected') {
                            (new ThreadRevisionModel())->create($threadId, $viewerId, $title, $content, $summary, 'rejected', $aiResult);
                            $error = 'AI 审核未通过，原帖内容未受影响。原因：' . (string)($aiResult['reason'] ?? '内容可能违反社区规则');
                            require theme_view('web/thread/edit.php');
                            return;
                        }
                    }
                    $revisionId = (new ThreadRevisionModel())->create($threadId, $viewerId, $title, $content, $summary, 'pending');
                    if (!$aiIntervened) {
                        try { (new ReviewNotificationService())->notifyRevisionPending((int)($oldThread['section_id'] ?? 0), $threadId, $revisionId, $title); } catch (\Throwable $e) {}
                    }
                    $_SESSION['flash_success'] = '帖子修改已提交，审核通过后会更新原帖；审核前原帖保持不变。';
                    header('Location: /index.php?path=me&tab=pending');
                    exit;
                }
                $model->updateOwned($threadId, (int)$thread['user_id'], [
                    'title' => $title,
                    'content' => $content,
                    'summary' => $summary,
                    'status' => (string)($oldThread['status'] ?? 'published'),
                    'paid_visible_enabled' => $paidEnabled,
                    'paid_visible_price' => $paidPrice,
                    'paid_visible_currency' => $paidCurrency,
                ]);
                (new ThreadEditLogModel())->create($threadId, $viewerId, 'user', $oldThread, [
                    'title' => $title,
                    'content' => $content,
                    'section_id' => $oldThread['section_id'] ?? null,
                    'status' => $oldThread['status'] ?? 'published',
                ]);
                header('Location: /index.php?path=thread&id=' . $threadId);
                exit;
            }
        }

        require theme_view('web/thread/edit.php');
    }

    public function history(): void
    {
        if (!auth_check()) {
            header('Location: /index.php?path=login');
            exit;
        }
        $threadId = (int)($_GET['id'] ?? 0);
        $thread = $threadId > 0 ? (new ThreadModel())->find($threadId) : null;
        if (!$thread || !$this->canManageThread($thread)) {
            http_response_code(403);
            exit('你没有权限查看编辑历史');
        }
        $logs = (new ThreadEditLogModel())->byThreadId($threadId);
        require theme_view('web/thread/history.php');
    }

    public function toggleFavorite(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        $threadId = (int)($_POST['thread_id'] ?? 0);
        $userId = (int)auth_user()['id'];
        $thread = $threadId > 0 ? (new ThreadModel())->find($threadId) : null;
        if (!$thread || !$this->canViewThread($thread)) {
            http_response_code(404);
            exit('帖子不存在');
        }
        $favorited = (new \App\Models\EngagementModel())->toggleFavorite($userId, $threadId);
        if ($favorited) {
            try { (new \App\Services\TaskService())->recordAction($userId, 'favorite_thread', 'thread', $threadId); } catch (\Throwable $e) {}
            try {
                $ownerId = (int)($thread['user_id'] ?? 0);
                if ($ownerId > 0 && $ownerId !== $userId && (new \App\Models\NotificationSettingModel())->enabled($ownerId, 'favorite')) {
                    (new \App\Models\SystemMessageModel())->createPersonal($ownerId, '有人收藏了你的帖子', '你的帖子《' . (string)($thread['title'] ?? '') . '》被收藏了。', 0, 'favorite');
                }
            } catch (\Throwable $e) {}
        }
        redirect_or_ajax('/index.php?path=thread&id=' . $threadId);
    }

    public function toggleLike(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        $type = (string)($_POST['target_type'] ?? 'thread');
        $targetId = (int)($_POST['target_id'] ?? 0);
        if (!$this->canLikeTarget($type, $targetId)) {
            http_response_code(404);
            exit('内容不存在');
        }
        $liked = (new \App\Models\EngagementModel())->toggleLike((int)auth_user()['id'], $type, $targetId);
        if ($liked) {
            try {
                if ($type === 'thread') {
                    $likedThread = (new ThreadModel())->find($targetId);
                    $ownerId = (int)($likedThread['user_id'] ?? 0);
                    if ($ownerId > 0 && $ownerId !== (int)auth_user()['id']) (new \App\Services\TaskService())->recordAction($ownerId, 'thread_liked', 'thread', $targetId);
                } else {
                    $likedPost = (new PostModel())->find($targetId);
                    $ownerId = (int)($likedPost['user_id'] ?? 0);
                    if ($ownerId > 0 && $ownerId !== (int)auth_user()['id']) (new \App\Services\TaskService())->recordAction($ownerId, 'post_liked', 'post', $targetId);
                }
            } catch (\Throwable $e) {}
        }
        $threadId = (int)($_POST['thread_id'] ?? ($type === 'thread' ? $targetId : 0));
        if ($threadId <= 0 && $type === 'post') {
            $post = (new PostModel())->find($targetId);
            $threadId = (int)($post['thread_id'] ?? 0);
        }
        redirect_or_ajax('/index.php?path=thread&id=' . $threadId . ($type === 'post' ? '#post-' . $targetId : ''));
    }

    public function report(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        $type = (string)($_POST['target_type'] ?? $_GET['type'] ?? 'thread');
        $targetId = (int)($_POST['target_id'] ?? $_GET['id'] ?? 0);
        if (!in_array($type, ['thread', 'post'], true) || $targetId <= 0) {
            http_response_code(404);
            exit('举报对象不存在');
        }
        header('Location: /index.php?path=report&type=' . rawurlencode($type) . '&id=' . $targetId);
        exit;
    }

    public function reward(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        $threadId = (int)($_POST['thread_id'] ?? 0);
        try {
            $rewardId = (new \App\Models\ThreadRewardModel())->reward(
                (int)auth_user()['id'],
                $threadId,
                (string)($_POST['currency_code'] ?? ''),
                (string)($_POST['amount'] ?? '')
            );
            try {
                (new \App\Services\TaskService())->recordAction((int)auth_user()['id'], 'thread_reward_sent', 'thread_reward', $rewardId);
                $rewardThread = (new ThreadModel())->find($threadId);
                $authorId = (int)($rewardThread['user_id'] ?? 0);
                if ($authorId > 0) (new \App\Services\TaskService())->recordAction($authorId, 'thread_reward_received', 'thread_reward', $rewardId);
            } catch (\Throwable $e) {}
            redirect_or_ajax('/index.php?path=thread&id=' . $threadId, ['message' => '打赏成功']);
        } catch (\Throwable $e) {
            if (is_ajax_request()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /index.php?path=thread&id=' . $threadId);
            exit;
        }
    }



    public function closeBounty(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        $threadId = (int)($_POST['thread_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        try {
            $result = (new \App\Models\QuestionBountyModel())->requestClose($threadId, (int)auth_user()['id'], $reason);
            $msg = $result === 'review' ? '检测到高匹配回复，已提交悬赏审核' : '悬赏帖已关闭，余额已处理';
            redirect_or_ajax('/index.php?path=thread&id=' . $threadId, ['message'=>$msg]);
        } catch (\Throwable $e) {
            if (is_ajax_request()) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE); exit; }
            $_SESSION['flash_error'] = $e->getMessage(); header('Location: /index.php?path=thread&id=' . $threadId); exit;
        }
    }

    public function acceptAnswer(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        $threadId = (int)($_POST['thread_id'] ?? 0);
        $postId = (int)($_POST['post_id'] ?? 0);
        try {
            (new \App\Models\QuestionModel())->acceptAnswer($threadId, $postId, (int)auth_user()['id']);
            redirect_or_ajax('/index.php?path=thread&id=' . $threadId . '#best-answer', ['message' => '已设置最佳答案']);
        } catch (\Throwable $e) {
            if (is_ajax_request()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /index.php?path=thread&id=' . $threadId);
            exit;
        }
    }

    public function readProgress(): void
    {
        if (!auth_check()) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'login'=>false], JSON_UNESCAPED_UNICODE); return; }
        csrf_verify();
$tid = (int)($_POST['thread_id'] ?? 0);
        $progress = (int)($_POST['progress'] ?? 0);
        (new \App\Models\ThreadReadProgressModel())->mark((int)auth_user()['id'], $tid, $progress, !empty($_POST['last_post_id']) ? (int)$_POST['last_post_id'] : null);
        if ($progress >= 98) { (new ThreadModel())->markReadComplete($tid); }
        header('Content-Type: application/json');
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
    }

    public function unlockPaid(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        $threadId = (int)($_POST['thread_id'] ?? 0);
        try {
            (new \App\Models\ThreadPaywallModel())->unlock((int)auth_user()['id'], $threadId);
            redirect_or_ajax('/index.php?path=thread&id=' . $threadId, ['message'=>'已解锁']);
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            redirect_or_ajax('/index.php?path=thread&id=' . $threadId);
        }
    }

    public function rewardList(): void
    {
        $threadId = (int)($_GET['thread_id'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        header('Content-Type: application/json');
        if ($threadId <= 0) {
            echo json_encode(['ok' => false, 'message' => '帖子不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $data = (new \App\Models\ThreadRewardModel())->page($threadId, $page, 10);
            $rows = [];
            foreach ($data['rows'] as $i => $row) {
                $rows[] = [
                    'rank' => (($data['page'] - 1) * $data['page_size']) + $i + 1,
                    'user_id' => (int)($row['user_id'] ?? 0),
                    'name' => user_display_name($row, '用户'),
                    'avatar' => (string)($row['author_avatar'] ?? ''),
                    'amount_label' => (string)($row['amount_label'] ?? ''),
                    'reward_count' => (int)($row['reward_count'] ?? 0),
                    'verification_name' => (string)($row['verification_name'] ?? ''),
                    'verification_color' => (string)($row['verification_color'] ?? ''),
                    'level_badge' => user_level_badge_html($row, 'level-badge small'),
                ];
            }
            echo json_encode(array_merge(['ok' => true], $data, ['rows' => $rows]), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'message' => '打赏列表读取失败'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function blockUser(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        $targetId = (int)($_POST['user_id'] ?? 0);
        $action = (string)($_POST['action'] ?? 'block');
        $model = new \App\Models\BlockModel();
        if ($action === 'unblock') {
            $model->unblock((int)auth_user()['id'], $targetId);
        } else {
            $model->block((int)auth_user()['id'], $targetId);
            try { (new \App\Models\PrivateChatModel())->hideConversation((int)auth_user()['id'], $targetId); } catch (\Throwable $e) {}
        }
        redirect_or_ajax(normalize_local_redirect((string)($_SERVER['HTTP_REFERER'] ?? ''), '/index.php?path=me'));
    }

    public function deletePost(): void
    {
        if (!auth_check()) {
            header('Location: /index.php?path=login');
            exit;
        }
        csrf_verify();
        $postId = (int)($_POST['post_id'] ?? 0);
        $postModel = new PostModel();
        $post = $postId > 0 ? $postModel->find($postId) : null;
        if (!$post || !$postModel->canDelete($post, (int)auth_user()['id'])) {
            http_response_code(403);
            exit('你没有权限删除该回复');
        }
        $threadId = (int)$post['thread_id'];
        $postModel->delete($postId);
        header('Location: /index.php?path=thread&id=' . $threadId);
        exit;
    }

    public function manage(): void
    {
        if (!auth_check()) {
            header('Location: /index.php?path=login');
            exit;
        }
        csrf_verify();

        $threadId = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $moderationAction = (string)($_POST['moderation_action'] ?? '');
        $targetSectionId = (int)($_POST['target_section_id'] ?? 0);
        $thread = $threadId > 0 ? (new ThreadModel())->find($threadId) : null;
        if (!$thread || ($moderationAction === '' && !in_array($status, ['published','hidden','deleted'], true))) {
            http_response_code(400);
            exit('请求无效');
        }

        $model = new ThreadModel();
        $redirect = '/index.php?path=thread&id=' . $threadId;
        if ($moderationAction !== '') {
            $fieldMap = ['featured'=>'is_featured','recommended'=>'is_recommended','locked'=>'is_locked'];
            if ($moderationAction === 'move') {
                if (!$this->canEditThread($thread)) {
                    http_response_code(403);
                    exit('你没有权限执行该操作');
                }
                $validSectionIds = array_map(static fn($section) => (int)$section['id'], (new \App\Models\SectionModel())->list());
                if ($targetSectionId <= 0 || !in_array($targetSectionId, $validSectionIds, true)) {
                    http_response_code(400);
                    exit('请选择有效板块');
                }
                $model->updateByAdmin($threadId, [
                    'section_id' => $targetSectionId,
                    'title' => (string)$thread['title'],
                    'content' => (string)$thread['content'],
                    'summary' => $thread['summary'] ?? mb_substr(strip_tags((string)$thread['content']), 0, 120),
                    'status' => (string)$thread['status'],
                ]);
            } elseif (in_array($moderationAction, ['top_global','top_section','top_cancel'], true)) {
                if (!$this->canModerateThread($thread, 'top')) {
                    http_response_code(403);
                    exit('你没有权限执行该操作');
                }
                $scope = $moderationAction === 'top_global' ? 'global' : ($moderationAction === 'top_section' ? 'section' : 'none');
                $model->updateModeration($threadId, ['is_top' => $scope === 'none' ? 0 : 1, 'top_scope' => $scope]);
            } elseif (in_array($moderationAction, ['broadcast_section', 'broadcast_cancel'], true)) {
                if (!$this->canModerateThread($thread, 'recommended')) {
                    http_response_code(403);
                    exit('你没有权限执行该操作');
                }
                $bannerModel = new AdminBannerModel();
                if ($moderationAction === 'broadcast_cancel') {
                    $bannerModel->cancelThreadBroadcast($threadId);
                } else {
                    $bannerModel->createFromThread($thread);
                }
            } else {
                if (!isset($fieldMap[$moderationAction]) || !$this->canModerateThread($thread, $moderationAction)) {
                    http_response_code(403);
                    exit('你没有权限执行该操作');
                }
                $field = $fieldMap[$moderationAction];
                $model->updateModeration($threadId, [$field => empty($thread[$field]) ? 1 : 0]);
            }
        } else {
            if (!$this->canChangeStatus($thread, $status)) {
                http_response_code(403);
                exit('你没有权限执行该操作');
            }
            if ($status === 'deleted') {
                $model->deleteHard($threadId);
                $redirect = '/index.php';
            } else {
                $model->updateStatus($threadId, $status);
            }
        }
        redirect_or_ajax($redirect, ['reload' => $status !== 'deleted']);
    }

    private function canViewThread(array $thread): bool
    {
        if (($thread['status'] ?? '') === 'published') {
            return true;
        }
        $userId = (int)(auth_user()['id'] ?? 0);
        return $userId > 0 && ((int)($thread['user_id'] ?? 0) === $userId || $this->canManageThread($thread) || Permission::can('review.thread') || Permission::can('review.thread', 'section', (int)($thread['section_id'] ?? 0)));
    }

    private function canReplyInThread(array $thread): bool
    {
        if (in_array((string)($thread['question_status'] ?? ''), ['resolved','closed','reviewing_close'], true) && !$this->canManageThread($thread)) {
            return false;
        }
        if (!empty($thread['is_locked']) && !$this->canManageThread($thread)) {
            return false;
        }
        return auth_check();
    }

    private function canLikeTarget(string $type, int $targetId): bool
    {
        if ($targetId <= 0) return false;
        if ($type === 'thread') {
            $thread = (new ThreadModel())->find($targetId);
            return $thread && $this->canViewThread($thread);
        }
        if ($type === 'post') {
            $post = (new PostModel())->find($targetId);
            if (!$post || ($post['status'] ?? '') !== 'published') return false;
            $thread = (new ThreadModel())->find((int)($post['thread_id'] ?? 0));
            return $thread && $this->canViewThread($thread);
        }
        return false;
    }

    private function canEditThread(array $thread): bool
    {
        return ThreadPermission::canEdit($thread);
    }

    private function canChangeStatus(array $thread, string $status): bool
    {
        return ThreadPermission::canChangeStatus($thread, $status);
    }

    private function canModerateThread(array $thread, string $action): bool
    {
        return ThreadPermission::canModerate($thread, $action);
    }

    private function canManageThread(array $thread): bool
    {
        return ThreadPermission::canAnyManage($thread);
    }
}
