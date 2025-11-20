<?php
/**
 * Import historickych cen z TPCA_Slouceny_Kompletni.xlsx
 * Importuje pouze ceny pro meridla, ktera existuji v databazi
 */

require_once __DIR__ . '/web/includes/config.php';

echo "=============================================================\n";
echo "Import historickych cen (2016-2024) do MySQL\n";
echo "=============================================================\n\n";

$csvFile = __DIR__ . '/historicke_ceny.csv';

if (!file_exists($csvFile)) {
    die("CSV soubor nenalezen: $csvFile\n");
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "Pripojeno k databazi\n\n";
    
    // Nacti vsechna evidencni cisla z databaze
    $stmt = $pdo->query("SELECT id, evidencni_cislo FROM meridla");
    $existujiciMeridla = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existujiciMeridla[$row['evidencni_cislo']] = $row['id'];
    }
    
    echo "V databazi je celkem " . count($existujiciMeridla) . " meridel\n\n";
    
    // Priprav INSERT statement
    $insertSQL = "INSERT INTO ceny_meridel (meridlo_id, rok, cena, je_manualni, poznamka) 
                  VALUES (?, ?, ?, 1, ?)
                  ON DUPLICATE KEY UPDATE 
                  cena = VALUES(cena),
                  je_manualni = VALUES(je_manualni),
                  poznamka = VALUES(poznamka)";
    
    $insertStmt = $pdo->prepare($insertSQL);
    
    // Otevri CSV
    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        die("Nelze otevrit CSV soubor\n");
    }
    
    // Skip header
    fgets($handle);
    
    echo "Zacinam import...\n";
    echo "------------------------------------------------------------\n";
    
    $processedCount = 0;
    $importedPricesCount = 0;
    $skippedCount = 0;
    $lineNumber = 1;
    
    $roky = [2016, 2018, 2020, 2021, 2022, 2023, 2024];
    
    while (($line = fgets($handle)) !== false) {
        $lineNumber++;
        $line = trim($line);
        
        if (empty($line)) {
            continue;
        }
        
        // Parse CSV line
        $data = str_getcsv($line, ',', '"');
        
        if (count($data) < 8) {
            echo "Varovani: Radek $lineNumber ma malo sloupcu, preskakuji\n";
            continue;
        }
        
        $evidencni_cislo = trim($data[0]);
        
        if (empty($evidencni_cislo)) {
            continue;
        }
        
        // Zkontroluj, zda meridlo existuje v databazi
        if (!isset($existujiciMeridla[$evidencni_cislo])) {
            $skippedCount++;
            if ($skippedCount <= 5) {
                echo "SKIP: $evidencni_cislo (nenalezeno v databazi)\n";
            }
            continue;
        }
        
        $meridlo_id = $existujiciMeridla[$evidencni_cislo];
        $processedCount++;
        
        if ($processedCount <= 20 || $processedCount % 50 == 0) {
            echo "[$processedCount] $evidencni_cislo => ";
        }
        
        $importedForThisMeridlo = 0;
        
        // Importuj ceny pro jednotlive roky
        for ($i = 0; $i < count($roky); $i++) {
            $rok = $roky[$i];
            $cena_text = trim($data[$i + 1]); // +1 protoze 0 je evidencni_cislo
            
            if ($cena_text === '' || $cena_text === '0') {
                continue;
            }
            
            // Prevod na cislo
            $cena = floatval($cena_text);
            
            if ($cena <= 0) {
                continue;
            }
            
            // Insert do databaze
            $insertStmt->execute([
                $meridlo_id,
                $rok,
                $cena,
                "Import z TPCA sloučený - rok $rok"
            ]);
            
            $importedPricesCount++;
            $importedForThisMeridlo++;
        }
        
        if ($processedCount <= 20 || $processedCount % 50 == 0) {
            echo "$importedForThisMeridlo cen\n";
        }
    }
    
    fclose($handle);
    
    echo "------------------------------------------------------------\n\n";
    
    // Statistiky
    echo "=============================================================\n";
    echo "VYSLEDEK IMPORTU\n";
    echo "=============================================================\n";
    echo "Zpracovano meridel: $processedCount\n";
    echo "Preskoceno (neexistuje v DB): $skippedCount\n";
    echo "Importovano cen celkem: $importedPricesCount\n\n";
    
    // Statistika podle roku
    echo "Ceny podle roku:\n";
    foreach ($roky as $rok) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as pocet FROM ceny_meridel WHERE rok = ?");
        $stmt->execute([$rok]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];
        echo "  Rok $rok: $count cen\n";
    }
    
    // Rok 2025 (uz mame z predchoziho importu)
    $stmt = $pdo->prepare("SELECT COUNT(*) as pocet FROM ceny_meridel WHERE rok = 2025");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];
    echo "  Rok 2025: $count cen (z predchoziho importu)\n";
    
    echo "\n";
    
    // Ukazka meridel s nejvice cenami
    echo "Top 5 meridel s nejvice historickymi cenami:\n";
    $stmt = $pdo->query("
        SELECT m.evidencni_cislo, m.nazev_meridla, COUNT(c.rok) as pocet_cen
        FROM meridla m
        JOIN ceny_meridel c ON m.id = c.meridlo_id
        GROUP BY m.id
        ORDER BY pocet_cen DESC
        LIMIT 5
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['evidencni_cislo']} - {$row['nazev_meridla']}: {$row['pocet_cen']} cen\n";
    }
    
    echo "\n";
    echo "Import dokoncen uspesne!\n";
    echo "=============================================================\n";
    
} catch (PDOException $e) {
    die("Chyba databaze: " . $e->getMessage() . "\n");
}
