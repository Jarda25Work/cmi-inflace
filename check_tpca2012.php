<?php
require 'web/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = IOFactory::load('zdroje/TPCA2012.xls');
$sheet = $spreadsheet->getActiveSheet();

echo "Prvních 15 řádků TPCA2012.xls:\n\n";

for ($row = 1; $row <= 15; $row++) {
    echo "Řádek $row: ";
    for ($col = 'A'; $col <= 'H'; $col++) {
        $value = $sheet->getCell($col . $row)->getValue();
        if (!empty($value)) {
            echo "$col=[$value] ";
        }
    }
    echo "\n";
}

echo "\n\nCelkový počet řádků: " . $sheet->getHighestRow() . "\n";
