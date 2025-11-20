<?php
// Nastavení kódování na začátku souboru
header('Content-Type: text/html; charset=UTF-8');

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Vyžaduje přihlášení
requireLogin();

// Získání parametrů filtru
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$orderBy = isset($_GET['order']) ? $_GET['order'] : 'evidencni_cislo';
$orderDir = isset($_GET['dir']) ? $_GET['dir'] : 'ASC';
$filterOdchylky = isset($_GET['odchylky']) ? (int)$_GET['odchylky'] : 0;

// Získání všech dat (bez stránkování)
$result = getMeridla(1, $search, $orderBy, $orderDir, $filterOdchylky, 999999);
$meridla = $result['data'];

// Nastavení hlaviček pro Excel export
$filename = 'meridla_export_' . date('Y-m-d_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Začátek HTML tabulky pro Excel
echo "\xEF\xBB\xBF"; // UTF-8 BOM
?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Měřidla</x:Name>
                    <x:WorksheetOptions>
                        <x:Print>
                            <x:ValidPrinterInfo/>
                        </x:Print>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
</head>
<body>
<table border="1">
    <thead>
        <tr style="background-color: #0062AD; color: white; font-weight: bold;">
            <th>Evidenční číslo</th>
            <th>Název měřidla</th>
            <th>Firma kalibrující</th>
            <th>Status</th>
            <th>Kategorie</th>
            <th>Aktuální cena (<?php echo CURRENT_YEAR; ?>)</th>
            <th>Rok poslední ceny</th>
            <th>Certifikát</th>
            <th>Poslední kalibrace</th>
            <th>Plánovaná kalibrace</th>
            <th>Frekvence kalibrace</th>
            <th>Měřící rozsah</th>
            <th>Přesnost</th>
            <th>Dovolená odchylka</th>
            <th>Poznámka CMI</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($meridla as $meridlo): ?>
            <?php
            // Získání detailních dat
            $detail = getMeridloDetail($meridlo['id']);
            ?>
            <tr>
                <td><?php echo htmlspecialchars($detail['evidencni_cislo']); ?></td>
                <td><?php echo htmlspecialchars($detail['nazev_meridla']); ?></td>
                <td><?php echo htmlspecialchars($detail['firma_kalibrujici'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($detail['status'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($detail['kategorie'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars(formatCena($meridlo['aktualni_cena'])); ?></td>
                <td><?php echo htmlspecialchars($meridlo['rok_posledni_ceny'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($detail['certifikat'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($detail['posledni_kalibrace'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($detail['planovani_kalibrace'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($detail['frekvence_kalibrace'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($detail['mer_rozsah'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($detail['presnost'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($detail['dovolena_odchylka'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($detail['poznamka_cmi'] ?? ''); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
