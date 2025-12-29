<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */
$stmt = $pdo->query('DESCRIBE plugin_dflow_flows');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
