# DATABÁZOVÝ NÁVRH - SHRNUTÍ

## 📋 Vytvořené soubory

1. **database_schema.sql** - Kompletní databázová struktura
2. **import_data.sql** - SQL skripty pro import dat
3. **import_to_mysql.py** - Python skript pro automatický import z Excelu
4. **README_DATABASE.md** - Detailní dokumentace

## 🗄️ Struktura databáze

### Tabulky:

1. **meridla** - Evidence měřidel
   - Evidenční číslo (unikátní klíč)
   - Popis, poznámka, požadavky
   - Oddělení ČMI
   - Příznak aktivní/neaktivní

2. **ceny_meridel** - Historické ceny
   - Vazba na měřidlo
   - Rok a cena
   - Příznak: původní vs. vypočítaná cena

3. **inflace** - Inflace podle roků
   - Rok a inflace v procentech
   - Předvyplněno 2012-2025
   - Zdroj dat (ČSÚ)

4. **konfigurace** - Systémové nastavení
   - Výchozí inflace
   - Aktuální rok
   - Další parametry

5. **audit_log** - Log změn
   - Historie všech změn
   - Kdo, kdy, co změnil

## 🔧 Funkce a procedury

### Stored Procedure: `sp_vypocitej_cenu(meridlo_id, rok)`
Vypočítá cenu pro daný rok s detailním výstupem.

```sql
CALL sp_vypocitej_cenu(1, 2025);
```

**Výstup:**
- Vypočítaná cena
- Ze kterého roku byl výpočet
- Původní cena
- Zpráva

### Funkce: `fn_get_cena(meridlo_id, rok)`
Vrací cenu pro daný rok (automaticky počítá pokud chybí).

```sql
SELECT fn_get_cena(1, 2025);
```

## 💡 Jak funguje výpočet ceny

### Algoritmus:
1. Najdi poslední známou cenu před požadovaným rokem
2. Postupně aplikuj inflaci pro každý rok
3. Vzorec: `nová_cena = stará_cena × (1 + inflace%/100)`

### Příklad:
- **Měřidlo 1868**, chceme cenu pro **2025**
- Poslední známá cena: **2024 = 357 Kč**
- Inflace 2025: **3%**
- Výpočet: 357 × 1.03 = **367.71 Kč**

### Přes více let:
- **Poslední cena:** 2022 = 298 Kč
- **2023:** 298 × 1.107 (10.7%) = 329.89 Kč
- **2024:** 329.89 × 1.024 (2.4%) = 337.81 Kč
- **2025:** 337.81 × 1.03 (3.0%) = **347.94 Kč**

## 📊 Předvyplněná inflace (ČR)

```
2012: 3.3%    2019: 2.8%
2013: 1.4%    2020: 3.2%
2014: 0.4%    2021: 3.8%
2015: 0.3%    2022: 15.1% (!)
2016: 0.7%    2023: 10.7%
2017: 2.5%    2024: 2.4%
2018: 2.1%    2025: 3.0% (odhad)
```

## 🚀 Postup instalace

### 1. Vytvoření databáze:
```sql
CREATE DATABASE cmi_inflace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cmi_inflace;
SOURCE database_schema.sql;
```

### 2. Import dat z Excelu:

**Pomocí Python skriptu:**
```bash
pip install pandas openpyxl mysql-connector-python
python import_to_mysql.py
```

**Nebo manuálně:**
1. Export Excelu do CSV
2. Spustit `import_data.sql`

## 📝 Příklady dotazů

### Aktuální ceny všech měřidel:
```sql
SELECT 
    evidencni_cislo,
    popis_meridla,
    fn_get_cena(id, YEAR(CURDATE())) as aktualni_cena
FROM meridla
WHERE aktivni = 1;
```

### Historie cen měřidla:
```sql
SELECT 
    m.evidencni_cislo,
    c.rok,
    c.cena,
    IF(c.cena_puvodni=1, 'Původní', 'Vypočítaná') as typ
FROM meridla m
JOIN ceny_meridel c ON m.id = c.meridlo_id
WHERE m.evidencni_cislo = '1868'
ORDER BY c.rok;
```

### Cena pro budoucí rok:
```sql
SELECT 
    evidencni_cislo,
    popis_meridla,
    fn_get_cena(id, 2026) as cena_2026
FROM meridla
WHERE aktivni = 1;
```

## 🌐 Web aplikace

### Doporučené API endpointy:

```
GET  /api/meridla              - seznam měřidel
GET  /api/meridla/{id}         - detail měřidla
GET  /api/meridla/{id}/cena/{rok} - cena pro rok
GET  /api/inflace              - seznam inflací
POST /api/inflace              - přidat/upravit inflaci
GET  /api/vypocet/{id}/{rok}   - výpočet s detaily
```

### Technologie:
- **Backend:** PHP/Laravel, Python/FastAPI, Node.js/Express
- **Frontend:** Vue.js, React, nebo vanilla JS
- **DB:** MySQL 8.0+

## ✅ Výhody tohoto řešení

1. ✓ **Automatický výpočet** - ceny se počítají automaticky
2. ✓ **Flexibilní** - lze přidat nové roky kdykoli
3. ✓ **Transparentní** - vidíš, která cena je původní a která vypočítaná
4. ✓ **Auditovatelné** - vše se loguje do audit_log
5. ✓ **Škálovatelné** - zvládne tisíce měřidel
6. ✓ **Jednoduché API** - funkce `fn_get_cena()` dělá vše automaticky

## 🔄 Údržba

### Přidání nového roku:
```sql
-- Stačí přidat inflaci
INSERT INTO inflace (rok, inflace_procenta, zdroj) 
VALUES (2026, 2.5, 'ČSÚ');

-- Ceny se automaticky přepočítají!
```

### Aktualizace konkrétní ceny:
```sql
INSERT INTO ceny_meridel (meridlo_id, rok, cena, cena_puvodni)
VALUES (1, 2026, 450.00, 1)
ON DUPLICATE KEY UPDATE cena = 450.00;
```

## 📞 Další kroky

1. Vytvoř MySQL databázi pomocí `database_schema.sql`
2. Naimportuj data pomocí `import_to_mysql.py`
3. Otestuj dotazy z dokumentace
4. Vytvoř web aplikaci s API
5. Přidej uživatelské rozhraní pro správu inflace