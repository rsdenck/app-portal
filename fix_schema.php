<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=portal', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("ALTER TABLE plugin_dflow_interfaces MODIFY COLUMN name VARCHAR(255)");
    $pdo->exec("ALTER TABLE plugin_dflow_interfaces MODIFY COLUMN description TEXT");
    echo "Schema updated.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
