═══════════════════════════════════════════════════════════════
  EXPORT DATABÁZE CMI INFLACE - Pro import v phpMyAdmin
═══════════════════════════════════════════════════════════════

📦 OBSAH EXPORTU:
-----------------
1. cmi_inflace_STRUKTURA_DATA.sql  - Tabulky + všechna data
2. cmi_inflace_PROCEDURY.sql       - SQL funkce a procedury
3. README.txt                      - Tento soubor


🚀 IMPORT NA NOVÉM SERVERU (phpMyAdmin):
-----------------------------------------

KROK 1: Vytvoř databázi
------------------------
1. Otevři phpMyAdmin
2. Klikni "Nová" (New)
3. Název: c3meridla  (nebo jiný název dle potřeby)
4. Kódování: utf8mb4_unicode_ci
5. Klikni "Vytvořit"


KROK 2: NEJDŘÍV Import procedur a funkcí ⚠️
--------------------------------------------
DŮLEŽITÉ: Procedury MUSÍ být naimportovány PŘED daty!

1. Vyber databázi v levém menu
2. Klikni na záložku "SQL"
3. Vyber soubor: cmi_inflace_PROCEDURY.sql
   NEBO zkopíruj obsah souboru a vlož do pole
4. Klikni "Spustit"
5. Mělo by se objevit: "3 queries executed successfully"


KROK 3: Pak import struktury a dat
-----------------------------------
1. Stále v téže databázi
2. Klikni na záložku "Import"
3. Vyber soubor: cmi_inflace_STRUKTURA_DATA.sql
4. Formát: SQL
5. Klikni "Spustit"
6. Počkej na dokončení (může trvat 30-60 sekund)


KROK 4: Ověření
----------------
Zkontroluj v phpMyAdmin:

Tabulky (7 ks):
  ✓ audit_log
  ✓ ceny_meridel (~1400+ záznamů)
  ✓ inflace (10 roků)
  ✓ konfigurace
  ✓ meridla (~552 záznamů)
  ✓ users (2+ uživatelé)

Rutiny (3 ks):
  ✓ fn_get_cena (FUNCTION)
  ✓ fn_vypocitat_cenu_s_inflaci (FUNCTION)
  ✓ sp_aktualizovat_ceny (PROCEDURE)


⚙️ KONFIGURACE APLIKACE:
-------------------------
Uprav soubor: web/includes/config.php

// Databáze
define('DB_HOST', 'localhost');          // nebo IP serveru
define('DB_NAME', 'c3meridla');          // ← ZMĚŇ na svůj název databáze!
define('DB_USER', 'tvuj_db_user');       // ← ZMĚŇ!
define('DB_PASS', 'tvoje_heslo');        // ← ZMĚŇ!

// OpenID Connect
define('OIDC_REDIRECT_URI', 'https://kalibrace.cmi.cz/oidc_callback.php');  // ← ZMĚŇ doménu!


🔧 ČASTÉ PROBLÉMY:
------------------

Problém: "#1305 - FUNCTION c3meridla.fn_get_cena does not exist"
Řešení: 
  ⚠️ Importoval jsi v ŠPATNÉM POŘADÍ!
  - Procedury MUSÍ být naimportovány PŘED strukturou/daty
  - Smaž všechny tabulky a rutiny
  - Importuj znovu v pořadí:
    1. cmi_inflace_PROCEDURY.sql  (funkce a procedury)
    2. cmi_inflace_STRUKTURA_DATA.sql  (tabulky a data)

Problém: "Max execution time exceeded"
Řešení: 
  - Zvyš v php.ini: max_execution_time = 300
  - Restartuj Apache/PHP-FPM

Problém: "Packet too large"
Řešení:
  - Zvyš v php.ini: post_max_size = 128M
                    upload_max_filesize = 128M
  - Restartuj Apache/PHP-FPM

Problém: Foreign key constraint error
Řešení:
  - Před importem spusť v SQL:
    SET FOREIGN_KEY_CHECKS=0;
  - Po importu spusť:
    SET FOREIGN_KEY_CHECKS=1;


✅ HOTOVO!
----------
Po úspěšném importu a nastavení config.php je aplikace
připravena k použití na: https://kalibrace.cmi.cz

Test: Přihlášení přes CMI účet → zobrazení měřidel → kalkulace cen


═══════════════════════════════════════════════════════════════
Export vytvořen: 13.11.2025
Systém kalibrace měřidel CMI
═══════════════════════════════════════════════════════════════
