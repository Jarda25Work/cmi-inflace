<?php
/**
 * Import NEW structure CSV to MySQL
 */

ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

echo "=============================================================\n";
echo "Import dat z NEW CSV struktury do MySQL\n";
echo "=============================================================\n\n";

$csvFile = 'c:\_Qsync\PrimaKurzy\cmi-inflace\meridla_export_new.csv';
$dbHost = 'localhost';
$dbName = 'cmi_inflace';
$dbUser = 'root';
$dbPass = '';

if (!file_exists($csvFile)) {
    die("Chyba: CSV soubor nenalezen: $csvFile\n");
}

// Pripojeni k databazi
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
    die("Chyba pripojeni: " . $e->getMessage() . "\n");
}

// Smazani starych dat
echo "Mazani starych dat...\n";
$pdo->exec("DELETE FROM ceny_meridel");
$pdo->exec("DELETE FROM meridla");
$pdo->exec("ALTER TABLE meridla AUTO_INCREMENT = 1");
echo "Stara data smazana\n\n";

// SQL dotazy
$insertMeridloSQL = "INSERT INTO meridla (
    evidencni_cislo, nazev_meridla, firma_kalibrujici,
    status, certifikat, posledni_kalibrace,
    planovani_kalibrace, frekvence_kalibrace, kategorie,
    dovolena_odchylka, mer_rozsah, presnost, poznamka_cmi
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmtMeridlo = $pdo->prepare($insertMeridloSQL);

$insertCenaSQL = "INSERT INTO ceny_meridel (meridlo_id, rok, cena, je_manualni, poznamka)
                  VALUES (?, 2025, ?, 1, 'Import z Excel - fiskalni rok 2025')";
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

echo "Zacinam import...\n";
echo str_repeat("-", 60) . "\n";

while (($line = fgets($handle)) !== false) {
    $data = explode("\t", $line);
    
    if (count($data) < 20) {
        continue;
    }
    
    $evidCislo = trim($data[0]);
    if (empty($evidCislo)) {
        continue;
    }
    
    try {
        // Zpracovani datumu - pokud je ve formatu "XI.24", ulozime jako text do poznamky
        $datumText = !empty($data[6]) ? trim($data[6]) : null;
        $planovaniText = !empty($data[7]) ? trim($data[7]) : null;
        
        // Frekvence
        $frekvence = null;
        if (!empty($data[9]) && is_numeric($data[9])) {
            $frekvence = (int)$data[9];
        }
        
        // Poznámka - spojíme různé informace
        $poznamkaCasti = [];
        if (!empty($data[4])) $poznamkaCasti[] = "Poznámky: " . trim($data[4]);
        if (!empty($data[11])) $poznamkaCasti[] = "Kalibrace: " . trim($data[11]);
        if (!empty($data[12])) $poznamkaCasti[] = "Úsek: " . trim($data[12]);
        if (!empty($data[13])) $poznamkaCasti[] = "Shop: " . trim($data[13]);
        if (!empty($data[14])) $poznamkaCasti[] = "WP: " . trim($data[14]);
        if (!empty($data[15])) $poznamkaCasti[] = "Vlastník: " . trim($data[15]);
        
        $poznamka = !empty($poznamkaCasti) ? implode("; ", $poznamkaCasti) : null;
        
        // Insert meridla
        $stmtMeridlo->execute([
            $evidCislo,
            !empty($data[1]) ? trim($data[1]) : null,
            !empty($data[2]) ? trim($data[2]) : null,
            !empty($data[3]) ? trim($data[3]) : null,
            !empty($data[5]) ? trim($data[5]) : null,
            $datumText,
            $planovaniText,
            $frekvence,
            !empty($data[10]) ? trim($data[10]) : null,
            !empty($data[16]) ? trim($data[16]) : null,
            !empty($data[17]) ? trim($data[17]) : null,
            !empty($data[18]) ? trim($data[18]) : null,
            $poznamka
        ]);
        
        $meridloId = $pdo->lastInsertId();
        $importedMeridla++;
        
        $nazev = mb_substr($data[1], 0, 40);
        echo sprintf("[%03d] %s - %s\n", $importedMeridla, $evidCislo, $nazev);
        
        // Import ceny pro rok 2025
        if (isset($data[19])) {
            $cena = trim($data[19]);
            if (!empty($cena) && is_numeric($cena) && $cena > 0) {
                $stmtCena->execute([$meridloId, $cena]);
                $importedCeny++;
            }
        }
        
    } catch (PDOException $e) {
        echo "CHYBA pri importu $evidCislo: " . $e->getMessage() . "\n";
    }
}

fclose($handle);

echo str_repeat("-", 60) . "\n";
echo "\n=============================================================\n";
echo "VYSLEDEK IMPORTU\n";
echo "=============================================================\n";
echo "Importovano meridel: $importedMeridla\n";
echo "Importovano cen: $importedCeny\n";

// Kontrola
$stmt = $pdo->query("SELECT COUNT(*) as pocet FROM meridla");
$result = $stmt->fetch();
echo "\nCelkem meridel v databazi: " . $result['pocet'] . "\n";

$stmt = $pdo->query("SELECT rok, COUNT(*) as pocet FROM ceny_meridel GROUP BY rok");
echo "\nCeny podle roku:\n";
while ($row = $stmt->fetch()) {
    echo "  Rok {$row['rok']}: {$row['pocet']} cen\n";
}

$stmt = $pdo->query("SELECT evidencni_cislo, nazev_meridla, firma_kalibrujici FROM meridla ORDER BY evidencni_cislo LIMIT 10");
echo "\nUkazka prvnich 10 meridel:\n";
while ($row = $stmt->fetch()) {
    $nazev = mb_substr($row['nazev_meridla'], 0, 35);
    echo "  {$row['evidencni_cislo']} - {$nazev} ({$row['firma_kalibrujici']})\n";
}

echo "\nImport dokoncen uspesne!\n";
echo "=============================================================\n";
?>
