<?php
// Nastavení kódování na začátku souboru
header('Content-Type: text/html; charset=UTF-8');

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Vyžaduje admin práva
requireAdmin();

// Zpracování formuláře
$success = false;
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Příprava dat
        $data = [
            'evidencni_cislo' => trim($_POST['evidencni_cislo']),
            'nazev_meridla' => trim($_POST['nazev_meridla']),
            'firma_kalibrujici' => trim($_POST['firma_kalibrujici']) ?: null,
            'status' => trim($_POST['status']) ?: null,
            'certifikat' => trim($_POST['certifikat']) ?: null,
            'posledni_kalibrace' => !empty($_POST['posledni_kalibrace']) ? $_POST['posledni_kalibrace'] : null,
            'planovani_kalibrace' => !empty($_POST['planovani_kalibrace']) ? $_POST['planovani_kalibrace'] : null,
            'frekvence_kalibrace' => trim($_POST['frekvence_kalibrace']) ?: null,
            'kategorie' => trim($_POST['kategorie']) ?: null,
            'dovolena_odchylka' => trim($_POST['dovolena_odchylka']) ?: null,
            'mer_rozsah' => trim($_POST['mer_rozsah']) ?: null,
            'presnost' => trim($_POST['presnost']) ?: null,
            'poznamka_cmi' => trim($_POST['poznamka_cmi']) ?: null
        ];
        
        // Validace povinných polí
        if (empty($data['evidencni_cislo'])) {
            throw new Exception('Evidenční číslo je povinné!');
        }
        
        if (empty($data['nazev_meridla'])) {
            throw new Exception('Název měřidla je povinný!');
        }
        
        // Vytvoření měřidla
        $newId = createMeridlo($data);
        
        if ($newId) {
            // Uložení ceny pokud je vyplněná
            if (!empty($_POST['cena_aktualni'])) {
                $cena = (float)str_replace([' ', ','], ['', '.'], $_POST['cena_aktualni']);
                $poznamka = !empty($_POST['poznamka_ceny']) ? $_POST['poznamka_ceny'] : 'Počáteční cena';
                saveCena($newId, CURRENT_YEAR, $cena, true, $poznamka);
            }
            
            // Redirect na detail nového měřidla
            header('Location: detail.php?id=' . $newId . '&created=1');
            exit;
        } else {
            $error = 'Chyba při vytváření měřidla.';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Přidat nové měřidlo - ' . APP_NAME;
?>

<?php include 'includes/header.php'; ?>

<div class="gov-breadcrumbs" style="margin-top: 1rem;">
    <ol class="gov-breadcrumbs__list">
        <li class="gov-breadcrumbs__item">
            <a href="index.php" class="gov-breadcrumbs__link">Přehled měřidel</a>
        </li>
        <li class="gov-breadcrumbs__item" aria-current="page">Přidat měřidlo</li>
    </ol>
</div>

<h1 class="gov-heading--large" style="margin-top: 2rem;">Přidat nové měřidlo</h1>

<?php if ($error): ?>
    <div class="gov-alert gov-alert--error">
        <div class="gov-alert__content">
            <p><strong>Chyba:</strong> <?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
<?php endif; ?>

<form method="POST" action="add.php" class="edit-form">
    
    <!-- Základní údaje -->
    <section>
        <h2 class="gov-heading--medium">Základní údaje</h2>
        
        <div class="gov-form-group">
            <label for="evidencni_cislo" class="gov-label">
                Evidenční číslo <span class="gov-required">*</span>
            </label>
            <input 
                type="text" 
                id="evidencni_cislo" 
                name="evidencni_cislo"
                class="gov-form-control" 
                value="<?php echo htmlspecialchars($_POST['evidencni_cislo'] ?? ''); ?>"
                required
                placeholder="např. 0238"
            >
            <span class="gov-hint">Jedinečný identifikátor měřidla</span>
        </div>
        
        <div class="gov-form-group">
            <label for="nazev_meridla" class="gov-label">
                Název měřidla <span class="gov-required">*</span>
            </label>
            <input 
                type="text" 
                id="nazev_meridla" 
                name="nazev_meridla" 
                class="gov-form-control" 
                value="<?php echo htmlspecialchars($_POST['nazev_meridla'] ?? ''); ?>"
                required
                placeholder="např. zvukoměr + mikrofon"
            >
        </div>
        
        <div class="gov-form-group">
            <label for="firma_kalibrujici" class="gov-label">Firma kalibrující</label>
            <input 
                type="text" 
                id="firma_kalibrujici" 
                name="firma_kalibrujici" 
                class="gov-form-control" 
                value="<?php echo htmlspecialchars($_POST['firma_kalibrujici'] ?? ''); ?>"
                placeholder="např. ČMI"
            >
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="gov-form-group">
                <label for="status" class="gov-label">Status</label>
                <input 
                    type="text" 
                    id="status" 
                    name="status" 
                    class="gov-form-control" 
                    value="<?php echo htmlspecialchars($_POST['status'] ?? ''); ?>"
                    placeholder="např. Aktivní"
                >
            </div>
            
            <div class="gov-form-group">
                <label for="certifikat" class="gov-label">Certifikát</label>
                <input 
                    type="text" 
                    id="certifikat" 
                    name="certifikat" 
                    class="gov-form-control" 
                    value="<?php echo htmlspecialchars($_POST['certifikat'] ?? ''); ?>"
                    placeholder="číslo certifikátu"
                >
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div class="gov-form-group">
                <label for="posledni_kalibrace" class="gov-label">Poslední kalibrace</label>
                <input 
                    type="date" 
                    id="posledni_kalibrace" 
                    name="posledni_kalibrace" 
                    class="gov-form-control" 
                    value="<?php echo htmlspecialchars($_POST['posledni_kalibrace'] ?? ''); ?>"
                >
            </div>
            
            <div class="gov-form-group">
                <label for="planovani_kalibrace" class="gov-label">Plánování kalibrace</label>
                <input 
                    type="date" 
                    id="planovani_kalibrace" 
                    name="planovani_kalibrace" 
                    class="gov-form-control" 
                    value="<?php echo htmlspecialchars($_POST['planovani_kalibrace'] ?? ''); ?>"
                >
            </div>
            
            <div class="gov-form-group">
                <label for="frekvence_kalibrace" class="gov-label">Frekvence kalibrace</label>
                <input 
                    type="text" 
                    id="frekvence_kalibrace" 
                    name="frekvence_kalibrace" 
                    class="gov-form-control" 
                    value="<?php echo htmlspecialchars($_POST['frekvence_kalibrace'] ?? ''); ?>"
                    placeholder="např. 1 rok"
                >
            </div>
        </div>
    </section>
    
    <!-- Technické parametry -->
    <section style="margin-top: 2rem;">
        <h2 class="gov-heading--medium">Technické parametry</h2>
        
        <div class="gov-form-group">
            <label for="kategorie" class="gov-label">Kategorie</label>
            <input 
                type="text" 
                id="kategorie" 
                name="kategorie" 
                class="gov-form-control" 
                value="<?php echo htmlspecialchars($_POST['kategorie'] ?? ''); ?>"
                placeholder="např. Elektrické měření"
            >
        </div>
        
        <div class="gov-form-group">
            <label for="mer_rozsah" class="gov-label">Měřicí rozsah</label>
            <input 
                type="text" 
                id="mer_rozsah" 
                name="mer_rozsah" 
                class="gov-form-control" 
                value="<?php echo htmlspecialchars($_POST['mer_rozsah'] ?? ''); ?>"
                placeholder="např. 0-100 dB"
            >
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="gov-form-group">
                <label for="presnost" class="gov-label">Přesnost</label>
                <input 
                    type="text" 
                    id="presnost" 
                    name="presnost" 
                    class="gov-form-control" 
                    value="<?php echo htmlspecialchars($_POST['presnost'] ?? ''); ?>"
                    placeholder="např. ±0,5%"
                >
            </div>
            
            <div class="gov-form-group">
                <label for="dovolena_odchylka" class="gov-label">Dovolená odchylka</label>
                <input 
                    type="text" 
                    id="dovolena_odchylka" 
                    name="dovolena_odchylka" 
                    class="gov-form-control" 
                    value="<?php echo htmlspecialchars($_POST['dovolena_odchylka'] ?? ''); ?>"
                    placeholder="např. ±1%"
                >
            </div>
        </div>
        
        <div class="gov-form-group">
            <label for="poznamka_cmi" class="gov-label">Poznámka CMI</label>
            <textarea 
                id="poznamka_cmi" 
                name="poznamka_cmi" 
                class="gov-form-control" 
                rows="4"
                placeholder="Další informace o měřidle..."
            ><?php echo htmlspecialchars($_POST['poznamka_cmi'] ?? ''); ?></textarea>
        </div>
    </section>
    
    <!-- Počáteční cena -->
    <section style="margin-top: 2rem;">
        <h2 class="gov-heading--medium">Počáteční cena (volitelné)</h2>
        
        <p class="gov-body-text">
            Můžete zadat počáteční cenu měřidla pro aktuální rok (<?php echo CURRENT_YEAR; ?>).
        </p>
        
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem;">
            <div class="gov-form-group">
                <label for="cena_aktualni" class="gov-label">Cena (Kč)</label>
                <input 
                    type="number" 
                    id="cena_aktualni" 
                    name="cena_aktualni" 
                    class="gov-form-control" 
                    value="<?php echo htmlspecialchars($_POST['cena_aktualni'] ?? ''); ?>"
                    step="0.01"
                    placeholder="např. 1500.00"
                >
            </div>
            
            <div class="gov-form-group">
                <label for="poznamka_ceny" class="gov-label">Poznámka k ceně</label>
                <input 
                    type="text" 
                    id="poznamka_ceny" 
                    name="poznamka_ceny" 
                    class="gov-form-control" 
                    value="<?php echo htmlspecialchars($_POST['poznamka_ceny'] ?? ''); ?>"
                    placeholder="volitelné"
                >
            </div>
        </div>
    </section>
    
    <!-- Tlačítka -->
    <div class="form-actions">
        <button type="submit" class="gov-button gov-button--primary">
            Přidat měřidlo
        </button>
        <a href="index.php" class="gov-button gov-button--secondary">
            Zrušit
        </a>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
