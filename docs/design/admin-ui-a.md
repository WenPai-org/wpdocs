# 文派文档 · Admin UI 使用规范与标准（档 A）

> 本文件是 **本仓自洽摘要**，供 Grok Build / 外协在不打开 linuxjoy 全树时也能守档。  
> 真源仍在 linuxjoy：`docs/design/wenpai-admin-ui/` + skill `.agents/skills/wenpai-admin-ui/SKILL.md`。  
> 档位登记：`tiers.yml` → **`wpdocs: A`**。  
> kit：FeiCode `WenPai-org/wenpai-admin-ui` **0.2.3**。

---

## 1. 档 A 只许做的事

1. vendor kit **0.2.3** 到 `lib/wenpai-admin-ui/`
2. 页根用 `.wenpai-app`；字段用 `.field`（**不要** `.form-table`）
3. enqueue handle：**CSS 与 JS 都是** `wenpai-admin-ui`；产品 CSS 才带 `wpdocs` slug
4. 跑 `wenpai-admin-lint.py --plugin-root <本仓>`
5. 视觉至少：**空 / 正常 / 错** 三态（真 wp-admin 或 Playground）

未知档先补 `tiers.yml`，不要猜。一次只换一只插件后台。

---

## 2. 本插件信息架构

| 菜单 / 页 | 答什么 | 主按钮 |
|-----------|--------|--------|
| 文派文档 → **状态** | 版本、docs base、探测、绑定/跳转计数、迁移提示 | 刷新探测（如有） |
| **绑定** | 列表 + 手工 upsert；文章列表列「文档绑定」 | 保存绑定 |
| **跳转** | 列表 + upsert；matched/gone；链到 dry-run | 保存跳转 |

- 顶栏横 Tab ≤ 3（本产品三页即可）；**不要**第四个「服务」广告 Tab
- 帮助链可放页头右侧，不占 Tab
- 简单 / 高级：跟 kit；高级一次一组；藏依赖用 `hidden` 不删字段
- 中文 UI；text domain `wpdocs`
- 危险动作（批量清 hits、整表导入覆盖等）进工具感区域，确认用 `.wenpai-gate`

---

## 3. 类名与壳（硬）

| 要 | 不要 |
|----|------|
| `.wenpai-app` | 自创应用根 |
| `.field` 单列分组 | `.form-table`、灰底裸 `h3` |
| `main.wenpai-main` | |
| `.foot` / `.mode` | `.wenpai-foot` / `.wenpai-mode` |
| kit 按钮类 | WP `.button` 当皮 |
| `.wenpai-gate` 确认 | 自建对话框 / 骨架屏 / 气泡 |

CSS 变量挂在 `.wenpai-app`（`--wenpai-*`），**禁止写 `:root`**（多插件互漏）。

强调色跟 Gutenberg / 文派后台蓝 `#3858e9`，不是官网绿，不是紫渐变。

---

## 4. 视觉与组件禁忌

- 禁止：Emoji 当图标、图标字体、热链 CDN Remix、Phosphor 类名 `.ph`
- 图标：按需 SVG，`currentColor`；页头品牌标用产品自己的 SVG
- 禁止：紫色炫光、多图层阴影堆砌、假广告位、密钥/真桶名进截图或帮助页
- 确认门：`.wenpai-gate`；说明写在字段 hint
- 不要跟 `PLUGIN-UI-DESIGN.md` 的 wp-components 皮

字阶（摘要）：页 h1 24/32；卡标题 16/24；正文 14/22；说明 12–13 淡色。输入/主钮高 36px。卡圆角 12、无阴影。

---

## 5. 换壳流程（强制顺序）

```text
1. wenpai-admin-tier.py wpdocs → 确认 A
2. 写 prototypes/wpdocs/（status / bindings / redirects 静态 HTML）
3. 人签 prototypes/wpdocs/APPROVAL.md（人名 + 日期）
4. bash scripts/wenpai/vendor-wenpai-admin-ui.sh <插件根> 0.2.3
5. 改 src/Admin/** 视图为 .wenpai-app；enqueue kit
6. wenpai-admin-lint.py --plugin-root .
7. 真后台看空/正常/错；未验写「未验壳门」
```

**无 APPROVAL 时：** 只许契约、Repository、REST、Listener、导入、测试、**原型 HTML**。  
**禁止**宣称后台换壳完成；**禁止**大改 `src/Admin` PHP 模板结构。

---

## 6. 硬禁清单（抄自 skill，本仓再钉一次）

- 改 Update URI；把 Plugin URI 写成 Update URI
- 无 `APPROVAL.md` 写该插件后台 PHP 模板
- 一次任务改两只以上插件后台
- kit/产品页用 `.button` / `.form-table` 当皮
- `remove_all_actions('admin_notices')`
- token 写 `:root`；每插件各 enqueue 一份 kit CSS 互盖
- 自建对话框 / 分页控件 / 滑块 / 骨架屏
- 第四个「服务」Tab 或广告位（未定商业化）
- 改文件头 Plugin Name（更新认文件头）
- 密钥、真邮箱进原型或截图

---

## 7. 原型目录约定

路径：`prototypes/wpdocs/`

| 文件 | 内容 |
|------|------|
| `status.html` | 状态页静态稿 |
| `bindings.html` | 绑定：表单 + 表 |
| `redirects.html` | 跳转：表单 + 表 |
| `README.md` | 如何本地打开；与档 A 对照说明 |
| `APPROVAL.md` | **仅人写**：签署人、日期、看过哪几页 |

可用纯 HTML + 少量内联 CSS；不要外链字体图标 CDN。母版文案不要抄智储/WPSlug 产品句。

---

## 8. 发版门（提醒）

发版走 linuxjoy `gate.md` §5 三门：AI 自评 → AI 出图给人看 → 人测。  
任一门不过不发版、不先写教程营销。本 Build 轮默认 **不到发版**。
