<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

echo "=== Verificação de Categorias e Schemas JSON ===\n";

$stmt = $pdo->query("SELECT id, name, slug, schema_json FROM ticket_categories");
$categories = $stmt->fetchAll();

if (empty($categories)) {
    echo "ERRO: Nenhuma categoria encontrada.\n";
    exit;
}

foreach ($categories as $cat) {
    echo "Categoria: {$cat['name']} ({$cat['slug']})\n";
    
    $schema = json_decode($cat['schema_json'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "  - ERRO: JSON inválido no schema_json.\n";
        continue;
    }
    
    if (is_array($schema)) {
        echo "  - OK: Schema JSON válido (" . count($schema) . " campos definidos).\n";
        foreach ($schema as $field) {
            echo "    > Campo: {$field['name']} ({$field['type']})\n";
        }
    } else {
        echo "  - AVISO: Schema está vazio ou não é um array.\n";
    }
    echo "\n";
}
