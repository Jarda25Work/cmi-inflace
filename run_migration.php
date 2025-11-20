<?php
require_once __DIR__ . '/web/includes/config.php';

try {
    $pdo = getDbConnection();
    $sql = file_get_contents(__DIR__ . '/sql/07_ignorovat_odchylku.sql');
    $pdo->exec($sql);
    echo "Migration 07_ignorovat_odchylku.sql completed successfully!\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
