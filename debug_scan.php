<?php
$pdo = new PDO('mysql:host=localhost;dbname=portal', 'root', '');
$stmt = $pdo->query('SELECT * FROM plugin_dflow_ip_scanning LIMIT 5');
if($stmt) {
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    echo "Table not found or error\n";
    print_r($pdo->errorInfo());
}
