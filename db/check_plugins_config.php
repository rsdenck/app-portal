<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */
$pdo->exec("CREATE TABLE IF NOT EXISTS plugin_bgp_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) UNIQUE,
    data LONGTEXT,
    updated_at DATETIME
)");
echo "Table created or already exists.\n";
