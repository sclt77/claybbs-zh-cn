<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class QuestionBountyModel
{
    public function settings(): array
    {
        $s = new SettingModel();
        return [
            'accept_fee' => (float)($s->get('question_bounty_accept_fee', '0') ?? 0),
            'close_fee' => (float)($s->get('question_bounty_close_fee', '0') ?? 0),
            'ai_enabled' => $s->getBool('question_bounty_ai_enabled', true),
            'ai_threshold' => (float)($s->get('question_bounty_ai_threshold', '90') ?? 90),
        ];
    }

    public function saveSettings(array $data): void
    {
        (new SettingModel())->saveMany([
            'question_bounty_accept_fee' => number_format(max(0, (float)($data['accept_fee'] ?? 0)), 6, '.', ''),
            'question_bounty_close_fee' => number_format(max(0, (float)($data['close_fee'] ?? 0)), 6, '.', ''),
            'question_bounty_ai_enabled' => !empty($data['ai_enabled']) ? '1' : '0',
            'question_bounty_ai_threshold' => (string)max(0, min(100, (float)($data['ai_threshold'] ?? 90))),
        ]);
    }

    public function requestClose(int $threadId, int $userId, string $reason = ''): string
    {
        $db = Database::connection();
        $thread = (new ThreadModel())->find($threadId);
        if (!$thread || (int)$thread['user_id'] !== $userId || (string)($thread['question_status'] ?? '') !== 'open') throw new \RuntimeException('悬赏帖不存在或不可关闭');
        $settings = $this->settings();
        $high = $this->highScores($threadId, (float)$settings['ai_threshold']);
        if ($high) {
            $stmt = $db->prepare("INSERT INTO question_bounty_reviews (thread_id, requester_id, reason, status, high_score_post_id, ai_snapshot, created_at, updated_at) VALUES (:thread_id,:requester_id,:reason,'pending',:post_id,:snapshot,NOW(),NOW())
                ON DUPLICATE KEY UPDATE reason=VALUES(reason), status='pending', high_score_post_id=VALUES(high_score_post_id), ai_snapshot=VALUES(ai_snapshot), updated_at=NOW()");
            $stmt->execute([':thread_id'=>$threadId, ':requester_id'=>$userId, ':reason'=>$reason, ':post_id'=>(int)$high[0]['post_id'], ':snapshot'=>json_encode($high, JSON_UNESCAPED_UNICODE)]);
            Database::connection()->prepare("UPDATE threads SET question_status='reviewing_close', updated_at=NOW() WHERE id=:id")->execute([':id'=>$threadId]);
            return 'review';
        }
        $this->closeThread($thread, $settings, '楼主关闭悬赏：' . $reason);
        return 'closed';
    }

    public function closeThread(array $thread, array $settings, string $remark = ''): void
    {
        $currency = (string)($thread['bounty_currency'] ?? '');
        $amount = (float)($thread['bounty_amount'] ?? 0);
        $fee = min($amount, max(0, (float)($settings['close_fee'] ?? 0)));
        $refund = max(0, $amount - $fee);
        $wallet = new WalletModel();
        if ($refund > 0) $wallet->unlockBalance((int)$thread['user_id'], $currency, number_format($refund,6,'.',''), 'question_bounty_refund', '悬赏关闭退款', $remark, 'thread', (int)$thread['id']);
        if ($fee > 0) {
            
            $wallet->payFromLocked((int)$thread['user_id'], (int)$thread['user_id'], $currency, number_format($fee,6,'.',''), number_format($fee,6,'.',''), '悬赏关闭手续费', $remark, 'thread', (int)$thread['id']);
        }
        Database::connection()->prepare("UPDATE threads SET question_status='closed', is_locked=1, updated_at=NOW() WHERE id=:id")->execute([':id'=>(int)$thread['id']]);
    }

    public function scorePost(int $threadId, int $postId): void
    {
        $settings = $this->settings();
        if (empty($settings['ai_enabled'])) return;
        $thread = (new ThreadModel())->find($threadId);
        $post = (new PostModel())->find($postId);
        if (!$thread || !$post || empty($thread['section_is_question']) || (string)($thread['question_status'] ?? '') !== 'open') return;
        $result = (new \App\Services\QuestionMatchService())->score($thread, $post);
        $stmt = Database::connection()->prepare("INSERT INTO question_answer_scores (thread_id, post_id, answer_user_id, score, reason, raw_response, status, created_at, updated_at) VALUES (:thread_id,:post_id,:answer_user_id,:score,:reason,:raw,'scored',NOW(),NOW()) ON DUPLICATE KEY UPDATE score=VALUES(score), reason=VALUES(reason), raw_response=VALUES(raw_response), status='scored', updated_at=NOW()");
        $stmt->execute([':thread_id'=>$threadId, ':post_id'=>$postId, ':answer_user_id'=>(int)$post['user_id'], ':score'=>(float)$result['score'], ':reason'=>(string)$result['reason'], ':raw'=>json_encode($result, JSON_UNESCAPED_UNICODE)]);
    }

    public function scoresForThread(int $threadId): array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM question_answer_scores WHERE thread_id=:id ORDER BY score DESC, id ASC");
        $stmt->execute([':id'=>$threadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function highScores(int $threadId, float $threshold): array
    {
        $stmt = Database::connection()->prepare("SELECT s.*, p.content, u.nickname AS author_name FROM question_answer_scores s JOIN posts p ON p.id=s.post_id LEFT JOIN users u ON u.id=s.answer_user_id WHERE s.thread_id=:id AND s.score>=:score AND p.status='published' ORDER BY s.score DESC, s.id ASC");
        $stmt->execute([':id'=>$threadId, ':score'=>$threshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function reviews(string $status = 'pending'): array
    {
        $where = $status !== '' ? "WHERE r.status=:status" : '';
        $sql = "SELECT r.*, t.title, t.bounty_currency, t.bounty_amount, u.nickname AS requester_name FROM question_bounty_reviews r JOIN threads t ON t.id=r.thread_id LEFT JOIN users u ON u.id=r.requester_id {$where} ORDER BY r.id DESC LIMIT 200";
        $stmt = Database::connection()->prepare($sql);
        if ($status !== '') $stmt->bindValue(':status', $status);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function review(int $reviewId, string $decision, int $postId, int $adminId, string $note = ''): void
    {
        $stmt = Database::connection()->prepare("SELECT * FROM question_bounty_reviews WHERE id=:id LIMIT 1");
        $stmt->execute([':id'=>$reviewId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$review || (string)$review['status'] !== 'pending') throw new \RuntimeException('审核记录不存在或已处理');
        $thread = (new ThreadModel())->find((int)$review['thread_id']);
        if (!$thread) throw new \RuntimeException('帖子不存在');
        if ($decision === 'accept') {
            (new QuestionModel())->acceptAnswer((int)$thread['id'], $postId, (int)$thread['user_id'], true);
            $status='accepted_answer';
        } elseif ($decision === 'close') {
            $this->closeThread($thread, $this->settings(), '管理员同意关闭：' . $note);
            $status='approved_close';
        } else {
            Database::connection()->prepare("UPDATE threads SET question_status='open', updated_at=NOW() WHERE id=:id")->execute([':id'=>(int)$thread['id']]);
            $status='rejected';
        }
        Database::connection()->prepare("UPDATE question_bounty_reviews SET status=:status, reviewer_id=:rid, review_note=:note, reviewed_at=NOW(), updated_at=NOW() WHERE id=:id")->execute([':status'=>$status, ':rid'=>$adminId, ':note'=>$note, ':id'=>$reviewId]);
    }
}
