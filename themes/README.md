# ClayBBS Themes

每个主题一个目录：`themes/{slug}`。

必需文件：

- `theme.json`：主题清单
- `assets/css/theme.css`：主题样式，推荐
- `views/`：可选，用同路径覆盖 `app/views/` 的前台视图

推荐 `theme.json`：

```json
{
  "type": "theme",
  "slug": "clay-light",
  "name": "Clay Light",
  "version": "1.0.0",
  "author": "ClayBBS",
  "api_version": "1.0.0",
  "description": "示例主题"
}
```

## 稳定 Theme API

主题模板可使用：

```php
use App\Extension\ThemeApi as ClayTheme;

echo ClayTheme::assetUrl('assets/css/theme.css');
echo ClayTheme::e($title ?? '');
```

常用方法：

- `ClayTheme::active()` 当前主题 slug
- `ClayTheme::view($view)` 解析主题覆盖视图
- `ClayTheme::assetUrl($path, $slug = null)` 生成主题资源 URL
- `ClayTheme::cssTag($path = 'assets/css/theme.css', $slug = null)`
- `ClayTheme::e()` HTML 转义

## 加密发行约定

完整规范见 `docs/extension-manifest-spec.md`。

发行加密核心时，`app/Extension`、`app/core/ThemeManager.php`、`app/helpers/theme.php`、`themes/` 应保持明文或兼容可调用。主题只覆盖公开视图结构和资源，不应依赖核心内部服务实现。

构建命令：

```bash
php scripts/build-encrypted-dist.php /path/to/ClayJM
```
