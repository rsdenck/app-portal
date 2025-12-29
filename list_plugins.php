<?php
require __DIR__ . '/includes/bootstrap.php';
$stmt = $pdo->query("SELECT name, label FROM plugins");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['name'] . ': ' . $row['label'] . PHP_EOL;
}
