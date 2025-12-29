<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */
try {
    $stmt = $pdo->query("SELECT id, name FROM ticket_categories");
    while($row = $stmt->fetch()) {
        echo $row['id'] . ": " . $row['name'] . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
