<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

echo "=== Applying v0.7.1 Migration (Secure Document Management) ===\n\n";

try {
    $db = Database::getInstance();

    $sql = file_get_contents(__DIR__ . '/migration_v0_7_1.sql');
    if ($sql === false) {
        throw new Exception("Could not read migration_v0_7_1.sql");
    }

    $db->exec($sql);
    echo "[+] Successfully applied migration_v0_7_1.sql\n";
    echo "[+] Created paper_versions and paper_files tables (if not exists)\n\n";

    foreach (['paper_versions', 'paper_files'] as $table) {
        $check = $db->query("SHOW TABLES LIKE '$table'")->fetchColumn();
        if (!$check) {
            throw new Exception("Table creation failed for $table");
        }
        echo "[✓] Table '$table' verified in database.\n";
        $cols = $db->query("DESCRIBE $table")->fetchAll();
        echo "  Columns:\n";
        foreach ($cols as $c) {
            echo "    - {$c['Field']} ({$c['Type']}) Null: {$c['Null']} Default: {$c['Default']}\n";
        }
        echo "\n";
    }

    echo "=== v0.7.1 Migration applied successfully! ===\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
