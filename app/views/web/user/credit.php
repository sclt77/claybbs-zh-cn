<?php
$summary = $summary ?? [];
$logs = $logs ?? [];
$score = (int)($summary['score'] ?? 100);
$level = $summary['level'] ?? ['label'=>'正常','tone'=>'normal'];
$settings = $summary['settings'] ?? [];
$min = (int)($settings['min_score'] ?? 0);
$max = max($min + 1, (int)($settings['max_score'] ?? 120));
$percent = max(0, min(100, (int)round((($score - $min) / max(1, $max - $min)) * 100)));
$lowThreshold = (int)($settings['low_threshold'] ?? 60);
$excellentThreshold = (int)($settings['excellent_threshold'] ?? 100);
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>用户信用 - ClayBBS</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.credit-page{min-height:100vh;background:var(--bg-main,#f6f8fb);padding:18px 0 104px}.credit-shell{max-width:980px}.credit-hero{position:relative;overflow:hidden;border:1px solid rgba(226,232,240,.9);border-radius:24px;background:linear-gradient(135deg,rgba(255,255,255,.96),rgba(239,246,255,.92));box-shadow:0 18px 48px rgba(15,23,42,.08);padding:26px}.credit-hero:before{content:"";position:absolute;right:-80px;top:-90px;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(2,132,199,.22),transparent 68%)}.credit-head{position:relative;display:flex;align-items:flex-start;justify-content:space-between;gap:14px}.credit-title{margin:0;font-size:28px;letter-spacing:-.04em;color:var(--text-main,#0f172a)}.credit-sub{position:relative;margin:8px 0 0;color:var(--text-soft,#64748b);font-size:14px;line-height:1.7}.credit-rule-btn{position:relative;z-index:2;min-height:40px;border:1px solid rgba(2,132,199,.22);border-radius:999px;background:rgba(255,255,255,.82);color:#0284c7;box-shadow:0 10px 24px rgba(15,23,42,.08);padding:0 14px;font-size:13px;font-weight:950;cursor:pointer;display:inline-flex;align-items:center;gap:7px;touch-action:manipulation}.credit-rule-btn svg{width:16px;height:16px}.credit-rule-btn:hover{background:#0284c7;color:#fff}.credit-score-card{position:relative;margin-top:20px;display:grid;grid-template-columns:180px minmax(0,1fr);gap:20px;align-items:center}.credit-circle{width:168px;height:168px;border-radius:50%;display:grid;place-items:center;background:conic-gradient(var(--primary,#0284c7) <?= $percent ?>%, rgba(148,163,184,.18) 0);box-shadow:inset 8px 8px 18px rgba(15,23,42,.06),inset -8px -8px 18px rgba(255,255,255,.92)}.credit-circle-inner{width:126px;height:126px;border-radius:50%;background:rgba(255,255,255,.94);display:grid;place-items:center;text-align:center}.credit-score-num{font-size:42px;font-weight:950;letter-spacing:-.06em;color:var(--text-main,#0f172a);line-height:1}.credit-score-label{font-size:12px;color:var(--text-soft,#64748b);font-weight:900}.credit-level{display:inline-flex;margin-bottom:12px;border-radius:999px;padding:6px 11px;font-size:12px;font-weight:950;background:rgba(2,132,199,.10);color:#0284c7}.credit-level.ok{background:rgba(22,163,74,.12);color:#16a34a}.credit-level.danger{background:rgba(220,38,38,.10);color:#dc2626}.credit-grid{margin-top:16px;display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.credit-stat{border:1px solid rgba(226,232,240,.9);border-radius:16px;background:rgba(255,255,255,.68);padding:14px}.credit-stat strong{display:block;font-size:22px;color:var(--text-main,#0f172a)}.credit-stat span{font-size:12px;color:var(--text-soft,#64748b);font-weight:900}.credit-card{margin-top:16px;border:1px solid rgba(226,232,240,.9);border-radius:22px;background:var(--card-bg,#fff);box-shadow:0 10px 28px rgba(15,23,42,.045);padding:18px}.credit-card h2{margin:0 0 12px;font-size:17px;color:var(--text-main,#0f172a)}.credit-log{display:grid;gap:0}.credit-log-item{display:grid;grid-template-columns:88px minmax(0,1fr) 128px;gap:12px;align-items:start;padding:13px 0;border-bottom:1px solid var(--line-soft,#e2e8f0)}.credit-log-item:last-child{border-bottom:0}.credit-change{font-size:18px;font-weight:950}.credit-change.pos{color:#16a34a}.credit-change.neg{color:#dc2626}.credit-log-reason{font-size:14px;color:var(--text-main,#0f172a);font-weight:800}.credit-log-meta{font-size:12px;color:var(--text-muted,#94a3b8);margin-top:4px}.credit-time{font-size:12px;color:var(--text-muted,#94a3b8);text-align:right}.empty{text-align:center;padding:46px 12px;color:var(--text-muted,#94a3b8)}.credit-modal{position:fixed;inset:0;z-index:1400;display:none;align-items:center;justify-content:center;padding:18px}.credit-modal.is-open{display:flex}.credit-modal-mask{position:absolute;inset:0;background:rgba(15,23,42,.48);backdrop-filter:blur(8px)}.credit-modal-panel{position:relative;width:min(620px,100%);max-height:min(720px,88vh);overflow:auto;border-radius:24px;background:rgba(255,255,255,.98);box-shadow:0 28px 80px rgba(15,23,42,.28);border:1px solid rgba(226,232,240,.9);padding:22px}.credit-modal-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px}.credit-modal-title{margin:0;font-size:22px;letter-spacing:-.04em;color:#0f172a}.credit-modal-desc{margin:6px 0 0;color:#64748b;font-size:13px;line-height:1.7}.credit-close{width:38px;height:38px;border:0;border-radius:999px;background:#f1f5f9;color:#334155;font-size:22px;line-height:1;cursor:pointer}.credit-rule-list{display:grid;gap:10px}.credit-rule-item{border:1px solid #e2e8f0;border-radius:16px;background:#f8fafc;padding:13px 14px}.credit-rule-item strong{display:block;color:#0f172a;font-size:14px;margin-bottom:5px}.credit-rule-item p{margin:0;color:#64748b;font-size:13px;line-height:1.7}.credit-rule-note{margin-top:12px;border-radius:16px;background:rgba(2,132,199,.08);color:#075985;padding:12px 14px;font-size:13px;line-height:1.7}html[data-theme="dark"] .credit-hero,html[data-theme="dark"] .credit-card{background:#111827;border-color:#263244}html[data-theme="dark"] .credit-circle-inner,html[data-theme="dark"] .credit-stat{background:#0f172a;border-color:#263244}html[data-theme="dark"] .credit-rule-btn{background:rgba(15,23,42,.78);border-color:#263244;color:#7dd3fc}html[data-theme="dark"] .credit-modal-panel{background:#111827;border-color:#263244}html[data-theme="dark"] .credit-modal-title,html[data-theme="dark"] .credit-rule-item strong{color:#e5e7eb}html[data-theme="dark"] .credit-modal-desc,html[data-theme="dark"] .credit-rule-item p{color:#cbd5e1}html[data-theme="dark"] .credit-rule-item{background:#0f172a;border-color:#263244}html[data-theme="dark"] .credit-close{background:#1e293b;color:#e5e7eb}html[data-theme="dark"] .credit-rule-note{background:rgba(56,189,248,.10);color:#bae6fd}@media(max-width:768px){.credit-page{padding-top:0}.container.credit-shell{padding:0}.credit-hero,.credit-card{border-radius:0;border-left:0;border-right:0}.credit-head{align-items:flex-start}.credit-title{font-size:25px}.credit-rule-btn{min-height:38px;padding:0 12px;font-size:12px}.credit-score-card{grid-template-columns:1fr}.credit-circle{margin:auto}.credit-grid{grid-template-columns:repeat(2,1fr);padding:0}.credit-log-item{grid-template-columns:70px minmax(0,1fr);}.credit-time{grid-column:2;text-align:left}.credit-modal{align-items:flex-end;padding:0}.credit-modal-panel{width:100%;max-height:86vh;border-radius:24px 24px 0 0;padding:20px 18px calc(24px + env(safe-area-inset-bottom))}}@media(prefers-reduced-motion:no-preference){.credit-modal-panel{animation:creditPop .18s ease-out}@keyframes creditPop{from{opacity:.6;transform:translateY(10px) scale(.98)}to{opacity:1;transform:none}}}
</style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>
<main class="credit-page"><div class="container credit-shell">
  <section class="credit-hero">
    <div class="credit-head">
      <div>
        <h1 class="credit-title">用户信用分</h1>
        <p class="credit-sub">信用分用于呈现你在社区中的可信状态。保持友善交流、发布真实内容、合理使用举报，都有助于维持良好信用。</p>
      </div>
      <button class="credit-rule-btn" type="button" id="creditRuleOpen" aria-haspopup="dialog" aria-controls="creditRuleModal">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
        信用规则
      </button>
    </div>
    <div class="credit-score-card">
      <div class="credit-circle"><div class="credit-circle-inner"><div><div class="credit-score-num"><?= $score ?></div><div class="credit-score-label">当前信用</div></div></div></div>
      <div>
        <span class="credit-level <?= htmlspecialchars((string)($level['tone'] ?? 'normal')) ?>"><?= htmlspecialchars((string)($level['label'] ?? '正常')) ?></span>
        <div class="credit-sub">当前分值区间为 <?= $min ?> - <?= $max ?>。低于 <?= $lowThreshold ?> 分时会进入低信用状态，达到 <?= $excellentThreshold ?> 分可视为优秀信用。</div>
        <div class="credit-grid">
          <div class="credit-stat"><strong><?= (int)($summary['valid_reports'] ?? 0) ?></strong><span>有效协助</span></div>
          <div class="credit-stat"><strong><?= (int)($summary['invalid_reports'] ?? 0) ?></strong><span>未采纳举报</span></div>
          <div class="credit-stat"><strong><?= (int)($summary['violations'] ?? 0) ?></strong><span>违规记录</span></div>
          <div class="credit-stat"><strong><?= (int)($summary['manual_adjustments'] ?? 0) ?></strong><span>人工处理</span></div>
        </div>
      </div>
    </div>
  </section>

  <section class="credit-card"><h2>信用流水</h2><div class="credit-log">
    <?php if (!$logs): ?><div class="empty">暂无信用变动记录</div><?php endif; ?>
    <?php foreach ($logs as $log): $change=(int)($log['score_change'] ?? 0); ?><div class="credit-log-item">
      <div class="credit-change <?= $change >= 0 ? 'pos' : 'neg' ?>"><?= $change >= 0 ? '+' : '' ?><?= $change ?></div>
      <div><div class="credit-log-reason"><?= htmlspecialchars($log['reason'] ?? '') ?></div><div class="credit-log-meta"><?= htmlspecialchars($log['action'] ?? '') ?> · <?= (int)($log['before_score'] ?? 0) ?> → <?= (int)($log['after_score'] ?? 0) ?></div></div>
      <div class="credit-time"><?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)($log['created_at'] ?? 'now')))) ?></div>
    </div><?php endforeach; ?>
  </div></section>
</div></main>

<div class="credit-modal" id="creditRuleModal" role="dialog" aria-modal="true" aria-labelledby="creditRuleTitle" aria-hidden="true">
  <div class="credit-modal-mask" data-credit-rule-close></div>
  <div class="credit-modal-panel" tabindex="-1">
    <div class="credit-modal-head">
      <div><h2 class="credit-modal-title" id="creditRuleTitle">信用分规则</h2><p class="credit-modal-desc">下面是面向所有用户公开展示的信用说明。具体处理会结合内容事实、社区规则和管理员审核结果。</p></div>
      <button class="credit-close" type="button" data-credit-rule-close aria-label="关闭信用规则">×</button>
    </div>
    <div class="credit-rule-list">
      <div class="credit-rule-item"><strong>什么是信用分</strong><p>信用分用于反映账号近期在社区内的可信状态。信用良好通常意味着你能更顺畅地参与发帖、回复、私聊和互动。</p></div>
      <div class="credit-rule-item"><strong>怎样获得信用提升</strong><p>提交真实、清晰、对社区有帮助的举报并被核实有效后，信用分会增加 <?= (int)($settings['valid_report_reward'] ?? 2) ?> 分；为了避免刷分，每日最多奖励 <?= (int)($settings['daily_report_reward_limit'] ?? 10) ?> 分。</p></div>
      <div class="credit-rule-item"><strong>哪些情况会降低信用</strong><p>发布违规内容、骚扰他人、恶意私聊，或被举报后经核实确有问题，信用分会降低 <?= (int)($settings['valid_report_penalty'] ?? 5) ?> 分。反复提交无效或恶意举报也可能影响信用。</p></div>
      <?php if (!empty($settings['restrict_enabled'])): ?><div class="credit-rule-item"><strong>低信用会有什么影响</strong><p>当信用分低于 <?= $lowThreshold ?> 分时，部分操作会受到每日次数限制：发帖 <?= (int)($settings['low_daily_threads'] ?? 1) ?> 次、回复 <?= (int)($settings['low_daily_posts'] ?? 5) ?> 次、私聊 <?= (int)($settings['low_daily_private_messages'] ?? 10) ?> 条、朋友圈 <?= (int)($settings['low_daily_moments'] ?? 1) ?> 条<?= !empty($settings['low_disable_private_images']) ? '，并暂时不能发送私聊图片' : '' ?>。</p></div><?php endif; ?>
      <?php if (!empty($settings['recovery_enabled'])): ?><div class="credit-rule-item"><strong>信用可以恢复吗</strong><p>可以。保持正常使用、避免违规后，系统会按时间逐步恢复信用：每 <?= (int)($settings['recovery_interval_hours'] ?? 24) ?> 小时恢复 <?= (int)($settings['recovery_amount'] ?? 2) ?> 分，最高恢复到 <?= (int)($settings['recovery_cap'] ?? 100) ?> 分。</p></div><?php endif; ?>
      <div class="credit-rule-item"><strong>如果对处理结果有疑问</strong><p>可以通过站内消息或社区指定渠道联系管理员说明情况。管理员会根据记录、内容上下文和社区规则进行复核。</p></div>
    </div>
    <div class="credit-rule-note">建议：正常交流、尊重他人、只在确有问题时举报，是保持良好信用最稳定的方式。</div>
  </div>
</div>

<script>
(function(){
  var modal=document.getElementById('creditRuleModal');
  var open=document.getElementById('creditRuleOpen');
  if(!modal||!open) return;
  var panel=modal.querySelector('.credit-modal-panel');
  var lastFocus=null;
  function show(){lastFocus=document.activeElement;modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');document.documentElement.style.overflow='hidden';document.body.style.overflow='hidden';setTimeout(function(){panel&&panel.focus();},0)}
  function hide(){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');document.documentElement.style.overflow='';document.body.style.overflow='';if(lastFocus&&lastFocus.focus) lastFocus.focus()}
  open.addEventListener('click',show);
  modal.querySelectorAll('[data-credit-rule-close]').forEach(function(el){el.addEventListener('click',hide)});
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal.classList.contains('is-open')) hide()});
})();
</script>
<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?>
<?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>
</body></html>
