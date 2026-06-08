<button class="theme-toggle-fab" type="button" data-theme-toggle title="切换日夜模式" aria-label="切换日夜模式">
  <svg class="theme-toggle-icon theme-toggle-icon--system" data-theme-icon-system viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"></rect><path d="M8 20h8M12 16v4"></path><circle cx="18" cy="7" r="1.4" fill="currentColor" stroke="none"></circle></svg>
  <svg class="theme-toggle-icon theme-toggle-icon--light" data-theme-icon-light viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path></svg>
  <svg class="theme-toggle-icon theme-toggle-icon--dark" data-theme-icon-dark viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A8.5 8.5 0 1 1 11.21 3 6.5 6.5 0 0 0 21 12.79z"></path></svg>
</button>
<style>
.theme-toggle-fab{position:fixed;right:18px;bottom:132px;z-index:520;width:46px;height:46px;border-radius:999px;border:1px solid rgba(226,232,240,.9);background:rgba(255,255,255,.92);color:#0f172a;display:grid;place-items:center;cursor:pointer;box-shadow:0 16px 36px rgba(15,23,42,.18);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);transition:transform .16s ease,border-color .16s ease,color .16s ease,background .16s ease}.theme-toggle-fab:hover{transform:translateY(-2px);border-color:var(--primary,#0284c7);color:var(--primary,#0284c7)}.theme-toggle-icon{display:none;width:21px;height:21px}.theme-toggle-fab[data-mode="system"] .theme-toggle-icon--system,.theme-toggle-fab[data-mode="light"] .theme-toggle-icon--light,.theme-toggle-fab[data-mode="dark"] .theme-toggle-icon--dark{display:block}html[data-theme="dark"] .theme-toggle-fab{background:rgba(15,23,42,.86);border-color:#334155;color:#e5e7eb;box-shadow:0 18px 42px rgba(0,0,0,.38)}html[data-theme="dark"] .theme-toggle-fab:hover{color:#38bdf8;border-color:#38bdf8}::view-transition-old(root),::view-transition-new(root){animation:none;mix-blend-mode:normal;}html[data-clay-theme-vt="active"]::view-transition-group(root){animation-duration:var(--clay-theme-vt-duration,520ms);}@media(prefers-reduced-motion:reduce){.theme-toggle-fab{transition:none}}@media(max-width:768px){.theme-toggle-fab{right:14px;bottom:126px;width:42px;height:42px}.theme-toggle-icon{width:20px;height:20px}}
</style>
<script>
(function(){
  if(window.__clayThemeToggleLoaded) return; window.__clayThemeToggleLoaded=true;
  var animating=false;
  var duration=560;
  function preferred(){try{return localStorage.getItem('clay_theme')||'system'}catch(e){return 'system'}}
  function actual(mode){return mode==='dark'||(mode==='system'&&window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light'}
  function syncButtons(mode){document.querySelectorAll('[data-theme-toggle]').forEach(function(btn){btn.setAttribute('data-mode',mode);});}
  function apply(mode){var a=actual(mode);document.documentElement.setAttribute('data-theme',a);document.documentElement.setAttribute('data-theme-mode',mode);syncButtons(mode);}
  function radius(x,y){var w=window.visualViewport?window.visualViewport.width:(window.innerWidth||document.documentElement.clientWidth||0);var h=window.visualViewport?window.visualViewport.height:(window.innerHeight||document.documentElement.clientHeight||0);return Math.ceil(Math.hypot(Math.max(x,w-x),Math.max(y,h-y)))+32;}
  function centerOf(origin){var w=window.visualViewport?window.visualViewport.width:(window.innerWidth||document.documentElement.clientWidth||0);var h=window.visualViewport?window.visualViewport.height:(window.innerHeight||document.documentElement.clientHeight||0);var x=w-40,y=h-140;if(origin&&origin.getBoundingClientRect){var r=origin.getBoundingClientRect();x=r.left+r.width/2;y=r.top+r.height/2;}return {x:x,y:y};}
  function cleanup(){delete document.documentElement.dataset.clayThemeVt;document.documentElement.style.removeProperty('--clay-theme-vt-duration');animating=false;}
  function transitionApply(mode,origin){
    var reduce=false;try{reduce=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;}catch(e){}
    if(reduce||animating||typeof document.startViewTransition!=='function'){apply(mode);return;}
    animating=true;
    var c=centerOf(origin), r=radius(c.x,c.y), root=document.documentElement;
    root.dataset.clayThemeVt='active';root.style.setProperty('--clay-theme-vt-duration',duration+'ms');
    var vt=document.startViewTransition(function(){apply(mode);});
    if(vt&&vt.ready&&typeof vt.ready.then==='function'){
      vt.ready.then(function(){
        root.animate({clipPath:['circle(0px at '+c.x+'px '+c.y+'px)','circle('+r+'px at '+c.x+'px '+c.y+'px)']},{duration:duration,easing:'ease-in-out',fill:'forwards',pseudoElement:'::view-transition-new(root)'});
      }).catch(cleanup);
    }
    if(vt&&vt.finished&&typeof vt.finished.finally==='function'){vt.finished.finally(cleanup);}else{setTimeout(cleanup,duration+120);}
  }
  window.ClayTheme={apply:apply,spreadApply:transitionApply,transitionApply:transitionApply};
  apply(preferred());
  document.addEventListener('click',function(e){var b=e.target.closest('[data-theme-toggle]');if(!b)return;var cur=preferred();var next=actual(cur)==='dark'?'light':'dark';try{localStorage.setItem('clay_theme',next)}catch(_){}transitionApply(next,b);});
  if(window.matchMedia){try{matchMedia('(prefers-color-scheme: dark)').addEventListener('change',function(){if(preferred()==='system')apply('system')});}catch(e){}}
})();
</script>
