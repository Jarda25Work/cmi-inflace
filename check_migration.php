<?php
require_once __DIR__ . '/web/includes/config.php';

try {
    $pdo = getDbConnection();
    
    // Zkontroluj, zda sloupec ignorovat_odchylku existuje
    $sql = "SHOW COLUMNS FROM ceny_meridel LIKE 'ignorovat_odchylku'";
    $stmt = $pdo->query($sql);
    $column = $stmt->fetch();
    
    if ($column) {
        echo "✓ Sloupec 'ignorovat_odchylku' existuje v tabulce 'ceny_meridel'\n";
        echo "Typ: " . $column['Type'] . "\n";
        echo "Default: " . $column['Default'] . "\n";
        echo "\nMigrace byla úspěšně provedena!\n";
    } else {
        echo "✗ Sloupec 'ignorovat_odchylku' NEEXISTUJE v tabulce 'ceny_meridel'\n";
        echo "\nJe třeba spustit migraci:\n";
        echo "ALTER TABLE ceny_meridel ADD COLUMN ignorovat_odchylku TINYINT(1) DEFAULT 0 AFTER je_manualni;\n";
    }
    
} catch (PDOException $e) {
    echo "Chyba: " . $e->getMessage() . "\n";
}
