#!/usr/bin/env bash
#
# PostToolUse hook — spustí se po každé editaci souboru (Edit/Write).
# Pokud byl upravený PHP soubor, ověří ho `php -l`. Při syntaktické chybě
# vrátí nenulový kód a zprávu na stderr → Claude Code chybu uvidí a může reagovat.
#
# Claude Code předává kontext hooku jako JSON na stdin a v env proměnných.
# Klíčová proměnná: CLAUDE_FILE_PATH = cesta k právě upravenému souboru.

set -uo pipefail

file="${CLAUDE_FILE_PATH:-}"

# Pokud nevíme soubor, zkusíme ho vytáhnout z JSON na stdin (tool_input.file_path).
if [[ -z "$file" ]]; then
  payload="$(cat 2>/dev/null || true)"
  file="$(printf '%s' "$payload" | grep -oE '"file_path"[[:space:]]*:[[:space:]]*"[^"]+"' | head -1 | sed -E 's/.*"file_path"[[:space:]]*:[[:space:]]*"([^"]+)".*/\1/')"
fi

# Zajímají nás jen .php soubory.
if [[ "$file" != *.php ]]; then
  exit 0
fi

if [[ ! -f "$file" ]]; then
  exit 0
fi

# PHP je v tomhle projektu jen v Dockeru — na hostiteli `php` být nemusí.
# Když chybí, lint tiše přeskočíme (syntax se ověří v kontejneru:
# `docker compose exec web php -l <soubor>`), ať hook nehlásí falešný poplach.
if ! command -v php >/dev/null 2>&1; then
  echo "ℹ️  PHP lint přeskočen (php není na hostiteli): $file"
  exit 0
fi

# Lint.
if ! output="$(php -l "$file" 2>&1)"; then
  echo "❌ PHP lint selhal v souboru: $file" >&2
  echo "$output" >&2
  exit 2   # nenulový kód → Claude Code zaznamená problém
fi

echo "✅ PHP lint OK: $file"
exit 0
