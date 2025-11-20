<?php
/**
 * Funkce pro práci s měřidly
 */

require_once __DIR__ . '/config.php';

/**
 * Získá seznam měřidel s aktuální cenou a stránkováním
 */
function getMeridla($page = 1, $search = '', $orderBy = 'evidencni_cislo', $orderDir = 'ASC', $filterOdchylky = 0, $itemsPerPage = ITEMS_PER_PAGE) {
    $pdo = getDbConnection();
    $offset = ($page - 1) * $itemsPerPage;
    
    // Validace sloupců pro řazení
    $allowedColumns = [
        'evidencni_cislo' => 'm.evidencni_cislo',
        'nazev_meridla' => 'm.nazev_meridla',
        'firma_kalibrujici' => 'm.firma_kalibrujici',
        'status' => 'm.status',
        'kategorie' => 'm.kategorie',
        'aktualni_cena' => 'aktualni_cena'
    ];
    
    // Validace směru řazení
    $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
    
    // Kontrola, zda je sloupec povolen
    if (!isset($allowedColumns[$orderBy])) {
        $orderBy = 'evidencni_cislo';
    }
    
    $orderColumn = $allowedColumns[$orderBy];
    
    $where = "WHERE m.aktivni = 1";
    $hasSearch = false;
    
    if ($search !== '' && $search !== null) {
        $where .= " AND (m.evidencni_cislo LIKE ? 
                    OR m.nazev_meridla LIKE ? 
                    OR m.firma_kalibrujici LIKE ?)";
        $hasSearch = true;
    }
    
    // Hlavní dotaz - pokud filtrujeme odchylky, musíme načíst všechna data
    if ($filterOdchylky == 1) {
        $sql = "SELECT 
                    m.id,
                    m.evidencni_cislo,
                    m.nazev_meridla,
                    m.firma_kalibrujici,
                    m.status,
                    m.kategorie,
                    fn_get_cena(m.id, ?) as aktualni_cena,
                    (SELECT cena FROM ceny_meridel c WHERE c.meridlo_id = m.id ORDER BY c.rok DESC LIMIT 1) as posledni_ulozena_cena,
                    (SELECT rok FROM ceny_meridel c WHERE c.meridlo_id = m.id ORDER BY c.rok DESC LIMIT 1) as rok_posledni_ceny
                FROM meridla m
                $where
                ORDER BY $orderColumn $orderDir";
    } else {
        $sql = "SELECT 
                    m.id,
                    m.evidencni_cislo,
                    m.nazev_meridla,
                    m.firma_kalibrujici,
                    m.status,
                    m.kategorie,
                    fn_get_cena(m.id, ?) as aktualni_cena,
                    (SELECT cena FROM ceny_meridel c WHERE c.meridlo_id = m.id ORDER BY c.rok DESC LIMIT 1) as posledni_ulozena_cena,
                    (SELECT rok FROM ceny_meridel c WHERE c.meridlo_id = m.id ORDER BY c.rok DESC LIMIT 1) as rok_posledni_ceny
                FROM meridla m
                $where
                ORDER BY $orderColumn $orderDir
                LIMIT ? OFFSET ?";
    }
    
    $stmt = $pdo->prepare($sql);
    
    $paramIndex = 1;
    
    // První parametr je current_year pro fn_get_cena
    $stmt->bindValue($paramIndex++, CURRENT_YEAR, PDO::PARAM_INT);
    
    // Pokud je search, přidej 3x search parametr
    if ($hasSearch) {
        $searchPattern = "%$search%";
        $stmt->bindValue($paramIndex++, $searchPattern);
        $stmt->bindValue($paramIndex++, $searchPattern);
        $stmt->bindValue($paramIndex++, $searchPattern);
    }
    
    // LIMIT a OFFSET pouze pokud nefiltrujeme odchylky
    if ($filterOdchylky != 1) {
        $stmt->bindValue($paramIndex++, $itemsPerPage, PDO::PARAM_INT);
        $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $allMeridla = $stmt->fetchAll();
    
    // Pokud je filter na odchylky zapnutý, filtruj výsledky
    if ($filterOdchylky == 1) {
        $meridla = array_filter($allMeridla, function($meridlo) {
            return maOdchylneCeny($meridlo['id']);
        });
        $meridla = array_values($meridla); // Přeindexuj pole
        
        // Pro stránkování potřebujeme správný výřez
        $total = count($meridla);
        $meridla = array_slice($meridla, $offset, $itemsPerPage);
    } else {
        $meridla = $allMeridla;
        
        // Počet celkem pro stránkování
        $countSql = "SELECT COUNT(*) as total FROM meridla m $where";
        $countStmt = $pdo->prepare($countSql);
        
        if ($hasSearch) {
            $searchPattern = "%$search%";
            $countStmt->bindValue(1, $searchPattern);
            $countStmt->bindValue(2, $searchPattern);
            $countStmt->bindValue(3, $searchPattern);
        }
        
        $countStmt->execute();
        $total = $countStmt->fetch()['total'];
    }
    
    return [
        'data' => $meridla,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $itemsPerPage),
        'itemsPerPage' => $itemsPerPage
    ];
}

/**
 * Získá detail jednoho měřidla
 */
function getMeridloDetail($id) {
    $pdo = getDbConnection();
    
    $sql = "SELECT * FROM meridla WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    
    return $stmt->fetch();
}

/**
 * Získá historii cen měřidla
 */
function getCenyMeridla($meridloId) {
    $pdo = getDbConnection();
    
    $sql = "SELECT 
                c.rok,
                c.cena,
                c.je_manualni,
                c.poznamka,
                c.ignorovat_odchylku,
                c.created_at,
                c.updated_at
            FROM ceny_meridel c
            WHERE c.meridlo_id = :meridlo_id
            ORDER BY c.rok DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['meridlo_id' => $meridloId]);
    
    return $stmt->fetchAll();
}

/**
 * Získá vypočítané ceny pro měřidlo přes roky
 */
function getVypocitaneCeny($meridloId, $odRoku, $doRoku) {
    $pdo = getDbConnection();
    $ceny = [];
    
    for ($rok = $odRoku; $rok <= $doRoku; $rok++) {
        $sql = "SELECT fn_get_cena(:meridlo_id, :rok) as cena";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['meridlo_id' => $meridloId, 'rok' => $rok]);
        $result = $stmt->fetch();
        
        $ceny[$rok] = $result['cena'];
    }
    
    return $ceny;
}

/**
 * Aktualizuje měřidlo
 */
function updateMeridlo($id, $data) {
    $pdo = getDbConnection();
    
    $sql = "UPDATE meridla SET
                nazev_meridla = :nazev_meridla,
                firma_kalibrujici = :firma_kalibrujici,
                status = :status,
                certifikat = :certifikat,
                posledni_kalibrace = :posledni_kalibrace,
                planovani_kalibrace = :planovani_kalibrace,
                frekvence_kalibrace = :frekvence_kalibrace,
                kategorie = :kategorie,
                dovolena_odchylka = :dovolena_odchylka,
                mer_rozsah = :mer_rozsah,
                presnost = :presnost,
                poznamka_cmi = :poznamka_cmi
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        'id' => $id,
        'nazev_meridla' => $data['nazev_meridla'],
        'firma_kalibrujici' => $data['firma_kalibrujici'],
        'status' => $data['status'],
        'certifikat' => $data['certifikat'] ?? null,
        'posledni_kalibrace' => $data['posledni_kalibrace'] ?? null,
        'planovani_kalibrace' => $data['planovani_kalibrace'] ?? null,
        'frekvence_kalibrace' => $data['frekvence_kalibrace'] ?? null,
        'kategorie' => $data['kategorie'] ?? null,
        'dovolena_odchylka' => $data['dovolena_odchylka'] ?? null,
        'mer_rozsah' => $data['mer_rozsah'] ?? null,
        'presnost' => $data['presnost'] ?? null,
        'poznamka_cmi' => $data['poznamka_cmi'] ?? null
    ]);
}

/**
 * Vytvoří nové měřidlo
 */
function createMeridlo($data) {
    $pdo = getDbConnection();
    
    // Zkontroluj, zda evidenční číslo již neexistuje
    $checkSql = "SELECT COUNT(*) FROM meridla WHERE evidencni_cislo = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$data['evidencni_cislo']]);
    
    if ($checkStmt->fetchColumn() > 0) {
        throw new Exception('Evidenční číslo ' . $data['evidencni_cislo'] . ' již existuje!');
    }
    
    $sql = "INSERT INTO meridla (
                evidencni_cislo,
                nazev_meridla,
                firma_kalibrujici,
                status,
                certifikat,
                posledni_kalibrace,
                planovani_kalibrace,
                frekvence_kalibrace,
                kategorie,
                dovolena_odchylka,
                mer_rozsah,
                presnost,
                poznamka_cmi
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    $result = $stmt->execute([
        $data['evidencni_cislo'],
        $data['nazev_meridla'],
        $data['firma_kalibrujici'] ?? null,
        $data['status'] ?? null,
        $data['certifikat'] ?? null,
        $data['posledni_kalibrace'] ?? null,
        $data['planovani_kalibrace'] ?? null,
        $data['frekvence_kalibrace'] ?? null,
        $data['kategorie'] ?? null,
        $data['dovolena_odchylka'] ?? null,
        $data['mer_rozsah'] ?? null,
        $data['presnost'] ?? null,
        $data['poznamka_cmi'] ?? null
    ]);
    
    if ($result) {
        return $pdo->lastInsertId();
    }
    
    return false;
}

/**
 * Smaže měřidlo a všechny jeho ceny
 */
function deleteMeridlo($id) {
    $pdo = getDbConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Nejdřív smazat všechny ceny
        $sql = "DELETE FROM ceny_meridel WHERE meridlo_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        
        // Pak smazat měřidlo
        $sql = "DELETE FROM meridla WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$id]);
        
        $pdo->commit();
        
        return $result;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Uloží nebo aktualizuje cenu měřidla
 */
function saveCena($meridloId, $rok, $cena, $jeManualni = true, $poznamka = null, $ignorovatOdchylku = false) {
    $pdo = getDbConnection();
    
    $sql = "INSERT INTO ceny_meridel (meridlo_id, rok, cena, je_manualni, poznamka, ignorovat_odchylku)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                cena = VALUES(cena),
                je_manualni = VALUES(je_manualni),
                poznamka = VALUES(poznamka),
                ignorovat_odchylku = VALUES(ignorovat_odchylku)";
    
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        $meridloId,
        $rok,
        $cena,
        $jeManualni ? 1 : 0,
        $poznamka,
        $ignorovatOdchylku ? 1 : 0
    ]);
}

/**
 * Smaže cenu měřidla
 */
function deleteCena($meridloId, $rok) {
    $pdo = getDbConnection();
    
    $sql = "DELETE FROM ceny_meridel WHERE meridlo_id = :meridlo_id AND rok = :rok";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute(['meridlo_id' => $meridloId, 'rok' => $rok]);
}

/**
 * Formátuje cenu
 */
function formatCena($cena) {
    if ($cena === null) {
        return '-';
    }
    return number_format(round((float)$cena), 0, ',', ' ') . ' Kč';
}

/**
 * Získá všechny roky s inflací
 */
function getInflaceRoky() {
    $pdo = getDbConnection();
    
    $sql = "SELECT rok, inflace_procenta, zdroj FROM inflace ORDER BY rok DESC";
    $stmt = $pdo->query($sql);
    
    return $stmt->fetchAll();
}

/**
 * Získá inflaci pro konkrétní rok
 */
function getInflace($rok) {
    $pdo = getDbConnection();
    
    $sql = "SELECT rok, inflace_procenta, zdroj FROM inflace WHERE rok = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$rok]);
    
    return $stmt->fetch();
}

/**
 * Uloží nebo aktualizuje inflaci
 */
function saveInflace($rok, $inflaceProcenta, $zdroj = null) {
    $pdo = getDbConnection();
    
    $sql = "INSERT INTO inflace (rok, inflace_procenta, zdroj)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                inflace_procenta = VALUES(inflace_procenta),
                zdroj = VALUES(zdroj)";
    
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([$rok, $inflaceProcenta, $zdroj]);
}

/**
 * Smaže inflaci
 */
function deleteInflace($rok) {
    $pdo = getDbConnection();
    
    $sql = "DELETE FROM inflace WHERE rok = ?";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([$rok]);
}

/**
 * Získá všechny uživatele
 */
function getAllUsers() {
    $pdo = getDbConnection();
    
    $sql = "SELECT id, username, email, full_name, role, active, created_at, last_login 
            FROM users 
            ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * Získá detail uživatele
 */
function getUserById($id) {
    $pdo = getDbConnection();
    
    $sql = "SELECT id, username, email, full_name, role, active, created_at, last_login 
            FROM users 
            WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    return $stmt->fetch();
}

/**
 * Aktualizuje práva uživatele
 */
function updateUserRole($userId, $role) {
    $pdo = getDbConnection();
    
    // Validace role
    if (!in_array($role, ['admin', 'read'])) {
        throw new Exception('Neplatná role');
    }
    
    $sql = "UPDATE users SET role = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([$role, $userId]);
}

/**
 * Aktivuje/deaktivuje uživatele
 */
function toggleUserActive($userId) {
    $pdo = getDbConnection();
    
    $sql = "UPDATE users SET active = NOT active WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([$userId]);
}

/**
 * Smaže uživatele
 */
function deleteUser($userId) {
    $pdo = getDbConnection();
    
    // Nesmí smazat sám sebe
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
        throw new Exception('Nemůžete smazat sám sebe!');
    }
    
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([$userId]);
}

/**
 * Získá konfigurační hodnotu
 */
function getKonfigurace($klic, $default = null) {
    $pdo = getDbConnection();
    
    $sql = "SELECT hodnota FROM konfigurace WHERE klic = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$klic]);
    
    $result = $stmt->fetch();
    return $result ? $result['hodnota'] : $default;
}

/**
 * Uloží konfigurační hodnotu
 */
function saveKonfigurace($klic, $hodnota, $popis = null) {
    $pdo = getDbConnection();
    
    $sql = "INSERT INTO konfigurace (klic, hodnota, popis)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                hodnota = VALUES(hodnota),
                popis = COALESCE(VALUES(popis), popis)";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$klic, $hodnota, $popis]);
}

/**
 * Vypočte teoretickou cenu pro daný rok podle inflace
 */
function vypocitejTeoretickouCenu($meridloId, $rok) {
    $pdo = getDbConnection();
    
    $sql = "SELECT fn_get_cena(?, ?) as cena";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$meridloId, $rok]);
    
    $result = $stmt->fetch();
    return $result ? (float)$result['cena'] : null;
}

/**
 * Zjistí, zda je ručně zadaná cena odchylná od vypočtené
 * @return array ['je_odchylna' => bool, 'vypocitana_cena' => float, 'odchylka_procent' => float]
 */
function zjistiOdchylkuCeny($meridloId, $rok, $rucniCena, $ignorovatOdchylku = false) {
    $pdo = getDbConnection();
    
    // Pokud je nastaveno ignorování odchylky, vždy vracíme false
    if ($ignorovatOdchylku) {
        return [
            'je_odchylna' => false,
            'vypocitana_cena' => null,
            'odchylka_procent' => 0,
            'ignorovano' => true
        ];
    }
    
    // Získat toleranci z konfigurace
    $tolerance = (float)getKonfigurace('cena_tolerance_procenta', 5);
    
    // Najít poslední uloženou cenu PŘED tímto rokem (nezahrnuje aktuální rok)
    $sql = "SELECT rok, cena FROM ceny_meridel 
            WHERE meridlo_id = ? AND rok < ? 
            ORDER BY rok DESC 
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$meridloId, $rok]);
    $predchoziCena = $stmt->fetch();
    
    if (!$predchoziCena) {
        // Není z čeho počítat inflaci
        return [
            'je_odchylna' => false,
            'vypocitana_cena' => null,
            'odchylka_procent' => 0
        ];
    }
    
    // Vypočítat teoretickou cenu z předchozí uložené ceny pomocí fn_vypocitat_cenu_s_inflaci
    $sql = "SELECT fn_vypocitat_cenu_s_inflaci(?, ?, ?) as vypocitana_cena";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$predchoziCena['cena'], $predchoziCena['rok'], $rok]);
    $result = $stmt->fetch();
    $vypocitanaCena = $result ? (float)$result['vypocitana_cena'] : null;
    
    if ($vypocitanaCena === null || $vypocitanaCena == 0) {
        return [
            'je_odchylna' => false,
            'vypocitana_cena' => null,
            'odchylka_procent' => 0
        ];
    }
    
    // Vypočítat procentuální odchylku
    $odchylka = abs($rucniCena - $vypocitanaCena);
    $odchylkaProcent = ($odchylka / $vypocitanaCena) * 100;
    
    return [
        'je_odchylna' => $odchylkaProcent > $tolerance,
        'vypocitana_cena' => $vypocitanaCena,
        'odchylka_procent' => $odchylkaProcent,
        'bazova_cena' => $predchoziCena['cena'],
        'bazovy_rok' => $predchoziCena['rok']
    ];
}

/**
 * Zkontroluje, zda měřidlo má nějakou odchylnou cenu v historii
 */
function maOdchylneCeny($meridloId) {
    $pdo = getDbConnection();
    
    // Získat všechny ručně zadané ceny včetně flagu pro ignorování
    $sql = "SELECT rok, cena, ignorovat_odchylku FROM ceny_meridel 
            WHERE meridlo_id = ? AND je_manualni = 1
            ORDER BY rok";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$meridloId]);
    $ceny = $stmt->fetchAll();
    
    foreach ($ceny as $cena) {
        $kontrola = zjistiOdchylkuCeny($meridloId, $cena['rok'], $cena['cena'], $cena['ignorovat_odchylku']);
        if ($kontrola['je_odchylna']) {
            return true;
        }
    }
    
    return false;
}
