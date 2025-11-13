# Export a Import databáze přes phpMyAdmin

## Export z phpMyAdmin

### Krok 1: Export struktury a dat
1. Otevři phpMyAdmin (http://localhost/phpmyadmin)
2. Vyber databázi **cmi_inflace** v levém menu
3. Klikni na záložku **Export**
4. Zvol **Vlastní** metodu exportu
5. V sekci **Tabulky** vyber všechny tabulky
6. V sekci **Výstup** zaškrtni:
   - ☑ Uložit výstup do souboru
   - Formát: SQL
   - Komprese: zip
7. V sekci **Volby pro export objektů**:
   - Zaškrtni: ☑ DROP TABLE
   - Zaškrtni: ☑ IF NOT EXISTS
   - Odškrtni: ☐ Zobrazit komentáře (aby se vyhnul DEFINER)
8. **DŮLEŽITÉ**: V sekci **Volby pro export dat**:
   - Zaškrtni: ☑ Kompletní INSERTy
   - Odškrtni: ☐ Rozšířené INSERTy (pro lepší čitelnost)
9. Klikni na **Spustit**

### Krok 2: Export procedur a funkcí
**DŮLEŽITÉ**: Procedury a funkce je lepší exportovat ručně!

**Automatický export (může obsahovat DEFINER):**
1. V phpMyAdmin vyber databázi **cmi_inflace**
2. Záložka **Rutiny**
3. Pro každou funkci/proceduru:
   - fn_get_cena
   - fn_vypocitat_cenu_s_inflaci
   - sp_aktualizovat_ceny
4. Klikni **Exportovat** a ulož do souboru

**DOPORUČENÝ způsob (bez DEFINER):**
- Použij připravený soubor: `sql/02_procedury_funkce_portable.sql`
- Tento soubor obsahuje procedury BEZ DEFINER a s SQL SECURITY INVOKER
- Funguje na jakémkoliv serveru s jakýmkoliv uživatelem

---

## Import do phpMyAdmin (na novém serveru)

### Příprava
Ujisti se, že máš tyto soubory:
- `export_schema.sql` (struktura + data z kroku 1)
- `02_procedury_funkce_portable.sql` (procedury bez DEFINER)

### Krok 1: Vytvoř databázi
1. Otevři phpMyAdmin na novém serveru
2. Klikni na **Nová**
3. Název databáze: **cmi_inflace**
4. Porovnání: **utf8mb4_unicode_ci**
5. Klikni **Vytvořit**

### Krok 2: Import struktury a dat
1. Vyber databázi **cmi_inflace**
2. Klikni na záložku **Import**
3. **Vybrat soubor**: `export_schema.sql` (nebo .zip)
4. Formát: **SQL**
5. Klikni **Spustit**
6. Počkej na dokončení (může trvat delší dobu u velkých dat)

### Krok 3: Import procedur a funkcí
1. Stále v databázi **cmi_inflace**
2. Klikni na **SQL** záložku
3. **Možnost A** - Nahraj soubor:
   - Klikni na **Vybrat soubor**
   - Zvol `02_procedury_funkce_portable.sql`
   - Klikni **Spustit**
   
4. **Možnost B** - Zkopíruj obsah:
   - Otevři soubor `02_procedury_funkce_portable.sql` v textovém editoru
   - Zkopíruj CELÝ obsah
   - Vlož do SQL pole v phpMyAdmin
   - Klikni **Spustit**

### Krok 4: Ověření
V phpMyAdmin zkontroluj:
1. **Tabulky** (mělo by jich být 7):
   - audit_log
   - ceny_meridel
   - inflace
   - konfigurace
   - meridla
   - users
   - další...
   
2. **Rutiny** (měly by být 3):
   - fn_get_cena (FUNCTION, Security type: INVOKER)
   - fn_vypocitat_cenu_s_inflaci (FUNCTION, Security type: INVOKER)
   - sp_aktualizovat_ceny (PROCEDURE, Security type: INVOKER)

3. **Data** - zkontroluj počty záznamů:
   - meridla: ~552 záznamů
   - ceny_meridel: ~1400+ záznamů
   - inflace: 10 roků (2016-2025)
   - users: 2+ uživatelé

---

## Časté problémy a řešení

### Chyba: "Access denied for user..."
**Problém**: DEFINER v procedurách odkazuje na neexistujícího uživatele

**Řešení**:
1. Použij soubor `02_procedury_funkce_portable.sql` místo exportu z phpMyAdmin
2. Tento soubor má `SQL SECURITY INVOKER` místo DEFINER

### Chyba: "Cannot delete or update a parent row..."
**Problém**: Foreign key constraint při importu dat

**Řešení**:
1. V phpMyAdmin před importem dat běž SQL:
```sql
SET FOREIGN_KEY_CHECKS=0;
```
2. Importuj data
3. Po importu běž:
```sql
SET FOREIGN_KEY_CHECKS=1;
```

### Chyba: "Max execution time exceeded"
**Problém**: Import trvá příliš dlouho

**Řešení**:
1. V `php.ini` zvyš hodnoty:
   - `max_execution_time = 300`
   - `max_input_time = 300`
   - `post_max_size = 128M`
   - `upload_max_filesize = 128M`
2. Restartuj Apache/PHP-FPM
3. Zkus import znovu

### Procedury nejsou vidět po importu
**Problém**: Export obsahoval DEFINER s neexistujícím uživatelem

**Řešení**:
1. Smaž existující procedury v phpMyAdmin (SQL záložka):
```sql
DROP FUNCTION IF EXISTS fn_get_cena;
DROP FUNCTION IF EXISTS fn_vypocitat_cenu_s_inflaci;
DROP PROCEDURE IF EXISTS sp_aktualizovat_ceny;
```
2. Importuj `02_procedury_funkce_portable.sql`

---

## Aktualizace konfigurace aplikace

Po úspěšném importu aktualizuj `web/includes/config.php`:

```php
// Databázové připojení
define('DB_HOST', 'localhost');           // nebo IP serveru
define('DB_NAME', 'cmi_inflace');
define('DB_USER', 'tvuj_uzivatel');       // změň na DB uživatele
define('DB_PASS', 'tvoje_heslo');         // změň na DB heslo

// OpenID Connect
define('OIDC_REDIRECT_URI', 'https://kalibrace.cmi.cz/oidc_callback.php');  // změň doménu!
```

---

## Hotovo! 🎉

Databáze je nyní připravena na novém serveru.

Test funkčnosti:
1. Otevři webovou aplikaci
2. Přihlaš se přes CMI účet (OIDC)
3. Zkontroluj, že vidíš měřidla
4. Zkontroluj, že se počítají ceny s inflací
