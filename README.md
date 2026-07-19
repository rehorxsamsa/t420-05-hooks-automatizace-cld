# Díl 05 — Hooks & automatizace

> **Co se naučíš:** hook události `SessionStart`, `SessionEnd`, `UserPromptSubmit`,
> `PreToolUse`, `PostToolUse`, `Stop`; jak je zapojit v `settings.json`; jaké env proměnné
> hooky dostávají. Postavíme **tři funkční hooky** a ověříme, že reálně běží.
>
> **Na čem:** automatický PHP lint po každé editaci, kontext na startu session, guardrail na prompt.

---

## 🚀 Rychlý start

PHP běží **jen v Dockeru** (na hostiteli žádné není). Rozběhnutí webu:

```bash
docker compose up -d --build     # web poběží na http://localhost:8080
docker compose down              # zastavení
```

Aplikace je jednoduchý **správce úkolů** — přidat / odškrtnout / smazat, s pruhem hotovo v %.
PHP příkazy (lint, testy) spouštěj **v kontejneru**, ne na hostiteli:

```bash
docker compose exec web php -l src/Service/TaskService.php
```

## 📄 Dokumentace v repu

| Soubor | Pro koho | Obsah |
|---|---|---|
| **README.md** (tento) | student dílu 05 | výukový text o hoocích |
| [ARCHITECTURE.md](ARCHITECTURE.md) | vývojář | vrstvená architektura appky + dvě roviny projektu |
| [DEMO.md](DEMO.md) | prezentující | jak appku předvést laikovi bez znalosti AI |
| [CLAUDE.md](CLAUDE.md) | Claude Code | pokyny pro AI asistenta v tomhle repu |

---

## 1. Co je hook a proč ho chtít

**Hook** je shell příkaz, který Claude Code spustí **automaticky** v určitém bodě svého životního cyklu. Není to něco, co Claude „rozhodne" udělat — je to deterministické: stane se událost → spustí se tvůj příkaz.

Typické využití pro PHP projekt:
- po každé editaci souboru → **automaticky lint** (`php -l`), případně formátovač
- na startu session → vypsat stav projektu
- před odesláním promptu → guardrail proti destruktivním operacím

Hooky se konfigurují v **`.claude/settings.json`** (z cheatsheetu „Settings Location: `~/.claude/settings.json` nebo `.claude/settings.json`").

---

## 2. Hook události (z cheatsheetu „Hook Events")

| Událost | Kdy se spustí | Typické použití |
|---|---|---|
| `SessionStart` | na začátku session | vypsat kontext, načíst stav |
| `SessionEnd` | na konci session | úklid, log, notifikace |
| `UserPromptSubmit` | když odešleš prompt | guardrail, obohacení promptu |
| `PreToolUse` | **před** použitím nástroje | zablokovat/schválit akci |
| `PostToolUse` | **po** použití nástroje | lint, formátování, testy |
| `Stop` | když Claude dokončí odpověď | finální kontrola, notifikace |

**Klíčový rozdíl `PreToolUse` vs. `PostToolUse`:**
- `PreToolUse` = *než* Claude něco udělá (např. zablokovat editaci `.env`)
- `PostToolUse` = *poté* co to udělal (např. zkontrolovat výsledek)

---

## 3. Env proměnné, které hook dostává

Claude Code předává hooku kontext jako **JSON na stdin** a v **env proměnných**:

| Proměnná | Obsah |
|---|---|
| `CLAUDE_FILE_PATH` | cesta k právě upravenému souboru (u Edit/Write) |
| `CLAUDE_TOOL_NAME` | jméno použitého nástroje |
| `CLAUDE_TOOL_INPUT` | vstup nástroje |
| `CLAUDE_SESSION_ID` | ID session |
| `CLAUDE_PROJECT_DIR` | kořen projektu |

> ⚠️ Názvy proměnných se mezi verzemi mohou lišit a JSON na stdin je nejspolehlivější zdroj. Naše hooky proto čtou `CLAUDE_FILE_PATH`, a když chybí, vytáhnou `file_path` z JSON na stdin — robustní vůči oběma.

---

## 4. Hook #1 — automatický PHP lint po editaci (`PostToolUse`)

Nejužitečnější hook pro nás. Po každé editaci PHP souboru spustí `php -l`. Když Claude vygeneruje syntakticky rozbitý PHP, hook vrátí nenulový kód a Claude to **hned vidí a opraví**.

Soubor `.claude/hooks/php-lint.sh` (zkráceně):
```bash
#!/usr/bin/env bash
set -uo pipefail

file="${CLAUDE_FILE_PATH:-}"
# fallback: vytáhni file_path z JSON na stdin
if [[ -z "$file" ]]; then
  payload="$(cat)"
  file="$(printf '%s' "$payload" | grep -oE '"file_path"[^"]*"[^"]+"' | ... )"
fi

[[ "$file" != *.php ]] && exit 0      # jen PHP soubory
[[ ! -f "$file" ]] && exit 0

if ! output="$(php -l "$file" 2>&1)"; then
  echo "❌ PHP lint selhal: $file" >&2
  echo "$output" >&2
  exit 2                               # nenulový kód → Claude zaznamená problém
fi
echo "✅ PHP lint OK: $file"
```

Zapojení v `.claude/settings.json` — všimni si **`matcher`**, který omezí hook jen na editační nástroje:
```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [
          { "type": "command", "command": "bash .claude/hooks/php-lint.sh" }
        ]
      }
    ]
  }
}
```

**Ověřeno, že funguje** (můžeš si to spustit sám):
```bash
cd t420-05-hooks-automatizace-cld
chmod +x .claude/hooks/*.sh

# zdravý soubor → exit 0
CLAUDE_FILE_PATH="src/Service/TaskService.php" bash .claude/hooks/php-lint.sh

# rozbitý soubor → exit 2 + chyba na stderr
echo "<?php neplatný kód {{{" > /tmp/x.php
CLAUDE_FILE_PATH="/tmp/x.php" bash .claude/hooks/php-lint.sh; echo "exit: $?"
```

> 🎯 Tohle je nejlepší „bezpečnostní síť" pro AI-asistovaný vývoj v PHP: Claude fyzicky nemůže nechat za sebou syntakticky rozbitý soubor, aniž by o tom hned věděl.

---

## 5. Hook #2 — kontext na startu (`SessionStart`)

`.claude/hooks/session-start.sh` vypíše počet PHP souborů a stav gitu:
```bash
#!/usr/bin/env bash
echo "📚 Task Library — start session"
echo "   PHP souborů: $(find src -name '*.php' | wc -l)"
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "   Git větev: $(git branch --show-current) (změněných: $(git status --short | wc -l))"
fi
```

Zapojení:
```json
"SessionStart": [
  { "hooks": [ { "type": "command", "command": "bash .claude/hooks/session-start.sh" } ] }
]
```

Výstup na startu session:
```
📚 Task Library — start session
   PHP souborů: 7
   Git větev: main (změněných: 0)
```

---

## 6. Hook #3 — guardrail na prompt (`UserPromptSubmit`)

`.claude/hooks/user-prompt-guard.sh` varuje, když prompt zmiňuje destruktivní operaci (smazat databázi, drop table, smazat `.env`):
```bash
#!/usr/bin/env bash
payload="$(cat)"
prompt="$(printf '%s' "$payload" | grep -oE '"prompt"[^"]*"[^"]*"')"
if printf '%s' "$prompt" | grep -qiE 'smaž.*(databáz|tasks\.sqlite)|drop table|rm .*\.env'; then
  echo "⚠️  Pozor: prompt zmiňuje destruktivní operaci. Zkontroluj záměr." >&2
fi
```

Ověřeno:
```bash
echo '{"prompt":"smaž databázi tasks.sqlite"}' | bash .claude/hooks/user-prompt-guard.sh
# → ⚠️ Pozor: prompt zmiňuje destruktivní operaci...

echo '{"prompt":"přidej pole priority"}' | bash .claude/hooks/user-prompt-guard.sh
# → (ticho, vše v pořádku)
```

> **Hook vs. permissions:** Tenhle guard jen *varuje*. Kdybys chtěl akci tvrdě *zablokovat*, použiješ buď `permissions.deny` v settings (díl 03), nebo `PreToolUse` hook, který vrátí nenulový kód. Hooky a permissions se doplňují.

---

## 7. Hooky vs. skilly vs. CLAUDE.md — kdy co

Snadno se to plete. Rozhodovací pravidlo:

| Chci… | Použij |
|---|---|
| …aby se něco stalo **automaticky** na událost (lint po editaci) | **Hook** |
| …mít pojmenovaný **postup na vyžádání** (`/php-review`) | **Skill** (díl 02) |
| …aby Claude **věděl** o konvencích projektu | **CLAUDE.md** (díl 01) |
| …natvrdo **zakázat** akci | **permissions.deny** nebo **PreToolUse hook** (díl 03) |

Hook = deterministická automatizace. Skill = prompt na vyžádání. CLAUDE.md = znalost. Permissions = mantinely.

---

## Shrnutí dílu 05

Umíš všech 6 hook událostí a hlavně rozdíl `PreToolUse` (před akcí, lze blokovat) vs. `PostToolUse` (po akci, lze kontrolovat). Víš, že hooky se konfigurují v `.claude/settings.json` s volitelným `matcher` (např. `Edit|Write`) a dostávají kontext přes env (`CLAUDE_FILE_PATH`) i JSON na stdin. Postavili a **otestovali** jsme tři funkční hooky: automatický PHP lint po editaci (nejdůležitější), kontext na startu session, a guardrail na prompt. A víš, kdy sáhnout po hooku vs. skillu vs. CLAUDE.md vs. permissions.

---

## ✅ Test dílu 05

**1. Co je hook a čím se liší od skillu (`/php-review`)?**

<details><summary>Odpověď</summary>

Hook je shell příkaz, který Claude Code spustí **automaticky** na danou událost (deterministicky). Skill je pojmenovaný postup, který spouštíš **na vyžádání** přes `/<jméno>`. Hook = automatizace na událost, skill = prompt na povel.
</details>

**2. Jaký je rozdíl mezi `PreToolUse` a `PostToolUse`? Dej příklad ke každému.**

<details><summary>Odpověď</summary>

`PreToolUse` = **před** použitím nástroje, lze akci zablokovat (např. zabránit editaci `.env`). `PostToolUse` = **po** použití nástroje, lze zkontrolovat výsledek (např. `php -l` po editaci PHP).
</details>

**3. Kde se hooky konfigurují a k čemu slouží `matcher`?**

<details><summary>Odpověď</summary>

V `.claude/settings.json` (nebo `~/.claude/settings.json`) pod klíčem `hooks`. `matcher` omezí, na které nástroje hook reaguje — např. `"matcher": "Edit|Write"` spustí hook jen po editačních nástrojích, ne po čtení.
</details>

**4. Jak hook zjistí, který soubor byl právě upravený? Proč náš lint hook čte i JSON ze stdin?**

<details><summary>Odpověď</summary>

Z env proměnné `CLAUDE_FILE_PATH`. Náš hook má fallback: když `CLAUDE_FILE_PATH` chybí, vytáhne `file_path` z JSON na stdin. Důvod — názvy env proměnných se mezi verzemi mohou lišit a JSON na stdin je nejspolehlivější zdroj kontextu.
</details>

**5. Náš php-lint hook vrátí na rozbitém souboru exit kód 2. Co to způsobí a proč je to užitečné?**

<details><summary>Odpověď</summary>

Nenulový kód + zpráva na stderr znamenají, že Claude Code problém zaznamená a Claude může hned reagovat (opravit syntaxi). Užitečné: Claude nemůže nechat za sebou syntakticky rozbitý PHP, aniž by o tom okamžitě věděl — automatická bezpečnostní síť.
</details>

**6. Chceš destruktivní operaci (např. `git push`) tvrdě zakázat, ne jen varovat. Co použiješ?**

<details><summary>Odpověď</summary>

`permissions.deny` v `settings.json` (díl 03), např. `"Bash(git push *:*)"`, nebo `PreToolUse` hook, který vrátí nenulový kód. Náš `UserPromptSubmit` guard jen varuje (exit 0) — hooky a permissions se doplňují.
</details>

**7. Seřaď do správné kategorie: (a) lint po každé editaci, (b) `/php-review` na povel, (c) „business logika patří do Service", (d) zákaz editace `.env`.**

<details><summary>Odpověď</summary>

(a) → **Hook** (PostToolUse). (b) → **Skill**. (c) → **CLAUDE.md**. (d) → **permissions.deny** nebo **PreToolUse hook**.
</details>

## 🎲 7 zajímavostí o projektu

1. **Appka je jen kulisa.** Hlavní hodnota repa není správce úkolů, ale tři hooky v `.claude/`. Task Library je jen reálný objekt, na kterém se automatizace demonstruje — proto je celý runtime (Docker, front controller, šablona) doplněný až dodatečně.

2. **Meta bezpečnostní síť.** `php-lint.sh` (`PostToolUse`) lintuje PHP, který právě vygeneroval sám Claude. Nástroj tak hlídá kvalitu kódu, který sám píše — Claude fyzicky nemůže nechat za sebou syntakticky rozbitý soubor, aniž by o tom hned věděl.

3. **Hook, který se musel naučit mlčet.** PHP je záměrně jen v Dockeru, na hostiteli žádné není — takže `php -l` na hostu končilo `php: command not found` a hlásilo falešný poplach po každé editaci. Hook proto teď pozná chybějící `php` a lint tiše přeskočí; syntax se ověří v kontejneru.

4. **Nula závislostí.** Žádný Composer — namespace `App\` mapuje na `src/` ručně psaný PSR-4 autoloader o 26 řádcích.

5. **Routing bez jediného `.htaccess`.** Žádný `mod_rewrite` ani přepisovací pravidla. Apache `FallbackResource /index.php` pošle každou neexistující cestu na front controller se zachovaným `REQUEST_URI`, který pak čte `Router`.

6. **Přepínání stavu bez čtení.** `toggle()` nepotřebuje nejdřív načíst úkol — přepne ho jedním SQL: `UPDATE tasks SET done = 1 - done WHERE id = :id`.

7. **Databáze, která se postaví sama.** `data/tasks.sqlite` neexistuje v repu — vznikne, vytvoří schéma a naseeduje se při úplně prvním requestu. Prvním ukázkovým úkolem je příznačně *„Naučit se Claude Code"*.

---

😄 Pro pobavení: [7 vtipů o projektu (in English)](JOKE.md)

→ Pokračuj na [Díl 06 — Git worktrees + agentní workflow](../t420-06-git-worktrees-cld/README.md)
