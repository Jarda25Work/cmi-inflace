<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';
requireAdmin();

$message = '';
$messageType = '';

// Aktuální uložený rok (default systémový aktuální)
$currentSystemYear = (int)date('Y');
$displayYear = (int)getKonfigurace('display_year', $currentSystemYear);

// Načti dostupné roky z tabulky inflace
$pdo = getDbConnection();
$inflaceRoky = [];
try {
    $stmtInfl = $pdo->query("SELECT rok FROM inflace ORDER BY rok DESC");
    $inflaceRoky = array_map(fn($r) => (int)$r['rok'], $stmtInfl->fetchAll());
} catch (Exception $e) {
    // Pokud selže dotaz, necháme prázdné a povolíme jen currentSystemYear
    $inflaceRoky = [$currentSystemYear];
}
if (!in_array($displayYear, $inflaceRoky)) {
    // Pokud uložená konfigurace ukazuje na rok bez inflace, vrať na aktuální systémový nebo první dostupný
    $displayYear = $inflaceRoky ? max($inflaceRoky) : $currentSystemYear;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    try {
        $newYear = isset($_POST['display_year']) ? (int)$_POST['display_year'] : $currentSystemYear;
        if (!in_array($newYear, $inflaceRoky)) {
            throw new Exception('Zadaný rok nemá evidovanou inflaci. Dostupné roky: ' . implode(', ', $inflaceRoky));
        }
        saveKonfigurace('display_year', $newYear, 'Rok pro zobrazení aktuální ceny v přehledu');
        $displayYear = $newYear;
        $message = 'Rok byl úspěšně uložen.';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

$pageTitle = 'Nastavení systému - ' . APP_NAME;
include 'includes/header.php';
?>
<div class="gov-breadcrumbs" style="margin-top: 1rem;">
    <ol class="gov-breadcrumbs__list">
        <li class="gov-breadcrumbs__item">
            <a href="index.php" class="gov-breadcrumbs__link">Přehled měřidel</a>
        </li>
        <li class="gov-breadcrumbs__item" aria-current="page">Nastavení</li>
    </ol>
</div>

<h1 class="gov-heading--large" style="margin-top: 2rem;">Nastavení systému</h1>

<?php if ($message): ?>
    <div class="gov-alert gov-alert--<?php echo $messageType === 'success' ? 'success' : 'error'; ?>" style="margin-top:1rem;">
        <div class="gov-alert__content"><p><?php echo htmlspecialchars($message); ?></p></div>
    </div>
<?php endif; ?>

<div class="gov-card" style="margin-top:2rem;">
  <div class="gov-card__content">
    <h2 class="gov-heading--medium">Rok pro zobrazení aktuální ceny</h2>
    <p class="gov-body-text">Tento rok se použije v přehledu pro výpočet a zobrazení sloupce „Aktuální cena (Rok)“. Pokud pro tento rok neexistuje manuální záznam, cena je dopočtena inflací z poslední uložené ceny.</p>
    <form method="POST" action="nastaveni.php" class="gov-form" style="max-width:400px;">
        <?php echo csrfField(); ?>
        <div class="gov-form-group">
            <label for="display_year" class="gov-label">Rok pro zobrazení *</label>
            <select id="display_year" name="display_year" class="gov-form-control" required>
                <?php foreach ($inflaceRoky as $rok): ?>
                    <option value="<?php echo $rok; ?>" <?php echo $rok === $displayYear ? 'selected' : ''; ?>><?php echo $rok; ?></option>
                <?php endforeach; ?>
            </select>
            <span class="gov-hint">Lze vybrat pouze roky, pro které je zadána inflace.</span>
        </div>
        <button type="submit" class="gov-button gov-button--primary">Uložit rok</button>
        <a href="index.php" class="gov-button gov-button--secondary">Zpět</a>
    </form>
  </div>
</div>

<div class="gov-card" style="margin-top:2rem;">
  <div class="gov-card__content">
    <h2 class="gov-heading--medium">Statistiky cen</h2>
    <?php
      $pdo = getDbConnection();
      $sql = "SELECT COUNT(DISTINCT meridlo_id) AS pocet_meridel FROM ceny_meridel WHERE je_manualni = 1";
      $stmt = $pdo->query($sql);
      $stats = $stmt->fetch();
    ?>
    <p class="gov-body-text">Celkem <strong><?php echo (int)$stats['pocet_meridel']; ?></strong> měřidel má alespoň jednu manuální cenu.</p>
    <p class="gov-body-text" style="color:#666;font-size:0.85rem;">Odchylky byly odstraněny; systém pracuje s uloženými a vypočtenými cenami podle inflace.</p>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
