# ClayBBS Extension API / Theme API

## 目标

为了支持后续核心代码加密发行，ClayBBS 从这一版开始把插件与主题的依赖边界收敛到稳定公开契约。

目标不是“限制扩展”，而是把第三方开发从核心内部实现细节里解耦出来：

- 插件依赖公开 `PluginApi`
- 主题依赖公开 `ThemeApi`
- Hook / 路由 / 资源目录 / 模板覆盖结构稳定化
- 核心业务可在发行包中加密，但插件/主题生态仍能继续开发与运行

---

## 当前契约版本

- Extension API: `1.0.0`
- Minimum core contract: `1.0.0`

建议插件、主题 manifest 都声明：

```json
{
  "api_version": "1.0.0"
}
```

---

## 插件目录结构

```text
plugins/{slug}/
  plugin.json
  bootstrap.php
  views/
  assets/
  migrations/
  install.sql
```

### `plugin.json` 示例

```json
{
  "type": "plugin",
  "slug": "hello-notice",
  "name": "Hello Notice",
  "version": "1.0.0",
  "author": "ClayBBS",
  "api_version": "1.0.0",
  "description": "示例插件"
}
```

### 公开插件入口

插件应优先在 `bootstrap.php` 中使用：

```php
use App\Extension\PluginApi as ClayPlugin;
```

### PluginApi 当前能力

- `listen($hook, $callback, $priority = 10)`
- `fire($hook, $payload = [])`
- `filter($hook, $value, $context = [])`
- `route($method, $path, $handler)`
- `get($path, $handler)`
- `post($path, $handler)`
- `db()`
- `setting($key, $default = null)`
- `setSetting($key, $value)`
- `pluginSetting($slug, $key, $default = null)`
- `setPluginSetting($slug, $key, $value)`
- `assetUrl($slug, $path)`
- `csrfField()`
- `csrfVerify()`
- `currentUser()`
- `e()`

---

## 稳定 Hook

当前建议长期保留的扩展 Hook：

- `app.booted`
- `web.routes`
- `admin.menu.plugins`
- `admin.menu.system`
- `view.styles`
- `user.badges`
- `user.nameplate`
- `user.center.quick_actions`

说明：

- `web.routes` 用于插件注册前台公开路由
- `admin.menu.plugins` 用于追加后台“论坛插件”菜单
- `view.styles` 用于注入样式/脚本片段
- `user.badges` / `user.nameplate` 属于前台展示插槽

后续如新增 Hook，应优先走“补公开插槽”而不是让插件直接 include / monkey patch 核心文件。

---

## 插件路由示例

```php
use App\Extension\PluginApi as ClayPlugin;

ClayPlugin::listen('web.routes', static function (array $payload): array {
    ClayPlugin::get('/hello-plugin', static function (): void {
        echo 'hello plugin';
    });

    ClayPlugin::post('/hello-plugin/save', static function (): void {
        ClayPlugin::csrfVerify();
        echo 'saved';
    });

    return $payload;
});
```

---

## 主题目录结构

```text
themes/{slug}/
  theme.json
  assets/css/theme.css
  views/
```

### `theme.json` 示例

```json
{
  "type": "theme",
  "slug": "clay-light",
  "name": "Clay Light",
  "version": "1.0.0",
  "author": "ClayBBS",
  "api_version": "1.0.0",
  "description": "示例主题"
}
```

### ThemeApi 当前能力

- `active()`
- `view($view)`
- `assetUrl($path, $slug = null)`
- `cssTag($path = 'assets/css/theme.css', $slug = null)`
- `e()`

主题模板中建议：

```php
use App\Extension\ThemeApi as ClayTheme;

echo ClayTheme::assetUrl('assets/css/theme.css');
echo ClayTheme::e($title ?? '');
```

---

## 主题覆盖规则

主题可以通过：

```text
themes/{slug}/views/
```

覆盖：

```text
app/views/
```

即：

```text
app/views/web/thread/show.php
```

可被：

```text
themes/{slug}/views/web/thread/show.php
```

覆盖。

约束：

- 主题应只覆盖公开视图结构
- 不要在主题里直接依赖核心内部 service/model/controller 细节
- 如某个主题需求必须读取业务数据，应先新增公开模板变量或新增 Hook/插槽

---

## 数据库与迁移约定

插件包支持：

- `install.sql`
- `database/install.sql`
- `migrations/*.sql`

系统会记录插件迁移执行状态。

约束：

- 禁止操作数据库级权限
- 禁止 `GRANT / REVOKE / CREATE USER / DROP DATABASE`
- 禁止导出文件类 SQL
- 禁止需要超级权限的全局语句

---

## 加密发行边界

未来如果 ClayBBS 发布商业加密版，下面这些内容必须保持明文或至少兼容可调用：

```text
app/Extension
app/core/Hook.php
app/core/Router.php
app/core/PluginManager.php
app/core/ThemeManager.php
app/helpers/theme.php
plugins/
themes/
config/
database/
public/
assets/
```

推荐加密对象：

- 核心业务逻辑
- 授权校验
- 更新中心通信
- 风控 / 安全校验
- 商业化内部服务
- 关键后台处理逻辑

不建议加密死的对象：

- 插件 manifest
- 主题 manifest
- 公开 Hook 名称
- 主题模板结构
- 插件/主题目录约定
- 开发文档

---

## 兼容建议

后续所有插件/主题都建议：

1. 声明 `api_version`
2. 不直接依赖未公开的核心 class 行为
3. 不假设后台菜单 HTML 结构永远不变
4. 不直接改写核心文件
5. 尽量通过 Hook / Theme 覆盖 / 公开 API 完成扩展

如果需要新增能力，优先给核心提“公开插槽 / 公开 API”需求，而不是继续走内部耦合。 
