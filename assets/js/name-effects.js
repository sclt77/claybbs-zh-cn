
(function () {
  if (window.__clayNameEffects) return;
  window.__clayNameEffects = true;

  function initGlitch(el) {
    if (el.__npGlitchDone) return;
    el.__npGlitchDone = true;
    var t = el.querySelector('.np-fx-text');
    if (t) t.setAttribute('data-np-name', t.textContent || '');
  }

  function initStarlight(el) {
    if (el.__npStarDone) return;
    el.__npStarDone = true;
    var anime = window.anime;
    if (typeof anime !== 'function') return; 
    var count = 4;
    var stars = [];
    for (var i = 0; i < count; i++) {
      var s = document.createElement('span');
      s.className = 'np-star';
      el.appendChild(s);
      stars.push(s);
    }
    function place(s) {
      var w = el.offsetWidth || 60, h = el.offsetHeight || 18;
      s.style.left = (Math.random() * w) + 'px';
      s.style.top = (Math.random() * h) + 'px';
    }
    stars.forEach(function (s, idx) {
      place(s);
      anime({
        targets: s,
        opacity: [0, 1, 0],
        scale: [0.4, 1.2, 0.4],
        duration: 1400 + Math.random() * 1200,
        delay: idx * 350,
        easing: 'easeInOutSine',
        loop: true,
        begin: function () { place(s); }
      });
    });
  }

  function scan(root) {
    if (!root || !root.querySelectorAll) return;
    root.querySelectorAll('.np-fx--glitch').forEach(initGlitch);
    root.querySelectorAll('.np-fx--starlight').forEach(initStarlight);
  }

  function boot() {
    scan(document);
    var obs = new MutationObserver(function (muts) {
      for (var i = 0; i < muts.length; i++) {
        var added = muts[i].addedNodes;
        for (var j = 0; j < added.length; j++) {
          var n = added[j];
          if (n.nodeType === 1) {
            if (n.classList && n.classList.contains('np-fx')) {
              if (n.classList.contains('np-fx--glitch')) initGlitch(n);
              if (n.classList.contains('np-fx--starlight')) initStarlight(n);
            }
            scan(n);
          }
        }
      }
    });
    obs.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
