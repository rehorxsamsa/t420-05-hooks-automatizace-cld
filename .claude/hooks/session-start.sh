#!/usr/bin/env bash
#
# SessionStart hook — spustí se na začátku session.
# Vypíše stručný stav projektu, aby měl vývojář (i Claude) hned přehled.

set -uo pipefail

echo "📚 Task Library — start session"
echo "   PHP souborů: $(find src -name '*.php' 2>/dev/null | wc -l | tr -d ' ')"

if command -v git >/dev/null 2>&1 && git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  branch="$(git branch --show-current 2>/dev/null || echo '?')"
  changed="$(git status --short 2>/dev/null | wc -l | tr -d ' ')"
  echo "   Git větev: $branch (změněných souborů: $changed)"
fi

exit 0
