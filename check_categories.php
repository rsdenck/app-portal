<?php
require_once 'includes/bootstrap.php';
try {
    $stmt = $pdo->query("SELECT id, name FROM ticket_categories");
    while($row = $stmt->fetch()) {
        echo $row['id'] . ": " . $row['name'] . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
