<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminAuditLogModel;
use App\Models\PaymentModel;
use App\Models\SettingModel;
use App\Models\WalletModel;

class PaymentController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('payment.view');
    }

    public function index(): void
    {
        $tab=(string)($_GET['tab'] ?? 'channels');
        $model=new PaymentModel();
        $channels=$model->channels();
        $packages=$model->packages();
        $orders=$model->orders(['status'=>(string)($_GET['status'] ?? ''),'channel'=>(string)($_GET['channel'] ?? '')],300);
        $logs=$model->callbackLogs(200);
        $codes=$model->redeemCodes();
        $currencies=(new WalletModel())->currencies();
        $baseUrl = $this->siteBaseUrl();
        $callbackUrls = [];
        foreach (array_keys(PaymentModel::CHANNELS) as $code) {
            $callbackUrls[$code] = $baseUrl . '/index.php?path=payment/notify&channel=' . rawurlencode($code);
        }
        $returnUrl = $baseUrl . '/index.php?path=payment/return';
        require dirname(__DIR__, 2) . '/views/admin/content/payments.php';
    }

    public function channel(): void
    {
        Permission::require('payment.channel');
        csrf_verify();
        (new PaymentModel())->saveChannel($_POST);
        (new AdminAuditLogModel())->record('payment.channel.save', 'payment_channel', 0, ['code' => (string)($_POST['code'] ?? '')]);
        redirect_or_ajax('/admin.php?path=payments&tab=channels');
    }

    public function package(): void
    {
        Permission::require('payment.package');
        csrf_verify();
        (new PaymentModel())->savePackage($_POST);
        (new AdminAuditLogModel())->record('payment.package.save', 'payment_package', (int)($_POST['id'] ?? 0), ['currency_code' => (string)($_POST['currency_code'] ?? ''), 'amount' => (string)($_POST['amount'] ?? ''), 'pay_amount' => (string)($_POST['pay_amount'] ?? '')]);
        redirect_or_ajax('/admin.php?path=payments&tab=packages');
    }

    public function deletePackage(): void
    {
        Permission::require('payment.package');
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        (new PaymentModel())->deletePackage($id);
        (new AdminAuditLogModel())->record('payment.package.delete', 'payment_package', $id);
        redirect_or_ajax('/admin.php?path=payments&tab=packages');
    }

    public function markPaid(): void
    {
        Permission::require('payment.order.manual_paid');
        csrf_verify();
        $orderNo = (string)($_POST['order_no'] ?? '');
        $confirm = trim((string)($_POST['confirm_text'] ?? ''));
        if ($orderNo === '' || $confirm !== 'PAID') {
            $_SESSION['flash_error'] = '手动入账需要输入确认文本 PAID。';
            redirect_or_ajax('/admin.php?path=payments&tab=orders');
        }
        try {
            (new PaymentModel())->markPaid($orderNo, 'manual', 'admin manual paid', (int)($_SESSION['auth_user']['id'] ?? 0));
            (new AdminAuditLogModel())->record('payment.order.manual_paid', 'payment_order', 0, ['order_no' => $orderNo]);
        } catch (\Throwable $e) { $_SESSION['flash_error']=$e->getMessage(); }
        redirect_or_ajax('/admin.php?path=payments&tab=orders');
    }

    public function deleteOrder(): void
    {
        Permission::require('payment.order.delete');
        csrf_verify();
        $orderNo = (string)($_POST['order_no'] ?? '');
        try {
            (new PaymentModel())->deleteOrder($orderNo);
            (new AdminAuditLogModel())->record('payment.order.delete', 'payment_order', 0, ['order_no' => $orderNo]);
        } catch (\Throwable $e) { $_SESSION['flash_error']=$e->getMessage(); }
        redirect_or_ajax('/admin.php?path=payments&tab=orders');
    }

    public function redeemCode(): void
    {
        Permission::require('payment.redeem.manage');
        csrf_verify();
        (new PaymentModel())->createRedeemCode($_POST);
        (new AdminAuditLogModel())->record('payment.redeem.create', 'payment_redeem_code', 0, ['currency_code' => (string)($_POST['currency_code'] ?? ''), 'amount' => (string)($_POST['amount'] ?? ''), 'max_uses' => (int)($_POST['max_uses'] ?? 1)]);
        redirect_or_ajax('/admin.php?path=payments&tab=redeem');
    }

    public function deleteRedeemCode(): void
    {
        Permission::require('payment.redeem.manage');
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        (new PaymentModel())->deleteRedeemCode($id);
        (new AdminAuditLogModel())->record('payment.redeem.delete', 'payment_redeem_code', $id);
        redirect_or_ajax('/admin.php?path=payments&tab=redeem');
    }

    private function siteBaseUrl(): string
    {
        try {
            $siteUrl = trim((string)(new SettingModel())->get('site_url', ''));
            if ($siteUrl !== '') {
                return rtrim($siteUrl, '/');
            }
        } catch (\Throwable $e) {}

        $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return ($https ? 'https://' : 'http://') . $host;
    }
}
