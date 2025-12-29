<?php
$config = require_once __DIR__ . '/config/config.php';
$dbConfig = $config['db'];

try {
    $pdo = new PDO($dbConfig['dsn'], $dbConfig['user'], $dbConfig['pass'], $dbConfig['options']);

    echo "--- Verificação de Integridade de Fluxos DFlow ---\n";

    // 1. Verificar total de fluxos
    $count = $pdo->query("SELECT COUNT(*) FROM plugin_dflow_flows")->fetchColumn();
    echo "Total de fluxos na tabela: $count\n";

    if ($count > 0) {
        // 2. Verificar range de timestamps
        $range = $pdo->query("SELECT MIN(ts) as min_ts, MAX(ts) as max_ts FROM plugin_dflow_flows")->fetch(PDO::FETCH_ASSOC);
        echo "Fluxo mais antigo: " . $range['min_ts'] . "\n";
        echo "Fluxo mais recente: " . $range['max_ts'] . "\n";
        echo "Data/Hora Atual: " . date('Y-m-d H:i:s') . "\n";

        // 3. Verificar fluxos nos últimos 5 minutos
        $recent = $pdo->query("SELECT COUNT(*) FROM plugin_dflow_flows WHERE ts >= NOW() - INTERVAL 5 MINUTE")->fetchColumn();
        echo "Fluxos ativos (últimos 5 min): $recent\n";

        // 4. Verificar top 5 fontes de tráfego (para validar se há dados reais)
        echo "\nTop 5 Fontes de Tráfego:\n";
        $top = $pdo->query("SELECT src_ip, SUM(bytes) as total_bytes FROM plugin_dflow_flows GROUP BY src_ip ORDER BY total_bytes DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($top as $row) {
            echo "IP: {$row['src_ip']} - " . number_format($row['total_bytes'] / 1024, 2) . " KB\n";
        }
    } else {
        echo "AVISO: A tabela plugin_dflow_flows está vazia. O ingestor ou a engine podem não estar rodando.\n";
    }

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
