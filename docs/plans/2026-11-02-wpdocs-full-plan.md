# 文派文档（wpdocs）完整重构计划

> **仓**：https://feicode.com/WenPai-org/wpdocs（公开）  
> **工位**：`~/Projects/wpdocs`  
> **对照（勿当真源）**：`CocoBook/cocobook-docs`  
> **后台档位**：wenpai-admin-ui **A**  
> **配套交接**：[`2026-11-02-wpdocs-grok-build-brief.md`](2026-11-02-wpdocs-grok-build-brief.md)  
> **契约**：[`../contracts/wpdocs-v1.md`](../contracts/wpdocs-v1.md)  
> **Admin 规范摘要**：[`../design/admin-ui-a.md`](../design/admin-ui-a.md)

---

## 0. 目标

把品牌站上的「文档桥」从旧插件 `cocobook-docs` 重构为文派产品 **文派文档 `wpdocs`**：

1. 契约清晰：Bind / Redirect / Publish（只读）
2. 后台严格按 wenpai-admin-ui **档 A**
3. 不碰 BookStack 主题壳（阅读 / SEO / 反馈 / 相关）
4. 不自建评论；只存可选 `external_id` 供 Cyberforums 挂件

---

## 1. 命名与身份

| 项 | 值 |
|----|-----|
| 显示名 | 文派文档 |
| 技术 id / text domain | `wpdocs` |
| 主文件 | `wpdocs.php` |
| 命名空间 | `Wpdocs\` |
| Update URI | `https://updates.wenpai.net`（**禁止改**） |
| Package CDN | `downloads.feicode.com`（发版时） |
| 常量 / option / 表前缀 | `WPDOCS_*` / `wpdocs_*` |
| 旧名兼容 | `Compat\Legacy`：`COCOBOOK_DOCS_*` → `WPDOCS_*`，至少一个大版本 |

---

## 2. 边界（铁律）

| 能力 | 本插件 | 明确不做 |
|------|--------|----------|
| Bind | `wp_post_id` ↔ BookStack page + slug + 可选 `external_id` | 不写 BookStack；不嵌论坛 UI |
| Redirect | 旧 WP 路径 → 301/410；dry-run；旧表导入 | 不管 docs 站内 `slug_history` |
| Publish | 只读探测 docs base / 发布树 / sitemap | **正文、Markdown 永不进 `wp_options`** |
| Admin | 档 A：状态 / 绑定 / 跳转 | 无 `APPROVAL.md` 不改 PHP 壳 |
| Theme | — | 不改 `cocobook-docs-theme` CSS/tokens/SEO/反馈/相关 |

主题管 docs 站阅读体验；插件管品牌站侧映射与跳转。

---

## 3. 数据模型

### 3.1 `{$wpdb->prefix}wpdocs_bindings`

| 字段 | 类型意图 | 约束 |
|------|----------|------|
| id | PK | |
| wp_post_id | bigint | UNIQUE |
| docs_page_id | bigint | BookStack page id |
| docs_book_slug | varchar | |
| docs_page_slug | varchar | |
| external_id | varchar nullable | 如 `wpcy:post:11144` |
| source | enum | `manual` / `slug` / `import` |
| status | enum | `active` / `disabled` |
| updated_at | datetime | |

### 3.2 `{$wpdb->prefix}wpdocs_redirects`

| 字段 | 类型意图 | 约束 |
|------|----------|------|
| id | PK | |
| wp_path | varchar | UNIQUE，规范化以 `/` 开头 |
| page_id | bigint nullable | matched 时用；目标用 id 不写死 URL |
| status | enum | `matched` / `pending` / `unmatched` / `gone` |
| hits | bigint | |
| last_hit | datetime nullable | |

**激活**：`dbDelta` 建表。若新 redirects 表为空且存在 `{$prefix}cocobook_redirects`，**一次性导入**（幂等：已有行不覆盖）。

### 3.3 规范化

- `wp_path`：去 host、去 query、去尾斜杠（根 `/` 除外）、统一前导 `/`
- reserved：不劫持 `wp-admin`、`wp-login.php`、`wp-json`、`xmlrpc.php` 等

---

## 4. REST（`/wpdocs/v1`，一律 `manage_options`）

| 方法 | 路径 | 作用 |
|------|------|------|
| GET | `/status` | 版本、表计数、docs base、探测摘要 |
| GET | `/bindings` | 列表（分页参数可后补） |
| POST | `/bindings` | upsert（按 `wp_post_id`） |
| GET | `/bindings/resolve` | `?post_id=` 或 `?page_id=` / slug 反查 |
| GET | `/redirects` | 列表 |
| POST | `/redirects` | upsert（按 `wp_path`） |
| GET | `/redirects/dry-run` | `?path=`；**不写 hits** |

错误：`WP_Error` → JSON；不泄密钥 / token / 真实邮箱。

---

## 5. 模块与里程碑

| 里程碑 | 内容 | 完成判据 |
|--------|------|----------|
| M0 契约 | `docs/contracts/wpdocs-v1.md` + 本计划 + Admin 规范摘要 | 仓内可读 |
| M1 骨架 | `wpdocs.php` + `Plugin` + Legacy + 激活建表 | 可激活 |
| M2 Bind | Repository + REST + 文章列表列 | upsert/resolve 可用 |
| M3 Redirect | Repository + Listener + dry-run + 旧表导入 | 行为对齐旧插件要点 |
| M4 Admin 原型 | `prototypes/wpdocs/` 三页 | 可本地打开；等人签 APPROVAL |
| M5 Admin 换壳 | vendor kit 0.2.3 + `.wenpai-app` 视图 | **仅 APPROVAL 后**；lint 绿 |
| M6 Publish | `HttpSource` 只读探测加深 | status 字段文档化 |
| M7 发版通道 | Update URI / package CDN / zip | 另开会话；不改 URI |

当前起点：M0–M3 骨架已在 `main`（`0.3.0-dev`）；M4 起由 Grok Build 切片推进（见 brief §8）。

---

## 6. 从 `cocobook-docs` 迁移要求

对照仓只读，不把旧仓当重构真源。

| 旧能力 | 新要求 |
|--------|--------|
| `Redirects.php` status / 404 log / page_id 目标 | Listener + 上限 + reserved + 注释行为差 |
| Catalog slug 自动匹配 | Bind `source=slug` 批处理（可第二刀；接口形状先定） |
| Embed / 评论 shortcode | CHANGELOG 写清：兼容别名 **或** 仍走旧插件 |
| 设置页密钥 / base URL | 支持 `WPDOCS_*` 与 `COCOBOOK_DOCS_*` 常量覆盖 |

迁移验收：

1. 激活后 redirects 导入行数与旧表一致（若旧表存在）
2. 同一 `wp_path` dry-run 与旧 301/410 一致
3. 停用旧插件、仅开 wpdocs，关键旧链不断

---

## 7. Admin UI 要求（摘要；细则见 `docs/design/admin-ui-a.md`）

- 档：**A**；kit：**wenpai-admin-ui 0.2.3**
- 页：状态 / 绑定 / 跳转；中文；菜单「文派文档」
- 类名：`.wenpai-app`、`.field`、`main.wenpai-main`、`.foot`、`.mode`
- 禁止：`.form-table` / `.button` 当皮、Emoji 图标、字体图标 CDN、第四个「服务」广告 Tab、无 APPROVAL 改 PHP 模板
- 流程：静态原型 → 人签 `prototypes/wpdocs/APPROVAL.md` → vendor + 换壳 → `wenpai-admin-lint`

---

## 8. 质量门

```bash
find . -name '*.php' -not -path './lib/*' | xargs -n1 php -l
# 有 linuxjoy 时
python3 <linuxjoy>/scripts/wenpai/wenpai-admin-tier.py wpdocs
python3 <linuxjoy>/scripts/wenpai/wenpai-admin-lint.py --plugin-root .
```

- PHP ≥ 8.0
- 不提交密钥；禁止 `rm -rf` 整仓；禁止 force push `main`
- 未验视觉写「未验壳门」；phpcs 绿 ≠ 壳完成

---

## 9. 非本计划

- 生产 BookStack / DNS / CyberCDN 变更
- `#11` prepublish 主题部署
- tokens / DS 主题换皮
- 一次任务改第二只文派插件后台

---

## 10. 执行切片（Grok）

见 brief §8：默认 **G1→G4**；**G5 等人签 APPROVAL**。一路一轮，点名文件，不扫 `~/Projects` 其它仓。
