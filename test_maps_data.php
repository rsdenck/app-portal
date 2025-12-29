<?php
// Simular acesso ao plugin_dflow_maps_data.php
$_GET['mode'] = 'hosts';
ob_start();
require 'app/plugin_dflow_maps_data.php';
$output = ob_get_clean();

$data = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Erro ao decodificar JSON: " . json_last_error_msg() . "\n";
    echo "Saída bruta:\n" . substr($output, 0, 500) . "...\n";
} else {
    echo "JSON Válido!\n";
    echo "Total de Nós: " . count($data['nodes']) . "\n";
    echo "Total de Links: " . count($data['links']) . "\n";
    
    if (count($data['nodes']) > 0) {
        echo "Exemplo de Nó: " . json_encode($data['nodes'][0]) . "\n";
    }
    if (count($data['links']) > 0) {
        echo "Exemplo de Link: " . json_encode($data['links'][0]) . "\n";
    }
}
