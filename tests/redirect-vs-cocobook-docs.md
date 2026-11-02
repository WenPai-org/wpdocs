# Redirect vs cocobook-docs（契约对照，本轮未扫旧仓）

本轮按 `docs/contracts/wpdocs-v1.md` 与 brief §6/§8 实现。未 clone、未扫 `CocoBook/cocobook-docs`。

| 项 | wpdocs（本轮） | 旧插件（TODO：对照 `src/Redirects.php`） |
|----|----------------|------------------------------------------|
| 表 | `{$prefix}wpdocs_redirects`；激活时空表一次性导入 `cocobook_redirects` | `{$prefix}cocobook_redirects` |
| 404 日志 | 插入 `status=unmatched` `source=404`；新行上限 `Repository::UNMATCHED_LOG_CAP`（默认 500，`WPDOCS_UNMATCHED_LOG_CAP` 可覆盖） | TODO：是否写 option `cocobook_docs_404`、有无上限 |
| reserved | `wp-admin`、`wp-login.php`、`wp-json`、`xmlrpc.php` 及 wp 入口/内容目录；不 301/410、不记 404、upsert 拒绝 | TODO：精确名单 |
| matched 目标 | 只存 `page_id`；运行时 Bind slug 或 `{base}/link/{page_id}` | TODO：是否曾写死绝对 URL |
| dry-run | `GET /wpdocs/v1/redirects/dry-run?path=`；`hits_written=false`；不插 404 行；响应带 `page_id` | TODO：旧插件有无等价预览 |
| 410 / 301 | `gone` → 410 并 `hit`；`matched`+`page_id` → 301 并 `hit` | 验收：同一 `wp_path` 应与旧 301/410 一致 |

有旧仓时只读打开 `Redirects.php`，把上表 TODO 勾掉即可；不要把旧仓当重构真源。
