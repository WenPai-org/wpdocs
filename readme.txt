=== 文派文档 ===
Contributors: wenpai
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.3.0-dev
License: GPLv2 or later

品牌站 ↔ BookStack 文档绑定、旧路径跳转、发布树只读诊断。

== Description ==

文派文档（技术名 wpdocs）承接原 CocoBook Docs（cocobook-docs）的品牌站侧职责：

* Bind：文章 ↔ docs 页
* Redirect：旧 WP 路径 301/410
* Publish：发布树 / BookStack 只读诊断（不写正文进 option）

后台遵循 wenpai-admin-ui。Update URI 仍为 updates.wenpai.net。

== Changelog ==

= 0.3.0-dev =
* 改名文派文档 / wpdocs，新表与 REST 契约
* 兼容旧 option / 表名别名（见 Compat\Legacy）
