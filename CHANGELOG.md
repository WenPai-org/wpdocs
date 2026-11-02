# Changelog

## 0.3.0-dev — 2026-11-02

- Admin：vendor wenpai-admin-ui **0.2.3**，三页换 `.wenpai-app` 壳（需 `prototypes/wpdocs/APPROVAL.md`）。
- REST upsert 同时接受 JSON 与表单参数；`wp_post_id<=0` 拒绝写入。
- Studio 联测站：`~/Studio/wpdocs-dev`（sandbox · http://localhost:8899），插件软链 `wp-content/plugins/wpdocs` → `~/Projects/wpdocs`。
- 产品名「文派文档」，技术 id `wpdocs`（FeiCode `WenPai-org/wpdocs`）。
- 契约见 `docs/contracts/wpdocs-v1.md`；完整计划见 `docs/plans/2026-11-02-wpdocs-full-plan.md`。
- 新表 `{$wpdb->prefix}wpdocs_bindings` / `wpdocs_redirects`；旧 `cocobook_*` 经 `Compat\Legacy` 只读别名。
- REST 命名空间 `/wpdocs/v1`。
- 后台按 wenpai-admin-ui；原 `cocobook-docs` 仓保留作迁移对照，本仓为重构真源。
- Redirect（相对旧 `cocobook-docs`）：404/未匹配写入 `wpdocs_redirects` 且有上限（默认 500，`WPDOCS_UNMATCHED_LOG_CAP`）；`wp-admin` / `wp-login.php` / `wp-json` / `xmlrpc.php` 等 reserved 不跳转、不记日志；dry-run 不写 `hits`；matched 只存 `page_id`，URL 运行时解析。旧 option `cocobook_docs_404` 不再作为写入目标（见 `tests/redirect-vs-cocobook-docs.md`）。
- Bind：`POST /wpdocs/v1/bindings/resolve-batch` 按 `post_name`↔`docs_page_slug` 批量草稿（默认 dry-run，`apply=true` 才写入，`source=slug`）。
- Publish：`GET /status` 对 docs base、`/cocobook/sitemap.xml`、发布树做短超时 HEAD 探测；失败写入 JSON，不抛 fatal，不把正文写入 option。
