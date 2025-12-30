<?php
require 'includes/bootstrap.php';

$sqls = [
    "CREATE TABLE IF NOT EXISTS plugin_dflow_ip_blocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cidr VARCHAR(50) NOT NULL UNIQUE,
        description VARCHAR(255),
        is_active TINYINT(1) DEFAULT 1,
        last_scan TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS plugin_dflow_scan_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        block_id INT,
        status ENUM('active', 'inactive') DEFAULT 'inactive',
        ports_open TEXT,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (ip_address),
        FOREIGN KEY (block_id) REFERENCES plugin_dflow_ip_blocks(id) ON DELETE CASCADE
    )",
    "INSERT IGNORE INTO plugin_dflow_ip_blocks (cidr, description) VALUES 
        ('186.250.184.0/22', 'Bloco de IPs Armazem Cloud 1'),
        ('143.0.120.0/22', 'Bloco de IPs Armazem Cloud 2'),
        ('132.255.220.0/22', 'Bloco de IPs Armazem Cloud 3')"
];

foreach ($sqls as $sql) {
    try {
        $pdo->exec($sql);
        echo "Executed: " . substr($sql, 0, 50) . "...\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
echo "Database schema updated for IP scanning.\n";
