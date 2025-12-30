<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

echo "Setting up DFLOW IP Scanning Blocks...\n";

// 1. Ensure table exists
$sql = "CREATE TABLE IF NOT EXISTS plugin_dflow_ip_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cidr VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    last_scan TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$pdo->exec($sql);

$sqlScan = "CREATE TABLE IF NOT EXISTS plugin_dflow_ip_scanning (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    block_id INT,
    status ENUM('active', 'inactive', 'unknown') DEFAULT 'unknown',
    last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    open_ports TEXT,
    FOREIGN KEY (block_id) REFERENCES plugin_dflow_ip_blocks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$pdo->exec($sqlScan);

// 2. Insert blocks
$blocks = [
    ['cidr' => '186.250.184.0/22', 'description' => 'User Block 1'],
    ['cidr' => '143.0.120.0/22', 'description' => 'User Block 2'],
    ['cidr' => '132.255.220.0/22', 'description' => 'User Block 3']
];

foreach ($blocks as $block) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO plugin_dflow_ip_blocks (cidr, description) VALUES (?, ?)");
    $stmt->execute([$block['cidr'], $block['description']]);
    echo "Added/Checked block: {$block['cidr']}\n";
}

echo "Done.\n";
