<?php
require 'includes/bootstrap.php';
$plugins = plugins_get_all($pdo);
echo "Total plugins: " . count($plugins) . "\n";
foreach($plugins as $p) {
    echo " - " . $p['name'] . " (" . $p['category'] . ")\n";
}
