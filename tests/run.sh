#!/usr/bin/env bash
# Local Redirect checks (no WordPress bootstrap).
#   bash tests/run.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "== php -l =="
fail=0
while IFS= read -r f; do
	if ! php -l "$f"; then
		fail=1
	fi
done < <(find . -name '*.php' -not -path './lib/*' | sort)
if [[ "$fail" -ne 0 ]]; then
	echo "php -l failed" >&2
	exit 1
fi

echo "== Redirect unit tests (no WP) =="
php tests/RedirectPathTest.php

echo "== Bind resolve-batch unit tests (no WP) =="
php tests/BindResolveDraftTest.php

echo "== Publish probe unit tests (no WP) =="
php tests/PublishProbeTest.php

echo "== BookStack client unit tests (no WP) =="
php tests/BookStackClientTest.php

echo "== Bind suggest unit tests (no WP) =="
php tests/BindSuggestTest.php

echo "== Bind remote meta unit tests (no WP) =="
php tests/BindRemoteMetaTest.php

echo "== Catalog index TTL unit tests (no WP) =="
php tests/CatalogIndexTtlTest.php

echo "== Bind drift check unit tests (no WP) =="
php tests/BindDriftCheckTest.php

echo "== dry-run must not persist =="
# Listener::dry_run body (until the next method) must not call hit() or log_unmatched().
dry_body="$(awk '
	/function dry_run\(/ {grab=1}
	grab {print}
	grab && /^	public static function / && !/function dry_run\(/ {exit}
' src/Redirect/Listener.php)"
if echo "$dry_body" | grep -E 'Repository::(hit|log_unmatched)\('; then
	echo "dry-run writes hits or unmatched rows" >&2
	exit 1
fi
echo "ok dry-run has no hit/log_unmatched"

echo "== resolve-batch plan must not persist =="
if grep -E '^[^/*]*Repository::(upsert|apply_slug_drafts)\(' src/Bind/ResolveDraft.php src/Bind/Slug.php; then
	echo "ResolveDraft/Slug writes bindings" >&2
	exit 1
fi
echo "ok ResolveDraft/Slug have no upsert"

echo "== resolve-batch REST writes only when apply =="
batch_body="$(awk '
	/function resolve_batch\(/ {grab=1}
	grab {print}
	grab && /^	(public|private) static function / && !/function resolve_batch\(/ {exit}
' src/Rest/Controller.php)"
if ! echo "$batch_body" | grep -q 'BindRepo::apply_slug_drafts'; then
	echo "resolve_batch missing apply_slug_drafts" >&2
	exit 1
fi
if ! echo "$batch_body" | grep -q 'if ( \$apply )'; then
	echo "resolve_batch missing apply gate" >&2
	exit 1
fi
# apply_slug_drafts must sit inside the apply branch (between if ($apply) and its closing brace).
apply_block="$(echo "$batch_body" | awk '
	/if \( \$apply \) \{/ {grab=1}
	grab {print; depth += gsub(/\{/,"{") - gsub(/\}/,"}") ; if (grab && depth==0) exit}
')"
if ! echo "$apply_block" | grep -q 'BindRepo::apply_slug_drafts'; then
	echo "apply_slug_drafts is not inside if ( \$apply )" >&2
	exit 1
fi
echo "ok resolve_batch upserts only when apply"

echo "== publish probes must not persist body =="
if grep -E 'update_option|add_option|delete_option|wp_remote_retrieve_body|wp_remote_get\(' src/Publish/*.php; then
	echo "Publish wrote options or fetched a GET body" >&2
	exit 1
fi
if ! grep -q '/cocobook/sitemap.xml' src/Publish/Probe.php; then
	echo "sitemap path /cocobook/sitemap.xml missing" >&2
	exit 1
fi
if ! grep -q 'wp_remote_head' src/Publish/HttpSource.php; then
	echo "HttpSource missing wp_remote_head" >&2
	exit 1
fi
status_body="$(awk '
	/function status\(/ {grab=1}
	grab {print}
	grab && /^	public static function / && !/function status\(/ {exit}
' src/Rest/Controller.php)"
if ! echo "$status_body" | grep -q 'HttpSource::status'; then
	echo "GET /status missing HttpSource::status" >&2
	exit 1
fi
if ! echo "$status_body" | grep -q 'Probe::failed_status'; then
	echo "GET /status missing Probe::failed_status fallback" >&2
	exit 1
fi
if ! echo "$status_body" | grep -q 'catch ( Throwable'; then
	echo "GET /status missing Throwable catch" >&2
	exit 1
fi
echo "ok publish HEAD-only, no option writes"

echo "ALL PASS"
