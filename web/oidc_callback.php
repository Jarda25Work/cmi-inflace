<?php
// Nastavení kódování na začátku souboru
header('Content-Type: text/html; charset=UTF-8');

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/oidc.php';

// Inicializuj session PŘED použitím OIDC funkcí
initSession();

// Kontrola parametrů
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    header('Location: login.php?error=missing_params');
    exit;
}

try {
    // Přihlas uživatele pomocí OIDC
    $user = loginWithOIDC($_GET['code'], $_GET['state']);
    
    // Přesměruj na hlavní stránku
    header('Location: index.php?oidc_login=success');
    exit;
    
} catch (Exception $e) {
    // Loguj chybu a přesměruj na login
    error_log('OIDC Login Error: ' . $e->getMessage());
    header('Location: login.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>
