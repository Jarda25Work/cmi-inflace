<?php
// Nastavení kódování na začátku souboru
header('Content-Type: text/html; charset=UTF-8');

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

// Vyžaduje přihlášení
requireLogin();

// Získání ID měřidla
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$meridloId = (int)$_GET['id'];

// Získání detailu měřidla
$meridlo = getMeridloDetail($meridloId);

if (!$meridlo) {
    header('Location: index.php');
    exit;
}

// Získání historie cen
$ceny = getCenyMeridla($meridloId);

$pageTitle = 'Detail měřidla: ' . $meridlo['evidencni_cislo'] . ' - ' . APP_NAME;
?>

<?php include 'includes/header.php'; ?>

<div class="gov-breadcrumbs" style="margin-top: 1rem;">
    <ol class="gov-breadcrumbs__list">
        <li class="gov-breadcrumbs__item">
            <a href="index.php" class="gov-breadcrumbs__link">Přehled měřidel</a>
        </li>
        <li class="gov-breadcrumbs__item" aria-current="page">
            <?php echo htmlspecialchars($meridlo['evidencni_cislo']); ?>
        </li>
    </ol>
</div>

<div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center;">
    <h1 class="gov-heading--large">Detail měřidla</h1>
    <?php if (isAdmin()): ?>
    <div style="display: flex; gap: 1rem;">
        <a href="edit.php?id=<?php echo $meridlo['id']; ?>" class="gov-button gov-button--primary">
            Editovat měřidlo
        </a>
        <a href="delete_meridlo.php?id=<?php echo $meridlo['id']; ?>&<?php echo http_build_query(['csrf_token' => generateCsrfToken()]); ?>" 
           class="gov-button gov-button--error"
           onclick="return confirm('Opravdu chcete smazat toto měřidlo včetně všech jeho cen?');">
            Smazat měřidlo
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if (isset($_GET['created']) && $_GET['created'] == 1): ?>
    <div class="gov-alert gov-alert--success" style="margin-top: 1rem;">
        <div class="gov-alert__content">
            <p>Měřidlo bylo úspěšně vytvořeno.</p>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="gov-alert gov-alert--error" style="margin-top: 1rem;">
        <div class="gov-alert__content">
            <p><strong>Chyba:</strong> 
            <?php 
                if ($_GET['error'] == 'delete') {
                    echo 'Nepodařilo se smazat měřidlo.';
                } else {
                    echo htmlspecialchars($_GET['error']);
                }
            ?>
            </p>
        </div>
    </div>
<?php endif; ?>

<!-- Základní informace -->
<div class="detail-section">
    <h2 class="gov-heading--medium">Základní údaje</h2>
    
    <div class="detail-grid">
        <div class="detail-item">
            <label>Evidenční číslo:</label>
            <div class="value"><?php echo htmlspecialchars($meridlo['evidencni_cislo']); ?></div>
        </div>
        
        <div class="detail-item">
            <label>Název měřidla:</label>
            <div class="value"><?php echo htmlspecialchars($meridlo['nazev_meridla']); ?></div>
        </div>
        
        <div class="detail-item">
            <label>Firma kalibrující:</label>
            <div class="value"><?php echo htmlspecialchars($meridlo['firma_kalibrujici'] ?? '-'); ?></div>
        </div>
        
        <div class="detail-item">
            <label>Status:</label>
            <div class="value"><?php echo htmlspecialchars($meridlo['status'] ?? '-'); ?></div>
        </div>
        
        <div class="detail-item">
            <label>Certifikát:</label>
            <div class="value"><?php echo htmlspecialchars($meridlo['certifikat'] ?? '-'); ?></div>
        </div>
        
        <div class="detail-item">
            <label>Kategorie:</label>
            <div class="value"><?php echo htmlspecialchars($meridlo['kategorie'] ?? '-'); ?></div>
        </div>
    </div>
</div>

<!-- Kalibrace -->
<div class="detail-section">
    <h2 class="gov-heading--medium">Kalibrace</h2>
    
    <div class="detail-grid">
        <div class="detail-item">
            <label>Datum poslední kalibrace:</label>
            <div class="value">
                <?php echo $meridlo['datum_posledni_kalibrace'] 
                    ? date('d.m.Y', strtotime($meridlo['datum_posledni_kalibrace'])) 
                    : '-'; ?>
            </div>
        </div>
        
        <div class="detail-item">
            <label>Plánovaná kalibrace:</label>
            <div class="value">
                <?php echo $meridlo['planovani_kalibrace'] 
                    ? date('d.m.Y', strtotime($meridlo['planovani_kalibrace'])) 
                    : '-'; ?>
            </div>
        </div>
        
        <div class="detail-item">
            <label>Frekvence kalibrace:</label>
            <div class="value"><?php echo htmlspecialchars($meridlo['frekvence_kalibrace'] ?? '-'); ?></div>
        </div>
        
        <div class="detail-item">
            <label>Poslední kalibrace provedl:</label>
            <div class="value"><?php echo htmlspecialchars($meridlo['posledni_kalibrace'] ?? '-'); ?></div>
        </div>
    </div>
</div>

<!-- Technické parametry -->
<div class="detail-section">
    <h2 class="gov-heading--medium">Technické parametry</h2>
    
    <div class="detail-grid">
        <div class="detail-item">
            <label>Měřící rozsah:</label>
            <div class="value"><?php echo htmlspecialchars($meridlo['mer_rozsah'] ?? '-'); ?></div>
        </div>
        
        <div class="detail-item">
            <label>Přesnost:</label>
            <div class="value"><?php echo htmlspecialchars($meridlo['presnost'] ?? '-'); ?></div>
        </div>
        
        <div class="detail-item">
            <label>Dovolená odchylka:</label>
            <div class="value"><?php echo htmlspecialchars($meridlo['dovolena_odchylka'] ?? '-'); ?></div>
        </div>
        
        <div class="detail-item">
            <label>Poznámka CMI:</label>
            <div class="value"><?php echo nl2br(htmlspecialchars($meridlo['poznamka_cmi'] ?? '-')); ?></div>
        </div>
    </div>
</div>

<!-- Historie cen -->
<div class="detail-section">
    <h2 class="gov-heading--medium">Historie cen</h2>
    
    <?php if (empty($ceny)): ?>
        <p class="gov-body-text">Pro toto měřidlo nejsou evidovány žádné ceny.</p>
        <?php if (isAdmin()): ?>
        <p class="gov-body-text">
            <a href="edit.php?id=<?php echo $meridlo['id']; ?>" class="gov-link">
                Přidat cenu
            </a>
        </p>
        <?php endif; ?>
    <?php else: ?>
        <div class="history-table">
            <table class="gov-table">
                <thead>
                    <tr>
                        <th>Rok</th>
                        <th>Cena</th>
                        <th>Typ</th>
                        <th>Poznámka</th>
                        <th>Vytvořeno</th>
                        <th>Aktualizováno</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ceny as $cena): ?>
                        <?php
                        // Kontrola odchylky u ručně zadaných cen
                        $odchylka = null;
                        $rowClass = '';
                        $tooltip = '';
                        
                        if ($cena['je_manualni']) {
                            $odchylka = zjistiOdchylkuCeny($meridlo['id'], $cena['rok'], $cena['cena'], $cena['ignorovat_odchylku']);
                            if ($odchylka['je_odchylna']) {
                                $rowClass = 'class="cena-warning"';
                                $tooltip = sprintf(
                                    'title="⚠ Odchylka %.1f%% od vypočtené ceny %s"',
                                    $odchylka['odchylka_procent'],
                                    formatCena($odchylka['vypocitana_cena'])
                                );
                            }
                        }
                        ?>
                        <tr <?php echo $rowClass; ?> <?php echo $tooltip; ?>>
                            <td><strong><?php echo $cena['rok']; ?></strong></td>
                            <td>
                                <?php echo formatCena($cena['cena']); ?>
                                <?php if ($odchylka && $odchylka['je_odchylna']): ?>
                                    <span style="color: #dc3545; font-size: 1.2em; margin-left: 0.3rem;" title="Odchylka od vypočtené ceny">⚠</span>
                                <?php endif; ?>
                                <?php if ($cena['ignorovat_odchylku']): ?>
                                    <span style="color: #6c757d; font-size: 0.9em; margin-left: 0.3rem;" title="Kontrola odchylky ignorována">🔕</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cena['je_manualni']): ?>
                                    <span class="badge-manual">Manuální</span>
                                <?php else: ?>
                                    <span class="badge-auto">Vypočítaná</span>
                                <?php endif; ?>
                                <?php if ($cena['ignorovat_odchylku']): ?>
                                    <br><small style="color: #6c757d;">Ignorovat odchylku</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($cena['poznamka'] ?? '-'); ?>
                                <?php if ($odchylka && $odchylka['je_odchylna']): ?>
                                    <br><small style="color: #dc3545;">Vypočteno: <?php echo formatCena($odchylka['vypocitana_cena']); ?> (odchylka <?php echo number_format($odchylka['odchylka_procent'], 1); ?>%)</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($cena['created_at'])); ?></td>
                            <td>
                                <?php echo $cena['updated_at'] 
                                    ? date('d.m.Y H:i', strtotime($cena['updated_at'])) 
                                    : '-'; ?>
                            </td>
                            <td>
                                <?php if (isAdmin()): ?>
                                <a href="edit.php?id=<?php echo $meridlo['id']; ?>&edit_rok=<?php echo $cena['rok']; ?>" 
                                   class="gov-button gov-button--small gov-button--secondary">
                                    Upravit
                                </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Náhled vypočítaných cen pro roky bez záznamu -->
<div class="detail-section">
    <h2 class="gov-heading--medium">Vypočítané ceny (inflace)</h2>
    
    <?php
    // Získat roky kde už jsou ceny uložené
    $ulozeneRoky = array_column($ceny, 'rok');
    
    // Zobrazit vypočítané ceny pro roky 2016 až CURRENT_YEAR
    $vypocitaneCeny = getVypocitaneCeny($meridloId, 2016, CURRENT_YEAR);
    ?>
    
    <p class="gov-body-text">
        Následující ceny jsou vypočítány na základě inflace z nejbližší dostupné ceny. 
        Pokud chcete cenu pro určitý rok uložit jako manuální, přejděte do režimu editace.
    </p>
    
    <div class="table-wrapper">
        <table class="gov-table">
            <thead>
                <tr>
                    <th>Rok</th>
                    <th>Vypočítaná cena</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vypocitaneCeny as $rok => $cena): ?>
                    <tr>
                        <td><strong><?php echo $rok; ?></strong></td>
                        <td><?php echo formatCena($cena); ?></td>
                        <td>
                            <?php if (in_array($rok, $ulozeneRoky)): ?>
                                <span class="badge-manual">Uložená</span>
                            <?php else: ?>
                                <span style="color: #666;">Jen vypočteno</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top: 2rem;">
    <a href="index.php" class="gov-button gov-button--secondary">
        &larr; Zpět na přehled
    </a>
</div>

<?php include 'includes/footer.php'; ?>
