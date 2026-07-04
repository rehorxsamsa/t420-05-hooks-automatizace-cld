# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Co to je

Díl 05 výukové série o Claude Code. Téma je **hooks & automatizace** — `README.md` je učební text. PHP aplikace v `src/` (knihovna úkolů, „Task Library") slouží jen jako reálný objekt, na kterém se hooky demonstrují. Těžiště projektu je `.claude/`, ne aplikace.

## Hooks — jádro projektu

Nakonfigurované v `.claude/settings.json`, skripty v `.claude/hooks/`. **Tři aktivní hooky:**

| Skript | Událost | matcher | Co dělá |
|---|---|---|---|
| `php-lint.sh` | `PostToolUse` | `Edit\|Write` | `php -l` po editaci `.php`; při chybě `exit 2` + stderr |
| `session-start.sh` | `SessionStart` | — | vypíše počet PHP souborů a stav gitu |
| `user-prompt-guard.sh` | `UserPromptSubmit` | — | varuje (jen `exit 0`) na destruktivní prompt |

Konvence platná pro všechny hooky zde: čtou `CLAUDE_FILE_PATH` / `CLAUDE_TOOL_*` z env, a když env chybí, **fallback** vytáhne data z JSON na stdin (`grep`+`sed` na `file_path`, resp. `prompt`). Env názvy se mezi verzemi liší, stdin JSON je nejspolehlivější — drž tenhle dvojitý vzor při úpravách.

Sémantika exit kódů, na které hooky spoléhají: `exit 2` = tvrdá signalizace chyby Claudovi (php-lint), `exit 0` = jen informace/varování (guard). Neměň to bez záměru.

Ruční ověření hooků:
```bash
chmod +x .claude/hooks/*.sh
CLAUDE_FILE_PATH="src/Service/TaskService.php" bash .claude/hooks/php-lint.sh   # OK
echo '{"prompt":"smaž databázi tasks.sqlite"}' | bash .claude/hooks/user-prompt-guard.sh   # varování
```

> `php-lint.sh` volá `php` přímo (na PATH). To je výjimka z workspace pravidla „PHP jen v Dockeru" — tenhle projekt Docker nemá a hook počítá s lokálním `php` pro `php -l`. Pokud lokální PHP není, hook lint jen tiše přeskočí (soubor existuje, `php` chybí → nenulový výstup); pro reálné spuštění se PHP očekává dostupné v prostředí, kde běží Claude Code.

## Architektura aplikace (src/)

Vrstvená, bez frameworku a bez Composeru. PSR-4 autoload `App\` → `src/` řeší ruční `autoload.php`.

```
Router → Controller → Service → Repository → Database (PDO/SQLite)
                          ↑ Model\Task (doménová entita)
```

Pravidla vrstev (jsou i v komentářích kódu — dodržuj je):
- **Controller** orchestruje request a render, **nikdy nesahá na repository přímo** — jen přes Service.
- **Service** = business logika a validace (`add()` odmítá prázdný title).
- **Repository** = jediné místo s SQL. Implementuje `TaskRepositoryInterface` (Service ho bere přes DI, default `new TaskRepository()`).
- **Database** = singleton PDO připojení; při prvním běhu vytvoří schéma a naseeduje tři úkoly (`migrate()`).

## Spuštění (Docker)

PHP je jen v Dockeru (workspace pravidlo). Web běží na **portu 8080**, DocumentRoot je `public/`, routing přes Apache `FallbackResource /index.php` (viz `docker/000-default.conf`).

```bash
docker compose up -d --build       # start (poprvé build s pdo_sqlite)
docker compose down                # stop
docker compose logs -f web         # logy
```

App: <http://localhost:8080>. Routy definuje `public/index.php`: `GET /`, `POST /tasks`, `POST /tasks/{id}/toggle`, `POST /tasks/{id}/delete`.

**PHP příkazy (lint, testy) spouštěj v kontejneru**, ne na hostu:
```bash
docker compose exec web php -l src/Service/TaskService.php
```

`data/` je bind-mount s právy `777`, aby do něj `www-data` (jiný UID než host) mohl zapsat SQLite. `data/tasks.sqlite` vznikne a naseeduje se sám při prvním requestu.

> ⚠️ Hook `php-lint.sh` (`PostToolUse`) volá `php` **na hostu**, kde ale žádné není → po každé editaci `.php` skončí `exit 2` s `php: command not found`. Je to falešný poplach (ne syntaktická chyba); syntax ověř `docker compose exec web php -l <soubor>`.

## Jazyk a workspace pravidla

Odpovídej **česky**. Platí nadřazená pravidla z `/home/q/projects/CLAUDE.md`: nikdy `git push` ani žádné remote operace (lokální commity OK jen na pokyn); hlavní větev je `main`.
