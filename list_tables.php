<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */
$stmt = $pdo->query('SHOW TABLES');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
