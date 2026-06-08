(function(){
  function escapeHtml(s){return String(s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
  function ensureBox(){
    var old=document.querySelector('.mention-picker-box');
    if(old) return old;
    var box=document.createElement('div');
    box.className='mention-picker-box';
    box.style.cssText='position:absolute;z-index:999999;display:none;min-width:190px;max-width:280px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 16px 40px rgba(15,23,42,.22);overflow:hidden;font-size:13px;';
    box.addEventListener('mousedown',function(e){e.preventDefault();});
    document.body.appendChild(box);
    return box;
  }
  function renderUsers(box, users, onPick){
    if(!users || !users.length){box.style.display='none';box.innerHTML='';return;}
    box.innerHTML=users.map(function(u){var name=u.name||u.username;return '<button type="button" data-name="'+escapeHtml(name)+'" style="display:block;width:100%;border:0;background:#fff;text-align:left;padding:10px 12px;cursor:pointer;color:#0f172a;"><b>@'+escapeHtml(name)+'</b><span style="display:block;color:#94a3b8;font-size:12px;">'+escapeHtml(u.username||'')+'</span></button>';}).join('');
    box.onclick=function(e){var btn=e.target.closest('button[data-name]'); if(btn) onPick(btn.dataset.name||'');};
  }
  function search(q, cb){
    fetch('/api.php?path=users/search&q='+encodeURIComponent(q||''),{credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(data){cb(data.users||[]);})
      .catch(function(){cb([]);});
  }
  function plainBeforeTextarea(el){return (el.value||'').slice(0, el.selectionStart||0);}
  function textareaCaretApprox(el){
    var rect=el.getBoundingClientRect();
    try{
      var style=getComputedStyle(el), div=document.createElement('div');
      var props=['boxSizing','width','height','overflowX','overflowY','borderTopWidth','borderRightWidth','borderBottomWidth','borderLeftWidth','paddingTop','paddingRight','paddingBottom','paddingLeft','fontStyle','fontVariant','fontWeight','fontStretch','fontSize','fontSizeAdjust','lineHeight','fontFamily','textAlign','textTransform','textIndent','textDecoration','letterSpacing','wordSpacing','tabSize','MozTabSize'];
      props.forEach(function(p){div.style[p]=style[p];});
      div.style.position='absolute';div.style.visibility='hidden';div.style.whiteSpace='pre-wrap';div.style.wordWrap='break-word';div.style.left='-9999px';div.style.top='0';
      var before=(el.value||'').slice(0, el.selectionStart||0);
      div.textContent=before;
      var span=document.createElement('span');span.textContent='\u200b';div.appendChild(span);
      document.body.appendChild(div);
      var dr=div.getBoundingClientRect();
      var sr=span.getBoundingClientRect();
      var line=parseFloat(style.lineHeight || '20');
      document.body.removeChild(div);
      return {left:rect.left + (sr.left - dr.left) - el.scrollLeft, top:rect.top + (sr.top - dr.top) - el.scrollTop, bottom:rect.top + (sr.top - dr.top) - el.scrollTop + line};
    }catch(e){return {left:rect.left+12, top:rect.top+32, bottom:rect.top+52};}
  }
  window.ClayMentionPicker={
    bind:function(getTarget){
      var box=ensureBox(), timer=null, active=null, currentQuery='', lastToken='';
      function hide(){box.style.display='none';box.innerHTML='';active=null;lastToken='';}
      function place(rect){box.style.left=(rect.left+window.scrollX)+'px';box.style.top=(rect.bottom+window.scrollY+8)+'px';box.style.display='block';}
      function runSearch(el,m){
        active=el; currentQuery=m[1]||''; lastToken='@'+currentQuery;
        clearTimeout(timer);
        timer=setTimeout(function(){
          if(!active) return;
          search(currentQuery,function(users){
            if(!active) return;
            renderUsers(box,users,function(name){
              if(!active) return;
              var start=active.selectionStart||0,end=active.selectionEnd||start,val=active.value||'';
              var left=val.slice(0,start).replace(/@[\p{L}\p{N}_\u4e00-\u9fa5]*$/u,'');
              var text='@'+name+' ';
              active.value=left+text+val.slice(end);
              var pos=(left+text).length;
              active.setSelectionRange(pos,pos);active.focus();hide();
            });
            place(textareaCaretApprox(active));
          });
        },80);
      }
      function check(target){
        var el=getTarget(target); if(!el || el.classList.contains('ql-editor')) return;
        var before=plainBeforeTextarea(el), m=before.match(/(?:^|\s)@([\p{L}\p{N}_\u4e00-\u9fa5]{0,30})$/u);
        if(!m){clearTimeout(timer);hide();return;}
        runSearch(el,m);
      }
      document.addEventListener('input',function(e){check(e.target);},true);
      document.addEventListener('keyup',function(e){check(e.target);},true);
      document.addEventListener('focusin',function(e){check(e.target);},true);
      document.addEventListener('compositionend',function(e){setTimeout(function(){check(e.target);},0);},true);
      document.addEventListener('selectionchange',function(){if(active) check(active);});
      document.addEventListener('click',function(e){if(!e.target.closest('.mention-picker-box')) setTimeout(function(){if(!document.activeElement || document.activeElement!==active) hide();},120);});
    },
    bindQuill:function(quill, host){
      if(!quill || !host) return;
      var box=ensureBox(), timer=null, currentRange=null, currentQuery='';
      function hide(){box.style.display='none';box.innerHTML='';}
      function check(){
        var range=quill.getSelection();
        if(!range){hide();return;}
        currentRange=range;
        var before=quill.getText(0, range.index || 0);
        var m=before.match(/@([\p{L}\p{N}_\u4e00-\u9fa5]{0,30})$/u);
        clearTimeout(timer); if(!m){hide();return;}
        currentQuery=m[1]||'';
        timer=setTimeout(function(){
          search(currentQuery,function(users){
            renderUsers(box,users,function(name){
              var r=quill.getSelection() || currentRange;
              var len=currentQuery.length+1;
              var start=Math.max(0,(r.index||0)-len);
              quill.deleteText(start,len,'user');
              quill.insertText(start,'@'+name+' ','user');
              quill.setSelection(start+name.length+2,0,'user');
              hide();
            });
            var bounds=quill.getBounds(range.index || 0);
            var hostRect=host.getBoundingClientRect();
            box.style.left=(hostRect.left+window.scrollX+bounds.left)+'px';
            box.style.top=(hostRect.top+window.scrollY+bounds.top+bounds.height+8)+'px';
            box.style.display='block';
          });
        },80);
      }
      quill.on('text-change', check);
      quill.on('selection-change', function(){setTimeout(check,0);});
      document.addEventListener('click',function(e){if(!e.target.closest('.mention-picker-box') && !e.target.closest('.ql-editor')) hide();});
    }
  };
})();
