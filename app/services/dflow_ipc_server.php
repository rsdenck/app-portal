<?php
declare(strict_types=1);

require __DIR__ . '/../../includes/bootstrap.php';
/** @var PDO $pdo */

// Configurações
$socketPath = "/tmp/dflow_ingest.sock";
$batchSize = 500;
$idleTimeout = 1; // segundos

function log_ipc($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] [IPC-SERVER] $msg\n";
}

// Limpeza de socket anterior
if (file_exists($socketPath)) {
    unlink($socketPath);
}

$server = stream_socket_server("unix://$socketPath", $errno, $errstr);
if (!$server) {
    log_ipc("Falha ao criar socket: $errstr ($errno)");
    exit(1);
}

chmod($socketPath, 0666); // Garantir que o engine C possa escrever
log_ipc("Escutando em $socketPath...");

$stmtFlow = $pdo->prepare("INSERT INTO plugin_dflow_flows 
    (src_ip, src_port, dst_ip, dst_port, protocol, app_proto, bytes, packets, vlan, ts, tcp_flags, rtt_ms, eth_type, pcp, sni, ja3, anomaly, cve, src_mac, dst_mac) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmtHost = $pdo->prepare("INSERT INTO plugin_dflow_hosts (ip_address, mac_address, vlan, total_bytes, last_seen) 
    VALUES (?, ?, ?, ?, FROM_UNIXTIME(?)) 
    ON DUPLICATE KEY UPDATE 
    mac_address = IF(VALUES(mac_address) IS NOT NULL AND VALUES(mac_address) != '00:00:00:00:00:00', VALUES(mac_address), mac_address),
    vlan = IF(VALUES(vlan) > 0, VALUES(vlan), vlan),
    total_bytes = total_bytes + VALUES(total_bytes), 
    last_seen = VALUES(last_seen)");

$stmtAsnMap = $pdo->prepare("INSERT INTO plugin_dflow_flow_asn_map (flow_id, src_asn_id, dst_asn_id, snapshot_id) VALUES (?, ?, ?, ?)");

$asnCache = [];

function resolve_ip_to_asn(PDO $pdo, string $ip, int $ts, &$asnCache) {
    if (isset($asnCache[$ip])) return $asnCache[$ip];

    // Busca o prefixo mais específico (longest prefix match) válido no timestamp do fluxo
    $sql = "SELECT asn_id, snapshot_id FROM plugin_dflow_asn_prefixes 
            WHERE INET_ATON(?) & INET_ATON(SUBSTRING_INDEX(prefix, '/', 1)) = INET_ATON(SUBSTRING_INDEX(prefix, '/', 1))
            AND (valid_from <= FROM_UNIXTIME(?) AND (valid_to IS NULL OR valid_to >= FROM_UNIXTIME(?)))
            ORDER BY CAST(SUBSTRING_INDEX(prefix, '/', -1) AS UNSIGNED) DESC LIMIT 1";
    
    // Simplificação para IPv4. IPv6 requer INET6_ATON
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ip, $ts, $ts]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($res) {
        $asnCache[$ip] = $res;
        return $res;
    }

    return null;
}

$buffer = [];
$lastCommit = time();

while (true) {
    $read = [$server];
    $write = $except = null;
    
    if (stream_select($read, $write, $except, $idleTimeout) > 0) {
        $conn = stream_socket_accept($server);
        if ($conn) {
            log_ipc("Nova conexão do Engine C...");
            while (!feof($conn)) {
                $line = fgets($conn);
                if ($line === false) break;
                
                $line = trim($line);
                if (empty($line)) continue;
                
                $data = explode('|', $line);
                if (count($data) < 16) continue;

                $buffer[] = $data;

                if (count($buffer) >= $batchSize) {
                    processBatch($pdo, $stmtFlow, $stmtHost, $stmtAsnMap, $asnCache, $buffer);
                    $buffer = [];
                    $lastCommit = time();
                }
            }
            fclose($conn);
            log_ipc("Engine C desconectado.");
        }
    }

    // Commit por timeout se houver algo no buffer
    if (count($buffer) > 0 && (time() - $lastCommit) >= 5) {
        processBatch($pdo, $stmtFlow, $stmtHost, $stmtAsnMap, $asnCache, $buffer);
        $buffer = [];
        $lastCommit = time();
    }

    // Processamento de Fallback (Arquivos)
    processFallbackFiles($pdo, $stmtFlow, $stmtHost, $stmtAsnMap, $asnCache);
}

function processFallbackFiles(PDO $pdo, $stmtFlow, $stmtHost, $stmtAsnMap, &$asnCache) {
    $logDir = getenv('DFLOW_LOG_DIR') ?: dirname(__DIR__, 2) . '/dflow-engine';
    $files = glob($logDir . "/dflow_pending_flows_t*.log");
    
    foreach ($files as $file) {
        if (filesize($file) > 0) {
            log_ipc("Processando arquivo de fallback: " . basename($file));
            $handle = fopen($file, 'r+');
            if ($handle && flock($handle, LOCK_EX)) {
                $batch = [];
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    $batch[] = explode('|', $line);
                    if (count($batch) >= 500) {
                        processBatch($pdo, $stmtFlow, $stmtHost, $stmtAsnMap, $asnCache, $batch);
                        $batch = [];
                    }
                }
                if (count($batch) > 0) {
                    processBatch($pdo, $stmtFlow, $stmtHost, $stmtAsnMap, $asnCache, $batch);
                }
                ftruncate($handle, 0);
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    }
}

function processBatch(PDO $pdo, $stmtFlow, $stmtHost, $stmtAsnMap, &$asnCache, array $batch) {
    try {
        $pdo->beginTransaction();
        foreach ($batch as $data) {
            $ts = (int)$data[0];
            $srcIp = $data[1];
            $dstIp = $data[2];
            $srcPort = (int)$data[3];
            $dstPort = (int)$data[4];
            $protoNum = (int)$data[5];
            $bytes = (int)$data[6];
            $packets = (int)$data[7];
            $l7 = $data[8];
            $sni = $data[9];
            $ja3 = $data[10];
            $anomaly = $data[11];
            $cve = $data[12];
            $srcMac = $data[13];
            $dstMac = $data[14];
            $vlan = (int)$data[15];
            $tcpFlags = (int)($data[16] ?? 0);
            $rtt = (float)($data[17] ?? 0);
            $ethType = $data[18] ?? '0x0800';
            $pcp = (int)($data[19] ?? 0);

            $proto = $protoNum == 6 ? 'TCP' : ($protoNum == 17 ? 'UDP' : (string)$protoNum);

            $stmtFlow->execute([
                $srcIp, $srcPort, $dstIp, $dstPort, $proto, $l7, $bytes, $packets, $vlan, $ts,
                $tcpFlags, $rtt, $ethType, $pcp, $sni, $ja3, $anomaly, $cve, $srcMac, $dstMac
            ]);
            
            $flowId = $pdo->lastInsertId();

            $stmtHost->execute([$srcIp, $srcMac, $vlan, $bytes, $ts]);
            $stmtHost->execute([$dstIp, $dstMac, $vlan, $bytes, $ts]);

            // Correlação ASN Temporal
            $srcAsn = resolve_ip_to_asn($pdo, $srcIp, $ts, $asnCache);
            $dstAsn = resolve_ip_to_asn($pdo, $dstIp, $ts, $asnCache);

            if ($srcAsn || $dstAsn) {
                $stmtAsnMap->execute([
                    $flowId,
                    $srcAsn['asn_id'] ?? null,
                    $dstAsn['asn_id'] ?? null,
                    $srcAsn['snapshot_id'] ?? $dstAsn['snapshot_id'] ?? null
                ]);
            }
        }
        $pdo->commit();
        
        // Limpa cache se crescer demais para evitar leak de memória
        if (count($asnCache) > 10000) $asnCache = [];

        log_ipc("Batch de " . count($batch) . " fluxos processado via IPC com correlação BGP.");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_ipc("Erro no processamento de batch: " . $e->getMessage());
    }
}
