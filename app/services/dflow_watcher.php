<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/**
 * DFlow Watcher - Anomaly Detection & Security Correlation Engine
 * 
 * Responsável por analisar flows reais, comparar com baselines e classificar eventos sob MITRE ATT&CK.
 * Foco: Observabilidade e Forense (Zero Bloqueio).
 */
class DFlowWatcher
{
    private PDO $pdo;
    private float $zScoreThreshold = 3.0; // Desvio padrão para anomalias de volume
    private int $scanIpThreshold = 20;    // IPs únicos para port scan horizontal
    private int $scanPortThreshold = 50;  // Portas únicas para port scan vertical
    private int $windowMinutes = 5;       // Janela de análise em minutos

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function run(): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] DFlow Watcher: Iniciando ciclo de análise...\n";

        $this->detectVolumeAnomalies();
        $this->detectScans();
        $this->detectIcmpSweep();
        $this->detectTcpAnomalies();
        $this->detectL7Mismatch();

        echo "[" . date('Y-m-d H:i:s') . "] DFlow Watcher: Ciclo finalizado.\n";
    }

    /**
     * Detecta anomalias de volume baseadas em Baseline (Z-Score)
     * Agora expandido para múltiplas dimensões (VLAN, ASN, Interface, Protocolo)
     */
    private function detectVolumeAnomalies(): void
    {
        echo "- Analisando anomalias de volume (VLAN, ASN, Interface, Protocolo)...\n";
        
        $hour = (int)date('G');
        $dow = (int)date('w');

        $dimensions = [
            'vlan' => "SELECT f.vlan as entity_id, SUM(f.bytes) as current_bytes, MAX(b.avg_bytes) as avg_bytes, MAX(b.stddev_bytes) as stddev_bytes 
                       FROM plugin_dflow_flows f JOIN plugin_dflow_baselines_dim b ON b.entity_value = CAST(f.vlan AS CHAR)
                       WHERE f.ts > DATE_SUB(NOW(), INTERVAL :mins MINUTE) AND b.entity_type = 'vlan' AND b.hour_of_day = :hour AND b.day_of_week = :dow GROUP BY f.vlan",
            
            'asn' => "SELECT f.id as entity_id, SUM(f.bytes) as current_bytes, MAX(b.avg_bytes) as avg_bytes, MAX(b.stddev_bytes) as stddev_bytes, MAX(a.organization) as org_name
                          FROM (
                              SELECT as_src as id, bytes, ts FROM plugin_dflow_flows WHERE as_src > 0
                              UNION ALL
                              SELECT as_dst as id, bytes, ts FROM plugin_dflow_flows WHERE as_dst > 0
                          ) f
                          JOIN plugin_dflow_baselines_dim b ON b.entity_value = CAST(f.id AS CHAR)
                          LEFT JOIN plugin_dflow_asns a ON a.asn_number = f.id
                          WHERE f.ts > DATE_SUB(NOW(), INTERVAL :mins MINUTE) AND b.entity_type = 'asn' AND b.hour_of_day = :hour AND b.day_of_week = :dow GROUP BY f.id",
            
            'interface' => "SELECT f.name as entity_id, SUM(f.bytes) as current_bytes, MAX(b.avg_bytes) as avg_bytes, MAX(b.stddev_bytes) as stddev_bytes
                            FROM (
                                SELECT input_if as name, bytes, ts FROM plugin_dflow_flows WHERE input_if != ''
                                UNION ALL
                                SELECT output_if as name, bytes, ts FROM plugin_dflow_flows WHERE output_if != ''
                            ) f
                            JOIN plugin_dflow_baselines_dim b ON b.entity_value = f.name
                            WHERE f.ts > DATE_SUB(NOW(), INTERVAL :mins MINUTE) AND b.entity_type = 'interface' AND b.hour_of_day = :hour AND b.day_of_week = :dow GROUP BY f.name",
            
            'protocol' => "SELECT f.proto as entity_id, SUM(f.bytes) as current_bytes, MAX(b.avg_bytes) as avg_bytes, MAX(b.stddev_bytes) as stddev_bytes
                           FROM plugin_dflow_flows f JOIN plugin_dflow_baselines_dim b ON b.entity_value = f.proto
                           WHERE f.ts > DATE_SUB(NOW(), INTERVAL :mins MINUTE) AND b.entity_type = 'protocol' AND b.hour_of_day = :hour AND b.day_of_week = :dow GROUP BY f.proto"
        ];

        foreach ($dimensions as $dim => $sql) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'mins' => $this->windowMinutes,
                'hour' => $hour,
                'dow' => $dow
            ]);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['stddev_bytes'] > 0) {
                    $zScore = ($row['current_bytes'] - $row['avg_bytes']) / $row['stddev_bytes'];
                    
                    if ($zScore > $this->zScoreThreshold) {
                        $this->logSecurityEvent(
                            'volume_anomaly',
                            'high',
                            null, null, 
                            ($dim === 'asn' ? (int)$row['entity_id'] : null), 
                            null, 
                            ($dim === 'protocol' ? $row['entity_id'] : null), 
                            null,
                            [
                                'dimension' => $dim,
                                'entity_id' => $row['entity_id'],
                                    'org_name' => $row['org_name'] ?? null,
                                    'current_bytes' => (int)$row['current_bytes'],
                                'avg_bytes' => (int)$row['avg_bytes'],
                                'z_score' => round($zScore, 2)
                            ],
                            ['T1498'] // Network Denial of Service (MITRE)
                        );
                    }
                }
            }
        }
    }

    /**
     * Detecta ICMP Sweeps (Pings em massa)
     */
    private function detectIcmpSweep(): void
    {
        echo "- Analisando ICMP Sweeps...\n";

        $sql = "
            SELECT 
                src_ip, 
                COUNT(DISTINCT dst_ip) as target_count
            FROM plugin_dflow_flows
            WHERE ts > DATE_SUB(NOW(), INTERVAL :mins MINUTE)
              AND proto = 'ICMP'
            GROUP BY src_ip
            HAVING target_count > :threshold
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['mins' => $this->windowMinutes, 'threshold' => $this->scanIpThreshold]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->logSecurityEvent(
                'icmp_sweep',
                'medium',
                $row['src_ip'], null, null, null, 'ICMP', null,
                [
                    'target_ips_count' => $row['target_count']
                ],
                ['T1595.001'] // Active Scanning: IP Addresses (ICMP Sweep)
            );
        }
    }

    /**
     * Detecta Port Scans (Horizontal e Vertical)
     */
    private function detectScans(): void
    {
        echo "- Analisando scans de porta...\n";

        // Port Scan Horizontal (Mesma porta, muitos IPs de destino)
        // Indica tentativa de encontrar um serviço específico na rede
        $sqlHorizontal = "
            SELECT 
                src_ip, 
                dst_port, 
                COUNT(DISTINCT dst_ip) as target_count,
                GROUP_CONCAT(DISTINCT proto) as protos,
                MIN(ts) as first_seen,
                MAX(ts) as last_seen
            FROM plugin_dflow_flows
            WHERE ts > DATE_SUB(NOW(), INTERVAL :mins MINUTE)
              AND dst_port > 0
            GROUP BY src_ip, dst_port
            HAVING target_count > :threshold
        ";

        $stmt = $this->pdo->prepare($sqlHorizontal);
        $stmt->execute(['mins' => $this->windowMinutes, 'threshold' => $this->scanIpThreshold]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->logSecurityEvent(
                'port_scan_horizontal',
                'medium',
                $row['src_ip'], null, null, null, null, null,
                [
                    'target_port' => $row['dst_port'],
                    'target_ips_count' => $row['target_count'],
                    'protocols' => $row['protos'],
                    'first_seen' => $row['first_seen'],
                    'last_seen' => $row['last_seen']
                ],
                ['T1046', 'T1595'] // Network Service Scanning, Active Scanning
            );
        }

        // Port Scan Vertical (Muitas portas no mesmo IP de destino)
        // Indica tentativa de encontrar vulnerabilidades ou serviços em um host específico
        $sqlVertical = "
            SELECT 
                src_ip, 
                dst_ip, 
                COUNT(DISTINCT dst_port) as port_count,
                GROUP_CONCAT(DISTINCT dst_port ORDER BY dst_port ASC SEPARATOR ', ') as ports,
                MIN(ts) as first_seen,
                MAX(ts) as last_seen
            FROM plugin_dflow_flows
            WHERE ts > DATE_SUB(NOW(), INTERVAL :mins MINUTE)
              AND dst_port > 0
            GROUP BY src_ip, dst_ip
            HAVING port_count > :threshold
        ";

        $stmt = $this->pdo->prepare($sqlVertical);
        $stmt->execute(['mins' => $this->windowMinutes, 'threshold' => $this->scanPortThreshold]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Limitar a lista de portas para não estourar o JSON
            $portList = strlen($row['ports']) > 255 ? substr($row['ports'], 0, 252) . '...' : $row['ports'];
            
            $this->logSecurityEvent(
                'port_scan_vertical',
                'medium',
                $row['src_ip'], $row['dst_ip'], null, null, null, null,
                [
                    'target_ports_count' => $row['port_count'],
                    'ports_sample' => $portList,
                    'first_seen' => $row['first_seen'],
                    'last_seen' => $row['last_seen']
                ],
                ['T1046'] // Network Service Scanning
            );
        }
    }

    /**
     * Detecta SYN Floods e anomalias TCP
     */
    private function detectTcpAnomalies(): void
    {
        echo "- Analisando anomalias TCP/SYN...\n";

        // Detecção simplificada: Alto volume de flows pequenos com flag SYN (sem bytes de payload significativos)
        // Nota: tcp_flags deve ser capturado pelo sensor
        $sqlSyn = "
            SELECT 
                dst_ip, 
                COUNT(*) as syn_count,
                SUM(bytes) / COUNT(*) as avg_bytes_per_flow
            FROM plugin_dflow_flows
            WHERE ts > DATE_SUB(NOW(), INTERVAL :mins MINUTE)
              AND tcp_flags & 2 -- SYN Flag
              AND (tcp_flags & 16) = 0 -- NOT ACK
            GROUP BY dst_ip
            HAVING syn_count > 500 AND avg_bytes_per_flow < 100
        ";

        $stmt = $this->pdo->prepare($sqlSyn);
        $stmt->execute(['mins' => $this->windowMinutes]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->logSecurityEvent(
                'syn_flood',
                'critical',
                null, $row['dst_ip'], null, null, 'TCP', null,
                [
                    'syn_rate' => $row['syn_count'],
                    'avg_bytes' => $row['avg_bytes_per_flow']
                ],
                ['T1499.002'] // Endpoint Denial of Service: Service Exhaustion (SYN Flood)
            );
        }
    }

    /**
     * Detecta Mismatch entre Porta e Protocolo L7 (DPI vs Port)
     */
    private function detectL7Mismatch(): void
    {
        echo "- Analisando L7 Mismatch...\n";

        // Mapeamento básico de portas padrão
        $standardPorts = [
            'HTTP' => [80, 8080, 81],
            'HTTPS' => [443, 8443],
            'DNS' => [53],
            'SSH' => [22],
            'FTP' => [21],
            'SMTP' => [25, 587, 465],
            'RDP' => [3389]
        ];

        $sql = "
            SELECT src_ip, dst_ip, dst_port, app_proto, proto
            FROM plugin_dflow_flows
            WHERE ts > DATE_SUB(NOW(), INTERVAL :mins MINUTE)
              AND app_proto IS NOT NULL 
              AND app_proto != 'UNKNOWN'
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['mins' => $this->windowMinutes]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $proto = strtoupper($row['app_proto']);
            if (isset($standardPorts[$proto]) && !in_array((int)$row['dst_port'], $standardPorts[$proto])) {
                $this->logSecurityEvent(
                    'l7_mismatch',
                    'low',
                    $row['src_ip'], $row['dst_ip'], null, null, $row['proto'], $row['app_proto'],
                    [
                        'expected_ports' => $standardPorts[$proto],
                        'actual_port' => $row['dst_port']
                    ],
                    ['T1071'] // Application Layer Protocol (C2 ou Evasion)
                );
            }
        }
    }

    /**
     * Registra um evento de segurança na tabela plugin_dflow_security_events
     */
    private function logSecurityEvent(
        string $type,
        string $severity,
        ?string $srcIp,
        ?string $dstIp,
        ?int $srcAsn,
        ?int $dstAsn,
        ?string $protoL4,
        ?string $protoL7,
        array $evidence,
        array $mitre
    ): void {
        $sql = "INSERT INTO plugin_dflow_security_events 
                (event_type, severity, src_ip, dst_ip, src_asn, dst_asn, protocol_l4, protocol_l7, evidence, mitre_techniques)
                VALUES (:type, :sev, :src, :dst, :s_asn, :d_asn, :p4, :p7, :ev, :mitre)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'type' => $type,
            'sev' => $severity,
            'src' => $srcIp,
            'dst' => $dstIp,
            's_asn' => $srcAsn,
            'd_asn' => $dstAsn,
            'p4' => $protoL4,
            'p7' => $protoL7,
            'ev' => json_encode($evidence),
            'mitre' => json_encode($mitre)
        ]);
    }
}

// Execução se chamado via CLI
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $watcher = new DFlowWatcher($pdo);
    
    $once = in_array('--once', $argv);

    if ($once) {
        try {
            $watcher->run();
        } catch (Exception $e) {
            echo "ERRO no Watcher: " . $e->getMessage() . "\n";
            exit(1);
        }
        exit(0);
    }

    // Loop infinito controlado (Daemon)
    echo "DFlow Watcher Daemon iniciado.\n";
    while (true) {
        try {
            $watcher->run();
        } catch (Exception $e) {
            echo "ERRO no Watcher: " . $e->getMessage() . "\n";
        }
        sleep(60); // Executa a cada minuto
    }
}
