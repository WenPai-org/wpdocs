# Changelog

## 0.3.0-dev — 2026-11-02

- 产品名「文派文档」，技术 id `wpdocs`（FeiCode `WenPai-org/wpdocs`）。
- 契约见 `docs/contracts/wpdocs-v1.md`；完整计划见 `docs/plans/2026-11-02-wpdocs-full-plan.md`。
- 新表 `{$wpdb->prefix}wpdocs_bindings` / `wpdocs_redirects`；旧 `cocobook_*` 经 `Compat\Legacy` 只读别名。
- REST 命名空间 `/wpdocs/v1`。
- 后台按 wenpai-admin-ui；原 `cocobook-docs` 仓保留作迁移对照，本仓为重构真源。
