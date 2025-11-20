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
$filterOdchylky = isset($_GET['odchylky']) ? (int)$_GET['odchylky'] : 0; // 2 = pouze bez ceny, 3 = přesné slovo
$exactMatch = ($filterOdchylky === 3); // přesné hledání když je vybrána hodnota 3

// Získání všech dat (bez stránkování)
$result = getMeridla(1, $search, $orderBy, $orderDir, $filterOdchylky, 999999, $exactMatch);
$meridla = $result['data'];

// Nastavení hlaviček pro CSV export
$filename = 'meridla_export_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Output stream
$output = fopen('php://output', 'w');

// UTF-8 BOM pro správné zobrazení českých znaků v Excelu
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

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

fputcsv($output, $headers, ';');

// Data
foreach ($meridla as $meridlo) {
    // Získání detailních dat
    $detail = getMeridloDetail($meridlo['id']);
    
    $row = [
        $detail['evidencni_cislo'],
        $detail['nazev_meridla'],
        $detail['firma_kalibrujici'] ?? '',
        $detail['status'] ?? '',
        $detail['kategorie'] ?? '',
        formatCena($meridlo['aktualni_cena']),
        $meridlo['rok_posledni_ceny'] ?? 'N/A',
        $detail['certifikat'] ?? '',
        $detail['posledni_kalibrace'] ?? '',
        $detail['planovani_kalibrace'] ?? '',
        $detail['frekvence_kalibrace'] ?? '',
        $detail['mer_rozsah'] ?? '',
        $detail['presnost'] ?? '',
        $detail['dovolena_odchylka'] ?? '',
        $detail['poznamka_cmi'] ?? ''
    ];
    
    fputcsv($output, $row, ';');
}

fclose($output);
exit;
