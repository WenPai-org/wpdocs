# wpdocs tests

本轮（G2）可在无 WordPress bootstrap 下本地跑 Redirect 路径与 dry-run 计划。

```bash
# 插件根
bash tests/run.sh
# 等价拆开：
find . -name '*.php' -not -path './lib/*' | xargs -n1 php -l
php tests/RedirectPathTest.php
```

需要：PHP ≥ 8.0，`php` 在 PATH。不连数据库、不启 Studio、不读密钥。

| 文件 | 作用 |
|------|------|
| `RedirectPathTest.php` | `Path::norm_path` / reserved / 404 skip / unmatched cap / `Decision::preview` |
| `run.sh` | `php -l` + 上述单测 + 断言 `Listener::dry_run` 不调用 `hit`/`log_unmatched` |
| `redirect-vs-cocobook-docs.md` | 与旧插件行为差（契约；本轮未扫旧仓） |

未覆盖（需 WP）：真实 301/410、表导入、`is_404()` 写 unmatched 行。有 Studio 站点后再补。
