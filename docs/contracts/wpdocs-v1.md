# wpdocs v1 契约

产品名：文派文档。技术 id：`wpdocs`。品牌站侧插件真源：`WenPai-org/wpdocs`（公开）。

配套：[`../plans/2026-11-02-wpdocs-full-plan.md`](../plans/2026-11-02-wpdocs-full-plan.md)、[`../design/admin-ui-a.md`](../design/admin-ui-a.md)。

---

## 1. 边界

| 能力 | 所有者 | 不做 |
|------|--------|------|
| Bind | 本插件表 `wpdocs_bindings` | 不写 BookStack；不嵌 Cyberforums 业务 UI |
| Redirect | 本插件表 `wpdocs_redirects` | 不管 docs 站内 `slug_history` |
| Publish | 只读探测 docs / 发布树 / sitemap | 正文、Markdown **永不**进 `wp_options` |
| 阅读壳 / SEO / 反馈 / 相关 | `cocobook-docs-theme` | 本插件不改主题 |
| 评论挂件 | Cyberforums + 可选 `external_id` | 本插件不自建评论 |

---

## 2. 表

### `{$wpdb->prefix}wpdocs_bindings`

- `wp_post_id` UNIQUE
- `docs_page_id`, `docs_book_slug`, `docs_page_slug`
- `external_id` 可选（例 `wpcy:post:11144`）
- `source` ∈ `manual` | `slug` | `import`
- `status` ∈ `active` | `disabled`

### `{$wpdb->prefix}wpdocs_redirects`

- `wp_path` UNIQUE，规范化 `/path`
- `page_id`（matched 时；目标用 id，不写死绝对 URL）
- `status` ∈ `matched` | `pending` | `unmatched` | `gone`
- `hits`, `last_hit`

激活建表；新 redirects 空且存在 `{$prefix}cocobook_redirects` → **一次性导入**。

---

## 3. REST

命名空间 `/wpdocs/v1`，权限一律 `manage_options`：

| 方法 | 路径 | 作用 |
|------|------|------|
| GET | `/status` | 版本、计数、docs / sitemap / 发布树 HEAD 探测摘要 |
| GET \| POST | `/bindings` | 列表 / upsert |
| GET | `/bindings/resolve` | 按 post 或 page/slug 反查 |
| POST | `/bindings/resolve-batch` | `post_name`↔slug 批量草稿；默认 dry-run；`apply=true` 才 upsert |
| GET \| POST | `/redirects` | 列表 / upsert |
| GET | `/redirects/dry-run?path=` | 预览匹配；**不写 hits** |

错误用 `WP_Error` 习惯；JSON 不泄密钥。

---

## 4. 兼容

`Compat\Legacy`：

- 常量 `COCOBOOK_DOCS_*` → `WPDOCS_*`
- option 双读（新优先）；写入策略见实现注释
- 激活时 redirects 空表 ← `cocobook_redirects` 导入

Update URI 恒为 `https://updates.wenpai.net`。

---

## 5. 附录（G3+）

- Bind 批量 `source=slug` dry-run 请求/响应形状（见下）
- Publish status JSON 字段表（见下）

### Bind 批量 resolve（G3）

`POST /wpdocs/v1/bindings/resolve-batch`（`manage_options`）。默认 **dry-run**：不写 `wpdocs_bindings`。仅当 `apply=true`（布尔 `true` / 整数 `1` / 字符串 `"1"` / `"true"`）才对 `action=bind` 的草稿 `upsert`，且 `source=slug`。其它 `apply` 值（缺省、`false`、`0`、`"yes"`）一律不写库。

本轮 **不** 向 BookStack 拉目录；`pages` 由调用方传入。模糊匹配（前缀 / Levenshtein / 标题）为 stub，恒返回空候选。`wp_post_id` 已有行 → `skip_existing`（UNIQUE，不覆盖 `manual` / `import`）。

单次最多处理 `ResolveDraft::MAX_ITEMS`（500）条 posts 与 500 条 pages，超出部分丢弃，`summary.capped=true`。

#### 请求 JSON

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `posts` | array | 否（缺省 `[]`） | 待匹配 WP 文章 |
| `posts[].wp_post_id` | int | 是（否则 `invalid`） | 别名 `ID` |
| `posts[].post_name` | string | 是（否则 `invalid`） | 与 `docs_page_slug` 比；别名 `slug`。REST 若只给 id 会 `get_post()` 补 `post_name` |
| `posts[].post_type` | string | 否 | 缺省 `post` |
| `posts[].book_slug` | string | 否 | 只在该书内匹配；别名 `docs_book_slug`；覆盖请求级 `book_slug` |
| `posts[].external_id` | string | 否 | 原样写入草稿 |
| `pages` | array | 否（缺省 `[]`） | 文档站目录快照 |
| `pages[].docs_page_id` | int | 建议 | 别名 `page_id` |
| `pages[].docs_book_slug` | string | 否 | 别名 `book_slug` |
| `pages[].docs_page_slug` | string | 是（空则忽略该行） | 别名 `page_slug` / `slug` |
| `existing` | array | 否 | `wp_post_id` 整数列表，或含 `wp_post_id` 的对象。REST **省略**该键时从表补齐；显式 `[]` 表示忽略已有行 |
| `book_slug` | string | 否 | 全批书过滤 |
| `apply` | bool | 否 | 缺省 `false`（dry-run） |

#### 响应 JSON

| 字段 | 类型 | 说明 |
|------|------|------|
| `source` | string | 恒 `slug` |
| `apply` | bool | 是否按写库意图解析 |
| `applied` | bool | 是否至少 upsert 了一行。`ResolveDraft::plan()` 恒为 `false` |
| `applied_ids` | int[] | 写入的 binding `id`；dry-run 为 `[]` |
| `book_slug` | string | 规范化后的请求级书过滤 |
| `summary.posts` | int | 实际处理的 post 数 |
| `summary.pages` | int | 去重后的 page 数 |
| `summary.bind` / `ambiguous` / `unmatched` / `skip_existing` / `invalid` | int | 按 `action` 计数 |
| `summary.capped` | bool | 是否因 500 上限截断 |
| `items[]` | array | 与 `posts` 顺序对应 |

`items[]` 每项：

| 字段 | 类型 | 说明 |
|------|------|------|
| `wp_post_id` | int | |
| `post_name` | string | 规范化后 |
| `post_type` | string | |
| `book_slug` | string | 实际用于过滤的书 |
| `match` | string | `exact` \| `book_page` \| `ambiguous` \| `none` |
| `action` | string | `bind` \| `skip_existing` \| `unmatched` \| `ambiguous` \| `invalid` |
| `reason` | string | `exact` / `book_page` / `already_bound` / `multiple_candidates` / `no_candidate` / `missing_wp_post_id` / `missing_post_name` |
| `candidates` | array | `{docs_page_id, docs_book_slug, docs_page_slug}` |
| `draft` | object\|null | `action=bind`（及 `skip_existing` 若已能匹配）时的 upsert 形状；`source=slug` `status=active` |

#### 匹配规则

1. 规范化：小写、空白→`-`、保留 `a-z0-9/-`（不调用 `sanitize_title`，便于无 WP 单测）
2. `exact`：`post_name === docs_page_slug`（可加 `book_slug` 过滤）
3. 无 exact 时 `book_page`：`post_name === "{book}/{page}"` 或 `"{book}-{page}"`
4. 同阶多于 1 条 → `ambiguous`，不写库
5. 已有 `wp_post_id` 行 → `skip_existing`
6. fuzzy stub：不产生候选

#### dry-run

`GET /bindings/resolve` 仍只反查已有行。批量草稿走 `POST /bindings/resolve-batch`。`plan()` 与 `Slug` **不**调用 `Repository::upsert` / `apply_slug_drafts`。

### Redirect dry-run / 404 日志（G2 勘误）

- `GET /redirects/dry-run?path=` **不写** `hits` / `last_hit`，**不插入** unmatched 404 行。响应含 `hits_written: false`。matched 带 `page_id`（表内不存绝对 URL）；`target` 若出现则为运行时由 `page_id` 解析。
- 404 未匹配：`status=unmatched` `source=404` 新行上限为 `WPDOCS_UNMATCHED_LOG_CAP`（缺省 `Repository::UNMATCHED_LOG_CAP` = 500；`0` 关闭新插入）。已有 unmatched/pending 行可增加 hits。
- reserved（`/wp-admin`、`/wp-login.php`、`/wp-json`、`/xmlrpc.php` 等）不 301/410、不记 404、upsert 拒绝。

### Publish status（G4）

`GET /wpdocs/v1/status`（`manage_options`）。发布探测只读：HTTP **HEAD**，超时 `Probe::TIMEOUT_SEC`（3 秒），跟随最多 2 次跳转。失败写入对应 probe 的 `error` 且 `code=0`，**不抛** REST fatal。响应 **不含** body / markdown / content；**禁止**把探测正文写入 `wp_options`。

Sitemap 路径相对 `base_url`：`/cocobook/sitemap.xml`。发布树默认 `{base_url}/books`；`WPDOCS_TREE_URL` 优先，否则 `wpdocs_settings.tree_url`；该设置键存在且为空字符串则跳过 tree 探测（`skipped=true`，不发请求）。

`publish.ok` **只跟** `home.ok`（docs base 是否 2xx）。sitemap / tree 失败不把整段标为不健康。

#### 响应 JSON

| 字段 | 类型 | 说明 |
|------|------|------|
| `version` | string | `WPDOCS_VERSION` |
| `bindings` | int | `wpdocs_bindings` 行数 |
| `redirects` | int | `wpdocs_redirects` 行数 |
| `publish` | object | 探测摘要（见下） |

`publish`：

| 字段 | 类型 | 说明 |
|------|------|------|
| `base_url` | string | 文档站根，无尾斜杠（缺省 `https://docs.cocobook.com`） |
| `tree_url` | string | 发布树 HEAD 目标；空则 tree 为 skipped |
| `timeout` | int | 单次探测秒数，恒 `3` |
| `home` | object | HEAD `{base_url}/` |
| `sitemap` | object | HEAD `{base_url}/cocobook/sitemap.xml` |
| `tree` | object | HEAD `tree_url` |
| `ok` | bool | 等于 `home.ok` |
| `error` | string | 组装层 fatal；各 probe 自己的错误在 `*.error`。无 fatal 时为 `""` |

`home` / `sitemap` / `tree` 每项（无 body 字段）：

| 字段 | 类型 | 说明 |
|------|------|------|
| `url` | string | 实际请求 URL；skipped 且无目标时为 `""` |
| `method` | string | 恒 `HEAD` |
| `code` | int | HTTP 状态；传输失败或 skipped 为 `0` |
| `ms` | int | 耗时毫秒；skipped 为 `0` |
| `error` | string | `WP_Error` / 异常信息；有 HTTP 响应时为 `""` |
| `ok` | bool | `code` ∈ [200, 300) 且非 skipped |
| `skipped` | bool | URL 为空，未发请求 |
