# Build Scripts

- `build-encrypted-dist.php`
  - 主构建脚本：将项目源文件按白名单分类复制/加密到 ClayJM 发行目录
  - 前置条件：SourceGuardian 编码器 license 有效
  - 用法：`php scripts/build-encrypted-dist.php [/path/to/ClayJM] [--dry-run]`
- `build-encryption-whitelist.php`
  - 生成 ClayBBS 商业发行加密排除白名单 JSON
  - 用于后续接入 ionCube / SourceGuardian / 自定义发行管线
