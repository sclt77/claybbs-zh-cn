<?php

declare(strict_types=1);

function clay_editor_toolbar(string $tableButtonId = 'insertTableBlock', string $paidButtonId = 'insertPaidBlock'): string
{
    $tableButtonId = htmlspecialchars($tableButtonId, ENT_QUOTES, 'UTF-8');
    $paidButtonId = htmlspecialchars($paidButtonId, ENT_QUOTES, 'UTF-8');
    return <<<HTML
<div id="quill-toolbar" class="ql-toolbar ql-snow clay-editor-toolbar">
  <div class="clay-editor-toolbar-scroll" aria-label="编辑器工具栏">
  <span class="ql-formats"><select class="ql-header" title="标题"><option value="1">标题 1</option><option value="2">标题 2</option><option value="3">标题 3</option><option selected>正文</option></select></span>
  <span class="ql-formats"><button class="ql-bold" type="button" title="加粗"></button><button class="ql-italic" type="button" title="斜体"></button><button class="ql-underline" type="button" title="下划线"></button></span>
  <span class="ql-formats"><button class="ql-list" value="ordered" type="button" title="有序列表"></button><button class="ql-list" value="bullet" type="button" title="无序列表"></button><button class="ql-blockquote" type="button" title="引用"></button></span>
  <span class="ql-formats"><button class="ql-align" value="center" type="button" title="居中"><svg viewBox="0 0 18 18"><line class="ql-stroke" x1="4" x2="14" y1="5" y2="5"></line><line class="ql-stroke" x1="6" x2="12" y1="9" y2="9"></line><line class="ql-stroke" x1="4" x2="14" y1="13" y2="13"></line></svg></button><button class="ql-link" type="button" title="链接"></button><button class="ql-image" type="button" title="图片"></button><button class="clay-table-toolbar-btn" type="button" id="{$tableButtonId}" title="插入表格" aria-label="表格"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="2"/><path d="M4 10h16M4 15h16M9 5v14M15 5v14"/></svg></button><button class="clay-paid-toolbar-btn" type="button" id="{$paidButtonId}" title="插入付费查看内容" aria-label="付费查看"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V8a5 5 0 0 1 10 0v2"/><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M12 14v3"/></svg></button><button class="ql-clean" type="button" title="清除格式"></button></span>
  </div>
</div>
HTML;
}
