# 文派文档（wpdocs）· Grok Build 开发交接包

> **仓（公开）**：https://feicode.com/WenPai-org/wpdocs  
> **工位**：`~/Projects/wpdocs`（Mac；源码 host 登记 wenpai）  
> **对照仓（勿当真源）**：`CocoBook/cocobook-docs`  
> **档位**：wenpai-admin-ui **A**（已写入 linuxjoy `docs/design/wenpai-admin-ui/tiers.yml`）  
> **日期**：2026-11-02  
> **派工**：Grok Build CLI（`grok-4.6`），一任务一路

---

## 0. 一句话

把品牌站上的「文档桥」从 `cocobook-docs` **重构为文派文档 `wpdocs`**：契约清晰的 Bind / Redirect / Publish 只读诊断；后台严格按 **wenpai-admin-ui 档 A**；不碰 BookStack 主题壳、不嵌 Cyberforums 业务 UI。

---

## 1. 产品与命名

| 项 | 值 |
|----|-----|
| 产品显示名 | 文派文档 |
| 技术 id / text domain | `wpdocs` |
| 主文件 | `wpdocs.php`（**不要改 Plugin Name 文件头以外的 Update URI**） |
| Update URI | `https://updates.wenpai.net`（禁止改） |
| Package CDN | `downloads.feicode.com`（发版时，非本任务） |
| FeiCode | `WenPai-org/wpdocs` **public** |
| 旧插件 | `CocoBook/cocobook-docs`：对照与迁移源，不再当重构真源 |

常量 / option / 表：新名 `WPDOCS_*` / `wpdocs_*`；旧名经 `Compat\Legacy` 兼容至少一个大版本。

---

## 2. 范围与非目标

### 做

1. **Bind**：`wp_post_id` ↔ BookStack `page_id` + book/page slug + 可选 `external_id`（评论挂件用）
2. **Redirect**：旧 WP 路径 → 301 到当前 docs URL，或 410；dry-run；可从 `{$prefix}cocobook_redirects` 导入
3. **Publish**：只读探测 docs 站 / 发布树健康（禁止把正文写入 `option`）
4. **REST** `/wpdocs/v1/*`（`manage_options`）
5. **Admin**：档 A 整页壳（见 §5）；状态 / 绑定 / 跳转三页
6. **测试**：至少 PHP lint + 一张「空站激活建表」说明；有条件补 Studio/Playground 步骤

### 不做

- 改 `cocobook-docs-theme` CSS / tokens / SEO / 反馈 / 相关阅读
- 自建评论系统；评论只引用 `external_id` / Cyberforums 挂件
- 改注册商 / DNS / 生产 BookStack 数据
- 一次任务改第二只文派插件后台
- 用 `.form-table` / `.button` 当皮；跟 `PLUGIN-UI-DESIGN.md` 的 wp-components
- 无 `APPROVAL.md`（人签）就大改后台 Blade/PHP 模板结构（见 §5.4）

---

## 3. 契约（真源）

先读仓内：

- `docs/contracts/wpdocs-v1.md`
- `docs/plans/2026-11-02-wpdocs-full-plan.md`
- `docs/design/admin-ui-a.md`（档 A 自洽规范；无 linuxjoy 时以此为准）

### 3.1 表

**`{$wpdb->prefix}wpdocs_bindings`**

| 字段 | 说明 |
|------|------|
| wp_post_id | UNIQUE |
| docs_page_id / docs_book_slug / docs_page_slug | 目标页 |
| external_id | 可选，如 `wpcy:post:11144` |
| source | manual / slug / import |
| status | active / disabled |

**`{$wpdb->prefix}wpdocs_redirects`**

| 字段 | 说明 |
|------|------|
| wp_path | UNIQUE，规范化 `/path` |
| page_id | BookStack page id（matched） |
| status | matched / pending / unmatched / gone |
| hits / last_hit | 命中 |

激活：建表；若新 redirects 表为空且存在 `cocobook_redirects`，**一次性导入**。

### 3.2 REST（稳定）

| 方法 | 路径 | 作用 |
|------|------|------|
| GET | `/wpdocs/v1/status` | 版本、计数、docs 探测 |
| GET/POST | `/wpdocs/v1/bindings` | 列表 / upsert |
| GET | `/wpdocs/v1/bindings/resolve` | 按 post 或 page 反查 |
| GET/POST | `/wpdocs/v1/redirects` | 列表 / upsert |
| GET | `/wpdocs/v1/redirects/dry-run?path=` | 不写 hits |

权限：一律 `manage_options`。错误用 WP_Error 习惯，JSON 不泄密钥。

### 3.3 与发布树

- 发布树 / BookStack：**只读**
- 正文、Markdown **永不**进 `wp_options`
- 映射与跳转只存本插件表

### 3.4 与主题

| 主题 | 插件 |
|------|------|
| docs 站内 slug_history、SEO、反馈、相关 | 品牌站 Bind / Redirect |
| `/cocobook/*` 管理页 | `wp-admin` 文派文档菜单 |

---

## 4. 当前仓状态（起点）

`main` 已有 `0.3.0-dev` 骨架：

- `wpdocs.php`、`src/Plugin.php`、`Compat/Legacy`
- `Bind/Repository`、`Redirect/{Repository,Listener}`
- `Rest/Controller`、`Publish/HttpSource`
- `Admin/Menu` + 简陋 views（**尚未档 A 换壳**）

Grok 任务 = **按规范把骨架做实到可审原型 + 可测**，不是另起空仓。

---

## 5. Admin UI 规范与标准（档 A · 强制）

### 5.1 必读（按顺序）

**本仓（无 linuxjoy 也能开工）：**

1. `docs/design/admin-ui-a.md`
2. `docs/contracts/wpdocs-v1.md`
3. 本 brief + `docs/plans/2026-11-02-wpdocs-full-plan.md`

**有 linuxjoy 时再加深：**

1. `docs/design/wenpai-admin-ui/tiers.yml` → **`wpdocs: A`**
2. Skill：`.agents/skills/wenpai-admin-ui/SKILL.md`
3. `standards.md`、`components.md`、`ux.md`、`gate.md`、`system-plan.md`、`use-plan.md`
4. 命令：`python3 scripts/wenpai/wenpai-admin-tier.py wpdocs`（必须退出 0）

kit：**wenpai-admin-ui 0.2.3**（`WenPai-org/wenpai-admin-ui`）。vendor：

```bash
bash scripts/wenpai/vendor-wenpai-admin-ui.sh <插件根> 0.2.3
```

### 5.2 档 A 只许这样

- 页根：`.wenpai-app`；字段：`.field`（不要 `.form-table`）
- 壳类名：`main.wenpai-main`、`.foot`、`.mode`（禁止自创 `.wenpai-foot`）
- CSS/JS handle 名：`wenpai-admin-ui`（产品 CSS 才带 `wpdocs` slug）
- 横 Tab / 简单·高级：跟 kit；**不要**第四个「服务」广告 Tab
- 确认用 `.wenpai-gate`；不要自建对话框 / 骨架屏 / Emoji 图标
- 图标：禁止字体图标、热链 CDN Remix；跟 `assets.md`
- 帮助链可指向 wpcy / 文档站；密钥与真桶名禁止进截图

### 5.3 硬禁（摘录）

- 改 Update URI；把 Plugin URI 写成 Update URI
- **无 `prototypes/wpdocs/APPROVAL.md`（人、日期）就大改后台 PHP 模板**
- 一次任务改两只以上插件后台
- kit 或产品页用 WP `.button` / `.form-table` 当皮
- `remove_all_actions('admin_notices')` 乱清
- token 写在 `:root`；每插件各 enqueue 一份 kit CSS 互盖

### 5.4 换壳流程（给 Grok 的顺序）

1. `wenpai-admin-tier.py wpdocs` → 确认 A  
2. 建 `prototypes/wpdocs/`：状态 / 绑定 / 跳转 **静态原型**（可参考 kit `prototypes/harvest.html`，**不要抄母版产品文案**）  
3. 等人签 `prototypes/wpdocs/APPROVAL.md` 后，再改 `src/Admin/**`  
4. vendor kit 0.2.3 → enqueue → 视图改 `.wenpai-app`  
5. `wenpai-admin-lint.py --plugin-root <本仓>`  
6. 视觉：空 / 正常 / 错 三态；未验写「未验壳门」

**本轮若无人签 APPROVAL**：只许完善契约、Repository、REST、Listener、导入、测试与**原型 HTML**；不要宣称后台换壳完成。

### 5.5 文案与信息架构

| 菜单 | 内容 |
|------|------|
| 文派文档 → 状态 | 版本、docs base、探测、绑定/跳转计数、迁移提示 |
| 绑定 | 列表 + 手工 upsert；文章列表列「文档绑定」保留 |
| 跳转 | 列表 + 手工 upsert；标明 matched/gone；链接 dry-run |

渐进披露：高级项可藏，不删字段。中文 UI；text domain `wpdocs`。

---

## 6. 从旧插件迁移要求

对照 `~/Projects/cocobook-docs`（或 clone `CocoBook/cocobook-docs`）：

- `src/Redirects.php` 行为：status、404 log、目标用 page_id 不写死 URL  
- `Catalog` slug 自动匹配：可迁为 Bind `source=slug` 批处理（可第二刀）  
- Embed / 评论 shortcode：本轮可保留兼容别名或明确「仍走旧插件」——须在 CHANGELOG 写清  
- 设置页密钥：支持 `WPDOCS_*` 与 `COCOBOOK_DOCS_*` 常量覆盖  

迁移验收：

1. 激活 wpdocs，redirects 表从旧表导入行数一致（若旧表存在）  
2. 同一 `wp_path` dry-run 与旧插件 301/410 行为一致  
3. 停用旧插件后仅启用 wpdocs，关键旧链不断  

---

## 7. 工程与质量门

```bash
# 在插件根
find . -name '*.php' -not -path './lib/*' | xargs -n1 php -l
# 有 linuxjoy 时
python3 <linuxjoy>/scripts/wenpai/wenpai-admin-tier.py wpdocs
python3 <linuxjoy>/scripts/wenpai/wenpai-admin-lint.py --plugin-root .
```

- PHP ≥ 8.0；WPCS 有则跑，无则勿假装绿  
- 不提交密钥；`.gitignore` 已有  
- 提交信息：中文或英文短句，说明 why；作者可用本机身份（FeiCode 已授 write）  
- **禁止** `rm -rf` 清整仓；**禁止** force push main  

---

## 8. Grok Build 本轮任务切片（按序，一路一轮）

| 轮 | 目标 | 可改文件（点名） | 完成判据 |
|----|------|------------------|----------|
| G1 | 补 `prototypes/wpdocs/` 三页静态原型 + README（换壳流程说明） | `prototypes/wpdocs/**`、本 brief 若需勘误 | 三页可本地打开；无密钥 |
| G2 | Redirect：补齐 404 日志上限、reserved 路径、与旧插件行为差注释；dry-run 单测脚本 | `src/Redirect/**`、`tests/**` | `php -l` 绿；`tests/` 有可跑说明 |
| G3 | Bind：批量 resolve 草稿（按 post_name↔slug 的接口形状，可 dry-run） | `src/Bind/**`、`src/Rest/Controller.php` | REST 形状写入契约附录 |
| G4 | Publish：可选探测 `/cocobook/sitemap.xml` 与发布树 HEAD | `src/Publish/HttpSource.php` | status JSON 字段文档化 |
| G5 | **仅当 APPROVAL 已签**：vendor kit + Admin 换壳 | `src/Admin/**`、`lib/wenpai-admin-ui/**`、`assets/**` | lint；否则跳过并写 BLOCKED |

默认 **先做 G1→G4**；G5 等人批。

---

## 9. 给 CLI 的调用约定

```bash
export PATH="$HOME/.grok/bin:$PATH"
command grok -m grok-4.6 --disable-web-search --no-plan --no-subagents \
  --cwd "$HOME/Projects/wpdocs" --max-turns 16 --always-approve \
  --output-format plain \
  --prompt-file /tmp/wpdocs-grok-g1.txt
```

Prompt 必须：点名文件、禁止扫整个 `~/Projects`、禁止改 linuxjoy 无关仓、禁止生产 SSH。

---

## 10. 验收清单（DoD）

- [x] 仓公开可读：https://feicode.com/WenPai-org/wpdocs  
- [x] `tiers.yml` 含 `wpdocs: A`  
- [x] 契约与本 brief 在仓内（另：`docs/design/admin-ui-a.md`、完整计划）  
- [x] G1 原型落地（`prototypes/wpdocs/{status,bindings,redirects}.html`）  
- [x] G2–G4 行为与测试说明落地（`tests/run.sh` ALL PASS；main `14eae03`）  
- [ ] G5 仅在 APPROVAL 后（**BLOCKED**：等人签 `prototypes/wpdocs/APPROVAL.md`）  
- [x] CHANGELOG 记录相对 `cocobook-docs` 的破坏性/兼容项（G2–G4 条目）  

---

## 11. 联系与拍板

- 产品/命名/公开：已定  
- 后台换壳人签：待 feibisi 签 `prototypes/wpdocs/APPROVAL.md`  
- 生产启用旧站迁移：另开会话，不在本 Build 轮  
MD