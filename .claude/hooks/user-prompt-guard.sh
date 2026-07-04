#!/usr/bin/env bash
#
# UserPromptSubmit hook — spustí se, když odešleš prompt (před zpracováním).
# Ukázka „guardrail": pokud prompt obsahuje pokyn smazat databázi nebo .env,
# upozorní. (Nic neblokuje natvrdo — jen varuje na stderr.)

set -uo pipefail

payload="$(cat 2>/dev/null || true)"
# prompt bývá v JSON pod klíčem "prompt"
prompt="$(printf '%s' "$payload" | grep -oE '"prompt"[[:space:]]*:[[:space:]]*"[^"]*"' | head -1)"

if printf '%s' "$prompt" | grep -qiE 'smaž.*(databáz|tasks\.sqlite)|drop table|rm .*\.env'; then
  echo "⚠️  Pozor: prompt zmiňuje destruktivní operaci nad daty/konfigurací. Zkontroluj záměr." >&2
fi

exit 0
