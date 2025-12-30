<?php
$config = require 'config/config.php';
try {
    $db = $config['db'];
    $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], $db['options']);
    
    $tables = ['plugin_dflow_flows', 'plugin_dflow_recon', 'plugin_dflow_vlans', 'plugin_dflow_baselines'];
    
    foreach ($tables as $table) {
        echo "\nStructure of $table:\n";
        try {
            $stmt = $pdo->query("DESCRIBE $table");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "  {$row['Field']} - {$row['Type']}\n";
            }
        } catch (Exception $e) {
            echo "  Error describing $table: " . $e->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "Connection error: " . $e->getMessage();
}
