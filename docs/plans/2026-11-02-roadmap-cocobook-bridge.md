# WPDocs 完善路线图：对齐 CocoBook/BookStack 能力，补齐 WP 文档桥盲点

> 日期：2026-11-02 · 仓：`wpdocs-clone` · 现状真源：`docs/contracts/wpdocs-v1.md`、`docs/plans/2026-11-02-wpdocs-full-plan.md`
> 对照： [CocoBook 能力](https://docs.cocobook.com/features) · [CocoBook API](https://demo.bookstackapp.com/api/docs)

## 0. REST 命名空间（已拍板）

**保持 `wpdocs/v1`，不改为 `wenpai-doc/v1` 或 `cocobook-docs/v1`。**

| 候选 | 结论 | 理由 |
| --- | --- | --- |
| `wpdocs/v1` | **采用** | 与插件技术名、表前缀 `wpdocs_*`、text domain、契约 `docs/contracts/wpdocs-v1.md` 一致 |
| `wenpai-doc/v1` | 不用 | 品牌口语≠技术 slug；易与未存在的 `wenpai-doc` 插件混淆 |
| `cocobook-docs/v1` | 不用作主命名空间 | 旧品牌；若有外部调用方，最多加只读兼容别名，不作为真源 |

若发现生产仍打旧命名空间：再加 `cocobook-docs/v1` → 同回调的兼容注册，并写弃用期；当前无证据则不做。

---

## 1. 产品定位（先钉死，避免做歪）

**文派文档 = 品牌站侧的文档桥**，不是第二套 BookStack。

| 做 | 不做（契约红线） |
| --- | --- |
| Bind：WP post ↔ BS page | 不写 BookStack 正文 / 不改主题壳 |
| Redirect：旧路径 301/410 | 不自建 Shelf/Book/Chapter CPT 知识库 |
| Publish：只读探测（base / sitemap / 发布树） | 不把正文塞进 option；不嵌论坛业务 UI |
| REST `/wpdocs/v1` 管绑定与状态 | 不替代 `cocobook-docs-theme` 阅读/SEO/反馈 |

阅读体验（四级层级、TOC、段落锚点、修订、深色模式、导出）由 **CocoBook/BookStack + 主题** 承担；本插件负责 **连接、跳转、诊断、迁移兼容**。

现状代码面：`Bind` / `Redirect` / `Publish` / `Rest` / `Admin` / `Compat\Legacy`。无 CPT。Token 常量 `WPDOCS_*`（兼容 `COCOBOOK_DOCS_*`）。

---

## 2. CocoBook / BookStack 能力 → 桥侧应对

| CocoBook / BS 能力 | API / 机制 | WPDocs 应对（在红线内） |
| --- | --- | --- |
| Shelf→Book→Chapter→Page | `/api/shelves|books|chapters|pages` | **只读目录**：用 Token 拉 books contents / search，喂 Bind 候选与 Studio 树预览；不落库正文 |
| 全文搜索 | `GET /api/search?query=` | 绑定向导：按 slug/标题调 search；替换目前「模糊匹配 stub 恒空」 |
| 页面 HTML/Markdown | pages read：`raw_html` vs `html` | Bind 校验页存在；展示标题/slug；**禁止**缓存整页 HTML 到 WP |
| 标签 name/value | tags on entities | 绑定筛选条件（可选 meta）；不做标签权威源 |
| 附件 / 图库 | attachments, image-gallery | 诊断：绑定页附件数；断链告警（P2） |
| 导出 ZIP/MD/PDF | `.../export/*`, `/api/imports` | **不做站内导出**；Studio 链到 docs 导出或运维脚本 |
| 内容级权限 | content-permissions | 映射提示：私密页绑定失败原因（401/404） |
| 修订 / 评论 / 段落锚点 | revisions, comments, bkmrk | 主题职责；桥最多存 `external_id` 给评论挂件 |
| Webhooks | 事件 POST | P2：page_update → 刷新绑定校验 / 发布树缓存 |
| 系统信息 | `GET /api/system` | Publish/Studio：version、base_url 对齐 |
| 速率 180/min、Token 头 | `Authorization: Token id:secret` | Http Client 统一；429 退避；密钥不进 git |

---

## 3. WordPress 侧盲点（文档项目场景）

| WP 盲点 | 对「品牌站↔文档站」的伤害 | 本插件补法 |
| --- | --- | --- |
| 文章与文档站两套 URL | SEO/外链碎片、404 | Redirect 表 + unmatched 上限日志；matched 只存 `page_id`，URL 运行时解析 |
| 无稳定「文档页 ID」语义 | 运营靠手抄链接 | Bind 表强制 `docs_page_id` + slug；slug 变更可探测 |
| Cron/远程不可靠 | 「看起来连着其实挂了」 | Publish HEAD 探测 → status JSON；Studio empty/ok/bad |
| REST 调用方自备目录 | 易过期 | P1：服务端只读拉取 BS catalog（短缓存），`/suggest` 不再 stub |
| option 塞大 JSON | 撑爆、含敏感 | 契约已禁正文进 option；状态只留摘要 |
| 多插件抢 404 | 跳转打架 | reserved 路径黑名单；决策顺序文档化 |
| 密钥进库明文 | 泄露 | 常量优先；设置页掩码；永不写 changelog |
| 主题换了文档版式全毁 | 品牌站误当知识库 | Help 文案强调：阅读在 docs 站；WP 只桥接 |

---

## 4. 分期（贴合现仓，可执行）

### P0 — 桥可靠（本周可验）

1. **BookStack Http Client**（`src/Http/`）：Token 头、timeout、401/404/429 分类；`system` ping + `pages/{id}` + `search`
2. **Studio/Status**：用 Client 结果填 ok/bad；保留现有 sitemap/tree HEAD
3. **Bind 校验**：保存前 `GET /api/pages/{id}`；失败不写脏行
4. **契约测试**：Client mock；Probe 已有单测保持绿
5. 扫清 Redirect TODO：对照旧仓 reserved / 404 存储（`tests/redirect-vs-cocobook-docs.md`）

### P1 — 绑定体验与目录真源

1. `/wpdocs/v1` suggest：调用 `/api/search`（及可选 books contents），去掉空 stub — **已**
2. 短 TTL 缓存发布树（object cache / transient），禁止整页 HTML — **已**（`Catalog::index_ttl`：索引 ≤300s；正文仍走 `page_ttl`/文件缓存，不进 option）
3. 后台绑定表：显示远端 title、broken / 别名已变 — **已**（`Bind\RemoteMeta`）
4. slug 漂移任务：周期抽检 binding → BS slug 是否变 — **已**（日检 `Bind\DriftCheck` + 概览告警；列表「别名已变」）

### P2 — 事件与运维

1. Webhook 接收（校验签名/共享密钥）→ 使缓存失效 / 标记 binding stale
2. 附件/权限失败可观测（Studio 列表）
3. 导出运维手册：如何用 BS ZIP；WPDocs 不实现第二套导出器
4. Application Passwords 调 REST 的说明 + 能力矩阵

### P3 — 工程化

1. Contract tests 对 BS demo 或录制 fixtures（VCR）
2. 可选：从 Git Markdown **运维导入 BS**（脚本仓，非本插件）补「内容在 DB、难 PR」短板
3. 指标：绑定覆盖率、404 unmatched 趋势、探测失败率

---

## 5. 软件工程约束（持续）

- 领域边界：`Bind` / `Redirect` / `Publish` / `Http\BookStackClient`；Admin 与 Rest 只编排
- 同步策略：**只读 + 校验**；若未来要双向写 BS，必须新开契约版本，不得偷偷在 v1 写
- 幂等：redirects legacy 导入、bindings UNIQUE(`wp_post_id`)
- 可测：纯函数 Path/Decision；Http 可注入
- UI：wenpai Tier A；主栏模块 `gap:20`（已落 `assets/admin/admin.css`）

---

## 6. 验收

1. 配置 base + Token → Studio **ok**，能显示 BS `system.version`
2. 绑定一页：非法 id 被拒；合法 id 显示远端标题
3. suggest 输入关键词返回真实候选（非空 stub）
4. 断开 docs 站 → status **bad**，无 fatal、无 option 膨胀
5. 旧 `cocobook_redirects` 空表导入仍幂等；reserved 路径不跳转

---

## 7. 立刻下一步

1. ~~实现 `Http\BookStackClient` + Status 接入~~（已）
2. ~~Bind 保存校验~~（已）
3. ~~打通 search suggest~~（`/search` + `/suggest` + 绑定页 UI）
4. ~~补 `redirect-vs-cocobook-docs` 旧仓对照 TODO~~（已）
5. P1 余量已收口。下一步进 **P2**（Webhook 使缓存失效已有雏形；附件/权限可观测；导出手册；App Passwords 说明）。
