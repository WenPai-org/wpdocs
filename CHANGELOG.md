# Changelog

## 0.3.0-dev

- 恢复旧 cocobook-docs 设置能力：连接（Token）、书目/站点标签、关闭/试点/全部、评论 BFF、缓存 TTL、测试连接/立即同步、Webhook 刷新
- 前台接管（Catalog + Frontend body swap）与评论挂件（shortcode/block）迁入
- 跳转受「启用旧链接跳转」开关约束；顶栏新增「设置」Tab

## 0.3.0-dev (unreleased)

### Added
- `Wpdocs\Http\BookStackClient`：只读 Token 客户端（system / page / search）
- `GET /wpdocs/v1/search?q=`：BookStack 搜索代理
- Status `publish.api`：Token 已配时 ping `/api/system`；Studio 状态行展示
- Bind 保存（后台 + REST）：有 Token 且 `docs_page_id>0` 时先校验远端页
- `GET /wpdocs/v1/suggest?q=`：搜索文档页候选；绑定页「搜索文档页」一点即填
- 激活时一次性导入旧 option `cocobook_docs_404` → unmatched 行

### Changed
- REST 命名空间保持 `wpdocs/v1`（不用 wenpai-doc / cocobook-docs）
- 主栏模块间距 `gap: 20px`
- Admin `.field` 回到 kit `280px|1fr`（`field-label` + `field-ctl`）；去掉产品侧单列堆叠覆盖
- Status 探测结果 transient 缓存 5 分钟；`?refresh=1` / 保存地址强制重探；多路 HEAD 并行
- `tests/redirect-vs-cocobook-docs.md` 对照旧仓勾完；Redirect TODO 注释清除


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
