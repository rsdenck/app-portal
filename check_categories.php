<?php
require_once __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */
$stmt = $pdo->query("SELECT DISTINCT category FROM plugins");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
