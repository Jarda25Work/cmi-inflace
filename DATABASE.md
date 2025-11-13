# 🗄️ Database Documentation

Kompletní dokumentace databázové struktury a funkcí.

## 📊 Schéma databáze

### Entity Relationship Diagram

```
┌─────────────┐         ┌──────────────┐         ┌────────────┐
│   meridla   │────────<│ceny_meridel  │         │   inflace  │
│             │         │              │         │            │
│ id (PK)     │         │ id (PK)      │         │ id (PK)    │
│ evidencni...│         │ meridlo_id(FK│         │ rok        │
│ nazev       │         │ rok          │         │ inflace... │
│ ...         │         │ cena         │         │ ...        │
└─────────────┘         │ je_manualni  │         └────────────┘
       │                └──────────────┘
       │                       
       │ 
       │                ┌──────────────┐
       └───────────────>│  audit_log   │
                        │              │
                        │ id (PK)      │
                        │ meridlo_id   │
                        │ akce         │
                        │ ...          │
                        └──────────────┘

┌─────────────┐         ┌──────────────┐
│    users    │         │ konfigurace  │
│             │         │              │
│ id (PK)     │         │ id (PK)      │
│ username    │         │ klic         │
│ email       │         │ hodnota      │
│ role        │         └──────────────┘
│ active      │
└─────────────┘
```

## 📋 Tabulky

### 1. `meridla`

Hlavní tabulka kalibračních měřidel.

| Sloupec | Typ | Null | Default | Popis |
|---------|-----|------|---------|-------|
| `id` | int(11) | NO | AUTO_INCREMENT | Primární klíč |
| `evidencni_cislo` | varchar(50) | NO | | Evidenční číslo (UNIQUE) |
| `sn` | varchar(100) | YES | NULL | Sériové číslo |
| `nazev` | varchar(255) | NO | | Název měřidla |
| `laborator` | varchar(100) | YES | 'ČMI' | Laboratoř |
| `stav` | varchar(50) | YES | 'v používání' | Stav měřidla |
| `typ_kalibrace` | varchar(10) | YES | NULL | Typ kalibrace (C/A/...) |
| `kal_externi` | varchar(100) | YES | NULL | Externí kalibrace |
| `posledni_kalibrace` | varchar(50) | YES | NULL | Datum poslední kalibrace |
| `pristi_kalibrace` | varchar(50) | YES | NULL | Datum příští kalibrace |
| `periodicita` | int(11) | YES | NULL | Periodicita v letech |
| `pocet` | varchar(50) | YES | '1' | Počet kusů |
| `rozliseni` | varchar(100) | YES | NULL | Rozlišení |
| `rozsah` | varchar(100) | YES | NULL | Měřicí rozsah |
| `max_chyba` | varchar(100) | YES | NULL | Maximální chyba |
| `poznamka` | text | YES | NULL | Poznámka |
| `umisteni` | varchar(255) | YES | NULL | Umístění |
| `active` | tinyint(1) | YES | 1 | Aktivní |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Datum vytvoření |
| `updated_at` | timestamp | NO | CURRENT_TIMESTAMP | Datum aktualizace |

**Indexy:**
- PRIMARY KEY: `id`
- UNIQUE KEY: `evidencni_cislo`
- INDEX: `active`

**Příklad:**
```sql
SELECT * FROM meridla 
WHERE evidencni_cislo = '0004';
```

### 2. `ceny_meridel`

Ceny měřidel po letech s automatickým výpočtem inflace.

| Sloupec | Typ | Null | Default | Popis |
|---------|-----|------|---------|-------|
| `id` | int(11) | NO | AUTO_INCREMENT | Primární klíč |
| `meridlo_id` | int(11) | NO | | FK na `meridla.id` |
| `rok` | int(11) | NO | | Rok ceny |
| `cena` | decimal(10,2) | NO | | Cena v Kč |
| `je_manualni` | tinyint(1) | YES | 0 | 1=ručně zadaná, 0=vypočtená |
| `poznamka` | varchar(500) | YES | NULL | Poznámka k ceně |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Datum vytvoření |
| `updated_at` | timestamp | NO | CURRENT_TIMESTAMP | Datum aktualizace |

**Indexy:**
- PRIMARY KEY: `id`
- UNIQUE KEY: `meridlo_id`, `rok`
- INDEX: `meridlo_id`
- FOREIGN KEY: `meridlo_id` → `meridla(id)` ON DELETE CASCADE

**VIEW:** `v_ceny_s_inflaci`
```sql
CREATE VIEW v_ceny_s_inflaci AS
SELECT 
    cm.*,
    m.evidencni_cislo,
    m.nazev,
    fn_get_cena(cm.meridlo_id, cm.rok) AS cena_s_inflaci
FROM ceny_meridel cm
JOIN meridla m ON cm.meridlo_id = m.id;
```

### 3. `inflace`

Inflační koeficienty ČSÚ pro automatický výpočet cen.

| Sloupec | Typ | Null | Default | Popis |
|---------|-----|------|---------|-------|
| `id` | int(11) | NO | AUTO_INCREMENT | Primární klíč |
| `rok` | int(11) | NO | | Rok (UNIQUE) |
| `inflace_procenta` | decimal(6,3) | NO | | Inflace v % (např. 3.500) |
| `inflace_decimal` | decimal(8,6) | YES | NULL | Jako desetinné číslo (0.035) |
| `zdroj` | varchar(200) | YES | 'ČSÚ' | Zdroj dat |
| `poznamka` | text | YES | NULL | Poznámka |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Datum vytvoření |
| `updated_at` | timestamp | NO | CURRENT_TIMESTAMP | Datum aktualizace |

**Indexy:**
- PRIMARY KEY: `id`
- UNIQUE KEY: `rok`

**Trigger:** Automatický výpočet `inflace_decimal`
```sql
CREATE TRIGGER tr_inflace_before_insert
BEFORE INSERT ON inflace
FOR EACH ROW
SET NEW.inflace_decimal = NEW.inflace_procenta / 100;
```

### 4. `users`

Uživatelé systému s rolemi.

| Sloupec | Typ | Null | Default | Popis |
|---------|-----|------|---------|-------|
| `id` | int(11) | NO | AUTO_INCREMENT | Primární klíč |
| `username` | varchar(100) | NO | | Uživatelské jméno (UNIQUE) |
| `email` | varchar(255) | YES | NULL | Email |
| `password_hash` | varchar(255) | YES | NULL | Bcrypt hash (NULL = pouze OIDC) |
| `full_name` | varchar(255) | YES | NULL | Celé jméno |
| `role` | enum('admin','read') | NO | 'read' | Role uživatele |
| `active` | tinyint(1) | YES | 1 | Aktivní účet |
| `last_login` | timestamp | YES | NULL | Poslední přihlášení |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Datum vytvoření |
| `updated_at` | timestamp | NO | CURRENT_TIMESTAMP | Datum aktualizace |

**Indexy:**
- PRIMARY KEY: `id`
- UNIQUE KEY: `username`
- INDEX: `active`

**Role:**
- `admin` - Plný přístup (read/write/delete/user management)
- `read` - Pouze čtení

### 5. `audit_log`

Auditní log změn pro sledování operací.

| Sloupec | Typ | Null | Default | Popis |
|---------|-----|------|---------|-------|
| `id` | int(11) | NO | AUTO_INCREMENT | Primární klíč |
| `user_id` | int(11) | YES | NULL | ID uživatele |
| `username` | varchar(100) | YES | NULL | Uživatelské jméno |
| `akce` | varchar(20) | NO | | INSERT/UPDATE/DELETE/SECURITY |
| `tabulka` | varchar(50) | YES | NULL | Název tabulky |
| `zaznam_id` | int(11) | YES | NULL | ID záznamu |
| `popis` | text | YES | NULL | Popis změny |
| `ip_adresa` | varchar(45) | YES | NULL | IP adresa |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Timestamp |

**Indexy:**
- PRIMARY KEY: `id`
- INDEX: `user_id`, `akce`, `created_at`

**Logované akce:**
- `INSERT` - Vytvoření záznamu
- `UPDATE` - Aktualizace záznamu
- `DELETE` - Smazání záznamu
- `SECURITY` - Bezpečnostní událost (failed login, rate limit, ...)

### 6. `konfigurace`

Systémová konfigurace (klíč-hodnota).

| Sloupec | Typ | Null | Default | Popis |
|---------|-----|------|---------|-------|
| `id` | int(11) | NO | AUTO_INCREMENT | Primární klíč |
| `klic` | varchar(100) | NO | | Klíč (UNIQUE) |
| `hodnota` | text | YES | NULL | Hodnota |
| `popis` | varchar(255) | YES | NULL | Popis nastavení |
| `updated_at` | timestamp | NO | CURRENT_TIMESTAMP | Datum aktualizace |

**Indexy:**
- PRIMARY KEY: `id`
- UNIQUE KEY: `klic`

## 🔧 SQL Funkce a Procedury

### Funkce: `fn_get_cena(meridlo_id, rok)`

Získá cenu měřidla pro daný rok včetně automatického výpočtu inflace.

**Parametry:**
- `p_meridlo_id` (INT) - ID měřidla
- `p_rok` (INT) - Rok

**Návratová hodnota:** DECIMAL(10,2) - Cena v Kč

**Logika:**
1. Zkusí najít existující cenu pro daný rok
2. Pokud neexistuje, hledá nejnovější starší cenu
3. Vypočte inflaci pomocí `fn_vypocitat_cenu_s_inflaci()`
4. Vrátí cenu s inflací

**Příklad:**
```sql
-- Získej cenu měřidla #1 pro rok 2025
SELECT fn_get_cena(1, 2025);

-- Použití ve VIEW
CREATE VIEW v_ceny_aktualni AS
SELECT 
    m.id,
    m.evidencni_cislo,
    m.nazev,
    fn_get_cena(m.id, YEAR(NOW())) AS cena_aktualni
FROM meridla m;
```

**SQL Security:** `INVOKER` - běží s právy volajícího uživatele

### Funkce: `fn_vypocitat_cenu_s_inflaci(cena, od_rok, do_rok)`

Vypočte cenu s inflací mezi dvěma roky.

**Parametry:**
- `p_cena` (DECIMAL) - Původní cena
- `p_od_rok` (INT) - Počáteční rok
- `p_do_rok` (INT) - Cílový rok

**Návratová hodnota:** DECIMAL(10,2) - Cena s inflací

**Vzorec:**
```
Nová cena = Původní cena × ∏(1 + inflace_i)
```
kde inflace_i je inflace pro rok i

**Příklad:**
```sql
-- Vypočti inflaci ceny 1000 Kč z roku 2020 na 2025
SELECT fn_vypocitat_cenu_s_inflaci(1000.00, 2020, 2025);

-- Výsledek: 1175.85 (při průměrné inflaci 3.3% ročně)
```

**Implementace:**
```sql
CREATE FUNCTION fn_vypocitat_cenu_s_inflaci(
    p_cena DECIMAL(10,2),
    p_od_rok INT,
    p_do_rok INT
)
RETURNS DECIMAL(10,2)
SQL SECURITY INVOKER
READS SQL DATA
BEGIN
    DECLARE v_vysledna_cena DECIMAL(10,2);
    DECLARE v_rok INT;
    DECLARE v_inflace DECIMAL(8,6);
    
    SET v_vysledna_cena = p_cena;
    SET v_rok = p_od_rok + 1;
    
    WHILE v_rok <= p_do_rok DO
        SELECT inflace_decimal INTO v_inflace
        FROM inflace
        WHERE rok = v_rok;
        
        IF v_inflace IS NOT NULL THEN
            SET v_vysledna_cena = v_vysledna_cena * (1 + v_inflace);
        END IF;
        
        SET v_rok = v_rok + 1;
    END WHILE;
    
    RETURN ROUND(v_vysledna_cena, 2);
END;
```

### Procedura: `sp_aktualizovat_ceny(rok)`

Hromadná aktualizace cen pro všechna měřidla na daný rok.

**Parametry:**
- `p_rok` (INT) - Cílový rok

**Akce:**
1. Projde všechna aktivní měřidla
2. Pro každé měřidlo volá `fn_get_cena()`
3. Vloží/aktualizuje cenu v tabulce `ceny_meridel`

**Příklad:**
```sql
-- Aktualizuj ceny pro rok 2025
CALL sp_aktualizovat_ceny(2025);

-- Zkontroluj výsledek
SELECT m.evidencni_cislo, cm.cena, cm.rok
FROM ceny_meridel cm
JOIN meridla m ON cm.meridlo_id = m.id
WHERE cm.rok = 2025
ORDER BY m.evidencni_cislo;
```

**Použití:**
- Při přidání nového roku inflace
- Při hromadné aktualizaci cen
- V nightly jobu pro automatickou aktualizaci

## 📝 Běžné SQL dotazy

### Získání všech měřidel s aktuálními cenami

```sql
SELECT 
    m.id,
    m.evidencni_cislo,
    m.nazev,
    m.stav,
    m.periodicita,
    fn_get_cena(m.id, YEAR(NOW())) AS cena_aktualni,
    m.pristi_kalibrace
FROM meridla m
WHERE m.active = 1
ORDER BY m.evidencni_cislo;
```

### Vyhledání měřidel

```sql
SELECT * FROM meridla
WHERE (
    evidencni_cislo LIKE '%0004%'
    OR nazev LIKE '%trhačka%'
    OR poznamka LIKE '%INSTRON%'
)
AND active = 1
ORDER BY evidencni_cislo;
```

### Historie cen měřidla

```sql
SELECT 
    cm.rok,
    cm.cena AS cena_ulozena,
    fn_get_cena(cm.meridlo_id, cm.rok) AS cena_s_inflaci,
    cm.je_manualni,
    cm.poznamka
FROM ceny_meridel cm
WHERE cm.meridlo_id = 1
ORDER BY cm.rok DESC;
```

### Měřidla s blížící se kalibrací

```sql
SELECT 
    evidencni_cislo,
    nazev,
    pristi_kalibrace,
    DATEDIFF(STR_TO_DATE(pristi_kalibrace, '%m.%y'), NOW()) AS dnu_do_kalibrace
FROM meridla
WHERE active = 1
  AND pristi_kalibrace IS NOT NULL
  AND STR_TO_DATE(pristi_kalibrace, '%m.%y') > NOW()
  AND STR_TO_DATE(pristi_kalibrace, '%m.%y') < DATE_ADD(NOW(), INTERVAL 90 DAY)
ORDER BY pristi_kalibrace;
```

### Statistiky inflace

```sql
SELECT 
    rok,
    inflace_procenta,
    ROUND(AVG(inflace_procenta) OVER (
        ORDER BY rok 
        ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
    ), 2) AS prumer_3roky
FROM inflace
ORDER BY rok DESC;
```

### Audit log - poslední akce

```sql
SELECT 
    al.created_at,
    al.username,
    al.akce,
    al.tabulka,
    al.popis,
    al.ip_adresa
FROM audit_log al
ORDER BY al.created_at DESC
LIMIT 50;
```

### Uživatelé s posledním přihlášením

```sql
SELECT 
    username,
    full_name,
    email,
    role,
    active,
    last_login,
    DATEDIFF(NOW(), last_login) AS dnu_od_prihlaseni
FROM users
ORDER BY last_login DESC;
```

## 🔄 Migrace a aktualizace

### Přidání nového sloupce

```sql
ALTER TABLE meridla 
ADD COLUMN novy_sloupec VARCHAR(100) NULL 
AFTER umisteni;

-- Zaloguj změnu
INSERT INTO audit_log (username, akce, tabulka, popis)
VALUES ('admin', 'ALTER', 'meridla', 'Přidán sloupec novy_sloupec');
```

### Přidání indexu

```sql
CREATE INDEX idx_meridla_stav ON meridla(stav);

ANALYZE TABLE meridla;
```

### Aktualizace dat

```sql
-- Vždy v transakci
START TRANSACTION;

UPDATE meridla
SET stav = 'vyřazeno'
WHERE evidencni_cislo IN ('0001', '0002');

-- Zkontroluj změny
SELECT * FROM meridla 
WHERE evidencni_cislo IN ('0001', '0002');

-- Pokud OK
COMMIT;
-- Pokud chyba
-- ROLLBACK;
```

## 📊 Výkon a optimalizace

### Analýza pomalých dotazů

```sql
-- Zapni slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;  -- 1 sekunda

-- Zkontroluj slow query log
-- tail -f /var/log/mysql/mysql-slow.log
```

### EXPLAIN dotazů

```sql
EXPLAIN SELECT 
    m.*,
    fn_get_cena(m.id, 2025) AS cena
FROM meridla m
WHERE m.active = 1;
```

### Optimalizace tabulek

```sql
-- Analyzuj tabulky
ANALYZE TABLE meridla, ceny_meridel, inflace;

-- Optimalizuj tabulky
OPTIMIZE TABLE audit_log;

-- Zkontroluj fragmentaci
SELECT 
    table_name,
    ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
    ROUND((data_free) / 1024 / 1024, 2) AS free_mb
FROM information_schema.tables
WHERE table_schema = 'cmi_inflace';
```

## 🔐 Oprávnění

### Vytvoření read-only uživatele

```sql
CREATE USER 'cmi_readonly'@'localhost' IDENTIFIED BY 'SecureP@ss123';
GRANT SELECT ON cmi_inflace.* TO 'cmi_readonly'@'localhost';
GRANT EXECUTE ON FUNCTION cmi_inflace.fn_get_cena TO 'cmi_readonly'@'localhost';
GRANT EXECUTE ON FUNCTION cmi_inflace.fn_vypocitat_cenu_s_inflaci TO 'cmi_readonly'@'localhost';
FLUSH PRIVILEGES;
```

### Vytvoření backup uživatele

```sql
CREATE USER 'cmi_backup'@'localhost' IDENTIFIED BY 'BackupP@ss123';
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON cmi_inflace.* TO 'cmi_backup'@'localhost';
FLUSH PRIVILEGES;
```

## 📚 Reference

- [MySQL 8.0 Reference Manual](https://dev.mysql.com/doc/refman/8.0/en/)
- [MySQL Performance Tuning](https://dev.mysql.com/doc/refman/8.0/en/optimization.html)
- [SQL Security](https://dev.mysql.com/doc/refman/8.0/en/security.html)
