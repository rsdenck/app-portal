<?php
require __DIR__ . '/includes/bootstrap.php';
$_GET['mode'] = 'hosts';
ob_start();
include __DIR__ . '/app/plugin_dflow_maps_data.php';
$output = ob_get_clean();
echo $output;
