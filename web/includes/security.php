<?php
/**
 * Bezpečnostní funkce
 */

/**
 * Generuje CSRF token
 */
function generateCsrfToken() {
    initSession();
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Ověří CSRF token
 */
function verifyCsrfToken($token) {
    initSession();
    
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Vyžaduje platný CSRF token pro POST požadavky
 */
function requireCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        
        if (!verifyCsrfToken($token)) {
            http_response_code(403);
            die('Neplatný CSRF token. Obnovte stránku a zkuste znovu.');
        }
    }
}

/**
 * Vytvoří HTML input s CSRF tokenem
 */
function csrfField() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Sanitizuje výstup pro bezpečné zobrazení v HTML
 */
function e($string) {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitizuje výstup pro použití v atributech
 */
function attr($string) {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitizuje URL
 */
function sanitizeUrl($url) {
    // Povolí pouze relativní URL nebo URL začínající https://
    if (empty($url)) {
        return '';
    }
    
    // Relativní URL
    if ($url[0] === '/' || strpos($url, './') === 0) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
    
    // Absolutní URL - musí být https
    $parsed = parse_url($url);
    if ($parsed && isset($parsed['scheme']) && $parsed['scheme'] === 'https') {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
    
    return '';
}

/**
 * Nastaví bezpečnostní hlavičky
 */
function setSecurityHeaders() {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // XSS Protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://gov-design-system-next.netlify.app; style-src 'self' 'unsafe-inline' https://gov-design-system-next.netlify.app; img-src 'self' data: https:; font-src 'self' https://gov-design-system-next.netlify.app;");
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // HTTPS Strict Transport Security (pouze pro HTTPS)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // Permissions Policy
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

/**
 * Validuje číslo
 */
function validateNumber($value, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return false;
    }
    
    $value = floatval($value);
    
    if ($min !== null && $value < $min) {
        return false;
    }
    
    if ($max !== null && $value > $max) {
        return false;
    }
    
    return true;
}

/**
 * Validuje rok
 */
function validateYear($year) {
    return validateNumber($year, 2000, 2100);
}

/**
 * Validuje email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Loguje bezpečnostní událost
 */
function logSecurityEvent($event, $details = '') {
    $pdo = getDbConnection();
    
    $sql = "INSERT INTO audit_log (
                user_id, 
                username, 
                akce, 
                tabulka, 
                zaznam_id, 
                popis, 
                ip_adresa
            ) VALUES (?, ?, 'SECURITY', 'system', NULL, ?, ?)";
    
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'anonymous';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $description = $event . ($details ? ': ' . $details : '');
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $username, $description, $ip]);
    } catch (PDOException $e) {
        error_log("Failed to log security event: " . $e->getMessage());
    }
}

/**
 * Rate limiting pro přihlášení
 */
function checkLoginRateLimit($username) {
    initSession();
    
    $key = 'login_attempts_' . $username;
    $maxAttempts = 5;
    $timeWindow = 900; // 15 minut
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'first_attempt' => time()
        ];
    }
    
    $attempts = $_SESSION[$key];
    
    // Reset pokud uplynulo 15 minut
    if (time() - $attempts['first_attempt'] > $timeWindow) {
        $_SESSION[$key] = [
            'count' => 0,
            'first_attempt' => time()
        ];
        return true;
    }
    
    // Zkontroluj limit
    if ($attempts['count'] >= $maxAttempts) {
        $remainingTime = $timeWindow - (time() - $attempts['first_attempt']);
        $minutes = ceil($remainingTime / 60);
        
        logSecurityEvent('LOGIN_RATE_LIMIT', "Username: $username");
        
        return "Příliš mnoho pokusů o přihlášení. Zkuste to znovu za $minutes minut.";
    }
    
    return true;
}

/**
 * Zaznamená neúspěšný pokus o přihlášení
 */
function recordFailedLogin($username) {
    initSession();
    
    $key = 'login_attempts_' . $username;
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'first_attempt' => time()
        ];
    }
    
    $_SESSION[$key]['count']++;
    
    logSecurityEvent('FAILED_LOGIN', "Username: $username");
}

/**
 * Vymaže záznamy o pokusech o přihlášení
 */
function clearLoginAttempts($username) {
    initSession();
    
    $key = 'login_attempts_' . $username;
    unset($_SESSION[$key]);
}
