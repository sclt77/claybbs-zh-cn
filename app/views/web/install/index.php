<?php
$step = $step ?? 1; $error = $error ?? ''; $title = $title ?? ''; $cfg = $cfg ?? [];
$stepLabels = [1=>'环境',2=>'授权',3=>'数据库',35=>'模式',4=>'迁移',5=>'管理员',6=>'完成'];
function allChecksPass(array $checks): bool { foreach ($checks as $c) { if (!$c[1]) return false; } return true; }
?>
<!DOCTYPE html><html lang="zh"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>论坛安装向导 - <?= htmlspecialchars($title) ?></title><link rel="stylesheet" href="/assets/css/style.css"><style>
:root{
  --bg:#f8fafc;
  --bg-soft:#f1f5f9;
  --bg-elevated:#ffffff;
  --bg-elevated-2:#f8fafc;
  --text:#0f172a;
  --text-soft:#475569;
  --text-muted:#94a3b8;
  --line:#e2e8f0;
  --line-soft:#f1f5f9;
  --primary:#0284c7;
  --primary-hover:#0369a1;
  --shadow-strong:0 10px 30px rgba(15,23,42,.08);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg-soft);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;color:var(--text)}
.card{background:var(--bg-elevated);border-radius:16px;box-shadow:var(--shadow-strong);width:100%;max-width:520px;overflow:hidden}
.header{background:linear-gradient(135deg,var(--primary),var(--primary-hover));color:#fff;padding:32px 36px}
.header h1{font-size:22px;font-weight:700;margin-bottom:4px}
.header p{font-size:13px;opacity:.8}
.steps{display:flex;padding:20px 36px;border-bottom:1px solid var(--line-soft)}
.step{flex:1;text-align:center;position:relative}
.step:not(:last-child)::after{content:'';position:absolute;top:14px;right:0;width:100%;height:2px;background:var(--line);z-index:0}
.step-num{width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;position:relative;z-index:1}
.step.done .step-num{background:var(--primary);color:#fff}
.step.active .step-num{background:var(--primary);color:#fff;box-shadow:0 0 0 3px rgba(2,132,199,.18)}
.step.pending .step-num{background:var(--line);color:var(--text-muted)}
.step-label{font-size:11px;color:var(--text-muted);margin-top:4px;display:block}
.step.active .step-label,.step.done .step-label{color:var(--primary)}
.body{padding:32px 36px}
.error{background:#fce8e6;color:#c62828;border-radius:8px;padding:12px 16px;font-size:14px;margin-bottom:20px}
body[data-theme="dark"] .error,html[data-theme="dark"] .error{background:#450a0a;color:#fecaca}
.form-group{margin-bottom:18px}
label{display:block;font-size:13px;color:var(--text-soft);margin-bottom:6px;font-weight:500}
input[type=text],input[type=password],input[type=number],input[type=email],input[type=datetime-local]{width:100%;border:1.5px solid var(--line);border-radius:8px;padding:10px 14px;font-size:14px;outline:none;background:var(--bg-elevated);color:var(--text)}
.hint{font-size:12px;color:var(--text-muted);margin-top:4px}
.row{display:flex;gap:12px}
.row .form-group{flex:1}
.btn{display:block;background:var(--primary);color:#fff;border:none;border-radius:8px;padding:12px 28px;font-size:15px;font-weight:600;cursor:pointer;width:100%;margin-top:8px;text-align:center;text-decoration:none}
.btn:hover{background:var(--primary-hover)}
.check-item{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--line-soft);font-size:14px}
.check-item:last-child{border-bottom:none}
.ok{color:#2e7d32;font-weight:600}
.fail{color:#c62828;font-weight:600}
.success-icon{font-size:64px;text-align:center;margin-bottom:16px}.cmd{background:#0f172a;color:#e2e8f0;border-radius:10px;padding:12px 14px;font-size:12px;white-space:pre-wrap;overflow-wrap:anywhere;margin:10px 0}.notice{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:10px;padding:12px 14px;font-size:13px;line-height:1.7;margin:12px 0}
</style></head><body><div class="card"><div class="header"><h1>论坛安装向导</h1><p>当前步骤：<?= htmlspecialchars($title) ?></p></div><div class="steps"><?php $stepOrder=[1,2,3,4,5,6]; foreach($stepOrder as $i): $cls=$i<$step?'done':($i===$step||($i===3&&$step===35)?'active':'pending'); $numLabel=$i<$step?'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:14px;height:14px;"><path d="M20 6 9 17l-5-5"></path></svg>':$i; ?><div class="step <?= $cls ?>"><span class="step-num"><?= $numLabel ?></span><span class="step-label"><?= $stepLabels[$i] ?></span></div><?php endforeach; ?></div><div class="body"><?php if($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if($step===1): ?>
<?php $clayguard = $clayguard ?? ['required'=>false,'loaded'=>false,'version'=>'','install_command'=>'bash tools/install-clayguard-loader.sh','check_command'=>'php tools/clayguard-check.php']; $checks=[['PHP >= 7.4',version_compare(PHP_VERSION,'7.4.0','>='),PHP_VERSION],['PDO 扩展',extension_loaded('pdo'),''],['PDO MySQL',extension_loaded('pdo_mysql'),''],['openssl',extension_loaded('openssl'),''],['curl',extension_loaded('curl'),''],['config/ 可写',is_writable(dirname(__DIR__,4).'/config'),'']]; if(!empty($clayguard['required'])){ $checks[]=['ClayGuard 运行组件',!empty($clayguard['loaded']),!empty($clayguard['loaded']) ? ($clayguard['version'] ?: '已启用') : '未启用']; } $allOk=allChecksPass($checks); ?>
<?php foreach($checks as $c): ?><div class="check-item"><span class="<?= $c[1]?'ok':'fail' ?>"><?php if($c[1]): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:16px;height:16px;"><path d="M20 6 9 17l-5-5"></path></svg><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:16px;height:16px;"><path d="m18 6-12 12"></path><path d="m6 6 12 12"></path></svg><?php endif; ?></span><span><?= htmlspecialchars($c[0]) ?></span><?php if($c[2]): ?><span style="color:var(--text-muted);font-size:12px"><?= htmlspecialchars($c[2]) ?></span><?php endif; ?></div><?php endforeach; ?>
<?php if(!empty($clayguard['required']) && empty($clayguard['loaded'])): ?>
<div class="notice"><strong>需要先启用 ClayGuard 运行组件</strong><br>请在服务器网站根目录执行下面命令，完成后刷新本页面或点击重新检测。</div>
<div class="cmd"><?= htmlspecialchars((string)$clayguard['install_command']) ?></div>
<a class="btn" href="/install.php?step=1">重新检测</a>
<?php elseif($allOk): ?><form method="POST" action="/install.php"><?= csrf_field() ?><input type="hidden" name="step" value="2"><button class="btn" style="margin-top:24px">下一步：验证授权</button></form><?php else: ?><p style="color:#c62828;margin-top:16px;font-size:14px">请先满足上述环境要求再继续。</p><?php endif; ?>
<?php elseif($step===2): ?>
<form method="POST" action="/install.php"><?= csrf_field() ?><input type="hidden" name="step" value="2"><div class="form-group"><label>授权码 License Key</label><input type="text" name="license_key" required placeholder="请输入官方授权码"></div><div class="form-group"><label>授权域名</label><input type="text" name="license_domain" value="<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') ?>" required placeholder="example.com"><p class="hint">系统会自动验证授权，并获取运行所需的授权文件。</p></div><button class="btn">验证授权并获取授权文件</button></form>
<?php elseif($step===3): ?>
<form method="POST" action="/install.php"><?= csrf_field() ?><input type="hidden" name="step" value="3"><div class="row"><div class="form-group"><label>数据库主机</label><input type="text" name="db_host" value="localhost" required></div><div class="form-group" style="max-width:100px"><label>端口</label><input type="text" name="db_port" value="3306" required></div></div><div class="form-group"><label>数据库名</label><input type="text" name="db_name" value="xm_forum" required><p class="hint">不存在会自动创建</p></div><div class="form-group"><label>用户名</label><input type="text" name="db_user" value="root" required></div><div class="form-group"><label>密码（无密码留空）</label><input type="password" name="db_pass" placeholder="无密码留空"></div><button class="btn">测试连接并保存配置</button></form>
<?php elseif($step===35): ?>
<div style="margin-bottom:20px;padding:14px 16px;background:#fef3c7;border:1px solid #f59e0b;border-radius:12px;font-size:14px;color:#92400e">
  <strong style="display:inline-flex;align-items:center;gap:7px"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 auto"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>检测到数据库已有数据</strong>
  <p style="margin:8px 0 0;font-size:13px;color:#a16207">数据库中已存在 <strong><?= count($existingTables) ?></strong> 张表（<?= htmlspecialchars(implode('、', array_slice($existingTables, 0, 5))) ?><?= count($existingTables) > 5 ? '...' : '' ?>）</p>
</div>
<form method="POST" action="/install.php"><?= csrf_field() ?><input type="hidden" name="step" value="35">
  <div style="display:grid;gap:12px;margin-bottom:20px">
    <label style="display:flex;align-items:flex-start;gap:12px;padding:16px;border:2px solid var(--line);border-radius:12px;cursor:pointer;transition:.15s" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--line)'">
      <input type="radio" name="db_mode" value="fresh" style="margin-top:3px;accent-color:var(--primary)">
      <div>
        <div style="font-weight:600;color:var(--text);margin-bottom:4px">全新安装</div>
        <div style="font-size:13px;color:var(--text-soft)">清空数据库所有数据，重新导入完整数据库。适用于全新部署或需要重置的场景。</div>
      </div>
    </label>
    <label style="display:flex;align-items:flex-start;gap:12px;padding:16px;border:2px solid var(--line);border-radius:12px;cursor:pointer;transition:.15s" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--line)'">
      <input type="radio" name="db_mode" value="upgrade" style="margin-top:3px;accent-color:var(--primary)">
      <div>
        <div style="font-weight:600;color:var(--text);margin-bottom:4px">保留旧数据升级</div>
        <div style="font-size:13px;color:var(--text-soft)">保留现有数据，只补齐缺失的表和字段。适用于系统升级或数据迁移场景。</div>
      </div>
    </label>
  </div>
  <button class="btn">确认并继续</button>
</form>
<?php elseif($step===4): ?>
<p style="font-size:14px;color:var(--text-soft);margin-bottom:24px">数据库连接成功！点击下方按钮执行建表迁移。</p><form method="POST" action="/install.php"><?= csrf_field() ?><input type="hidden" name="step" value="4"><input type="hidden" name="run_migration" value="1"><button class="btn">执行数据库迁移</button></form>
<?php elseif($step===5): ?>
<form method="POST" action="/install.php"><?= csrf_field() ?><input type="hidden" name="step" value="5"><div class="form-group"><label>登录账号（用户名）</label><input type="text" name="admin_username" required placeholder="admin"></div><div class="form-group"><label>昵称</label><input type="text" name="admin_nickname" placeholder="留空默认同用户名"></div><div class="form-group"><label>邮箱</label><input type="email" name="admin_email" required placeholder="admin@example.com"></div><div class="form-group"><label>密码（至少 6 位）</label><input type="password" name="admin_password" required></div><button class="btn">创建管理员并完成安装</button></form>
<?php elseif($step===6): ?>
<div class="success-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:54px;height:54px;"><path d="M20 6 9 17l-5-5"></path></svg></div><h2 style="text-align:center;margin-bottom:12px;color:var(--text);">安装完成！</h2><p style="text-align:center;color:var(--text-soft);font-size:14px;margin-bottom:24px">数据库已就绪，管理员账号已创建。</p><a href="/index.php" class="btn">进入论坛首页</a><a href="/admin.php" class="btn" style="background:var(--bg-elevated);color:var(--primary);border:1.5px solid var(--primary);margin-top:10px">进入后台管理</a>
<?php endif; ?></div></div></body></html>