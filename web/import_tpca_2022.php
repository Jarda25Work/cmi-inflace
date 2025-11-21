<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Import TPCA 2022</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .import-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats {
            background: #f8f9fa;
            padding: 1.5rem;
            margin: 1rem 0;
            border-radius: 4px;
            border-left: 4px solid #0062AD;
        }
        .highlight {
            font-weight: bold;
            color: #0062AD;
            font-size: 1.3rem;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            margin: 1rem 0;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 1rem;
            margin: 1rem 0;
            color: #721c24;
        }
        .log {
            background: #f8f9fa;
            padding: 1rem;
            margin: 1rem 0;
            max-height: 400px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.9rem;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="import-container">
        <h1>Import cen z TPCA2022.xls</h1>
        <p><strong>Podmínka:</strong> Importovat POUZE pro měřidla bez jakékoli ceny v databázi</p>
        
        <?php
        require_once __DIR__ . '/includes/config.php';
        require_once __DIR__ . '/includes/auth.php';
        require_once __DIR__ . '/includes/functions.php';
        require_once __DIR__ . '/vendor/autoload.php';

        use PhpOffice\PhpSpreadsheet\IOFactory;

        requireLogin();

        $xlsFile = __DIR__ . '/TPCA2022.xls';
        $rok = 2022;

        $confirm = isset($_POST['confirm']) && $_POST['confirm'] === '1';

        if (!file_exists($xlsFile)) {
            echo '<div class="error">';
            echo '<strong>CHYBA:</strong> Soubor nenalezen: ' . htmlspecialchars($xlsFile) . '<br>';
            echo 'Prosím zkopíruj TPCA2022.xls do složky web/';
            echo '</div>';
            exit;
        }

        if (!$confirm) {
            // FÁZE 1: KONTROLA

        try {
            $pdo = getDbConnection();
            
            echo '<p>Načítám Excel soubor...</p>';
            $spreadsheet = IOFactory::load($xlsFile);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            
            echo '<p>Celkem řádků v Excel: <strong>' . $highestRow . '</strong></p>';
            
            echo '<h2>Kontrola dat</h2>';
    $toImport = 0;
    $checkSkippedHasCena = 0;
    $checkSkippedNoCena = 0;
    $checkNotFound = 0;
    
    for ($row = 2; $row <= $highestRow; $row++) {
        $evidencni_cislo = trim($sheet->getCell('A' . $row)->getValue());
        $cena = $sheet->getCell('F' . $row)->getValue();
        
        if (empty($evidencni_cislo)) continue;
        
        if ($cena === null || $cena === '' || floatval($cena) <= 0) {
            $checkSkippedNoCena++;
            continue;
        }
        
        // Najdi měřidlo podle evidenčního čísla - číselné porovnání: 244 = 0244
        if (is_numeric($evidencni_cislo)) {
            $stmt = $pdo->prepare("SELECT id FROM meridla WHERE CAST(evidencni_cislo AS UNSIGNED) = ? AND aktivni = 1");
            $stmt->execute([intval($evidencni_cislo)]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM meridla WHERE evidencni_cislo = ? AND aktivni = 1");
            $stmt->execute([$evidencni_cislo]);
        }
        $meridlo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$meridlo) {
            $checkNotFound++;
            continue;
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ceny_meridel WHERE meridlo_id = ?");
        $stmt->execute([$meridlo['id']]);
        $maCenu = $stmt->fetchColumn() > 0;
        
        if ($maCenu) {
            $checkSkippedHasCena++;
            continue;
        }
        
            $toImport++;
        }
        
        echo '<div class="stats">';
        echo '<h3>Výsledek kontroly:</h3>';
        echo '<p class="highlight">Bude importováno: ' . $toImport . ' záznamů</p>';
        echo '<p>Přeskočeno (už má cenu): ' . $checkSkippedHasCena . '</p>';
        echo '<p>Přeskočeno (není cena): ' . $checkSkippedNoCena . '</p>';
        echo '<p>Nenalezeno v DB: ' . $checkNotFound . '</p>';
        echo '</div>';
        
        if ($toImport === 0) {
            echo '<div class="warning"><strong>Není co importovat.</strong></div>';
            echo '</div></body></html>';
            exit(0);
        }
        
        echo '<form method="POST">';
        echo '<input type="hidden" name="confirm" value="1">';
        echo '<button type="submit" class="gov-button gov-button--primary" style="font-size: 1.2rem; padding: 1rem 2rem;">Potvrdit a spustit import</button>';
        echo ' <a href="index.php" class="gov-button gov-button--secondary">Zrušit</a>';
        echo '</form>';
        
    } catch (Exception $e) {
        echo '<div class="error">';
        echo '<strong>CHYBA:</strong> ' . htmlspecialchars($e->getMessage()) . '<br>';
        echo 'Soubor: ' . htmlspecialchars($e->getFile()) . '<br>';
        echo 'Řádek: ' . $e->getLine();
        echo '</div>';
    }
    
} else {
    // FÁZE 2: IMPORT
    try {
        $pdo = getDbConnection();
        
        $spreadsheet = IOFactory::load($xlsFile);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        
        echo '<h2>Probíhá import...</h2>';
        echo '<div class="log">';
    $inserted = 0;
    $skippedHasCena = 0;
    $skippedNoCena = 0;
    $notFound = 0;
    $errors = [];
    
    // Projdi řádky (přeskoč hlavičku)
    for ($row = 2; $row <= $highestRow; $row++) {
        $evidencni_cislo = trim($sheet->getCell('A' . $row)->getValue());
        $cena = $sheet->getCell('F' . $row)->getValue();
        
        // Přeskoč prázdné evidenční číslo
        if (empty($evidencni_cislo)) {
            continue;
        }
        
        // Přeskoč bez ceny nebo neplatnou cenu
        if ($cena === null || $cena === '' || floatval($cena) <= 0) {
            $skippedNoCena++;
            continue;
        }
        
        $cena = floatval($cena);
        
        // Najdi měřidlo podle evidenčního čísla - číselné porovnání: 244 = 0244
        if (is_numeric($evidencni_cislo)) {
            $stmt = $pdo->prepare("SELECT id FROM meridla WHERE CAST(evidencni_cislo AS UNSIGNED) = ? AND aktivni = 1");
            $stmt->execute([intval($evidencni_cislo)]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM meridla WHERE evidencni_cislo = ? AND aktivni = 1");
            $stmt->execute([$evidencni_cislo]);
        }
        $meridlo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$meridlo) {
            $notFound++;
            $errors[] = "Měřidlo '$evidencni_cislo' nenalezeno v databázi";
            continue;
        }
        
        $meridlo_id = $meridlo['id'];
        
        // KLÍČOVÁ KONTROLA: Má měřidlo JAKOUKOLIV cenu v databázi?
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ceny_meridel WHERE meridlo_id = ?");
        $stmt->execute([$meridlo_id]);
        $maCenu = $stmt->fetchColumn() > 0;
        
        if ($maCenu) {
            $skippedHasCena++;
            continue; // Přeskoč - měřidlo už má nějakou cenu
        }
        
        // Vlož novou cenu pro rok 2022
        $stmt = $pdo->prepare("
            INSERT INTO ceny_meridel (meridlo_id, rok, cena, je_manualni, poznamka)
            VALUES (?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            $meridlo_id,
            $rok,
            $cena,
            "Importováno z TPCA2022.xls " . date('Y-m-d H:i:s')
        ]);
        $inserted++;
        echo '<p>✓ Měřidlo <strong>' . htmlspecialchars($evidencni_cislo) . '</strong> (ID ' . $meridlo_id . ') - nová cena ' . $rok . ': ' . $cena . ' Kč</p>';
        
        if ($inserted % 50 == 0) {
            echo '<p class="highlight">--- Zpracováno: ' . $inserted . ' záznamů ---</p>';
        }
    }
    
        echo '</div>'; // close .log div
        
        echo '<div class="stats">';
        echo '<h3>Import dokončen</h3>';
        echo '<p class="success"><strong>Vloženo nových cen: ' . $inserted . '</strong></p>';
        echo '<p>Přeskočeno (měřidlo už má cenu): ' . $skippedHasCena . '</p>';
        echo '<p>Přeskočeno (není cena v Excel): ' . $skippedNoCena . '</p>';
        echo '<p>Nenalezeno v databázi: ' . $notFound . '</p>';
        echo '</div>';
        
        if (!empty($errors)) {
            echo '<div class="warning">';
            echo '<h4>Nenalezená měřidla (prvních 20):</h4>';
            echo '<ul>';
            foreach (array_slice($errors, 0, 20) as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
            if (count($errors) > 20) {
                echo '<p>... a dalších ' . (count($errors) - 20) . ' nenalezených</p>';
            }
            echo '</div>';
        }
        
        // Kontrolní statistiky
        echo '<div class="stats">';
        echo '<h3>Kontrola databáze</h3>';
        $stmt = $pdo->query("SELECT COUNT(*) FROM ceny_meridel WHERE rok = $rok");
        $count = $stmt->fetchColumn();
        echo '<p>Celkový počet cen pro rok ' . $rok . ' v databázi: <strong>' . $count . '</strong></p>';
        
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT m.id) 
            FROM meridla m 
            WHERE m.aktivni = 1 
            AND NOT EXISTS (SELECT 1 FROM ceny_meridel c WHERE c.meridlo_id = m.id)
        ");
        $bezCeny = $stmt->fetchColumn();
        echo '<p>Měřidel stále bez jakékoli ceny: <strong>' . $bezCeny . '</strong></p>';
        echo '</div>';
        
        echo '<p><a href="index.php" class="gov-button gov-button--primary">Zpět na hlavní stránku</a></p>';
        
    } catch (Exception $e) {
        echo '<div class="error">';
        echo '<strong>CHYBA:</strong> ' . htmlspecialchars($e->getMessage()) . '<br>';
        echo 'Soubor: ' . htmlspecialchars($e->getFile()) . '<br>';
        echo 'Řádek: ' . $e->getLine();
        echo '</div>';
    }
}
?>
    </div>
</body>
</html>
