<?php

namespace App\Models;

use App\Core\Database;

class QuestionModel
{
    public function acceptAnswer(int $threadId, int $postId, int $ownerId, bool $adminOverride = false): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $threadStmt = $db->prepare("SELECT id,user_id,section_id,title,question_status,bounty_currency,bounty_amount,accepted_post_id FROM threads WHERE id=:id FOR UPDATE");
            $threadStmt->execute([':id' => $threadId]);
            $thread = $threadStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$thread || (!$adminOverride && (int)$thread['user_id'] !== $ownerId)) {
                throw new \RuntimeException('你只能采纳自己帖子的回复');
            }
            if ((string)($thread['question_status'] ?? 'open') === 'resolved' || !empty($thread['accepted_post_id'])) {
                throw new \RuntimeException('该问题已经设置过最佳答案');
            }
            $sectionStmt = $db->prepare("SELECT is_question FROM sections WHERE id=:id LIMIT 1");
            $sectionStmt->execute([':id' => (int)$thread['section_id']]);
            if ((int)$sectionStmt->fetchColumn() !== 1) {
                throw new \RuntimeException('该板块不是问答板块');
            }
            $postStmt = $db->prepare("SELECT * FROM posts WHERE id=:id AND thread_id=:thread_id AND status='published' FOR UPDATE");
            $postStmt->execute([':id' => $postId, ':thread_id' => $threadId]);
            $post = $postStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$post) {
                throw new \RuntimeException('回复不存在或不可采纳');
            }
            $answerUserId = (int)$post['user_id'];
            if ($answerUserId === $ownerId) {
                throw new \RuntimeException('不能采纳自己的回复');
            }

            $currency = strtoupper(trim((string)($thread['bounty_currency'] ?? '')));
            $amount = (float)($thread['bounty_amount'] ?? 0);
            $fee = 0.0;
            $netAmount = 0.0;
            $bountyReceiveLabel = '';
            if ($currency !== '' && $amount > 0) {
                $fee = min($amount, max(0, (float)((new QuestionBountyModel())->settings()['accept_fee'] ?? 0)));
                $netAmount = max(0, $amount - $fee);
                $bountyReceiveLabel = function_exists('currency_pay_label') ? currency_pay_label($netAmount, $currency) : (rtrim(rtrim(number_format($netAmount, 6, '.', ''), '0'), '.') . ' ' . $currency);
                (new WalletModel())->payFromLocked((int)$thread['user_id'], $answerUserId, $currency, number_format($amount, 6, '.', ''), number_format($fee, 6, '.', ''), '问答悬赏结算', '采纳《' . (string)$thread['title'] . '》的最佳答案', 'thread', $threadId);
            }

            $db->prepare("UPDATE posts SET is_accepted=1, updated_at=NOW() WHERE id=:id")->execute([':id' => $postId]);
            $db->prepare("UPDATE threads SET accepted_post_id=:post_id, accepted_user_id=:user_id, accepted_at=NOW(), question_status='resolved', is_locked=1, updated_at=NOW() WHERE id=:thread_id")
                ->execute([':post_id' => $postId, ':user_id' => $answerUserId, ':thread_id' => $threadId]);
            $db->commit();

            try {
                $message = '你在帖子《' . (string)$thread['title'] . '》中的回复被楼主采纳为最佳答案。';
                if ($bountyReceiveLabel !== '') {
                    $message .= ' 悬赏已到账：' . $bountyReceiveLabel . '。';
                    if ($fee > 0 && function_exists('currency_pay_label')) {
                        $message .= ' 平台手续费：' . currency_pay_label($fee, $currency) . '。';
                    }
                }
                (new SystemMessageModel())->createPersonal($answerUserId, '你的回复被采纳为最佳答案', $message, 1, 'reply');
            } catch (\Throwable $e) {}
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
