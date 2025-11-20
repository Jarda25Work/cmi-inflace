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
$filterOdchylky = isset($_GET['odchylky']) ? (int)$_GET['odchylky'] : 0;

// Získání všech dat (bez stránkování)
$result = getMeridla(1, $search, $orderBy, $orderDir, $filterOdchylky, 999999);
$meridla = $result['data'];

// Nastavení hlaviček pro Excel export (Tab-delimited text)
$filename = 'meridla_export_' . date('Y-m-d_His') . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Output stream
$output = fopen('php://output', 'w');

// UTF-8 BOM pro správné zobrazení českých znaků
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Funkce pro escapování speciálních znaků v Tab-delimited formátu
function escapeTabDelimited($value) {
    // Nahraď Tab a nový řádek mezerami
    $value = str_replace(["\t", "\r\n", "\r", "\n"], ' ', $value);
    // Pokud obsahuje uvozovky, zdvojnásob je a obal do uvozovek
    if (strpos($value, '"') !== false) {
        $value = '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

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

fwrite($output, implode("\t", $headers) . "\n");

// Data
foreach ($meridla as $meridlo) {
    // Získání detailních dat
    $detail = getMeridloDetail($meridlo['id']);
    
    $row = [
        escapeTabDelimited($detail['evidencni_cislo']),
        escapeTabDelimited($detail['nazev_meridla']),
        escapeTabDelimited($detail['firma_kalibrujici'] ?? ''),
        escapeTabDelimited($detail['status'] ?? ''),
        escapeTabDelimited($detail['kategorie'] ?? ''),
        escapeTabDelimited(formatCena($meridlo['aktualni_cena'])),
        escapeTabDelimited($meridlo['rok_posledni_ceny'] ?? 'N/A'),
        escapeTabDelimited($detail['certifikat'] ?? ''),
        escapeTabDelimited($detail['posledni_kalibrace'] ?? ''),
        escapeTabDelimited($detail['planovani_kalibrace'] ?? ''),
        escapeTabDelimited($detail['frekvence_kalibrace'] ?? ''),
        escapeTabDelimited($detail['mer_rozsah'] ?? ''),
        escapeTabDelimited($detail['presnost'] ?? ''),
        escapeTabDelimited($detail['dovolena_odchylka'] ?? ''),
        escapeTabDelimited($detail['poznamka_cmi'] ?? '')
    ];
    
    fwrite($output, implode("\t", $row) . "\n");
}

fclose($output);
exit;
