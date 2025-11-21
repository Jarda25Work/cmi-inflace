<?php
/**
 * Import cen za rok 2025 z CSV souboru
 * Soubor: ../zdroje/Inflace/Import_ceny_2025.csv
 * 
 * Spuštění: php import_ceny_2025.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$csvFile = __DIR__ . '/Import_ceny_2025.csv';
$rok = 2025;

echo "=== Import cen za rok $rok ===\n\n";

if (!file_exists($csvFile)) {
    die("CHYBA: Soubor nenalezen: $csvFile\n");
}

try {
    $pdo = getDbConnection();
    
    // Načti CSV
    $file = fopen($csvFile, 'r');
    if (!$file) {
        die("CHYBA: Nelze otevřít soubor: $csvFile\n");
    }
    
    // Přeskoč hlavičku
    $header = fgetcsv($file, 1000, ';');
    echo "Hlavička: " . implode(' | ', $header) . "\n\n";
    
    $inserted = 0;
    $updated = 0;
    $notFound = 0;
    $skipped = 0;
    $errors = [];
    $skippedRecords = [];
    
    while (($data = fgetcsv($file, 1000, ';')) !== false) {
        if (count($data) < 3) {
            continue;
        }
        
        $evidencni_cislo = trim($data[0]);
        $cena = floatval(str_replace(',', '.', $data[1]));
        $import_rok = intval($data[2]);
        
        if (empty($evidencni_cislo) || $cena <= 0) {
            $skipped++;
            $reason = empty($evidencni_cislo) ? "prázdné evidenční číslo" : "neplatná cena ($cena)";
            $skippedRecords[] = "Řádek: evidenční číslo='$evidencni_cislo', cena='$cena' - důvod: $reason";
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
            $notFound++;
            $errors[] = "Měřidlo '$evidencni_cislo' nenalezeno v databázi";
            continue;
        }
        
        $meridlo_id = $meridlo['id'];
        
        // Zkontroluj, zda už existuje cena pro tento rok
        $stmt = $pdo->prepare("SELECT id, cena FROM ceny_meridel WHERE meridlo_id = ? AND rok = ?");
        $stmt->execute([$meridlo_id, $rok]);
        $existujici = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existujici) {
            // Aktualizuj existující cenu
            $stmt = $pdo->prepare("
                UPDATE ceny_meridel 
                SET cena = ?, je_manualni = 1, poznamka = CONCAT(COALESCE(poznamka, ''), ' | Importováno z CSV ', NOW())
                WHERE id = ?
            ");
            $stmt->execute([$cena, $existujici['id']]);
            $updated++;
            echo "UPDATE: Měřidlo $evidencni_cislo (ID $meridlo_id) - cena aktualizována: {$existujici['cena']} → $cena Kč\n";
        } else {
            // Vlož novou cenu
            $stmt = $pdo->prepare("
                INSERT INTO ceny_meridel (meridlo_id, rok, cena, je_manualni, poznamka)
                VALUES (?, ?, ?, 1, ?)
            ");
            $stmt->execute([
                $meridlo_id,
                $rok,
                $cena,
                "Importováno z CSV " . date('Y-m-d H:i:s')
            ]);
            $inserted++;
            echo "INSERT: Měřidlo $evidencni_cislo (ID $meridlo_id) - nová cena: $cena Kč\n";
        }
    }
    
    fclose($file);
    
    echo "\n=== HOTOVO ===\n";
    echo "Vloženo nových cen: $inserted\n";
    echo "Aktualizováno cen: $updated\n";
    echo "Přeskočeno (neplatná data): $skipped\n";
    echo "Nenalezeno v databázi: $notFound\n";
    echo "Celkem zpracováno: " . ($inserted + $updated) . "\n";
    
    if (!empty($errors)) {
        echo "\n=== NENALEZENÁ MĚŘIDLA (prvních 20) ===\n";
        foreach (array_slice($errors, 0, 20) as $error) {
            echo "- $error\n";
        }
        if (count($errors) > 20) {
            echo "... a dalších " . (count($errors) - 20) . " nenalezených\n";
        }
    }
    
    if (!empty($skippedRecords)) {
        echo "\n=== PŘESKOČENÉ ZÁZNAMY (neplatná data) ===\n";
        foreach ($skippedRecords as $record) {
            echo "- $record\n";
        }
    }
    
    // Kontrolní dotaz
    echo "\n=== KONTROLA ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM ceny_meridel WHERE rok = $rok");
    $count = $stmt->fetchColumn();
    echo "Celkový počet cen pro rok $rok v databázi: $count\n";
    
} catch (Exception $e) {
    echo "CHYBA: " . $e->getMessage() . "\n";
    echo "Řádek: " . $e->getLine() . "\n";
    exit(1);
}
