<?php
require_once 'includes/bootstrap.php';
$stmt = $pdo->query('DESCRIBE plugin_dflow_flows');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
