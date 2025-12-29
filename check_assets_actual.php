<?php
require_once __DIR__ . '/includes/bootstrap.php';

$stmt = $pdo->query("DESCRIBE assets");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}
