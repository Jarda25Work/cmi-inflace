<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

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

// Vytvoření nového spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Měřidla');

// Hlavičky sloupců
$headers = [
    'Evidenční číslo',
    'Název měřidla',
    'Firma kalibrující',
    'Status',
    'Kategorie',
    'Aktuální cena (' . CURRENT_YEAR . ')',
    'Rok poslední ceny',
    'Certifikát',
    'Poslední kalibrace',
    'Plánovaná kalibrace',
    'Frekvence kalibrace',
    'Měřící rozsah',
    'Přesnost',
    'Dovolená odchylka',
    'Poznámka CMI'
];

// Zápis hlaviček
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
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
    ]
];
$sheet->getStyle('A1:O1')->applyFromArray($headerStyle);

// Zápis dat
$row = 2;
foreach ($meridla as $meridlo) {
    $detail = getMeridloDetail($meridlo['id']);
    
    $sheet->setCellValue('A' . $row, $detail['evidencni_cislo']);
    $sheet->setCellValue('B' . $row, $detail['nazev_meridla']);
    $sheet->setCellValue('C' . $row, $detail['firma_kalibrujici'] ?? '');
    $sheet->setCellValue('D' . $row, $detail['status'] ?? '');
    $sheet->setCellValue('E' . $row, $detail['kategorie'] ?? '');
    $sheet->setCellValue('F' . $row, formatCena($meridlo['aktualni_cena']));
    $sheet->setCellValue('G' . $row, $meridlo['rok_posledni_ceny'] ?? 'N/A');
    $sheet->setCellValue('H' . $row, $detail['certifikat'] ?? '');
    $sheet->setCellValue('I' . $row, $detail['posledni_kalibrace'] ?? '');
    $sheet->setCellValue('J' . $row, $detail['planovani_kalibrace'] ?? '');
    $sheet->setCellValue('K' . $row, $detail['frekvence_kalibrace'] ?? '');
    $sheet->setCellValue('L' . $row, $detail['mer_rozsah'] ?? '');
    $sheet->setCellValue('M' . $row, $detail['presnost'] ?? '');
    $sheet->setCellValue('N' . $row, $detail['dovolena_odchylka'] ?? '');
    $sheet->setCellValue('O' . $row, $detail['poznamka_cmi'] ?? '');
    
    $row++;
}

// Auto-size sloupců
foreach (range('A', 'O') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Zamrznutí prvního řádku
$sheet->freezePane('A2');

// Nastavení hlaviček pro stažení
$filename = 'meridla_export_' . date('Y-m-d_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Zápis do výstupu
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
