<?php
/**
 * Skript pro doplnění cen za rok 2018
 * 
 * Logika:
 * - Pro každé měřidlo zkontroluje, zda má cenu pro rok 2018
 * - Pokud ne, najde poslední dostupnou cenu z roku < 2018
 * - Tu zkopíruje jako cenu pro rok 2018
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$cilovy_rok = 2018;
$inserted = 0;
$updated = 0;
$skipped = 0;

try {
    $pdo = getDbConnection();
    
    // Získej všechna aktivní měřidla
    $stmt = $pdo->query("SELECT id FROM meridla WHERE aktivni = 1");
    $meridla = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Zpracovávám " . count($meridla) . " měřidel...\n\n";
    
    foreach ($meridla as $meridlo_id) {
        // Zkontroluj, zda existuje cena pro cílový rok
        $stmt = $pdo->prepare("SELECT id, cena FROM ceny_meridel WHERE meridlo_id = ? AND rok = ?");
        $stmt->execute([$meridlo_id, $cilovy_rok]);
        $exist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Pokud existuje a má cenu, přeskoč
        if ($exist && $exist['cena'] !== null) {
            $skipped++;
            continue;
        }
        
        // Najdi poslední dostupnou cenu z dřívějších let
        $stmt = $pdo->prepare("
            SELECT rok, cena, je_manualni, poznamka 
            FROM ceny_meridel 
            WHERE meridlo_id = ? AND rok < ? AND cena IS NOT NULL 
            ORDER BY rok DESC 
            LIMIT 1
        ");
        $stmt->execute([$meridlo_id, $cilovy_rok]);
        $prev = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Pokud není předchozí cena, přeskoč
        if (!$prev) {
            continue;
        }
        
        // Rozhodni zda INSERT nebo UPDATE
        if (!$exist) {
            // Řádek neexistuje - INSERT
            $stmt = $pdo->prepare("
                INSERT INTO ceny_meridel (meridlo_id, rok, cena, je_manualni, poznamka)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $meridlo_id,
                $cilovy_rok,
                $prev['cena'],
                $prev['je_manualni'],
                "Doplněno skriptem z roku " . $prev['rok']
            ]);
            $inserted++;
            echo "Měřidlo ID $meridlo_id: VLOŽENA cena {$prev['cena']} Kč z roku {$prev['rok']}\n";
        } else {
            // Řádek existuje s NULL cenou - UPDATE
            $stmt = $pdo->prepare("
                UPDATE ceny_meridel 
                SET cena = ?, je_manualni = ?, 
                    poznamka = CONCAT(COALESCE(poznamka, ''), ' | Doplněno skriptem z roku ', ?)
                WHERE id = ?
            ");
            $stmt->execute([
                $prev['cena'],
                $prev['je_manualni'],
                $prev['rok'],
                $exist['id']
            ]);
            $updated++;
            echo "Měřidlo ID $meridlo_id: AKTUALIZOVÁNA cena {$prev['cena']} Kč z roku {$prev['rok']}\n";
        }
    }
    
    echo "\n=== HOTOVO ===\n";
    echo "Vloženo nových záznamů: $inserted\n";
    echo "Aktualizováno NULL záznamů: $updated\n";
    echo "Přeskočeno (už mělo cenu): $skipped\n";
    echo "Celkem zpracováno: " . count($meridla) . "\n\n";
    
    // Kontrolní dotaz
    $stmt = $pdo->query("SELECT COUNT(*) FROM ceny_meridel WHERE rok = $cilovy_rok AND cena IS NOT NULL");
    $count = $stmt->fetchColumn();
    echo "Celkový počet cen pro rok $cilovy_rok: $count\n";
    
} catch (Exception $e) {
    echo "CHYBA: " . $e->getMessage() . "\n";
    exit(1);
}
