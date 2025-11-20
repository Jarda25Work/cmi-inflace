<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Vyžaduje přihlášení
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$pdo = getDbConnection();

// Najdi všechny unikátní termíny z názvů měřidel a firem, které obsahují hledaný řetězec
$like = "%$query%";

// Rozděl názvy měřidel na slova a najdi relevantní varianty
$sql = "SELECT DISTINCT
            CASE
                WHEN nazev_meridla LIKE ? THEN nazev_meridla
                WHEN firma_kalibrujici LIKE ? THEN firma_kalibrujici
                WHEN evidencni_cislo LIKE ? THEN evidencni_cislo
            END as suggestion_text,
            CASE
                WHEN nazev_meridla LIKE ? THEN 'název'
                WHEN firma_kalibrujici LIKE ? THEN 'firma'
                ELSE 'evidenční číslo'
            END as source_type
        FROM meridla
        WHERE aktivni = 1
          AND (nazev_meridla LIKE ? OR firma_kalibrujici LIKE ? OR evidencni_cislo LIKE ?)
        ORDER BY
            CASE 
                WHEN suggestion_text LIKE ? THEN 1
                ELSE 2
            END,
            LENGTH(suggestion_text),
            suggestion_text
        LIMIT 15";

$likeStart = "$query%";

$stmt = $pdo->prepare($sql);
$stmt->execute([$like, $like, $like, $like, $like, $like, $like, $like, $likeStart]);

$results = [];
$seen = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $text = trim($row['suggestion_text']);
    if ($text && !isset($seen[strtolower($text)])) {
        $results[] = [
            'text' => $text,
            'type' => $row['source_type']
        ];
        $seen[strtolower($text)] = true;
    }
}

echo json_encode(array_slice($results, 0, 10));
