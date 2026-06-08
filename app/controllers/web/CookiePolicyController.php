<?php

namespace App\Controllers\Web;

use App\Models\SettingModel;

class CookiePolicyController
{
    public function show(): void
    {
        $settings = (new SettingModel())->all();
        $title = trim((string)($settings['cookie_policy_title'] ?? 'Cookie 政策')) ?: 'Cookie 政策';
        $content = trim((string)($settings['cookie_policy_content'] ?? self::defaultPolicy()));
        require theme_view('web/page/cookie_policy.php');
    }

    public static function defaultPolicy(): string
    {
        return "为了让 ClayBBS 正常运行，我们会使用必要 Cookie 保存登录状态、CSRF 安全令牌、主题偏好、Cookie 同意状态等基础信息。\n\n必要 Cookie 用于账号登录、安全校验、会话保持和站点基础功能，关闭后可能导致无法登录、无法发布内容或安全校验失败。\n\n如果站点接入统计、广告、第三方登录或嵌入内容，相关服务可能会在你同意后使用非必要 Cookie。站点会在这里补充具体第三方服务说明。\n\n你可以通过浏览器设置清除 Cookie。清除后，登录状态和 Cookie 同意状态也会被重置。";
    }
}
