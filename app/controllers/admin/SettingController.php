<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\SettingModel;

class SettingController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.settings');
    }

    public function index(): void
    {
        $model = new SettingModel();
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $section = $_POST['section'] ?? 'site';
            try {
                if ($section === 'site') {
                    $model->saveMany([
                    'site_name'      => trim((string) ($_POST['site_name'] ?? 'ClayBBS')),
                    'site_logo_text' => trim((string) ($_POST['site_logo_text'] ?? 'ClayBBS')),
                    'site_tagline'   => trim((string) ($_POST['site_tagline'] ?? '')),
                    'footer_text'    => trim((string) ($_POST['footer_text'] ?? '')),
                    'site_url'       => rtrim(trim((string) ($_POST['site_url'] ?? '')), '/'),
                ]);
                } elseif ($section === 'smtp') {
                    $model->saveMany([
                        'smtp_host'     => trim((string) ($_POST['smtp_host'] ?? '')),
                        'smtp_port'     => trim((string) ($_POST['smtp_port'] ?? '465')),
                        'smtp_username' => trim((string) ($_POST['smtp_username'] ?? '')),
                        'smtp_password' => trim((string) ($_POST['smtp_password'] ?? '')),
                        'smtp_from'     => trim((string) ($_POST['smtp_from'] ?? '')),
                        'smtp_from_name'=> trim((string) ($_POST['smtp_from_name'] ?? 'ClayBBS')),
                        'smtp_encrypt'  => trim((string) ($_POST['smtp_encrypt'] ?? 'ssl')),
                    ]);
                } elseif ($section === 'register') {
                    $model->saveMany([
                        'email_verify_required' => ($_POST['email_verify_required'] ?? '0') === '1' ? '1' : '0',
                    ]);
                } elseif ($section === 'review') {
                    $model->saveMany([
                        'thread_review_required' => ($_POST['thread_review_required'] ?? '0') === '1' ? '1' : '0',
                        'post_review_required' => ($_POST['post_review_required'] ?? '0') === '1' ? '1' : '0',
                    ]);
                } elseif ($section === 'publish_entry') {
                    $model->saveMany([
                        'publish_entry_notice_title' => trim((string)($_POST['publish_entry_notice_title'] ?? '近期公告')),
                        'publish_entry_notice_content' => trim((string)($_POST['publish_entry_notice_content'] ?? '欢迎来到社区，发布内容前请遵守社区规则。')),
                    ]);
                } elseif ($section === 'cookie') {
                    $model->saveMany([
                        'cookie_notice_enabled' => ($_POST['cookie_notice_enabled'] ?? '0') === '1' ? '1' : '0',
                        'cookie_notice_title' => trim((string)($_POST['cookie_notice_title'] ?? 'Cookie 使用提示')),
                        'cookie_notice_content' => trim((string)($_POST['cookie_notice_content'] ?? '我们使用必要 Cookie 保持登录状态并保障站点安全。')),
                        'cookie_notice_button' => trim((string)($_POST['cookie_notice_button'] ?? '我知道了')),
                        'cookie_policy_title' => trim((string)($_POST['cookie_policy_title'] ?? 'Cookie 政策')),
                        'cookie_policy_content' => trim((string)($_POST['cookie_policy_content'] ?? \App\Controllers\Web\CookiePolicyController::defaultPolicy())),
                        'cookie_consent_days' => (string)max(1, min(3650, (int)($_POST['cookie_consent_days'] ?? 365))),
                    ]);
                } elseif ($section === 'friend') {
                    $model->saveMany([
                        'friend_id_prefix' => preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim((string)($_POST['friend_id_prefix'] ?? 'CY')))) ?: 'CY',
                        'friend_system_enabled' => ($_POST['friend_system_enabled'] ?? '0') === '1' ? '1' : '0',
                        'private_chat_enabled' => ($_POST['private_chat_enabled'] ?? '0') === '1' ? '1' : '0',
                        'group_chat_enabled' => ($_POST['group_chat_enabled'] ?? '0') === '1' ? '1' : '0',
                        'group_chat_review_enabled' => ($_POST['group_chat_review_enabled'] ?? '0') === '1' ? '1' : '0',
                        'private_chat_message_max_length' => (string)max(50, min(5000, (int)($_POST['private_chat_message_max_length'] ?? 1000))),
                        'private_chat_poll_interval' => (string)max(1200, min(30000, (int)($_POST['private_chat_poll_interval'] ?? 3000))),
                        'friend_search_nickname_enabled' => ($_POST['friend_search_nickname_enabled'] ?? '0') === '1' ? '1' : '0',
                    ]);
                } elseif ($section === 'oauth') {
                    $data = [];
                    foreach (['qq','github','wechat','rainbow'] as $provider) {
                        $data['oauth_' . $provider . '_enabled'] = ($_POST['oauth_' . $provider . '_enabled'] ?? '0') === '1' ? '1' : '0';
                        $data['oauth_' . $provider . '_client_id'] = trim((string)($_POST['oauth_' . $provider . '_client_id'] ?? ''));
                        $data['oauth_' . $provider . '_client_secret'] = trim((string)($_POST['oauth_' . $provider . '_client_secret'] ?? ''));
                        $data['oauth_' . $provider . '_redirect_uri'] = trim((string)($_POST['oauth_' . $provider . '_redirect_uri'] ?? ''));
                        if ($provider === 'rainbow') {
                            $data['oauth_rainbow_base_url'] = rtrim(trim((string)($_POST['oauth_rainbow_base_url'] ?? '')), '/');
                        }
                    }
                    $model->saveMany($data);
                }
                $success = '设置已保存';
            } catch (\Throwable $e) {
                $error = '保存失败：' . $e->getMessage();
            }
        }

        $settings = array_merge($model->getSiteConfig(), $model->all());
        require dirname(__DIR__, 2) . '/views/admin/settings/index.php';
    }
}
