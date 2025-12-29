<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */
// Use $pdo from bootstrap.php

// Test attack ticket
$attack = [
    'attacker' => '1.2.3.4',
    'target' => '5.6.7.8',
    'name' => 'Test Exploit Attempt',
    'severity' => 'high',
    'abuse_score' => 95,
    'cves' => ['CVE-2025-5287']
];

echo "Testing Attack Ticket...\n";
require_once __DIR__ . '/scripts/threat_intel_collector.php';

// Mock Corgea Client for test
class MockCorgea {
    public function searchCve($id) { return ['severity' => 'CRITICAL', 'remediation' => 'Patch immediately']; }
}

$ticketId = create_attack_ticket($pdo, $attack, new MockCorgea());
echo "Created Attack Ticket ID: $ticketId\n";

// Test BGP ticket
echo "Testing BGP Ticket...\n";
$bgpTicketId = create_bgp_ticket($pdo, '12345', 'AS67890');
echo "Created BGP Ticket ID: $bgpTicketId\n";

// Verify in DB
$stmt = $pdo->prepare("SELECT id, subject, description FROM tickets WHERE id IN (?, ?)");
$stmt->execute([$ticketId, $bgpTicketId]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($tickets);
