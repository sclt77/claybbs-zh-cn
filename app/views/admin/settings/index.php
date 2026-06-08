<?php
$pageTitle = '站点设置';
require dirname(__DIR__) . '/layouts/main.php';
?>

<div class="settings-head">
  <div>
    <div class="page-title">站点设置</div>
    <p>集中管理论坛基础信息、SMTP 邮件、注册验证与审核入口。</p>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="settings-alert error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
  <div class="settings-alert success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="settings-tabs" role="tablist" aria-label="站点设置分类">
  <button type="button" class="settings-tab active" data-tab="site">基础设置</button>
  <button type="button" class="settings-tab" data-tab="smtp">SMTP 邮件</button>
  <button type="button" class="settings-tab" data-tab="register">注册设置</button>
  <button type="button" class="settings-tab" data-tab="review">内容审核</button>
  <button type="button" class="settings-tab" data-tab="publish-entry">发布入口</button>
  <button type="button" class="settings-tab" data-tab="cookie">Cookie 通知</button>
  <button type="button" class="settings-tab" data-tab="friend">好友私聊</button>
  <button type="button" class="settings-tab" data-tab="oauth">第三方登录</button>
</div>

<section class="settings-panel active" data-panel="site">
  <div class="settings-section-title">
    <h3>基础设置</h3>
    <p>用于前台展示、系统邮件链接和站点识别。</p>
  </div>
  <form method="post" class="settings-grid-form">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="site">
    <label>
      <span>站点名称</span>
      <input class="input" name="site_name" value="<?= htmlspecialchars((string)($settings['site_name'] ?? 'ClayBBS')) ?>" required>
    </label>
    <label>
      <span>Logo 文字</span>
      <input class="input" name="site_logo_text" value="<?= htmlspecialchars((string)($settings['site_logo_text'] ?? 'ClayBBS')) ?>" required>
    </label>
    <label class="full-row">
      <span>首页副标题</span>
      <input class="input" name="site_tagline" value="<?= htmlspecialchars((string)($settings['site_tagline'] ?? '')) ?>">
    </label>
    <label class="full-row">
      <span>页脚文案</span>
      <input class="input" name="footer_text" value="<?= htmlspecialchars((string)($settings['footer_text'] ?? '')) ?>">
    </label>
    <label class="full-row">
      <span>站点 URL</span>
      <input class="input" name="site_url" value="<?= htmlspecialchars((string)($settings['site_url'] ?? '')) ?>" placeholder="https://example.com">
      <small>用于注册验证邮件中的链接，末尾不加斜杠。</small>
    </label>
    <div class="settings-actions full-row"><button class="btn" type="submit">保存基础设置</button></div>
  </form>
</section>

<section class="settings-panel" data-panel="smtp">
  <div class="settings-section-title">
    <h3>SMTP 邮件</h3>
    <p>用于注册验证邮件等系统通知发送。</p>
  </div>
  <form method="post" class="settings-grid-form">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="smtp">
    <label>
      <span>SMTP 服务器</span>
      <input class="input" name="smtp_host" value="<?= htmlspecialchars((string)($settings['smtp_host'] ?? '')) ?>" placeholder="smtp.example.com">
    </label>
    <label>
      <span>端口</span>
      <input class="input" name="smtp_port" value="<?= htmlspecialchars((string)($settings['smtp_port'] ?? '465')) ?>" placeholder="465">
    </label>
    <label>
      <span>用户名</span>
      <input class="input" name="smtp_username" value="<?= htmlspecialchars((string)($settings['smtp_username'] ?? '')) ?>">
    </label>
    <label>
      <span>密码</span>
      <input class="input" type="password" name="smtp_password" value="<?= htmlspecialchars((string)($settings['smtp_password'] ?? '')) ?>">
    </label>
    <label>
      <span>发件人邮箱</span>
      <input class="input" name="smtp_from" value="<?= htmlspecialchars((string)($settings['smtp_from'] ?? '')) ?>">
    </label>
    <label>
      <span>发件人名称</span>
      <input class="input" name="smtp_from_name" value="<?= htmlspecialchars((string)($settings['smtp_from_name'] ?? 'ClayBBS')) ?>">
    </label>
    <label>
      <span>加密方式</span>
      <select class="input" name="smtp_encrypt">
        <option value="ssl" <?= ($settings['smtp_encrypt'] ?? 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL</option>
        <option value="tls" <?= ($settings['smtp_encrypt'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
        <option value="none" <?= ($settings['smtp_encrypt'] ?? '') === 'none' ? 'selected' : '' ?>>无</option>
      </select>
    </label>
    <div class="settings-actions full-row"><button class="btn" type="submit">保存 SMTP 设置</button></div>
  </form>
</section>

<section class="settings-panel" data-panel="register">
  <div class="settings-section-title">
    <h3>注册设置</h3>
    <p>控制新用户注册后的邮箱验证要求。</p>
  </div>
  <form method="post" class="settings-simple-form">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="register">
    <div class="radio-row">
      <span>邮箱验证</span>
      <label><input type="radio" name="email_verify_required" value="1" <?= ($settings['email_verify_required'] ?? '0') === '1' ? 'checked' : '' ?>> 开启（注册后必须验证邮箱才能登录）</label>
      <label><input type="radio" name="email_verify_required" value="0" <?= ($settings['email_verify_required'] ?? '0') !== '1' ? 'checked' : '' ?>> 关闭（无需验证直接登录）</label>
    </div>
    <div class="settings-actions"><button class="btn" type="submit">保存注册设置</button></div>
  </form>
</section>

<section class="settings-panel" data-panel="review">
  <div class="settings-section-title">
    <h3>内容审核</h3>
    <p>人工审核、AI 审核、提供商和审核日志已统一迁移到后台“审核中心”。</p>
  </div>
  <div class="review-redirect">
    <div>
      <strong>审核配置已集中管理</strong>
      <span>请前往审核中心维护发帖/回复审核、AI 提供商和审核记录。</span>
    </div>
    <a class="btn btn-light" href="/admin.php?path=review">前往审核中心</a>
  </div>
</section>

<section class="settings-panel" data-panel="publish-entry">
  <div class="settings-section-title">
    <h3>发布入口</h3>
    <p>配置移动端底栏“发帖”弹层顶部公告。</p>
  </div>
  <form method="post" class="settings-grid-form">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="publish_entry">
    <label class="full-row">
      <span>公告标题</span>
      <input class="input" name="publish_entry_notice_title" value="<?= htmlspecialchars((string)($settings['publish_entry_notice_title'] ?? '发帖规范')) ?>" placeholder="发帖规范">
    </label>
    <label class="full-row">
      <span>公告内容</span>
      <textarea class="textarea admin-min-h-120" name="publish_entry_notice_content" placeholder="欢迎来到社区，发布内容前请遵守社区规则。"><?= htmlspecialchars((string)($settings['publish_entry_notice_content'] ?? '欢迎来到社区，发布内容前请遵守社区规则。')) ?></textarea>
      <small>会显示在移动端底栏发帖弹层顶部，可留空。</small>
    </label>
    <div class="settings-actions full-row"><button class="btn" type="submit">保存发布入口设置</button></div>
  </form>
</section>

<section class="settings-panel" data-panel="cookie">
  <div class="settings-section-title">
    <h3>Cookie 通知</h3>
    <p>配置首次访问提示与 Cookie 政策内容。用户未确认前，返回页面仍会继续提示。</p>
  </div>
  <form method="post" class="settings-grid-form">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="cookie">
    <div class="radio-row full-row">
      <span>功能开关</span>
      <label><input type="checkbox" name="cookie_notice_enabled" value="1" <?= ($settings['cookie_notice_enabled'] ?? '1') === '1' ? 'checked' : '' ?>> 开启 Cookie 通知</label>
    </div>
    <label>
      <span>通知标题</span>
      <input class="input" name="cookie_notice_title" value="<?= htmlspecialchars((string)($settings['cookie_notice_title'] ?? 'Cookie 使用提示')) ?>">
    </label>
    <label>
      <span>确认按钮</span>
      <input class="input" name="cookie_notice_button" value="<?= htmlspecialchars((string)($settings['cookie_notice_button'] ?? '我知道了')) ?>">
    </label>
    <label class="full-row">
      <span>通知内容</span>
      <textarea class="textarea admin-min-h-100" name="cookie_notice_content"><?= htmlspecialchars((string)($settings['cookie_notice_content'] ?? '我们使用必要 Cookie 保持登录状态并保障站点安全。继续使用前请确认。')) ?></textarea>
    </label>
    <label>
      <span>同意有效期（天）</span>
      <input class="input" type="number" min="1" max="3650" name="cookie_consent_days" value="<?= htmlspecialchars((string)($settings['cookie_consent_days'] ?? '365')) ?>">
    </label>
    <label>
      <span>政策标题</span>
      <input class="input" name="cookie_policy_title" value="<?= htmlspecialchars((string)($settings['cookie_policy_title'] ?? 'Cookie 政策')) ?>">
    </label>
    <label class="full-row">
      <span>Cookie 政策内容</span>
      <textarea class="textarea admin-min-h-220" name="cookie_policy_content"><?= htmlspecialchars((string)($settings['cookie_policy_content'] ?? \App\Controllers\Web\CookiePolicyController::defaultPolicy())) ?></textarea>
      <small>前台 Cookie 通知中的“查看 Cookie 政策”会打开这篇内容。</small>
    </label>
    <div class="settings-actions full-row"><button class="btn" type="submit">保存 Cookie 设置</button><a class="btn btn-light admin-link-clean" href="/index.php?path=cookie-policy" target="_blank">预览政策</a></div>
  </form>
</section>

<section class="settings-panel" data-panel="friend">
  <div class="settings-section-title">
    <h3>好友私聊</h3>
    <p>配置公开账号 ID、好友入口和私聊审核。</p>
  </div>
  <form method="post" class="settings-grid-form">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="friend">
    <label>
      <span>账号 ID 前缀</span>
      <input class="input" name="friend_id_prefix" value="<?= htmlspecialchars((string)($settings['friend_id_prefix'] ?? 'CY')) ?>" placeholder="CY">
      <small>新用户随机账号 ID 使用此前缀，默认 CY。</small>
    </label>
    <label>
      <span>单条消息长度</span>
      <input class="input" type="number" min="50" max="5000" name="private_chat_message_max_length" value="<?= htmlspecialchars((string)($settings['private_chat_message_max_length'] ?? '1000')) ?>">
    </label>
    <label>
      <span>轮询间隔 ms</span>
      <input class="input" type="number" min="1200" max="30000" name="private_chat_poll_interval" value="<?= htmlspecialchars((string)($settings['private_chat_poll_interval'] ?? '3000')) ?>">
    </label>
    <div class="radio-row full-row">
      <span>功能开关</span>
      <label><input type="checkbox" name="friend_system_enabled" value="1" <?= ($settings['friend_system_enabled'] ?? '1') === '1' ? 'checked' : '' ?>> 开启好友系统</label>
      <label><input type="checkbox" name="private_chat_enabled" value="1" <?= ($settings['private_chat_enabled'] ?? '1') === '1' ? 'checked' : '' ?>> 开启私聊</label>
      <label><input type="checkbox" name="group_chat_enabled" value="1" <?= ($settings['group_chat_enabled'] ?? '1') === '1' ? 'checked' : '' ?>> 开启群聊</label>
      <label><input type="checkbox" name="group_chat_review_enabled" value="1" <?= ($settings['group_chat_review_enabled'] ?? '0') === '1' ? 'checked' : '' ?>> 群聊消息 AI 审核</label>
      <label><input type="checkbox" name="friend_search_nickname_enabled" value="1" <?= ($settings['friend_search_nickname_enabled'] ?? '1') === '1' ? 'checked' : '' ?>> 允许通过昵称搜索</label>
    </div>
    <div class="settings-actions full-row"><button class="btn" type="submit">保存好友私聊设置</button></div>
  </form>
</section>

<section class="settings-panel" data-panel="oauth">
  <div class="settings-section-title">
    <h3>第三方登录</h3>
    <p>配置 QQ、GitHub、微信开放平台与彩虹聚合登录。回调地址可留空，系统会按站点 URL 自动生成。</p>
  </div>
  <form method="post" class="settings-grid-form">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="oauth">
    <?php $oauthNames=['qq'=>'QQ','github'=>'GitHub','wechat'=>'微信','rainbow'=>'彩虹聚合登录']; foreach($oauthNames as $key=>$name): ?>
      <div class="full-row admin-setting-value"><?= htmlspecialchars($name) ?></div>
      <div class="radio-row full-row admin-no-border-top">
        <label><input type="checkbox" name="oauth_<?= $key ?>_enabled" value="1" <?= ($settings['oauth_'.$key.'_enabled'] ?? '0') === '1' ? 'checked' : '' ?>> 开启 <?= htmlspecialchars($name) ?> 登录</label>
      </div>
      <?php if($key === 'rainbow'): ?>
        <label class="full-row"><span>聚合登录地址</span><input class="input" name="oauth_rainbow_base_url" value="<?= htmlspecialchars((string)($settings['oauth_rainbow_base_url'] ?? '')) ?>" placeholder="https://pay.example.com"></label>
      <?php endif; ?>
      <label><span>AppID / Client ID</span><input class="input" name="oauth_<?= $key ?>_client_id" value="<?= htmlspecialchars((string)($settings['oauth_'.$key.'_client_id'] ?? '')) ?>"></label>
      <label><span>AppSecret / Client Secret</span><input class="input" type="password" name="oauth_<?= $key ?>_client_secret" value="<?= htmlspecialchars((string)($settings['oauth_'.$key.'_client_secret'] ?? '')) ?>"></label>
      <label class="full-row"><span>回调地址</span><input class="input" name="oauth_<?= $key ?>_redirect_uri" value="<?= htmlspecialchars((string)($settings['oauth_'.$key.'_redirect_uri'] ?? '')) ?>" placeholder="<?= htmlspecialchars('/index.php?path=oauth/callback&provider=' . $key) ?>"><small>建议在第三方平台填完整域名地址，例如 https://你的域名/index.php?path=oauth/callback&provider=<?= htmlspecialchars($key) ?></small></label>
    <?php endforeach; ?>
    <div class="settings-actions full-row"><button class="btn" type="submit">保存第三方登录设置</button></div>
  </form>
</section>


<script>
(function(){
  const tabs = document.querySelectorAll('.settings-tab');
  const panels = document.querySelectorAll('.settings-panel');
  const key = 'clay-admin-settings-tab';
  function activate(name){
    tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.tab === name));
    panels.forEach(panel => panel.classList.toggle('active', panel.dataset.panel === name));
    try { localStorage.setItem(key, name); } catch(e) {}
  }
  tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.tab)));
  let initial = 'site';
  try { initial = localStorage.getItem(key) || initial; } catch(e) {}
  if (!document.querySelector('.settings-tab[data-tab="' + initial + '"]')) initial = 'site';
  activate(initial);
})();
</script>

<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
