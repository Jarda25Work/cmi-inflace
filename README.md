# CMI Systém kalibrace měřidel s inflací

Webová aplikace pro správu kalibračních měřidel s automatickým výpočtem inflace cen.

## 🚀 Technologie

- **Backend**: PHP 8.2+
- **Databáze**: MySQL 8.0+
- **Frontend**: Gov.cz Design System
- **Autentizace**: OpenID Connect (Keycloak)

## 📋 Funkce

- ✅ Správa kalibračních měřidel (552 záznamů)
- ✅ Automatický výpočet inflace cen podle ČSÚ
- ✅ Historie cen po letech (2016-2025)
- ✅ OpenID Connect přihlášení (login.cmi.cz)
- ✅ Administrace uživatelů (role: admin/read)
- ✅ Vyhledávání a třídění měřidel
- ✅ Audit log změn

## 🔧 Instalace

### 1. Požadavky

- PHP 8.2+
- MySQL 8.0+
- Composer (pro závislosti)
- OpenID Connect server (Keycloak)

### 2. Databáze

```bash
# Import struktury a dat
mysql -u root -p < sql/01_schema.sql
mysql -u root -p < sql/02_procedury_funkce_portable.sql
mysql -u root -p < sql/03_data_meridla.sql
mysql -u root -p < sql/04_data_ceny.sql
mysql -u root -p < sql/05_data_inflace.sql
```

### 3. Konfigurace

Zkopíruj `web/includes/config.example.php` na `web/includes/config.php` a uprav:

```php
// Databáze
define('DB_HOST', 'localhost');
define('DB_NAME', 'cmi_inflace');
define('DB_USER', 'root');
define('DB_PASS', '');

// OpenID Connect
define('OIDC_ISSUER', 'https://login.cmi.cz/auth/realms/CMI/');
define('OIDC_CLIENT_ID', 'publibtest');
define('OIDC_REDIRECT_URI', 'http://localhost:8000/oidc_callback.php');
```

### 4. Spuštění

```bash
cd web
php -S localhost:8000
```

Aplikace běží na: http://localhost:8000

## 📦 Export/Import databáze

Pro přenos na jiný server použij export v `export_phpmyadmin/`:

1. **CMI_INFLACE_EXPORT.zip** obsahuje:
   - `cmi_inflace_STRUKTURA_DATA.sql` - tabulky a data
   - `cmi_inflace_PROCEDURY.sql` - funkce a procedury
   - `README.txt` - návod na import

2. Import v phpMyAdmin:
   - Vytvoř databázi s kódováním `utf8mb4_unicode_ci`
   - **NEJDŘÍV** importuj procedury (SQL záložka)
   - **PAK** importuj strukturu a data (Import záložka)

## 🔐 Oprávnění

- **admin** - plný přístup, správa uživatelů
- **read** - pouze čtení

## 📊 Databázová struktura

- `meridla` - seznam kalibračních měřidel
- `ceny_meridel` - ceny měřidel po letech
- `inflace` - inflační koeficienty ČSÚ
- `users` - uživatelé a jejich role
- `audit_log` - log změn
- `konfigurace` - systémová nastavení

## 🔄 SQL Funkce

- `fn_get_cena(meridlo_id, rok)` - získá cenu s inflací
- `fn_vypocitat_cenu_s_inflaci(cena, od_rok, do_rok)` - výpočet inflace
- `sp_aktualizovat_ceny(rok)` - hromadná aktualizace cen

## 📝 Vývoj

### Struktura projektu

```
cmi-inflace/
├── web/                    # Webová aplikace
│   ├── includes/          # PHP funkce a konfigurace
│   ├── index.php          # Hlavní stránka
│   ├── login.php          # OpenID přihlášení
│   ├── meridlo_detail.php # Detail měřidla
│   └── users.php          # Správa uživatelů
├── sql/                   # SQL skripty
├── export_phpmyadmin/     # Export pro produkci
├── zdroje/               # Zdrojová Excel data
└── README.md
```

## 🐛 Řešení problémů

### Chyba: "FUNCTION c3meridla.fn_get_cena does not exist"
- Importoval jsi v špatném pořadí
- **Řešení**: Smaž tabulky a importuj znovu - nejdřív procedury, pak data

### Chyba: "#1227 - Access denied; you need SUPER privilege"
- Export obsahuje DEFINER
- **Řešení**: Tento export už neobsahuje DEFINER, použij aktuální verzi

## 📄 Licence

© 2025 CMI - Czech Metrology Institute

## � Dokumentace

Pro podrobnější informace viz:

- **[SECURITY.md](SECURITY.md)** - Bezpečnostní opatření a best practices
- **[DEPLOYMENT.md](DEPLOYMENT.md)** - Kompletní návod pro produkční nasazení
- **[DATABASE.md](DATABASE.md)** - Databázové schéma a SQL dokumentace
- **[CONTRIBUTING.md](CONTRIBUTING.md)** - Průvodce pro přispěvatele

## �👨‍💻 Autor

Vytvořeno pro CMI - Český metrologický institut

## 🤝 Přispívání

Příspěvky jsou vítány! Prosím přečtěte si [CONTRIBUTING.md](CONTRIBUTING.md) před odesláním pull requestu.

## 🔒 Security

Pokud najdete bezpečnostní chybu, nahlaste ji prosím zodpovědně. Viz [SECURITY.md](SECURITY.md) pro detaily.
