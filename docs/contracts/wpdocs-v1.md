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
| GET | `/status` | 版本、计数、docs 探测摘要 |
| GET \| POST | `/bindings` | 列表 / upsert |
| GET | `/bindings/resolve` | 按 post 或 page/slug 反查 |
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

## 5. 附录（G3+ 可补）

- Bind 批量 `source=slug` dry-run 请求/响应形状
- Publish status JSON 字段表（sitemap HEAD、发布树 HEAD 等）
