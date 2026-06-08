<?php
$pageTitle = '编辑回复';
require dirname(__DIR__) . '/layouts/main.php';
$post = $post ?? [];
$currentContent = (string)($_POST['content'] ?? $post['content'] ?? '');
?>
<link href="/public/assets/css/quill.snow.css" rel="stylesheet">
<link href="/assets/css/editor-enhance.css?v=202605062146" rel="stylesheet">

<div class="page-header">
  <div class="page-title">编辑回复</div>
  <a class="btn btn-light admin-link-clean" href="/admin.php?path=posts">返回回复管理</a>
</div>
<div class="card post-edit-card">
  <?php if (!empty($error)): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <div class="post-edit-meta">所属帖子：<a href="/index.php?path=thread&id=<?= (int)($post['thread_id'] ?? 0) ?>" target="_blank"><?= htmlspecialchars($post['thread_title'] ?? '') ?></a></div>
  <form method="post" action="/admin.php?path=posts/edit" id="admin-post-edit-form" class="post-edit-form" data-no-ajax>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)($post['id'] ?? 0) ?>">
    <div class="editor-shell" id="editor-shell">
      <div id="quill-toolbar" class="ql-toolbar ql-snow clay-editor-toolbar">
          <span class="ql-formats">
            <select class="ql-header" title="标题">
              <option value="1">标题 1</option>
              <option value="2">标题 2</option>
              <option value="3">标题 3</option>
              <option selected>正文</option>
            </select>
          </span>
          <span class="ql-formats">
            <button class="ql-bold" type="button" title="加粗"></button>
            <button class="ql-italic" type="button" title="斜体"></button>
            <button class="ql-underline" type="button" title="下划线"></button>
            <button class="ql-strike" type="button" title="删除线"></button>
          </span>
          <span class="ql-formats clay-advanced">
            <span class="clay-color-control clay-color-text" title="文字颜色"><select class="ql-color"></select></span>
            <span class="clay-color-control clay-color-bg" title="背景色"><select class="ql-background"></select></span>
          </span>
          <span class="ql-formats">
            <button class="ql-align" value="" type="button" title="左对齐"><svg viewBox="0 0 18 18"><line class="ql-stroke" x1="3" x2="15" y1="5" y2="5"></line><line class="ql-stroke" x1="3" x2="12" y1="9" y2="9"></line><line class="ql-stroke" x1="3" x2="15" y1="13" y2="13"></line></svg></button>
            <button class="ql-align" value="center" type="button" title="居中"><svg viewBox="0 0 18 18"><line class="ql-stroke" x1="4" x2="14" y1="5" y2="5"></line><line class="ql-stroke" x1="6" x2="12" y1="9" y2="9"></line><line class="ql-stroke" x1="4" x2="14" y1="13" y2="13"></line></svg></button>
            <button class="ql-align" value="right" type="button" title="右对齐"><svg viewBox="0 0 18 18"><line class="ql-stroke" x1="3" x2="15" y1="5" y2="5"></line><line class="ql-stroke" x1="6" x2="15" y1="9" y2="9"></line><line class="ql-stroke" x1="3" x2="15" y1="13" y2="13"></line></svg></button>
            <button class="ql-align" value="justify" type="button" title="两端对齐"><svg viewBox="0 0 18 18"><line class="ql-stroke" x1="3" x2="15" y1="5" y2="5"></line><line class="ql-stroke" x1="3" x2="15" y1="9" y2="9"></line><line class="ql-stroke" x1="3" x2="15" y1="13" y2="13"></line></svg></button>
          </span>
          <span class="ql-formats">
            <button class="ql-list" value="ordered" type="button" title="有序列表"></button>
            <button class="ql-list" value="bullet" type="button" title="无序列表"></button>
            <button class="ql-list" value="check" type="button" title="任务列表"></button>
          </span>
          <span class="ql-formats clay-advanced">
            <button class="ql-indent" value="-1" type="button" title="减少缩进"></button>
            <button class="ql-indent" value="+1" type="button" title="增加缩进"></button>
          </span>
          <span class="ql-formats clay-advanced">
            <button class="ql-script" value="sub" type="button" title="下标"></button>
            <button class="ql-script" value="super" type="button" title="上标"></button>
          </span>
          <span class="ql-formats clay-advanced">
            <button class="ql-blockquote" type="button" title="引用"></button>
            <button class="ql-code-block" type="button" title="代码块"></button>
          </span>
          <span class="ql-formats">
            <button class="ql-link" type="button" title="链接"></button>
            <button class="ql-image" type="button" title="图片"></button>
            <button class="ql-clean" type="button" title="清除格式"></button>
          </span>
          <span class="ql-formats clay-more-group"><button class="clay-toolbar-more" type="button" aria-expanded="false" title="更多工具">更多</button></span>
        </div>
        <div id="quill-editor"></div>
      <textarea class="textarea fallback-editor" id="fallback-editor"><?= htmlspecialchars($currentContent) ?></textarea>
      <input type="hidden" name="content" id="content-input">
    </div>
    <div class="editor-tip">支持加粗、颜色、对齐、上下标、列表、引用、代码块、链接和清除格式。工具栏会在长文编辑时吸顶跟随。</div>
    <div class="post-edit-actions">
      <button class="btn" type="submit">保存修改</button>
      <a class="btn btn-light" href="/admin.php?path=posts">取消</a>
    </div>
  </form>
</div>

<script src="/public/assets/js/quill.js"></script>
<script>
(function(){
  var form=document.getElementById('admin-post-edit-form');
  var shell=document.getElementById('editor-shell');
  var hidden=document.getElementById('content-input');
  var fallback=document.getElementById('fallback-editor');
  var old=<?= json_encode($currentContent) ?>;
  var quill=null;
  function plain(html){return (html||'').replace(/<br\s*\/?>/gi,'\n').replace(/<[^>]+>/g,'');}
  function fallbackOn(){shell.classList.add('editor-fallback');fallback.style.display='block';if(old&&!fallback.value)fallback.value=plain(old);}
function bindToolbarMore(){
  var toolbar=document.getElementById('quill-toolbar');
  if(!toolbar) return;
  var btn=toolbar.querySelector('.clay-toolbar-more');
  if(!btn) return;
  btn.addEventListener('click', function(){
    var open=!toolbar.classList.contains('is-expanded');
    toolbar.classList.toggle('is-expanded', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.textContent = open ? '收起' : '更多';
  });
}
bindToolbarMore();
  if(typeof window.Quill==='undefined'){
    fallbackOn();
  }else{
    try{
      quill=new Quill('#quill-editor',{theme:'snow',placeholder:'请输入回复内容...',modules:{toolbar: '#quill-toolbar'}});
      if(old)quill.clipboard.dangerouslyPasteHTML(old);
    }catch(e){fallbackOn();quill=null;}
  }
  
form.addEventListener('submit',function(e){
    var c='';
    if(quill){
      if(quill.getText().trim()===''){e.preventDefault();alert('请输入回复内容');return;}
      c=quill.root.innerHTML;
    }else{
      c=fallback.value.trim();
      if(!c){e.preventDefault();alert('请输入回复内容');return;}
      c=c.replace(/[&<>]/g,function(x){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[x]}).replace(/\n/g,'<br>');
    }
    hidden.value=c;
  });
})();
</script>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
