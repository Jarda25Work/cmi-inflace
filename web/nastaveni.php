<?php
// Nastavení kódování na začátku souboru
header('Content-Type: text/html; charset=UTF-8');

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

// Vyžaduje admin práva
requireAdmin();

$message = '';
$messageType = '';

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF ochrana
    requireCsrfToken();
    
    try {
        $tolerance = (float)$_POST['cena_tolerance_procenta'];
        
        if ($tolerance < 0 || $tolerance > 100) {
            throw new Exception('Tolerance musí být mezi 0 a 100 %');
        }
        
        saveKonfigurace('cena_tolerance_procenta', $tolerance, 'Tolerance odchylky ceny v % - pokud je ručně zadaná cena odchylná od vypočtené o více než tuto hodnotu, zobrazí se varování');
        
        $message = 'Nastavení bylo úspěšně uloženo.';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Získání aktuálního nastavení
$tolerance = getKonfigurace('cena_tolerance_procenta', 5);

$pageTitle = 'Nastavení systému - ' . APP_NAME;
?>

<?php include 'includes/header.php'; ?>

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
    <div class="gov-alert gov-alert--<?php echo $messageType === 'success' ? 'success' : 'error'; ?>" style="margin-top: 1rem;">
        <div class="gov-alert__content">
            <p><?php echo e($message); ?></p>
        </div>
    </div>
<?php endif; ?>

<!-- Nastavení tolerance cen -->
<div class="gov-card" style="margin-top: 2rem;">
    <div class="gov-card__content">
        <h2 class="gov-heading--medium">Tolerance odchylky cen</h2>
        
        <p class="gov-body-text">
            Pokud je ručně zadaná cena měřidla odchylná od vypočtené ceny podle inflace o více než nastavenou toleranci, 
            řádek bude v seznamu i detailu označen <span style="background-color: #ffcccc; padding: 0.2rem 0.5rem;">světle červeným podbarvením</span> 
            a ikonou <span style="color: #dc3545;">⚠</span>.
        </p>
        
        <form method="POST" action="nastaveni.php" class="gov-form" style="max-width: 600px;">
            <?php echo csrfField(); ?>
            
            <div class="gov-form-group">
                <label class="gov-label" for="cena_tolerance_procenta">
                    <span class="gov-label__text">Tolerance odchylky ceny (%) <span class="gov-required">*</span></span>
                </label>
                <input 
                    type="number" 
                    class="gov-form-control" 
                    id="cena_tolerance_procenta" 
                    name="cena_tolerance_procenta" 
                    min="0" 
                    max="100" 
                    step="0.1"
                    value="<?php echo e($tolerance); ?>"
                    required
                    style="max-width: 200px;">
                <span class="gov-hint">
                    Zadejte hodnotu v procentech (např. 5 pro 5%). 
                    Pokud se ručně zadaná cena liší od vypočtené o více než tuto hodnotu, zobrazí se varování.
                </span>
            </div>
            
            <div style="margin-top: 1.5rem;">
                <button type="submit" class="gov-button gov-button--primary">
                    Uložit nastavení
                </button>
                <a href="index.php" class="gov-button gov-button--secondary">
                    Zrušit
                </a>
            </div>
        </form>
        
        <div class="gov-alert gov-alert--info" style="margin-top: 2rem;">
            <div class="gov-alert__content">
                <p><strong>Příklad:</strong></p>
                <p>
                    Při toleranci <strong>5 %</strong> a vypočtené ceně <strong>1000 Kč</strong>:<br>
                    • Ceny mezi <strong>950 Kč - 1050 Kč</strong> = OK (zelené)<br>
                    • Ceny mimo tento rozsah = Varování (červené)
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Statistiky odchylek (volitelné) -->
<div class="gov-card" style="margin-top: 2rem;">
    <div class="gov-card__content">
        <h2 class="gov-heading--medium">Statistiky odchylek</h2>
        
        <?php
        // Získat statistiky odchylek
        $pdo = getDbConnection();
        $sql = "SELECT COUNT(DISTINCT meridlo_id) as pocet_meridel 
                FROM ceny_meridel 
                WHERE je_manualni = 1";
        $stmt = $pdo->query($sql);
        $stats = $stmt->fetch();
        ?>
        
        <p class="gov-body-text">
            Celkem <strong><?php echo $stats['pocet_meridel']; ?></strong> měřidel má ručně zadané ceny.
        </p>
        
        <p class="gov-body-text" style="color: #666; font-size: 0.9rem;">
            Poznámka: Kontrola odchylek se provádí dynamicky při zobrazení seznamu a detailu.
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
