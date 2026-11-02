# wpdocs v1 契约

产品名：文派文档。技术 id：`wpdocs`。品牌站侧插件真源：`WenPai-org/wpdocs`。

## 边界

| 能力 | 所有者 | 不做 |
|------|--------|------|
| Bind | 本插件表 `wpdocs_bindings` | 不写 BookStack |
| Redirect | 本插件表 `wpdocs_redirects` | 不管 docs 站内 slug_history |
| Publish | 只读探测 docs / 发布树 | 正文不进 `option` |

主题 `cocobook-docs-theme`：阅读壳 / SEO / 反馈 / 相关阅读。评论挂件仍走 Cyberforums。

## REST

命名空间 `/wpdocs/v1`（需 `manage_options`）：

- `GET /status`
- `GET|POST /bindings`，`GET /bindings/resolve`
- `GET|POST /redirects`，`GET /redirects/dry-run?path=`

## 兼容

`Compat\Legacy`：常量 `COCOBOOK_DOCS_*` → `WPDOCS_*`；option 双写；激活时若新跳转表空则从 `{$prefix}cocobook_redirects` 导入。
