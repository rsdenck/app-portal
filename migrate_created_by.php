<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */
try {
    $cols = $pdo->query("SHOW COLUMNS FROM assets")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('created_by_role', $cols)) {
        $pdo->exec("ALTER TABLE assets ADD COLUMN created_by_role VARCHAR(20) DEFAULT 'atendente'");
        // Update existing assets to be owned by attendant by default
        $pdo->exec("UPDATE assets SET created_by_role = 'atendente' WHERE created_by_role IS NULL");
    }
    
    echo "Migration for created_by_role done.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
