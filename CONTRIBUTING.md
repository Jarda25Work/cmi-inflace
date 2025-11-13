# 🤝 Contributing to CMI Systém kalibrace měřidel

Děkujeme za váš zájem přispět do projektu CMI Systém kalibrace měřidel!

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Security Guidelines](#security-guidelines)
- [Testing](#testing)
- [Submitting Changes](#submitting-changes)
- [Documentation](#documentation)
- [Questions](#questions)

## 📜 Code of Conduct

### Naše zásady

- **Respekt** - Buďte zdvořilí a respektující ke všem přispěvatelům
- **Profesionalita** - Udržujte profesionální komunikaci
- **Spolupráce** - Pracujte společně na řešení problémů
- **Bezpečnost** - Nikdy nesdílejte citlivé údaje (hesla, tokeny, API klíče)

### Nepřijatelné chování

- Urážlivé nebo diskriminační komentáře
- Obtěžování jiných přispěvatelů
- Zveřejňování citlivých informací
- Jiné neprofesionální chování

## 🚀 Getting Started

### Prerekvizity

Před začátkem se ujistěte, že máte nainstalováno:

- **PHP 8.2+** s rozšířeními: pdo_mysql, mbstring, curl, openssl
- **MySQL 8.0+** nebo MariaDB 10.5+
- **Git** pro správu verzí
- **Composer** (volitelné, pokud přidáváte závislosti)
- **Web server** - Apache nebo Nginx (nebo PHP built-in server pro vývoj)

### Fork & Clone

1. **Fork repository** na GitHubu (klikněte na tlačítko "Fork")

2. **Clone your fork**:
```bash
git clone https://github.com/YOUR-USERNAME/cmi-inflace.git
cd cmi-inflace
```

3. **Add upstream remote**:
```bash
git remote add upstream https://github.com/Jarda25Work/cmi-inflace.git
```

4. **Verify remotes**:
```bash
git remote -v
# origin    https://github.com/YOUR-USERNAME/cmi-inflace.git (fetch)
# origin    https://github.com/YOUR-USERNAME/cmi-inflace.git (push)
# upstream  https://github.com/Jarda25Work/cmi-inflace.git (fetch)
# upstream  https://github.com/Jarda25Work/cmi-inflace.git (push)
```

## 🔧 Development Setup

### 1. Database Setup

Vytvořte vývojovou databázi:

```sql
CREATE DATABASE cmi_inflace_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cmi_dev'@'localhost' IDENTIFIED BY 'dev_password';
GRANT ALL PRIVILEGES ON cmi_inflace_dev.* TO 'cmi_dev'@'localhost';
FLUSH PRIVILEGES;
```

Importujte schéma:

```bash
mysql -u cmi_dev -p cmi_inflace_dev < database/schema.sql
# Pokud máte testovací data:
mysql -u cmi_dev -p cmi_inflace_dev < database/test_data.sql
```

### 2. Configuration

Vytvořte konfigurační soubor:

```bash
cp web/includes/config.example.php web/includes/config.php
```

Upravte `config.php` pro vývojové prostředí:

```php
<?php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'cmi_inflace_dev');
define('DB_USER', 'cmi_dev');
define('DB_PASS', 'dev_password');
define('DB_CHARSET', 'utf8mb4');

// App
define('APP_NAME', 'CMI Systém kalibrace měřidel (DEV)');
define('APP_ENV', 'development'); // DŮLEŽITÉ pro vývojové logování

// OIDC (použijte testovací Keycloak nebo zakomentujte)
define('OIDC_ENABLED', false); // Pro vývoj bez OIDC
// ... zbytek konfigurace
```

### 3. Start Development Server

```bash
cd web
php -S localhost:8000
```

Otevřete prohlížeč: http://localhost:8000

### 4. Create Test Admin User

Pro vývoj bez OIDC vytvořte testovacího admina:

```sql
INSERT INTO users (username, email, full_name, role, active)
VALUES ('dev_admin', 'admin@dev.local', 'Dev Admin', 'admin', 1);
```

## 💻 Coding Standards

### PHP Code Style

Dodržujte **PSR-12** coding standard:

```php
<?php
// Správné odsazení: 4 mezery (ne tabulátory)
// Složené závorky na nové řádce

class MeridloManager
{
    private $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    public function getMeridlo(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM meridla WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
```

### Naming Conventions

- **Variables**: `$camelCase` - `$meridloId`, `$userName`
- **Functions**: `camelCase()` - `getMeridlo()`, `saveCena()`
- **Classes**: `PascalCase` - `MeridloManager`, `InflaceCalculator`
- **Constants**: `SCREAMING_SNAKE_CASE` - `DB_HOST`, `APP_NAME`
- **Database tables**: `snake_case` - `meridla`, `ceny_meridel`
- **Database columns**: `snake_case` - `evidencni_cislo`, `nazev`

### File Structure

```
cmi-inflace/
├── web/
│   ├── includes/
│   │   ├── config.example.php
│   │   ├── auth.php
│   │   ├── functions.php
│   │   ├── security.php
│   │   ├── header.php
│   │   └── footer.php
│   ├── assets/
│   │   └── css/
│   ├── index.php
│   ├── add.php
│   ├── edit.php
│   └── ...
├── database/
│   ├── schema.sql
│   └── migrations/
├── docs/
│   ├── SECURITY.md
│   ├── DEPLOYMENT.md
│   └── DATABASE.md
└── README.md
```

### Comments & Documentation

```php
/**
 * Získá cenu měřidla pro daný rok včetně inflace
 *
 * @param int $meridloId ID měřidla
 * @param int $rok Rok
 * @return float|null Cena nebo null pokud neexistuje
 */
function getCenaProRok(int $meridloId, int $rok): ?float
{
    // Implementace...
}
```

### HTML & Templates

```php
<!-- Používejte Gov.cz Design System komponenty -->

<div class="gov-form-group">
    <label for="nazev" class="gov-label">
        <span class="gov-label__text">Název měřidla</span>
    </label>
    <input 
        type="text" 
        id="nazev" 
        name="nazev" 
        class="gov-form-control" 
        value="<?php echo e($meridlo['nazev']); ?>"
        required
    >
</div>
```

## 🔒 Security Guidelines

**KRITICKÉ**: Všechny změny musí dodržovat bezpečnostní standardy!

### 1. SQL Injection Prevention

```php
// ✅ SPRÁVNĚ - Prepared statements
$stmt = $db->prepare("SELECT * FROM meridla WHERE id = ?");
$stmt->execute([$id]);

// ❌ ŠPATNĚ - String concatenation
$query = "SELECT * FROM meridla WHERE id = " . $id;
$result = $db->query($query);
```

### 2. XSS Prevention

```php
// ✅ SPRÁVNĚ - Output escaping
echo e($userInput); // Pro HTML kontext
echo '<input value="' . attr($searchQuery) . '">'; // Pro atributy

// ❌ ŠPATNĚ - Direct output
echo $userInput;
echo '<input value="' . $searchQuery . '">';
```

### 3. CSRF Protection

```php
// ✅ SPRÁVNĚ - CSRF token ve formuláři
<form method="POST">
    <?php echo csrfField(); ?>
    <!-- form fields -->
</form>

// ✅ SPRÁVNĚ - CSRF validace v handleru
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    // zpracování formuláře
}
```

### 4. Input Validation

```php
// ✅ SPRÁVNĚ - Validace všech vstupů
$rok = validateNumber($_POST['rok'], 2000, 2100);
if (!$rok) {
    throw new Exception('Neplatný rok');
}

$email = validateEmail($_POST['email']);
if (!$email) {
    throw new Exception('Neplatný email');
}
```

### 5. Authentication & Authorization

```php
// ✅ SPRÁVNĚ - Kontrola oprávnění
requireAdmin(); // Pro admin-only stránky
requireLogin(); // Pro přihlášené uživatele

// ✅ SPRÁVNĚ - Kontrola vlastnictví
if (!canUserEditMeridlo($userId, $meridloId)) {
    http_response_code(403);
    die('Access denied');
}
```

### Security Checklist

Před submitem PR zkontrolujte:

- [ ] Všechny SQL dotazy používají prepared statements
- [ ] Všechny výstupy jsou escapované pomocí `e()` nebo `attr()`
- [ ] Všechny POST formuláře mají CSRF token
- [ ] Všechny handlery POST požadavků validují CSRF token
- [ ] Všechny vstupy jsou validované (čísla, emaily, roky, ...)
- [ ] Citlivé akce (delete, edit) vyžadují oprávnění
- [ ] Žádné hesla nebo tokeny v kódu
- [ ] Error messages neodhalují citlivé informace
- [ ] Session management je secure

## 🧪 Testing

### Manual Testing

1. **Testujte všechny změny lokálně** před submitem

2. **Test cases**:
```php
// Příklad: test přidání měřidla
1. Otevřete add.php
2. Vyplňte všechna povinná pole
3. Submitujte formulář
4. Ověřte, že měřidlo bylo vytvořeno
5. Zkontrolujte audit log
6. Zkontrolujte, že CSRF token funguje (zkuste submit bez tokenu)
```

3. **Browser testing**:
   - Chrome/Edge (nejnovější)
   - Firefox (nejnovější)
   - Safari (pokud možné)

### Security Testing

```bash
# Test CSRF ochrany
curl -X POST http://localhost:8000/add.php \
  -d "evidencni_cislo=TEST&nazev=Test" \
  # Očekávaný výsledek: 403 Forbidden

# Test XSS
# 1. Zkuste vložit <script>alert('XSS')</script> do pole
# 2. Ověřte, že je escapovaný při zobrazení
```

### Database Testing

```sql
-- Test integrity constraints
-- Mělo by selhat (duplicate evidencni_cislo):
INSERT INTO meridla (evidencni_cislo, nazev) 
VALUES ('0001', 'Test');

-- Test foreign keys
-- Mělo by selhat (neexistující meridlo_id):
INSERT INTO ceny_meridel (meridlo_id, rok, cena) 
VALUES (99999, 2025, 1000);
```

## 📝 Submitting Changes

### Branch Naming

```bash
# Feature branches
git checkout -b feature/add-export-excel

# Bug fix branches
git checkout -b bugfix/fix-inflace-calculation

# Security fix branches
git checkout -b security/fix-csrf-vulnerability

# Documentation branches
git checkout -b docs/update-api-documentation
```

### Commit Messages

Používejte **Conventional Commits**:

```bash
# Feature
git commit -m "feat: add Excel export functionality"

# Bug fix
git commit -m "fix: correct inflation calculation for leap years"

# Security fix
git commit -m "security: add CSRF protection to user forms"

# Documentation
git commit -m "docs: update DATABASE.md with new tables"

# Refactoring
git commit -m "refactor: extract price calculation to separate function"

# Tests
git commit -m "test: add unit tests for inflation calculator"
```

**Format**:
```
<type>: <short description>

<detailed description (optional)>

<footer (optional)>
```

**Types**:
- `feat` - nová funkce
- `fix` - oprava bugu
- `security` - bezpečnostní oprava
- `docs` - dokumentace
- `style` - formátování (whitespace, coding style)
- `refactor` - refaktoring kódu
- `test` - testy
- `chore` - údržba (dependencies, config)

### Pull Request Process

1. **Sync with upstream**:
```bash
git fetch upstream
git merge upstream/main
```

2. **Push your branch**:
```bash
git push origin feature/your-feature
```

3. **Create Pull Request**:
   - Jděte na GitHub repository
   - Klikněte na "New Pull Request"
   - Vyberte váš branch
   - Vyplňte PR template:

```markdown
## Description
Popis změn (co, proč, jak)

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Security fix
- [ ] Documentation update
- [ ] Code refactoring

## Testing
- [ ] Tested locally
- [ ] All forms work correctly
- [ ] Database operations successful
- [ ] Security measures validated

## Security Checklist
- [ ] SQL injection prevention verified
- [ ] XSS protection implemented
- [ ] CSRF tokens in place
- [ ] Input validation added
- [ ] Authorization checks included

## Screenshots (if applicable)
<screenshot>

## Related Issues
Closes #123
```

4. **Wait for review** - maintainer provede review

5. **Address feedback** - proveďte požadované úpravy

6. **Merge** - po schválení bude PR merged

### PR Review Criteria

Maintainer zkontroluje:

✅ **Funkčnost** - Funguje jak má?
✅ **Bezpečnost** - Jsou dodrženy bezpečnostní standardy?
✅ **Code quality** - Je kód čitelný a maintainable?
✅ **Documentation** - Je změna zdokumentována?
✅ **Tests** - Je změna otestována?
✅ **Breaking changes** - Rozbíjí existující funkcionalitu?

## 📚 Documentation

### Co dokumentovat

Dokumentujte:

- **Nové funkce** - v REAMDE.md nebo separátní dokumentaci
- **API změny** - v API.md (pokud existuje)
- **Database změny** - v DATABASE.md
- **Security changes** - v SECURITY.md
- **Deployment změny** - v DEPLOYMENT.md

### Dokumentační template

```markdown
## Funkce: Export do Excelu

### Popis
Umožňuje exportovat data měřidel do Excel souboru (.xlsx).

### Použití
1. Klikněte na tlačítko "Export do Excelu"
2. Zvolte rozsah dat (rok od - do)
3. Klikněte "Stáhnout"

### Technická implementace
- Knihovna: PHPSpreadsheet
- Formát: XLSX
- Maximální počet řádků: 10 000

### API Endpoint (pokud existuje)
```
GET /api/export/excel
Parameters:
  - from_year (int): Rok od
  - to_year (int): Rok do
Response: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
```

### Security Considerations
- Vyžaduje admin oprávnění
- Rate limit: 10 requests/hour
- CSRF protected
```

## ❓ Questions

### Kde se ptát?

- **Issues** - Pro bugy a feature requests vytvořte issue na GitHubu
- **Discussions** - Pro obecné otázky použijte GitHub Discussions
- **Email** - Pro citlivé security issues: security@example.com

### FAQ

**Q: Mohu přidat novou závislost (Composer package)?**
A: Ano, ale nejprve vytvořte issue a diskutujte to s maintainerem.

**Q: Jak testuji OIDC autentizaci lokálně?**
A: Buď si nastavte lokální Keycloak, nebo nastavte `OIDC_ENABLED = false` v config.php.

**Q: Můžu změnit databázové schéma?**
A: Ano, ale vytvořte migrační skript a zdokumentujte změnu v DATABASE.md.

**Q: Jak nahlásím security vulnerability?**
A: Neveřejně přes email security@example.com, NE přes public issue!

**Q: Můžu použít jinou CSS framework místo Gov.cz Design System?**
A: Ne, projekt používá povinně Gov.cz Design System pro state compliance.

## 🎯 Good First Issues

Pokud hledáte, kde začít:

### Jednoduché úkoly
- [ ] Přidat PHPDoc komentáře do functions.php
- [ ] Vylepšit error messages (přeložit do češtiny)
- [ ] Přidat tooltip nápovědy k formulářovým polím
- [ ] Opravit CSS styly (responsive design)

### Střední úkoly
- [ ] Přidat pagination na audit log stránku
- [ ] Implementovat vyhledávání v historii cen
- [ ] Přidat sorting na více sloupců současně
- [ ] Vylepšit validaci formulářů (client-side)

### Pokročilé úkoly
- [ ] Implementovat REST API pro externí systémy
- [ ] Přidat grafické zobrazení inflace (charts)
- [ ] Implementovat batch import z CSV
- [ ] Vytvořit automatické reporty (PDF generation)

---

## 📜 License

Přispíváním do projektu souhlasíte s tím, že váš kód bude licencován pod stejnou licencí jako projekt (viz LICENSE soubor).

## 🙏 Acknowledgments

Děkujeme všem přispěvatelům! 🎉

- **Maintainer**: [Jarda25Work](https://github.com/Jarda25Work)
- **Design System**: [Gov.cz Design System](https://gov.cz/designsystem)
- **Contributors**: [All Contributors](https://github.com/Jarda25Work/cmi-inflace/graphs/contributors)

---

**Šťastné kódování! 🚀**

Pokud máte jakékoli otázky, neváhejte se zeptat v GitHub Issues nebo Discussions.
