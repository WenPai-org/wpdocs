=== 文派文档 ===
Contributors: wenpai
Tags: documentation, bookstack, redirects
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.3.0
License: GPLv2 or later

内容从哪来（保留正文 / 文档站读取 / Git 写入）、绑定、旧路径跳转、CocoBook 只读探测。

== Description ==

文派文档（技术名 wpdocs）承接原 CocoBook Docs（cocobook-docs）的品牌站侧职责。

主选择「内容从哪来」三选一：

* 保留正文：品牌站自己管正文；可选一次性从 CocoBook 导入
* 文档站读取：打开文章时从 CocoBook 读正文；统一评论仅此路径
* Git 写入：从 Forgejo / 本地目录把 Markdown 写入文章

旁路能力：

* Bind：文章 ↔ CocoBook 页
* Redirect：旧 WP 路径 301/410
* Publish：发布树 / BookStack 只读诊断（不写正文进 option）

后台遵循 wenpai-admin-ui。Update URI 仍为 updates.wenpai.net。

== Changelog ==

= Unreleased =
* 后台 UI 对齐 wenpai-admin-ui 0.8.1；设置简单/高级分组重排；后台菜单名「文档」；页面加载不再每次请求文档站书目列表

= 0.3.0 =
* 主选择：保留正文 / 文档站读取 / Git 写入；评论仅文档站读取
* 改名文派文档 / wpdocs，新表与 REST 契约
* 兼容旧 option / 表名别名（见 Compat\Legacy）
