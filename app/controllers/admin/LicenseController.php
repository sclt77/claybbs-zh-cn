<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Services\LicenseGuardService;

class LicenseController
{
    public function __construct()
    {
        AdminAuth::check(false);
    }

    public function index(): void
    {
        $service = new LicenseGuardService();
        $config = $service->loadConfig();
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            try {
                $licenseKey = trim((string)($_POST['license_key'] ?? ($config['license_key'] ?? '')));
                $domain = trim((string)($_POST['domain'] ?? ($config['domain'] ?? '')));
                $status = $service->onlineVerify($licenseKey, $domain);
                $success = '正版验证已通过，后台访问已恢复';
                if (function_exists('ajax_ok')) {
                    ajax_ok(['message' => $success, 'redirect' => '/admin.php']);
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                if (function_exists('is_ajax_request') && is_ajax_request()) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'message' => $error], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
        }

        $status = $service->adminStatus();
        $maskedLicenseKey = $service->maskedLicenseKey($status);
        $currentDomain = $service->currentDomain($config);
        $savedLicenseKey = (string)($config['license_key'] ?? '');
        require dirname(__DIR__, 2) . '/views/admin/license/index.php';
    }
}
