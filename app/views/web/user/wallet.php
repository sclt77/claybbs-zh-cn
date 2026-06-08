<?php
$balances = $balances ?? [];
$transactions = $transactions ?? [];
$walletSummary = $walletSummary ?? [];
$currentCurrency = (string)($_GET['currency'] ?? '');
$currentType = (string)($_GET['type'] ?? '');
$assetAccounts = count($balances);
$holdingAccounts = 0;
$lockedAccounts = 0;
$selectedBalance = null;
foreach ($balances as $b) {
    if ((float)($b['balance'] ?? 0) > 0) $holdingAccounts++;
    if ((float)($b['locked_balance'] ?? 0) > 0) $lockedAccounts++;
    if ($currentCurrency !== '' && (string)($b['currency_code'] ?? '') === $currentCurrency) $selectedBalance = $b;
}
if (!$selectedBalance && !empty($balances)) $selectedBalance = $balances[0];
$selectedCode = (string)($selectedBalance['currency_code'] ?? '');
$selectedName = (string)($selectedBalance['name'] ?? $selectedCode ?: '资产');
$selectedPrecision = (int)($selectedBalance['precision'] ?? 2);
$selectedAmount = (float)($selectedBalance['balance'] ?? 0);
$selectedLocked = (float)($selectedBalance['locked_balance'] ?? 0);
$txCount = (int)($walletSummary['tx_count'] ?? count($transactions));
function wallet_url_with(array $merge = []): string {
    $params = ['path' => 'wallet'];
    $currency = (string)($_GET['currency'] ?? '');
    $type = (string)($_GET['type'] ?? '');
    if ($currency !== '') $params['currency'] = $currency;
    if ($type !== '') $params['type'] = $type;
    foreach ($merge as $k => $v) {
        if ($v === null || $v === '') unset($params[$k]); else $params[$k] = $v;
    }
    return '/index.php?' . http_build_query($params);
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>钱包</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.wallet-page{min-height:100vh;padding:0 0 108px;background:linear-gradient(180deg,#f2f7ff 0%,#f8fbff 42%,#f7f8fb 72%,#f7f8fb 100%)}.wallet-shell{max-width:1040px;margin:0 auto}.wallet-hero{position:relative;min-height:300px;padding:22px 18px 42px;overflow:hidden;background:radial-gradient(circle at 82% 34%,rgba(125,184,255,.28),transparent 34%),linear-gradient(135deg,#eaf4ff 0%,#f7fbff 45%,#fff9ed 100%)}.wallet-hero::before{content:'';position:absolute;right:7%;top:66px;width:230px;height:230px;border-radius:50%;background:linear-gradient(135deg,rgba(255,255,255,.62),rgba(255,255,255,.18));border:1px solid rgba(255,255,255,.72);box-shadow:inset 0 0 0 22px rgba(255,255,255,.18),0 26px 70px rgba(56,120,190,.13);z-index:0}.wallet-hero::after{content:'';position:absolute;right:calc(7% + 70px);top:136px;width:90px;height:90px;border-radius:30px;background:linear-gradient(135deg,#3b82f6,#60a5fa);opacity:.18;transform:rotate(18deg);z-index:0}.wallet-top{position:relative;z-index:1;display:grid;grid-template-columns:1fr;align-items:center}.wallet-back{justify-self:start;width:38px;height:38px;border-radius:999px;display:grid;place-items:center;text-decoration:none;color:#334155;background:rgba(255,255,255,.34);border:1px solid rgba(255,255,255,.42);font-size:28px;line-height:1}.wallet-title{text-align:center;margin:0;color:#1f2937;font-size:26px;font-weight:800;letter-spacing:-.03em}.wallet-actions{justify-self:end;display:flex;gap:8px}.wallet-actions a{height:34px;border-radius:999px;padding:0 12px;display:inline-flex;align-items:center;text-decoration:none;font-size:12px;font-weight:900;border:1px solid rgba(255,255,255,.52);background:rgba(255,255,255,.38);color:#475569}.wallet-balance{position:relative;z-index:1;margin-top:74px;display:flex;align-items:flex-end;gap:14px;min-width:0}.wallet-mark{width:50px;height:50px;border-radius:18px;display:grid;place-items:center;background:rgba(255,255,255,.66);border:1px solid rgba(226,232,240,.82);color:#2563eb;box-shadow:0 14px 30px rgba(37,99,235,.10);flex:0 0 auto}.wallet-mark svg{width:30px;height:30px}.wallet-balance-num{font-size:72px;font-weight:500;line-height:.9;color:#020617;letter-spacing:-.06em;max-width:100%;overflow:hidden;text-overflow:ellipsis}.wallet-balance-name{font-size:28px;font-weight:800;color:#111827;padding-bottom:8px;white-space:nowrap}.wallet-locked{position:relative;z-index:1;margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;color:#475569;font-size:13px;font-weight:850}.wallet-locked span,.wallet-recharge-chip{height:28px;display:inline-flex;align-items:center;border-radius:999px;padding:0 10px;background:rgba(255,255,255,.62);border:1px solid rgba(226,232,240,.78);box-shadow:0 8px 22px rgba(15,23,42,.035)}.wallet-recharge-chip{text-decoration:none;color:#2563eb;font-weight:950;background:#fff}.currency-strip{position:relative;z-index:1;margin-top:22px;display:flex;gap:9px;overflow-x:auto;padding-bottom:2px;scrollbar-width:none}.currency-strip::-webkit-scrollbar{display:none}.currency-chip{flex:0 0 auto;display:inline-flex;align-items:center;height:38px;border-radius:999px;padding:0 14px;background:rgba(255,255,255,.62);border:1px solid rgba(226,232,240,.82);color:#475569;text-decoration:none;font-size:13px;font-weight:950;box-shadow:0 8px 22px rgba(15,23,42,.035)}.currency-chip.active{background:#111827;border-color:#111827;color:#fff;box-shadow:0 10px 24px rgba(15,23,42,.14)}.wallet-panel{position:relative;margin:-26px 12px 0;background:rgba(255,255,255,.98);border:1px solid rgba(226,232,240,.9);border-radius:22px 22px 18px 18px;box-shadow:0 18px 42px rgba(15,23,42,.08);overflow:hidden}.panel-tabs{display:flex;gap:26px;align-items:center;padding:20px 22px 12px;border-bottom:1px solid #eef2f7}.panel-tab{position:relative;color:#111827;font-size:20px;font-weight:900}.panel-tab::after{content:'';position:absolute;left:50%;transform:translateX(-50%);bottom:-12px;width:42px;height:5px;border-radius:999px;background:#111827}.panel-meta{margin-left:auto;color:#94a3b8;font-size:12px;font-weight:800}.asset-compact{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1px;background:#eef2f7;border-bottom:1px solid #eef2f7}.asset-compact div{background:#fff;padding:14px 16px}.asset-compact span{display:block;color:#94a3b8;font-size:12px;font-weight:900}.asset-compact strong{display:block;margin-top:5px;color:#0f172a;font-size:18px;font-weight:950}.filter-zone{display:grid;gap:12px;padding:14px 20px;border-bottom:1px solid #eef2f7}.tx-filter{display:flex;gap:8px;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;-webkit-overflow-scrolling:touch}.tx-filter::-webkit-scrollbar{display:none}.wallet-filter-select{position:relative;flex:0 0 auto}.wallet-filter-select select{appearance:none;-webkit-appearance:none;height:38px!important;min-width:132px;border:1px solid #e2e8f0!important;border-radius:999px!important;background:#f8fafc!important;color:#334155!important;font-size:12px!important;font-weight:900!important;padding:0 34px 0 14px!important;box-shadow:none!important}.wallet-filter-select::after{content:'⌄';position:absolute;right:13px;top:50%;transform:translateY(-56%);color:#64748b;font-size:14px;pointer-events:none}.tx-filter .btn{flex:0 0 auto;height:38px!important;border-radius:999px!important;padding:0 14px!important;font-size:12px!important;font-weight:950!important;background:#e8eef6!important;border-color:#e8eef6!important;color:#334155!important}.quick-types{display:flex;gap:8px;flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;width:100%;padding:0 1px 2px;scrollbar-width:none;-webkit-overflow-scrolling:touch}.quick-types::-webkit-scrollbar{display:none}.quick-types a{flex:0 0 auto;border-radius:999px;padding:8px 14px;background:#f3f6fa;color:#64748b;text-decoration:none;font-size:12px;font-weight:950;border:1px solid #e2e8f0;text-align:center;white-space:nowrap}.quick-types a.active,.quick-types a:hover{background:#111827;border-color:#111827;color:#fff}.tx-list{display:grid}.tx-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center;padding:15px 20px;border-bottom:1px solid rgba(226,232,240,.72)}.tx-row:last-child{border-bottom:0}.tx-title{font-weight:950;color:#0f172a;line-height:1.45}.tx-badge{font-size:11px;border-radius:999px;padding:2px 7px;background:rgba(245,158,11,.10);color:#b45309;font-weight:950;margin-left:6px}.tx-meta{font-size:12px;color:#94a3b8;margin-top:5px;line-height:1.65}.tx-amount{font-weight:950;color:#16a34a;font-size:16px;white-space:nowrap}.tx-amount.negative{color:#ef4444}.empty{display:grid;place-items:center;gap:10px;color:#94a3b8;padding:70px 12px 50px;text-align:center}.empty svg{width:54px;height:54px;color:#d1d5db}.empty small{display:block;margin-top:26px;font-size:13px;color:#c0c4cc}.muted-dot{color:#cbd5e1;margin:0 4px}@media(max-width:760px){.wallet-page{padding-bottom:104px}.wallet-hero{min-height:312px;padding:18px 14px 44px}.wallet-hero::before{right:-56px;top:88px;width:220px;height:220px}.wallet-hero::after{right:18px;top:154px;width:76px;height:76px;border-radius:24px}.wallet-title{font-size:24px}.wallet-actions{display:none}.wallet-balance{margin-top:82px;gap:10px}.wallet-balance-num{font-size:68px}.wallet-balance-name{font-size:26px}.wallet-panel{margin:-24px 0 0;border-radius:20px 20px 0 0;border-left:0;border-right:0}.panel-tabs{padding:20px 22px 12px}.panel-tab{font-size:22px}.asset-compact{grid-template-columns:repeat(3,minmax(0,1fr))}.asset-compact div{padding:12px 10px}.asset-compact strong{font-size:16px}.filter-zone{padding:13px 18px}.tx-row{grid-template-columns:1fr;padding:14px 18px}.tx-amount{text-align:left}}@media(max-width:390px){.wallet-balance-num{font-size:58px}.wallet-balance-name{font-size:22px}.asset-compact{grid-template-columns:1fr 1fr}.asset-compact div:first-child{grid-column:1/-1}.panel-meta{display:none}.filter-zone{padding-left:16px;padding-right:16px}}html[data-theme="dark"] .wallet-page{background:linear-gradient(180deg,#0f172a 0%,#111827 48%,#0b1220 100%)!important}html[data-theme="dark"] .wallet-hero{background:radial-gradient(circle at 84% 34%,rgba(59,130,246,.20),transparent 34%),linear-gradient(135deg,#111827 0%,#172033 52%,#101827 100%)!important}html[data-theme="dark"] .wallet-hero::before{background:linear-gradient(135deg,rgba(30,41,59,.72),rgba(15,23,42,.30))!important;border-color:rgba(71,85,105,.38)!important;box-shadow:inset 0 0 0 22px rgba(148,163,184,.05),0 26px 70px rgba(0,0,0,.22)!important}html[data-theme="dark"] .wallet-hero::after{background:linear-gradient(135deg,#60a5fa,#2563eb)!important;opacity:.22!important}html[data-theme="dark"] .wallet-title,html[data-theme="dark"] .wallet-balance-num,html[data-theme="dark"] .wallet-balance-name{color:#f8fafc!important}html[data-theme="dark"] .wallet-mark{background:rgba(15,23,42,.72)!important;border-color:rgba(96,165,250,.22)!important;color:#93c5fd!important;box-shadow:0 14px 30px rgba(0,0,0,.18)!important}html[data-theme="dark"] .wallet-locked span,html[data-theme="dark"] .wallet-recharge-chip{background:rgba(15,23,42,.74)!important;border-color:rgba(71,85,105,.62)!important;color:#cbd5e1!important;box-shadow:none!important}html[data-theme="dark"] .wallet-recharge-chip{color:#93c5fd!important}html[data-theme="dark"] .wallet-actions a{background:rgba(15,23,42,.72)!important;border-color:rgba(71,85,105,.58)!important;color:#cbd5e1!important}html[data-theme="dark"] .currency-chip{background:rgba(15,23,42,.72)!important;border-color:rgba(71,85,105,.62)!important;color:#93c5fd!important;box-shadow:none!important}html[data-theme="dark"] .currency-chip.active{background:#e5e7eb!important;border-color:#e5e7eb!important;color:#111827!important;box-shadow:0 10px 24px rgba(0,0,0,.22)!important}html[data-theme="dark"] .wallet-panel,html[data-theme="dark"] .asset-compact div{background:#111827!important;border-color:#263244!important}html[data-theme="dark"] .wallet-panel{box-shadow:0 18px 42px rgba(0,0,0,.26)!important}html[data-theme="dark"] .asset-compact,html[data-theme="dark"] .panel-tabs,html[data-theme="dark"] .filter-zone{border-color:#263244!important;background:#1f2937!important}html[data-theme="dark"] .tx-row{border-color:#263244!important}html[data-theme="dark"] .panel-tab,html[data-theme="dark"] .tx-title,html[data-theme="dark"] .asset-compact strong{color:#e5e7eb!important}html[data-theme="dark"] .panel-meta,html[data-theme="dark"] .asset-compact span,html[data-theme="dark"] .tx-meta{color:#94a3b8!important}html[data-theme="dark"] .panel-tab::after{background:#e5e7eb!important}html[data-theme="dark"] .wallet-filter-select select{background:#f8fafc!important;color:#334155!important;border-color:#cbd5e1!important}html[data-theme="dark"] .tx-filter .btn{background:#0f172a!important;border-color:#263244!important;color:#cbd5e1!important}html[data-theme="dark"] .quick-types a{background:#0f172a!important;border-color:#263244!important;color:#93c5fd!important}html[data-theme="dark"] .quick-types a.active,html[data-theme="dark"] .quick-types a:hover{background:#e5e7eb!important;color:#111827!important;border-color:#e5e7eb!important}html[data-theme="dark"] .empty{color:#94a3b8!important}

/* dark wallet controls patch */
html[data-theme="dark"] .wallet-page .wallet-locked span,
html[data-theme="dark"] .wallet-page .wallet-recharge-chip,
html[data-theme="dark"] .wallet-page .currency-chip,
html[data-theme="dark"] .wallet-page .quick-types a,
html[data-theme="dark"] .wallet-page .tx-filter .btn{
  background:#0f172a!important;
  border:1px solid #334155!important;
  color:#bfdbfe!important;
  box-shadow:none!important;
}
html[data-theme="dark"] .wallet-page .wallet-recharge-chip:hover,
html[data-theme="dark"] .wallet-page .currency-chip:hover,
html[data-theme="dark"] .wallet-page .quick-types a:hover,
html[data-theme="dark"] .wallet-page .tx-filter .btn:hover{
  background:#172033!important;
  border-color:#3b82f6!important;
  color:#dbeafe!important;
}
html[data-theme="dark"] .wallet-page .currency-chip.active,
html[data-theme="dark"] .wallet-page .quick-types a.active{
  background:#2563eb!important;
  border-color:#60a5fa!important;
  color:#fff!important;
}
html[data-theme="dark"] .wallet-page .wallet-filter-select select{
  background:#0f172a!important;
  border:1px solid #334155!important;
  color:#e5e7eb!important;
  box-shadow:none!important;
}
html[data-theme="dark"] .wallet-page .wallet-filter-select::after{color:#93c5fd!important;}
html[data-theme="dark"] .wallet-page .filter-zone{background:#172033!important;}
html[data-theme="dark"] .wallet-page .asset-compact{background:#263244!important;}
html[data-theme="dark"] .wallet-page .asset-compact div{background:#0f172a!important;}
html[data-theme="dark"] .wallet-page .panel-tabs{background:#111827!important;}
html[data-theme="dark"] .theme-toggle-fab{background:#0f172a!important;border-color:#334155!important;color:#e5e7eb!important;}
</style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>
<main class="wallet-page">
  <div class="wallet-shell">
    <section class="wallet-hero">
      <div class="wallet-top">
        <div class="wallet-actions"><a href="/index.php?path=wallet/recharge&currency=<?= urlencode($selectedCode) ?>">充值</a><a href="/index.php?path=payment/orders">我的订单</a><a href="/index.php?path=messages&type=finance">财务消息</a></div>
      </div>
      <div class="wallet-balance">
        <div class="wallet-mark" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none"><rect x="8" y="12" width="32" height="24" rx="8" stroke="currentColor" stroke-width="4"/><path d="M32 24h8v8h-8a4 4 0 0 1 0-8Z" fill="currentColor" opacity=".18"/><path d="M16 18h15" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg></div>
        <div class="wallet-balance-num"><?= number_format($selectedAmount, $selectedPrecision) ?></div>
        <div class="wallet-balance-name"><?= htmlspecialchars($selectedName) ?></div>
      </div>
      <div class="wallet-locked"><span>冻结中 <?= number_format($selectedLocked, $selectedPrecision) ?> <?= htmlspecialchars($selectedName) ?></span><span><?= (int)$assetAccounts ?> 种资产</span><a class="wallet-recharge-chip" href="/index.php?path=wallet/recharge&currency=<?= urlencode($selectedCode) ?>">充值</a><a class="wallet-recharge-chip" href="/index.php?path=payment/orders">我的订单</a></div>
      <?php if (count($balances) > 1): ?>
      <div class="currency-strip" aria-label="资产切换">
        <?php foreach ($balances as $b): ?><?php $code=(string)$b['currency_code']; $active=$code===$selectedCode; ?>
          <a class="currency-chip <?= $active ? 'active' : '' ?>" href="<?= htmlspecialchars(wallet_url_with(['currency'=>$code])) ?>"><span><?= htmlspecialchars($b['name'] ?? $code) ?></span></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <section class="wallet-panel">
      <div class="panel-tabs"><div class="panel-tab">收支明细</div><div class="panel-meta">只显示最近 30 条</div></div>
      <div class="asset-compact"><div><span>当前资产</span><strong><?= htmlspecialchars($selectedName) ?></strong></div><div><span>持有资产</span><strong><?= (int)$holdingAccounts ?></strong></div><div><span>记录数量</span><strong><?= (int)$txCount ?></strong></div></div>
      <div class="filter-zone">
        <form class="tx-filter" method="get" action="/index.php">
          <input type="hidden" name="path" value="wallet">
          <label class="wallet-filter-select"><select name="currency"><option value="">全部资产</option><?php foreach ($balances as $b): ?><option value="<?= htmlspecialchars($b['currency_code']) ?>" <?= $currentCurrency===$b['currency_code']?'selected':'' ?>><?= htmlspecialchars($b['name'] ?? $b['currency_code']) ?></option><?php endforeach; ?></select></label>
          <label class="wallet-filter-select"><select name="type"><option value="">全部类型</option><option value="income" <?= $currentType==='income'?'selected':'' ?>>收入</option><option value="expense" <?= $currentType==='expense'?'selected':'' ?>>支出</option><option value="admin_adjust" <?= $currentType==='admin_adjust'?'selected':'' ?>>系统调整</option><option value="reversal" <?= $currentType==='reversal'?'selected':'' ?>>冲正</option><option value="frozen" <?= $currentType==='frozen'?'selected':'' ?>>冻结</option><option value="unfrozen" <?= $currentType==='unfrozen'?'selected':'' ?>>解冻</option><option value="reward" <?= $currentType==='reward'?'selected':'' ?>>奖励</option></select></label>
          <button class="btn btn-light">筛选</button><?php if ($currentCurrency!=='' || $currentType!==''): ?><a class="btn btn-light" style="text-decoration:none;" href="/index.php?path=wallet">清除</a><?php endif; ?>
        </form>
        <div class="quick-types"><a class="<?= $currentType===''?'active':'' ?>" href="<?= htmlspecialchars(wallet_url_with(['type'=>''])) ?>">全部</a><a class="<?= $currentType==='income'?'active':'' ?>" href="<?= htmlspecialchars(wallet_url_with(['type'=>'income'])) ?>">收入</a><a class="<?= $currentType==='expense'?'active':'' ?>" href="<?= htmlspecialchars(wallet_url_with(['type'=>'expense'])) ?>">支出</a><a class="<?= $currentType==='reversal'?'active':'' ?>" href="<?= htmlspecialchars(wallet_url_with(['type'=>'reversal'])) ?>">冲正</a><a class="<?= $currentType==='frozen'?'active':'' ?>" href="<?= htmlspecialchars(wallet_url_with(['type'=>'frozen'])) ?>">冻结</a><a class="<?= $currentType==='unfrozen'?'active':'' ?>" href="<?= htmlspecialchars(wallet_url_with(['type'=>'unfrozen'])) ?>">解冻</a><a class="<?= $currentType==='reward'?'active':'' ?>" href="<?= htmlspecialchars(wallet_url_with(['type'=>'reward'])) ?>">奖励</a></div>
      </div>
      <?php if (!empty($transactions)): ?><div class="tx-list"><?php foreach ($transactions as $tx): ?><?php $amount=(float)$tx['amount']; $precision=(int)($tx['precision'] ?? 2); $isReversal=($tx['type'] ?? '')==='reversal'; $isReversed=!empty($tx['reversed_by']); $currencyName=$tx['currency_name'] ?: currency_name_by_code((string)$tx['currency_code']); if (empty($tx['currency_exists'])) $currencyName .= '（已移除资产）'; ?><div class="tx-row"><div><div class="tx-title"><?= htmlspecialchars($tx['title'] ?: $tx['type']) ?><?php if ($isReversal): ?><span class="tx-badge">冲正</span><?php elseif ($isReversed): ?><span class="tx-badge">已冲正</span><?php endif; ?></div><div class="tx-meta"><?= htmlspecialchars($tx['created_at'] ?? '') ?><span class="muted-dot">·</span><?= htmlspecialchars($currencyName) ?><span class="muted-dot">·</span>余额 <?= htmlspecialchars((string)($tx['balance_after'] ?? '0')) ?><?php if (!empty($tx['remark'])): ?><span class="muted-dot">·</span><?= htmlspecialchars(currency_localize_text((string)$tx['remark'])) ?><?php endif; ?></div></div><div class="tx-amount<?= $amount < 0 ? ' negative' : '' ?>"><?= $amount >= 0 ? '+' : '' ?><?= number_format($amount, $precision) ?></div></div><?php endforeach; ?></div><?php else: ?><div class="empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5M9 12h6M9 16h5"/></svg><div>暂时没有数据</div><small>只显示最近 30 条</small></div><?php endif; ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?><?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>
</body></html>
