# Redirect vs cocobook-docs（契约对照）

对照源：`~/.cbd-e2e-backups/plugin-cocobook-docs-20261028-112551/src/Redirects.php`（2026-11-02 勾完）。旧仓不是重构真源。

| 项 | wpdocs | 旧插件 |
|----|--------|--------|
| 表 | `{$prefix}wpdocs_redirects`；激活时空表一次性导入 `cocobook_redirects` | `{$prefix}cocobook_redirects` |
| 404 日志 | 插入 `status=unmatched` `source=404`；新行上限 `Repository::UNMATCHED_LOG_CAP`（默认 500）；已有 unmatched/pending 则 `hit++` | option `cocobook_docs_404`：`path => [hits, last]`，上限 **200**；跳过 `/wp-*` 与常见静态后缀 |
| 404 option 迁移 | 激活时一次性把 `cocobook_docs_404` / `wpdocs_404` 导入 unmatched 行（`wpdocs_404_imported` 旗标） | — |
| reserved | 显式名单：`/wp-admin`、`/wp-login.php`、`/wp-json`、`/xmlrpc.php`、若干 wp 入口、`/wp-content`、`/wp-includes`；不 301/410、不记 404、upsert 拒绝 | 无独立名单；404 日志用 `/wp-*` 前缀 + 扩展名黑名单；`is_admin` / REST / cron 早退 |
| matched 目标 | 只存 `page_id`；运行时 Bind slug 或 `{base}/link/{page_id}` | 同：只存 `page_id`；catalog 拼 `/books/{book}/page/{slug}`，否则 `/link/{id}` |
| dry-run | `GET /wpdocs/v1/redirects/dry-run?path=`；`hits_written=false`；不插 404 行；响应带 `page_id` | **无**等价 REST 预览 |
| 410 / 301 | `gone` → 410 并 `hit`；`matched`+`page_id` → 301 并 `hit` | 同 |
