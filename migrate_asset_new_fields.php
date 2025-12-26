<?php
require __DIR__ . '/includes/bootstrap.php';

try {
    $pdo->exec("ALTER TABLE assets ADD COLUMN resource_name VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE assets ADD COLUMN allocation_place VARCHAR(255) NULL");
    echo "Migration successful: Added resource_name and allocation_place columns to assets table.\n";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Migration skipped: Columns already exist.\n";
    } else {
        echo "Migration failed: " . $e->getMessage() . "\n";
    }
}
