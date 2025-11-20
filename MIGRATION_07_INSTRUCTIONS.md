# Migrace 07: Ignorování odchylek cen

## Přehled
Tato migrace přidává funkci "Ignorovat odchylku" pro jednotlivé ceny měřidel. Administrátor může označit konkrétní cenu jako ignorovanou, což způsobí, že systém nebude kontrolovat ani zobrazovat odchylku od vypočtené inflační ceny.

## Databázová změna

### SQL příkaz k provedení:
```sql
ALTER TABLE ceny_meridel
ADD COLUMN ignorovat_odchylku TINYINT(1) DEFAULT 0
COMMENT 'Pokud je 1, odchylka této ceny se nebude kontrolovat a zobrazovat'
AFTER je_manualni;
```

### Postup provedení:

#### Varianta 1: Přes phpMyAdmin
1. Přihlaste se do phpMyAdmin
2. Vyberte databázi `c3meridla` (produkce) nebo `cmi_inflace` (vývoj)
3. Klikněte na záložku "SQL"
4. Zkopírujte a vložte výše uvedený SQL příkaz
5. Klikněte na tlačítko "Provést"

#### Varianta 2: Přes příkazový řádek MySQL
```bash
mysql -u root -p
USE c3meridla;  # nebo cmi_inflace pro vývojovou databázi
ALTER TABLE ceny_meridel ADD COLUMN ignorovat_odchylku TINYINT(1) DEFAULT 0 COMMENT 'Pokud je 1, odchylka této ceny se nebude kontrolovat a zobrazovat' AFTER je_manualni;
```

#### Varianta 3: Import SQL souboru
```bash
mysql -u root -p c3meridla < sql/07_ignorovat_odchylku.sql
```

## Ověření migrace

Po provedení migrace ověřte úspěšné přidání sloupce:

```sql
DESCRIBE ceny_meridel;
```

Měli byste vidět nový sloupec `ignorovat_odchylku` s těmito vlastnostmi:
- Typ: `tinyint(1)`
- Null: `NO`
- Default: `0`
- Pozice: Po sloupci `je_manualni`

## Funkce v aplikaci

### Backend (web/includes/functions.php)
- `zjistiOdchylkuCeny()` - přijímá parametr `$ignorovatOdchylku`, při true vrací `je_odchylna=false`
- `maOdchylneCeny()` - načítá `ignorovat_odchylku` z databáze a předává do zjistiOdchylkuCeny()
- `saveCena()` - ukládá hodnotu `ignorovat_odchylku` do databáze
- `getCenyMeridla()` - vrací `ignorovat_odchylku` společně s ostatními daty ceny

### Frontend

#### detail.php
- Zobrazuje 🔕 ikonu u cen, které mají ignorování zapnuté
- Pod typem ceny zobrazuje text "Ignorovat odchylku"
- Nezvýrazňuje červeně řádky s ignorovanými odchylkami

#### edit.php
- Checkbox "Ignorovat odchylku" u každého roku v editačním formuláři
- Nápověda: "Pokud je zaškrtnuto, nebude se kontrolovat odchylka od vypočtené ceny"
- Zachovává stav checkboxu při editaci

## Testování

1. **Test nastavení ignorování:**
   - Otevřete měřidlo s odchylkou ceny
   - Klikněte na "Editovat"
   - U roku s odchylkou zaškrtněte "Ignorovat odchylku"
   - Uložte změny
   - Ověřte, že se červené zvýraznění a ⚠ ikona již nezobrazuje
   - Ověřte, že se zobrazuje 🔕 ikona a badge "Ignorovat odchylku"

2. **Test odebrání ignorování:**
   - U ignorované ceny odškrtněte checkbox
   - Uložte změny
   - Ověřte, že se červené zvýraznění a ⚠ ikona opět zobrazuje

3. **Test v přehledu:**
   - Ověřte, že měřidlo s ignorovanými odchylkami se v hlavním přehledu nezobrazuje červeně
   - Ověřte, že se nezvýrazňuje, i když má odchylku od vypočtené ceny

## Rollback

V případě problémů lze migraci vrátit zpět příkazem:

```sql
ALTER TABLE ceny_meridel DROP COLUMN ignorovat_odchylku;
```

⚠️ **Pozor:** Tímto se ztratí všechny uložené informace o ignorovaných odchylkách.

## Související commity

- Commit: b9ef8b5
- Commit message: "feat: add ignore deviation checkbox feature"
- Datum: 2024

## Poznámky

- Sloupec je typu TINYINT(1) pro úsporu místa (1 byte na záznam)
- Výchozí hodnota 0 znamená, že standardně se odchylky kontrolují
- Funkce je dostupná pouze administrátorům přes editační formulář
- Ignorování se nastavuje per cena, ne per měřidlo - umožňuje flexibilitu
