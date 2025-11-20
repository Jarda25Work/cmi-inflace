<?php
// Generování hashů hesel pro uživatele
$password = 'cmi2025';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Hash pro heslo '$password':\n";
echo $hash . "\n\n";

// SQL pro vložení uživatelů
echo "SQL příkazy:\n";
echo "INSERT INTO users (username, password_hash, role, full_name) VALUES\n";
echo "('cmiadmin', '$hash', 'admin', 'CMI Administrátor'),\n";
echo "('cmiread', '$hash', 'read', 'CMI Pouze čtení');\n";
?>
