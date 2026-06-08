    </div>
  </main>
</div>
<?php $siteCfg = (new \App\Models\SettingModel())->getSiteConfig(); ?>
<div class="admin-footer-note"><?= htmlspecialchars($siteCfg['footer_text'] ?? ('© ' . date('Y') . ' ClayBBS')) ?></div>
<link rel="stylesheet" href="/assets/css/admin-redesign.css?v=2026052602">
<?php require __DIR__ . '/dark-fix.php'; ?>
<style>
.ajax-toast-wrap{position:fixed;right:18px;top:76px;z-index:9999;display:grid;gap:10px;max-width:min(360px,calc(100vw - 32px));}
.ajax-toast{background:#0f172a;color:#fff;border-radius:14px;padding:12px 14px;box-shadow:0 18px 45px rgba(15,23,42,.22);font-size:13px;line-height:1.5;animation:ajaxToastIn .18s ease both;}
.ajax-toast.ok{background:#166534}.ajax-toast.err{background:#991b1b}.ajax-toast.info{background:#0f172a}
.ajax-busy{opacity:.62;pointer-events:none;}
@keyframes ajaxToastIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
</style>
<script>
(function(){
  if (window.__clayAjaxForms) return;
  window.__clayAjaxForms = true;
  function toast(msg, type){
    var wrap=document.querySelector('.ajax-toast-wrap');
    if(!wrap){wrap=document.createElement('div');wrap.className='ajax-toast-wrap';document.body.appendChild(wrap);}
    var el=document.createElement('div');el.className='ajax-toast '+(type||'info');el.textContent=msg||'已完成';wrap.appendChild(el);
    setTimeout(function(){el.style.opacity='0';el.style.transform='translateY(-6px)';},2600);
    setTimeout(function(){el.remove();},3100);
  }
  function shouldSkip(form){
    if(!form || form.method.toLowerCase()!=='post') return true;
    if(form.hasAttribute('data-no-ajax')) return true;
    if(form.enctype && form.enctype.toLowerCase()==='multipart/form-data' && form.querySelector('input[type=file]')) return true;
    var action=form.getAttribute('action')||location.href;
    if(action.indexOf('install.php')!==-1 || action.indexOf('logout')!==-1) return true;
    return false;
  }
  function successText(form){
    var act=(form.querySelector('input[name="_action"]')||{}).value||'';
    var actUrl=form.getAttribute('action')||'';
    if(act.indexOf('delete')!==-1 || /delete/i.test(actUrl)) return '删除成功';
    if(act.indexOf('create')!==-1) return '添加成功';
    if(act.indexOf('update')!==-1 || /toggle|settings|users|sections|announcements|banners|roles/.test(actUrl)) return '保存成功';
    return '操作成功';
  }
  document.addEventListener('submit', function(e){
    if(e.defaultPrevented) return;
    var form=e.target;
    if(shouldSkip(form)) return;
    e.preventDefault();
    var btn=e.submitter || form.querySelector('button[type=submit],button:not([type])');
    var old=btn?btn.textContent:'';
    if(btn){btn.disabled=true;btn.textContent='处理中...';}
    form.classList.add('ajax-busy');
    fetch(form.getAttribute('action') || location.href, {
      method:'POST',
      body:(function(){var fd=new FormData(form); if(btn && btn.name) fd.set(btn.name, btn.value); return fd;})(),
      credentials:'same-origin',
      headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html,application/json'}
    }).then(function(res){
      if(!res.ok) throw new Error('HTTP '+res.status);
      return res.text();
    }).then(function(text){
      var act=(form.querySelector('input[name="_action"]')||{}).value||'';
      var payload=null;
      try{ payload=JSON.parse(text); }catch(e){}
      if(payload && payload.ok===false){ throw new Error(payload.error||'操作失败'); }
      if(!payload){
        var doc=null;
        try{ doc=new DOMParser().parseFromString(text,'text/html'); }catch(e){}
        var errBox=doc?doc.querySelector('.admin-alert.err,[data-ajax-error]'):null;
        if(errBox){ throw new Error((errBox.textContent||'操作失败').trim()||'操作失败'); }
      }
      toast(successText(form),'ok');
      if(payload && payload.redirect){
        setTimeout(function(){ location.href=payload.redirect; }, 300);
        return;
      }
      if(form.hasAttribute('data-stay-on-success')){
        return;
      }
      setTimeout(function(){ location.reload(); }, 450);
      return;
    }).catch(function(err){
      toast('操作失败：'+err.message,'err');
    }).finally(function(){
      form.classList.remove('ajax-busy');
      if(btn){btn.disabled=false;btn.textContent=old;}
    });
  }, false);
})();
</script>


<script>
document.addEventListener('click', function(e){
  var c=e.target.closest('[data-admin-collapse]');
  if(c){var box=c.closest('.admin-collapse'); if(box) box.classList.toggle('open'); return;}
  var t=e.target.closest('[data-edit-target]');
  if(t){var id=t.getAttribute('data-edit-target'); var panel=document.getElementById(id); if(panel){panel.classList.toggle('open'); t.textContent=panel.classList.contains('open')?'收起':'编辑';} }
});
</script>
<script>
(function(){
  if(window.__clayThemeToggleLoaded) return; window.__clayThemeToggleLoaded=true;
  var animating=false,duration=560;
  function preferred(){try{return localStorage.getItem('clay_theme')||'system'}catch(e){return 'system'}}
  function actual(mode){return mode==='dark'||(mode==='system'&&window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light'}
  function syncButtons(mode){document.querySelectorAll('[data-theme-toggle]').forEach(function(btn){btn.setAttribute('data-mode',mode);});}
  function apply(mode){var a=actual(mode);document.documentElement.setAttribute('data-theme',a);document.documentElement.setAttribute('data-theme-mode',mode);syncButtons(mode);}
  function radius(x,y){var w=window.visualViewport?window.visualViewport.width:(window.innerWidth||document.documentElement.clientWidth||0);var h=window.visualViewport?window.visualViewport.height:(window.innerHeight||document.documentElement.clientHeight||0);return Math.ceil(Math.hypot(Math.max(x,w-x),Math.max(y,h-y)))+32;}
  function centerOf(origin){var w=window.visualViewport?window.visualViewport.width:(window.innerWidth||document.documentElement.clientWidth||0);var h=window.visualViewport?window.visualViewport.height:(window.innerHeight||document.documentElement.clientHeight||0);var x=w-40,y=24;if(origin&&origin.getBoundingClientRect){var r=origin.getBoundingClientRect();x=r.left+r.width/2;y=r.top+r.height/2;}return {x:x,y:y};}
  function cleanup(){delete document.documentElement.dataset.clayThemeVt;document.documentElement.style.removeProperty('--clay-theme-vt-duration');animating=false;}
  function transitionApply(mode,origin){var reduce=false;try{reduce=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;}catch(e){}if(reduce||animating||typeof document.startViewTransition!=='function'){apply(mode);return;}animating=true;var c=centerOf(origin),r=radius(c.x,c.y),root=document.documentElement;root.dataset.clayThemeVt='active';root.style.setProperty('--clay-theme-vt-duration',duration+'ms');var vt=document.startViewTransition(function(){apply(mode);});if(vt&&vt.ready&&typeof vt.ready.then==='function'){vt.ready.then(function(){root.animate({clipPath:['circle(0px at '+c.x+'px '+c.y+'px)','circle('+r+'px at '+c.x+'px '+c.y+'px)']},{duration:duration,easing:'ease-in-out',fill:'forwards',pseudoElement:'::view-transition-new(root)'});}).catch(cleanup);}if(vt&&vt.finished&&typeof vt.finished.finally==='function'){vt.finished.finally(cleanup);}else{setTimeout(cleanup,duration+120);}}
  window.ClayTheme={apply:apply,spreadApply:transitionApply,transitionApply:transitionApply};apply(preferred());
  function handleThemeToggle(btn){var cur=preferred();var next=actual(cur)==='dark'?'light':'dark';try{localStorage.setItem('clay_theme',next)}catch(_){}transitionApply(next,btn);}
  window.ClayThemeToggleClick=handleThemeToggle;
  document.addEventListener('click',function(e){var b=e.target.closest('[data-theme-toggle]');if(!b)return;e.preventDefault();e.stopPropagation();handleThemeToggle(b);}, true);
  if(window.matchMedia){try{matchMedia('(prefers-color-scheme: dark)').addEventListener('change',function(){if(preferred()==='system')apply('system')});}catch(e){}}
})();
</script>
<script>
(function(){
  if(window.__clayThemeThanksEgg) return; window.__clayThemeThanksEgg=true;
  var clicks=0,timer=null,running=false;
  function close(){var el=document.querySelector('.clay-thanks-stage');if(!el)return;el.classList.remove('show');running=false;setTimeout(function(){if(el&&!el.classList.contains('show'))el.remove();},420);}
  function show(owner){
    if(running) return; running=true;
    owner=(owner||'站长').replace(/[<>]/g,'').trim()||'站长';
    var el=document.createElement('div');el.className='clay-thanks-stage';
    var sparks='';for(var i=0;i<34;i++){sparks+='<i class="clay-thanks-spark" style="left:'+(8+Math.random()*84)+'%;top:'+(10+Math.random()*78)+'%;--tx:'+(Math.random()*120-60)+'px;--ty:'+(Math.random()*120-60)+'px;animation-delay:'+(-Math.random()*1.8)+'s"></i>';}
    el.innerHTML='<div class="clay-thanks-box"><div class="clay-thanks-text">感谢 '+owner+' 站长<br>使用此系统</div><div class="clay-thanks-sub">ClayBBS · build with care</div>'+sparks+'</div>';
    document.body.appendChild(el);el.addEventListener('click',close);
    requestAnimationFrame(function(){el.classList.add('show');});
    setTimeout(close,5200);
  }
  document.addEventListener('click',function(e){
    var btn=e.target.closest('.admin-theme-toggle[data-theme-toggle]');
    if(!btn) return;
    clicks++;clearTimeout(timer);timer=setTimeout(function(){clicks=0;},1200);
    if(clicks>=5){clicks=0;setTimeout(function(){show(btn.getAttribute('data-clay-owner')||'站长');},80);}
  },true);
})();
</script>

<style>
.clay-thanks-stage{position:fixed;inset:0;z-index:99998;display:grid;place-items:center;background:linear-gradient(135deg,rgba(2,6,23,.96),rgba(15,23,42,.94));opacity:0;pointer-events:none;overflow:hidden;transition:opacity .38s ease;}
.clay-thanks-stage.show{opacity:1;pointer-events:auto;}
.clay-thanks-stage::before{content:'';position:absolute;inset:-20%;background:conic-gradient(from 90deg,rgba(56,189,248,.04),rgba(168,85,247,.24),rgba(34,197,94,.12),rgba(248,113,113,.18),rgba(56,189,248,.04));filter:blur(26px);animation:clayThanksSpin 4.8s linear infinite;}
.clay-thanks-stage::after{content:'';position:absolute;inset:0;background:repeating-linear-gradient(0deg,rgba(255,255,255,.055) 0 1px,transparent 1px 7px);mix-blend-mode:screen;opacity:.28;animation:clayThanksScan 1.8s linear infinite;}
.clay-thanks-box{position:relative;width:min(760px,88vw);padding:34px 26px;border:1px solid rgba(125,211,252,.42);background:linear-gradient(180deg,rgba(15,23,42,.38),rgba(2,6,23,.22));box-shadow:0 0 60px rgba(56,189,248,.18),inset 0 0 36px rgba(168,85,247,.1);text-align:center;transform:translateY(18px) scale(.96);opacity:0;animation:clayThanksIn .82s .18s cubic-bezier(.2,.9,.2,1) forwards;}
.clay-thanks-text{font-size:clamp(24px,5vw,56px);font-weight:950;line-height:1.32;letter-spacing:.06em;color:#e0f2fe;text-shadow:0 0 12px rgba(56,189,248,.78),0 0 32px rgba(168,85,247,.58),0 0 72px rgba(34,197,94,.28);}
.clay-thanks-sub{margin-top:14px;font-size:12px;color:rgba(226,232,240,.56);letter-spacing:.18em;text-transform:uppercase;}
.clay-thanks-spark{position:absolute;width:5px;height:5px;border-radius:999px;background:#fff;box-shadow:0 0 12px #7dd3fc;animation:clayThanksFloat 1.8s ease-in-out infinite alternate;}
@keyframes clayThanksSpin{to{transform:rotate(360deg)}}
@keyframes clayThanksScan{to{transform:translateY(14px)}}
@keyframes clayThanksIn{to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes clayThanksFloat{to{transform:translate3d(var(--tx),var(--ty),0) scale(.35);opacity:.22}}
.clay-easter-sky{position:fixed;inset:0;z-index:99999;background:radial-gradient(circle at 50% 62%,rgba(30,64,175,.16),rgba(2,6,23,.94) 58%,rgba(2,6,23,.98));opacity:0;pointer-events:none;transition:opacity .42s ease;overflow:hidden;}
.clay-easter-sky.show{opacity:1;pointer-events:auto;}
.clay-easter-canvas{position:absolute;inset:0;width:100%;height:100%;}
.clay-easter-title{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) scale(.96);font-size:clamp(30px,7vw,96px);font-weight:900;letter-spacing:.16em;color:rgba(255,255,255,.72);text-shadow:0 0 12px rgba(125,211,252,.45),0 0 36px rgba(216,180,254,.34),0 0 72px rgba(248,113,113,.28);filter:blur(12px);opacity:0;white-space:nowrap;transition:opacity 1.4s ease,filter 2s ease,transform 2s ease;}
.clay-easter-sky.write .clay-easter-title{opacity:.92;filter:blur(2.8px);transform:translate(-50%,-50%) scale(1);}
.clay-easter-hint{position:absolute;left:50%;bottom:28px;transform:translateX(-50%);font-size:12px;color:rgba(226,232,240,.55);opacity:0;transition:opacity .6s ease;}
.clay-easter-sky.write .clay-easter-hint{opacity:1;}
@media (prefers-reduced-motion: reduce){.clay-easter-sky,.clay-easter-title{transition:none!important}.clay-easter-title{filter:blur(3px)!important;opacity:.9!important}}
</style>
<script>
(function(){
  if(window.__clayAdminEasterEgg) return; window.__clayAdminEasterEgg=true;
  var clicks=0,timer=null,running=false;
  function makeLayer(){
    var layer=document.querySelector('.clay-easter-sky');
    if(layer) return layer;
    layer=document.createElement('div');layer.className='clay-easter-sky';
    layer.innerHTML='<canvas class="clay-easter-canvas"></canvas><div class="clay-easter-title">卢女士好久不见</div><div class="clay-easter-hint">点击任意位置关闭</div>';
    document.body.appendChild(layer);
    layer.addEventListener('click', close);
    return layer;
  }
  function close(){
    var layer=document.querySelector('.clay-easter-sky');
    if(!layer) return;
    layer.classList.remove('show','write');running=false;
    setTimeout(function(){ if(layer&&!layer.classList.contains('show')) layer.remove(); },520);
  }
  function burst(ctx,w,h,x,y,count,baseHue,particles){
    for(var i=0;i<count;i++){
      var a=Math.random()*Math.PI*2,sp=1.5+Math.random()*5.8,life=70+Math.random()*45;
      particles.push({x:x,y:y,vx:Math.cos(a)*sp,vy:Math.sin(a)*sp-1.5,r:1.2+Math.random()*2.4,h:baseHue+Math.random()*70,life:life,max:life,drag:.982});
    }
  }
  function show(){
    if(running) return; running=true;
    var reduce=false;try{reduce=matchMedia('(prefers-reduced-motion: reduce)').matches}catch(e){}
    var layer=makeLayer(),canvas=layer.querySelector('canvas'),ctx=canvas.getContext('2d'),particles=[],rockets=[],start=performance.now(),dpr=Math.min(2,window.devicePixelRatio||1);
    function resize(){canvas.width=Math.floor(innerWidth*dpr);canvas.height=Math.floor(innerHeight*dpr);canvas.style.width=innerWidth+'px';canvas.style.height=innerHeight+'px';ctx.setTransform(dpr,0,0,dpr,0,0);}resize();
    addEventListener('resize',resize,{once:false});
    layer.classList.add('show');
    if(reduce){setTimeout(function(){layer.classList.add('write')},120);setTimeout(close,4200);return;}
    function launch(){
      var w=innerWidth,h=innerHeight;
      rockets.push({x:w*(.18+Math.random()*.64),y:h+20,vx:(Math.random()-.5)*1.4,vy:-(7+Math.random()*4),tx:w*(.18+Math.random()*.64),ty:h*(.18+Math.random()*.48),hue:190+Math.random()*170});
    }
    for(var i=0;i<5;i++) setTimeout(launch,i*360);
    function frame(now){
      var w=innerWidth,h=innerHeight,t=now-start;
      ctx.globalCompositeOperation='source-over';ctx.fillStyle='rgba(2,6,23,.22)';ctx.fillRect(0,0,w,h);
      ctx.globalCompositeOperation='lighter';
      if(t<3100 && Math.random()<.055) launch();
      for(var i=rockets.length-1;i>=0;i--){var r=rockets[i];r.x+=r.vx;r.y+=r.vy;r.vy+=.045;ctx.beginPath();ctx.arc(r.x,r.y,2,0,Math.PI*2);ctx.fillStyle='hsla('+r.hue+',100%,72%,.9)';ctx.fill();if(r.y<=r.ty||r.vy>=-1.2){burst(ctx,w,h,r.x,r.y,76+Math.random()*44,r.hue,particles);rockets.splice(i,1);}}
      for(var j=particles.length-1;j>=0;j--){var p=particles[j],a=Math.max(0,p.life/p.max);p.x+=p.vx;p.y+=p.vy;p.vx*=p.drag;p.vy=p.vy*p.drag+.035;p.life--;ctx.beginPath();ctx.arc(p.x,p.y,p.r*a,0,Math.PI*2);ctx.fillStyle='hsla('+p.h+',100%,'+(58+22*a)+'%,'+(a*.82)+')';ctx.shadowBlur=18*a;ctx.shadowColor='hsla('+p.h+',100%,70%,'+a+')';ctx.fill();if(p.life<=0)particles.splice(j,1);}
      ctx.shadowBlur=0;
      if(t>3100) layer.classList.add('write');
      if(t<7600 && running) requestAnimationFrame(frame); else setTimeout(close,1600);
    }
    requestAnimationFrame(frame);
  }
  document.addEventListener('click',function(e){
    var logo=e.target.closest('[data-clay-easter-logo]');
    if(!logo) return;
    clicks++; clearTimeout(timer); timer=setTimeout(function(){clicks=0},1300);
    if(clicks>=5){e.preventDefault();clicks=0;show();}
  },true);
})();
</script>

</body>
</html>
