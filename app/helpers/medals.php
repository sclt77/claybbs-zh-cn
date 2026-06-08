<?php

declare(strict_types=1);

if (!function_exists('clay_medal_display_css')) {
    function clay_medal_display_css(): string
    {
        return <<<'CSS'
.clay-user-badges{
  --clay-medal-icon-size:16px;
  --clay-medal-font-size:11px;
  --clay-medal-max-width:118px;
  display:inline-flex;
  align-items:center;
  gap:4px;
  flex-wrap:wrap;
  max-width:100%;
  vertical-align:middle;
}
.clay-user-medal{
  --clay-medal-icon-size:16px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:3px;
  max-width:var(--clay-medal-max-width);
  min-width:0;
  min-height:calc(var(--clay-medal-icon-size) + 4px);
  padding:2px 6px 2px 3px;
  border-radius:999px;
  background:color-mix(in srgb,var(--medal-color,#f59e0b) 10%,#fff);
  border:1px solid color-mix(in srgb,var(--medal-color,#f59e0b) 22%,#e2e8f0);
  color:#334155;
  font-size:var(--clay-medal-font-size);
  font-weight:900;
  line-height:1.15;
  text-decoration:none!important;
  vertical-align:middle;
  box-shadow:0 2px 8px rgba(15,23,42,.04);
}
.clay-user-medal img,
.clay-user-medal svg{
  width:var(--clay-medal-icon-size)!important;
  height:var(--clay-medal-icon-size)!important;
  max-width:var(--clay-medal-icon-size)!important;
  max-height:var(--clay-medal-icon-size)!important;
  object-fit:contain;
  display:block;
  flex:0 0 var(--clay-medal-icon-size);
  border-radius:50%;
}
.clay-user-medal-name,
.clay-user-medal span{
  display:block;
  min-width:0;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
}
.author-name .clay-user-badges,
.author-card-name .clay-user-badges{
  --clay-medal-icon-size:14px;
  --clay-medal-font-size:0;
  --clay-medal-max-width:18px;
  flex-wrap:nowrap;
  gap:3px;
}
.author-name .clay-user-medal,
.author-card-name .clay-user-medal{
  --clay-medal-icon-size:14px;
  width:18px;
  height:18px;
  min-height:18px;
  padding:1px;
  border-radius:999px;
  overflow:hidden;
}
.author-name .clay-user-medal-name,
.author-name .clay-user-medal span,
.author-card-name .clay-user-medal-name,
.author-card-name .clay-user-medal span{
  position:absolute!important;
  width:1px!important;
  height:1px!important;
  padding:0!important;
  margin:-1px!important;
  overflow:hidden!important;
  clip:rect(0,0,0,0)!important;
  white-space:nowrap!important;
  border:0!important;
}
.me-badges-row .clay-user-badges,
.profile-badges-row .clay-user-badges{
  --clay-medal-icon-size:18px;
  --clay-medal-font-size:12px;
  --clay-medal-max-width:132px;
  margin-top:8px;
}
.medal-my-list .clay-user-badges{
  --clay-medal-icon-size:18px;
  --clay-medal-font-size:12px;
  --clay-medal-max-width:132px;
}
.me-badges-row .clay-user-medal,
.profile-badges-row .clay-user-medal,
.medal-my-list .clay-user-medal{
  --clay-medal-icon-size:18px;
}
@media(max-width:640px){
  .me-badges-row .clay-user-badges,
  .profile-badges-row .clay-user-badges,
  .medal-my-list .clay-user-badges{
    --clay-medal-icon-size:16px;
    --clay-medal-font-size:11px;
    --clay-medal-max-width:112px;
  }
  .me-badges-row .clay-user-medal,
  .profile-badges-row .clay-user-medal,
  .medal-my-list .clay-user-medal{
    --clay-medal-icon-size:16px;
  }
}
.thread-time-medals-row{
  display:flex;
  align-items:center;
  gap:6px;
  margin:7px 0 0 calc(42px + clamp(6px,1.4vw,9px));
  min-width:0;
  color:var(--text-soft,#64748b);
}
.thread-time-medals,
.author-card-medal-list{
  --clay-medal-icon-size:27px;
  --clay-medal-font-size:12px;
  --clay-medal-max-width:24px;
  gap:8px;
}
.thread-time-medals .clay-user-medal,
.author-card-medal-list .clay-user-medal{
  --clay-medal-icon-size:27px;
  width:32px;
  height:32px;
  min-height:32px;
  max-width:32px;
  padding:1px;
  border-width:1px;
  border-color:color-mix(in srgb,var(--medal-color,#f59e0b) 55%,#f8fafc);
  background:
    radial-gradient(circle at 32% 18%, rgba(255,255,255,.98) 0 18%, rgba(255,255,255,.72) 32%, transparent 58%),
    color-mix(in srgb,var(--medal-color,#f59e0b) 20%,#fff);
  box-shadow:
    0 5px 14px rgba(15,23,42,.10),
    0 0 0 2px color-mix(in srgb,var(--medal-color,#f59e0b) 12%,transparent);
  filter:saturate(1.12) contrast(1.06);
  cursor:pointer;
  appearance:none;
  -webkit-appearance:none;
}
.thread-time-medals .clay-user-medal-name,
.thread-time-medals .clay-user-medal span,
.author-card-medal-list .clay-user-medal-name,
.author-card-medal-list .clay-user-medal span,
.clay-user-medal.is-icon-only .clay-user-medal-name,
.clay-user-medal.is-icon-only span{
  position:absolute!important;
  width:1px!important;
  height:1px!important;
  padding:0!important;
  margin:-1px!important;
  overflow:hidden!important;
  clip:rect(0,0,0,0)!important;
  white-space:nowrap!important;
  border:0!important;
}
.thread-time-medals .clay-user-medal:hover,
.author-card-medal-list .clay-user-medal:hover{
  transform:translateY(-1px) scale(1.04);
  box-shadow:
    0 9px 22px rgba(15,23,42,.14),
    0 0 0 3px color-mix(in srgb,var(--medal-color,#f59e0b) 18%,transparent);
}
.clay-medal-popover{
  position:fixed;
  z-index:1200;
  min-width:72px;
  max-width:220px;
  padding:8px 11px;
  border-radius:12px;
  background:rgba(15,23,42,.92);
  color:#fff;
  font-size:12px;
  font-weight:950;
  line-height:1.25;
  text-align:center;
  box-shadow:0 12px 30px rgba(15,23,42,.20);
  pointer-events:none;
  opacity:0;
  transform:translate(-50%,-8px) scale(.96);
  transition:opacity .12s ease,transform .12s ease;
  white-space:nowrap;
}
.clay-medal-popover.is-open{
  opacity:1;
  transform:translate(-50%,-12px) scale(1);
}
.clay-medal-popover::after{
  content:'';
  position:absolute;
  left:50%;
  bottom:-5px;
  width:10px;
  height:10px;
  background:rgba(15,23,42,.92);
  transform:translateX(-50%) rotate(45deg);
  border-radius:2px;
}
.author-card-medals{
  display:flex;
  justify-content:center;
  margin-top:10px;
}
html[data-theme="dark"] .clay-user-medal{
  background:color-mix(in srgb,var(--medal-color,#f59e0b) 16%,#0f172a);
  border-color:color-mix(in srgb,var(--medal-color,#f59e0b) 28%,#263244);
  color:#e2e8f0;
}
@media(max-width:768px){
  .thread-time-medals-row{margin-left:calc(36px + clamp(6px,1.4vw,9px));align-items:center;}
  .thread-time-medals{--clay-medal-icon-size:24px;--clay-medal-font-size:11px;--clay-medal-max-width:21px;gap:7px;}
  .thread-time-medals .clay-user-medal{--clay-medal-icon-size:24px;width:29px;height:29px;min-height:29px;max-width:29px;padding:1px;}
}
CSS;
    }
}

if (!function_exists('clay_medal_display_style_tag')) {
    function clay_medal_display_style_tag(): string
    {
        return '<style id="clay-medal-display-style">' . clay_medal_display_css() . '</style>';
    }
}
