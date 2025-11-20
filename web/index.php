<?php
// Nastavení kódování na začátku souboru
header('Content-Type: text/html; charset=UTF-8');

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Vyžaduje přihlášení
requireLogin();

// Dynamický rok pro zobrazení cen
$displayYear = (int)getKonfigurace('display_year', CURRENT_YEAR);

// Stránkování, vyhledávání a řazení
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$orderBy = isset($_GET['order']) ? $_GET['order'] : 'evidencni_cislo';
$orderDir = isset($_GET['dir']) ? $_GET['dir'] : 'ASC';
$filterOdchylky = isset($_GET['odchylky']) ? (int)$_GET['odchylky'] : 0; // 2 = pouze bez ceny, 3 = přesné slovo
$exactMatch = ($filterOdchylky === 3); // přesné hledání když je vybrána hodnota 3

// Získání dat
$result = getMeridla($page, $search, $orderBy, $orderDir, $filterOdchylky, ITEMS_PER_PAGE, $exactMatch);
$displayYear = $result['display_year'];
$meridla = $result['data'];
$totalPages = $result['pages'];
$total = $result['total'];

// Funkce pro vytvoření URL pro řazení
function getSortUrl($column) {
    global $orderBy, $orderDir, $search, $filterOdchylky;
    
    // Pokud klikáme na stejný sloupec, obrať směr
    $newDir = ($orderBy === $column && $orderDir === 'ASC') ? 'DESC' : 'ASC';
    
    $params = ['order' => $column, 'dir' => $newDir];
    if ($search) {
        $params['search'] = $search;
    }
    if ($filterOdchylky) {
        $params['odchylky'] = $filterOdchylky;
    }
    
    return 'index.php?' . http_build_query($params);
}

// Funkce pro získání ikony řazení
function getSortIcon($column) {
    global $orderBy, $orderDir;
    
    if ($orderBy !== $column) {
        return ' ⇅';
    }
    
    return $orderDir === 'ASC' ? ' ▲' : ' ▼';
}

$pageTitle = 'Přehled měřidel - ' . APP_NAME;
?>

<?php include 'includes/header.php'; ?>

<div class="gov-breadcrumbs" style="margin-top: 1rem;">
    <ol class="gov-breadcrumbs__list">
        <li class="gov-breadcrumbs__item">
            <a href="index.php" class="gov-breadcrumbs__link">Přehled měřidel</a>
        </li>
    </ol>
</div>

<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-top: 2rem;">
    <div>
        <h1 class="gov-heading--large" style="margin: 0;">Přehled měřidel</h1>
        <p class="gov-body-text">Celkem <strong><?php echo $total; ?></strong> měřidel</p>
    </div>
    <?php if (isAdmin()): ?>
        <a href="add.php" class="gov-button gov-button--primary">
            + Přidat měřidlo
        </a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
    <div class="gov-alert gov-alert--success" style="margin-top: 1rem;">
        <div class="gov-alert__content">
            <p>Měřidlo bylo úspěšně smazáno.</p>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] == 'notfound'): ?>
    <div class="gov-alert gov-alert--error" style="margin-top: 1rem;">
        <div class="gov-alert__content">
            <p>Měřidlo nebylo nalezeno.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Vyhledávání a filtry -->
<form method="GET" action="index.php" class="search-form" id="searchForm">
    <div class="gov-form-group search-autocomplete">
        <label for="searchInput" class="gov-label">Vyhledat měřidlo</label>
        <input 
            type="text" 
            id="searchInput" 
            name="search" 
            class="gov-form-control" 
            placeholder="Evidenční číslo, název nebo firma..."
            value="<?php echo htmlspecialchars($search); ?>"
            autocomplete="off"
        >
        <div id="searchSuggestions" class="autocomplete-suggestions"></div>
    </div>
    <div class="gov-form-group" style="flex-shrink: 0; margin-left: 2em;">
        <label for="filterOdchylky" class="gov-label">Filtr</label>
        <select id="filterOdchylky" name="odchylky" class="gov-form-control" style="width: 180px;">
            <option value="0" <?php echo $filterOdchylky == 0 && !$exactMatch ? 'selected' : ''; ?>>Všechna měřidla</option>
            <option value="2" <?php echo $filterOdchylky == 2 ? 'selected' : ''; ?>>Pouze bez ceny</option>
            <option value="3" <?php echo $exactMatch ? 'selected' : ''; ?>>Přesné slovo</option>
        </select>
    </div>
    <button type="submit" class="gov-button gov-button--primary">Filtrovat</button>
    <?php if ($search || $filterOdchylky): ?>
        <a href="index.php" class="gov-button gov-button--secondary">Zrušit filtry</a>
    <?php endif; ?>
</form>

<!-- Export do Excelu -->
<div style="margin-top: 1rem; display: flex; gap: 0.5rem; align-items: center;">
    <?php
    // Připrav URL parametry pro export
    $exportParams = [];
    if ($search) {
        $exportParams['search'] = $search;
    }
    if ($orderBy !== 'evidencni_cislo' || $orderDir !== 'ASC') {
        $exportParams['order'] = $orderBy;
        $exportParams['dir'] = $orderDir;
    }
    if ($filterOdchylky) {
        $exportParams['odchylky'] = $filterOdchylky;
    }
    $exportUrl = 'export_excel.php' . ($exportParams ? '?' . http_build_query($exportParams) : '');
    $exportAllUrl = 'export_excel.php';
    ?>
    
    <?php
    // Připrav URL pro XLSX export
    $exportXlsxUrl = str_replace('export_excel.php', 'export_xlsx.php', $exportUrl);
    $exportAllXlsxUrl = str_replace('export_excel.php', 'export_xlsx.php', $exportAllUrl);
    ?>
    
    <?php if ($search || $filterOdchylky): ?>
        <a href="<?php echo $exportXlsxUrl; ?>" class="gov-button gov-button--secondary" style="background: #28a745; color: white; border-color: #28a745;">
            📊 Exportovat výsledky (<?php echo $total; ?>)
        </a>
        <a href="<?php echo $exportAllXlsxUrl; ?>" class="gov-button gov-button--secondary" style="background: #28a745; color: white; border-color: #28a745;">
            📊 Exportovat vše
        </a>
    <?php else: ?>
        <a href="<?php echo $exportXlsxUrl; ?>" class="gov-button gov-button--secondary" style="background: #28a745; color: white; border-color: #28a745;">
            📊 Exportovat do Excelu
        </a>
    <?php endif; ?>
</div>

<!-- Tabulka s měřidly -->
<div class="meridla-table">
    <?php if (empty($meridla)): ?>
        <div class="alert alert-info">
            <?php if ($filterOdchylky == 2 && $search): ?>
                Nebyly nalezeny žádné měřidla bez ceny odpovídající vašemu hledání.
            <?php elseif ($filterOdchylky == 2): ?>
                Nebyla nalezena žádná měřidla bez ceny. Všechna měřidla mají zadanou cenu.
            <?php elseif ($search): ?>
                Nebyly nalezeny žádné měřidla odpovídající vašemu hledání.
            <?php else: ?>
                V systému nejsou evidována žádná měřidla.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="gov-table">
                <thead>
                    <tr>
                        <th>
                            <a href="<?php echo getSortUrl('evidencni_cislo'); ?>" class="sort-link">
                                Evidenční číslo<?php echo getSortIcon('evidencni_cislo'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo getSortUrl('nazev_meridla'); ?>" class="sort-link">
                                Název měřidla<?php echo getSortIcon('nazev_meridla'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo getSortUrl('firma_kalibrujici'); ?>" class="sort-link">
                                Firma kalibrující<?php echo getSortIcon('firma_kalibrujici'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo getSortUrl('status'); ?>" class="sort-link">
                                Status<?php echo getSortIcon('status'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo getSortUrl('kategorie'); ?>" class="sort-link">
                                Kategorie<?php echo getSortIcon('kategorie'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo getSortUrl('aktualni_cena'); ?>" class="sort-link">
                                Aktuální cena (<?php echo $displayYear; ?>)<?php echo getSortIcon('aktualni_cena'); ?>
                            </a>
                        </th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meridla as $meridlo): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($meridlo['evidencni_cislo']); ?>
                            </td>
                            <td>
                                <a href="detail.php?id=<?php echo $meridlo['id']; ?>" class="gov-link">
                                    <?php echo htmlspecialchars($meridlo['nazev_meridla']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($meridlo['firma_kalibrujici'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($meridlo['status'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($meridlo['kategorie'] ?? '-'); ?></td>
                            <td>
                                <strong><?php echo formatCena($meridlo['aktualni_cena']); ?></strong>
                                <?php if ($meridlo['rok_posledni_ceny'] && $meridlo['rok_posledni_ceny'] < $displayYear): ?>
                                    <br><small style="color: #666;">(dopočteno inflací z roku <?php echo $meridlo['rok_posledni_ceny']; ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="detail.php?id=<?php echo $meridlo['id']; ?>" class="gov-button gov-button--small gov-button--secondary">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Stránkování -->
        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Stránkování">
                <?php
                // Vytvoř parametry pro paginaci
                $params = [];
                if ($search) {
                    $params['search'] = $search;
                }
                if ($filterOdchylky) {
                    $params['odchylky'] = $filterOdchylky;
                }
                if ($orderBy !== 'evidencni_cislo' || $orderDir !== 'ASC') {
                    $params['order'] = $orderBy;
                    $params['dir'] = $orderDir;
                }
                
                function getPaginationUrl($pageNum, $params) {
                    $params['page'] = $pageNum;
                    return '?' . http_build_query($params);
                }
                
                // Předchozí stránka
                if ($page > 1): ?>
                    <a href="<?php echo getPaginationUrl($page - 1, $params); ?>" class="pagination-prev">
                        &laquo; Předchozí
                    </a>
                <?php endif;
                
                // Čísla stránek
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                
                if ($start > 1): ?>
                    <a href="<?php echo getPaginationUrl(1, $params); ?>">1</a>
                    <?php if ($start > 2): ?>
                        <span>...</span>
                    <?php endif;
                endif;
                
                for ($i = $start; $i <= $end; $i++):
                    if ($i == $page): ?>
                        <span><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo getPaginationUrl($i, $params); ?>"><?php echo $i; ?></a>
                    <?php endif;
                endfor;
                
                if ($end < $totalPages):
                    if ($end < $totalPages - 1): ?>
                        <span>...</span>
                    <?php endif; ?>
                    <a href="<?php echo getPaginationUrl($totalPages, $params); ?>"><?php echo $totalPages; ?></a>
                <?php endif;
                
                // Další stránka
                if ($page < $totalPages): ?>
                    <a href="<?php echo getPaginationUrl($page + 1, $params); ?>" class="pagination-next">
                        Další &raquo;
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
