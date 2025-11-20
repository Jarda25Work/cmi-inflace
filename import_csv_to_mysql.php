<?php
/**
 * Import dat z CSV do MySQL s korektnim UTF-8
 */

ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

echo "=============================================================\n";
echo "Import dat z CSV do MySQL\n";
echo "=============================================================\n\n";

$csvFile = 'c:\_Qsync\PrimaKurzy\cmi-inflace\meridla_export.csv';
$dbHost = 'localhost';
$dbName = 'cmi_inflace';
$dbUser = 'root';
$dbPass = '';

if (!file_exists($csvFile)) {
    die("Chyba: CSV soubor nenalezen: $csvFile\n");
}

echo "CSV soubor nalezen: $csvFile\n";

// Pripojeni k databazi s UTF-8
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
    echo "Pripojeno k databazi\n\n";
} catch (PDOException $e) {
    die("Chyba pripojeni k databazi: " . $e->getMessage() . "\n");
}

// Priprava SQL dotazu
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

// Nacteni CSV
$handle = fopen($csvFile, 'r');
if (!$handle) {
    die("Nelze otevrit CSV soubor\n");
}

// Preskocit hlavicku
fgets($handle);

$importedMeridla = 0;
$importedCeny = 0;
$errors = 0;

echo "Zacinam import...\n";
echo str_repeat("-", 60) . "\n";

while (($line = fgets($handle)) !== false) {
    $data = explode("\t", $line);
    
    if (count($data) < 25) {
        continue;
    }
    
    $evidCislo = trim($data[0]);
    if (empty($evidCislo)) {
        continue;
    }
    
    try {
        // Insert meridla
        $stmtMeridlo->execute([
            $evidCislo,
            !empty($data[1]) ? trim($data[1]) : null,
            !empty($data[2]) ? trim($data[2]) : null,
            !empty($data[3]) ? trim($data[3]) : null,
            !empty($data[4]) ? trim($data[4]) : null,
            !empty($data[5]) ? trim($data[5]) : null,
            !empty($data[6]) && $data[6] != '0000-00-00' ? trim($data[6]) : null,
            !empty($data[7]) ? trim($data[7]) : null,
            !empty($data[8]) ? trim($data[8]) : null,
            !empty($data[9]) && is_numeric($data[9]) ? (int)$data[9] : null,
            !empty($data[10]) ? trim($data[10]) : null,
            !empty($data[11]) ? trim($data[11]) : null,
            !empty($data[12]) ? trim($data[12]) : null,
            !empty($data[13]) ? trim($data[13]) : null,
            !empty($data[14]) ? trim($data[14]) : null
        ]);
        
        $meridloId = $pdo->lastInsertId();
        $importedMeridla++;
        
        $nazev = mb_substr($data[2], 0, 40);
        echo sprintf("[%03d] %s - %s\n", $importedMeridla, $evidCislo, $nazev);
        
        // Import cen pro vsechny roky (2016-2025)
        $roky = [2016, 2017, 2018, 2019, 2020, 2021, 2022, 2023, 2024, 2025];
        for ($i = 0; $i < 10; $i++) {
            $cenaIndex = 15 + $i;
            if (isset($data[$cenaIndex])) {
                $cena = trim($data[$cenaIndex]);
                if (!empty($cena) && is_numeric($cena) && $cena > 0) {
                    $stmtCena->execute([$meridloId, $roky[$i], $cena]);
                    $importedCeny++;
                }
            }
        }
        
    } catch (PDOException $e) {
        echo "CHYBA pri importu $evidCislo: " . $e->getMessage() . "\n";
        $errors++;
    }
}

fclose($handle);

echo str_repeat("-", 60) . "\n";
echo "\n=============================================================\n";
echo "VYSLEDEK IMPORTU\n";
echo "=============================================================\n";
echo "Importovano meridel: $importedMeridla\n";
echo "Importovano cen: $importedCeny\n";
if ($errors > 0) {
    echo "Chyb: $errors\n";
}

// Kontrola dat
echo "\n=============================================================\n";
echo "KONTROLA IMPORTOVANYCH DAT\n";
echo "=============================================================\n";

$stmt = $pdo->query("SELECT COUNT(*) as pocet FROM meridla");
$result = $stmt->fetch();
echo "Celkem meridel v databazi: " . $result['pocet'] . "\n";

$stmt = $pdo->query("SELECT rok, COUNT(*) as pocet FROM ceny_meridel GROUP BY rok ORDER BY rok");
echo "\nCeny podle roku:\n";
while ($row = $stmt->fetch()) {
    echo "  Rok {$row['rok']}: {$row['pocet']} cen\n";
}

$stmt = $pdo->query("SELECT evidencni_cislo, nazev_meridla, firma_kalibrujici FROM meridla LIMIT 5");
echo "\nUkazka prvnich 5 meridel:\n";
while ($row = $stmt->fetch()) {
    echo "  {$row['evidencni_cislo']} - {$row['nazev_meridla']} ({$row['firma_kalibrujici']})\n";
}

echo "\nImport dokoncen uspesne!\n";
echo "=============================================================\n";
?>
