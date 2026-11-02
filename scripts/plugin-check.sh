#!/usr/bin/env bash
# Release Plugin Check for wpdocs (self-hosted Update URI).
# Prefer candidate zip → extract into WP plugins → check THAT tree.
# Ignore ONLY: plugin_updater_detected, trademarked_term
#
# Usage:
#   bash scripts/plugin-check.sh [--path=<WP根>] [--zip=dist/foo.zip]
#   bash scripts/plugin-check.sh wpdocs --path=/path/to/wp
set -euo pipefail

PLUGIN_SLUG="wpdocs"
WP_PATH=""
ZIP_PATH=""
EXTRA=()
while [[ $# -gt 0 ]]; do
	case "$1" in
		wpdocs) PLUGIN_SLUG="$1"; shift ;;
		--path=*) WP_PATH="${1#--path=}"; shift ;;
		--path) WP_PATH="${2:-}"; shift 2 ;;
		--zip=*) ZIP_PATH="${1#--zip=}"; shift ;;
		--zip) ZIP_PATH="${2:-}"; shift 2 ;;
		*) EXTRA+=("$1"); shift ;;
	esac
done

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP_PATH="${WP_PATH:-${WENPAI_PLUGIN_CHECK_WP:-}}"
if [[ -z "$WP_PATH" ]]; then
	for cand in "$HOME/Studio/wpdocs-dev" "$HOME/Studio/wpslug-dev" "$HOME/Studio/wpsss-dev"; do
		if [[ -d "$cand/wp-content/plugins/plugin-check" ]]; then
			WP_PATH="$cand"
			break
		fi
	done
fi
if [[ -z "$WP_PATH" ]]; then
	echo "WP path required (--path or WENPAI_PLUGIN_CHECK_WP); plugin-check plugin must be installed" >&2
	exit 2
fi

if [[ "$WP_PATH" == *"/Studio/"* ]] && [[ -x /usr/local/bin/studio ]]; then
	# WordPress Studio Playground: host `wp` cannot open SQLite db.php.
	WP=(/usr/local/bin/studio wp)
elif command -v wp >/dev/null 2>&1; then
	WP=(wp)
elif [[ -f /var/www/html/wp-cli.phar ]]; then
	WP=(php /var/www/html/wp-cli.phar)
else
	echo "wp-cli is required (or /usr/local/bin/studio for Studio sites)" >&2
	exit 127
fi

ROOT_ARGS=()
if [[ "$(id -u)" -eq 0 ]]; then
	ROOT_ARGS+=(--allow-root)
fi

IGNORE_CODES="plugin_updater_detected,trademarked_term"
PLUGINS_DIR="$WP_PATH/wp-content/plugins"
LIVE="$PLUGINS_DIR/$PLUGIN_SLUG"
BACKUP=""
STAGED=0

restore() {
	if [[ "$STAGED" -eq 1 ]]; then
		rm -rf "$LIVE"
		if [[ -n "$BACKUP" && -e "$BACKUP" ]]; then
			mv "$BACKUP" "$LIVE"
		fi
	fi
}
trap restore EXIT

if [[ "${WPDOCS_SKIP_ZIP:-}" != "1" ]]; then
	if [[ -z "$ZIP_PATH" ]]; then
		VER=$(php -r 'preg_match("/Version:\\s*(\\S+)/", file_get_contents("'"$REPO_ROOT"'/wpdocs.php"), $m); echo $m[1];')
		ZIP_PATH="$REPO_ROOT/dist/${PLUGIN_SLUG}-${VER}-candidate.zip"
	fi
	if [[ ! -f "$ZIP_PATH" ]]; then
		echo "missing zip $ZIP_PATH — run: python3 scripts/build-candidate.py --version <ver>" >&2
		exit 2
	fi
	if [[ -e "$LIVE" || -L "$LIVE" ]]; then
		BACKUP="$PLUGINS_DIR/.${PLUGIN_SLUG}.bak-plugin-check.$$"
		mv "$LIVE" "$BACKUP"
	fi
	tmpdir=$(mktemp -d)
	unzip -q "$ZIP_PATH" -d "$tmpdir"
	if [[ -d "$tmpdir/$PLUGIN_SLUG" ]]; then
		mv "$tmpdir/$PLUGIN_SLUG" "$LIVE"
	else
		echo "zip root missing $PLUGIN_SLUG/" >&2
		rm -rf "$tmpdir"
		exit 2
	fi
	rm -rf "$tmpdir"
	STAGED=1
fi

cmd=(
	"${WP[@]}"
	plugin
	check
	"$PLUGIN_SLUG"
	--path="$WP_PATH"
	--mode=update
	--ignore-codes="$IGNORE_CODES"
	--ignore-warnings
	--exclude-directories=tests,docs,scripts,prototypes,node_modules,.git,.forgejo,.github
	--format=strict-table
)
if [[ ${#ROOT_ARGS[@]} -gt 0 ]]; then
	cmd+=("${ROOT_ARGS[@]}")
fi
if [[ ${#EXTRA[@]} -gt 0 ]]; then
	cmd+=("${EXTRA[@]}")
fi
"${cmd[@]}"
