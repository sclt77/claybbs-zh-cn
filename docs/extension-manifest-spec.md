# ClayBBS Extension Manifest 规范 v1.0.0

本规范定义 ClayBBS 插件 / 主题市场的标准清单文件 `market.json` / `plugin.json` / `theme.json`，
确保扩展可被市场正确验证、下载、安装、更新。

---

## 1. 通用 Manifest

所有扩展包的顶层清单文件名为 `market.json`（兼容 `manifest.json`）。

### 1.1 必需字段

```json
{
  "type": "plugin|theme",
  "slug": "hello-notice",
  "name": "Hello Notice",
  "version": "1.0.0",
  "author": "ClayBBS"
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| `type` | string | `plugin` 或 `theme`，不可省略 |
| `slug` | string | 唯一标识，只允许 `a-zA-Z0-9_-`，最长 80 |
| `name` | string | 人类可读名称 |
| `version` | string | 语义化版本，格式 `1.0.0`，最长 40 |
| `author` | string | 开发者/团队名称 |

### 1.2 推荐字段

| 字段 | 类型 | 说明 |
|------|------|------|
| `description` | string | 简短描述 |
| `api_version` | string | **强烈推荐**声明 Extension API 版本，如 `1.0.0` |
| `min_core_version` | string | 最低要求 ClayBBS 核心版本，如 `1.0.0` |
| `homepage` | string | 项目主页 URL |
| `license` | string | 许可证标识，如 `MIT` / `proprietary` |
| `icon` | string | 图标 URL 或相对路径 |
| `price` | number | 0 表示免费，>0 表示付费 |
| `screenshots` | array | 截图 URL 数组 |
| `changelog` | string | 更新日志（Markdown 或纯文本） |

### 1.3 完整示例

```json
{
  "type": "plugin",
  "slug": "hello-notice",
  "name": "Hello Notice",
  "version": "1.0.0",
  "author": "ClayBBS",
  "api_version": "1.0.0",
  "min_core_version": "1.0.0",
  "description": "在新用户注册后发送欢迎通知",
  "homepage": "https://claybbs.com",
  "license": "MIT",
  "icon": "https://claybbs.com/uploads/hello-icon.png",
  "price": 0,
  "screenshots": [
    "https://claybbs.com/uploads/hello-1.png",
    "https://claybbs.com/uploads/hello-2.png"
  ],
  "changelog": "1.0.0 - 首次发布\n- 支持新用户欢迎通知\n- 支持自定义通知模板"
}
```

---

## 2. Plugin Manifest 补充

### `plugin.json`

插件目录下必须有 `plugin.json`，等同于从 `market.json` 引用的同一份清单。插件侧的额外约定：

```json
{
  "type": "plugin",
  "slug": "hello-notice",
  "name": "Hello Notice",
  "version": "1.0.0",
  "author": "ClayBBS",
  "api_version": "1.0.0",
  "min_core_version": "1.0.0",
  "description": "...",
  "dependencies": {
    "some-plugin": "1.0.0"
  },
  "permissions": [],
  "protected_features": [],
  "license": {
    "required": false,
    "protected_hooks": [],
    "protected_routes": [],
    "protected_features": []
  }
}
```

### Plugin 特有字段

| 字段 | 类型 | 说明 |
|------|------|------|
| `dependencies` | object | 依赖的其他插件 slug→最低版本，如 `{"badges":"1.0.0"}` |
| `permissions` | array | 插件需要声明的权限标识 |
| `protected_features` | array | 受授权保护的功能标识，授权无效时功能不可用 |
| `license.required` | bool | 是否要求市场授权 |
| `license.protected_hooks` | array | 受保护 Hook 正则，未授权时不注册 |
| `license.protected_routes` | array | 受保护路由正则，未授权时不可访问 |
| `license.protected_features` | array | 受保护功能标识 |

---

## 3. Theme Manifest 补充

### `theme.json`

主题目录下必须有 `theme.json`。主题侧的额外约定：

```json
{
  "type": "theme",
  "slug": "clay-light",
  "name": "Clay Light",
  "version": "1.0.0",
  "author": "ClayBBS",
  "api_version": "1.0.0",
  "min_core_version": "1.0.0",
  "description": "...",
  "price": 0
}
```

### Theme 覆盖规则

主题通过 `themes/{slug}/views/` 覆盖 `app/views/` 下的前台视图。覆盖路径一致：

```text
app/views/web/thread/show.php
→ themes/{slug}/views/web/thread/show.php
```

约束：

- 不要覆盖后台管理视图
- 不要在主题模板中依赖核心内部服务细节
- 优先通过 ThemeApi 获取资源 URL
- 只覆盖公开视图结构，不改核心路由或控制器

---

## 4. 市场包文件结构

### Plugin 包

```text
market.json           # 顶层清单
plugin.json           # 同 market.json 或插件专属清单
bootstrap.php         # 入口
views/                # 可选，视图
assets/               # 可选，静态资源
install.sql           # 可选，首次安装 SQL
database/install.sql  # 可选，同 install.sql
migrations/           # 可选，数据库迁移
  *.sql
```

### Theme 包

```text
market.json           # 顶层清单
theme.json            # 同 market.json 或主题专属清单
assets/               # 样式/图片/脚本
  css/theme.css       # 必须提供主样式或通过 ThemeApi 引入
views/                # 可选，覆盖前台视图
```

---

## 5. 版本约束

- `version` 遵循 semver 格式：`MAJOR.MINOR.PATCH`，最长 40 字符
- `api_version` 用于 Extension API 兼容检查
- `min_core_version` 用于最低论坛版本检查
- 版本号允许 `X.Y.Z`。比较使用 `version_compare()`

---

## 6. 授权与加密约定

- 授权的扩展通过市场 `license_key` 安装
- 免费扩展（`price: 0`）不要求授权，安装后管理员可在后台启用
- 付费扩展安装前必须校验授权
- 核心加密发行不影响扩展目录：`plugins/` `themes/` 始终明文或兼容可调用
- Extension API 第一方公开门面 `PluginApi` / `ThemeApi` 始终明文
- 详见 `docs/extension-api.md`

---

## 7. 市场发布流程建议

1. 开发者按照本规范准备好扩展包 ZIP
2. 在官方站开发者后台提交扩展，上传 ZIP
3. 市场服务验证 `market.json` / `plugin.json` / `theme.json` 结构与完整性
4. 审核通过后发布
5. 用户通过授权 Key 在论坛后台安装扩展
6. 安装时校验 `api_version`、`min_core_version`、依赖与授权
7. 安装完成后自动执行 `install.sql` / `migrations/*.sql`
8. 管理员在后台“插件 / 主题”页启用/管理扩展

---

## 附：与 Extension API 的关系

本规范取代之前 `plugins/README.md` / `themes/README.md` 中的约定。

Extension API (`PluginApi` / `ThemeApi`) 是**运行时契约**；本规范是**市场发布与安装契约**。

扩展开发者应同时阅读：

- `docs/extension-api.md`：运行时发展能力
- 本文档：市场发布 manifest 规范
