<?php
/**
 * Sloučení všech TPCA Excel souborů do jednoho přehledného XLSX
 * 
 * Vytvoří soubor: TPCA_Consolidated.xlsx s cenami po letech
 * Formát: Evidenční číslo | 2012 | 2016 | 2018 | 2020 | 2021 | 2022 | 2023 | 2024 | 2025
 */

require_once __DIR__ . '/web/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

set_time_limit(300);
ini_set('memory_limit', '512M');

echo "Spouštím sloučení TPCA souborů...\n\n";

// Soubory k načtení
$sourceFiles = [
    2012 => 'zdroje/TPCA2012.xls',
    2016 => 'zdroje/TPCA2016.xls',
    2018 => 'zdroje/TPCA2018.xls',
    2020 => 'zdroje/TPCA2020 .xls',
    2021 => 'zdroje/TPCA2021.xls',
    2022 => 'zdroje/TPCA2022.xls',
    2023 => 'zdroje/TPCA2023.xls',
    2024 => 'zdroje/TPCA2024.xls',
    2025 => 'zdroje/TPCA2025.xls'
];

// Pole pro ukládání dat: [evidCislo => [rok => cena]]
$allData = [];

// Projdi všechny soubory
foreach ($sourceFiles as $year => $file) {
    $filePath = __DIR__ . '/' . $file;
    
    if (!file_exists($filePath)) {
        echo "⚠️  Přeskakuji rok $year - soubor nenalezen: $file\n";
        continue;
    }
    
    echo "📄 Načítám rok $year: $file\n";
    
    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        
        // Detekuj sloupce
        $headerRow = 1;
        $evidCisloCol = null;
        $cenaCol = null;
        
        // Hledej hlavičky
        $nazevCol = null;
        for ($col = 'A'; $col <= 'Z'; $col++) {
            $headerValue = strtolower(trim($sheet->getCell($col . $headerRow)->getValue()));
            
            if (strpos($headerValue, 'eviden') !== false || strpos($headerValue, 'číslo') !== false || strpos($headerValue, 'number') !== false) {
                $evidCisloCol = $col;
            }
            if (strpos($headerValue, 'cena') !== false || strpos($headerValue, 'kč') !== false || strpos($headerValue, 'price') !== false || strpos($headerValue, 'czk') !== false) {
                $cenaCol = $col;
            }
            if (strpos($headerValue, 'czech') !== false || strpos($headerValue, 'description') !== false || strpos($headerValue, 'název') !== false || strpos($headerValue, 'nazev') !== false) {
                $nazevCol = $col;
            }
            
            // Zkus i druhý řádek
            if (!$evidCisloCol || !$cenaCol || !$nazevCol) {
                $headerValue2 = strtolower(trim($sheet->getCell($col . '2')->getValue()));
                if (!$evidCisloCol && (strpos($headerValue2, 'eviden') !== false || strpos($headerValue2, 'číslo') !== false || strpos($headerValue2, 'number') !== false)) {
                    $evidCisloCol = $col;
                    $headerRow = 2;
                }
                if (!$cenaCol && (strpos($headerValue2, 'cena') !== false || strpos($headerValue2, 'kč') !== false || strpos($headerValue2, 'price') !== false || strpos($headerValue2, 'czk') !== false)) {
                    $cenaCol = $col;
                    $headerRow = 2;
                }
                if (!$nazevCol && (strpos($headerValue2, 'czech') !== false || strpos($headerValue2, 'description') !== false || strpos($headerValue2, 'název') !== false || strpos($headerValue2, 'nazev') !== false)) {
                    $nazevCol = $col;
                    $headerRow = 2;
                }
            }
        }
        
        // Výchozí pozice pokud detekce selhala
        if (!$evidCisloCol) $evidCisloCol = 'A';
        if (!$cenaCol) $cenaCol = 'G'; // Změněno z C na G kvůli TPCA2012
        if (!$nazevCol) $nazevCol = 'B'; // Czech description
        
        echo "   Sloupce: Evid.č.=$evidCisloCol, Název=$nazevCol, Cena=$cenaCol, Hlavička=řádek $headerRow\n";
        
        $rowCount = 0;
        
        // Načti data
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $evidCislo = trim($sheet->getCell($evidCisloCol . $row)->getValue());
            $nazev = trim($sheet->getCell($nazevCol . $row)->getValue());
            $cenaValue = $sheet->getCell($cenaCol . $row)->getValue();
            
            // Přeskoč prázdné
            if (empty($evidCislo) || empty($cenaValue)) {
                continue;
            }
            
            // Normalizuj evidenční číslo
            $evidCislo = preg_replace('/[^0-9]/', '', $evidCislo);
            if (empty($evidCislo)) {
                continue;
            }
            
            // Normalizuj cenu
            $cena = preg_replace('/[^0-9,.]/', '', $cenaValue);
            $cena = str_replace(',', '.', $cena);
            $cena = floatval($cena);
            
            if ($cena <= 0) {
                continue;
            }
            
            // Ulož do pole
            if (!isset($allData[$evidCislo])) {
                $allData[$evidCislo] = ['nazev' => $nazev, 'ceny' => []];
            }
            $allData[$evidCislo]['ceny'][$year] = $cena;
            // Aktualizuj název (použij poslední nalezený)
            if (!empty($nazev)) {
                $allData[$evidCislo]['nazev'] = $nazev;
            }
            $rowCount++;
        }
        
        echo "   ✅ Načteno záznamů: $rowCount\n\n";
        
    } catch (Exception $e) {
        echo "   ❌ Chyba: " . $e->getMessage() . "\n\n";
    }
}

// Seřaď podle evidenčního čísla
ksort($allData);

echo "📊 Celkem nalezeno unikátních měřidel: " . count($allData) . "\n\n";
echo "📝 Vytvářím výstupní soubor TPCA_Consolidated.xlsx...\n";

// Vytvoř nový spreadsheet
$outputSpreadsheet = new Spreadsheet();
$outputSheet = $outputSpreadsheet->getActiveSheet();
$outputSheet->setTitle('TPCA Ceny');

// Hlavičky
$years = [2012, 2016, 2018, 2020, 2021, 2022, 2023, 2024, 2025];
$headers = array_merge(['Evidenční číslo', 'Název měřidla'], $years);

$col = 'A';
foreach ($headers as $header) {
    $outputSheet->setCellValue($col . '1', $header);
    $col++;
}

// Stylování hlaviček
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '0062AD']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];
$outputSheet->getStyle('A1:K1')->applyFromArray($headerStyle);

// Zamrznutí hlavičky
$outputSheet->freezePane('A2');

// Data
$row = 2;
foreach ($allData as $evidCislo => $data) {
    // Evidenční číslo jako text
    $outputSheet->setCellValueExplicit('A' . $row, $evidCislo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    
    // Název měřidla
    $outputSheet->setCellValue('B' . $row, $data['nazev']);
    
    // Ceny po letech
    $colIndex = 2; // C = 2 (protože B je název)
    foreach ($years as $year) {
        $colLetter = chr(65 + $colIndex); // C, D, E, ...
        if (isset($data['ceny'][$year])) {
            $outputSheet->setCellValue($colLetter . $row, $data['ceny'][$year]);
        } else {
            $outputSheet->setCellValue($colLetter . $row, '');
        }
        $colIndex++;
    }
    
    $row++;
}

// Formátování cen
$outputSheet->getStyle('C2:K' . ($row - 1))
    ->getNumberFormat()
    ->setFormatCode('#,##0.00 "Kč"');

// Ohraničení všech buněk
$outputSheet->getStyle('A1:K' . ($row - 1))
    ->getBorders()
    ->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN);

// Auto-size sloupců
foreach (range('A', 'K') as $col) {
    $outputSheet->getColumnDimension($col)->setAutoSize(true);
}

// Uložení souboru
$outputFile = __DIR__ . '/TPCA_Consolidated.xlsx';
$writer = new Xlsx($outputSpreadsheet);
$writer->save($outputFile);

echo "✅ Soubor vytvořen: TPCA_Consolidated.xlsx\n";
echo "📍 Umístění: $outputFile\n\n";
echo "📋 Statistika:\n";
echo "   - Celkem měřidel: " . count($allData) . "\n";
echo "   - Roky: " . implode(', ', $years) . "\n";
echo "   - Chybí roky: 2013, 2014, 2015, 2017, 2019\n\n";
echo "✅ Hotovo! Nyní zkontroluj soubor TPCA_Consolidated.xlsx před importem.\n";
