<?php
require_once 'includes/bootstrap.php';
$pdo->exec('DROP TABLE IF EXISTS plugin_dflow_security_events, plugin_dflow_baselines_dim');
plugins_ensure_table($pdo);
echo "Tables recreated with correct collation.\n";
