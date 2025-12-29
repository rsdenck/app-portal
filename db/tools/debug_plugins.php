<?php
require 'includes/bootstrap.php';
try {
    $stmt = $pdo->query('SELECT name FROM plugins');
    while($row = $stmt->fetch()) {
        echo $row['name'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
