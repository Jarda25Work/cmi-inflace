<?php
/**
 * Import historických cen měřidel z TPCA Excel souborů
 * 
 * Tento skript:
 * 1. Načte všechny TPCA Excel soubory ze složky zdroje/
 * 2. Sjednotí data z různých let (2012-2025)
 * 3. Importuje/aktualizuje ceny do databáze podle evidenčního čísla
 * 
 * DŮLEŽITÉ: Spustit ručně na serveru přes prohlížeč nebo CLI
 */

require_once __DIR__ . '/web/vendor/autoload.php';
require_once __DIR__ . '/web/includes/config.php';
require_once __DIR__ . '/web/includes/functions.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Nastavení časového limitu
set_time_limit(300); // 5 minut
ini_set('memory_limit', '512M');

echo "<html><head><meta charset='UTF-8'></head><body>";
echo "<h1>Import historických cen měřidel</h1>";
echo "<pre>";

// Soubory k importu
$sourceFiles = [
    'zdroje/TPCA2012.xls' => 2012,
    'zdroje/TPCA2016.xls' => 2016,
    'zdroje/TPCA2018.xls' => 2018,
    'zdroje/TPCA2020 .xls' => 2020,
    'zdroje/TPCA2021.xls' => 2021,
    'zdroje/TPCA2022.xls' => 2022,
    'zdroje/TPCA2023.xls' => 2023,
    'zdroje/TPCA2024.xls' => 2024,
    'zdroje/TPCA2025.xls' => 2025
];

$pdo = getDbConnection();
$stats = [
    'total_files' => 0,
    'total_rows' => 0,
    'imported' => 0,
    'updated' => 0,
    'skipped' => 0,
    'errors' => 0
];

foreach ($sourceFiles as $file => $year) {
    $filePath = __DIR__ . '/' . $file;
    
    if (!file_exists($filePath)) {
        echo "❌ Soubor nenalezen: $file\n";
        continue;
    }
    
    echo "\n📄 Zpracovávám: $file (rok $year)\n";
    echo str_repeat('-', 80) . "\n";
    
    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        
        echo "   Celkem řádků: $highestRow\n";
        
        // Najdi sloupce (mohou se lišit v různých souborech)
        $headerRow = 1;
        $evidCisloCol = null;
        $cenaCol = null;
        
        // Projdi první řádky a najdi hlavičky
        for ($col = 'A'; $col <= 'Z'; $col++) {
            $headerValue = strtolower(trim($sheet->getCell($col . $headerRow)->getValue()));
            
            if (strpos($headerValue, 'eviden') !== false || strpos($headerValue, 'číslo') !== false) {
                $evidCisloCol = $col;
            }
            if (strpos($headerValue, 'cena') !== false || strpos($headerValue, 'kč') !== false) {
                $cenaCol = $col;
            }
            
            // Zkus i druhý řádek, pokud je tam hlavička
            if (!$evidCisloCol || !$cenaCol) {
                $headerValue2 = strtolower(trim($sheet->getCell($col . '2')->getValue()));
                if (!$evidCisloCol && (strpos($headerValue2, 'eviden') !== false || strpos($headerValue2, 'číslo') !== false)) {
                    $evidCisloCol = $col;
                    $headerRow = 2;
                }
                if (!$cenaCol && (strpos($headerValue2, 'cena') !== false || strpos($headerValue2, 'kč') !== false)) {
                    $cenaCol = $col;
                    $headerRow = 2;
                }
            }
        }
        
        if (!$evidCisloCol || !$cenaCol) {
            echo "   ⚠️  Sloupce nenalezeny (Evidenční číslo: $evidCisloCol, Cena: $cenaCol)\n";
            echo "   Zkouším standardní pozice (A=evidenční číslo, C=cena)...\n";
            $evidCisloCol = 'A';
            $cenaCol = 'C';
        }
        
        echo "   Detekováno: Evidenční číslo=sloupec $evidCisloCol, Cena=sloupec $cenaCol, Hlavička=řádek $headerRow\n\n";
        
        $fileImported = 0;
        $fileUpdated = 0;
        $fileSkipped = 0;
        
        // Zpracuj data
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $stats['total_rows']++;
            
            $evidCislo = trim($sheet->getCell($evidCisloCol . $row)->getValue());
            $cenaValue = $sheet->getCell($cenaCol . $row)->getValue();
            
            // Přeskoč prázdné řádky
            if (empty($evidCislo) || empty($cenaValue)) {
                continue;
            }
            
            // Normalizuj evidenční číslo (odstraň mezery, speciální znaky)
            $evidCislo = preg_replace('/[^0-9]/', '', $evidCislo);
            if (empty($evidCislo)) {
                continue;
            }
            
            // Normalizuj cenu (odstraň měnu, mezery, převeď čárku na tečku)
            $cena = preg_replace('/[^0-9,.]/', '', $cenaValue);
            $cena = str_replace(',', '.', $cena);
            $cena = floatval($cena);
            
            if ($cena <= 0) {
                $fileSkipped++;
                continue;
            }
            
            // Najdi měřidlo podle evidenčního čísla
            $sqlFind = "SELECT id FROM meridla WHERE evidencni_cislo = ?";
            $stmtFind = $pdo->prepare($sqlFind);
            $stmtFind->execute([$evidCislo]);
            $meridlo = $stmtFind->fetch();
            
            if (!$meridlo) {
                $fileSkipped++;
                continue;
            }
            
            $meridloId = $meridlo['id'];
            
            // Zkontroluj, zda cena pro tento rok už existuje
            $sqlCheck = "SELECT id, cena FROM ceny_meridel WHERE meridlo_id = ? AND rok = ?";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$meridloId, $year]);
            $existingCena = $stmtCheck->fetch();
            
            if ($existingCena) {
                // Aktualizuj pouze pokud se cena liší
                if (abs($existingCena['cena'] - $cena) > 0.01) {
                    $sqlUpdate = "UPDATE ceny_meridel SET cena = ?, je_manualni = 1, updated_at = NOW() WHERE id = ?";
                    $stmtUpdate = $pdo->prepare($sqlUpdate);
                    $stmtUpdate->execute([$cena, $existingCena['id']]);
                    $fileUpdated++;
                    $stats['updated']++;
                } else {
                    $fileSkipped++;
                }
            } else {
                // Vlož novou cenu
                $sqlInsert = "INSERT INTO ceny_meridel (meridlo_id, rok, cena, je_manualni, created_at, updated_at) 
                              VALUES (?, ?, ?, 1, NOW(), NOW())";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([$meridloId, $year, $cena]);
                $fileImported++;
                $stats['imported']++;
            }
        }
        
        echo "   ✅ Importováno nových: $fileImported\n";
        echo "   🔄 Aktualizováno: $fileUpdated\n";
        echo "   ⏭️  Přeskočeno: $fileSkipped\n";
        
        $stats['total_files']++;
        
    } catch (Exception $e) {
        echo "   ❌ Chyba: " . $e->getMessage() . "\n";
        $stats['errors']++;
    }
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "📊 CELKOVÁ STATISTIKA\n";
echo str_repeat('=', 80) . "\n";
echo "Zpracováno souborů: {$stats['total_files']}\n";
echo "Celkem řádků: {$stats['total_rows']}\n";
echo "✅ Nově importováno: {$stats['imported']}\n";
echo "🔄 Aktualizováno: {$stats['updated']}\n";
echo "⏭️  Přeskočeno: {$stats['skipped']}\n";
echo "❌ Chyb: {$stats['errors']}\n";
echo "\n✅ Import dokončen!\n";

echo "</pre></body></html>";
