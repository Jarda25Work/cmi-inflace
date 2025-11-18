<?php
// Nastav bezpečnostní hlavičky
require_once __DIR__ . '/security.php';
setSecurityHeaders();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; echo e(APP_NAME); ?></title>
    
    <!-- Gov.cz Design System CSS -->
    <link rel="stylesheet" href="https://gov-design-system-next.netlify.app/dist/css/design-system.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="gov-header">
        <div class="gov-header__inner">
            <a href="index.php" class="gov-header__logo">
                <span class="gov-header__logo-text"><?php echo APP_NAME; ?></span>
            </a>
            <nav class="gov-header__nav">
                <ul class="gov-header__nav-list">
                    <li class="gov-header__nav-item">
                        <a href="index.php" class="gov-header__nav-link">Měřidla</a>
                    </li>
                    <?php if (isAdmin()): ?>
                    <li class="gov-header__nav-item">
                        <a href="inflace.php" class="gov-header__nav-link">Inflace</a>
                    </li>
                    <li class="gov-header__nav-item">
                        <a href="users.php" class="gov-header__nav-link">Uživatelé</a>
                    </li>
                    <li class="gov-header__nav-item">
                        <a href="nastaveni.php" class="gov-header__nav-link">Nastavení</a>
                    </li>
                    <?php endif; ?>
                    <li class="gov-header__nav-item">
                        <span class="gov-header__nav-link" style="cursor: default;">
                            <?php 
                            $user = getCurrentUser();
                            // Zobraz celé jméno místo username
                            $displayName = $user['full_name'] ?? $user['username'];
                            $roleText = $user['role'] === 'admin' ? 'Admin' : 'Read';
                            echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
                            echo ' <span style="opacity: 0.7;">(' . htmlspecialchars($roleText, ENT_QUOTES, 'UTF-8') . ')</span>';
                            ?>
                        </span>
                    </li>
                    <li class="gov-header__nav-item">
                        <a href="logout.php" class="gov-header__nav-link" onclick="return confirm('Opravdu se chcete odhlásit?');">Odhlásit</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="gov-container gov-container--wide">
