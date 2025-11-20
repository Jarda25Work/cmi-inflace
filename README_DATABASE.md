# Databázový návrh pro systém kalkulace cen měřidel s inflací

## Přehled

Systém slouží k evidenci měřidel a jejich historických cen s možností automatického výpočtu ceny pro libovolný rok na základě inflace.

## Databázové tabulky

### 1. `meridla`
Hlavní tabulka obsahující základní údaje o měřidlech.

**Sloupce:**
- `id` - primární klíč
- `evidencni_cislo` - unikátní evidenční číslo měřidla (VARCHAR 20)
- `popis_meridla` - popis měřidla (VARCHAR 500)
- `poznamka` - poznámka (VARCHAR 500)
- `pozadavek_na_kalibraci` - požadavky na kalibraci (VARCHAR 500)
- `oddeleni_cmi` - oddělení ČMI (VARCHAR 100)
- `aktivni` - zda je měřidlo aktivní (TINYINT, default 1)
- `created_at`, `updated_at` - časová razítka

**Indexy:**
- PRIMARY KEY na `id`
- UNIQUE KEY na `evidencni_cislo`
- INDEX na `aktivni`

---

### 2. `ceny_meridel`
Tabulka obsahující historické ceny měřidel podle roků.

**Sloupce:**
- `id` - primární klíč
- `meridlo_id` - cizí klíč na `meridla.id`
- `rok` - rok platnosti ceny (INT)
- `cena` - cena v Kč (DECIMAL 10,2)
- `cena_puvodni` - příznak (1 = původní ze souboru, 0 = vypočítaná)
- `created_at`, `updated_at` - časová razítka

**Indexy:**
- PRIMARY KEY na `id`
- FOREIGN KEY na `meridlo_id`
- UNIQUE KEY na kombinaci `(meridlo_id, rok)`
- INDEX na `rok`

---

### 3. `inflace`
Tabulka obsahující údaje o inflaci podle roků.

**Sloupce:**
- `id` - primární klíč
- `rok` - rok (INT, UNIQUE)
- `inflace_procenta` - inflace v procentech (DECIMAL 5,2)
  - Příklad: 3.50 pro 3,5% inflaci
- `zdroj` - zdroj dat (VARCHAR 200), např. "ČSÚ"
- `poznamka` - poznámka (TEXT)
- `created_at`, `updated_at` - časová razítka

**Předvyplněná data:**
```
2012: 3.3%
2013: 1.4%
2014: 0.4%
2015: 0.3%
2016: 0.7%
2017: 2.5%
2018: 2.1%
2019: 2.8%
2020: 3.2%
2021: 3.8%
2022: 15.1%
2023: 10.7%
2024: 2.4%
2025: 3.0% (odhad)
```

---

### 4. `konfigurace`
Systémová konfigurace.

**Sloupce:**
- `id` - primární klíč
- `klic` - klíč konfigurace (VARCHAR 100, UNIQUE)
- `hodnota` - hodnota (TEXT)
- `popis` - popis nastavení (VARCHAR 500)
- `created_at`, `updated_at` - časová razítka

**Výchozí hodnoty:**
- `default_inflace`: 3.0 - výchozí inflace pokud není zadána
- `aktualni_rok`: aktuální rok
- `prepocet_auto`: 1 - automatický přepočet při načtení

---

### 5. `audit_log`
Audit log pro sledování změn.

**Sloupce:**
- `id` - primární klíč
- `tabulka` - název tabulky (VARCHAR 50)
- `zaznam_id` - ID záznamu (INT)
- `akce` - typ akce: INSERT, UPDATE, DELETE (VARCHAR 20)
- `puvodni_data` - původní data (JSON)
- `nova_data` - nová data (JSON)
- `uzivatel` - uživatel (VARCHAR 100)
- `ip_adresa` - IP adresa (VARCHAR 45)
- `created_at` - časové razítko

---

## Views

### `v_meridla_s_cenami`
Zobrazení měřidel se všemi jejich cenami v jednom řádku.

```sql
SELECT * FROM v_meridla_s_cenami;
```

Vrací měřidla s historií cen ve formátu:
`rok:cena:typ` oddělené středníky, kde typ = P (původní) nebo V (vypočítaná)

---

## Stored Procedures

### `sp_vypocitej_cenu(p_meridlo_id, p_ciljovy_rok)`
Vypočítá cenu měřidla pro zadaný rok na základě inflace.

**Parametry:**
- `p_meridlo_id` (INT) - ID měřidla
- `p_ciljovy_rok` (INT) - rok, pro který chceme spočítat cenu

**Použití:**
```sql
CALL sp_vypocitej_cenu(1, 2025);
```

**Logika:**
1. Najde poslední známou cenu před cílovým rokem
2. Postupně aplikuje inflaci pro každý rok až do cílového roku
3. Vrátí vypočítanou cenu, bázový rok a zprávu

**Příklad výstupu:**
```
vypocitana_cena: 395.00
bazovy_rok: 2024
bazova_cena: 357.00
ciljovy_rok: 2025
zprava: "Vypočítáno z roku 2024"
```

---

## Funkce

### `fn_get_cena(p_meridlo_id, p_rok)`
Vrací cenu měřidla pro daný rok (s automatickým výpočtem pokud chybí).

**Parametry:**
- `p_meridlo_id` (INT) - ID měřidla
- `p_rok` (INT) - rok

**Návratová hodnota:** DECIMAL(10,2) - cena nebo NULL

**Použití:**
```sql
SELECT fn_get_cena(1, 2025) as cena;

SELECT 
    evidencni_cislo,
    popis_meridla,
    fn_get_cena(id, 2025) as cena_2025
FROM meridla
WHERE aktivni = 1;
```

**Logika:**
1. Zkusí najít přímou cenu v tabulce `ceny_meridel`
2. Pokud neexistuje, spočítá z poslední známé ceny + inflace
3. Vrátí vypočítanou cenu zaokrouhlenou na 2 desetinná místa

---

## Jak funguje výpočet ceny

### Algoritmus:

1. **Najdi poslední známou cenu:**
   - Pro daný rok hledej poslední cenu v letech menších než cílový rok
   
2. **Aplikuj inflaci rok po roce:**
   ```
   nová_cena = stará_cena × (1 + inflace/100)
   ```
   
3. **Příklad:**
   - Cena v roce 2024: 357 Kč
   - Inflace 2025: 3%
   - Cena v roce 2025: 357 × (1 + 3/100) = 357 × 1.03 = 367.71 Kč

### Příklad výpočtu přes více let:

**Měřidlo č. 1868:**
- Poslední známá cena: 2022 = 298 Kč
- Chceme cenu pro rok 2025

**Výpočet:**
1. Rok 2023: 298 × (1 + 10.7/100) = 298 × 1.107 = 329.89 Kč
2. Rok 2024: 329.89 × (1 + 2.4/100) = 329.89 × 1.024 = 337.81 Kč
3. Rok 2025: 337.81 × (1 + 3.0/100) = 337.81 × 1.03 = 347.94 Kč

---

## Import dat

### Postup importu z Excel:

1. **Příprava databáze:**
   ```sql
   CREATE DATABASE cmi_inflace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE cmi_inflace;
   SOURCE database_schema.sql;
   ```

2. **Import pomocí Python skriptu:**
   ```bash
   pip install pandas openpyxl mysql-connector-python
   python import_to_mysql.py
   ```

3. **Nebo manuální import přes SQL:**
   - Export Excel do CSV
   - Použít `import_data.sql` skript

---

## Příklady dotazů

### 1. Zobrazit všechna měřidla s aktuální cenou:
```sql
SELECT 
    evidencni_cislo,
    popis_meridla,
    fn_get_cena(id, YEAR(CURDATE())) as aktualni_cena
FROM meridla
WHERE aktivni = 1
ORDER BY evidencni_cislo;
```

### 2. Zobrazit historii cen konkrétního měřidla:
```sql
SELECT 
    m.evidencni_cislo,
    m.popis_meridla,
    c.rok,
    c.cena,
    IF(c.cena_puvodni = 1, 'Původní', 'Vypočítaná') as typ
FROM meridla m
JOIN ceny_meridel c ON m.id = c.meridlo_id
WHERE m.evidencni_cislo = '1868'
ORDER BY c.rok;
```

### 3. Vypočítat ceny pro všechna měřidla pro rok 2026:
```sql
SELECT 
    evidencni_cislo,
    popis_meridla,
    fn_get_cena(id, 2026) as cena_2026
FROM meridla
WHERE aktivni = 1
ORDER BY evidencni_cislo;
```

### 4. Statistika inflace:
```sql
SELECT 
    rok,
    inflace_procenta,
    AVG(inflace_procenta) OVER (ORDER BY rok ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) as prumer_3_roky
FROM inflace
ORDER BY rok DESC;
```

### 5. Měřidla s největším nárůstem ceny:
```sql
SELECT 
    m.evidencni_cislo,
    m.popis_meridla,
    MIN(c.cena) as min_cena,
    MAX(c.cena) as max_cena,
    ROUND((MAX(c.cena) - MIN(c.cena)) / MIN(c.cena) * 100, 2) as narust_procent
FROM meridla m
JOIN ceny_meridel c ON m.id = c.meridlo_id
GROUP BY m.id, m.evidencni_cislo, m.popis_meridla
HAVING COUNT(c.id) >= 3
ORDER BY narust_procent DESC
LIMIT 10;
```

---

## Web aplikace - doporučená struktura

### API Endpointy:

1. **GET /api/meridla** - seznam měřidel
2. **GET /api/meridla/{id}** - detail měřidla
3. **GET /api/meridla/{id}/cena/{rok}** - cena pro daný rok
4. **GET /api/inflace** - seznam inflací
5. **POST /api/inflace** - přidání/úprava inflace
6. **GET /api/vypocet/{meridlo_id}/{rok}** - výpočet ceny s detaily

### Technologie:
- **Backend:** PHP (Laravel), Python (FastAPI/Flask), nebo Node.js (Express)
- **Frontend:** Vue.js, React, nebo čisté JavaScript
- **Databáze:** MySQL 8.0+

---

## Údržba

### Přidání nového roku:
```sql
-- Přidat inflaci pro nový rok
INSERT INTO inflace (rok, inflace_procenta, zdroj) 
VALUES (2026, 2.5, 'ČSÚ');

-- Všechny ceny se automaticky přepočítají funkcí fn_get_cena()
```

### Aktualizace ceny konkrétního měřidla:
```sql
INSERT INTO ceny_meridel (meridlo_id, rok, cena, cena_puvodni)
VALUES (1, 2026, 450.00, 1)
ON DUPLICATE KEY UPDATE cena = 450.00;
```

### Mazání starých dat:
```sql
-- Smazat ceny starší než 10 let
DELETE FROM ceny_meridel 
WHERE rok < YEAR(CURDATE()) - 10;
```