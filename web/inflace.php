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
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'save') {
            $rok = (int)$_POST['rok'];
            $inflaceProcenta = (float)str_replace(',', '.', $_POST['inflace_procenta']);
            $zdroj = trim($_POST['zdroj']);
            
            if ($rok > 2000 && $rok < 2100 && $inflaceProcenta >= -50 && $inflaceProcenta <= 100) {
                if (saveInflace($rok, $inflaceProcenta, $zdroj)) {
                    $message = "Inflace pro rok $rok byla úspěšně uložena.";
                    $messageType = 'success';
                } else {
                    $message = "Chyba při ukládání inflace.";
                    $messageType = 'error';
                }
            } else {
                $message = "Neplatné hodnoty. Rok musí být mezi 2000-2100 a inflace mezi -50% až 100%.";
                $messageType = 'error';
            }
        } elseif ($_POST['action'] === 'delete') {
            $rok = (int)$_POST['rok'];
            if (deleteInflace($rok)) {
                $message = "Inflace pro rok $rok byla smazána.";
                $messageType = 'success';
            } else {
                $message = "Chyba při mazání inflace.";
                $messageType = 'error';
            }
        }
    }
}

// Získání dat
$rokyInflace = getInflaceRoky();

$pageTitle = 'Správa inflace - ' . APP_NAME;
?>

<?php include 'includes/header.php'; ?>

<div class="gov-breadcrumbs" style="margin-top: 1rem;">
    <ol class="gov-breadcrumbs__list">
        <li class="gov-breadcrumbs__item">
            <a href="index.php" class="gov-breadcrumbs__link">Přehled měřidel</a>
        </li>
        <li class="gov-breadcrumbs__item">
            <span class="gov-breadcrumbs__text">Správa inflace</span>
        </li>
    </ol>
</div>

<h1 class="gov-heading--large" style="margin-top: 2rem;">Správa inflace</h1>

<?php if ($message): ?>
    <div class="gov-alert gov-alert--<?php echo $messageType === 'success' ? 'success' : 'error'; ?>" style="margin-bottom: 2rem;">
        <div class="gov-alert__content">
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
<?php endif; ?>

<!-- Formulář pro přidání/editaci inflace -->
<div class="gov-card" style="margin-bottom: 2rem;">
    <div class="gov-card__content">
        <h2 class="gov-heading--medium">Přidat/upravit inflaci</h2>
        
        <form method="POST" action="inflace.php" class="gov-form">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="save">
            
            <div class="gov-form-group">
                <label class="gov-label" for="rok">
                    <span class="gov-label__text">Rok <span class="gov-required">*</span></span>
                </label>
                <input 
                    type="number" 
                    class="gov-form-control" 
                    id="rok" 
                    name="rok" 
                    min="2000" 
                    max="2100" 
                    required
                    placeholder="např. 2025"
                    style="max-width: 200px;">
            </div>
            
            <div class="gov-form-group">
                <label class="gov-label" for="inflace_procenta">
                    <span class="gov-label__text">Inflace (%) <span class="gov-required">*</span></span>
                    <span class="gov-hint">Zadejte číslo s desetinnou čárkou nebo tečkou, např. 2,5 nebo -0,3</span>
                </label>
                <input 
                    type="text" 
                    class="gov-form-control" 
                    id="inflace_procenta" 
                    name="inflace_procenta" 
                    required
                    placeholder="např. 2,5"
                    pattern="^-?[0-9]+([,\.][0-9]+)?$"
                    style="max-width: 200px;">
            </div>
            
            <div class="gov-form-group">
                <label class="gov-label" for="zdroj">
                    <span class="gov-label__text">Zdroj</span>
                    <span class="gov-hint">Volitelné - odkaz na zdroj dat (ČSÚ, apod.)</span>
                </label>
                <input 
                    type="text" 
                    class="gov-form-control" 
                    id="zdroj" 
                    name="zdroj"
                    placeholder="např. ČSÚ - https://www.czso.cz/">
            </div>
            
            <button type="submit" class="gov-button gov-button--primary">
                Uložit inflaci
            </button>
        </form>
    </div>
</div>

<!-- Tabulka s existující inflací -->
<div class="gov-card">
    <div class="gov-card__content">
        <h2 class="gov-heading--medium">Přehled inflace</h2>
        
        <table class="gov-table">
            <thead>
                <tr>
                    <th>Rok</th>
                    <th>Inflace</th>
                    <th>Zdroj</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rokyInflace)): ?>
                    <tr>
                        <td colspan="4" class="text-center">Žádná data o inflaci</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rokyInflace as $inflace): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($inflace['rok']); ?></strong></td>
                            <td>
                                <span class="<?php echo $inflace['inflace_procenta'] < 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo number_format($inflace['inflace_procenta'], 2, ',', ' '); ?> %
                                </span>
                            </td>
                            <td>
                                <?php if ($inflace['zdroj']): ?>
                                    <?php if (filter_var($inflace['zdroj'], FILTER_VALIDATE_URL)): ?>
                                        <a href="<?php echo htmlspecialchars($inflace['zdroj']); ?>" target="_blank" class="gov-link">
                                            <?php echo htmlspecialchars($inflace['zdroj']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($inflace['zdroj']); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button 
                                    type="button" 
                                    class="gov-button gov-button--secondary gov-button--sm"
                                    onclick="editInflace(<?php echo $inflace['rok']; ?>, <?php echo $inflace['inflace_procenta']; ?>, '<?php echo htmlspecialchars($inflace['zdroj'] ?? '', ENT_QUOTES); ?>')">
                                    Upravit
                                </button>
                                
                                <form method="POST" action="inflace.php" style="display: inline;" onsubmit="return confirm('Opravdu chcete smazat inflaci pro rok <?php echo $inflace['rok']; ?>?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="rok" value="<?php echo $inflace['rok']; ?>">
                                    <button type="submit" class="gov-button gov-button--secondary gov-button--sm">
                                        Smazat
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 1.5rem;">
            <p class="gov-hint">
                <strong>Poznámka:</strong> Inflace se používá pro automatický výpočet cen měřidel v různých letech. 
                Pokud změníte hodnotu inflace, ovlivní to všechny vypočítané ceny v systému.
            </p>
        </div>
    </div>
</div>

<script>
function editInflace(rok, inflace, zdroj) {
    document.getElementById('rok').value = rok;
    document.getElementById('inflace_procenta').value = inflace.toString().replace('.', ',');
    document.getElementById('zdroj').value = zdroj;
    
    // Scroll to form
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
    
    // Focus on inflace field
    setTimeout(() => {
        document.getElementById('inflace_procenta').focus();
        document.getElementById('inflace_procenta').select();
    }, 500);
}
</script>

<?php include 'includes/footer.php'; ?>
