<?php
declare(strict_types=1);

/**
 * Creates a DFlow alert and optionally opens a ticket in the 'redes' category.
 */
function dflow_create_alert(PDO $pdo, string $type, string $severity, string $subject, string $description, ?string $targetIp = null, ?string $sourceIp = null, int $vlan = 0): int
{
    $stmt = $pdo->prepare("
        INSERT INTO plugin_dflow_alerts (type, severity, subject, description, target_ip, source_ip, vlan, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$type, $severity, $subject, $description, $targetIp, $sourceIp, $vlan]);
    $alertId = (int)$pdo->lastInsertId();

    // Check if we should open a ticket (for medium/high/critical alerts)
    if (in_array($severity, ['high', 'critical', 'medium'])) {
        dflow_check_and_create_ticket($pdo, $alertId);
    }

    return $alertId;
}

/**
 * Checks an alert and creates a ticket if necessary.
 */
function dflow_check_and_create_ticket(PDO $pdo, int $alertId): ?int
{
    $stmt = $pdo->prepare("SELECT * FROM plugin_dflow_alerts WHERE id = ?");
    $stmt->execute([$alertId]);
    $alert = $stmt->fetch();

    if (!$alert || $alert['ticket_id']) {
        return null;
    }

    // Get Redes Category ID (we know it's 2, but let's be safe)
    $stmtCat = $pdo->prepare("SELECT id FROM ticket_categories WHERE slug = 'redes' LIMIT 1");
    $stmtCat->execute();
    $categoryId = $stmtCat->fetchColumn();

    if (!$categoryId) return null;

    // Get a default client user (for system alerts)
    $stmtClient = $pdo->prepare("SELECT id FROM users WHERE role = 'cliente' LIMIT 1");
    $stmtClient->execute();
    $clientId = $stmtClient->fetchColumn();

    if (!$clientId) return null;

    $subject = "[DFLOW ALERT] " . $alert['subject'];
    $description = "Alert Type: " . $alert['type'] . "\n" .
                   "Severity: " . $alert['severity'] . "\n" .
                   "Source IP: " . ($alert['source_ip'] ?: 'N/A') . "\n" .
                   "Target IP: " . ($alert['target_ip'] ?: 'N/A') . "\n\n" .
                   "Details:\n" . $alert['description'];

    $extra = [
        'dflow_alert_id' => $alertId,
        'type' => $alert['type'],
        'severity' => $alert['severity']
    ];

    // Create ticket using repository function
    require_once __DIR__ . '/repository.php';
    $ticketId = ticket_create($pdo, (int)$clientId, (int)$categoryId, $subject, $description, $extra, null, $alert['severity']);

    // Update alert with ticket ID
    $stmtUpdate = $pdo->prepare("UPDATE plugin_dflow_alerts SET ticket_id = ? WHERE id = ?");
    $stmtUpdate->execute([$ticketId, $alertId]);

    return $ticketId;
}

/**
 * Scans recent flows for anomalies and generates alerts.
 */
function dflow_process_anomalies(PDO $pdo): void
{
    // 1. Check for high throughput from unknown IPs (Potential DDoS)
    $stmtDdos = $pdo->prepare("
        SELECT src_ip, SUM(bps) as total_bps, COUNT(*) as flows 
        FROM plugin_dflow_flows 
        WHERE timestamp > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        GROUP BY src_ip 
        HAVING total_bps > 100000000 
    ");
    $stmtDdos->execute();
    while ($row = $stmtDdos->fetch()) {
        dflow_create_alert(
            $pdo, 
            'ddos_detection', 
            'critical', 
            "Potential DDoS detected from " . $row['src_ip'], 
            "High traffic volume detected: " . format_rate($row['total_bps']) . " with " . $row['flows'] . " flows.",
            null,
            $row['src_ip']
        );
    }

    // 2. Check for suspicious L7 protocols (e.g., BitTorrent in corporate net)
    $suspiciousProtos = ['BitTorrent', 'Tor', 'Psiphon'];
    $placeholders = implode(',', array_fill(0, count($suspiciousProtos), '?'));
    $stmtProto = $pdo->prepare("
        SELECT src_ip, dst_ip, l7_proto, bytes 
        FROM plugin_dflow_flows 
        WHERE l7_proto IN ($placeholders) AND timestamp > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        LIMIT 10
    ");
    $stmtProto->execute($suspiciousProtos);
    while ($row = $stmtProto->fetch()) {
        dflow_create_alert(
            $pdo,
            'suspicious_protocol',
            'medium',
            "Suspicious protocol " . $row['l7_proto'] . " detected",
            "Host " . $row['src_ip'] . " is using " . $row['l7_proto'] . " to communicate with " . $row['dst_ip'],
            $row['dst_ip'],
            $row['src_ip']
        );
    }
}
