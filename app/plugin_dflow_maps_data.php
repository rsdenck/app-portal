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
    $hosts = $pdo->prepare("SELECT ip_address as id, hostname as label FROM plugin_dflow_hosts WHERE vlan = ? LIMIT 500");
    $hosts->execute([$vlan]);
    $hosts = $hosts->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregação de fluxos para reduzir volume e incluir telemetria
    $flows = $pdo->prepare("SELECT 
        src_ip as source, 
        dst_ip as target, 
        SUM(bytes) as bytes, 
        SUM(packets) as packets, 
        AVG(rtt_ms) as rtt, 
        MAX(l7_proto) as l7_proto 
        FROM plugin_dflow_flows 
        WHERE vlan = ? 
        GROUP BY src_ip, dst_ip 
        ORDER BY bytes DESC 
        LIMIT 500");
    $flows->execute([$vlan]);
    $flows = $flows->fetchAll(PDO::FETCH_ASSOC);
} else {
    $hosts = $pdo->query("SELECT ip_address as id, hostname as label FROM plugin_dflow_hosts LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
    $flows = $pdo->query("SELECT 
        src_ip as source, 
        dst_ip as target, 
        SUM(bytes) as bytes, 
        SUM(packets) as packets, 
        AVG(rtt_ms) as rtt, 
        MAX(l7_proto) as l7_proto 
        FROM plugin_dflow_flows 
        GROUP BY src_ip, dst_ip 
        ORDER BY bytes DESC 
        LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
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
