<?php
$__pageTitle = htmlspecialchars($software['name'] ?? '软件详情');
require dirname(__DIR__) . '/layouts/main.php';

$platformLabels = ['android' => 'Android', 'ios' => 'iOS', 'windows' => 'Windows', 'macos' => 'macOS'];
$hasDownloaded = $hasDownloaded ?? false;
$userRating = $userRating ?? null;
$typeMap = $typeMap ?? [];
?>

<style>
.soft-detail{padding:0 0 80px;background:var(--bg-main,#f5f5f5);min-height:100vh}
.soft-detail-topbar{position:sticky;top:0;z-index:110;display:flex;align-items:center;gap:10px;height:52px;padding:8px 12px;background:var(--card-bg,#fff);border-bottom:1px solid var(--line-soft,#eef2f7);box-shadow:0 8px 22px rgba(15,23,42,.04)}
.soft-back-btn{width:38px;height:38px;border-radius:12px;border:1px solid var(--line-soft,#e2e8f0);background:var(--bg-soft,#f8fafc);color:var(--text-main,#0f172a);display:inline-flex;align-items:center;justify-content:center;text-decoration:none;flex:none}
.soft-back-btn svg{width:22px;height:22px;display:block;stroke:currentColor;stroke-width:2.4;fill:none;stroke-linecap:round;stroke-linejoin:round}
.soft-detail-title{font-size:16px;font-weight:900;color:var(--text-main,#0f172a);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* ── 顶部信息卡 ── */
.soft-hero{background:var(--card-bg,#fff);padding:20px 16px 24px}
.soft-hero-top{display:flex;gap:16px;align-items:flex-start}
.soft-hero-logo{width:72px;height:72px;border-radius:20px;flex-shrink:0;overflow:hidden;background:linear-gradient(135deg,#f0f1f3,#e2e8f0);box-shadow:0 4px 16px rgba(0,0,0,.08)}
.soft-hero-logo img{width:100%;height:100%;object-fit:cover;border-radius:20px}
.soft-hero-logo .logo-placeholder{width:100%;height:100%;display:grid;place-items:center;color:#1f2937;background:linear-gradient(135deg,#eef2ff,#dbeafe)}
.soft-hero-logo .logo-placeholder svg{width:38px;height:38px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.soft-hero-info{flex:1;min-width:0}
.soft-hero-info h1{font-size:20px;font-weight:900;margin:0 0 4px;color:var(--text-main,#0f172a);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.soft-hero-info .dev{font-size:13px;color:var(--text-muted,#94a3b8);margin-bottom:6px}
.soft-hero-info .tags{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:8px}
.soft-hero-info .meta-row{display:flex;align-items:center;gap:12px;font-size:13px;color:var(--text-soft,#64748b)}
.soft-hero-info .meta-row .stars{color:#f59e0b}
.soft-hero-actions{display:flex;gap:10px;margin-top:14px}
.btn-dl{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 0;border-radius:99px;font-size:15px;font-weight:800;border:none;cursor:pointer;text-decoration:none;transition:opacity .15s}
.btn-dl:active{opacity:.85}
.btn-dl.primary{background:#3cc9a4;color:#fff;flex:1}
.btn-dl.primary.downloaded{background:#10b981}
.btn-dl.secondary{background:var(--bg-soft,#f5f5f5);color:var(--text-soft,#64748b);padding:10px 18px;font-size:13px}
.btn-dl svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2.3;stroke-linecap:round;stroke-linejoin:round}

/* ── 信息栏 ── */
.soft-info-bar{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:12px;padding:0}
.info-card{background:var(--card-bg,#fff);border-radius:14px;padding:14px;text-align:center}
.info-card .label{font-size:11px;color:var(--text-muted,#94a3b8);margin-bottom:4px}
.info-card .value{font-size:14px;font-weight:800;color:var(--text-main,#0f172a)}

/* ── 截图横滑 ── */
.soft-screens{padding:0 12px;margin-bottom:12px}
.soft-screens .title{font-size:16px;font-weight:800;margin-bottom:10px;color:var(--text-main,#0f172a);padding-left:4px}
.screen-scroll{display:flex;gap:10px;overflow-x:auto;padding-bottom:6px;scrollbar-width:none}
.screen-scroll::-webkit-scrollbar{display:none}
.screen-scroll img{height:200px;border-radius:12px;flex-shrink:0;object-fit:cover;background:var(--bg-soft,#f0f1f3)}

/* ── 详情区 ── */
.soft-section{background:var(--card-bg,#fff);border-radius:14px;margin:0 12px 12px;padding:16px}
.soft-section .title{font-size:16px;font-weight:800;margin-bottom:12px;color:var(--text-main,#0f172a)}
.soft-section .desc{font-size:14px;line-height:1.8;color:var(--text-soft,#64748b);white-space:pre-wrap;word-break:break-word}.soft-tabs{display:flex;gap:8px;margin:0 12px 12px;padding:6px;background:var(--card-bg,#fff);border-radius:14px}.soft-tab{flex:1;border:0;border-radius:10px;background:transparent;color:var(--text-soft,#64748b);font-weight:900;padding:10px;cursor:pointer}.soft-tab.active{background:#0f172a;color:#fff}.soft-tab-panel{display:none}.soft-tab-panel.active{display:block}.version-history{display:grid;gap:10px}.version-history-item{padding:13px;border-radius:14px;background:var(--bg-soft,#f8fafc)}.version-history-head{display:flex;justify-content:space-between;gap:10px;font-weight:950;color:var(--text-main,#0f172a)}.version-history-date{font-size:12px;color:var(--text-muted,#94a3b8);font-weight:700}.version-history-log{margin-top:8px;white-space:pre-wrap;line-height:1.7;color:var(--text-soft,#64748b);font-size:14px}

/* ── 评分 ── */
.rating-row{display:flex;align-items:center;gap:16px;margin-bottom:14px}
.rating-big{font-size:42px;font-weight:900;color:var(--text-main,#0f172a);line-height:1}
.rating-right .rating-stars{color:#f59e0b;font-size:20px;margin-bottom:2px}
.rating-right .rating-count{font-size:12px;color:var(--text-muted,#94a3b8)}
.rate-form{display:flex;gap:4px;margin-bottom:12px}
.rate-form .star-btn{background:none;border:none;font-size:28px;color:#e2e8f0;cursor:pointer;transition:color .1s}
.rate-form .star-btn.active,.rate-form .star-btn:hover{color:#f59e0b}

/* ── 评论 ── */
.review-item{padding:14px 0;border-bottom:1px solid var(--line-soft,#f0f0f0)}
.review-item:last-child{border-bottom:none}
.review-header{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.review-header img{width:36px;height:36px;border-radius:50%;object-fit:cover}
.review-header .name{font-weight:700;font-size:14px;color:var(--text-main,#0f172a)}
.review-header .time{font-size:11px;color:var(--text-muted,#94a3b8)}
.review-content{font-size:14px;line-height:1.7;color:var(--text-soft,#64748b)}
.review-reply{margin:8px 0 0 40px;padding:10px 12px;background:var(--bg-soft,#f8fafc);border-radius:10px;font-size:13px;color:var(--text-soft,#64748b)}
.review-reply strong{color:var(--text-main,#0f172a)}
.review-input{display:flex;gap:8px;margin-top:12px}
.review-input textarea{flex:1;padding:10px 14px;border:1px solid var(--line-soft,#e2e8f0);border-radius:12px;font-size:14px;min-height:40px;resize:none;background:var(--card-bg,#fff);color:var(--text-main,#0f172a)}
.review-input button{background:#3cc9a4;color:#fff;border:none;padding:0 18px;border-radius:99px;font-weight:700;font-size:13px;cursor:pointer;white-space:nowrap}

/* ── 上传者 ── */
.uploader-row{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg-soft,#f8fafc);border-radius:12px;margin-bottom:14px;width:fit-content}
.uploader-row img{width:32px;height:32px;border-radius:50%;object-fit:cover}
.uploader-row a{font-size:14px;font-weight:700;color:#3cc9a4;text-decoration:none}

/* ── 标签 ── */
.app-tag{padding:2px 8px;border-radius:6px;color:#fff;font-size:11px;font-weight:700;line-height:1.4}
.app-tag.t-original{background:#19be6b}
.app-tag.t-gold{background:#ff6600}
.app-tag.t-official{background:#2979ff}
.app-tag.t-repost{background:#7c72ff}
.app-category-tag{padding:2px 8px;border-radius:6px;background:var(--bg-soft,#f5f5f5);color:var(--text-muted,#666);font-size:11px;line-height:1.4}

/* ── 桌面端：应用详情页目录化布局 ── */
@media (min-width: 900px){
  .soft-detail{padding:26px clamp(22px,4vw,56px) 96px;background:linear-gradient(180deg,var(--bg-main,#f8fafc),var(--bg-main,#f4f6f8));display:grid;grid-template-columns:minmax(0,780px) 320px;gap:22px;align-items:start;max-width:1220px;margin:0 auto;box-sizing:content-box}
  .soft-detail-topbar{grid-column:1/-1;position:sticky;top:62px;z-index:120;height:auto;min-height:46px;padding:0;background:transparent;border-bottom:0;box-shadow:none;display:flex;align-items:center;gap:12px}
  .soft-back-btn{width:auto;height:38px;padding:0 13px;border-radius:12px;background:var(--card-bg,#fff);box-shadow:0 0 0 1px rgba(15,23,42,.08),0 8px 22px rgba(15,23,42,.04);font-size:13px;font-weight:850;gap:4px}.soft-back-btn::after{content:'返回';white-space:nowrap}.soft-back-btn svg{width:18px;height:18px}.soft-detail-title{font-size:14px;color:var(--text-soft,#64748b);font-weight:800}
  .soft-hero{grid-column:1/2;padding:34px;border-radius:24px;background:var(--card-bg,#fff);box-shadow:0 0 0 1px rgba(15,23,42,.08),0 18px 48px rgba(15,23,42,.07);overflow:hidden;position:relative;display:grid;grid-template-columns:minmax(0,1fr) 220px;gap:22px;align-items:center}.soft-hero::before{content:'';position:absolute;right:-90px;top:-120px;width:320px;height:320px;border-radius:999px;background:radial-gradient(circle,rgba(60,201,164,.16),transparent 68%);pointer-events:none}.soft-hero>*{position:relative;z-index:1}.soft-hero-top{grid-column:1/2;gap:22px;align-items:center}.soft-hero-logo{width:112px;height:112px;border-radius:28px;box-shadow:0 18px 42px rgba(15,23,42,.12),0 0 0 1px rgba(15,23,42,.07)}.soft-hero-logo img{border-radius:28px}.soft-hero-logo .logo-placeholder svg{width:54px;height:54px}.soft-hero-info h1{font-size:42px;line-height:1.08;letter-spacing:-1.8px;margin:0 0 10px;white-space:normal}.soft-hero-info .dev{font-size:15px;margin-bottom:14px}.soft-hero-info .tags{gap:8px;margin-bottom:14px}.soft-hero-info .meta-row{font-size:14px;gap:14px}.soft-hero-actions{grid-column:2/3;grid-row:1/span 2;margin-top:0;display:grid;gap:12px;align-self:center;background:rgba(248,250,252,.86);border-radius:20px;padding:16px;box-shadow:inset 0 0 0 1px rgba(15,23,42,.06)}.btn-dl{height:46px;padding:0 18px;border-radius:12px}.btn-dl.primary{background:#0f172a;box-shadow:0 14px 30px rgba(15,23,42,.16)}.btn-dl.secondary{background:var(--card-bg,#fff);padding:0 18px;font-size:14px}
  .uploader-row{margin:22px 0 0;width:auto;display:inline-flex;background:var(--bg-soft,#f8fafc)}
  .soft-info-bar{grid-column:2/3;grid-row:2/3;position:sticky;top:62px;display:grid;grid-template-columns:1fr;gap:10px;margin:0}.info-card{border-radius:18px;text-align:left;padding:16px 18px;box-shadow:0 0 0 1px rgba(15,23,42,.08);background:var(--card-bg,#fff)}.info-card .label{font-size:12px;font-weight:850}.info-card .value{font-size:18px;margin-top:2px}
  .soft-screens,.soft-tabs,.soft-tab-panel,.soft-section{grid-column:1/2}.soft-screens{padding:0;margin:0}.soft-screens .title,.soft-section .title{font-size:18px;font-weight:950;margin-bottom:14px}.screen-scroll{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;overflow:visible;padding-bottom:0}.screen-scroll img{width:100%;height:260px;border-radius:18px;box-shadow:0 0 0 1px rgba(15,23,42,.08)}.soft-section{margin:0;padding:24px;border-radius:22px;box-shadow:0 0 0 1px rgba(15,23,42,.08),0 12px 32px rgba(15,23,42,.045)}.soft-section .desc{font-size:16px;line-height:1.9}.rating-row{padding:14px 16px;border-radius:18px;background:var(--bg-soft,#f8fafc)}.rating-big{font-size:52px}.review-input textarea{min-height:48px}.review-input button{border-radius:12px;padding:0 22px}
}
@media (min-width: 900px) and (max-width: 1080px){.soft-detail{grid-template-columns:minmax(0,1fr) 280px;gap:16px}.soft-hero-info h1{font-size:34px}.soft-hero-logo{width:96px;height:96px}.soft-hero{padding:26px}}

/* ── 暗色模式 ── */
html[data-theme="dark"] .soft-hero{background:#1e293b}
html[data-theme="dark"] .soft-hero-info h1{color:#e5e7eb}
html[data-theme="dark"] .info-card{background:#1e293b}
html[data-theme="dark"] .info-card .value{color:#e5e7eb}
html[data-theme="dark"] .soft-section,html[data-theme="dark"] .soft-tabs{background:#1e293b}
html[data-theme="dark"] .soft-section .title{color:#e5e7eb}
html[data-theme="dark"] .review-header .name{color:#e5e7eb}
html[data-theme="dark"] .review-reply{background:#0f172a}
html[data-theme="dark"] .app-category-tag{background:#0f172a;color:#94a3b8}
html[data-theme="dark"] .btn-dl.secondary{background:#0f172a;color:#94a3b8}
html[data-theme="dark"] .soft-detail{background:#0f172a}
html[data-theme="dark"] .soft-detail-topbar{background:#111827;border-color:#263244}
html[data-theme="dark"] .soft-back-btn{background:#0f172a;border-color:#334155;color:#e5e7eb}
html[data-theme="dark"] .soft-detail-title{color:#e5e7eb}
html[data-theme="dark"] .review-input textarea{background:#1e293b;border-color:#334155;color:#e5e7eb}
html[data-theme="dark"] .uploader-row{background:#0f172a}
html[data-theme="dark"] .rating-big,html[data-theme="dark"] .version-history-head{color:#e5e7eb}html[data-theme="dark"] .version-history-item{background:#0f172a}
</style>

<div class="soft-detail">
  <div class="soft-detail-topbar">
    <button type="button" class="soft-back-btn" onclick="goBack(event)" aria-label="返回">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/><path d="M10 12h10"/></svg>
    </button>
    <div class="soft-detail-title"><?= htmlspecialchars($software['name']) ?></div>
  </div>
  
  <div class="soft-hero">
    <div class="soft-hero-top">
      <div class="soft-hero-logo">
        <?php if (!empty($software['icon'])): ?>
        <img src="<?= htmlspecialchars($software['icon']) ?>" alt="">
        <?php else: ?>
        <div class="logo-placeholder" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="7" y="2.5" width="10" height="19" rx="2.4"/><path d="M10 18h4"/><path d="M9 5.5h6"/></svg></div>
        <?php endif; ?>
      </div>
      <div class="soft-hero-info">
        <h1><?= htmlspecialchars($software['name']) ?></h1>
        <div class="dev"><?= htmlspecialchars($software['developer'] ?: '未知开发者') ?></div>
        <div class="tags">
          <?php $appType = $software['type'] ?? ''; if ($appType !== '' && isset($typeMap[$appType])): ?>
          <span class="app-tag" style="background:<?= htmlspecialchars((string)$typeMap[$appType]['color']) ?>"><?= htmlspecialchars((string)$typeMap[$appType]['name']) ?></span>
          <?php endif; ?>
          <?php if (!empty($software['category_name'])): ?>
          <span class="app-category-tag"><?= htmlspecialchars($software['category_name']) ?></span>
          <?php endif; ?>
        </div>
        <div class="meta-row">
          <span class="stars"><?= str_repeat('★', (int)round((float)$software['rating_avg'])) . str_repeat('☆', 5 - (int)round((float)$software['rating_avg'])) ?></span>
          <span><?= number_format((float)$software['rating_avg'], 1) ?></span>
          <span><?= number_format((int)$software['download_count']) ?> 次下载</span>
        </div>
      </div>
    </div>
    <?php if ($uploader): ?>
    <div class="uploader-row">
      <img src="<?= htmlspecialchars($uploader['avatar'] ?: '/assets/img/default-avatar.png') ?>" alt="">
      <a href="/index.php?path=user/profile&id=<?= (int)$uploader['id'] ?>"><?= htmlspecialchars($uploader['nickname'] ?: $uploader['username']) ?></a>
    </div>
    <?php endif; ?>
    <div class="soft-hero-actions">
      <a href="/index.php?path=software/download&id=<?= (int)$software['id'] ?>" class="btn-dl primary">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/></svg> 下载
      </a>
      <button class="btn-dl secondary" onclick="shareApp()">分享</button>
    </div>
  </div>

  
  <div class="soft-info-bar">
    <div class="info-card">
      <div class="label">版本</div>
      <div class="value"><?= htmlspecialchars($software['version']) ?></div>
    </div>
    <div class="info-card">
      <div class="label">大小</div>
      <div class="value"><?= htmlspecialchars($software['size'] ?: '未知') ?></div>
    </div>
    <div class="info-card">
      <div class="label">平台</div>
      <div class="value"><?= $platformLabels[$software['platform']] ?? $software['platform'] ?></div>
    </div>
  </div>

  
  <?php if (!empty($screenshots)): ?>
  <div class="soft-screens">
    <div class="title">应用截图</div>
    <div class="screen-scroll">
      <?php foreach ($screenshots as $ss): ?>
      <img src="<?= htmlspecialchars($ss['image_path']) ?>" alt="截图" loading="lazy">
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="soft-tabs" role="tablist" aria-label="软件详情切换">
    <button type="button" class="soft-tab active" data-soft-tab="intro">介绍</button>
    <button type="button" class="soft-tab" data-soft-tab="versions">更新日志</button>
    <button type="button" class="soft-tab" data-soft-tab="reviews">评论</button>
  </div>

  <div class="soft-tab-panel active" id="soft-tab-intro">
    <?php if (!empty($software['detail']) || !empty($software['description'])): ?>
    <div class="soft-section">
      <div class="title">应用介绍</div>
      <div class="desc"><?= nl2br(htmlspecialchars($software['detail'] ?: $software['description'])) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <div class="soft-tab-panel" id="soft-tab-versions">
    <div class="soft-section">
      <div class="title">更新日志</div>
      <?php if (empty($versions)): ?>
      <div class="desc">暂无更新日志</div>
      <?php else: ?>
      <div class="version-history">
        <?php foreach ($versions as $v): ?>
        <div class="version-history-item">
          <div class="version-history-head"><span>v<?= htmlspecialchars((string)$v['version']) ?></span><span class="version-history-date"><?= htmlspecialchars(date('Y-m-d', strtotime((string)($v['published_at'] ?: $v['created_at'])))) ?></span></div>
          <div class="version-history-log"><?= nl2br(htmlspecialchars((string)($v['changelog'] ?: '版本更新'))) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="soft-tab-panel" id="soft-tab-reviews">
    <div class="soft-section">
      <div class="title">评分与评论</div>
      <div class="rating-row">
        <div class="rating-big"><?= number_format((float)$software['rating_avg'], 1) ?></div>
        <div class="rating-right">
          <div class="rating-stars"><?= str_repeat('★', (int)round((float)$software['rating_avg'])) . str_repeat('☆', 5 - (int)round((float)$software['rating_avg'])) ?></div>
          <div class="rating-count"><?= (int)$software['rating_count'] ?> 个评分</div>
        </div>
      </div>
      <?php if (!empty($_SESSION['auth_user']['id'])): ?>
      <div class="rate-form">
        <?php for ($i = 1; $i <= 5; $i++): ?>
        <button type="button" class="star-btn<?= $userRating && (int)$userRating['rating'] >= $i ? ' active' : '' ?>" onclick="rate(<?= $i ?>)">★</button>
        <?php endfor; ?>
      </div>
      <div class="review-input">
        <textarea id="reviewContent" placeholder="写下你的评论..." rows="1"></textarea>
        <button onclick="submitReview()">发送</button>
      </div>
      <?php endif; ?>

      <?php foreach ($reviews as $r): ?>
      <div class="review-item">
        <div class="review-header">
          <img src="<?= htmlspecialchars($r['avatar'] ?: '/assets/img/default-avatar.png') ?>" alt="">
          <div>
            <div class="name"><?= htmlspecialchars($r['nickname'] ?: '用户') ?></div>
            <div class="time"><?= date('Y-m-d H:i', strtotime($r['created_at'])) ?></div>
          </div>
        </div>
        <div class="review-content"><?= nl2br(htmlspecialchars($r['content'])) ?></div>
        <?php if (!empty($r['replies'])): ?>
        <?php foreach ($r['replies'] as $reply): ?>
        <div class="review-reply">
          <strong><?= htmlspecialchars($reply['nickname'] ?: '用户') ?>：</strong><?= htmlspecialchars($reply['content']) ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>


<script>
(function(){var tabs=[].slice.call(document.querySelectorAll('.soft-tab'));var panels={intro:document.getElementById('soft-tab-intro'),versions:document.getElementById('soft-tab-versions'),reviews:document.getElementById('soft-tab-reviews')};tabs.forEach(function(t){t.addEventListener('click',function(){var k=t.dataset.softTab;tabs.forEach(function(x){x.classList.toggle('active',x===t);});Object.keys(panels).forEach(function(p){if(panels[p])panels[p].classList.toggle('active',p===k);});});});})();
function goBack(event){
  if(event && event.preventDefault) event.preventDefault();
  if(window.history.length > 1){
    window.history.back();
  }else{
    window.location.href = '/';
  }
}
function rate(star){
  fetch('/index.php?path=software/rate',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id=<?= (int)$software["id"] ?>&rating='+star})
  .then(r=>r.json()).then(d=>{if(d.ok)location.reload();else alert(d.error)});
}
function submitReview(){
  var c=document.getElementById('reviewContent').value.trim();if(!c)return;
  fetch('/index.php?path=software/review',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id=<?= (int)$software["id"] ?>&content='+encodeURIComponent(c)})
  .then(r=>r.json()).then(d=>{if(d.ok)location.reload();else alert(d.error)});
}
function shareApp(){
  if(navigator.share){navigator.share({title:'<?= htmlspecialchars($software["name"]) ?>',url:location.href});}
  else{navigator.clipboard.writeText(location.href);alert('链接已复制');}
}
</script>

<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>