<?php

/**
 * vCenter Alerts Engine
 * Analyzes collected vCenter data and creates tickets based on specific conditions.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/repository.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting vCenter Alerts Engine...\n";

// 1. Get local data
$localData = vcenter_get_local_data($pdo);
if (!$localData) {
    echo "No local data found. Run collector first.\n";
    exit(0);
}

$vms = $localData['vms'] ?? [];
$hosts = $localData['hosts'] ?? [];
$datastores = $localData['datastores'] ?? [];

// 2. Find Virtualização Category ID
$stmtCat = $pdo->prepare("SELECT id FROM ticket_categories WHERE slug = 'virtualizacao' LIMIT 1");
$stmtCat->execute();
$catRow = $stmtCat->fetch();
$categoryId = $catRow ? (int)$catRow['id'] : 1; // Fallback to 1

// 3. Find a default client to assign these tickets to (usually the first one or a system user)
// For this automation, we might need a specific policy on which client gets the ticket.
// For now, let's assume tickets are created for the client linked to the vCenter (if applicable)
// or just a general system-wide alert.
// Let's find the first 'cliente' user.
$stmtUser = $pdo->query("SELECT id FROM users WHERE role = 'cliente' LIMIT 1");
$defaultClientUserId = (int)$stmtUser->fetchColumn() ?: 1;

/**
 * Helper to check if a ticket for a specific resource and issue already exists (to avoid spam)
 */
function alert_exists(PDO $pdo, string $resourceName, string $issueKey): bool {
    $subjectPart = "[$resourceName] $issueKey";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE subject LIKE ? AND status_id NOT IN (SELECT id FROM ticket_statuses WHERE slug = 'encerrado')");
    $stmt->execute(["%$subjectPart%"]);
    return (int)$stmt->fetchColumn() > 0;
}

// --- RULES ENGINE ---

// 1. VM Rules
foreach ($vms as $vm) {
    $vmName = $vm['name'] ?? 'Unknown';
    
    // Rule: VM without IP (can indicate tools not running or freeze)
    if (($vm['power_state'] ?? '') === 'POWERED_ON' && empty($vm['ip_address'])) {
        $issue = "VM sem endereço IP detectado";
        if (!alert_exists($pdo, $vmName, $issue)) {
            $desc = "A VM $vmName está ligada mas não reportou endereço IP. Pode indicar que o VMware Tools não está em execução ou a VM está travada.\n\n";
            $desc .= "Detalhes:\n";
            $desc .= "- Host: " . ($vm['server_label'] ?? 'Unknown') . "\n";
            $desc .= "- OS: " . ($vm['guest_os'] ?? 'Unknown') . "\n";
            
            ticket_create($pdo, $defaultClientUserId, $categoryId, "[$vmName] $issue", $desc, [
                'resource' => $vmName,
                'type' => 'VM',
                'issue' => $issue
            ], null, 'medium');
            echo "   - Ticket created for VM $vmName: $issue\n";
        }
    }
}

// 2. Host Rules
foreach ($hosts as $host) {
    $hostName = $host['name'] ?? 'Unknown';

    // Rule: Host Disconnected or Not Connected
    if (($host['connection_state'] ?? '') !== 'CONNECTED') {
        $issue = "Host ESXi Desconectado ou com falha de conexão";
        if (!alert_exists($pdo, $hostName, $issue)) {
            $desc = "O host $hostName apresenta estado de conexão: " . ($host['connection_state'] ?? 'UNKNOWN') . ".\n";
            $desc .= "Isso pode indicar perda de ping, falha de rede ou queda do host.\n\n";
            $desc .= "Detalhes:\n";
            $desc .= "- Servidor: " . ($host['server_label'] ?? 'Unknown') . "\n";
            $desc .= "- Power: " . ($host['power_state'] ?? 'Unknown') . "\n";

            ticket_create($pdo, $defaultClientUserId, $categoryId, "[$hostName] $issue", $desc, [
                'resource' => $hostName,
                'type' => 'Host',
                'issue' => $issue
            ], null, 'high');
            echo "   - Ticket created for Host $hostName: $issue\n";
        }
    }

    // Rule: Maintenance Mode
    if ($host['in_maintenance'] ?? false) {
        $issue = "Host em modo de manutenção (Maintenance Mode)";
        if (!alert_exists($pdo, $hostName, $issue)) {
            $desc = "O host $hostName foi detectado em modo de manutenção.\n\n";
            ticket_create($pdo, $defaultClientUserId, $categoryId, "[$hostName] $issue", $desc, [
                'resource' => $hostName,
                'type' => 'Host',
                'issue' => $issue
            ], null, 'low');
            echo "   - Ticket created for Host $hostName: $issue\n";
        }
    }
}

// 3. Datastore Rules
foreach ($datastores as $ds) {
    $dsName = $ds['name'] ?? 'Unknown';
    $cap = $ds['capacity'] ?? 0;
    $free = $ds['free_space'] ?? 0;
    $pctFree = $cap > 0 ? ($free / $cap) * 100 : 100;

    // Rule: Space < 5% (Disaster)
    if ($pctFree <= 5) {
        $issue = "Datastore com espaço crítico (Abaixo de 5%) - DESASTRE";
        if (!alert_exists($pdo, $dsName, $issue)) {
            $desc = "O datastore $dsName está com apenas " . number_format($pctFree, 1) . "% livre.\n";
            $desc .= "Risco iminente de travamento de todas as VMs neste storage.\n\n";
            $desc .= "Capacidade: " . number_format($cap / (1024**3), 2) . " GB\n";
            $desc .= "Livre: " . number_format($free / (1024**3), 2) . " GB\n";

            ticket_create($pdo, $defaultClientUserId, $categoryId, "[$dsName] $issue", $desc, [
                'resource' => $dsName,
                'type' => 'Datastore',
                'issue' => $issue
            ], null, 'high');
            echo "   - Ticket created for Datastore $dsName: $issue\n";
        }
    }
    // Rule: Space < 10% (High)
    elseif ($pctFree <= 10) {
        $issue = "Datastore com pouco espaço (Abaixo de 10%) - ALTO";
        if (!alert_exists($pdo, $dsName, $issue)) {
            $desc = "O datastore $dsName está com apenas " . number_format($pctFree, 1) . "% livre.\n";
            ticket_create($pdo, $defaultClientUserId, $categoryId, "[$dsName] $issue", $desc, [
                'resource' => $dsName,
                'type' => 'Datastore',
                'issue' => $issue
            ], null, 'medium');
            echo "   - Ticket created for Datastore $dsName: $issue\n";
        }
    }

    // Rule: Shared by too many hosts (e.g., > 32 hosts might indicate a problem or non-best practice)
    if (($ds['shared_hosts_count'] ?? 0) > 32) {
        $issue = "Datastore compartilhado por hosts demais (> 32)";
        if (!alert_exists($pdo, $dsName, $issue)) {
            $desc = "O datastore $dsName está compartilhado por " . $ds['shared_hosts_count'] . " hosts.\n";
            $desc .= "Isso pode causar problemas de contenção de SCSI locks e performance.\n";

            ticket_create($pdo, $defaultClientUserId, $categoryId, "[$dsName] $issue", $desc, [
                'resource' => $dsName,
                'type' => 'Datastore',
                'issue' => $issue
            ], null, 'low');
            echo "   - Ticket created for Datastore $dsName: $issue\n";
        }
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Alerts Engine finished.\n";
