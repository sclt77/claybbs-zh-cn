<?php


$decoGuideType = $decoGuideType ?? 'general';
$decoGuideTitle = $decoGuideTitle ?? '装饰配置教程';
$decoGuidePanelClass = $decoGuidePanelClass ?? 'card';
$decoGuidePanelAttr = $decoGuidePanelAttr ?? 'data-panel';
$typeNames = [
    'avatar_frame' => '头像框',
    'badge' => '勋章',
    'bubble' => '聊天气泡',
    'nameplate' => '名字特效',
    'general' => '装饰',
];
$typeName = $typeNames[$decoGuideType] ?? '装饰';
$baseSteps = [
    ['num' => '01', 'title' => '先准备资源', 'desc' => '确认图片、SVG 或 CSS 代码已经整理好，命名尽量使用英文短横线，便于后续维护。'],
    ['num' => '02', 'title' => '填写基础信息', 'desc' => '名称给用户看，编码给系统识别；编码保存后建议不要频繁修改，避免旧数据关联失效。'],
    ['num' => '03', 'title' => '设置获取方式', 'desc' => '免费、商城、任务、等级、管理员授予对应不同入口；不想公开领取时请选择管理员授予。'],
    ['num' => '04', 'title' => '前台预览验证', 'desc' => '保存后到装饰中心或聊天窗口预览，检查尺寸、颜色、动效和深色模式显示。'],
];
$examples = [
    'avatar_frame' => [
        'accent' => '#0ea5e9',
        'soft' => '#e0f2fe',
        'icon' => '◎',
        'kicker' => 'FRAME GUIDE',
        'summary' => '头像框重点是“中心透明、外圈装饰、尺寸稳定”。建议使用 SVG 或透明 PNG，让用户头像始终处于视觉中心。',
        'resource_title' => '头像框资源规范',
        'resource_lines' => [
            '图片字段 image 填 /assets/img/avatar-frames/xxx.svg 或上传图片路径。',
            '推荐 SVG viewBox="0 0 200 200"，中心保持透明，外圈做装饰。',
            '外圈不要压住头像主体，移动端建议保留 10% 以上安全边距。',
            '品质下拉会同步 quality_name / quality_color，前台筛选按 quality 显示。',
        ],
        'fields' => ['code：唯一标识', 'image：图片路径', 'quality：品质代码', 'obtain_method：获取方式'],
        'tips' => ['管理员授予适合限定头像框', '商城购买必须选择货币和价格', '排序越小越靠前'],
        'code' => '<svg viewBox="0 0 200 200"><defs><linearGradient id="g"><stop stop-color="#38bdf8"/><stop offset="1" stop-color="#a78bfa"/></linearGradient></defs><circle cx="100" cy="100" r="86" fill="none" stroke="url(#g)" stroke-width="6"/><animateTransform attributeName="transform" type="rotate" from="0 100 100" to="360 100 100" dur="12s" repeatCount="indefinite"/></svg>',
    ],
    'badge' => [
        'accent' => '#f59e0b',
        'soft' => '#fef3c7',
        'icon' => '★',
        'kicker' => 'BADGE GUIDE',
        'summary' => '勋章要保证小尺寸也清晰。图标主体居中、轮廓明确，颜色与品质形成统一视觉层级。',
        'resource_title' => '勋章资源规范',
        'resource_lines' => [
            '图标字段 icon 填图标地址或上传后的图片路径，前台用 img 标签展示。',
            '推荐 SVG viewBox="0 0 120 120"，主体居中，不要贴边。',
            '品质 level 必须对应已配置的品质代码，例如 legend / epic / rare / standard。',
            '不希望用户自行获取的勋章，建议选择“管理员授予”。',
        ],
        'fields' => ['code：唯一标识', 'icon：图标路径', 'level：品质代码', 'category：分类'],
        'tips' => ['图标小尺寸要可辨认', '自动达成建议先少量测试', '已有用户获得时删除会受限制'],
        'code' => '<svg viewBox="0 0 120 120"><path d="M60 20 L90 40 V68 Q90 92 60 104 Q30 92 30 68 V40 Z" fill="#f59e0b"/><circle cx="60" cy="60" r="10"><animate attributeName="r" values="8;13;8" dur="1.6s" repeatCount="indefinite"/></circle></svg>',
    ],
    'bubble' => [
        'accent' => '#ec4899',
        'soft' => '#fce7f3',
        'icon' => '✦',
        'kicker' => 'BUBBLE GUIDE',
        'summary' => '气泡支持 Anime.js 粒子特效和自定义 CSS。配置时先保证聊天内容可读，再叠加粒子、光晕和背景。',
        'resource_title' => '气泡特效规范',
        'resource_lines' => [
            '可以直接粘贴 CSS，系统会从 .chat-msg.xxx 自动识别样式名并实时预览。',
            '也支持 JSON：type 字段决定样式名；预设特效支持 color/count/speed/size 参数。',
            '自定义 CSS 推荐写 .chat-msg.你的样式名，例如 .chat-msg.sakura。',
            '保存纯 CSS 时系统会自动包装成 JSON，前台聊天和装饰中心都会正常使用。',
        ],
        'fields' => ['effect_type：特效类型', 'effect_params：参数/CSS', 'quality：品质代码', 'sort_order：排序'],
        'tips' => ['背景再华丽也要保证文字清晰', '粒子数量过多会影响低端机', '深色/浅色主题都要试'],
        'code' => '// Anime.js 参数示例\n{"type":"sakura","color":"#fb7185","count":16,"speed":0.8,"size":4}\n\n// 自定义 CSS 示例\n.chat-msg.sakura{background:linear-gradient(135deg,#ff9a9e,#fad0c4,#ffecd2);border-radius:20px 24px 20px 8px;color:#831843;border:2px solid rgba(255,154,158,.4);box-shadow:0 4px 16px rgba(255,154,158,.3)}',
    ],
    'nameplate' => [
        'accent' => '#8b5cf6',
        'soft' => '#ede9fe',
        'icon' => 'A',
        'kicker' => 'NAME FX GUIDE',
        'summary' => '名字特效会出现在全站昵称位置。建议优先控制文字可读性，再使用渐变、描边、阴影和动画。',
        'resource_title' => '名字特效规范',
        'resource_lines' => [
            '可以像气泡一样直接粘贴 CSS，系统会从 .np-fx.xxx 或 .np-fx--xxx 自动识别样式名。',
            '昵称结构为 .np-fx.样式名.np-fx--样式名，文字层为 .np-fx-text。',
            '也支持 JSON：{"type":"样式名","css":"你的 CSS"}，保存后前台会自动注入。',
            '主色/强调色/文字色会注入为 CSS 变量，可在 CSS 中直接使用。',
        ],
        'fields' => ['style_key：样式名', 'frame_color：主色', 'accent_color：强调色', 'custom_css：自定义 CSS'],
        'tips' => ['不要让阴影盖住正文', '动画建议 2 秒以上循环', '用户名较长时也要检查换行'],
        'code' => '// 直接粘贴 CSS 示例\n.np-fx.aqua-name .np-fx-text{font-family:"KaiTi","STKaiti",serif;background:linear-gradient(90deg,var(--np-frame),var(--np-accent),var(--np-text));background-size:220% 100%;-webkit-background-clip:text;-webkit-text-fill-color:transparent;text-shadow:0 0 12px var(--np-accent);animation:npAquaName 3s linear infinite}\n@keyframes npAquaName{0%{background-position:0% 50%}100%{background-position:220% 50%}}',
    ],
];
$guide = $examples[$decoGuideType] ?? $examples['nameplate'];
?>
<section class="<?= htmlspecialchars($decoGuidePanelClass, ENT_QUOTES, 'UTF-8') ?>" <?= htmlspecialchars($decoGuidePanelAttr, ENT_QUOTES, 'UTF-8') ?>="guide">
  <style>
    .deco-guide{--dg-accent:<?= htmlspecialchars($guide['accent'], ENT_QUOTES, 'UTF-8') ?>;--dg-soft:<?= htmlspecialchars($guide['soft'], ENT_QUOTES, 'UTF-8') ?>;display:grid;gap:18px;color:#0f172a}.deco-guide *{box-sizing:border-box}.dg-hero{position:relative;overflow:hidden;border:1px solid rgba(148,163,184,.28);border-radius:28px;padding:22px;background:radial-gradient(circle at 88% 12%,color-mix(in srgb,var(--dg-accent) 22%,transparent),transparent 34%),linear-gradient(135deg,#fff 0%,#f8fafc 55%,var(--dg-soft) 100%);box-shadow:0 22px 60px rgba(15,23,42,.08)}.dg-hero:before{content:"";position:absolute;inset:auto -70px -90px auto;width:220px;height:220px;border-radius:999px;background:color-mix(in srgb,var(--dg-accent) 14%,transparent);filter:blur(2px)}.dg-hero-top{position:relative;display:flex;align-items:center;gap:14px}.dg-icon{width:52px;height:52px;border-radius:18px;display:grid;place-items:center;background:var(--dg-accent);color:#fff;font-size:24px;font-weight:1000;box-shadow:0 14px 32px color-mix(in srgb,var(--dg-accent) 28%,transparent)}.dg-kicker{margin:0 0 5px;color:var(--dg-accent);font-size:12px;font-weight:1000;letter-spacing:.16em}.dg-hero h3{margin:0;font-size:24px;line-height:1.12;letter-spacing:-.05em}.dg-hero p{position:relative;margin:14px 0 0;max-width:760px;color:#475569;line-height:1.75;font-size:14px}.dg-quick{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}.dg-mini{border:1px solid #e2e8f0;border-radius:20px;background:#fff;padding:14px;box-shadow:0 10px 28px rgba(15,23,42,.04)}.dg-mini b{display:block;margin-bottom:6px;font-size:13px}.dg-mini span{display:block;color:#64748b;font-size:12px;line-height:1.55}.dg-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(260px,.9fr);gap:16px}.dg-card{border:1px solid #e2e8f0;border-radius:24px;background:#fff;box-shadow:0 14px 38px rgba(15,23,42,.05);overflow:hidden}.dg-card-head{display:flex;align-items:center;gap:10px;padding:16px 18px;border-bottom:1px solid #e2e8f0;background:linear-gradient(180deg,#fff,#f8fafc)}.dg-dot{width:11px;height:11px;border-radius:999px;background:var(--dg-accent);box-shadow:0 0 0 5px color-mix(in srgb,var(--dg-accent) 14%,transparent)}.dg-card h4{margin:0;font-size:15px;letter-spacing:-.02em}.dg-body{padding:16px 18px}.dg-list{margin:0;padding:0;list-style:none;display:grid;gap:11px}.dg-list li{position:relative;padding-left:24px;color:#475569;font-size:13px;line-height:1.7}.dg-list li:before{content:"";position:absolute;left:0;top:.72em;width:8px;height:8px;border-radius:999px;background:var(--dg-accent)}.dg-steps{display:grid;gap:12px}.dg-step{display:grid;grid-template-columns:46px 1fr;gap:12px;align-items:start}.dg-step-num{height:38px;border-radius:14px;display:grid;place-items:center;background:var(--dg-soft);color:var(--dg-accent);font-weight:1000;font-size:12px}.dg-step h5{margin:0 0 4px;font-size:14px}.dg-step p{margin:0;color:#64748b;font-size:12px;line-height:1.65}.dg-code{margin:0;white-space:pre-wrap;word-break:break-word;background:#0f172a;color:#dbeafe;border-radius:22px;padding:16px;font-size:12px;line-height:1.7;overflow:auto;border:1px solid rgba(255,255,255,.08);box-shadow:inset 0 1px 0 rgba(255,255,255,.06)}.dg-tip{display:flex;gap:12px;align-items:flex-start;border:1px solid color-mix(in srgb,var(--dg-accent) 26%,#e2e8f0);background:linear-gradient(135deg,#fff,var(--dg-soft));border-radius:22px;padding:15px 16px;color:#334155;font-size:13px;line-height:1.7}.dg-tip strong{color:#0f172a}.dg-tip-mark{flex:0 0 30px;width:30px;height:30px;border-radius:12px;background:var(--dg-accent);color:#fff;display:grid;place-items:center;font-weight:1000}@media(max-width:820px){.dg-grid{grid-template-columns:1fr}.dg-hero{border-radius:22px;padding:18px}.dg-hero h3{font-size:21px}.dg-icon{width:46px;height:46px;border-radius:16px}.dg-body{padding:14px}.dg-card-head{padding:14px}.dg-quick{grid-template-columns:1fr 1fr}}@media(max-width:520px){.dg-quick{grid-template-columns:1fr}.dg-step{grid-template-columns:40px 1fr}.dg-step-num{height:34px;border-radius:12px}.deco-guide{gap:14px}}html[data-theme="dark"] .deco-guide{color:#e5e7eb}html[data-theme="dark"] .dg-hero,html[data-theme="dark"] .dg-card,html[data-theme="dark"] .dg-mini{background:#111827;border-color:#263244;box-shadow:none}html[data-theme="dark"] .dg-card-head{background:#0f172a;border-color:#263244}html[data-theme="dark"] .dg-hero p,html[data-theme="dark"] .dg-list li,html[data-theme="dark"] .dg-step p,html[data-theme="dark"] .dg-mini span{color:#94a3b8}html[data-theme="dark"] .dg-tip{background:#111827;color:#cbd5e1;border-color:#263244}html[data-theme="dark"] .dg-tip strong{color:#f8fafc}
  </style>
  <div class="deco-guide">
    <div class="dg-hero">
      <div class="dg-hero-top">
        <div class="dg-icon"><?= htmlspecialchars($guide['icon'], ENT_QUOTES, 'UTF-8') ?></div>
        <div>
          <div class="dg-kicker"><?= htmlspecialchars($guide['kicker'], ENT_QUOTES, 'UTF-8') ?></div>
          <h3><?= htmlspecialchars($decoGuideTitle, ENT_QUOTES, 'UTF-8') ?></h3>
        </div>
      </div>
      <p><?= htmlspecialchars($guide['summary'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="dg-quick">
      <?php foreach ($guide['fields'] as $field): ?>
        <?php $parts = explode('：', $field, 2); ?>
        <div class="dg-mini"><b><?= htmlspecialchars($parts[0], ENT_QUOTES, 'UTF-8') ?></b><span><?= htmlspecialchars($parts[1] ?? '', ENT_QUOTES, 'UTF-8') ?></span></div>
      <?php endforeach; ?>
    </div>

    <div class="dg-grid">
      <div class="dg-card">
        <div class="dg-card-head"><span class="dg-dot"></span><h4><?= htmlspecialchars($guide['resource_title'], ENT_QUOTES, 'UTF-8') ?></h4></div>
        <div class="dg-body"><ul class="dg-list"><?php foreach ($guide['resource_lines'] as $line): ?><li><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div>
      </div>
      <div class="dg-card">
        <div class="dg-card-head"><span class="dg-dot"></span><h4>配置流程</h4></div>
        <div class="dg-body"><div class="dg-steps"><?php foreach ($baseSteps as $step): ?><div class="dg-step"><div class="dg-step-num"><?= htmlspecialchars($step['num'], ENT_QUOTES, 'UTF-8') ?></div><div><h5><?= htmlspecialchars($step['title'], ENT_QUOTES, 'UTF-8') ?></h5><p><?= htmlspecialchars($step['desc'], ENT_QUOTES, 'UTF-8') ?></p></div></div><?php endforeach; ?></div></div>
      </div>
    </div>

    <div class="dg-card">
      <div class="dg-card-head"><span class="dg-dot"></span><h4>整体样式示例 CSS / SVG</h4></div>
      <div class="dg-body"><pre class="dg-code"><code><?= htmlspecialchars($guide['code'], ENT_QUOTES, 'UTF-8') ?></code></pre></div>
    </div>

    <div class="dg-tip"><div class="dg-tip-mark">!</div><div><strong>上线检查：</strong><?= htmlspecialchars(implode('；', $guide['tips']), ENT_QUOTES, 'UTF-8') ?>。配置完成后，请到前台预览实际显示效果；“管理员授予”表示用户不会自动获得，需要在后台手动发放。</div></div>
  </div>
</section>
