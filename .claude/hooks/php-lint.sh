#!/usr/bin/env bash
# PostToolUse hook: lint edited PHP. Fails only on `php -l` errors (exit 2 feeds stderr to Claude).
# phpcs and phpunit output is informational. Reads tool_input.file_path from stdin JSON.
cd "${CLAUDE_PROJECT_DIR:-$(dirname "$0")/../..}" || exit 0

input=$(cat)
if command -v jq >/dev/null 2>&1; then
	file=$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty')
else
	file=$(printf '%s' "$input" | python3 -c 'import json,sys;print(json.load(sys.stdin).get("tool_input",{}).get("file_path",""))')
fi

case "$file" in *.php) ;; *) exit 0 ;; esac
[ -f "$file" ] || exit 0
rel=${file#"$PWD"/}

out=$(php -l "$file" 2>&1) || { echo "$out" >&2; exit 2; }
echo "$out"

[ -x vendor/bin/phpcs ] && { vendor/bin/phpcs -q --report=summary "$file" || true; }

case "$rel" in
	checks/class-*.php)
		[ -x vendor/bin/phpunit ] && { vendor/bin/phpunit --filter CheckFixtureTest 2>&1 | tail -n 5 || true; } ;;
esac
exit 0
