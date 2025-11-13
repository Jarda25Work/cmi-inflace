<?php
/**
 * Konfigurace aplikace - EXAMPLE
 * Zkopíruj tento soubor na config.php a uprav hodnoty
 */

// Databáze
define('DB_HOST', 'localhost');
define('DB_NAME', 'cmi_inflace');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// OpenID Connect (Keycloak)
define('OIDC_ISSUER', 'https://login.cmi.cz/auth/realms/CMI/');
define('OIDC_CLIENT_ID', 'publibtest');
define('OIDC_CLIENT_SECRET', ''); // Pro public client není potřeba
define('OIDC_REDIRECT_URI', 'http://localhost:8000/oidc_callback.php');

// Aplikační nastavení
define('APP_NAME', 'CMI Systém kalibrace měřidel');
define('APP_VERSION', '1.0.0');

// Session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Změň na 1 pro HTTPS
ini_set('session.use_strict_mode', 1);

// Error reporting (vypni v produkci)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Časová zóna
date_default_timezone_set('Europe/Prague');
