# wpdocs tests

本轮（G2–G4）可在无 WordPress bootstrap 下本地跑 Redirect 路径、Bind slug 匹配、Publish 探测形状与 dry-run 计划。

```bash
# 插件根
bash tests/run.sh
# 等价拆开：
find . -name '*.php' -not -path './lib/*' | xargs -n1 php -l
php tests/RedirectPathTest.php
php tests/BindResolveDraftTest.php
php tests/PublishProbeTest.php
```

需要：PHP ≥ 8.0，`php` 在 PATH。不连数据库、不启 Studio、不读密钥。

| 文件 | 作用 |
|------|------|
| `RedirectPathTest.php` | `Path::norm_path` / reserved / 404 skip / unmatched cap / `Decision::preview` |
| `BindResolveDraftTest.php` | `Slug` 精确/`book_page` 匹配、fuzzy stub、`ResolveDraft::plan` 形状（`applied` 恒 false） |
| `PublishProbeTest.php` | sitemap/tree URL、HEAD 结果键、skipped、`publish` 信封；不发 HTTP |
| `run.sh` | `php -l` + 上述单测 + 断言 dry-run 不写 `hits` / bindings，Publish 不写 option / 不 GET 正文 |
| `redirect-vs-cocobook-docs.md` | 与旧插件行为差（契约；本轮未扫旧仓） |

未覆盖（需 WP）：真实 301/410、表导入、`is_404()` 写 unmatched 行、`apply=true` 真实 upsert、真实 HEAD 探测。有 Studio 站点后再补。
