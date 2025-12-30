<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/**
 * DFlow Baseline Updater
 * 
 * Calcula a média e desvio padrão do tráfego histórico para alimentar o Watcher.
 */
class DFlowBaselineUpdater
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function updateAll(): void
    {
        echo "Iniciando atualização de baselines...\n";
        
        $this->updateVlanBaselines();
        $this->updateAsnBaselines();
        $this->updateInterfaceBaselines();
        $this->updateProtocolBaselines();
        
        echo "Atualização de baselines finalizada.\n";
    }

    private function updateVlanBaselines(): void
    {
        echo "- Calculando baseline por VLAN/Hora/Dia da Semana...\n";

        $sql = "
            REPLACE INTO plugin_dflow_baselines_dim 
            (entity_type, entity_value, hour_of_day, day_of_week, avg_bytes, stddev_bytes, avg_packets, stddev_packets, sample_count)
            SELECT 
                'vlan',
                CAST(vlan AS CHAR),
                HOUR(hour_ts),
                DAYOFWEEK(hour_ts),
                AVG(total_bytes),
                STDDEV(total_bytes),
                AVG(total_packets),
                STDDEV(total_packets),
                COUNT(*)
            FROM (
                SELECT vlan, DATE_FORMAT(ts, '%Y-%m-%d %H:00:00') as hour_ts, SUM(bytes) as total_bytes, SUM(pkts) as total_packets
                FROM plugin_dflow_flows
                WHERE ts > DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND vlan IS NOT NULL
                GROUP BY vlan, DATE_FORMAT(ts, '%Y-%m-%d %H:00:00')
            ) as hourly_stats
            GROUP BY vlan, HOUR(hour_ts), DAYOFWEEK(hour_ts)
        ";

        $this->pdo->exec($sql);
    }

    private function updateAsnBaselines(): void
    {
        echo "- Calculando baseline por ASN/Hora/Dia da Semana...\n";

        // Calculamos para AS de origem e destino combinados para ver o volume total por ASN
        $sql = "
            REPLACE INTO plugin_dflow_baselines_dim 
            (entity_type, entity_value, hour_of_day, day_of_week, avg_bytes, stddev_bytes, avg_packets, stddev_packets, sample_count)
            SELECT 
                'asn',
                CAST(asn AS CHAR),
                HOUR(hour_ts),
                DAYOFWEEK(hour_ts),
                AVG(total_bytes),
                STDDEV(total_bytes),
                AVG(total_packets),
                STDDEV(total_packets),
                COUNT(*)
            FROM (
                -- União de tráfego por ASN de origem e destino
                SELECT asn, hour_ts, SUM(total_bytes) as total_bytes, SUM(total_packets) as total_packets
                FROM (
                    SELECT as_src as asn, DATE_FORMAT(ts, '%Y-%m-%d %H:00:00') as hour_ts, SUM(bytes) as total_bytes, SUM(pkts) as total_packets
                    FROM plugin_dflow_flows
                    WHERE ts > DATE_SUB(NOW(), INTERVAL 7 DAY) AND as_src IS NOT NULL AND as_src > 0
                    GROUP BY as_src, DATE_FORMAT(ts, '%Y-%m-%d %H:00:00')
                    
                    UNION ALL
                    
                    SELECT as_dst as asn, DATE_FORMAT(ts, '%Y-%m-%d %H:00:00') as hour_ts, SUM(bytes) as total_bytes, SUM(pkts) as total_packets
                    FROM plugin_dflow_flows
                    WHERE ts > DATE_SUB(NOW(), INTERVAL 7 DAY) AND as_dst IS NOT NULL AND as_dst > 0
                    GROUP BY as_dst, DATE_FORMAT(ts, '%Y-%m-%d %H:00:00')
                ) as combined
                GROUP BY asn, hour_ts
            ) as hourly_stats
            GROUP BY asn, HOUR(hour_ts), DAYOFWEEK(hour_ts)
        ";

        $this->pdo->exec($sql);
    }

    private function updateInterfaceBaselines(): void
    {
        echo "- Calculando baseline por Interface/Hora/Dia da Semana...\n";

        $sql = "
            REPLACE INTO plugin_dflow_baselines_dim 
            (entity_type, entity_value, hour_of_day, day_of_week, avg_bytes, stddev_bytes, avg_packets, stddev_packets, sample_count)
            SELECT 
                'interface',
                iface,
                HOUR(hour_ts),
                DAYOFWEEK(hour_ts),
                AVG(total_bytes),
                STDDEV(total_bytes),
                AVG(total_packets),
                STDDEV(total_packets),
                COUNT(*)
            FROM (
                SELECT iface, hour_ts, SUM(total_bytes) as total_bytes, SUM(total_packets) as total_packets
                FROM (
                    SELECT input_if as iface, DATE_FORMAT(ts, '%Y-%m-%d %H:00:00') as hour_ts, SUM(bytes) as total_bytes, SUM(pkts) as total_packets
                    FROM plugin_dflow_flows
                    WHERE ts > DATE_SUB(NOW(), INTERVAL 7 DAY) AND input_if IS NOT NULL AND input_if != ''
                    GROUP BY input_if, DATE_FORMAT(ts, '%Y-%m-%d %H:00:00')
                    
                    UNION ALL
                    
                    SELECT output_if as iface, DATE_FORMAT(ts, '%Y-%m-%d %H:00:00') as hour_ts, SUM(bytes) as total_bytes, SUM(pkts) as total_packets
                    FROM plugin_dflow_flows
                    WHERE ts > DATE_SUB(NOW(), INTERVAL 7 DAY) AND output_if IS NOT NULL AND output_if != ''
                    GROUP BY output_if, DATE_FORMAT(ts, '%Y-%m-%d %H:00:00')
                ) as combined
                GROUP BY iface, hour_ts
            ) as hourly_stats
            GROUP BY iface, HOUR(hour_ts), DAYOFWEEK(hour_ts)
        ";

        $this->pdo->exec($sql);
    }

    private function updateProtocolBaselines(): void
    {
        echo "- Calculando baseline por Protocolo/Hora/Dia da Semana...\n";

        $sql = "
            REPLACE INTO plugin_dflow_baselines_dim 
            (entity_type, entity_value, hour_of_day, day_of_week, avg_bytes, stddev_bytes, avg_packets, stddev_packets, sample_count)
            SELECT 
                'protocol',
                proto,
                HOUR(hour_ts),
                DAYOFWEEK(hour_ts),
                AVG(total_bytes),
                STDDEV(total_bytes),
                AVG(total_packets),
                STDDEV(total_packets),
                COUNT(*)
            FROM (
                SELECT proto, DATE_FORMAT(ts, '%Y-%m-%d %H:00:00') as hour_ts, SUM(bytes) as total_bytes, SUM(pkts) as total_packets
                FROM plugin_dflow_flows
                WHERE ts > DATE_SUB(NOW(), INTERVAL 7 DAY) AND proto IS NOT NULL
                GROUP BY proto, DATE_FORMAT(ts, '%Y-%m-%d %H:00:00')
            ) as hourly_stats
            GROUP BY proto, HOUR(hour_ts), DAYOFWEEK(hour_ts)
        ";

        $this->pdo->exec($sql);
    }
}

if (php_sapi_name() === 'cli') {
    $updater = new DFlowBaselineUpdater($pdo);
    $updater->updateAll();
}
