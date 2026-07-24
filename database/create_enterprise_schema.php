<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $phases = ['1', '2', '3'];
    
    foreach ($phases as $phase) {
        echo "Executing schema_phase$phase.sql...\n";
        $sql = file_get_contents(__DIR__ . "/schema_phase$phase.sql");
        $db->exec($sql);
        echo "Done with phase $phase!\n";
    }
    
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "Enterprise schema created successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
