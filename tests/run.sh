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

echo "ALL PASS"
