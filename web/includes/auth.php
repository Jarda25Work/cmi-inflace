<?php
/**
 * Autentizační funkce
 */

/**
 * Spustí session pokud ještě není spuštěna
 */
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Bezpečnostní nastavení session
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Session timeouts
        ini_set('session.gc_maxlifetime', 36000); // 10 hodin (36000 sekund)
        session_set_cookie_params(36000); // Cookie vyprší za 10 hodin
        
        // Pokud je HTTPS, nastav secure flag
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        
        session_start();
        
        // Kontrola timeoutů
        checkSessionTimeout();
    }
}

/**
 * Kontroluje timeout session (nečinnost a maximální délka)
 */
function checkSessionTimeout() {
    $inactivityTimeout = 3600; // 60 minut nečinnosti
    $maxLifetime = 36000; // 10 hodin maximálně
    
    // Kontrola poslední aktivity
    if (isset($_SESSION['last_activity'])) {
        $inactiveTime = time() - $_SESSION['last_activity'];
        
        if ($inactiveTime > $inactivityTimeout) {
            // Odhlásit kvůli nečinnosti
            logSecurityEvent('SESSION_TIMEOUT_INACTIVITY', 
                "Username: " . ($_SESSION['username'] ?? 'unknown') . 
                ", Inactive for: " . round($inactiveTime / 60) . " minutes");
            logout();
            header('Location: login.php?timeout=inactivity');
            exit;
        }
    }
    
    // Kontrola celkové délky session
    if (isset($_SESSION['created_at'])) {
        $sessionAge = time() - $_SESSION['created_at'];
        
        if ($sessionAge > $maxLifetime) {
            // Odhlásit kvůli maximální délce
            logSecurityEvent('SESSION_TIMEOUT_MAX', 
                "Username: " . ($_SESSION['username'] ?? 'unknown') . 
                ", Session age: " . round($sessionAge / 3600, 1) . " hours");
            logout();
            header('Location: login.php?timeout=expired');
            exit;
        }
    } else {
        // První přístup - nastav čas vytvoření
        $_SESSION['created_at'] = time();
    }
    
    // Aktualizuj čas poslední aktivity
    $_SESSION['last_activity'] = time();
}

/**
 * Ověří, zda je uživatel přihlášen
 */
function isLoggedIn() {
    initSession();
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Získá aktuálně přihlášeného uživatele
 */
function getCurrentUser() {
    initSession();
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
        'full_name' => $_SESSION['full_name'] ?? $_SESSION['username']
    ];
}

/**
 * Ověří, zda má uživatel admin práva
 */
function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['role'] === 'admin';
}

/**
 * Ověří, zda má uživatel alespoň práva pro čtení
 */
function canRead() {
    return isLoggedIn();
}

/**
 * Ověří, zda může uživatel zapisovat
 */
function canWrite() {
    return isAdmin();
}

/**
 * Přihlásí uživatele
 */
function login($username, $password) {
    // Rate limiting
    require_once __DIR__ . '/security.php';
    $rateLimit = checkLoginRateLimit($username);
    if ($rateLimit !== true) {
        return $rateLimit; // Vrátí chybovou zprávu
    }
    
    $pdo = getDbConnection();
    
    $sql = "SELECT id, username, password_hash, role, full_name, active 
            FROM users 
            WHERE username = ? AND active = 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Pokud má uživatel prázdné heslo, nelze se přihlásit heslem (pouze OIDC)
    if ($user && empty($user['password_hash'])) {
        recordFailedLogin($username);
        return false;
    }
    
    if ($user && password_verify($password, $user['password_hash'])) {
        initSession();
        
        // Regeneruj session ID pro bezpečnost
        session_regenerate_id(true);
        
        // Ulož data do session - zajisti UTF-8
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = mb_convert_encoding($user['username'], 'UTF-8', 'UTF-8');
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = mb_convert_encoding($user['full_name'] ?? $user['username'], 'UTF-8', 'UTF-8');
        $_SESSION['auth_method'] = 'password';
        $_SESSION['created_at'] = time(); // Čas vytvoření session
        $_SESSION['last_activity'] = time(); // Čas poslední aktivity
        
        // Vymaž záznamy o neúspěšných pokusech
        clearLoginAttempts($username);
        
        // Aktualizuj last_login
        $updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([$user['id']]);
        
        // Loguj úspěšné přihlášení
        logSecurityEvent('SUCCESSFUL_LOGIN', "Username: $username");
        
        return true;
    }
    
    // Zaznamenaj neúspěšný pokus
    recordFailedLogin($username);
    
    return false;
}

/**
 * Odhlásí uživatele
 */
function logout() {
    initSession();
    
    // Vymaž všechny session proměnné
    $_SESSION = [];
    
    // Zničí session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Zničí session
    session_destroy();
}

/**
 * Vyžaduje přihlášení - přesměruje na login pokud není přihlášen
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Vyžaduje admin práva
 */
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        header('Location: index.php?error=access_denied');
        exit;
    }
}
?>
