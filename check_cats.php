<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */
$cats = ticket_categories($pdo);
foreach($cats as $c) {
    echo $c['id'] . ' - ' . $c['name'] . ' (' . $c['slug'] . ")\n";
}
