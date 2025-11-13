<?php
// Nastavení kódování na začátku souboru
header('Content-Type: text/html; charset=UTF-8');

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Vyžaduje admin práva
requireAdmin();

// Zpracování mazání ceny přes GET (aby nenarusovalo hlavní formulář)
if (isset($_GET['delete_cena']) && isset($_GET['delete_rok']) && isset($_GET['id'])) {
    $meridloId = (int)$_GET['id'];
    $rok = (int)$_GET['delete_rok'];
    if (deleteCena($meridloId, $rok)) {
        header('Location: edit.php?id=' . $meridloId . '&deleted=1');
        exit;
    } else {
        $error = 'Chyba při mazání ceny.';
    }
}

// Zpracování formuláře
$success = false;
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $meridloId = (int)$_POST['id'];

        // Update základních údajů
        $data = [
            'nazev_meridla' => $_POST['nazev_meridla'],
            'firma_kalibrujici' => $_POST['firma_kalibrujici'],
            'status' => $_POST['status'],
            'certifikat' => $_POST['certifikat'],
            'posledni_kalibrace' => $_POST['posledni_kalibrace'],
            'planovani_kalibrace' => $_POST['planovani_kalibrace'] ?: null,
            'frekvence_kalibrace' => $_POST['frekvence_kalibrace'],
            'kategorie' => $_POST['kategorie'],
            'dovolena_odchylka' => $_POST['dovolena_odchylka'],
            'mer_rozsah' => $_POST['mer_rozsah'],
            'presnost' => $_POST['presnost'],
            'poznamka_cmi' => $_POST['poznamka_cmi']
        ];
        
        // Konverze prázdných datumů na NULL
        if (empty($data['planovani_kalibrace'])) {
            $data['planovani_kalibrace'] = null;
        }
        
        updateMeridlo($meridloId, $data);
        
        // Uložení cen pokud jsou vyplněné
        if (isset($_POST['ceny']) && is_array($_POST['ceny'])) {
            foreach ($_POST['ceny'] as $rok => $cenaData) {
                if (!empty($cenaData['cena'])) {
                    $cena = (float)str_replace([' ', ','], ['', '.'], $cenaData['cena']);
                    $poznamka = !empty($cenaData['poznamka']) ? $cenaData['poznamka'] : null;
                    saveCena($meridloId, (int)$rok, $cena, true, $poznamka);
                }
            }
        }
        
        $success = true;
        
        // Redirect na detail po úspěšném uložení
        header('Location: detail.php?id=' . $meridloId . '&saved=1');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Získání ID měřidla
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$meridloId = (int)$_GET['id'];
$editRok = isset($_GET['edit_rok']) ? (int)$_GET['edit_rok'] : null;

// Získání detailu měřidla
$meridlo = getMeridloDetail($meridloId);

if (!$meridlo) {
    header('Location: index.php');
    exit;
}

// Získání historie cen
$ceny = getCenyMeridla($meridloId);

$pageTitle = 'Editace měřidla: ' . $meridlo['evidencni_cislo'] . ' - ' . APP_NAME;
?>

<?php include 'includes/header.php'; ?>

<div class="gov-breadcrumbs" style="margin-top: 1rem;">
    <ol class="gov-breadcrumbs__list">
        <li class="gov-breadcrumbs__item">
            <a href="index.php" class="gov-breadcrumbs__link">Přehled měřidel</a>
        </li>
        <li class="gov-breadcrumbs__item">
            <a href="detail.php?id=<?php echo $meridlo['id']; ?>" class="gov-breadcrumbs__link">
                <?php echo htmlspecialchars($meridlo['evidencni_cislo']); ?>
            </a>
        </li>
        <li class="gov-breadcrumbs__item" aria-current="page">Editace</li>
    </ol>
</div>

<h1 class="gov-heading--large" style="margin-top: 2rem;">Editace měřidla</h1>

<?php if ($error): ?>
    <div class="alert alert-error">
        <strong>Chyba:</strong> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<form method="POST" action="edit.php?id=<?php echo $meridlo['id']; ?>" class="edit-form" id="editForm">
    <input type="hidden" name="id" value="<?php echo $meridlo['id']; ?>">
    
    <!-- Základní údaje -->
    <section>
        <h2 class="gov-heading--medium">Základní údaje</h2>
        
        <div class="gov-form-group">
            <label for="evidencni_cislo" class="gov-label">Evidenční číslo</label>
            <input 
                type="text" 
                id="evidencni_cislo" 
                class="gov-form-control" 
                value="<?php echo htmlspecialchars($meridlo['evidencni_cislo']); ?>"
                disabled
            >
            <small class="gov-hint-text">Evidenční číslo nelze měnit</small>
        </div>
        
        <div class="gov-form-group">
            <label for="nazev_meridla" class="gov-label">Název měřidla *</label>
            <input 
                type="text" 
                id="nazev_meridla" 
                name="nazev_meridla" 
                class="gov-form-control" 
                value="<?php echo htmlspecialchars($meridlo['nazev_meridla']); ?>"
                required
            >
        </div>
        
        <div class="gov-form-group">
            <label for="firma_kalibrujici" class="gov-label">Firma kalibrující</label>
            <input 
                type="text" 
                id="firma_kalibrujici" 
                name="firma_kalibrujici" 
                class="gov-form-control" 
                value="<?php echo htmlspecialchars($meridlo['firma_kalibrujici'] ?? ''); ?>"
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
                    value="<?php echo htmlspecialchars($meridlo['status'] ?? ''); ?>"
                >
            </div>
            
            <div class="gov-form-group">
                <label for="certifikat" class="gov-label">Certifikát</label>
                <input 
                    type="text" 
                    id="certifikat" 
                    name="certifikat" 
                    class="gov-form-control" 
                    value="<?php echo htmlspecialchars($meridlo['certifikat'] ?? ''); ?>"
                >
            </div>
        </div>
        
        <div class="gov-form-group">
            <label for="kategorie" class="gov-label">Kategorie</label>
            <input 
                type="text" 
                id="kategorie" 
                name="kategorie" 
                class="gov-form-control" 
                value="<?php echo htmlspecialchars($meridlo['kategorie'] ?? ''); ?>"
            >
        </div>
    </section>
    
    <!-- Kalibrace -->
    <section style="margin-top: 2rem;">
        <h2 class="gov-heading--medium">Kalibrace</h2>
        
        <div class="gov-form-group">
            <label for="posledni_kalibrace" class="gov-label">Poslední kalibrace provedl</label>
            <input 
                type="text" 
                id="posledni_kalibrace" 
                name="posledni_kalibrace" 
                class="gov-form-control" 
                value="<?php echo htmlspecialchars($meridlo['posledni_kalibrace'] ?? ''); ?>"
            >
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="gov-form-group">
                <label for="planovani_kalibrace" class="gov-label">Plánovaná kalibrace</label>
                <input 
                    type="date" 
                    id="planovani_kalibrace" 
                    name="planovani_kalibrace" 
                    class="gov-form-control" 
                    value="<?php echo $meridlo['planovani_kalibrace'] ?? ''; ?>"
                >
            </div>
            
            <div class="gov-form-group">
                <label for="frekvence_kalibrace" class="gov-label">Frekvence kalibrace</label>
                <input 
                    type="text" 
                    id="frekvence_kalibrace" 
                    name="frekvence_kalibrace" 
                    class="gov-form-control" 
                    value="<?php echo htmlspecialchars($meridlo['frekvence_kalibrace'] ?? ''); ?>"
                    placeholder="např. 1 rok, 2 roky"
                >
            </div>
        </div>
    </section>
    
    <!-- Technické parametry -->
    <section style="margin-top: 2rem;">
        <h2 class="gov-heading--medium">Technické parametry</h2>
        
        <div class="gov-form-group">
            <label for="mer_rozsah" class="gov-label">Měřící rozsah</label>
            <input 
                type="text" 
                id="mer_rozsah" 
                name="mer_rozsah" 
                class="gov-form-control" 
                value="<?php echo htmlspecialchars($meridlo['mer_rozsah'] ?? ''); ?>"
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
                    value="<?php echo htmlspecialchars($meridlo['presnost'] ?? ''); ?>"
                >
            </div>
            
            <div class="gov-form-group">
                <label for="dovolena_odchylka" class="gov-label">Dovolená odchylka</label>
                <input 
                    type="text" 
                    id="dovolena_odchylka" 
                    name="dovolena_odchylka" 
                    class="gov-form-control" 
                    value="<?php echo htmlspecialchars($meridlo['dovolena_odchylka'] ?? ''); ?>"
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
            ><?php echo htmlspecialchars($meridlo['poznamka_cmi'] ?? ''); ?></textarea>
        </div>
    </section>
    
    <!-- Ruční úprava cen -->
    <section style="margin-top: 2rem;">
        <h2 class="gov-heading--medium">Ruční úprava cen</h2>
        
        <p class="gov-body-text">
            Zde můžete ručně zadat nebo upravit ceny pro konkrétní roky. 
            Tyto ceny budou označeny jako manuální a nebudou přepočítávány inflací.
        </p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; margin-top: 1rem;">
            <?php 
            $aktualniRok = CURRENT_YEAR;
            $roky = range($aktualniRok - 5, $aktualniRok);
            
            // Přidat editovaný rok pokud není v rozsahu
            if ($editRok && !in_array($editRok, $roky)) {
                $roky[] = $editRok;
                sort($roky);
            }
            
            foreach ($roky as $rok): 
                // Najít existující cenu pro tento rok
                $existujiciCena = null;
                foreach ($ceny as $c) {
                    if ($c['rok'] == $rok) {
                        $existujiciCena = $c;
                        break;
                    }
                }
                
                // Pokud cena neexistuje, vypočítat ji
                $cenaValue = $existujiciCena ? $existujiciCena['cena'] : null;
                $poznamkaValue = $existujiciCena ? $existujiciCena['poznamka'] : '';
                
                // Highlight pokud editujeme tento rok
                $highlight = ($editRok == $rok) ? 'border: 2px solid #0062AD; padding: 1rem; border-radius: 4px; background: #f0f8ff;' : 'padding: 1rem;';
            ?>
                <div style="<?php echo $highlight; ?>">
                    <h3 class="gov-heading--small">Rok <?php echo $rok; ?></h3>
                    
                    <div class="gov-form-group">
                        <label for="cena_<?php echo $rok; ?>" class="gov-label">Cena (Kč)</label>
                        <input 
                            type="number" 
                            id="cena_<?php echo $rok; ?>" 
                            name="ceny[<?php echo $rok; ?>][cena]" 
                            class="gov-form-control" 
                            value="<?php echo $cenaValue; ?>"
                            step="0.01"
                            placeholder="např. 1500.00"
                        >
                    </div>
                    
                    <div class="gov-form-group">
                        <label for="poznamka_<?php echo $rok; ?>" class="gov-label">Poznámka</label>
                        <input 
                            type="text" 
                            id="poznamka_<?php echo $rok; ?>" 
                            name="ceny[<?php echo $rok; ?>][poznamka]" 
                            class="gov-form-control" 
                            value="<?php echo htmlspecialchars($poznamkaValue); ?>"
                            placeholder="volitelné"
                        >
                    </div>
                    
                    <?php if ($existujiciCena): ?>
                        <small style="color: #666;">
                            Aktuální: <?php echo formatCena($existujiciCena['cena']); ?>
                            <?php if ($existujiciCena['je_manualni']): ?>
                                <span class="badge-manual">Manuální</span>
                            <?php else: ?>
                                <span class="badge-auto">Vypočítaná</span>
                            <?php endif; ?>
                            <br>
                            <a href="edit.php?id=<?php echo $meridlo['id']; ?>&delete_cena=1&delete_rok=<?php echo $rok; ?>" 
                               class="gov-button gov-button--small gov-button--secondary" 
                               style="margin-top: 0.5rem;"
                               onclick="return confirm('Opravdu smazat cenu pro rok <?php echo $rok; ?>?');">
                                Smazat cenu
                            </a>
                        </small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="alert alert-info" style="margin-top: 1rem;">
            <strong>Tip:</strong> Pokud necháte pole prázdné, cena pro daný rok nebude změněna. 
            Pro vypočtenou cenu systém automaticky použije inflaci z nejbližší dostupné ceny.
        </div>
    </section>
    
    <!-- Tlačítka -->
    <div class="form-actions">
        <button type="submit" class="gov-button gov-button--primary">
            Uložit změny
        </button>
        <a href="detail.php?id=<?php echo $meridlo['id']; ?>" class="gov-button gov-button--secondary">
            Zrušit
        </a>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
