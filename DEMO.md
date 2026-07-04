# DEMO — jak tuhle aplikaci prezentovat úplnému laikovi

> Scénář prezentace pro publikum, které **nezná AI, nezná Claude a nikdy neslyšelo slovo „hook"**.
> Cíl: za ~15 minut ukázat běžící appku a hlavně pochopit **jeden silný nápad** — že počítač
> umí sám hlídat kvalitu práce, kterou sám odvedl.
>
> Dokument je psaný jako **režie**: co udělat, co ukázat, co říct nahlas. Text „říct nahlas" je
> v uvozovkách — ber ho jako inspiraci, ne jako scénář ke čtení.

---

## 0. Než začneš (5 minut o samotě, před publikem)

Připrav si, ať nic nepadá naživo:

```bash
cd t420-05-hooks-automatizace-cld
docker compose up -d --build        # spustí web na pozadí
```

Ověř, že vše žije:

- V prohlížeči otevři **<http://localhost:8080>** → musíš vidět seznam úkolů „📚 Task Library".
- Měj otevřené **dvě okna vedle sebe**: vlevo **prohlížeč**, vpravo **terminál** (ať publikum vidí obojí).

**Zlaté pravidlo:** publikum nesmí koukat na prázdnou obrazovku a čekat. Všechno, co může padat nebo se dlouho načítat, měj hotové předem.

> 💡 Kdyby nešel port 8080 (obsazený), změň v `docker-compose.yml` řádek `"8080:80"` např. na `"8090:80"` a použij adresu s `:8090`.

---

## 1. Rámec: o čem to celé je (2 minuty, bez počítače)

Nespouštěj hned nic. Nejdřív dej publiku **kotvu**, ať ví, na co se budou dívat. Tři pojmy, každý přes analogii z běžného života.

### „Co je to za appku?"
> „Ukážu vám úplně obyčejný seznam úkolů — jako nákupní lístek. Přidáš úkol, odškrtneš hotové, smažeš. Nic převratného. **Zajímavé není CO to umí, ale JAK to vzniklo a jak se to hlídá.**"

### „Co je AI asistent na programování?" (analogie: velmi schopný pomocník)
> „Programování normálně znamená psát počítači instrukce ve složitém jazyce. Dnes existuje pomocník, kterému **řeknete běžnou větou, co chcete** — ‚přidej tlačítko na smazání úkolu' — a on ten složitý kód napíše za vás. Představte si mimořádně rychlého a sečtělého asistenta, kterému diktujete a on píše. Tenhle konkrétní asistent se jmenuje **Claude** a pracuje přímo v černém okně programátora (terminálu)."

### „Co je hook?" (analogie: automatický spouštěč)
> „Hook je **automatické pravidlo typu ‚vždy když se stane A, samo se udělá B'**. Znáte to z běžného života:
> - Vždy když **uložíte dokument**, Word vám **podtrhne překlepy**.
> - Vždy když **nastartujete auto**, samo se **zkontrolují světla a pásy**.
> - Vždy když někdo **otevře dveře**, spustí se **alarm**.
>
> Nikdo to nespouští ručně, nikdo na to nemyslí — prostě se to stane. A přesně tohle si dnes ukážeme u programování."

Tři věty na závěr úvodu, ať mají publikum připravené na pointu:
> „Takže: máme jednoduchou appku. Napsal ji AI asistent. A kolem něj běží automatická pravidla, která hlídají, že neudělá chybu. Pojďme se na to podívat."

---

## 2. Demo část A — appka funguje (3 minuty, hmatatelné)

Začni tím nejkonkrétnějším. Ať publikum vidí, že jde o **reálnou, fungující věc**, ne o teorii.

V prohlížeči na <http://localhost:8080>:

1. **Ukaž seznam.** „Tohle je ta appka. Tři úkoly, jeden už hotový (přeškrtnutý). Nahoře je pruh, kolik procent je splněno."
2. **Přidej úkol.** Do políčka napiš `Ukázat hooky publiku`, klikni **Přidat**. „Přibyl nahoře, procenta se přepočítala."
3. **Odškrtni ho.** Klikni **✓ Hotovo**. „Přeškrtlo se, pruh povyskočil."
4. **Smaž ho.** Klikni **🗑**. „Zmizel."

> Pointa části A: „Funguje to jako každá appka, co znáte. **Teď to zajímavé — jak to vzniklo.**"

*(Pokud chceš, dodej: „Celý tenhle kód nenapsal ručně člověk řádek po řádku — nadiktoval ho ten AI asistent.")*

---

## 3. Demo část B — kouzlo hooku naživo (4 minuty, tohle je vrchol)

Tady ukážeš **automatické pravidlo v akci**. Máš dvě varianty podle toho, jestli si troufáš na živé AI. **Doporučuju variantu 1** — je neprůstřelná a nepotřebuje nic vysvětlovat o AI.

### Varianta 1 (bezpečná, doporučená): „hlídač u dveří"

Máme hook, který **varuje před nebezpečným příkazem**. Předveď ho přímo v terminálu:

```bash
echo '{"prompt":"smaž databázi tasks.sqlite"}' | bash .claude/hooks/user-prompt-guard.sh
```

Objeví se:
```
⚠️  Pozor: prompt zmiňuje destruktivní operaci. Zkontroluj záměr.
```

> „Napsal jsem počítači něco jako ‚smaž databázi'. A automaticky — bez toho, aby to kdokoli spustil — vyskočilo **varování**. To je ten hook. Hlídač u dveří, který si všimne nebezpečného slova."

Pak ukaž, že u **neškodného** příkazu je ticho:

```bash
echo '{"prompt":"přidej úkol nakoupit mléko"}' | bash .claude/hooks/user-prompt-guard.sh
```

> „Nic. Žádné varování. Hlídač reaguje jen na nebezpečí, jinak nechá práci plynout."

### Varianta 2 (efektnější, ale potřebuje kontejner): „korektor pravopisu pro kód"

Ukaž hook, který po každé úpravě zkontroluje, jestli je kód správně napsaný (jako korektor pravopisu). Naschvál vyrobíme „překlep":

```bash
# schválně rozbitý soubor:
printf '<?php\nfunction ( {\n' > data/rozbite.php
docker compose exec -T -e CLAUDE_FILE_PATH=/var/www/html/data/rozbite.php web \
  bash /var/www/html/.claude/hooks/php-lint.sh
echo "návratový kód: $?"
rm -f data/rozbite.php
```

Objeví se `❌ PHP lint selhal…` a chybová hláška.

> „Vyrobil jsem v kódu ‚překlep'. Automatická kontrola ho **okamžitě našla a nahlásila** — přesně jako když Word podtrhne špatně napsané slovo. Kdyby ten kód psal AI asistent, hned by viděl, že udělal chybu, a opravil by ji. **Nemůže po sobě nechat nepořádek, aniž by o tom věděl.**"

---

## 4. Pointa — co si mají odnést (2 minuty)

Zavři počítač nebo se otoč k publiku. Jedna myšlenka, řečená jednoduše:

> „Viděli jste jednoduchý seznam úkolů. Ale ten skutečný příběh je tohle:
> **kód dnes umí psát AI asistent — a kolem něj běží automatická pravidla, která hlídají, že nic nepokazí.**
> Je to jako kdyby psal velmi rychlý pomocník a přes rameno mu koukal neúnavný korektor,
> který se nikdy neunaví a nikdy nezapomene zkontrolovat.
> Ta pravidla se jmenují **hooky** — ‚vždy když se stane tohle, automaticky udělej tamto'."

Volitelný silný závěr:
> „A možná nejzajímavější detail: ten korektor kontroluje práci, kterou napsal **ten samý AI**. Nástroj hlídá sám sebe."

---

## 5. Časový plán (celkem ~15 min)

| Část | Co | Čas |
|---|---|---|
| 1 | Rámec + tři analogie (bez PC) | 2 min |
| 2 | Demo A — appka funguje | 3 min |
| 3 | Demo B — hook naživo | 4 min |
| 4 | Pointa | 2 min |
| — | Otázky | zbytek |

*(Kratší verze na 5 minut: vynech část 2, jdi rovnou na jednu appku + Variantu 1 hooku + pointu.)*

---

## 6. Přílohy pro klidné vystoupení

### Slovníček pro publikum (kdyby padla otázka)
| Slovo | Řekni takhle |
|---|---|
| **AI / umělá inteligence** | „Program, který rozumí běžné řeči a umí za vás vytvořit text nebo kód." |
| **Claude** | „Jméno toho konkrétního AI asistenta na programování." |
| **Hook** | „Automatické pravidlo ‚vždy když A, udělej B'. Nikdo ho nespouští ručně." |
| **Kód** | „Instrukce, podle kterých počítač appku spustí." |
| **Terminál** | „Černé okno, kde programátoři píší příkazy místo klikání." |
| **Databáze** | „Sešit, kam si appka ukládá úkoly, aby nezmizely po zavření." |

### Časté otázky a odpovědi
- **„Nahradí to programátory?"** → „Spíš je to zrychlí — jako když kalkulačka nenahradila účetní, ale ušetřila jim počítání. Člověk pořád rozhoduje, co se má postavit a jestli je to správně."
- **„Jak AI ví, co má napsat?"** → „Naučila se z obrovského množství existujícího kódu a textu. Vy jí řeknete cíl větou, ona navrhne řešení."
- **„Co když AI udělá chybu?"** → „Přesně proto jsou ty hooky. Automaticky ji zachytí, jak jsme viděli u ‚korektora'."
- **„Musím tomu rozumět, abych to používal?"** → „Appku ne — funguje jako každá jiná. Tvorbu ano, ale míň než dřív, protože diktujete běžnou řečí."

### Když něco selže (záchrana naživo)
- **Web se nenačte** → v terminálu `docker compose up -d`, chvíli počkej, obnov stránku. Krajně: `docker compose restart web`.
- **Port obsazený** → viz poznámka v části 0 (změň `8080` na `8090`).
- **Hook „nic nedělá"** → u Varianty 1 zkontroluj, že příkaz obsahuje slovo `smaž`/`drop`/`rm .env`; guard reaguje jen na ně.
- **Úplná nouze** → přepni na obrázky/snímky obrazovky, které si předem uděláš z bodů 2 a 3. Vždy měj zálohu.

---

### Shrnutí jednou větou pro tebe jako prezentujícího
Ukazuješ obyčejný seznam úkolů, aby publikum na konci pochopilo jednu neobyčejnou věc: **kód dnes píše AI a automatická pravidla (hooky) hlídají, že nic nepokazí.**
