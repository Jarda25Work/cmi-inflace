<?php
/**
 * PHP Import script pro načtení dat z TCA inflace.xlsx
 * Správně zpracovává české znaky pomocí UTF-8
 */

// Nastavení kódování
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Načtení knihovny PhpSpreadsheet (pokud je nainstalována přes Composer)
// Pokud není, použijeme COM objekt Excel

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=============================================================\n";
echo "Import dat z TCA inflace.xlsx do MySQL\n";
echo "=============================================================\n\n";

// Konfigurace
$excelFile = 'c:\_Qsync\PrimaKurzy\cmi-inflace\zdroje\web\TCA inflace.xlsx';
$dbHost = 'localhost';
$dbName = 'cmi_inflace';
$dbUser = 'root';
$dbPass = '';

if (!file_exists($excelFile)) {
    die("Chyba: Soubor nenalezen: $excelFile\n");
}

echo "Soubor nalezen: $excelFile\n";

// Připojení k databázi s UTF-8
try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_czech_ci"
        ]
    );
    echo "✓ Připojeno k databázi\n\n";
} catch (PDOException $e) {
    die("Chyba připojení k databázi: " . $e->getMessage() . "\n");
}

// Načtení dat pomocí COM objektu Excel
try {
    echo "Načítám Excel soubor pomocí COM...\n";
    
    $excel = new COM("Excel.Application") or die("Nelze spustit Excel");
    $excel->Visible = false;
    $excel->DisplayAlerts = false;
    
    $workbook = $excel->Workbooks->Open($excelFile);
    $worksheet = $workbook->Worksheets(1);
    
    // Zjištění posledního řádku
    $lastRow = $worksheet->UsedRange->Rows->Count;
    $lastCol = $worksheet->UsedRange->Columns->Count;
    
    echo "Počet řádků: $lastRow\n";
    echo "Počet sloupců: $lastCol\n\n";
    
    // Najdeme první datový řádek (kde začínají evidenční čísla)
    $firstDataRow = 0;
    for ($row = 1; $row <= 20; $row++) {
        $cell = $worksheet->Cells($row, 1)->Value;
        if ($cell && preg_match('/^\d{4}$/', $cell)) {
            $firstDataRow = $row;
            echo "První datový řádek: $firstDataRow (evidenční číslo: $cell)\n\n";
            break;
        }
    }
    
    if (!$firstDataRow) {
        die("Chyba: Nenalezen první datový řádek s evidenčním číslem\n");
    }
    
    // Mapování sloupců (indexy od 1)
    $colEvid = 1;      // Evidenční číslo
    $colStanovena = 2; // Stanovená měřidla
    $colNazev = 3;     // NÁZEV MĚŘIDLA
    $colFirma = 4;     // Firma kalibrující
    $colStatus = 5;    // Status
    $colCertifikat = 6; // Certifikát
    $colDatum = 7;     // Datum poslední kalibrace
    $colPosledniKal = 8; // Poslední kalibrace provedl
    $colPlanovani = 9; // Plánování kalibrace
    $colFrekvence = 10; // Frekvence
    $colKategorie = 11; // Kategorie
    $colOdchylka = 12; // Dovolená odchylka
    $colRozsah = 13;   // Měřící rozsah
    $colPresnost = 14; // PŘESNOST
    $colPoznamka = 16; // Poznámka ČMI
    
    // Sloupce s cenami podle roků
    $cenoveSloupce = [
        2016 => 17,
        2017 => 18,
        2018 => 19,
        2019 => 20,
        2020 => 21,
        2021 => 22,
        2022 => 23,
        2023 => 24,
        2024 => 25,
        2025 => 28  // Sloupec "2025" (final)
    ];
    
    // Příprava SQL dotazů
    $insertMeridloSQL = "INSERT INTO meridla (
        evidencni_cislo, stanovena_meridla_etalony, nazev_meridla,
        firma_kalibrujici, status, certifikat,
        datum_posledni_kalibrace, posledni_kalibrace, planovani_kalibrace,
        frekvence_kalibrace, kategorie, dovolena_odchylka,
        mer_rozsah, presnost, poznamka_cmi
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmtMeridlo = $pdo->prepare($insertMeridloSQL);
    
    $insertCenaSQL = "INSERT INTO ceny_meridel (meridlo_id, rok, cena, je_manualni, poznamka)
                      VALUES (?, ?, ?, 1, 'Import z Excel')";
    $stmtCena = $pdo->prepare($insertCenaSQL);
    
    // Čítače
    $importedMeridla = 0;
    $importedCeny = 0;
    $skipped = 0;
    
    echo "Začínám import měřidel...\n";
    echo str_repeat("-", 60) . "\n";
    
    // Procházení řádků
    for ($row = $firstDataRow; $row <= $lastRow; $row++) {
        // Načtení evidenčního čísla
        $evidCislo = $worksheet->Cells($row, $colEvid)->Value;
        
        // Přeskočit prázdné řádky
        if (empty($evidCislo)) {
            $skipped++;
            continue;
        }
        
        $evidCislo = trim($evidCislo);
        
        // Načtení dalších údajů s korektním UTF-8
        $stanovena = $worksheet->Cells($row, $colStanovena)->Value;
        $nazev = $worksheet->Cells($row, $colNazev)->Value;
        $firma = $worksheet->Cells($row, $colFirma)->Value;
        $status = $worksheet->Cells($row, $colStatus)->Value;
        $certifikat = $worksheet->Cells($row, $colCertifikat)->Value;
        $posledniKal = $worksheet->Cells($row, $colPosledniKal)->Value;
        $planovani = $worksheet->Cells($row, $colPlanovani)->Value;
        $kategorie = $worksheet->Cells($row, $colKategorie)->Value;
        $odchylka = $worksheet->Cells($row, $colOdchylka)->Value;
        $rozsah = $worksheet->Cells($row, $colRozsah)->Value;
        $presnost = $worksheet->Cells($row, $colPresnost)->Value;
        $poznamka = $worksheet->Cells($row, $colPoznamka)->Value;
        
        // Datum
        $datumValue = $worksheet->Cells($row, $colDatum)->Value;
        $datum = null;
        if ($datumValue && is_numeric($datumValue)) {
            // Excel datum (počet dní od 1.1.1900)
            $unixDate = ($datumValue - 25569) * 86400;
            $datum = date('Y-m-d', $unixDate);
        }
        
        // Frekvence (měla by být číslo)
        $frekvenceValue = $worksheet->Cells($row, $colFrekvence)->Value;
        $frekvence = null;
        if ($frekvenceValue && is_numeric($frekvenceValue)) {
            $frekvence = (int)$frekvenceValue;
        }
        
        try {
            // Insert měřidla
            $stmtMeridlo->execute([
                $evidCislo,
                $stanovena ? trim($stanovena) : null,
                $nazev ? trim($nazev) : null,
                $firma ? trim($firma) : null,
                $status ? trim($status) : null,
                $certifikat ? trim($certifikat) : null,
                $datum,
                $posledniKal ? trim($posledniKal) : null,
                $planovani ? trim($planovani) : null,
                $frekvence,
                $kategorie ? trim($kategorie) : null,
                $odchylka ? trim($odchylka) : null,
                $rozsah ? trim($rozsah) : null,
                $presnost ? trim($presnost) : null,
                $poznamka ? trim($poznamka) : null
            ]);
            
            $meridloId = $pdo->lastInsertId();
            $importedMeridla++;
            
            echo "✓ [$importedMeridla] $evidCislo - " . mb_substr($nazev, 0, 40) . "\n";
            
            // Import cen pro všechny roky
            foreach ($cenoveSloupce as $rok => $colIndex) {
                $cenaValue = $worksheet->Cells($row, $colIndex)->Value;
                
                if ($cenaValue && is_numeric($cenaValue) && $cenaValue > 0) {
                    $stmtCena->execute([$meridloId, $rok, $cenaValue]);
                    $importedCeny++;
                }
            }
            
        } catch (PDOException $e) {
            echo "✗ Chyba při importu $evidCislo: " . $e->getMessage() . "\n";
        }
    }
    
    // Zavření Excel
    $workbook->Close(false);
    $excel->Quit();
    unset($worksheet, $workbook, $excel);
    
    echo str_repeat("-", 60) . "\n";
    echo "\n=============================================================\n";
    echo "VÝSLEDEK IMPORTU\n";
    echo "=============================================================\n";
    echo "✓ Importováno měřidel: $importedMeridla\n";
    echo "✓ Importováno cen: $importedCeny\n";
    echo "⚠ Přeskočeno prázdných řádků: $skipped\n";
    
    // Kontrola dat
    echo "\n=============================================================\n";
    echo "KONTROLA IMPORTOVANÝCH DAT\n";
    echo "=============================================================\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM meridla");
    $result = $stmt->fetch();
    echo "Celkem měřidel v databázi: " . $result['pocet'] . "\n";
    
    $stmt = $pdo->query("SELECT rok, COUNT(*) as pocet FROM ceny_meridel GROUP BY rok ORDER BY rok");
    echo "\nCeny podle roků:\n";
    while ($row = $stmt->fetch()) {
        echo "  Rok {$row['rok']}: {$row['pocet']} cen\n";
    }
    
    $stmt = $pdo->query("SELECT evidencni_cislo, nazev_meridla, firma_kalibrujici FROM meridla LIMIT 5");
    echo "\nUkázka prvních 5 měřidel:\n";
    while ($row = $stmt->fetch()) {
        echo "  {$row['evidencni_cislo']} - {$row['nazev_meridla']} ({$row['firma_kalibrujici']})\n";
    }
    
    echo "\n✓ Import dokončen úspěšně!\n";
    echo "=============================================================\n";
    
} catch (Exception $e) {
    echo "\n✗ Chyba: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
