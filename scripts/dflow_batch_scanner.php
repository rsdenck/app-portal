<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

// Set time limit to unlimited for CLI
if (php_sapi_name() === 'cli') {
    set_time_limit(0);
} else {
    set_time_limit(600);
}

// Ensure tables exist
$pdo->exec("CREATE TABLE IF NOT EXISTS plugin_dflow_ip_scanning (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    block_id INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'inactive',
    open_ports TEXT,
    last_checked DATETIME,
    UNIQUE KEY ip_block (ip_address, block_id)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS plugin_dflow_ip_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cidr VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    last_scan DATETIME
) ENGINE=InnoDB");

echo "Starting DFLOW Batch IP Scanner...\n";

function cidrToRange($cidr) {
    $range = array();
    $parts = explode('/', $cidr);
    if (count($parts) != 2) return [];
    
    $ip = $parts[0];
    $netmask = $parts[1];
    
    $ip_long = ip2long($ip);
    $mask = ~((1 << (32 - $netmask)) - 1);
    
    $start = $ip_long & $mask;
    $end = $ip_long | (~$mask & 0xFFFFFFFF);
    
    for ($i = $start; $i <= $end; $i++) {
        $range[] = long2ip($i);
    }
    return $range;
}

function checkIp($ip) {
    // Common ports to check for "activeness"
    $ports = [80, 443, 22, 161, 445, 3389, 8080, 21, 23, 25, 110, 143];
    $activePorts = [];
    
    foreach ($ports as $port) {
        // Very short timeout for scanning
        $connection = @fsockopen($ip, $port, $errno, $errstr, 0.02);
        if (is_resource($connection)) {
            $activePorts[] = $port;
            fclose($connection);
            // If any port is open, we consider it active
            break; 
        }
    }
    return $activePorts;
}

// Fetch active blocks
$blocks = $pdo->query("SELECT * FROM plugin_dflow_ip_blocks WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

foreach ($blocks as $block) {
    echo "Scanning block: {$block['cidr']}...\n";
    $ips = cidrToRange($block['cidr']);
    $total = count($ips);
    
    echo "  Total IPs in block: $total\n";

    // To avoid blocking the web request too long, we might want to only scan a portion 
    // or use a more efficient way. But for this task, we'll scan the whole block.
    // In a production environment, this should be a background worker.
    
    $batchSize = 50;
    for ($i = 0; $i < $total; $i += $batchSize) {
        $batch = array_slice($ips, $i, $batchSize);
        foreach ($batch as $ip) {
            $activePorts = checkIp($ip);
            $status = !empty($activePorts) ? 'active' : 'inactive';
            
            // MySQL syntax
            $stmt = $pdo->prepare("INSERT INTO plugin_dflow_ip_scanning (ip_address, block_id, status, open_ports, last_checked) 
                VALUES (?, ?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE status = VALUES(status), open_ports = VALUES(open_ports), last_checked = NOW()");
            $stmt->execute([$ip, $block['id'], $status, implode(',', $activePorts)]);
            
            if ($status === 'active') {
                echo "    [+] Active: $ip (Ports: " . implode(',', $activePorts) . ")\n";
                // Add/Update hosts table with vlan context from block
                $stmtHost = $pdo->prepare("INSERT INTO plugin_dflow_hosts (ip_address, last_seen, is_active, vlan) 
                    VALUES (?, NOW(), 1, ?) 
                    ON DUPLICATE KEY UPDATE last_seen = NOW(), is_active = 1, vlan = VALUES(vlan)");
                $stmtHost->execute([$ip, $block['vlan_id']]);
            }
        }
        // Give some feedback
        if (($i % 200) == 0 && $i > 0) {
            echo "  Processed $i/$total IPs...\n";
        }
    }
    
    // Update block last scan time
    $pdo->prepare("UPDATE plugin_dflow_ip_blocks SET last_scan = NOW() WHERE id = ?")->execute([$block['id']]);
    echo "  Block {$block['cidr']} completed.\n";
}

echo "Batch IP Scanner finished.\n";
