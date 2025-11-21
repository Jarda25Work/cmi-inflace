<?php
// Skript pro kontrolu struktury Excel souboru
require_once __DIR__ . '/web/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = 'zdroje/Inflace/TCA 2025-1Q2026fin.xlsx';
$spreadsheet = IOFactory::load($file);
$sheet = $spreadsheet->getActiveSheet();

$highestRow = $sheet->getHighestRow();
$highestColumn = $sheet->getHighestColumn();

echo "Soubor: $file\n";
echo "Počet řádků: $highestRow\n";
echo "Nejvyšší sloupec: $highestColumn\n\n";

echo "Prvních 10 řádků (sloupce A a T):\n";
echo str_repeat("-", 80) . "\n";

for($i = 1; $i <= min(10, $highestRow); $i++) {
    $evidencni = $sheet->getCell('A' . $i)->getValue();
    $cena = $sheet->getCell('T' . $i)->getValue();
    echo sprintf("Řádek %3d: A=[%s] T=[%s]\n", $i, $evidencni, $cena);
}

echo "\nKontrolní dotaz - řádky s cenou:\n";
echo str_repeat("-", 80) . "\n";
$count = 0;
for($i = 1; $i <= min(50, $highestRow); $i++) {
    $evidencni = $sheet->getCell('A' . $i)->getValue();
    $cena = $sheet->getCell('T' . $i)->getValue();
    
    if (!empty($cena) && is_numeric($cena)) {
        echo sprintf("Řádek %3d: Evidenční=%s, Cena=%s\n", $i, $evidencni, $cena);
        $count++;
        if ($count >= 5) break;
    }
}
