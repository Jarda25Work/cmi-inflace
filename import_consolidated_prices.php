<?php
/**
 * Import cen z TPCA_Consolidated.xlsx do databáze
 * 
 * BEZPEČNOSTNÍ UPOZORNĚNÍ:
 * - Tento soubor spusťte POUZE RUČNĚ na serveru
 * - Po úspěšném importu tento soubor OKAMŽITĚ SMAŽTE
 * - Doporučujeme zálohu databáze před importem
 * 
 * Spuštění:
 * 1. Nahrajte tento soubor + TPCA_Consolidated.xlsx na server
 * 2. Spusťte přes prohlížeč: https://meridla.cmi.cz/import_consolidated_prices.php
 * 3. Nebo přes SSH: php import_consolidated_prices.php
 * 4. Po dokončení SMAŽTE tento soubor i TPCA_Consolidated.xlsx
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

set_time_limit(300);
ini_set('memory_limit', '512M');

// Získej databázové připojení
$pdo = getDbConnection();

// Výstup v HTML
header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Import TPCA cen</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} table{border-collapse:collapse;margin-top:20px;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#0062AD;color:white;}</style>";
echo "</head><body>";

echo "<h1>🔄 Import cen z TPCA_Consolidated.xlsx</h1>";
echo "<p><strong>⚠️ UPOZORNĚNÍ:</strong> Po dokončení smažte tento soubor!</p><hr>";

$sourceFile = __DIR__ . '/TPCA_Consolidated.xlsx';

// Kontrola existence souboru
if (!file_exists($sourceFile)) {
    echo "<p class='error'>❌ Chyba: Soubor TPCA_Consolidated.xlsx nenalezen!</p>";
    echo "<p>Očekávaná cesta: $sourceFile</p>";
    echo "</body></html>";
    exit;
}

echo "<p>📄 Načítám soubor: TPCA_Consolidated.xlsx</p>";

try {
    $spreadsheet = IOFactory::load($sourceFile);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();
    
    echo "<p>✅ Soubor načten, celkem řádků: " . ($highestRow - 1) . "</p>";
    
    // Statistiky
    // Povolit automatické vytvoření chybějících měřidel? (lze vypnout parametrem create=0)
    $ALLOW_CREATE_MERIDLA = !isset($_GET['create']) || $_GET['create'] !== '0';

    $stats = [
        'total' => 0,
        'created' => 0,
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'not_found' => 0,
        'errors' => 0
    ];
    
    $errors = [];
    
    // Sloupce roků (C-K, protože B je název)
    $yearColumns = [
        'C' => 2012,
        'D' => 2016,
        'E' => 2018,
        'F' => 2020,
        'G' => 2021,
        'H' => 2022,
        'I' => 2023,
        'J' => 2024,
        'K' => 2025
    ];
    
    echo "<p>🔄 Zpracovávám záznamy...</p>";
    echo "<div style='max-height:400px;overflow-y:auto;border:1px solid #ccc;padding:10px;background:#f9f9f9;'>";
    
    // Projdi všechny řádky (od 2, protože 1 je hlavička)
    for ($row = 2; $row <= $highestRow; $row++) {
        $evidCislo = trim($sheet->getCell('A' . $row)->getValue());
        $nazev = trim($sheet->getCell('B' . $row)->getValue());
        
        if (empty($evidCislo)) {
            continue;
        }
        
        $stats['total']++;
        
        // Inicializace ID
        $meridloId = null;
        // Najdi měřidlo v databázi (porovnej číselně kvůli leading zeros)
        $stmt = $pdo->prepare("SELECT id FROM meridla WHERE CAST(evidencni_cislo AS UNSIGNED) = CAST(? AS UNSIGNED)");
        $stmt->execute([$evidCislo]);
        $meridlo = $stmt->fetch();
        
        if (!$meridlo) {
            if ($ALLOW_CREATE_MERIDLA) {
                // Pokus o vytvoření nového měřidla
                try {
                    // Normalizace evidenčního čísla – zachovat původní pokud má délku >= 4, jinak doplnit na 4 znaky
                    $evidForCreate = $evidCislo;
                    if (preg_match('/^[0-9]+$/', $evidCislo)) {
                        $trimmed = ltrim($evidCislo, '0');
                        if ($trimmed === '') { $trimmed = '0'; }
                        if (strlen($evidCislo) < 4) {
                            $evidForCreate = str_pad($trimmed, 4, '0', STR_PAD_LEFT);
                        }
                    }
                    $newId = createMeridlo([
                        'evidencni_cislo' => $evidForCreate,
                        'nazev_meridla' => $nazev ?: ('Měřidlo ' . $evidForCreate),
                        'firma_kalibrujici' => null,
                        'status' => 'Importováno',
                        'certifikat' => null,
                        'posledni_kalibrace' => null,
                        'planovani_kalibrace' => null,
                        'frekvence_kalibrace' => null,
                        'kategorie' => null,
                        'dovolena_odchylka' => null,
                        'mer_rozsah' => null,
                        'presnost' => null,
                        'poznamka_cmi' => 'Automaticky vytvořeno importem TPCA'
                    ]);
                    if ($newId) {
                        $stats['created']++;
                        echo "<span class='success'>🆕 Řádek $row: Vytvořeno nové měřidlo $evidForCreate ($nazev), ID=$newId</span><br>";
                        $meridloId = $newId;
                    } else {
                        echo "<span class='warning'>⚠️ Řádek $row: Nelze vytvořit měřidlo $evidCislo ($nazev)</span><br>";
                        $stats['not_found']++;
                        $errors[] = "Řádek $row: Nelze vytvořit měřidlo $evidCislo ($nazev)";
                        continue;
                    }
                } catch (Exception $ex) {
                    echo "<span class='error'>❌ Řádek $row: Chyba při vytváření měřidla $evidCislo ($nazev): " . htmlspecialchars($ex->getMessage()) . "</span><br>";
                    $stats['errors']++;
                    $errors[] = "Řádek $row: Chyba createMeridlo $evidCislo ($nazev): " . $ex->getMessage();
                    continue;
                }
            } else {
                echo "<span class='warning'>⚠️ Řádek $row: Měřidlo $evidCislo ($nazev) nenalezeno v databázi</span><br>";
                $stats['not_found']++;
                $errors[] = "Řádek $row: Měřidlo $evidCislo ($nazev) neexistuje v databázi";
                continue;
            }
        }
        
        if ($meridlo && !$meridloId) {
            $meridloId = $meridlo['id'];
        }

        // Bez validního ID nelze pokračovat
        if (!$meridloId) {
            echo "<span class='error'>❌ Řádek $row: meridlo_id je prázdné, řádek přeskočen</span><br>";
            $stats['errors']++;
            $errors[] = "Řádek $row: meridlo_id NULL pro evidenční číslo $evidCislo";
            continue;
        }
        $importedCount = 0;
        $updatedCount = 0;
        
        // Projdi všechny roky
        foreach ($yearColumns as $col => $year) {
            $cenaValue = $sheet->getCell($col . $row)->getValue();
            
            if (empty($cenaValue)) {
                continue; // Prázdná cena, přeskoč
            }
            
            $cena = floatval($cenaValue);
            
            if ($cena <= 0) {
                continue;
            }
            
            try {
                // Zkontroluj existenci ceny
                $stmt = $pdo->prepare("SELECT id, cena FROM ceny_meridel WHERE meridlo_id = ? AND rok = ?");
                $stmt->execute([$meridloId, $year]);
                $existingCena = $stmt->fetch();
                
                if ($existingCena) {
                    // Cena existuje - porovnej hodnoty
                    if (abs($existingCena['cena'] - $cena) > 0.01) {
                        // Aktualizuj pouze pokud se liší
                        $stmt = $pdo->prepare("UPDATE ceny_meridel SET cena = ?, je_manualni = 1 WHERE id = ?");
                        $stmt->execute([$cena, $existingCena['id']]);
                        $updatedCount++;
                    }
                } else {
                    // Nová cena - vlož
                    $stmt = $pdo->prepare("INSERT INTO ceny_meridel (meridlo_id, rok, cena, je_manualni) VALUES (?, ?, ?, 1)");
                    $stmt->execute([$meridloId, $year, $cena]);
                    $importedCount++;
                }
                
            } catch (Exception $e) {
                echo "<span class='error'>❌ Chyba při importu měřidla $evidCislo, rok $year: " . htmlspecialchars($e->getMessage()) . "</span><br>";
                $stats['errors']++;
                $errors[] = "Měřidlo $evidCislo, rok $year: " . $e->getMessage();
            }
        }
        
        if ($importedCount > 0 || $updatedCount > 0) {
            echo "<span class='success'>✅ Řádek $row: $evidCislo - $nazev (importováno: $importedCount, aktualizováno: $updatedCount)</span><br>";
            $stats['imported'] += $importedCount;
            $stats['updated'] += $updatedCount;
        } else {
            echo "<span>➖ Řádek $row: $evidCislo - $nazev (žádné změny)</span><br>";
            $stats['skipped']++;
        }
        
        // Flush výstup pro real-time zobrazení
        if ($row % 50 == 0) {
            flush();
            ob_flush();
        }
    }
    
    echo "</div>";
    
    // Souhrn
    echo "<hr><h2>📊 Souhrn importu</h2>";
    echo "<table>";
    echo "<tr><th>Položka</th><th>Počet</th></tr>";
    echo "<tr><td>Celkem zpracováno řádků</td><td>" . $stats['total'] . "</td></tr>";
    echo "<tr><td class='success'>🆕 Nově vytvořená měřidla</td><td>" . $stats['created'] . "</td></tr>";
    echo "<tr><td class='success'>✅ Nově importováno cen</td><td>" . $stats['imported'] . "</td></tr>";
    echo "<tr><td class='success'>🔄 Aktualizováno cen</td><td>" . $stats['updated'] . "</td></tr>";
    echo "<tr><td>➖ Přeskočeno (beze změn)</td><td>" . $stats['skipped'] . "</td></tr>";
    echo "<tr><td class='warning'>⚠️ Měřidel nenalezeno</td><td>" . $stats['not_found'] . "</td></tr>";
    echo "<tr><td class='error'>❌ Chyb</td><td>" . $stats['errors'] . "</td></tr>";
    echo "</table>";
    
    // Chyby
    if (count($errors) > 0) {
        echo "<hr><h3 class='error'>❌ Chyby při importu:</h3>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
    }
    
    echo "<hr><h2 class='success'>✅ Import dokončen!</h2>";
    echo "<p><strong style='color:red;'>⚠️ DŮLEŽITÉ: OKAMŽITĚ SMAŽTE tento soubor (import_consolidated_prices.php) a soubor TPCA_Consolidated.xlsx ze serveru!</strong></p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Kritická chyba: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
