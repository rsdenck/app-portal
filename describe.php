<?php
require 'includes/bootstrap.php';
$table = $argv[1] ?? 'plugin_dflow_flows';
$stmt = $pdo->query("DESCRIBE $table");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Field: " . $row['Field'] . " - Type: " . $row['Type'] . "\n";
}
