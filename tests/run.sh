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

echo "ALL PASS"
