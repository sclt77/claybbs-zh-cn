<?php

declare(strict_types=1);

namespace App\Core;



final class HookRegistry
{
    

    private const HOOKS = [
        
        'app.booted'               => ['系统启动完成',         '无',                  'fire'],
        'web.routes'               => ['前台路由注册',         'router — Router 实例', 'fire'],
        'admin.routes'             => ['后台路由注册',         'router — Router 实例', 'fire'],

        
        'thread.created'           => ['帖子发布后',          'thread — 帖子数据',    'fire'],
        'thread.updated'           => ['帖子编辑后',          'thread — 帖子数据',    'fire'],
        'thread.deleted'           => ['帖子删除后',          'thread — 帖子数据',    'fire'],
        'post.created'             => ['回复发布后',          'post — 回复数据',      'fire'],
        'user.registered'          => ['用户注册后',          'user — 用户数据',      'fire'],

        
        'view.styles'              => ['前台 head 样式/脚本',  'value — 累积 HTML',    'filter'],
        'view.login.after'         => ['登录表单下方',        'value — 累积 HTML',    'filter'],
        'admin.menu.plugins'       => ['后台插件菜单',        'value — 累积 HTML',    'filter'],
        'admin.menu.system'        => ['后台系统菜单',        'value — 累积 HTML',    'filter'],

        
        'user.badges'              => ['用户勋章展示',        'value — 累积 HTML',    'filter'],
        'user.nameplate'           => ['用户名牌展示',        'value — 名牌 HTML',    'filter'],
        'user.avatar_frame'        => ['用户头像框',          'value — 框数据/null',  'filter'],
        'user.chat_bubble'         => ['聊天气泡特效',        'value — {effect_type, effect_params}', 'filter'],
        'user.center.quick_actions'=> ['用户中心快捷操作',    'value — 累积 HTML',    'filter'],
    ];

    

    public static function all(): array
    {
        $result = [];
        foreach (self::HOOKS as $name => [$when, $payload, $type]) {
            $result[$name] = ['when' => $when, 'payload' => $payload, 'type' => $type];
        }
        return $result;
    }

    
    public static function exists(string $name): bool
    {
        return isset(self::HOOKS[$name]);
    }

    
    public static function get(string $name): ?array
    {
        if (!isset(self::HOOKS[$name])) return null;
        [$when, $payload, $type] = self::HOOKS[$name];
        return ['name' => $name, 'when' => $when, 'payload' => $payload, 'type' => $type];
    }

    
    public static function names(): array
    {
        return array_keys(self::HOOKS);
    }

    private function __construct() {}
}
