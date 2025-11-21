<?php
/**
 * Skript pro vytvoření import tabulky cen za rok 2025
 * Vytvoří CSV soubor z Excel dat (ruční konverze)
 * 
 * POSTUP:
 * 1. Otevři 'TCA 2025-1Q2026fin.xlsx' v Excelu
 * 2. Vytvoř nový list
 * 3. Zkopíruj sloupec A (evidenční číslo) do nového listu sloupec A
 * 4. Zkopíruj sloupec T (cena 2025) do nového listu sloupec B
 * 5. Do sloupce C všude dej hodnotu 2025
 * 6. Odstraň řádky kde je sloupec B prázdný
 * 7. Ulož jako 'Import_ceny_2025.xlsx'
 * 8. Spusť tento skript
 */

echo "=== Návod na přípravu import souboru ===\n\n";
echo "1. Otevři soubor: zdroje/Inflace/TCA 2025-1Q2026fin.xlsx\n";
echo "2. Vytvoř nový list (pravý klik na záložku -> Insert)\n";
echo "3. Do A1 napiš: Evidenční číslo\n";
echo "4. Do B1 napiš: Cena 2025\n";
echo "5. Do C1 napiš: Rok\n";
echo "6. Zkopíruj celý sloupec A z původního listu (bez hlavičky) -> vl

ož do A2\n";
echo "7. Zkopíruj celý sloupec T z původního listu (bez hlavičky) -> vložž do B2\n";
echo "8. Do C2 napiš: 2025 a zkopíruj dolů (Ctrl+Shift+End)\n";
echo "9. Použij Filtr (Data -> Filter) a filtruj sloupec B aby nezobrazoval prázdné\n";
echo "10. Smaž všechny řádky kde je B prázdné\n";
echo "11. Ulož jako: zdroje/Inflace/Import_ceny_2025.xlsx\n";
echo "12. Nebo ulož jako CSV: zdroje/Inflace/Import_ceny_2025.csv\n\n";

echo "Po vytvoření souboru můžeš použít existující import skripty pro nahrání do databáze.\n";
echo "\nPokud vytvoříš CSV, struktura musí být:\n";
echo "Evidenční číslo;Cena 2025;Rok\n";
echo "0001;1500;2025\n";
echo "0002;2300;2025\n";
echo "...\n";
