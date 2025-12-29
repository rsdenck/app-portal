<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */

$userId = 1; // Assuming user ID 1 is the one we are interested in (Ranlens Denck)
$stmt = $pdo->prepare("
    SELECT u.name, u.role, tc.name as category_name, tc.slug as category_slug 
    FROM users u 
    LEFT JOIN attendant_profiles ap ON ap.user_id = u.id 
    LEFT JOIN ticket_categories tc ON tc.id = ap.category_id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

echo "User: " . ($user['name'] ?? 'Not found') . "\n";
echo "Role: " . ($user['role'] ?? 'N/A') . "\n";
echo "Category: " . ($user['category_name'] ?? 'None') . " (" . ($user['category_slug'] ?? 'none') . ")\n";

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "\nTables in database:\n";
foreach ($tables as $t) {
    echo "- $t\n";
}
