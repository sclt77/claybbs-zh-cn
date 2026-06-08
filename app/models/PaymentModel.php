<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

class PaymentModel
{
    public const CHANNELS = [
        'wechat_official' => '微信官方支付',
        'alipay_official' => '支付宝官方支付',
        'alipay_qrcode' => '支付宝码支付',
        'epay' => '易支付',
    ];

    public function channels(): array
    {
        $rows = Database::connection()->query("SELECT * FROM payment_channels ORDER BY sort_order ASC,id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) $map[(string)$row['code']] = $row;
        foreach (self::CHANNELS as $code => $name) {
            if (!isset($map[$code])) {
                $map[$code] = ['code'=>$code,'name'=>$name,'status'=>'inactive','config_json'=>'{}','sort_order'=>0];
            }
        }
        return $map;
    }

    public function activeChannels(): array
    {
        return array_filter($this->channels(), static fn($c) => ($c['status'] ?? '') === 'active');
    }

    public function saveChannel(array $data): void
    {
        $code = (string)($data['code'] ?? '');
        if (!isset(self::CHANNELS[$code])) return;
        $config = [];
        foreach ([
            
            'app_id','mch_id','merchant_serial_no','api_v3_key','apiclient_cert_path','apiclient_key_path','platform_cert_path','trade_type','notify_url',
            
            'app_private_key','alipay_public_key','sign_type','gateway','return_url','product_code',
            
            'qrcode_url','qrcode_note','account_name',
            
            'pid','merchant_id','api_key','pay_type'
        ] as $key) {
            $config[$key] = trim((string)($data[$key] ?? ''));
        }
        Database::connection()->prepare("INSERT INTO payment_channels (code,name,status,config_json,sort_order,created_at,updated_at) VALUES (:code,:name,:status,:config,:sort,NOW(),NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),status=VALUES(status),config_json=VALUES(config_json),sort_order=VALUES(sort_order),updated_at=NOW()")
            ->execute([':code'=>$code, ':name'=>self::CHANNELS[$code], ':status'=>($data['status'] ?? '') === 'active' ? 'active' : 'inactive', ':config'=>json_encode($config, JSON_UNESCAPED_UNICODE), ':sort'=>(int)($data['sort_order'] ?? 0)]);
    }

    public function packages(): array
    {
        return Database::connection()->query("SELECT p.*, c.name AS currency_name, c.precision FROM payment_packages p LEFT JOIN currencies c ON c.code=p.currency_code ORDER BY p.sort_order ASC,p.id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function activePackages(string $currency = ''): array
    {
        $where = "WHERE p.status='active' AND c.status='active'"; $params=[];
        $currency = $currency !== '' && function_exists('currency_resolve_code') ? currency_resolve_code($currency) : strtoupper($currency);
        if ($currency !== '') { $where .= " AND p.currency_code=:currency"; $params[':currency']=$currency; }
        $stmt = Database::connection()->prepare("SELECT p.*, c.name AS currency_name, c.precision FROM payment_packages p INNER JOIN currencies c ON c.code=p.currency_code {$where} ORDER BY p.sort_order ASC,p.id ASC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function requireActiveCurrency(string $currency): array
    {
        $currency = function_exists('currency_resolve_code') ? currency_resolve_code($currency) : strtoupper(trim($currency));
        if ($currency === '') throw new RuntimeException('请选择有效货币');
        $stmt = Database::connection()->prepare("SELECT * FROM currencies WHERE code=:code AND status='active' LIMIT 1");
        $stmt->execute([':code'=>$currency]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('货币不存在或已停用');
        return $row;
    }

    public function savePackage(array $data): void
    {
        $id = (int)($data['id'] ?? 0);
        $currency = (string)$this->requireActiveCurrency((string)($data['currency_code'] ?? ''))['code'];
        $amount = max(0.000001, (float)($data['amount'] ?? 0));
        $payAmount = max(0.01, (float)($data['pay_amount'] ?? 0));
        $payload = [':currency'=>$currency, ':amount'=>$amount, ':pay_amount'=>$payAmount, ':title'=>trim((string)($data['title'] ?? '')), ':status'=>($data['status'] ?? '') === 'inactive' ? 'inactive' : 'active', ':sort'=>(int)($data['sort_order'] ?? 0)];
        if ($id > 0) {
            $payload[':id'] = $id;
            Database::connection()->prepare("UPDATE payment_packages SET currency_code=:currency,amount=:amount,pay_amount=:pay_amount,title=:title,status=:status,sort_order=:sort,updated_at=NOW() WHERE id=:id")->execute($payload);
        } else {
            Database::connection()->prepare("INSERT INTO payment_packages (currency_code,amount,pay_amount,title,status,sort_order,created_at,updated_at) VALUES (:currency,:amount,:pay_amount,:title,:status,:sort,NOW(),NOW())")->execute($payload);
        }
    }

    public function deletePackage(int $id): void
    {
        if ($id > 0) Database::connection()->prepare("DELETE FROM payment_packages WHERE id=:id")->execute([':id'=>$id]);
    }

    public function createOrder(int $userId, string $currency, float $amount, float $payAmount, string $channel, string $title = ''): array
    {
        if ($userId <= 0 || $amount <= 0 || $payAmount < 0.01) throw new RuntimeException('充值金额不正确');
        $currencyRow = $this->requireActiveCurrency($currency);
        $channels = $this->activeChannels();
        if (!isset($channels[$channel])) throw new RuntimeException('支付方式未开启');
        $orderNo = 'PAY' . date('YmdHis') . strtoupper(bin2hex(random_bytes(4)));
        Database::connection()->prepare("INSERT INTO payment_orders (order_no,user_id,currency_code,amount,pay_amount,channel,status,title,created_at,updated_at) VALUES (:no,:uid,:currency,:amount,:pay_amount,:channel,'pending',:title,NOW(),NOW())")
            ->execute([':no'=>$orderNo, ':uid'=>$userId, ':currency'=>(string)$currencyRow['code'], ':amount'=>$amount, ':pay_amount'=>$payAmount, ':channel'=>$channel, ':title'=>$title ?: '钱包充值']);
        return $this->orderByNo($orderNo) ?: [];
    }

    public function orderByNo(string $orderNo): ?array
    {
        $stmt = Database::connection()->prepare("SELECT o.*, u.username,u.nickname,c.name AS currency_name,c.precision FROM payment_orders o LEFT JOIN users u ON u.id=o.user_id LEFT JOIN currencies c ON c.code=o.currency_code WHERE o.order_no=:no LIMIT 1");
        $stmt->execute([':no'=>$orderNo]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function orders(array $filters = [], int $limit = 200): array
    {
        $where='WHERE 1=1'; $params=[];
        if (($filters['status'] ?? '') !== '') { $where.=' AND o.status=:status'; $params[':status']=$filters['status']; }
        if (($filters['channel'] ?? '') !== '') { $where.=' AND o.channel=:channel'; $params[':channel']=$filters['channel']; }
        if (!empty($filters['user_id'])) { $where.=' AND o.user_id=:user_id'; $params[':user_id']=(int)$filters['user_id']; }
        $stmt=Database::connection()->prepare("SELECT o.*,u.username,u.nickname,c.name AS currency_name FROM payment_orders o LEFT JOIN users u ON u.id=o.user_id LEFT JOIN currencies c ON c.code=o.currency_code {$where} ORDER BY o.id DESC LIMIT :limit");
        foreach($params as $k=>$v){ $stmt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
        $stmt->bindValue(':limit',max(1,min(500,$limit)),PDO::PARAM_INT); $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteOrder(string $orderNo): void
    {
        $orderNo = trim($orderNo);
        if ($orderNo === '') return;
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM payment_callback_logs WHERE order_no=:no")->execute([':no'=>$orderNo]);
            $db->prepare("DELETE FROM payment_orders WHERE order_no=:no")->execute([':no'=>$orderNo]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function markPaid(string $orderNo, string $tradeNo = '', string $raw = '', int $operatorId = 0): bool
    {
        $db=Database::connection();
        $order = null;
        $credited = false;
        $db->beginTransaction();
        try {
            $stmt=$db->prepare("SELECT * FROM payment_orders WHERE order_no=:no FOR UPDATE"); $stmt->execute([':no'=>$orderNo]); $order=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('订单不存在');
            if (($order['status'] ?? '') !== 'pending' && ($order['status'] ?? '') !== 'paid') throw new RuntimeException('订单状态不可入账');

            $exists = $db->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE ref_type='payment_order' AND ref_id=:ref AND user_id=:uid");
            $exists->execute([':ref'=>(int)$order['id'], ':uid'=>(int)$order['user_id']]);
            $credited = (int)$exists->fetchColumn() > 0;

            if (($order['status'] ?? '') === 'paid' && $credited) { $db->commit(); return true; }

            $currencyCode = (string)$this->requireActiveCurrency((string)$order['currency_code'])['code'];
            if ($tradeNo !== '') {
                $tradeCheck = $db->prepare("SELECT COUNT(*) FROM payment_orders WHERE trade_no=:trade AND id<>:id");
                $tradeCheck->execute([':trade'=>$tradeNo, ':id'=>(int)$order['id']]);
                if ((int)$tradeCheck->fetchColumn() > 0) throw new RuntimeException('第三方交易号已被其他订单使用');
            }

            (new WalletModel())->ensureWallets((int)$order['user_id']);
            $walletStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id=:uid AND currency_code=:code FOR UPDATE");
            $walletStmt->execute([':uid'=>(int)$order['user_id'], ':code'=>$currencyCode]);
            $balanceRaw = $walletStmt->fetchColumn();
            if ($balanceRaw === false) throw new RuntimeException('用户钱包不存在，请稍后重试');

            if (!$credited) {
                $amount = (float)$order['amount'];
                if ($amount <= 0) throw new RuntimeException('订单到账金额不正确');
                $after = (float)$balanceRaw + $amount;
                $afterString = number_format($after, 6, '.', '');
                $update = $db->prepare("UPDATE wallets SET balance=:balance, updated_at=NOW() WHERE user_id=:uid AND currency_code=:code");
                $update->execute([':balance'=>$afterString, ':uid'=>(int)$order['user_id'], ':code'=>$currencyCode]);
                if ($update->rowCount() < 1) throw new RuntimeException('钱包入账失败');
                $db->prepare("INSERT INTO wallet_transactions (user_id,currency_code,amount,balance_after,type,operator_id,reversal_of,title,remark,ref_type,ref_id,created_at) VALUES (:uid,:code,:amount,:balance,'recharge',:operator,NULL,'钱包充值',:remark,'payment_order',:ref,NOW())")
                    ->execute([':uid'=>(int)$order['user_id'], ':code'=>$currencyCode, ':amount'=>number_format($amount, 6, '.', ''), ':balance'=>$afterString, ':operator'=>$operatorId ?: null, ':remark'=>'支付订单 ' . $orderNo, ':ref'=>(int)$order['id']]);
                $credited = true;
            }

            $db->prepare("UPDATE payment_orders SET status='paid',trade_no=:trade,paid_at=COALESCE(paid_at,NOW()),updated_at=NOW(),currency_code=:currency WHERE id=:id")
                ->execute([':trade'=>$tradeNo !== '' ? $tradeNo : (string)($order['trade_no'] ?? ''), ':currency'=>$currencyCode, ':id'=>(int)$order['id']]);
            $db->commit();
        } catch (\Throwable $e) { $db->rollBack(); throw $e; }
        try { (new SystemMessageModel())->createPersonal((int)$order['user_id'], '充值到账', '你的充值已到账：' . rtrim(rtrim((string)$order['amount'], '0'), '.') . ' ' . currency_name_by_code((string)$order['currency_code']) . '。', 1); } catch (\Throwable $e) {}
        $this->logCallback((string)$order['channel'], $orderNo, 'success', $raw !== '' ? $raw : 'manual paid');
        return true;
    }

    public function logCallback(string $channel, string $orderNo, string $status, string $payload): void
    {
        $payload = $this->sanitizeCallbackPayload($payload);
        Database::connection()->prepare("INSERT INTO payment_callback_logs (channel,order_no,status,payload,created_at) VALUES (:channel,:no,:status,:payload,NOW())")
            ->execute([':channel'=>$channel, ':no'=>$orderNo, ':status'=>$status, ':payload'=>$payload]);
    }

    private function sanitizeCallbackPayload(string $payload): string
    {
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            $maskKeys = ['sign','signature','token','access_token','refresh_token','api_key','key','secret','app_private_key','alipay_public_key','api_v3_key','buyer_id','buyer_logon_id','openid','unionid','phone','mobile','email'];
            $walker = function (&$value, $key) use (&$walker, $maskKeys): void {
                if (is_array($value)) {
                    array_walk($value, $walker);
                    return;
                }
                $lower = strtolower((string)$key);
                foreach ($maskKeys as $maskKey) {
                    if ($lower === $maskKey || str_contains($lower, $maskKey)) {
                        $value = '[masked]';
                        return;
                    }
                }
            };
            array_walk($decoded, $walker);
            $payload = json_encode($decoded, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: $payload;
        }
        return mb_substr($payload, 0, 20000);
    }

    public function callbackLogs(int $limit = 200): array
    {
        $stmt=Database::connection()->prepare("SELECT * FROM payment_callback_logs ORDER BY id DESC LIMIT :limit");
        $stmt->bindValue(':limit',max(1,min(500,$limit)),PDO::PARAM_INT); $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function redeemCodes(): array
    {
        return Database::connection()->query("SELECT r.*,u.username,u.nickname FROM payment_redeem_codes r LEFT JOIN users u ON u.id=r.used_by ORDER BY r.id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createRedeemCode(array $data): string
    {
        $code = strtoupper(trim((string)($data['code'] ?? ''))) ?: strtoupper(bin2hex(random_bytes(6)));
        $currency = (string)$this->requireActiveCurrency((string)($data['currency_code'] ?? ''))['code'];
        Database::connection()->prepare("INSERT INTO payment_redeem_codes (code,currency_code,amount,max_uses,used_count,status,expires_at,created_at,updated_at) VALUES (:code,:currency,:amount,:max_uses,0,:status,:expires,NOW(),NOW())")
            ->execute([':code'=>$code, ':currency'=>$currency, ':amount'=>max(0.000001,(float)$data['amount']), ':max_uses'=>max(1,(int)($data['max_uses'] ?? 1)), ':status'=>($data['status'] ?? '') === 'inactive' ? 'inactive' : 'active', ':expires'=>trim((string)($data['expires_at'] ?? '')) ?: null]);
        return $code;
    }

    public function deleteRedeemCode(int $id): void
    {
        if ($id <= 0) return;
        $db = Database::connection();
        $used = $db->prepare("SELECT used_count FROM payment_redeem_codes WHERE id=:id LIMIT 1");
        $used->execute([':id'=>$id]);
        $usedCount = $used->fetchColumn();
        if ($usedCount === false) return;
        $log = $db->prepare("SELECT COUNT(*) FROM payment_redeem_logs WHERE redeem_code_id=:id");
        $log->execute([':id'=>$id]);
        if ((int)$usedCount > 0 || (int)$log->fetchColumn() > 0) {
            $db->prepare("UPDATE payment_redeem_codes SET status='inactive', updated_at=NOW() WHERE id=:id")->execute([':id'=>$id]);
            return;
        }
        $db->prepare("DELETE FROM payment_redeem_codes WHERE id=:id")->execute([':id'=>$id]);
    }

    public function redeem(int $userId, string $code): array
    {
        $code = strtoupper(trim($code));
        if ($userId <= 0 || $code === '') throw new RuntimeException('兑换码不能为空');
        $db=Database::connection();
        $row = [];
        $db->beginTransaction();
        try {
            $stmt=$db->prepare("SELECT * FROM payment_redeem_codes WHERE code=:code FOR UPDATE"); $stmt->execute([':code'=>$code]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || ($row['status'] ?? '') !== 'active') throw new RuntimeException('兑换码无效');
            if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) throw new RuntimeException('兑换码已过期');
            if ((int)$row['used_count'] >= (int)$row['max_uses']) throw new RuntimeException('兑换码已被使用');
            $used=$db->prepare("SELECT COUNT(*) FROM payment_redeem_logs WHERE redeem_code_id=:id AND user_id=:uid"); $used->execute([':id'=>(int)$row['id'], ':uid'=>$userId]);
            if ((int)$used->fetchColumn() > 0) throw new RuntimeException('你已使用过该兑换码');

            $currencyCode = (string)$this->requireActiveCurrency((string)$row['currency_code'])['code'];
            (new WalletModel())->ensureWallets($userId);
            $walletStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id=:uid AND currency_code=:code FOR UPDATE");
            $walletStmt->execute([':uid'=>$userId, ':code'=>$currencyCode]);
            $balanceRaw = $walletStmt->fetchColumn();
            if ($balanceRaw === false) throw new RuntimeException('用户钱包不存在，请稍后重试');
            $amount = (float)$row['amount'];
            if ($amount <= 0) throw new RuntimeException('兑换码金额不正确');
            $after = (float)$balanceRaw + $amount;
            $afterString = number_format($after, 6, '.', '');

            $db->prepare("UPDATE payment_redeem_codes SET used_count=used_count+1, used_by=:uid, used_at=NOW(), updated_at=NOW(), currency_code=:currency WHERE id=:id")
                ->execute([':uid'=>$userId, ':currency'=>$currencyCode, ':id'=>(int)$row['id']]);
            $db->prepare("INSERT INTO payment_redeem_logs (redeem_code_id,user_id,currency_code,amount,created_at) VALUES (:rid,:uid,:currency,:amount,NOW())")
                ->execute([':rid'=>(int)$row['id'], ':uid'=>$userId, ':currency'=>$currencyCode, ':amount'=>$row['amount']]);
            $update = $db->prepare("UPDATE wallets SET balance=:balance, updated_at=NOW() WHERE user_id=:uid AND currency_code=:code");
            $update->execute([':balance'=>$afterString, ':uid'=>$userId, ':code'=>$currencyCode]);
            if ($update->rowCount() < 1) throw new RuntimeException('兑换到账失败');
            $db->prepare("INSERT INTO wallet_transactions (user_id,currency_code,amount,balance_after,type,operator_id,reversal_of,title,remark,ref_type,ref_id,created_at) VALUES (:uid,:currency,:amount,:balance,'redeem_code',NULL,NULL,'兑换码兑换',:remark,'redeem_code',:ref,NOW())")
                ->execute([':uid'=>$userId, ':currency'=>$currencyCode, ':amount'=>number_format($amount, 6, '.', ''), ':balance'=>$afterString, ':remark'=>'兑换码 ' . $code, ':ref'=>(int)$row['id']]);
            $row['currency_code'] = $currencyCode;
            $db->commit();
        } catch (\Throwable $e) { $db->rollBack(); throw $e; }
        try { (new SystemMessageModel())->createPersonal($userId, '兑换成功', '你已成功兑换：' . rtrim(rtrim((string)$row['amount'], '0'), '.') . ' ' . currency_name_by_code((string)$row['currency_code']) . '。', 0); } catch (\Throwable $e) {}
        return $row;
    }
}
