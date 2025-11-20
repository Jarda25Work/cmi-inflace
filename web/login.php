<?php
// Nastavení kódování na začátku souboru
header('Content-Type: text/html; charset=UTF-8');

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/oidc.php';

// Inicializuj session PŘED použitím OIDC funkcí
initSession();

// Pokud je již přihlášen, přesměruj
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

// Zobrazení chyby z URL parametru
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

// Zprávy o timeoutu
if (isset($_GET['timeout'])) {
    if ($_GET['timeout'] === 'inactivity') {
        $error = 'Vaše relace byla ukončena kvůli nečinnosti (60 minut). Prosím přihlaste se znovu.';
    } elseif ($_GET['timeout'] === 'expired') {
        $error = 'Vaše relace vypršela (maximálně 10 hodin). Prosím přihlaste se znovu.';
    }
}

$pageTitle = 'Přihlášení - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Gov.cz Design System CSS -->
    <link rel="stylesheet" href="https://gov-design-system-next.netlify.app/dist/css/design-system.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0062AD 0%, #004a87 100%);
            padding: 2rem;
        }
        
        .login-box {
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 3rem;
            max-width: 450px;
            width: 100%;
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-logo h1 {
            color: #0062AD;
            font-size: 1.75rem;
            margin: 0;
            font-weight: 700;
        }
        
        .login-logo p {
            color: #6c757d;
            margin: 0.5rem 0 0 0;
        }
        
        .login-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 0.75rem 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        
        .login-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1.5rem;
            font-size: 0.875rem;
        }
        
        .login-info strong {
            display: block;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-logo">
                <h1><?php echo APP_NAME; ?></h1>
                <p>Systém pro správu kalibrovaných měřidel</p>
            </div>
            
            <?php if ($error): ?>
                <div class="login-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Přihlášení přes OpenID Connect -->
            <a href="<?php echo htmlspecialchars(getOIDCAuthorizationUrl()); ?>" 
               class="gov-button gov-button--primary" 
               style="width: 100%; display: block; text-align: center; text-decoration: none; padding: 0.75rem;">
                Přihlásit se přes CMI účet
            </a>
            
            <div style="text-align: center; margin-top: 1.5rem; color: #6c757d; font-size: 0.875rem;">
                <p>Pro přihlášení použijte svůj CMI účet z login.cmi.cz</p>
            </div>
        </div>
    </div>
</body>
</html>
