# Architektura projektu

Projekt má **dvě roviny**, které je dobré nezaměňovat:

1. **PHP aplikace „Task Library"** (`src/`, `public/`, `templates/`) — malá webová appka na správu úkolů. Je předmětem výuky, ne cílem sama o sobě.
2. **Automatizace Claude Code** (`.claude/`) — hooky, které se dějí kolem vývoje aplikace. Tohle je vlastní téma dílu 05.

Tento dokument popisuje obě roviny a jak spolu drží.

---

## 1. Běhový řetězec aplikace

Aplikace je **vrstvená**, bez frameworku a bez Composeru. Každý HTTP požadavek prochází pevným řetězcem, kde každá vrstva má jednu zodpovědnost a mluví jen se sousedem:

```
HTTP request
   │
   ▼
public/index.php ......... vstupní bod (front controller): sestaví Router, zaregistruje routy
   │
   ▼
Core\Router .............. (metoda + cesta) → callable; parsuje {id} v cestě
   │
   ▼
Controller\TaskController  orchestruje request, čte $_POST, volá Service, vrací view/redirect
   │
   ▼
Service\TaskService ...... business logika a validace (prázdný title, výpočet progress)
   │
   ▼
Repository\TaskRepository  jediné místo se SQL (přes TaskRepositoryInterface)
   │
   ▼
Core\Database ............ singleton PDO připojení k SQLite + auto-migrace/seed
   │
   ▼
data/tasks.sqlite
```

Napříč vrstvami putuje doménová entita **`Model\Task`** — nese data úkolu a přes `Task::fromRow()` se plní z DB řádku.

### Klíčové pravidlo vrstvení

> **Controller nikdy nesahá na Repository přímo — jen přes Service.**

Proč to má smysl: veškerá pravidla (validace, výpočty) žijí na jednom místě v Service a nemohou se rozejít mezi voláními. Controller zůstává hloupý (jen překládá HTTP ↔ volání Service), Repository zůstává hloupé (jen SQL). Když se změní pravidlo, měníš jeden soubor.

### Dependency injection a testovatelnost

`TaskService` přijímá `TaskRepositoryInterface` konstruktorem (default `new TaskRepository()`). Díky rozhraní lze v testu podstrčit in-memory fake repository a testovat business logiku bez databáze. To je jediný „šev" v jinak přímočaré aplikaci a je tam záměrně.

---

## 2. Komponenty `src/` podrobně

| Komponenta | Zodpovědnost | Nesmí |
|---|---|---|
| `Core\Router` | mapuje `(HTTP metoda + cesta)` na callable; podpora `{id}` přes regex | znát business logiku |
| `Controller\TaskController` | čte vstup (`$_POST`), volá Service, renderuje šablonu nebo redirect | sahat na Repository/DB, obsahovat pravidla |
| `Service\TaskService` | validace, business rozhodnutí (`add()` odmítá prázdný title, `progress()` počítá % hotových) | znát SQL, znát HTTP |
| `Repository\TaskRepository` | CRUD nad tabulkou `tasks` přes prepared statements | obsahovat business logiku |
| `Core\Database` | jedno PDO připojení (singleton), schéma + seed při prvním běhu | být volané odjinud než z Repository |
| `Model\Task` | doménová entita úkolu (`id`, `title`, `done`, `createdAt`) | mít chování navázané na DB/HTTP |

**Autoloading:** `autoload.php` je ruční PSR-4 loader — namespace `App\` → adresář `src/`. Např. `App\Controller\TaskController` → `src/Controller/TaskController.php`. Žádný Composer.

---

## 3. Datový model

Jediná tabulka `tasks`, vytvořená automaticky v `Database::migrate()`:

| Sloupec | Typ | Poznámka |
|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | |
| `title` | TEXT NOT NULL | název úkolu |
| `done` | INTEGER NOT NULL DEFAULT 0 | 0/1, v PHP se přetypuje na `bool` |
| `created_at` | TEXT NOT NULL | ISO 8601 (`date('c')`) |

Při prázdné tabulce se naseedují tři ukázkové úkoly. `toggle()` přepíná stav chytře přes `done = 1 - done` (žádné čtení-pak-zápis).

---

## 4. Routy

Definované ve `public/index.php`, obsluhované `TaskController`:

| Metoda | Cesta | Akce | Výsledek |
|---|---|---|---|
| GET | `/` | `index()` | HTML seznam úkolů + progress bar |
| POST | `/tasks` | `store()` | přidá úkol → redirect `/` |
| POST | `/tasks/{id}/toggle` | `toggle()` | přepne hotovo → redirect `/` |
| POST | `/tasks/{id}/delete` | `destroy()` | smaže úkol → redirect `/` |

Používá se vzor **PRG (Post/Redirect/Get)** — mutace vždy končí redirectem na `/`, takže refresh stránky nepošle formulář znovu. Neexistující cesty vrací `Router` jako 404. Obě stránky (seznam i 404) nesou v hlavičce název projektu.

---

## 5. Runtime (Docker)

PHP je záměrně **jen v Dockeru** (workspace pravidlo — na hostiteli žádné PHP).

```
docker-compose.yml
   └── služba `web`  (build z Dockerfile)
        ├── FROM php:8.3-apache  + rozšíření pdo_sqlite
        ├── port 8080 → 80
        ├── bind-mount  .:/var/www/html   (živé editace bez rebuildu)
        └── Apache vhost (docker/000-default.conf)
             ├── DocumentRoot → public/
             └── FallbackResource /index.php  ← všechny neexistující cesty na front controller
```

**Proč `FallbackResource` místo mod_rewrite:** pošle každou cestu, která není reálný soubor, na `public/index.php` se zachovaným `REQUEST_URI`, který pak čte `Router`. Čistší než `.htaccess` a nevyžaduje `AllowOverride`.

**Práva k `data/`:** adresář má `777`, protože SQLite soubor zapisuje uživatel `www-data` (jiný UID než host). `data/tasks.sqlite` vzniká a seeduje se sám při prvním requestu a je mimo git (`.gitignore`).

Spuštění: `docker compose up -d --build`, pak <http://localhost:8080>.

---

## 6. Automatizace Claude Code (`.claude/`)

Souběžná rovina, která nemění chování aplikace, ale vývoj kolem ní. Konfigurace v `.claude/settings.json`, skripty v `.claude/hooks/`.

| Hook | Událost | Kdy | Co dělá |
|---|---|---|---|
| `php-lint.sh` | `PostToolUse` (matcher `Edit\|Write`) | po editaci souboru | `php -l` nad `.php`; při chybě `exit 2` + stderr → Claude to hned vidí |
| `session-start.sh` | `SessionStart` | na startu session | vypíše počet PHP souborů a stav gitu |
| `user-prompt-guard.sh` | `UserPromptSubmit` | při odeslání promptu | varuje (`exit 0`) na destruktivní záměr |

Společný vzor všech hooků: kontext čtou z **env** (`CLAUDE_FILE_PATH`, …), a když env chybí, **fallback** vytáhne data z **JSON na stdin**. Env názvy se mezi verzemi liší, stdin JSON je nejspolehlivější zdroj.

**Sémantika exit kódů** je nosná: `exit 2` = tvrdá signalizace chyby Claudovi (php-lint), `exit 0` = jen informace/varování (guard). `php-lint.sh` navíc detekuje, že na hostiteli `php` není (jen v Dockeru), a v tom případě lint **tiše přeskočí** místo falešného poplachu — syntax se ověří v kontejneru (`docker compose exec web php -l <soubor>`).

### Kdy hook, kdy něco jiného

| Chci… | Nástroj |
|---|---|
| aby se něco stalo **automaticky** na událost (lint po editaci) | **Hook** |
| pojmenovaný **postup na vyžádání** | **Skill** |
| aby Claude **znal** konvence projektu | **CLAUDE.md** |
| natvrdo **zakázat** akci | **permissions.deny** nebo **PreToolUse hook** |

---

## Shrnutí jednou větou

Vrstvená PHP appka (`Router → Controller → Service → Repository → Database`, entita `Task` napříč) běžící v Docker/Apache s `public/` jako DocumentRoot, obklopená deterministickou automatizací Claude Code v `.claude/` — přičemž hlavní hodnota projektu je právě ta automatizace, ne appka samotná.
