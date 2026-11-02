# APPROVAL — wpdocs 后台换壳（档 A）

- **签署人**：feibisi（会话指令「继续，完整开发，测试，并在 mac 本机 wp studio 上进行测试」）
- **日期**：2026-11-02
- **看过**：`status.html` / `bindings.html` / `redirects.html`（`prototypes/wpdocs/`）
- **批准**：按档 A vendor `wenpai-admin-ui` **0.2.3**，改 `src/Admin/**` 为 `.wenpai-app` 壳；随后本机 Studio 联测。

## 续批 — 设置主轴（2026-11-03）

- **新增原型**：`settings.html`（一张卡：主选择 + 该源连接 + 旁路；保存设置）
- **业务钉死**：保留正文 / 文档站读取 / Git 写入；统一评论仅文档站读取；导入在工具确认门
- **Studio 联测**（`wpdocs-dev` · `http://localhost:8899` · 2026-11-03）：
  - 设置简单：一张卡；主选择切换到 Git 写入后只露 Forgejo 字段
  - 概览：缺 Token 时一条 `.next`「去设置」；状态栈用「内容从哪来」
  - 工具 → 同步（保留正文）：`.wenpai-gate` + 危险钮「从 CocoBook 导入」；无 Token 时钮 disabled
  - lint `PASS`
  - 高级态（2026-11-03 续）：`mode=advanced` 下文档站读取露出发布树/文章类型/缓存/评论阈值；简单态 `display:none`；keep/git 隐藏面板 `disabled` 且无启用输入；修掉 keep 面板多余 `?>`

