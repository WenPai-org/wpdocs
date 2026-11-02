# wpdocs 后台原型（档 A）

换壳前在此放状态 / 绑定 / 跳转静态页。人签 `APPROVAL.md` 后才改 `src/Admin`。

规范见 `docs/plans/2026-11-02-wpdocs-grok-build-brief.md` §5。

## 本地打开

三页是自包含 HTML（内联 CSS，无 CDN、无外链字体图标）。任选一种：

```bash
# 用系统浏览器打开
open prototypes/wpdocs/status.html

# 或同目录起静态服务（查询参数更稳）
cd prototypes/wpdocs && python3 -m http.server 8765
# 访问 http://127.0.0.1:8765/status.html
```

| 文件 | 菜单 | 内容 |
|------|------|------|
| `status.html` | 文派文档 → 状态 | 版本、文档站、首页/站点地图探测、绑定/跳转计数、迁移提示 |
| `bindings.html` | 绑定 | `.field` 一列表单 + `.tbl`；文章列表「文档绑定」列说明在高级 |
| `redirects.html` | 跳转 | 表单 + 表；标明已匹配 / 已失效；高级里试跳转 |

查询参数（可叠加）：

- `?state=ok` 正常（默认）
- `?state=empty` 空
- `?state=bad` 错
- `?mode=simple` 简单（默认；绑定 / 跳转）
- `?mode=advanced` 高级（绑定：来源/状态/反查；跳转：原标题/试跳转/导入说明）

示例：`bindings.html?state=empty&mode=advanced`

## 与档 A 对照

本轮不 vendor kit、不改 PHP。视觉按档 A 概念手写，便于人审：

| 档 A | 原型 |
|------|------|
| 页根 `.wenpai-app`；token 挂在该根上，不写 `:root` | 三页相同 |
| 字段 `.field` 一列；不要 `.form-table` | 绑定 / 跳转表单 |
| `main.wenpai-main`、`.foot`、`.mode` | 有；页脚类名是 `.foot` |
| 横 Tab 状态 / 绑定 / 跳转；帮助、反馈在页头右侧 | 无第四个「服务」Tab |
| 钮 `.btn` / `.btn-primary`；不要 `.button` | 有 |
| 表 `.tbl`；不要 `WP_List_Table` 皮 | 有 |
| 空 / 正常 / 错 | `?state=`；空态不写「暂无数据」 |
| 无 Emoji 图标、无 CDN 字体图标 | 页头品牌标为内联 SVG |
| 确认用 `.wenpai-gate` | 本三页无破坏性动作，未放确认门 |
| 密钥、token、真邮箱 | 未写入；文档站用 `https://docs.example.test` |

G5 等人签 `APPROVAL.md` 后再 vendor `wenpai-admin-ui` 0.2.3，用 kit CSS 换掉本内联皮。
