# 文派文档（wpdocs）运维手册

仓：`WenPai-org/wpdocs`。插件只做品牌站桥接；**不做**站内第二套导出器。

---

## 1. CocoBook 导出（ZIP / Markdown / PDF）

在 **CocoBook / BookStack** 后台对书或页使用原生导出：

| 需求 | 做法 |
|------|------|
| 整书备份 / 迁移 | 书页 → Export → ZIP / Markdown |
| 单页给编辑 | 页 → Export → Markdown / PDF |
| 批量进 Git | 用 BookStack Markdown 导出后放入 Forgejo；品牌站走「Git 写入」 |

wpdocs **不会**把正文塞进 `wp_options`，也 **不会**提供「从 WP 再导出一份 CocoBook」。

---

## 2. CocoBook → wpdocs Webhook

1. 打开 WP 后台 **文派文档 → 工具 → 同步**（文档站读取模式下），复制「CocoBook Webhook」完整 URL（路径含 `refresh_secret`）。
2. 在 CocoBook：**Settings → Webhooks**，事件至少勾选 page create / update / delete，POST 到该 URL。
3. 效果：对应页缓存与绑定「远端」元数据标记过期；下次打开绑定列表会重新探测。

密钥可在 `wp-config.php` 钉死：

```php
define( 'WPDOCS_REFRESH_SECRET', '…' );
```

未配置时，保存设置会自动生成 `refresh_secret`。

兼容旧路径：`POST /wp-json/cocobook-docs/v1/refresh/{secret}`（同回调）。

---

## 3. Application Passwords 调 REST

权限一律 `manage_options`。用管理员的 **Application Password**（用户 → 个人资料）作 Basic Auth。

| 方法 | 路径 | 作用 |
|------|------|------|
| GET | `/wp-json/wpdocs/v1/status` | 探测摘要（home/sitemap/tree/api）+ `metrics`（覆盖率 / unmatched / 探测失败率） |
| GET \| POST | `/wpdocs/v1/bindings` | 列表 / upsert |
| GET | `/wpdocs/v1/bindings/resolve` | 反查 |
| POST | `/wpdocs/v1/bindings/resolve-batch` | 别名草稿；默认 dry-run，`apply=true` 才写 |
| GET \| POST | `/wpdocs/v1/redirects` | 跳转列表 / upsert |
| GET | `/wpdocs/v1/redirects/dry-run?path=` | 预览；不写 hits |
| GET | `/wpdocs/v1/search?q=` | BookStack 搜索代理（需 Token） |
| GET | `/wpdocs/v1/suggest?q=` | 绑定候选 |
| POST | `/wpdocs/v1/refresh/{secret}` | Webhook；**不用** App Password，用路径密钥 |

示例（本机）：

```bash
curl -u 'admin:xxxx xxxx xxxx xxxx xxxx xxxx' \
  'http://localhost:8899/wp-json/wpdocs/v1/status'
```

密钥、Token、App Password **不进** changelog / 截图 / 帮助页明文以外的公开文档。

---

## 4. 常见观测

| 现象 | 看哪里 |
|------|--------|
| 绑定远端「失效 / 别名已变 / 无权限」 | 工具 → 绑定「远端」列；概览日检告警 |
| 探测失败 | 概览「重新探测」；`GET /status` |
| 绑定覆盖偏低 / unmatched 堆高 | 概览「绑定覆盖」「未匹配 404」；`GET /status` → `metrics`；日检写入 `wpdocs_metrics_v1`（14 日） |
| 索引偏旧 | 工具 → 同步「更新索引」；或等短 TTL（≤300s）与 Webhook |
| Git 推送不刷新 | Forgejo Webhook → 工具同步页给出的 Git URL；Secret 在设置高级 |

## 5. 不在本插件

从 Git Markdown **批量导入 BookStack** 属运维脚本仓，不进 wpdocs。品牌站只读桥接 + 校验。
