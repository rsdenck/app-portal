<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

header('Content-Type: application/json');

$mode = $_GET['mode'] ?? 'hosts'; // 'hosts' or 'topology'

if ($mode === 'topology') {
    // 1. Fetch Topology from plugin_dflow_topology
    $topology = $pdo->query("SELECT local_device_ip, local_port_index, remote_device_name, remote_port_id, protocol FROM plugin_dflow_topology")->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Fetch Devices/Interfaces to build a complete map
    $interfaces = $pdo->query("SELECT device_ip, if_index, name as if_name FROM plugin_dflow_interfaces")->fetchAll(PDO::FETCH_ASSOC);
    
    $nodes = [];
    $links = [];
    $nodesMap = [];

    foreach ($interfaces as $iface) {
        $devIp = $iface['device_ip'];
        if (!isset($nodesMap[$devIp])) {
            $nodesMap[$devIp] = ['id' => $devIp, 'label' => $devIp, 'group' => 'switch', 'val' => 20];
        }
    }

    foreach ($topology as $link) {
        $local = $link['local_device_ip'];
        $remote = $link['remote_device_name']; // For topology, we often only have the name of the remote peer
        
        if (!isset($nodesMap[$remote])) {
            $nodesMap[$remote] = ['id' => $remote, 'label' => $remote, 'group' => 'remote', 'val' => 15];
        }

        $links[] = [
            'source' => $local,
            'target' => $remote,
            'label' => $link['protocol'] . ": Port " . $link['local_port_index'] . " -> " . $link['remote_port_id'],
            'value' => 5
        ];
    }

    echo json_encode([
        'nodes' => array_values($nodesMap),
        'links' => $links
    ]);
    exit;
}

$vlan = isset($_GET['vlan']) ? (int)$_GET['vlan'] : 0;

if ($vlan > 0) {
    // Mode: VLAN Topology (Centralized view)
    $nodesMap = [];
    $links = [];

    // 1. Central VLAN Node
    $vlanKey = "vlan_$vlan";
    $nodesMap[$vlanKey] = [
        'id' => $vlanKey,
        'label' => "VLAN $vlan",
        'group' => 'vlan',
        'val' => 30,
        'color' => '#00ffff'
    ];

    // 2. Fetch Interfaces for this VLAN
    $stmtIf = $pdo->prepare("SELECT name, description, mac_address, device_ip FROM plugin_dflow_interfaces WHERE vlan = ?");
    $stmtIf->execute([$vlan]);
    $interfaces = $stmtIf->fetchAll(PDO::FETCH_ASSOC);

    foreach ($interfaces as $iface) {
        $ifKey = "if_" . md5($iface['device_ip'] . $iface['name']);
        if (!isset($nodesMap[$ifKey])) {
            $nodesMap[$ifKey] = [
                'id' => $ifKey,
                'label' => $iface['name'],
                'group' => 'interface',
                'val' => 20,
                'color' => '#ffa500',
                'description' => $iface['description']
            ];
            
            // Link Interface to VLAN
            $links[] = [
                'source' => $vlanKey,
                'target' => $ifKey,
                'label' => 'Membro',
                'thickness' => 2,
                'color' => '#333'
            ];
        }
    }

    // 3. Fetch Hosts for this VLAN
    $stmtHosts = $pdo->prepare("SELECT ip_address, hostname, mac_address, vendor FROM plugin_dflow_hosts WHERE vlan = ? LIMIT 200");
    $stmtHosts->execute([$vlan]);
    $hosts = $stmtHosts->fetchAll(PDO::FETCH_ASSOC);

    foreach ($hosts as $host) {
        $hKey = $host['ip_address'];
        if (!isset($nodesMap[$hKey])) {
            $nodesMap[$hKey] = [
                'id' => $hKey,
                'label' => $host['hostname'] ?: $host['ip_address'],
                'group' => 'host',
                'val' => 15,
                'color' => '#27c4a8',
                'mac' => $host['mac_address'],
                'vendor' => $host['vendor']
            ];

            // Link Host to VLAN
            $links[] = [
                'source' => $vlanKey,
                'target' => $hKey,
                'label' => 'Ativo',
                'thickness' => 1,
                'color' => '#27c4a8',
                'value' => 1 // Particle speed
            ];
        }
    }

    // 4. Fetch Flow links between these hosts (Traffic visibility)
    $stmtFlows = $pdo->prepare("SELECT src_ip, dst_ip, SUM(bytes) as total_bytes, MAX(app_proto) as proto 
                                FROM plugin_dflow_flows 
                                WHERE vlan = ? 
                                GROUP BY src_ip, dst_ip 
                                LIMIT 300");
    $stmtFlows->execute([$vlan]);
    $flows = $stmtFlows->fetchAll(PDO::FETCH_ASSOC);

    foreach ($flows as $flow) {
        if (isset($nodesMap[$flow['src_ip']]) && isset($nodesMap[$flow['dst_ip']])) {
            $links[] = [
                'source' => $flow['src_ip'],
                'target' => $flow['dst_ip'],
                'label' => ($flow['proto'] ?: 'Flow') . " (" . round($flow['total_bytes']/1024, 1) . " KB)",
                'thickness' => log($flow['total_bytes'] + 1),
                'color' => '#ffffff',
                'opacity' => 0.4,
                'value' => 2 // Particles for active traffic
            ];
        }
    }

    echo json_encode([
        'nodes' => array_values($nodesMap),
        'links' => $links
    ]);
    exit;
}

// Global mode or specific mode logic
if ($vlan == 0) {
    // Mode: Global Host Graph (Original logic)
    $hosts = $pdo->query("SELECT ip_address as id, hostname as label FROM plugin_dflow_hosts LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
    $flows = $pdo->query("SELECT 
        src_ip as source, 
        dst_ip as target, 
        SUM(bytes) as bytes, 
        SUM(pkts) as packets, 
        AVG(rtt_ms) as rtt, 
        MAX(app_proto) as l7_proto 
        FROM plugin_dflow_flows 
        GROUP BY src_ip, dst_ip 
        ORDER BY bytes DESC 
        LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
} else {
    // This part should not be reachable if vlan > 0 because of the exit at line 154
    // But we'll keep it as a fallback
    $hosts = [];
    $flows = [];
}

// Garantir que todos os IPs nos fluxos tenham um nó correspondente
$nodesMap = [];
foreach ($hosts as $h) {
    if (empty($h['id'])) continue;
    $nodesMap[$h['id']] = [
        'id' => $h['id'],
        'label' => !empty($h['label']) ? $h['label'] : $h['id'],
        'group' => 1,
        'val' => 10
    ];
}

foreach ($flows as $f) {
    if (empty($f['source']) || empty($f['target'])) continue;
    if (!isset($nodesMap[$f['source']])) {
        $nodesMap[$f['source']] = ['id' => $f['source'], 'label' => $f['source'], 'group' => 2, 'val' => 5];
    }
    if (!isset($nodesMap[$f['target']])) {
        $nodesMap[$f['target']] = ['id' => $f['target'], 'label' => $f['target'], 'group' => 2, 'val' => 5];
    }
}

$cleanLinks = [];
foreach ($flows as $f) {
    if (empty($f['source']) || empty($f['target'])) continue;
    
    // Log scale para o valor da espessura (bytes)
    $thickness = log((float)$f['bytes'] + 1) / 2;
    if ($thickness < 1) $thickness = 1;

    // Packets per second (simulado aqui como peso para partículas)
    $pps = log((float)$f['packets'] + 1);
    
    // RTT para opacidade
    $opacity = 1.0;
    if ($f['rtt'] > 0) {
        $opacity = max(0.2, 1.0 - ($f['rtt'] / 500.0)); // Diminui opacidade se RTT > 500ms
    }

    $cleanLinks[] = [
        'source' => $f['source'],
        'target' => $f['target'],
        'bytes' => (int)$f['bytes'],
        'packets' => (int)$f['packets'],
        'rtt' => (float)$f['rtt'],
        'l7_proto' => $f['l7_proto'] ?: 'Unknown',
        'thickness' => $thickness,
        'value' => $pps, // Usado para velocidade das partículas
        'opacity' => $opacity
    ];
}

echo json_encode([
    'nodes' => array_values($nodesMap),
    'links' => $cleanLinks
], JSON_PARTIAL_OUTPUT_ON_ERROR);
