# Architektura projektu

Dvě nezávislé roviny ve stejném repu:

1. **PHP aplikace „Task Library"** (`src/`, `public/`, `templates/`) — vrstvená CRUD appka nad SQLite. Slouží jako realistický objekt, na kterém se demonstruje rovina 2; není cílem sama o sobě.
2. **Automatizace Claude Code** (`.claude/`) — event-driven hooky kolem vývoje. Vlastní téma dílu 05 a hlavní hodnota repa.

Dokument popisuje kontrakty, invarianty a záměrné kompromisy obou rovin. Kód sám je komentovaný, tady je řeč o rozhodnutích, ne o řádcích.

---

## 1. Request pipeline

Front controller + ruční vrstvení, žádný framework, žádný Composer. Jeden směr toku, každá vrstva mluví jen se sousedem směrem dolů:

```
HTTP request
  → public/index.php        front controller: instancuje Router, registruje routy, dispatch()
  → Core\Router             (method, path) → callable; extrahuje {id} jako named capture
  → Controller\TaskController  I/O adaptér: $_POST ↔ Service, render šablony / 302 redirect
  → Service\TaskService     business pravidla + validace (jediná vrstva s doménovou sémantikou)
  → Repository\TaskRepository  jediné místo se SQL, za TaskRepositoryInterface
  → Core\Database           statické PDO/SQLite připojení (singleton) + migrace/seed
  → data/tasks.sqlite
```

Napříč vrstvami cestuje jediná doménová entita **`Model\Task`** (`Task::fromRow()` hydratuje z DB řádku).

### Invarianty vrstvení

- **Controller → Service → Repository, nikdy přeskočit.** Controller nesmí znát SQL ani `Database`, Repository nesmí znát HTTP ani doménová pravidla. To udržuje veškerou logiku (validace, výpočet `progress`) na jednom místě a nechává krajní vrstvy hloupé a nahraditelné.
- Tok je jednosměrný. Neexistují zpětné vazby ani sdílený stav mezi requesty kromě DB a singletonu připojení.

### Kde je testovací šev (a kde není)

DI existuje **jen na hranici Service**: `TaskService(?TaskRepositoryInterface $repo = null)` — v testu injektuješ in-memory fake a ověříš business logiku bez DB. To je záměrný a jediný šev.

Pozor na jeho hranici: default `new TaskRepository()` váže Service na konkrétní implementaci, dokud fake explicitně neinjektuješ (poor-man's DI, žádný kontejner). A `TaskRepository` sám volá `Database::connection()` **staticky** — repository v izolaci netestovatelné, integrační test poběží proti reálnému SQLite souboru. Pro účel projektu dostačuje; při rozšiřování je tohle první místo, kde by připojení mělo jít dovnitř konstruktorem.

---

## 2. Komponenty `src/`

| Komponenta | Kontrakt | Nesmí |
|---|---|---|
| `Core\Router` | `add(method, pattern, handler)`, `dispatch(method, uri)`; `{id}` → `(?P<id>\d+)`, handler dostane `?int $id` | znát doménu |
| `Controller\TaskController` | čte `$_POST`, deleguje na Service, renderuje / redirectuje | dotýkat se Repository/DB, nést pravidla |
| `Service\TaskService` | `list/add/toggle/remove/progress`; `add()` trimuje a hází `InvalidArgumentException` na prázdný title | znát SQL nebo HTTP |
| `Repository\TaskRepository` | implementuje `TaskRepositoryInterface`; `all()` přes `query()`, mutace přes prepared statements | nést business logiku |
| `Core\Database` | `connection(): PDO` singleton, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, idempotentní `migrate()` | být volaná odjinud než z Repository |
| `Model\Task` | `id`/`createdAt` readonly, `title`/`done` mutable; `fromRow()` factory | mít chování vázané na DB/HTTP |

**Rozhraní vs. použití:** `find(int): ?Task` Service přímo nevolá, ale `create()` ho používá interně — po `INSERT` přečte řádek přes `lastInsertId()` a vrátí hydratovaný `Task` (jinak `RuntimeException`). Není to tedy mrtvý kód, jen zatím nemá vlastní routu.

**Autoloading:** ruční PSR-4 v `autoload.php`, prefix `App\` → `src/`, `strncmp` guard + `is_file`. Žádný Composer, žádné dev závislosti.

---

## 3. Persistence

Jedna tabulka `tasks`, DDL v `Database::migrate()` přes `CREATE TABLE IF NOT EXISTS`:

| Sloupec | Typ | Poznámka |
|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | |
| `title` | TEXT NOT NULL | |
| `done` | INTEGER NOT NULL DEFAULT 0 | 0/1, v PHP cast na `bool` v `Task::fromRow()` |
| `created_at` | TEXT NOT NULL | ISO 8601, `date('c')` |

**Charakteristiky, které stojí za pozornost:**

- `migrate()` běží při každém *cold* připojení (jednou za proces). DDL je idempotentní; seed se řídí `SELECT COUNT(*)` — TOCTOU race na prázdné tabulce mezi souběžnými prvními requesty existuje, ale v single-writer SQLite je prakticky neškodný (nanejvýš duplicitní seed, což se v praxi neděje kvůli file locku).
- `toggle()` je jediný `UPDATE ... SET done = 1 - done` — žádný read-modify-write, žádný lost update.
- Bez explicitních transakcí a bez zámků na aplikační úrovni — spoléhá se na SQLite file-level locking. Odpovídá rozsahu; ne vzor pro concurrent write-heavy.
- `progress()` volá `repository->all()` znovu, nezávisle na `list()` → index render vyvolá **dva** `SELECT *`. Při této velikosti zanedbatelné; při škálování by šlo agregovat v SQL (`SUM(done)`), tady je záměrně čitelnost > efektivita.

---

## 4. Routing a HTTP sémantika

Routy registruje `public/index.php`, obsluhuje `TaskController`:

| Metoda | Cesta | Handler | Výsledek |
|---|---|---|---|
| GET | `/` | `index()` | HTML seznam + progress bar |
| POST | `/tasks` | `store()` | create → 302 `/` |
| POST | `/tasks/{id}/toggle` | `toggle()` | toggle → 302 `/` |
| POST | `/tasks/{id}/delete` | `destroy()` | delete → 302 `/` |

- **PRG (Post/Redirect/Get):** každá mutace končí redirectem na `/`, refresh tedy neopakuje POST. `redirect()` posílá jen `Location` bez status kódu → PHP defaultuje na **302** (ne 303).
- Router matchuje na `parse_url(..., PHP_URL_PATH)` — query string se ignoruje. Nematchnuté cesty → `404` s hlavičkou názvu projektu.
- **Mimo scope (vědomě):** žádná CSRF ochrana, autentizace, ani output escaping vrstva mimo šablonu. Pro výukovou lokální appku OK; pro produkci by to byly první tři TODO.

---

## 5. Runtime (Docker)

PHP **jen v Dockeru** (workspace pravidlo, na hostiteli žádné PHP):

```
docker-compose.yml → služba web (build z Dockerfile)
  ├── FROM php:8.3-apache + pdo_sqlite
  ├── 8080 → 80
  ├── bind-mount .:/var/www/html   (live edit bez rebuildu)
  └── vhost docker/000-default.conf
       ├── DocumentRoot → public/
       └── FallbackResource /index.php   (vše mimo reálné soubory → front controller)
```

- **`FallbackResource` místo `mod_rewrite`:** direktiva žije přímo ve vhostu (`<Directory>`), ne v `.htaccess` — neexistující cesty jdou na `public/index.php` se zachovaným `REQUEST_URI`. Méně pohyblivých částí než rewrite ruleset. (Conf sice má `AllowOverride All`, ale `FallbackResource` ho ke své funkci nepotřebuje.)
- **`data/` má `777`:** SQLite zapisuje `www-data` (jiné UID než host uživatel bind-mountu). `data/tasks.sqlite` vzniká a seeduje se sám při prvním requestu, je v `.gitignore`.

Start: `docker compose up -d --build` → <http://localhost:8080>.

---

## 6. Automatizace Claude Code (`.claude/`)

Rovina, která nemění runtime aplikace, ale řídí vývojovou smyčku kolem ní. Konfigurace `.claude/settings.json`, skripty `.claude/hooks/`.

| Hook | Událost (matcher) | Kontrakt |
|---|---|---|
| `php-lint.sh` | `PostToolUse` (`Edit\|Write`) | `php -l` nad `.php`; syntax error → `exit 2` + stderr (Claude to čte jako blokující chybu) |
| `session-start.sh` | `SessionStart` | informativní výpis počtu PHP souborů + stavu gitu |
| `user-prompt-guard.sh` | `UserPromptSubmit` | heuristika na destruktivní záměr → varování, vždy `exit 0` (nikdy neblokuje) |

**Vstupní kontrakt hooků:** kontext primárně z env (`CLAUDE_FILE_PATH`, `CLAUDE_TOOL_*`); **fallback** parsuje JSON na stdin (`grep`+`sed` na `file_path`/`prompt`). Env názvy se mezi verzemi Claude Code liší → stdin JSON je stabilní zdroj pravdy. Drž tenhle dvojitý vzor při úpravách.

**Exit-kód sémantika je nosná:** `exit 2` = tvrdá blokující signalizace, `exit 0` = neblokující info/varování. `php-lint.sh` navíc detekuje absenci `php` na hostiteli (PHP je jen v Dockeru) a v tom případě lint **tiše přeskočí** — jinak by po každé editaci `.php` padal falešný `php: command not found` (`exit 2`). Reálnou syntax ověříš v kontejneru: `docker compose exec web php -l <soubor>`.

### Rozhodovací tabulka: hook vs. alternativy

| Cíl | Nástroj |
|---|---|
| akce **automaticky** na událost (lint po editaci) | **Hook** |
| pojmenovaný **postup na vyžádání** | **Skill** |
| aby Claude **znal** konvence projektu | **CLAUDE.md** |
| natvrdo **zakázat** akci | **permissions.deny** nebo **PreToolUse** hook |

---

## TL;DR

Vrstvená PHP appka (`Router → Controller → Service → Repository → Database`, entita `Task` napříč, DI šev na hranici Service) na PHP 8.3/Apache s `public/` jako DocumentRoot a SQLite persistencí. Kolem ní deterministická, event-driven automatizace Claude Code v `.claude/` postavená na exit-kód sémantice a env/stdin fallbacku — a právě ta je předmětem dílu, ne appka.
