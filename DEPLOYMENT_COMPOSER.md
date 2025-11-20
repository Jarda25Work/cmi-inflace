# Nasazení aplikace na webserver

## Instalace závislostí

Po nahrání složky `web/` na server je nutné nainstalovat PHP závislosti pomocí Composeru.

### Postup:

1. Připoj se k serveru přes SSH
2. Přejdi do složky s aplikací (kde je složka `web/`)
3. Spusť instalaci závislostí:

```bash
cd web/
composer install --no-dev --optimize-autoloader --ignore-platform-reqs
```

### Parametry:
- `--no-dev` - nainstaluje pouze produkční závislosti (bez dev nástrojů)
- `--optimize-autoloader` - optimalizuje autoloader pro lepší výkon
- `--ignore-platform-reqs` - ignoruje chybějící PHP extensions (gd, zip) pokud nejsou potřeba

### Požadavky:
- PHP 8.0+
- Composer nainstalovaný na serveru
- MySQL 8.0+

### Závislosti:
- **PhpSpreadsheet** (pro XLSX export)

## Alternativa: Manuální nahrání vendor/

Pokud na serveru není dostupný Composer, můžeš nahrát celou složku `web/vendor/` lokálně vytvořenou:

1. Lokálně spusť: `cd web && composer install --no-dev --optimize-autoloader --ignore-platform-reqs`
2. Nahraj celou složku `web/` včetně `web/vendor/` na server přes FTP/SFTP
3. **Velikost:** cca 10-15 MB

## Co nahrát na server:

Celá složka `web/` obsahující:
- `*.php` - všechny PHP soubory
- `assets/` - CSS, JS, obrázky
- `includes/` - knihovny a konfigurace
- `vendor/` - Composer závislosti (pokud nahráváš manuálně)
- `composer.json` - seznam závislostí (pokud instaluješ na serveru)

## Konfigurace:

Po nahrání nezapomeň upravit `web/includes/config.php` s produkčními údaji:
- Databázové přihlašovací údaje
- OIDC nastavení
- Další konstanty
