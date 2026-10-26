<?php
require_once __DIR__ . '/config.php';

use Lab\Database;

try {
    $db = (new Database())->connect();
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    
    if (!$sql) {
        throw new Exception("Could not read schema.sql");
    }

    $db->exec($sql);
    echo "Database initialized successfully!\n";
} catch (Exception $e) {
    echo "Initialization error: " . $e->getMessage() . "\n";
}