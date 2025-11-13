# CMI Inflace - Webové rozhraní

Webové rozhraní pro správu kalibračních měřidel s automatickým výpočtem cen na základě inflace.

## Požadavky

- PHP 7.4 nebo vyšší
- MySQL 8.0 nebo vyšší
- Webserver (Apache, Nginx, nebo PHP built-in server)

## Instalace

### 1. Import databáze

```sql
-- Vytvořte databázi
CREATE DATABASE cmi_inflace CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;

-- Importujte schema z database_schema_web.sql
mysql -u root -p cmi_inflace < database_schema_web.sql
```

### 2. Konfigurace

Otevřete `web/includes/config.php` a upravte přístupové údaje k databázi:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cmi_inflace');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Import dat

Pro import dat z Excel souboru TCA inflace.xlsx použijte Python skript:

```bash
cd ..
python import_web_to_mysql.py
```

### 4. Spuštění webserveru

**Pro vývoj** - použijte PHP built-in server:

```bash
cd web
php -S localhost:8000
```

Otevřete prohlížeč na `http://localhost:8000`

**Pro produkci** - nakonfigurujte Apache nebo Nginx aby servírovaly složku `web/`

## Struktura projektu

```
web/
├── index.php           # Přehled měřidel s vyhledáváním a stránkováním
├── detail.php          # Detail měřidla s historií cen
├── edit.php            # Editace měřidla a ruční úprava cen
├── includes/
│   ├── config.php      # Konfigurace databáze a aplikace
│   ├── functions.php   # Pomocné funkce pro práci s daty
│   ├── header.php      # Společná hlavička stránek
│   └── footer.php      # Společná patička stránek
├── assets/
│   ├── css/
│   │   └── style.css   # Vlastní styly
│   └── js/
│       └── main.js     # JavaScript pro interaktivitu
└── api/
    └── (API endpointy - připraveno pro budoucí rozšíření)
```

## Funkce systému

### Přehled měřidel (index.php)
- Tabulka se všemi aktivními měřidly
- Zobrazení aktuální ceny (pro aktuální rok)
- Vyhledávání podle evidenčního čísla, názvu nebo firmy
- Stránkování (20 položek na stránku)

### Detail měřidla (detail.php)
- Kompletní informace o měřidle:
  - Základní údaje (evidenční číslo, název, firma, status, kategorie)
  - Kalibrace (data, frekvence)
  - Technické parametry (rozsah, přesnost, odchylka)
- Historie uložených cen
- Tabulka vypočítaných cen podle inflace pro všechny roky
- Tlačítko pro editaci

### Editace měřidla (edit.php)
- Úprava všech polí měřidla
- Ruční zadání/úprava cen pro konkrétní roky
- Možnost přidat poznámku ke každé ceně
- Manuální ceny jsou označeny a nebudou přepočítávány

## Výpočet cen podle inflace

Systém automaticky počítá ceny pro roky, kde není uložená manuální cena, pomocí funkce `fn_get_cena()`:

1. Pokud existuje uložená cena pro daný rok, vrátí ji
2. Jinak najde nejbližší dostupnou cenu (z minulosti)
3. Aplikuje inflaci pro každý rok mezi referenčním a cílovým rokem
4. Vzorec: `nová_cena = stará_cena × (1 + inflace%/100)`

## Gov.cz Design System

Projekt používá [Gov.cz Design System](https://designsystem.gov.cz/) pro konzistentní a přístupné uživatelské rozhraní podle standardů českých státních webů.

Komponenty:
- Formuláře a vstupní pole
- Tabulky
- Tlačítka
- Navigace (breadcrumbs)
- Typografie

## API Endpointy (připraveno)

Složka `api/` je připravena pro budoucí REST API endpointy, například:
- `GET /api/meridla.php` - Seznam měřidel (JSON)
- `GET /api/detail.php?id=X` - Detail měřidla (JSON)
- `POST /api/save_cena.php` - Uložení ceny

## Databázové procedury

### `fn_get_cena(meridlo_id, rok)`
Vrací cenu měřidla pro daný rok (uloženou nebo vypočtenou).

### `sp_uloz_vypocitane_ceny(rok)`
Hromadně uloží vypočítané ceny pro všechna aktivní měřidla pro daný rok.

### `sp_smaz_automaticke_ceny(rok)`
Smaže všechny automaticky vypočítané ceny pro daný rok (zachová manuální).

## Údržba

### Aktualizace inflace

Pro přidání nové míry inflace:

```sql
INSERT INTO inflace (rok, mira_inflace, zdroj) 
VALUES (2026, 2.5, 'ČSÚ');
```

### Hromadné uložení cen

Pro uložení všech vypočítaných cen pro rok 2025:

```sql
CALL sp_uloz_vypocitane_ceny(2025);
```

## Řešení problémů

### Prázdná stránka
- Zkontrolujte PHP error log
- Ověřte připojení k databázi v `config.php`

### Nefungující výpočet cen
- Ujistěte se, že jsou importována data inflace (tabulka `inflace`)
- Zkontrolujte, že existuje alespoň jedna referenční cena pro měřidlo

### Chyby zobrazení
- Zkontrolujte, že jsou správně načteny CSS soubory Gov.cz Design System
- Ověřte, že je webserver správně nakonfigurován pro servírování statických souborů

## Licence

Interní projekt pro CMI - Centrum měřidel a.s.