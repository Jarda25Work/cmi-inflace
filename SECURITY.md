# 🔒 Security Guidelines

## Bezpečnostní opatření

Tento dokument popisuje všechna bezpečnostní opatření implementovaná v aplikaci.

## ✅ Implementovaná ochrana

### 1. SQL Injection Prevention

**Ochrana:** Všechny databázové dotazy používají PDO prepared statements.

```php
// ✅ SPRÁVNĚ - prepared statement
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);

// ❌ ŠPATNĚ - nikdy nepoužívat
$query = "SELECT * FROM users WHERE id = " . $_GET['id'];
```

**Soubory:** `web/includes/functions.php`, `web/includes/auth.php`, `web/includes/oidc.php`

### 2. Cross-Site Scripting (XSS) Protection

**Ochrana:** Všechny výstupy jsou escapovány pomocí funkcí `e()` a `attr()`.

```php
// ✅ SPRÁVNĚ - escapovaný výstup
echo e($user['username']);
echo '<input value="' . attr($searchQuery) . '">';

// ❌ ŠPATNĚ - direct output
echo $user['username'];
```

**Funkce:** `web/includes/security.php`
- `e($string)` - escapuje text pro HTML
- `attr($string)` - escapuje text pro HTML atributy
- `sanitizeUrl($url)` - validuje a sanitizuje URL

### 3. Cross-Site Request Forgery (CSRF) Protection

**Ochrana:** CSRF tokeny pro všechny POST/DELETE operace.

```php
// V formuláři
<form method="post">
    <?php echo csrfField(); ?>
    <!-- form fields -->
</form>

// V PHP handleru
requireCsrfToken(); // Ověří token
```

**Funkce:** `web/includes/security.php`
- `generateCsrfToken()` - generuje token
- `verifyCsrfToken($token)` - ověřuje token
- `requireCsrfToken()` - vyžaduje platný token
- `csrfField()` - vytvoří HTML input s tokenem

### 4. Session Security

**Ochrana:**
- HTTPOnly cookies (brání XSS útokům na cookies)
- Secure flag pro HTTPS
- Strict SameSite policy
- Session regeneration po přihlášení
- Strict mode

```php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);  // Pro HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
```

**Soubor:** `web/includes/auth.php`

### 5. Rate Limiting

**Ochrana:** Omezení pokusů o přihlášení (5 pokusů za 15 minut).

```php
$rateLimit = checkLoginRateLimit($username);
if ($rateLimit !== true) {
    die($rateLimit); // Vrátí chybovou zprávu
}
```

**Funkce:** `web/includes/security.php`
- `checkLoginRateLimit($username)`
- `recordFailedLogin($username)`
- `clearLoginAttempts($username)`

### 6. Security Headers

**Ochrana:** HTTP security headers pro ochranu před různými útoky.

```php
setSecurityHeaders();
```

**Implementované hlavičky:**

| Header | Hodnota | Ochrana proti |
|--------|---------|---------------|
| `X-Frame-Options` | `DENY` | Clickjacking |
| `X-Content-Type-Options` | `nosniff` | MIME type sniffing |
| `X-XSS-Protection` | `1; mode=block` | XSS útoky |
| `Content-Security-Policy` | Omezené zdroje | XSS, injection |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Information leakage |
| `Strict-Transport-Security` | `max-age=31536000` | MITM útoky (HTTPS only) |
| `Permissions-Policy` | Blokuje geo/mic/camera | Neoprávněný přístup |

**Soubor:** `web/includes/security.php`

### 7. Password Hashing

**Ochrana:** Moderní bcrypt hashing s vysokou cenou.

```php
// Hashování hesla
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Ověření hesla
if (password_verify($password, $hash)) {
    // OK
}
```

**Soubor:** `web/includes/auth.php`

### 8. Input Validation

**Ochrana:** Validace všech vstupů.

```php
// Validace čísla
if (!validateNumber($value, $min, $max)) {
    die('Invalid number');
}

// Validace roku
if (!validateYear($year)) {
    die('Invalid year');
}

// Validace emailu
if (!validateEmail($email)) {
    die('Invalid email');
}
```

**Funkce:** `web/includes/security.php`

### 9. Audit Logging

**Ochrana:** Logování bezpečnostních událostí.

```php
logSecurityEvent('FAILED_LOGIN', "Username: $username");
logSecurityEvent('UNAUTHORIZED_ACCESS', "URL: $url");
```

**Logované události:**
- Přihlášení (úspěšné/neúspěšné)
- Rate limiting
- CSRF chyby
- Neautorizovaný přístup

**Tabulka:** `audit_log`

### 10. OpenID Connect Security

**Ochrana:**
- State parameter pro CSRF ochranu
- Validace issuer
- Ověření JWT tokenů
- Secure redirect URI

**Soubor:** `web/includes/oidc.php`

## 🚨 Security Checklist

### Před nasazením do produkce

- [ ] **Config:** Vytvoř `config.php` s produkčními údaji (necommituj!)
- [ ] **HTTPS:** Zapni HTTPS a `session.cookie_secure`
- [ ] **Error reporting:** Vypni `display_errors` v produkci
- [ ] **Database:** Změň DB heslo
- [ ] **OIDC:** Nastavkonfiguruj Client Secret (pokud není public)
- [ ] **OIDC Redirect:** Aktualizuj OIDC_REDIRECT_URI na produkční doménu
- [ ] **Keycloak:** Přidej produkční redirect URI do Keycloak clienta
- [ ] **File permissions:** Nastav správná oprávnění (755 pro složky, 644 pro soubory)
- [ ] **Git:** Ověř že `config.php` je v `.gitignore`
- [ ] **Backupy:** Nastav automatické zálohy databáze
- [ ] **Monitoring:** Nastav monitoring audit logu
- [ ] **Updates:** Pravidelně aktualizuj PHP a MySQL

### Produkční config.php

```php
<?php
// Vypni error reporting
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/error.log');

// Databáze
define('DB_HOST', 'localhost');
define('DB_NAME', 'production_db');
define('DB_USER', 'secure_user');
define('DB_PASS', 'SecureP@ssw0rd!');

// OpenID Connect
define('OIDC_ISSUER', 'https://login.cmi.cz/auth/realms/CMI/');
define('OIDC_CLIENT_ID', 'production_client');
define('OIDC_CLIENT_SECRET', 'your-secret-here');
define('OIDC_REDIRECT_URI', 'https://meridla.cmi.cz/oidc_callback.php');

// Session security
ini_set('session.cookie_secure', 1);      // HTTPS only
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
```

## 🛡️ Security Best Practices

### Pro vývojáře

1. **Nikdy necommituj citlivé údaje** (hesla, secrets, tokeny)
2. **Vždy používej prepared statements** pro SQL dotazy
3. **Vždy escapuj výstup** pomocí `e()` nebo `attr()`
4. **Přidej CSRF token** do všech formulářů
5. **Validuj všechny vstupy** na serveru (nikdy nedůvěřuj clientu)
6. **Loguj bezpečnostní události** pro audit
7. **Testuj bezpečnost** před každým releausem

### Pro administrátory

1. **Používej silná hesla** (min. 16 znaků, mix písmen/čísel/symbolů)
2. **Aktualizuj pravidelně** PHP, MySQL, dependencies
3. **Monitoruj audit log** pro podezřelou aktivitu
4. **Zálohuj databázi** automaticky každý den
5. **Používej HTTPS** vždy v produkci
6. **Omez přístup k DB** pouze z aplikačního serveru
7. **Nastavuj silná DB hesla** a měň je pravidelně

## 🔍 Penetration Testing

### Doporučené testy

1. **SQL Injection:** Test všech vstupních polí
2. **XSS:** Test všech výstupů a formulářů
3. **CSRF:** Test formulářů bez tokenu
4. **Session hijacking:** Test cookie security
5. **Rate limiting:** Test opakovaných pokusů o přihlášení
6. **Authorization:** Test přístupu k admin funkcím
7. **File upload:** Test nahrávání souborů (pokud je implementováno)

### Nástroje

- **OWASP ZAP** - automatické skenování
- **Burp Suite** - manuální testing
- **SQLMap** - SQL injection testing
- **XSSer** - XSS testing

## 📞 Hlášení bezpečnostních chyb

Pokud najdeš bezpečnostní chybu:

1. **Nehlásej veřejně** (ne GitHub Issues)
2. **Kontaktuj admina** přímo emailem
3. **Poskytni detaily:** URL, kroky k reprodukci, dopad
4. **Počkej na fix** před zveřejněním

## 📚 Odkazy

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Guide](https://phptherightway.com/#security)
- [PDO Security](https://www.php.net/manual/en/pdo.prepared-statements.php)
- [Session Security](https://www.php.net/manual/en/session.security.php)
