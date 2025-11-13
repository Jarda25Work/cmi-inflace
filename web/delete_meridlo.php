<?php
// Nastavení kódování na začátku souboru
header('Content-Type: text/html; charset=UTF-8');

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Vyžaduje admin práva
requireAdmin();

// Kontrola ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$meridloId = (int)$_GET['id'];

// Získání detailu měřidla pro zobrazení
$meridlo = getMeridloDetail($meridloId);

if (!$meridlo) {
    header('Location: index.php?error=notfound');
    exit;
}

try {
    // Smazání měřidla
    $result = deleteMeridlo($meridloId);
    
    if ($result) {
        header('Location: index.php?deleted=1');
        exit;
    } else {
        header('Location: detail.php?id=' . $meridloId . '&error=delete');
        exit;
    }
} catch (Exception $e) {
    header('Location: detail.php?id=' . $meridloId . '&error=' . urlencode($e->getMessage()));
    exit;
}
