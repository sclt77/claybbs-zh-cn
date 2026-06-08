<?php
$pageTitle = '正版验证';
require dirname(__DIR__) . '/layouts/main.php';
$state = (string)($status['state'] ?? 'locked');
$reason = (string)($status['reason'] ?? '');
$payload = is_array($status['payload'] ?? null) ? $status['payload'] : [];
$lastVerified = !empty($status['last_verified_at']) ? date('Y-m-d H:i:s', (int)$status['last_verified_at']) : '暂无';
$remainingDays = max(0, (int)ceil(((int)($status['remaining_grace_seconds'] ?? 0)) / 86400));
$stateText = ['valid' => '验证正常', 'grace' => '离线宽限中', 'locked' => '后台已锁定'][$state] ?? $state;
$stateClass = $state === 'valid' ? 'ok' : ($state === 'grace' ? 'warn' : 'bad');
?>

<section class="license-page license-state-<?= htmlspecialchars($stateClass) ?>">
  <div class="license-hero-line">
    <div class="license-kicker">ClayBBS License</div>
    <div class="license-status-pill <?= htmlspecialchars($stateClass) ?>"><span></span><?= htmlspecialchars($stateText) ?></div>
  </div>

  <div class="license-heading">
    <h2>正版验证</h2>
    <p>论坛前台保持可访问；授权异常或离线超过 7 天时，仅限制后台进入，并要求管理员重新完成官方验证。</p>
  </div>

  <?php if (!empty($error)): ?><div class="license-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (!empty($success)): ?><div class="license-alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="license-layout">
    <section class="license-panel status-panel">
      <div class="panel-title-row">
        <div>
          <h3>当前授权状态</h3>
          <p>本机缓存 + 官方签名双重校验</p>
        </div>
        <div class="days-left">
          <strong><?= $state === 'locked' ? '0' : htmlspecialchars((string)$remainingDays) ?></strong>
          <span>天宽限</span>
        </div>
      </div>

      <dl class="license-facts">
        <div><dt>当前域名</dt><dd><?= htmlspecialchars($currentDomain) ?></dd></div>
        <div><dt>授权码</dt><dd><?= htmlspecialchars($maskedLicenseKey) ?></dd></div>
        <div><dt>绑定域名</dt><dd><?= htmlspecialchars((string)($payload['domain'] ?? '未验证')) ?></dd></div>
        <div><dt>最近在线验证</dt><dd><?= htmlspecialchars($lastVerified) ?></dd></div>
        <div class="wide"><dt>状态原因</dt><dd><?= htmlspecialchars($reason ?: '暂无异常') ?></dd></div>
      </dl>
    </section>

    <section class="license-panel verify-panel">
      <h3>重新在线验证</h3>
      <p class="panel-desc">输入正版授权码后，论坛会连接 ClayBBS 官方站，验证官方 RSA 签名授权状态，并刷新本地 7 天离线宽限。</p>
      <form method="post" class="license-form">
        <?= csrf_field() ?>
        <label>
          <span>正版授权码</span>
          <input class="license-input" name="license_key" value="<?= htmlspecialchars($savedLicenseKey) ?>" placeholder="LIC-XXXXXXXXXXXX" required>
        </label>
        <label>
          <span>当前授权域名</span>
          <input class="license-input" name="domain" value="<?= htmlspecialchars($currentDomain) ?>" placeholder="ovo.claybbs.com" required>
        </label>
        <div class="license-actions">
          <button class="license-primary" type="submit">立即验证</button>
        </div>
      </form>
    </section>
  </div>

</section>


<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
