# Changelog

## Unreleased — Settings schema 迁移（kit 0.10.3）

- vendor wenpai-admin-ui **0.10.2 → 0.10.3**。
- 设置页接入 `Wenpai_Admin_Settings` schema（`src/Admin/Schema.php`）：16 个叶子字段走 `field_html()`，声明 type/label/desc/mode/group/depends_on；`content_mode`/`mode`/`comments` 等自定义控件仍注册进 schema 供 depends_on 与服务端初始状态。
- **连接字段合并**：CocoBook 地址 / Token ID / Token Secret 原来在「保留正文」「文档站读取」两面板各渲染一遍（同名输入靠 disable 防重复提交），现在合并为一组，keep/cocobook 共用，git 模式不显示。
- 表单字段名前缀 `wpdocs[...]` → `wpdocs_settings[...]`（对齐 option 名）；`Settings::save()` 新增 `__wenpai_clear` 密钥清除标记，兼容旧 `*_clear` checkbox。
- 密钥字段换 kit `secret` 类型：自带「已保存」态、「显示」开关、「清除已保存的值」。
- wp-config 常量锁定字段保留 `<code>` 展示（包在带 mode/group 属性的包装里，分组显隐不受影响）。
- 自定义件保留：主选择/读取范围 choice 卡、书目 checkgroup（远程数据）、试点/文章类型 CSV、Git 仓库组合字段、状态行、开关行。
- 验证：`tests/run.sh` 全绿；lint PASS findings=0；Studio wpdocs-dev 实测——三内容源切换显隐正确、分组 section 显隐正确、保存往返 site_tag/git_repositories 正确、简单+keep 连接块无重复输入、零 JS 错误。

## Unreleased — Admin UI kit 0.9.0 + IA 收敛

- vendor wenpai-admin-ui **0.6.2 → 0.9.0**：书目 `chk-grid` → kit `checkgroup`；文档页搜索/试解析/别名预览 → kit `suggest` + `preview`；跳转筛选 → `list_bar`；表格行操作 → `row_actions`；帮助页 → `help_page` 骨架；内容源显隐 → kit `depends_on`。
- 简单/高级与分组改用 kit 0.9 合同：`mode()`/`mode_attrs()`/`section()`/`group_attrs()` + `[data-wenpai-groups]`，私有 `data-for-mode`/`data-adv-group` 全部移除；同步页 Webhook 改 `code_inline()`。
- 概览（比对稿 `prototypes/wpdocs/compare-ia.html` 已审）：仍两卡；「探测与健康」只留探测失败率结论行，四条端点明细收进 `details`，**有异常自动展开**。
- 工具·同步：CocoBook / Forgejo Webhook 改为行内 code + kit `copy_button`，hint 合并为一条。
- 设置简单模式收敛为「主选择 + 连接状态行 + 开关 + 保存」，连接明细全部进高级；高级模式按内容源分 subnav 组（文档站读取：连接/书目与范围/评论/同步与缓存；Git：仓库/同步与缓存；保留正文：连接），非当前组 `hidden` 但值照常提交。
- 主选择字段 `is-hero` 通栏（简单/高级一致）。
- 性能：设置页书目列表走 `Client::remember` 缓存（TTL 1h，保存设置时失效）；同步页删除未使用的 books 远程请求。后台各页 ≈ WP 基线。
- 左侧菜单名「文派文档」→「文档」（浏览器标题不变）。

## Unreleased — Admin UI baseline

- vendor 精确的 wenpai-admin-ui **0.3.2** tag；菜单迁到「设置 → 文派文档」，新链接统一 `options-general.php?page=wpdocs&tab=...`，保留旧深链回调与文档标题。
- 概览 / 设置 / 工具 / 帮助共用一个页面路由；绑定、跳转、同步仍为工具内 subnav。设置保存与行动作保留 `updated/error/mode/filter` 状态，新增 `tests/AdminUrlTest.php`。
- 保留并整理设置页现有修改：主选择单列、书目可滚动多选列表、预览整框状态色；后台颜色全部改走 token，字重 400，移除新增阴影。
- 健康提示不再暴露 cURL / 主机错误详情；页脚和清除密钥文案统一中文。
- 验收：`tests/run.sh` 全绿；Admin UI lint + `--strict-colors` 通过；Chrome 九个新旧入口 200、360px 无横向溢出、无 JS 错误；原值保存前后 option 完全一致。helper 迁移仍待包含 `Wenpai_Admin_UI` 的正式 kit 版本。

## 0.3.0 — 2026-11-03

### 三种内容模式 + Git

- 主选择「内容从哪来」三选一：**保留正文** / **文档站读取** / **Git 写入**
- 设置：主选择 → 该源连接 → 旁路能力（旧链跳转）；Git 仓库用 `owner/name` 一行；工具子页仍是绑定/跳转/同步（同步按源切换）
- 统一评论仅「文档站读取」；整合 Git it Write；保留正文下可一次性从 CocoBook 导入；旧 `mode=off` 迁移为保留正文
- UI 命名收口：产品侧说 CocoBook，模式名钉死「读取 / 写入 / 保留」
- 设置对齐档 A：简单页一张卡（主选择 + 该源连接 + 旁路）；保存设置；导入用 `.wenpai-gate`
- 保存不再因隐藏面板误清书目 / Git 仓库；概览 `.next` 按内容源给出
- 工具 → 绑定：列表「远端」列显示 CocoBook 页标题；404→远端失效、别名漂移→别名已变（`Bind\RemoteMeta`，短 TTL transient，不存正文）
- 发布树索引：`Catalog` 用短 TTL（默认 ≤300s），`page_ttl=0` 不再让索引永不过期；页正文缓存仍跟 `page_ttl`，不进 option
- 日检抽查绑定远端：`Bind\DriftCheck`（失效/别名已变摘要进 transient；概览告警）
- P2 运维：Webhook 同步页给完整 URL 并 bust `RemoteMeta`；远端「无权限」+ 附件数；`docs/ops/wpdocs-ops.md`（导出 / App Passwords REST 矩阵）；帮助页运维节
- P3：`Metrics`（绑定覆盖 / unmatched / 探测失败率，14 日环）进 `GET /status` 与概览；`tests/fixtures/bookstack` VCR 契约测
- 试点列表为空时覆盖率分母回退到已发布文章数；契约补 `metrics` 字段；Studio `wpdocs-dev` 对照路线图 §6 验收

## 0.3.0-dev (UI)

- 档 A 三屏：概览 / 设置 / 工具（绑定·跳转·同步为工具内 subnav）
- 设置页：choice 模式卡、kit toggle、chk-grid 书目、渐进披露；同步迁到工具
- 概览只答「现在怎样」，地址表单迁入设置


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
