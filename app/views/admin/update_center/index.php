<?php $pageTitle='官方热更新'; require dirname(__DIR__) . '/layouts/main.php'; ?>
<?php
$pkg = (!empty($lastCheck) && !empty($lastCheck['update'])) ? ($lastCheck['package'] ?? []) : [];
$licensePayload = [];
if (!empty($config['license_data'])) {
    $licenseDecoded = json_decode((string)$config['license_data'], true);
    if (is_array($licenseDecoded) && is_array($licenseDecoded['payload'] ?? null)) {
        $licensePayload = $licenseDecoded['payload'];
    }
}
$siteId = trim((string)($config['site_id'] ?? ($licensePayload['site_id'] ?? '')));
$token = trim((string)($config['token'] ?? ($licensePayload['token'] ?? '')));
$licenseBound = $licenseLocalOk && !empty($config['license_key']) && !empty($config['domain']) && $siteId !== '';
$ready = $licenseBound && !empty($config['public_key']);
$lastHealth = $_SESSION['update_last_health'] ?? [];
?>
<div class="page-header"><div class="page-title">官方热更新</div></div>

<?php if (!empty($message)): ?><div class="admin-alert ok"><strong>操作成功</strong><pre><?= htmlspecialchars($message) ?></pre></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="admin-alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<section class="uc-hero card">
  <div>
    <span class="uc-badge">Hot Update Console</span>
    <h2>官方热更新控制台</h2>
    <p>当前论坛只支持官方热更新：授权校验、签名验包、差分更新、数据库迁移、快照回滚和失败上报补发。</p>
  </div>
  <form method="post" action="/admin.php?path=update-center">
    <?= csrf_field() ?><input type="hidden" name="_action" value="check">
    <button class="btn" type="submit">检查官方更新</button>
  </form>
</section>

<div class="uc-grid">
  <div class="card uc-stat"><span>当前版本</span><b><?= htmlspecialchars((string)($config['current_version'] ?? '0.0.0')) ?></b><em><?= htmlspecialchars((string)($config['branch'] ?? 'main')) ?></em></div>
  <div class="card uc-stat"><span>授权状态</span><b class="<?= $licenseLocalOk?'good':'bad' ?>"><?= $licenseLocalOk?'通过':'未通过' ?></b><em><?= !empty($config['domain']) ? htmlspecialchars((string)$config['domain']) : '未配置域名' ?></em></div>
  <div class="card uc-stat"><span>站点绑定</span><b class="<?= $licenseBound?'good':'bad' ?>"><?= $licenseBound?'已绑定':'未完成' ?></b><em><?= $siteId !== '' ? 'Site ID 已配置' : '缺少 Site ID' ?></em></div>
  <div class="card uc-stat"><span>待补发上报</span><b><?= count($queuedReports ?? []) ?></b><em><?= !empty($queuedReports)?'需要重试':'队列为空' ?></em></div>
</div>

<div class="uc-layout">
  <main class="uc-main">
    <section class="card">
      <div class="uc-section-head"><div><h3>更新状态</h3><p>检查官方站是否存在可用差分包。</p></div></div>
      <?php if (!empty($pkg)): ?>
        <div class="update-package">
          <div class="pkg-title"><span>发现新版本</span><strong><?= htmlspecialchars((string)($pkg['to_version'] ?? '')) ?></strong></div>
          <div class="pkg-summary">
            <span><?= htmlspecialchars((string)($pkg['from_version'] ?? ($config['current_version'] ?? ''))) ?> → <?= htmlspecialchars((string)($pkg['to_version'] ?? '')) ?></span>
            <span><?= !empty($pkg['has_code'])?'代码':'无代码' ?></span>
            <span><?= !empty($pkg['has_db'])?'含数据库':'无数据库' ?></span>
            <span><?= htmlspecialchars((string)($pkg['update_level'] ?? 'normal')) ?></span>
          </div>
          <?php if (!empty($pkg['notes'])): ?><div class="pkg-notes"><strong>更新说明</strong><div><?= nl2br(htmlspecialchars((string)$pkg['notes'])) ?></div></div><?php endif; ?>
          <details class="pkg-tech"><summary>技术详情</summary><div class="pkg-meta">
            <div><b>包 ID</b><span><?= (int)($pkg['id'] ?? 0) ?></span></div>
            <div><b>分支</b><span><?= htmlspecialchars((string)($pkg['branch'] ?? 'main')) ?></span></div>
            <div><b>回滚包</b><span><?= !empty($pkg['has_rollback'])?'可用':'无' ?></span></div>
            <div><b>完整包</b><span><?= !empty($pkg['full_file']) ? htmlspecialchars((string)$pkg['full_file']) : '无' ?></span></div>
            <div><b>包文件</b><span><?= htmlspecialchars((string)($pkg['filename'] ?? '')) ?></span></div>
            <div><b>强制更新</b><span><?= !empty($pkg['force_update'])?'是':'否' ?></span></div>
          </div></details>
          <form method="post" action="/admin.php?path=update-center" class="apply-form">
            <?= csrf_field() ?><input type="hidden" name="_action" value="apply"><input type="hidden" name="package_id" value="<?= (int)($pkg['id'] ?? 0) ?>">
            <label>二次确认<input class="input" name="confirm_text" placeholder="输入 UPDATE" required></label>
            <label>应用类型<select class="select" name="kind"><option value="package">热更新包</option><?php if (!empty($pkg['has_rollback'])): ?><option value="rollback">官方回滚包</option><?php endif; ?></select></label>
            <button class="btn warn" type="submit">执行热更新</button>
          </form>
        </div>
      <?php elseif (!empty($lastCheck)): ?>
        <div class="empty-state">当前已是官方最新版本。</div>
      <?php else: ?>
        <div class="empty-state">还没有检查更新，点击右上角“检查官方更新”。</div>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="uc-section-head"><div><h3>回滚与恢复</h3><p>热更新前会创建快照；也可使用最近一次官方回滚包。</p></div></div>
      <div class="rollback-grid">
        <form method="post" action="/admin.php?path=update-center" onsubmit="var v=prompt('确认从快照回滚？当前文件会被快照覆盖。请输入 ROLLBACK 确认'); if(v!=='ROLLBACK') return false; this.querySelector('[name=confirm_text]').value=v; return true;">
          <?= csrf_field() ?><input type="hidden" name="_action" value="rollback"><input type="hidden" name="confirm_text" value="">
          <label>本地快照<select class="select" name="snapshot_path" required><?php foreach ($snapshots as $snap): ?><option value="<?= htmlspecialchars(is_array($snap)?$snap['path']:$snap) ?>"><?= htmlspecialchars(is_array($snap)?$snap['label']:basename($snap)) ?></option><?php endforeach; ?></select></label>
          <button class="btn danger" type="submit">从快照回滚</button>
        </form>
        <form method="post" action="/admin.php?path=update-center" onsubmit="var v=prompt('确认应用官方回滚包？该操作会覆盖当前更新结果。请输入 ROLLBACK 确认'); if(v!=='ROLLBACK') return false; this.querySelector('[name=confirm_text]').value=v; return true;">
          <?= csrf_field() ?><input type="hidden" name="_action" value="rollback_pkg"><input type="hidden" name="confirm_text" value="">
          <label>官方回滚包<input class="input" value="storage/updates/rollback_last" readonly></label>
          <button class="btn danger" type="submit">应用官方回滚包</button>
        </form>
      </div>
    </section>

    <?php if (!empty($lastHealth) && is_array($lastHealth)): ?>
    <section class="card"><div class="uc-section-head"><div><h3>最近健康检查</h3><p>上次安装后的关键检查结果。</p></div></div><div class="health-list"><?php foreach($lastHealth as $check): ?><div class="health-row <?= !empty($check['ok'])?'ok':'bad' ?>"><b><?= htmlspecialchars((string)($check['name'] ?? '检查')) ?></b><span><?= !empty($check['ok'])?'通过':'异常' ?></span><em><?= htmlspecialchars((string)($check['message'] ?? '')) ?></em></div><?php endforeach; ?></div></section>
    <?php endif; ?>

    <?php if (!empty($queuedReports)): ?>
    <section class="card">
      <div class="uc-section-head"><div><h3>上报补发队列</h3><p>更新结果上报失败时会写入本地队列。</p></div><form method="post" action="/admin.php?path=update-center"><?= csrf_field() ?><input type="hidden" name="_action" value="retry_reports"><button class="btn btn-light">立即补发</button></form></div>
      <div class="table-responsive"><table class="table"><thead><tr><th>#</th><th>包ID</th><th>状态</th><th>事件</th><th>Full Key</th></tr></thead><tbody><?php foreach ($queuedReports as $idx => $item): ?><tr><td><?= (int)$idx+1 ?></td><td><?= htmlspecialchars((string)($item['package_id'] ?? '')) ?></td><td><?= htmlspecialchars((string)($item['status'] ?? '')) ?></td><td><?= htmlspecialchars((string)($item['event'] ?? '')) ?></td><td class="admin-break-small"><?= htmlspecialchars((string)($item['full_key'] ?? '')) ?></td></tr><?php endforeach; ?></tbody></table></div>
    </section>
    <?php endif; ?>
  </main>

  <aside class="uc-side">
    <section class="card">
      <h3>站点授权配置</h3>
      <p class="muted">只填写授权码和授权域名，其余信息从官方站自动同步或由系统自动识别，不允许手动修改。</p>
      <details class="config-box" <?= $ready?'':'open' ?>><summary><?= $ready?'查看/修改配置':'立即配置' ?></summary>
      <form method="post" action="/admin.php?path=update-center" class="config-form">
        <?= csrf_field() ?><input type="hidden" name="_action" value="save_config">
        <label>授权码<input class="input" name="license_key" value="<?= htmlspecialchars($config['license_key'] ?? '') ?>" placeholder="官方站授权码" required></label>
        <label>授权域名<input class="input" name="domain" value="<?= htmlspecialchars($config['domain'] ?? '') ?>" placeholder="例如 ovo.claybbs.com" required></label>
        <button class="btn" type="submit">从官方站同步授权</button>
      </form>
      <div class="readonly-config">
        <div><b>官方中心</b><span><?= htmlspecialchars($config['url'] ?? 'https://www.claybbs.com') ?></span></div>
        <div><b>站长</b><span><?= !empty($config['owner']) ? htmlspecialchars((string)$config['owner']) : '同步后自动获取' ?></span></div>
        <div><b>Site ID</b><span><?= $siteId !== '' ? '已同步' : '未同步' ?></span></div>
        <div><b>Token</b><span><?= $token !== '' ? '已同步' : '官方未返回，非绑定必需' ?></span></div>
        <div><b>当前版本</b><span><?= htmlspecialchars((string)($config['current_version'] ?? '0.0.0')) ?></span></div>
        <div><b>分支</b><span><?= htmlspecialchars((string)($config['branch'] ?? 'main')) ?></span></div>
        <div><b>官方公钥</b><span><?= !empty($config['public_key']) ? '已配置' : '未配置' ?></span></div>
      </div></details>
    </section>
  </aside>
</div>


<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
