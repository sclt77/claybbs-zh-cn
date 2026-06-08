<?php

namespace App\Controllers\Web;

use App\Models\PaymentModel;
use App\Models\WalletModel;
use App\Services\PaymentService;
use RuntimeException;

class PaymentController
{
    public function recharge(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        $userId=(int)auth_user()['id'];
        $payment=new PaymentModel();
        $wallet=new WalletModel();
        $balances=$wallet->balances($userId);
        $currency=(string)($_GET['currency'] ?? ($balances[0]['currency_code'] ?? ''));
        $packages=$payment->activePackages($currency);
        $channels=$payment->activeChannels();
        $message=(string)($_SESSION['flash_success'] ?? ''); unset($_SESSION['flash_success']);
        $error=(string)($_SESSION['flash_error'] ?? ''); unset($_SESSION['flash_error']);
        require theme_view('web/payment/recharge.php');
    }

    public function create(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        try {
            $payment=new PaymentModel();
            $currency=function_exists('currency_resolve_code') ? currency_resolve_code((string)($_POST['currency_code'] ?? '')) : strtoupper((string)($_POST['currency_code'] ?? ''));
            $channel=(string)($_POST['channel'] ?? '');
            $amount=0.0;
            $payAmount=(float)($_POST['pay_amount'] ?? 0);
            $packages = $payment->activePackages($currency);
            if (!empty($_POST['package_id'])) {
                foreach ($packages as $pkg) {
                    if ((int)$pkg['id'] === (int)$_POST['package_id']) { $amount=(float)$pkg['amount']; $payAmount=(float)$pkg['pay_amount']; break; }
                }
                if ($amount <= 0 || $payAmount <= 0) throw new RuntimeException('充值套餐无效或已停用');
            } else {
                if ($payAmount < 1) throw new RuntimeException('自定义充值金额不能低于 1 元');
                $base = $packages[0] ?? null;
                if (!$base || (float)$base['pay_amount'] <= 0) throw new RuntimeException('当前货币暂不可自定义充值');
                $amount = round($payAmount * ((float)$base['amount'] / (float)$base['pay_amount']), 6);
            }
            $order=$payment->createOrder((int)auth_user()['id'], $currency, $amount, $payAmount, $channel, '钱包充值');
            header('Location: /index.php?path=payment/order&order_no=' . urlencode((string)$order['order_no'])); exit;
        } catch (RuntimeException $e) { $_SESSION['flash_error']=$e->getMessage(); }
        catch (\Throwable $e) { $_SESSION['flash_error']='创建订单失败：' . $e->getMessage(); }
        header('Location: /index.php?path=wallet/recharge'); exit;
    }

    public function orders(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        $payment = new PaymentModel();
        $channels = $payment->channels();
        $service = new PaymentService();
        $orders = $payment->orders(['user_id'=>(int)auth_user()['id']], 100);
        foreach ($orders as $idx => $row) {
            if (($row['status'] ?? '') === 'pending' && in_array((string)$row['channel'], ['alipay_qrcode','alipay_official'], true)) {
                try {
                    if ($service->syncAlipayOrder($row, $channels[(string)$row['channel']] ?? [])) {
                        $fresh = $payment->orderByNo((string)$row['order_no']);
                        if ($fresh) $orders[$idx] = $fresh;
                    }
                } catch (\Throwable $e) {}
            }
        }
        require theme_view('web/payment/orders.php');
    }

    public function order(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        $order=(new PaymentModel())->orderByNo((string)($_GET['order_no'] ?? ''));
        if (!$order || (int)$order['user_id'] !== (int)auth_user()['id']) { header('Location: /index.php?path=wallet'); exit; }
        $channels=(new PaymentModel())->channels();
        $service = new PaymentService();
        if (($order['status'] ?? '') === 'pending' && in_array((string)$order['channel'], ['alipay_qrcode','alipay_official'], true)) {
            try {
                if ($service->syncAlipayOrder($order, $channels[(string)$order['channel']] ?? [])) {
                    $order=(new PaymentModel())->orderByNo((string)$order['order_no']) ?: $order;
                }
            } catch (\Throwable $e) {}
        }
        $payload=$service->paymentPayload($order, $channels[(string)$order['channel']] ?? []);
        require theme_view('web/payment/order.php');
    }

    public function redeem(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        try {
            (new PaymentModel())->redeem((int)auth_user()['id'], (string)($_POST['code'] ?? ''));
            $_SESSION['flash_success']='兑换成功，货币已到账。';
        } catch (RuntimeException $e) { $_SESSION['flash_error']=$e->getMessage(); }
        catch (\Throwable $e) { $_SESSION['flash_error']='兑换失败，请稍后再试。'; }
        header('Location: /index.php?path=wallet/recharge'); exit;
    }

    public function notify(): void
    {
        $channel=(string)($_GET['channel'] ?? $_POST['channel'] ?? '');
        $model=new PaymentModel();
        $channels=$model->channels();
        $params=array_merge($_GET,$_POST);
        $orderNo=(string)($params['out_trade_no'] ?? $params['order_no'] ?? '');
        if ($channel === '' && $orderNo !== '') {
            $order = $model->orderByNo($orderNo);
            $channel = (string)($order['channel'] ?? '');
        }
        try {
            if ($channel === 'epay') {
                $cfg=json_decode((string)($channels['epay']['config_json'] ?? '{}'), true) ?: [];
                if (!(new PaymentService())->verifyEpay($params, (string)($cfg['api_key'] ?? ''))) throw new RuntimeException('签名错误');
                $order = $model->orderByNo($orderNo);
                if (!$order) throw new RuntimeException('订单不存在');
                $paidMoney = isset($params['money']) ? (float)$params['money'] : (float)($params['total_fee'] ?? 0);
                if (abs($paidMoney - (float)$order['pay_amount']) > 0.01) throw new RuntimeException('支付金额不一致');
                $pid = (string)($cfg['pid'] ?: ($cfg['merchant_id'] ?? ''));
                if ($pid !== '' && isset($params['pid']) && (string)$params['pid'] !== $pid) throw new RuntimeException('商户号不一致');
                if (($params['trade_status'] ?? '') === 'TRADE_SUCCESS') $model->markPaid($orderNo, (string)($params['trade_no'] ?? ''), json_encode($params, JSON_UNESCAPED_UNICODE));
            } elseif (in_array($channel, ['alipay_qrcode','alipay_official'], true)) {
                $cfg=json_decode((string)($channels[$channel]['config_json'] ?? '{}'), true) ?: [];
                if (!(new PaymentService())->verifyAlipay($params, (string)($cfg['alipay_public_key'] ?? ''))) throw new RuntimeException('支付宝签名错误');
                $order = $model->orderByNo($orderNo);
                if (!$order) throw new RuntimeException('订单不存在');
                if (!empty($cfg['app_id']) && isset($params['app_id']) && (string)$params['app_id'] !== (string)$cfg['app_id']) throw new RuntimeException('支付宝 AppID 不一致');
                $paidMoney = isset($params['total_amount']) ? (float)$params['total_amount'] : (float)($params['buyer_pay_amount'] ?? 0);
                if (abs($paidMoney - (float)$order['pay_amount']) > 0.01) throw new RuntimeException('支付金额不一致');
                if (($params['trade_status'] ?? '') === 'TRADE_SUCCESS' || ($params['trade_status'] ?? '') === 'TRADE_FINISHED') {
                    $model->markPaid($orderNo, (string)($params['trade_no'] ?? ''), json_encode($params, JSON_UNESCAPED_UNICODE));
                } else {
                    $model->logCallback($channel,$orderNo,'ignored',json_encode($params, JSON_UNESCAPED_UNICODE));
                }
            } else {
                $model->logCallback($channel,$orderNo,'received',json_encode($params, JSON_UNESCAPED_UNICODE));
            }
            echo 'success';
        } catch (\Throwable $e) {
            $model->logCallback($channel,$orderNo,'failed',$e->getMessage() . "\n" . json_encode($params, JSON_UNESCAPED_UNICODE));
            echo 'fail';
        }
    }

    public function return(): void
    {
        header('Location: /index.php?path=payment/order&order_no=' . urlencode((string)($_GET['order_no'] ?? $_GET['out_trade_no'] ?? ''))); exit;
    }
}
