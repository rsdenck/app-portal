<?php
declare(strict_types=1);

/**
 * DFlow BGP Analyzer
 * Analisa o histórico de prefixos para detectar Hijacks e Route Leaks.
 */

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

function log_analyzer($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] [BGP-ANALYZER] $msg\n";
}

/**
 * Detecta possíveis Hijacks: Mesmo prefixo sendo anunciado por ASNs diferentes
 * sem um histórico de transição legítimo.
 */
function detect_hijacks(PDO $pdo) {
    log_analyzer("Iniciando detecção de BGP Hijacks...");
    
    $sql = "SELECT prefix, COUNT(DISTINCT asn_id) as asn_count, GROUP_CONCAT(asn_id) as asns
            FROM plugin_dflow_asn_prefixes
            WHERE valid_to IS NULL
            GROUP BY prefix
            HAVING asn_count > 1";
            
    $conflicts = $pdo->query($sql)->fetchAll();
    
    foreach ($conflicts as $c) {
        $msg = "Possível HIJACK detectado: Prefixo {$c['prefix']} anunciado por múltiplos ASNs: {$c['asns']}";
        log_analyzer($msg);
        
        // Registrar alerta no sistema
        $stmt = $pdo->prepare("INSERT INTO plugin_dflow_alerts (type, severity, subject, description) VALUES ('BGP_HIJACK', 'critical', ?, ?)");
        $stmt->execute(["BGP Hijack: {$c['prefix']}", $msg]);
    }
}

/**
 * Detecta Route Leaks: Mudança súbita e não esperada de ASN para um prefixo
 */
function detect_leaks(PDO $pdo) {
    log_analyzer("Iniciando detecção de Route Leaks...");
    // Lógica baseada na comparação com o último snapshot estável
}

// Execução
detect_hijacks($pdo);
log_analyzer("Análise finalizada.");
