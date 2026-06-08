<?php
$pageTitle = '插件 / 主题';
require dirname(__DIR__) . '/layouts/main.php';
$plugins = $plugins ?? [];
$themes = $themes ?? [];
$pluginBackups = $pluginBackups ?? [];
$pluginErrors = $pluginErrors ?? [];
$error = $error ?? '';
$tab = (string)($_GET['tab'] ?? 'plugins');
if (!in_array($tab, ['install','plugins','rollback','errors','themes'], true)) $tab = 'plugins';
function ext_tab_active(string $name, string $tab): string { return $name === $tab ? 'active' : ''; }
?>
<div class="page-header"><div class="page-title">插件 / 主题</div></div>
<?php if ($error): ?><div class="admin-alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (!empty($message)): ?><div class="admin-alert-ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<div class="admin-tabs" role="tablist" aria-label="插件主题管理">
  <a class="<?= ext_tab_active('plugins',$tab) ?>" href="/admin.php?path=extensions&tab=plugins">本地插件 <?= count($plugins) ?></a>
  <a class="<?= ext_tab_active('themes',$tab) ?>" href="/admin.php?path=extensions&tab=themes">本地主题 <?= count($themes) ?></a>
  <a class="<?= ext_tab_active('install',$tab) ?>" href="/admin.php?path=extensions&tab=install">授权安装</a>
  <a class="<?= ext_tab_active('rollback',$tab) ?>" href="/admin.php?path=extensions&tab=rollback">插件回滚 <?= count($pluginBackups) ?></a>
  <a class="<?= ext_tab_active('errors',$tab) ?>" href="/admin.php?path=extensions&tab=errors">运行错误 <?= count($pluginErrors) ?></a>
</div>

<?php if ($tab === 'install'): ?>
<div class="card admin-mb-18">
  <h3 class="admin-card-title">通过授权 Key 安装</h3>
  <div class="admin-muted admin-mb-14">请先在官方站应用市场获取/购买应用，在"我的购买"中复制授权 Key。安装和更新插件时，会自动执行插件包内的数据库 SQL。</div>
  <form method="post" action="/admin.php?path=extensions" data-refresh-on-success class="admin-grid-form-inline">
    <?= csrf_field() ?><input type="hidden" name="_action" value="install_by_key">
    <div><label class="admin-label">应用授权 Key</label><input class="input" name="license_key" placeholder="APP-XXXX-XXXX" required></div>
    <button class="btn" type="submit">获取并安装</button>
  </form>
</div>
<?php endif; ?>

<?php if ($tab === 'plugins'): ?>
<div class="card admin-mb-18">
  <h3 class="admin-card-title">本地插件</h3>
  <div class="admin-muted admin-mb-14">已接入稳定 Plugin API 公共层。建议插件声明 <code>api_version</code>,并仅依赖公开 Hook / Route / Setting / Theme 契约,避免直接耦合核心内部服务。</div>
  <div class="admin-grid-gap">
    <?php if ($plugins): foreach ($plugins as $p): ?>
      <?php $dep = $p['dependency_status'] ?? ['ok'=>true,'messages'=>[]]; $lastErr = $p['last_error'] ?? null; $api = $p['api_status'] ?? ['ok'=>true,'messages'=>[],'current'=>'','required'=>'']; ?>
      <div class="admin-list-card">
        <div>
          <div class="admin-bold"><?= htmlspecialchars($p['name']) ?> <span class="admin-muted">v<?= htmlspecialchars($p['version']) ?></span><?php if (!empty($p['update_available'])): ?><span class="badge badge-warn admin-ms-6">可更新到 v<?= htmlspecialchars((string)$p['latest_version']) ?></span><?php elseif (!empty($p['latest_version'])): ?><span class="badge badge-ok admin-ms-6">已是最新版</span><?php endif; ?><?php if (empty($api['ok'])): ?><span class="badge badge-err admin-ms-6">API 不兼容</span><?php elseif (!empty($p['api_version'])): ?><span class="badge badge-ok admin-ms-6">API <?= htmlspecialchars((string)$p['api_version']) ?></span><?php endif; ?><?php if (empty($dep['ok'])): ?><span class="badge badge-err admin-ms-6">依赖异常</span><?php endif; ?><?php if ($lastErr): ?><span class="badge badge-err admin-ms-6">运行错误</span><?php endif; ?></div>
          <div class="admin-muted admin-mt-4"><?= htmlspecialchars($p['description']) ?></div>
          <div class="admin-muted admin-mt-4">slug: <?= htmlspecialchars($p['slug']) ?> · Plugin API:<?= htmlspecialchars((string)($p['api_version'] ?: '未声明')) ?><?php if (!empty($p['license_required'])): ?> · 授权:<?= !empty($p['license_valid']) ? '有效' : '无效' ?><?php endif; ?><?php if (!empty($p['dependencies'])): ?> · 依赖:<?= htmlspecialchars(implode('、', array_map(static fn($d) => $d['slug'] . (!empty($d['version']) ? (' >= '.$d['version']) : ''), $p['dependencies']))) ?><?php endif; ?><?php if (!empty($p['market_error'])): ?> · 更新检查失败:<?= htmlspecialchars((string)$p['market_error']) ?><?php elseif (empty($p['market_item'])): ?> · 官方站未匹配到该插件<?php endif; ?></div>
          <?php if (empty($api['ok'])): ?><div class="admin-danger admin-mt-4">API 不兼容:<?= htmlspecialchars(implode(';', $api['messages'] ?? [])) ?></div><?php elseif (empty($p['api_version'])): ?><div class="admin-muted admin-mt-4">建议补充 <code>api_version</code>,便于未来核心加密发行时做兼容校验。</div><?php endif; ?>
          <?php if (empty($dep['ok'])): ?><div class="admin-danger admin-mt-4">依赖未满足:<?= htmlspecialchars(implode(';', $dep['messages'] ?? [])) ?></div><?php endif; ?>
          <?php if ($lastErr): ?><div class="admin-danger admin-mt-4">最近错误:<?= htmlspecialchars((string)($lastErr['message'] ?? '')) ?>(<?= htmlspecialchars((string)($lastErr['created_at'] ?? '')) ?>)</div><?php endif; ?>
        </div>
        <div class="admin-actions"><?php if (!empty($p['update_available']) && !empty($p['market_item_id'])): ?><form method="post" action="/admin.php?path=extensions" data-refresh-on-success onsubmit="return confirm('确定更新该插件?系统会先备份旧插件目录,并自动执行插件包内数据库 SQL。')"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int)$p['market_item_id'] ?>"><button class="btn" name="_action" value="update_plugin">更新</button></form><?php endif; ?><form method="post" action="/admin.php?path=extensions" data-refresh-on-success><?= csrf_field() ?><input type="hidden" name="slug" value="<?= htmlspecialchars($p['slug']) ?>"><?php if ($p['enabled']): ?><button class="btn btn-light" name="_action" value="disable_plugin">停用</button><?php else: ?><button class="btn" name="_action" value="enable_plugin">启用</button><?php endif; ?></form><form method="post" action="/admin.php?path=extensions" data-refresh-on-success onsubmit="return confirm('确定卸载该插件?系统会先执行插件包内 uninstall.sql，清理该插件创建的数据库表和数据，然后删除插件目录。') && confirm('请再次确认:卸载后插件文件和插件数据都会被删除，确定继续?')"><?= csrf_field() ?><input type="hidden" name="slug" value="<?= htmlspecialchars($p['slug']) ?>"><button class="btn btn-light" name="_action" value="remove_plugin">卸载</button></form></div>
      </div>
    <?php endforeach; else: ?><div class="admin-empty">暂无本地插件</div><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($tab === 'rollback'): ?>
<div class="card admin-mb-18">
  <h3 class="admin-card-title">插件回滚</h3>
  <div class="admin-muted admin-mb-14">插件更新前会自动备份旧目录;这里可以把指定备份恢复为当前插件,恢复前也会备份现有目录。</div>
  <div class="table-responsive"><table class="table"><thead><tr><th>插件</th><th>版本</th><th>备份</th><th>时间</th><th>操作</th></tr></thead><tbody>
    <?php foreach ($pluginBackups as $b): ?><tr><td><?= htmlspecialchars((string)($b['name'] ?? $b['slug'])) ?><div class="muted">slug: <?= htmlspecialchars((string)$b['slug']) ?></div></td><td><?= htmlspecialchars((string)($b['version'] ?? '')) ?></td><td><?= htmlspecialchars((string)$b['id']) ?></td><td><?= htmlspecialchars((string)$b['created_at']) ?></td><td><form method="post" action="/admin.php?path=extensions" data-refresh-on-success onsubmit="var v=prompt('确定回滚该插件备份?当前插件目录会先备份再被覆盖。请输入 ROLLBACK 确认'); if(v!=='ROLLBACK') return false; this.querySelector('[name=confirm_text]').value=v; return true;"><?= csrf_field() ?><input type="hidden" name="backup_id" value="<?= htmlspecialchars((string)$b['id']) ?>"><input type="hidden" name="confirm_text" value=""><button class="btn btn-light" name="_action" value="rollback_plugin">回滚</button></form></td></tr><?php endforeach; ?>
    <?php if (empty($pluginBackups)): ?><tr><td colspan="5" class="admin-empty">暂无插件备份</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<?php if ($tab === 'errors'): ?>
<div class="card admin-mb-18">
  <h3 class="admin-card-title">插件运行错误</h3>
  <div class="table-responsive"><table class="table"><thead><tr><th>时间</th><th>插件</th><th>阶段</th><th>错误</th></tr></thead><tbody>
    <?php foreach ($pluginErrors as $e): ?><tr><td><?= htmlspecialchars((string)$e['created_at']) ?></td><td><?= htmlspecialchars((string)$e['plugin_slug']) ?></td><td><?= htmlspecialchars((string)$e['phase']) ?></td><td class="admin-pre-wrap"><?= htmlspecialchars((string)$e['message']) ?></td></tr><?php endforeach; ?>
    <?php if (empty($pluginErrors)): ?><tr><td colspan="4" class="admin-empty">暂无插件运行错误</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<?php if ($tab === 'themes'): ?>
<div class="card">
  <h3 class="admin-card-title">本地主题</h3>
  <div class="admin-muted admin-mb-14">已接入稳定 Theme API 公共层。主题应只覆盖公开视图结构、资源目录与模板插槽,不要直接依赖核心内部服务实现。</div>
  <div class="admin-grid-gap">
    <?php foreach ($themes as $t): ?>
      <?php $api = $t['api_status'] ?? ['ok'=>true,'messages'=>[]]; ?>
      <div class="admin-list-card">
        <div><div class="admin-bold"><?= htmlspecialchars($t['name']) ?> <span class="admin-muted">v<?= htmlspecialchars($t['version']) ?></span><?php if (!empty($t['update_available'])): ?><span class="badge badge-warn admin-ms-6">可更新到 v<?= htmlspecialchars((string)$t['latest_version']) ?></span><?php elseif (!empty($t['latest_version'])): ?><span class="badge badge-ok admin-ms-6">已是最新版</span><?php endif; ?><?php if (empty($api['ok'])): ?><span class="badge badge-err admin-ms-6">API 不兼容</span><?php elseif (!empty($t['api_version'])): ?><span class="badge badge-ok admin-ms-6">API <?= htmlspecialchars((string)$t['api_version']) ?></span><?php endif; ?></div><div class="admin-muted admin-mt-4"><?= htmlspecialchars($t['description']) ?></div><div class="admin-muted admin-mt-4">slug: <?= htmlspecialchars($t['slug']) ?> · Theme API:<?= htmlspecialchars((string)($t['api_version'] ?: '未声明')) ?><?php if (!empty($t['license_required'])): ?> · 授权:<?= !empty($t['license_valid']) ? '有效' : '无效' ?><?php endif; ?><?php if (!empty($t['market_error'])): ?> · 更新检查失败:<?= htmlspecialchars((string)$t['market_error']) ?><?php endif; ?></div><?php if (empty($api['ok'])): ?><div class="admin-danger admin-mt-4">API 不兼容:<?= htmlspecialchars(implode(';', $api['messages'] ?? [])) ?></div><?php elseif (empty($t['api_version']) && $t['slug'] !== 'default'): ?><div class="admin-muted admin-mt-4">建议补充 <code>api_version</code>,便于未来加密发行包进行兼容验证。</div><?php endif; ?></div>
        <div class="admin-actions"><?php if (!empty($t['update_available']) && !empty($t['market_item_id'])): ?><form method="post" action="/admin.php?path=extensions" data-refresh-on-success onsubmit="return confirm('确定更新该主题?系统会先备份旧主题目录。')"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int)$t['market_item_id'] ?>"><button class="btn" name="_action" value="update_theme">更新</button></form><?php endif; ?><?php if ($t['active']): ?><span class="badge badge-ok">当前主题</span><?php else: ?><form method="post" action="/admin.php?path=extensions" data-refresh-on-success><?= csrf_field() ?><input type="hidden" name="slug" value="<?= htmlspecialchars($t['slug']) ?>"><button class="btn" name="_action" value="activate_theme">启用主题</button></form><?php endif; ?><?php if ($t['slug'] !== 'default'): ?><form method="post" action="/admin.php?path=extensions" data-refresh-on-success onsubmit="return confirm('确定卸载该主题?')"><?= csrf_field() ?><input type="hidden" name="slug" value="<?= htmlspecialchars($t['slug']) ?>"><button class="btn btn-light" name="_action" value="remove_theme">卸载</button></form><?php endif; ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
