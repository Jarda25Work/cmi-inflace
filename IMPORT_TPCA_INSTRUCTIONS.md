# Import historických cen z TPCA Excel souborů

## Účel
Tento skript importuje historické ceny měřidel z Excel souborů TPCA2012.xls až TPCA2025.xls do databáze.

## Dostupné roky v zdrojových souborech
✅ 2012, 2016, 2018, 2020, 2021, 2022, 2023, 2024, 2025
❌ Chybí: 2013, 2014, 2015, 2017, 2019

## Použití na serveru

### Varianta 1: Spuštění přes webový prohlížeč

1. Nahraj soubor `import_tpca_prices.php` do root složky na serveru (vedle složky `web/`)
2. Nahraj složku `zdroje/` se všemi TPCA Excel soubory
3. Otevři v prohlížeči: `https://meridla.cmi.cz/import_tpca_prices.php`
4. Počkej na dokončení (může trvat 1-2 minuty)
5. **Po úspěšném importu SMAŽ soubor `import_tpca_prices.php` ze serveru!**

### Varianta 2: Spuštění přes SSH/CLI

```bash
cd /path/to/application
php import_tpca_prices.php
```

## Co skript dělá

1. **Načte Excel soubory** ze složky `zdroje/`
2. **Detekuje strukturu** - automaticky najde sloupce s evidenčním číslem a cenou
3. **Normalizuje data**:
   - Evidenční čísla: odstraní mezery a speciální znaky
   - Ceny: převede na číselnou hodnotu, odstraní měnu
4. **Páruje s databází**:
   - Najde měřidlo podle evidenčního čísla
   - Zkontroluje, zda cena pro daný rok existuje
5. **Importuje/Aktualizuje**:
   - Nové ceny: vloží do databáze
   - Existující ceny: aktualizuje pouze pokud se liší
   - Označí jako manuální (`je_manualni = 1`)

## Výstup

Skript zobrazí:
- ✅ Počet nově importovaných cen
- 🔄 Počet aktualizovaných cen
- ⏭️ Počet přeskočených řádků (prázdné, duplicitní)
- ❌ Chyby (pokud nastanou)

## Bezpečnost

⚠️ **DŮLEŽITÉ:** Po dokončení importu **IHNED SMAŽ** soubor `import_tpca_prices.php` ze serveru!

Důvody:
- Obsahuje přístup k databázi
- Může být spuštěn kýmkoliv bez autentizace
- Není určen pro trvalé umístění na serveru

## Struktura očekávaných Excel souborů

Skript automaticky detekuje sloupce, ale očekává:
- **Sloupec s evidenčním číslem** (obsahuje "eviden", "číslo")
- **Sloupec s cenou** (obsahuje "cena", "kč")

Pokud detekce selže, použije výchozí pozice:
- A = Evidenční číslo
- C = Cena

## Řešení problémů

### "Soubor nenalezen"
- Zkontroluj, že složka `zdroje/` existuje a obsahuje Excel soubory
- Zkontroluj názvy souborů (TPCA2012.xls, TPCA2016.xls, atd.)

### "Sloupce nenalezeny"
- Skript zkusí výchozí pozice (A, C)
- Zkontroluj strukturu Excel souboru

### "Memory limit"
- Zvyš `memory_limit` v php.ini na 512M nebo více

### "Timeout"
- Zvyš `max_execution_time` v php.ini na 300 sekund nebo více

## Doporučený postup

1. **Záloha databáze** před importem
2. Nahraj soubory na server
3. Spusť import
4. Zkontroluj výsledky v aplikaci
5. **Smaž import skript**
6. Smaž zdrojové Excel soubory (volitelné, ale doporučené)
