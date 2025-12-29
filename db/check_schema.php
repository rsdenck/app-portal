<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */
$stmt = $pdo->query("DESCRIBE plugin_bgp_data");
while ($row = $stmt->fetch()) {
    print_r($row);
}
