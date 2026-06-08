# ClayBBS 全面重构实施计划 — Vue3 + Node.js（全新）

> **For Hermes:** 使用 subagent-driven-development skill 逐任务实施本计划。

**目标：** 将 ClayBBS 双站系统（论坛站 + 官方站）从 PHP 重构为 Vue3 前端 + Node.js 后端，保留论坛站 UI 样式，全新设计底层架构、插件/主题/授权/热更新系统。无旧版兼容包袱。

**架构：** Monorepo 单仓，三个独立应用共享类型定义。

**技术栈：**
- 前端：Vue 3 + Vite + TypeScript + Pinia + Vue Router
- 后端：Node.js 20+ + TypeScript + Koa2 + Knex.js
- 数据库：MySQL 8
- 缓存：Redis
- 实时通信：Socket.IO
- 部署：Docker + Docker Compose

**重要前提：** 系统尚未分发，不存在旧客户端，不需要任何 v1 兼容，不需要支持 PHP 引擎。全部从零开始。

---

## 项目规模评估

### 论坛站 (ovo.claybbs.com)

| 维度 | 数量 |
|------|------|
| 数据库表 | 92 |
| Web 页面模板 | 50 |
| API/路由 | 180+ |
| CSS 文件 | 4 (~206KB) |
| JS 文件 | 2 (~17KB) |
| 需保留 UI 的页面 | ~45 |
| 需重新设计的系统 | 4 (插件/主题/授权/热更新) |

### 官方站 (www.claybbs.com)

| 维度 | 数量 |
|------|------|
| 数据库表 | 22 |
| 控制器 | 20 |
| 服务 | 6 |
| 视图文件 | 45 |
| API 端点 | 11 |
| 全部重新设计 | ✅ |

---

## 项目目录结构

```
claybebs/
├── packages/
│   ├── forum/              # 论坛站前端 (ovo.claybbs.com)
│   │   ├── src/
│   │   │   ├── views/       # 页面组件（保留现有 UI）
│   │   │   ├── components/  # 通用组件
│   │   │   ├── stores/      # Pinia 状态
│   │   │   ├── router/
│   │   │   ├── api/         # API 调用层
│   │   │   ├── styles/      # 迁移过来的 CSS
│   │   │   ├── composables/
│   │   │   └── types/
│   │   ├── vite.config.ts
│   │   └── package.json
│   │
│   ├── official/           # 官方站前端 (www.claybbs.com) — 全新设计
│   │   ├── src/
│   │   │   ├── views/
│   │   │   ├── components/
│   │   │   ├── stores/
│   │   │   ├── router/
│   │   │   ├── api/
│   │   │   └── types/
│   │   └── package.json
│   │
│   ├── server/             # Node.js 后端（统一）
│   │   ├── src/
│   │   │   ├── forum/       # 论坛站路由/控制器/服务
│   │   │   ├── official/    # 官方站路由/控制器/服务
│   │   │   ├── shared/      # 共享中间件/工具
│   │   │   ├── websocket/   # Socket.IO
│   │   │   ├── plugins/     # 插件引擎
│   │   │   ├── hooks/       # 钩子系统
│   │   │   ├── themes/      # 主题引擎
│   │   │   └── update/      # 热更新引擎
│   │   ├── migrations/
│   │   └── package.json
│   │
│   ├── admin/              # 后台管理面板 (Vue3) — 全新设计
│   │   ├── src/
│   │   │   ├── forum/       # 论坛后台
│   │   │   └── official/    # 官方站后台
│   │   └── package.json
│   │
│   └── shared/             # 共享类型/常量
│       ├── types/
│       └── constants/
│
├── docker/
│   ├── Dockerfile.forum
│   ├── Dockerfile.official
│   ├── Dockerfile.server
│   └── docker-compose.yml
│
├── docs/plans/
├── pnpm-workspace.yaml
├── tsconfig.base.json
└── package.json
```

---

## Part A: 论坛站重构 (ovo.claybbs.com)

### Phase 0: 脚手架

#### Task 0.1: 创建 Monorepo
- pnpm-workspace.yaml
- tsconfig.base.json
- forum/server/shared/admin 四个 package
- 验证 `pnpm install`

#### Task 0.2: Vue3 论坛前端
- vite + vue-ts + vue-router + pinia + axios
- vite 代理 `/api` 到后端
- 基础路由骨架
- 验证能启动

#### Task 0.3: Node.js 后端
- koa2 + koa-router + knex + mysql2 + socket.io + ioredis
- 健康检查路由
- 验证能启动

#### Task 0.4: Docker Compose
- forum 前端 + server 后端 + mysql + redis
- 验证全栈启动

#### Task 0.5: 数据库连接
- knexfile.ts
- .env 配置
- 连接到现有 claybbs 库

---

### Phase 1: 数据库迁移

#### Task 1.1: 用户与认证 (7 张)
users, roles, permissions, role_permissions, user_roles, user_oauth_accounts, user_login_sessions

#### Task 1.2: 论坛内容 (15 张)
categories, sections, section_follows, threads, posts, thread_favorites, thread_drafts, reply_drafts, thread_edit_logs, thread_revisions, thread_read_progress, thread_paywall_unlocks, thread_rewards, thread_collections, thread_collection_items

#### Task 1.3: 聊天系统 (9 张)
chat_groups, chat_group_members, chat_group_messages, chat_group_invites, chat_group_join_requests, private_conversations, private_messages, moments, moment_profiles

#### Task 1.4: 用户社交 (11 张)
user_follows, user_blocks, user_reading_list, user_privacy_settings, user_notification_settings, content_likes, content_reports, content_report_logs, recycle_bin, attachments, mentions

#### Task 1.5: 财务支付 (9 张)
currencies, wallets, wallet_transactions, payment_channels, payment_packages, payment_orders, payment_callback_logs, payment_redeem_codes, payment_redeem_logs

#### Task 1.6: 装饰插件等级 (12 张)
plugin_badges, plugin_badge_qualities, plugin_badge_logs, plugin_user_badges, plugin_avatar_frames, plugin_avatar_frame_logs, plugin_user_avatar_frames, plugin_nameplates, plugin_nameplate_logs, plugin_user_nameplates, plugin_migrations, plugin_error_logs

#### Task 1.7: 系统管理 (~25 张)
settings, system_messages, user_message_reads, admin_audit_logs, moderator_actions, verification_*, levels, user_growth_stats, user_exp_logs, tasks, user_task_*, user_checkins, user_credit_*, ai_*, banners, announcements, announcement_reads, group_reports, system_updates, update_migrations

#### Task 1.8: 新增表
- `plugins` — 插件注册
- `plugin_hooks` — 钩子注册
- `plugin_routes` — 插件路由
- `themes` — 主题注册
- `theme_variables` — CSS 变量覆盖

#### Task 1.9: 数据迁移工具
scripts/migrate-data.ts — 从旧 PHP 库导入数据

---

### Phase 2: 认证系统

#### Task 2.1: JWT 中间件
- JWT 签发/验证
- Cookie + Header 双模式
- Refresh token

#### Task 2.2: 注册/登录/注销
- POST /api/auth/register, login, logout
- 频率限制

#### Task 2.3: OAuth
- QQ、GitHub、微信

#### Task 2.4: 忘记/重置密码

---

### Phase 3: 论坛核心

#### Task 3.1: 板块分类 API
#### Task 3.2: 帖子 CRUD API
#### Task 3.3: 回复 API
#### Task 3.4: 互动（点赞/收藏/关注/拉黑/举报）
#### Task 3.5: 搜索 API
#### Task 3.6: 文件上传

---

### Phase 4: 聊天系统 (WebSocket)

#### Task 4.1: Socket.IO 服务端
- JWT 认证、房间管理、在线状态

#### Task 4.2: 私聊 + WebSocket

#### Task 4.3: 群聊 + WebSocket

#### Task 4.4: 朋友圈 (Moments)

---

### Phase 5: 用户中心

#### Task 5.1: 个人中心 API
#### Task 5.2: 个人主页 API
#### Task 5.3: 用户设置 API
#### Task 5.4: 关注/粉丝列表

---

### Phase 6: 装饰系统

#### Task 6.1: 勋章 API
#### Task 6.2: 头像框 API
#### Task 6.3: 聊天气泡 API
#### Task 6.4: 铭牌 API

---

### Phase 7: 财务与支付

#### Task 7.1: 钱包 API
#### Task 7.2: 支付 API
#### Task 7.3: 成长/等级系统

---

### Phase 8: 后台管理 (Vue3)

#### Task 8.1: Admin 初始化
#### Task 8.2: 核心管理页（仪表盘/用户/帖子/板块/审核）
#### Task 8.3: 装饰/财务管理
#### Task 8.4: 系统管理（角色权限/设置/日志/备份）

---

### Phase 9: 前端 UI 迁移（保留现有样式）

#### Task 9.1: CSS 迁移
- style.css → global.css
- desktop-forum.css → desktop.css
- editor-enhance.css → editor.css
- PHP 内联样式 → 组件 style

#### Task 9.2: 布局组件
- TopBar.vue, BottomNav.vue, AppLayout.vue
- 保持 class 命名一致

#### Task 9.3: 首页
- HomeView.vue，复用 home-v2-* 系列 class

#### Task 9.4: 板块页
- SectionsView.vue, SectionView.vue
- 复用 plate-* 系列 class

#### Task 9.5: 帖子页
- ThreadView.vue, PublishView.vue, EditThreadView.vue
- 复用 forum-post-page, post-* 系列

#### Task 9.6: 聊天页（保留现有样式）
- ChatView.vue, PrivateChat.vue, GroupChat.vue, ChatDock.vue

#### Task 9.7: 用户中心（保留现有样式）
- MeView.vue, ProfileView.vue, SettingsView.vue, WalletView.vue

#### Task 9.8: 装饰中心
- DecorationView.vue, MedalsView.vue, AvatarFramesView.vue, BubblesView.vue

#### Task 9.9: 其他页面
- 登录/注册、搜索、公告、成长、认证、举报

---

### Phase 10: 论坛站测试部署

#### Task 10.1: 后端测试（Jest）
#### Task 10.2: API 集成测试（Supertest）
#### Task 10.3: 前端测试（Vitest）
#### Task 10.4: Docker 部署 + Nginx + SSL

---

## Part B: 官方站重构 (www.claybbs.com)

### Phase 11: 官方站脚手架

#### Task 11.1: Vue3 官方站前端
- vite + vue-ts，全新设计，不保留任何旧 UI

#### Task 11.2: 官方站后端路由
- server/src/official/ 下创建路由/控制器/服务
- 连接 clayup 数据库

#### Task 11.3: 官方站数据库迁移
- sites, packages, full_keys, publish_logs
- market_categories, market_items, market_versions, market_licenses, market_orders, market_acquisitions, market_appeals, market_item_images
- developer_applications, developer_orders, developer_withdrawals
- license_logs, license_keys, license_sites
- admin_users, admin_logs, settings

---

### Phase 12: 官方站 API 层

#### Task 12.1: 站点注册与认证
- HMAC-SHA256 签名验证（论坛站↔官方站通信）
- Redis nonce 防重放
- 站点注册/管理

#### Task 12.2: 更新中心 API
- POST /api/update/check — 检查更新（增量包）
- POST /api/update/download — 下载更新包（ZIP + 哈希 + RSA 签名）
- POST /api/update/report — 上报安装结果
- 增量包生成（diff patch）
- 全量包打包
- 版本链管理

#### Task 12.3: 授权 API
- GET /api/license/public-key — RSA 公钥
- POST /api/license/activate — 激活授权
- POST /api/license/verify — 在线验证
- 授权状态机：valid → grace（7天）→ locked
- 功能级别授权（features 数组）

#### Task 12.4: 插件市场 API
- POST /api/market/list — 列表
- POST /api/market/acquire — 获取
- POST /api/market/download — 下载（注入 license.json）
- POST /api/market/key-download — 授权码下载
- POST /api/market/pay-notify — 支付回调

---

### Phase 13: 官方站前台 UI（全新设计）

#### Task 13.1: 设计系统
- 色彩方案、字体、间距、组件库
- 响应式（桌面优先）

#### Task 13.2: 布局 + 首页
- 全新首页（产品介绍、功能特性、价格方案）

#### Task 13.3: 下载页
- 下载包、版本历史、系统要求

#### Task 13.4: 开发者中心
- 注册/登录、应用管理、版本提交、收入统计、提现

#### Task 13.5: 市场页面
- 插件市场、主题市场、详情页

#### Task 13.6: 文档中心
- API 文档、插件开发文档、主题开发文档

#### Task 13.7: 用户中心
- 个人资料、授权管理、购买历史

---

### Phase 14: 官方站后台管理（全新设计）

#### Task 14.1: 登录与权限
#### Task 14.2: 仪表盘（站点数/下载统计/收入/成功率）
#### Task 14.3: 站点管理（注册站点/授权发放/续期/吊销）
#### Task 14.4: 更新包管理（发布/回滚/推送配置）
#### Task 14.5: 市场管理（应用审核/版本审核/分类/举报）
#### Task 14.6: 开发者管理（审核/提现/申诉）
#### Task 14.7: 订单财务（订单/流水/结算）
#### Task 14.8: 系统设置（站点/支付/邮件/安全）
#### Task 14.9: 日志审计

---

### Phase 15: 官方站测试部署

---

## Part C: 通用系统

### Phase 16: 插件系统

#### Task 16.1: 插件引擎
- EventEmitter 事件总线
- plugin.json 声明 hooks/routes/config
- 插件可注册 API 路由 + 前端组件
- 钩子：boot、install、uninstall
- 插件配置持久化

#### Task 16.2: 插件管理器
- 发现、加载、卸载、配置

#### Task 16.3: 插件市场对接

---

### Phase 17: 主题系统

#### Task 17.1: 主题引擎
- CSS 变量系统
- 支持亮色/暗色两套变量
- 主题切换即时生效

#### Task 17.2: 主题管理器
- 发现、切换、卸载

#### Task 17.3: 主题市场对接

---

### Phase 18: 授权系统

#### Task 18.1: 授权架构
- RSA + HMAC 双签名
- 域名绑定 + 指纹验证
- 在线验证 + 7天离线宽限
- 功能级别授权
- Redis 缓存 + 定时刷新

#### Task 18.2: 授权守卫中间件

#### Task 18.3: 授权管理界面

---

### Phase 19: 热更新系统

#### Task 19.1: 更新中心客户端
- 与官方站 API 对接
- 增量/全量更新
- 签名校验

#### Task 19.2: 更新安装器
- 预检查 → 下载 → 校验 → 备份 → 安装 → 健康检查 → 上报
- 快照回滚

---

## 实施顺序

```
Phase 0 (脚手架) ──────────────────────┐
Phase 1 (数据库)                        │
Phase 2 (认证)                          │
Phase 3 (论坛核心)                      │
Phase 4 (聊天)                          │
Phase 5 (用户中心)                      │
Phase 6 (装饰)                          │
Phase 7 (财务)                          │
Phase 8 (后台)                          │
Phase 9 (UI迁移)  ← 保留样式           │ Part A
Phase 10 (测试部署)                     │ 论坛站
                                        │
Phase 11 (官方站脚手架) ← Phase 0 后可并行│
Phase 12 (官方站API)                    │ Part B
Phase 13 (官方站前台UI) ← 全新设计      │ 官方站
Phase 14 (官方站后台)                   │
Phase 15 (官方站测试)                   │
                                        │
Phase 16 (插件系统) ← Phase 3+12 后     │
Phase 17 (主题系统)                     │ Part C
Phase 18 (授权系统) ← Phase 12 后       │ 通用系统
Phase 19 (热更新)   ← Phase 18 后       │
```

---

## 工期估算

| 部分 | 预估 |
|------|------|
| **Part A: 论坛站** | 35-53 天 |
| **Part B: 官方站** | 21.5-31 天 |
| **Part C: 通用系统** | 12-16 天 |
| **总计（部分并行）** | **55-75 天** |
