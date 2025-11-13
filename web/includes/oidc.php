<?php
/**
 * OpenID Connect helper funkce
 */

/**
 * Získá discovery dokument z OIDC providera
 */
function getOIDCConfiguration() {
    $discoveryUrl = rtrim(OIDC_ISSUER, '/') . '/.well-known/openid-configuration';
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($discoveryUrl, false, $context);
    
    if ($response === false) {
        throw new Exception('Nepodařilo se načíst OIDC konfiguraci');
    }
    
    return json_decode($response, true);
}

/**
 * Vygeneruje authorization URL pro přihlášení
 */
function getOIDCAuthorizationUrl($state = null) {
    $config = getOIDCConfiguration();
    
    if ($state === null) {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oidc_state'] = $state;
    }
    
    $params = [
        'client_id' => OIDC_CLIENT_ID,
        'redirect_uri' => OIDC_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => OIDC_SCOPES,
        'state' => $state
    ];
    
    return $config['authorization_endpoint'] . '?' . http_build_query($params);
}

/**
 * Vymění authorization code za access token
 */
function exchangeCodeForToken($code) {
    $config = getOIDCConfiguration();
    
    $data = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => OIDC_REDIRECT_URI,
        'client_id' => OIDC_CLIENT_ID,
        'client_secret' => OIDC_CLIENT_SECRET
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($config['token_endpoint'], false, $context);
    
    if ($response === false) {
        throw new Exception('Nepodařilo se získat access token');
    }
    
    return json_decode($response, true);
}

/**
 * Dekóduje JWT token (bez verifikace - pouze pro získání dat)
 */
function decodeJWT($jwt) {
    $parts = explode('.', $jwt);
    
    if (count($parts) !== 3) {
        throw new Exception('Neplatný JWT token');
    }
    
    $payload = base64_decode(strtr($parts[1], '-_', '+/'));
    return json_decode($payload, true);
}

/**
 * Získá uživatelské informace z ID tokenu
 */
function getUserInfoFromToken($tokenResponse) {
    if (!isset($tokenResponse['id_token'])) {
        throw new Exception('ID token nebyl vrácen');
    }
    
    return decodeJWT($tokenResponse['id_token']);
}

/**
 * Získá nebo vytvoří uživatele v databázi na základě OIDC údajů
 */
function getOrCreateUserFromOIDC($userInfo) {
    $pdo = getDbConnection();
    
    // Použij preferred_username nebo email jako username
    $username = $userInfo['preferred_username'] ?? $userInfo['email'] ?? $userInfo['sub'];
    $email = $userInfo['email'] ?? '';
    $fullName = $userInfo['name'] ?? $username;
    
    // Najdi uživatele podle username (to je primární identifikátor)
    $sql = "SELECT * FROM users WHERE username = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Uživatel existuje - zkontroluj jestli se změnil email
        $updateFields = [];
        $updateParams = [];
        
        // Vždy aktualizuj full_name
        $updateFields[] = "full_name = ?";
        $updateParams[] = $fullName;
        
        // Pokud se email změnil, aktualizuj ho
        if ($user['email'] !== $email) {
            $updateFields[] = "email = ?";
            $updateParams[] = $email;
        }
        
        // Vždy aktualizuj last_login
        $updateFields[] = "last_login = NOW()";
        
        // Proveď update
        $sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $updateParams[] = $user['id'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateParams);
        
        return $user;
    } else {
        // Vytvoř nového uživatele s read-only právy (prázdné heslo = pouze OIDC login)
        $sql = "INSERT INTO users (username, email, full_name, role, password_hash, last_login)
                VALUES (?, ?, ?, 'read', '', NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $email, $fullName]);
        
        // Načti nově vytvořeného uživatele
        $userId = $pdo->lastInsertId();
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetch();
    }
}

/**
 * Přihlásí uživatele pomocí OIDC
 */
function loginWithOIDC($code, $state) {
    // Ověř state
    if (!isset($_SESSION['oidc_state']) || $_SESSION['oidc_state'] !== $state) {
        throw new Exception('Neplatný state parametr (možný CSRF útok)');
    }
    
    unset($_SESSION['oidc_state']);
    
    // Vyměň code za token
    $tokenResponse = exchangeCodeForToken($code);
    
    // Získej uživatelské údaje
    $userInfo = getUserInfoFromToken($tokenResponse);
    
    // Získej nebo vytvoř uživatele v DB
    $user = getOrCreateUserFromOIDC($userInfo);
    
    // Ulož do session
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['auth_method'] = 'oidc';
    
    return $user;
}
?>
