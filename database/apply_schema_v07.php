<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

echo "=== Applying v0.7 Examination Papers Schema ===\n\n";

try {
    $db = Database::getInstance();
    
    $sql = file_get_contents(__DIR__ . '/schema_phase4_v07.sql');
    if ($sql === false) {
        throw new Exception("Could not read schema_phase4_v07.sql");
    }
    
    $db->exec($sql);
    
    echo "[+] Successfully applied schema_phase4_v07.sql\n";
    echo "[+] Created examination_papers table (if not exists)\n";
    
    // Check that table exists
    $check = $db->query("SHOW TABLES LIKE 'examination_papers'")->fetchColumn();
    if ($check) {
        echo "[✓] Table 'examination_papers' verified in database.\n";
        
        $cols = $db->query("DESCRIBE examination_papers")->fetchAll();
        echo "\nTable columns:\n";
        foreach ($cols as $c) {
            echo "  - {$c['Field']} ({$c['Type']}) {$c['Null']} Default: {$c['Default']}\n";
        }
    } else {
        throw new Exception("Table creation failed.");
    }
    
    echo "\n=== Schema applied successfully! ===\n";
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
