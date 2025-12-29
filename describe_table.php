<?php
$config = require 'config/config.php';
$db = $config['db'];
$pdo = new PDO($db['dsn'], $db['user'], $db['pass']);
$stmt = $pdo->query('DESCRIBE plugin_dflow_flows');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Field: " . $row['Field'] . " - Type: " . $row['Type'] . "\n";
}
