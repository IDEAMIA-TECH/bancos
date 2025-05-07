<?php
require_once __DIR__ . '/config/database.php';

try {
    // Test connection
    $stmt = $pdo->query("SELECT VERSION()");
    $version = $stmt->fetchColumn();
    echo "Conexión exitosa a MySQL. Versión: " . $version . "\n";
    
    // Test database name
    $stmt = $pdo->query("SELECT DATABASE()");
    $dbname = $stmt->fetchColumn();
    echo "Base de datos actual: " . $dbname . "\n";
    
    // Test user privileges
    $stmt = $pdo->query("SHOW GRANTS");
    echo "Privilegios del usuario:\n";
    while ($row = $stmt->fetch()) {
        echo "- " . $row[0] . "\n";
    }
    
    // Test tables
    $stmt = $pdo->query("SHOW TABLES");
    echo "\nTablas en la base de datos:\n";
    while ($row = $stmt->fetch()) {
        echo "- " . $row[0] . "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 