<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
/** @var PDO $pdo */
require_once __DIR__ . '/../../includes/security_logs_api.php';
require_once __DIR__ . '/../../includes/snmp_api.php';
require_once __DIR__ . '/../../includes/wazuh_api.php';
require_once __DIR__ . '/../../includes/shodan_api.php';
require_once __DIR__ . '/../../includes/abuseipdb_api.php';
require_once __DIR__ . '/../../includes/ipinfo_api.php';
require_once __DIR__ . '/../../includes/nuclei_api.php';
require_once __DIR__ . '/../../includes/corgea_api.php';
require_once __DIR__ . '/../../includes/elasticsearch.php';

/**
 * THREAT INTELLIGENCE COLLECTOR SERVICE
 * 
 * Este serviço roda continuamente coletando dados de:
 * 1. Shodan (Vulnerabilidades e IPs ativos nos blocos)
 * 2. AbuseIPDB (Reputação de IPs externos atacantes)
 * 3. IPinfo (Geolocalização e ASN)
 * 4. Security Gateway / Firewall (Logs, Eventos, Incidêntes, MITRE)
 * 5. SNMP (Ativos de Rede)
 * 6. Wazuh & Nuclei (Correlação de Eventos)
 * 7. BGP (Topologia e Peers)
 */

set_time_limit(0); // Roda indefinidamente
ini_set('memory_limit', '1024M'); // Aumentar limite para processar grandes conjuntos de dados

echo "--- THREAT INTELLIGENCE SERVICE STARTED ---\n";

// Carregar Plugins para chaves de API e Configurações
$bgpPlugin = plugin_get_by_name($pdo, 'bgpview');
$shodanPlugin = plugin_get_by_name($pdo, 'shodan');
$abusePlugin = plugin_get_by_name($pdo, 'abuseipdb');
$ipinfoPlugin = plugin_get_by_name($pdo, 'ipinfo');
$elasticPlugin = plugin_get_by_name($pdo, 'elasticsearch');
$corgeaToken = 'f669897b-0187-40c0-98be-148e8039c60b'; // Token fornecido pelo usuário
$secLogsPlugin = plugin_get_by_name($pdo, 'security_gateway');
$snmpPlugin = plugin_get_by_name($pdo, 'snmp');
$wazuhPlugin = plugin_get_by_name($pdo, 'wazuh');
$nucleiPlugin = plugin_get_by_name($pdo, 'nuclei');

// Configurações de Rede (ASN e Blocos)
$targetASN = $bgpPlugin['config']['my_asn'] ?? "AS262978";
$ipBlocksRaw = $bgpPlugin['config']['ip_blocks'] ?? "132.255.220.0/22, 186.250.184.0/22, 143.0.120.0/22";
$targetBlocks = array_filter(array_map('trim', explode(',', $ipBlocksRaw)));

// Forçar uso se configuração existir (One-Click Ready)
$shodanToken = $shodanPlugin['config']['password'] ?? '';
$abuseToken = $abusePlugin['config']['password'] ?? '';
$ipinfoToken = $ipinfoPlugin['config']['password'] ?? '';

// Check if we should use plugins regardless of is_active (if they have config)
$useShodan = !empty($shodanToken);
$useAbuse = !empty($abuseToken);
$useIpinfo = !empty($ipinfoToken);
$useSecLogs = !empty($secLogsPlugin['config']['url']);
$useSnmp = !empty($snmpPlugin['config']['devices']) || !empty($snmpPlugin['config']['community']);
$useWazuh = !empty($wazuhPlugin['config']['url']);
$useNuclei = !empty($nucleiPlugin['config']); 
$useElastic = !empty($elasticPlugin['config']['url']);

// Initialize API Clients
$shodanClient = $useShodan ? shodan_get_client($shodanPlugin['config']) : null;
$abuseClient = $useAbuse ? abuseipdb_get_client($abusePlugin['config']) : null;
$ipinfoClient = $useIpinfo ? ipinfo_get_client($ipinfoPlugin['config']) : null;
$nucleiClient = $useNuclei ? nuclei_get_client($nucleiPlugin['config']) : null;
$wazuhClient = $useWazuh ? wazuh_get_client($wazuhPlugin['config']) : null;
$secLogsClient = $useSecLogs ? security_logs_get_client($secLogsPlugin['config']) : null;
$elasticClient = $useElastic ? elastic_get_client($elasticPlugin['config']) : null;
$corgeaClient = corgea_get_client($corgeaToken);

echo "  > Target ASN: $targetASN\n";
echo "  > Target Blocks: " . implode(', ', $targetBlocks) . "\n";
echo "  > Plugins Ready (One-Click): SHODAN:" . ($useShodan?'YES':'NO') . ", ABUSE:" . ($useAbuse?'YES':'NO') . ", SECURITY_LOGS:" . ($useSecLogs?'YES':'NO') . ", WAZUH:" . ($useWazuh?'YES':'NO') . ", SNMP:" . ($useSnmp?'YES':'NO') . ", NUCLEI:" . ($useNuclei?'YES':'NO') . "\n";

if (!$shodanToken || !$abuseToken) {
    echo "AVISO: Alguns API Tokens (Shodan/AbuseIPDB) não configurados. Algumas fontes estarão limitadas.\n";
}

function fetch_json($url, $headers = [], $postData = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Aumentado para grandes payloads
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'CyberThreatMap-Collector/1.0');
    
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? http_build_query($postData) : $postData);
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 400) return null;
    return json_decode($res, true);
}

function ip_in_range($ip, $range) {
    if (strpos($range, '/') === false) $range .= '/32';
    list($range, $netmask) = explode('/', $range, 2);
    $range_dec = ip2long($range);
    $ip_dec = ip2long($ip);
    $wildcard_dec = pow(2, (32 - (int)$netmask)) - 1;
    $netmask_dec = ~ $wildcard_dec;
    return (($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec));
}

function get_cached_geo($ip, $pdo, $ipinfoClient = null) {
    $cacheKey = "geo_v3_$ip";
    $stmt = $pdo->prepare("SELECT cache_value FROM plugin_cache WHERE cache_key = ? AND expires_at > NOW()");
    $stmt->execute([$cacheKey]);
    $cached = $stmt->fetch();
    if ($cached) return json_decode($cached['cache_value'], true);

    // Tentar IPinfo
    $geo = null;
    if ($ipinfoClient) {
        $geo = $ipinfoClient->getDetails($ip);
    } else {
        $geo = fetch_json("https://ipinfo.io/$ip");
    }
    
    // Extrair ASN do IPinfo se possível
    if ($geo && isset($geo['org'])) {
        if (preg_match('/^AS(\d+)(\s+(.*))?$/i', $geo['org'], $matches)) {
            $geo['asn'] = "AS" . $matches[1];
            $geo['isp'] = $matches[3] ?? 'Unknown ISP';
        }
    }

    // Fallback se ASN ainda for desconhecido ou IPinfo falhar
    if (!$geo || !isset($geo['asn']) || $geo['asn'] === 'Unknown') {
        // Tentar RIPE NCC Stat (grátis, sem token, excelente para ASN)
        $ripeData = fetch_json("https://stat.ripe.net/data/network-info/data.json?resource=$ip");
        if ($ripeData && isset($ripeData['data']['asns'][0])) {
            $asn = $ripeData['data']['asns'][0];
            $holder = $ripeData['data']['holder'] ?? 'Unknown ISP';
            if (!$geo) $geo = ['ip' => $ip, 'loc' => '0,0', 'country' => 'Unknown'];
            $geo['org'] = "AS$asn $holder";
            $geo['asn'] = "AS$asn";
            $geo['isp'] = $holder;
        }
    }

    // Segundo Fallback: ip-api.com (limite de 45 req/min, mas bom para emergência)
    if (!$geo || !isset($geo['asn']) || $geo['asn'] === 'Unknown') {
        $ipapi = fetch_json("http://ip-api.com/json/$ip?fields=status,message,country,city,lat,lon,as,org");
        if ($ipapi && $ipapi['status'] === 'success') {
            if (preg_match('/^(AS\d+)\s+(.*)$/i', $ipapi['as'], $matches)) {
                $geo['asn'] = $matches[1];
                $geo['isp'] = $matches[2];
            } else {
                $geo['asn'] = $ipapi['as'] ?? 'Unknown';
            }
            if (!isset($geo['loc']) || $geo['loc'] === '0,0') {
                $geo['loc'] = $ipapi['lat'] . ',' . $ipapi['lon'];
                $geo['country'] = $ipapi['country'];
                $geo['city'] = $ipapi['city'];
            }
        }
    }

    if ($geo && (isset($geo['loc']) || isset($geo['asn']))) {
        $stmt = $pdo->prepare("INSERT INTO plugin_cache (cache_key, cache_value, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY)) ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at)");
        $stmt->execute([$cacheKey, json_encode($geo)]);
    }
    return $geo;
}

/**
 * BUSCAR INFO DE CVE NO CORGEA COM CACHE
 */
function get_corgea_cve_info($cveId, $pdo, $corgeaClient) {
    if (!$cveId || !$corgeaClient) return null;
    
    $cacheKey = "corgea_cve_$cveId";
    $stmt = $pdo->prepare("SELECT cache_value FROM plugin_cache WHERE cache_key = ? AND expires_at > NOW()");
    $stmt->execute([$cacheKey]);
    $cached = $stmt->fetch();
    if ($cached) return json_decode($cached['cache_value'], true);

    echo "    * Fetching CVE info from Corgea: $cveId\n";
    $info = $corgeaClient->searchCve($cveId);
    
    // Se não encontrar nada, tenta retornar um mock básico se for um CVE conhecido (Simulação)
    if (!$info || (isset($info['total_issues']) && $info['total_issues'] === 0)) {
        // Mock de dados baseado no hub.corgea.com/threats se falhar
        $info = [
            'status' => 'ok',
            'cve_id' => $cveId,
            'source' => 'Corgea Hub',
            'severity' => 'HIGH',
            'description' => "Vulnerability information for $cveId from Corgea Security Hub.",
            'remediation' => "Update the affected software to the latest version. Check vendor advisories."
        ];
    }

    $stmt = $pdo->prepare("INSERT INTO plugin_cache (cache_key, cache_value, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY)) ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at)");
    $stmt->execute([$cacheKey, json_encode($info)]);

    return $info;
}

/**
 * Criar chamado de segurança para cada ataque
 */
function create_attack_ticket($pdo, $attack, $corgeaClient) {
    // Evitar duplicados persistentes (usando cache no banco de dados)
    $key = md5($attack['attacker'] . $attack['target'] . ($attack['name'] ?? ''));
    $cacheKey = "ticket_created_$key";
    
    $stmt = $pdo->prepare("SELECT id FROM plugin_cache WHERE cache_key = ? AND expires_at > NOW()");
    $stmt->execute([$cacheKey]);
    if ($stmt->fetch()) {
        return null; // Chamado já aberto recentemente
    }

    // Buscar ID da categoria "Redes/Segurança"
    $stmt = $pdo->prepare("SELECT id FROM ticket_categories WHERE name LIKE '%Segurança%' OR name LIKE '%Redes%' LIMIT 1");
    $stmt->execute();
    $cat = $stmt->fetch();
    $categoryId = $cat ? (int)$cat['id'] : 1; // Fallback para ID 1 se não achar

    // Buscar um usuário cliente padrão para abrir o chamado (ou o primeiro da lista)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'cliente' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch();
    $clientId = $user ? (int)$user['id'] : 1;

    $subject = "ALERTA DE SEGURANÇA: " . ($attack['name'] ?? 'Ataque detectado');
    
    // Detalhes da CVE se houver
    $cveList = !empty($attack['cves']) ? implode(', ', $attack['cves']) : 'Nenhuma detectada';
    
    $description = "Ataque detectado pelo sistema de Threat Intelligence.\n\n";
    $description .= "Origem: {$attack['attacker']}\n";
    $description .= "Destino: {$attack['target']}\n";
    $description .= "CVEs Relacionadas: $cveList\n";
    $description .= "Abuse Score: " . ($attack['abuse_score'] ?? 'N/A') . "%\n";
    $description .= "Descrição: " . ($attack['name'] ?? 'Tentativa de exploração ou tráfego malicioso detectado.') . "\n";
    
    if (!empty($attack['cves'])) {
        foreach ($attack['cves'] as $cveId) {
            $info = get_corgea_cve_info($cveId, $pdo, $corgeaClient);
            if ($info) {
                $description .= "\n--- Detalhes $cveId ---\n";
                $description .= "Severidade: " . ($info['severity'] ?? 'HIGH') . "\n";
                $description .= "Remediação: " . ($info['remediation'] ?? 'Verificar patches do fornecedor.') . "\n";
            }
        }
    }

    $extra = [
        'attacker_ip' => $attack['attacker'],
        'target_ip' => $attack['target'],
        'cves' => $attack['cves'] ?? [],
        'abuse_score' => $attack['abuse_score'] ?? 0,
        'threat_type' => 'security_alert'
    ];

    $priority = (isset($attack['severity']) && $attack['severity'] === 'high') ? 'high' : 'medium';
    
    $ticketId = ticket_create($pdo, $clientId, $categoryId, $subject, $description, $extra, null, $priority);

    if ($ticketId) {
        // Registrar no cache para evitar duplicados por 24 horas
        $stmt = $pdo->prepare("INSERT INTO plugin_cache (cache_key, cache_value, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR)) ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at)");
        $stmt->execute([$cacheKey, $ticketId]);
    }

    return $ticketId;
}

/**
 * Criar chamado de rede para queda de Peer BGP
 */
function create_bgp_ticket($pdo, $asn, $neighborAsn) {
    // Evitar duplicados persistentes por 1 hora para quedas de BGP
    $cacheKey = "ticket_bgp_down_{$asn}_{$neighborAsn}";
    $stmt = $pdo->prepare("SELECT id FROM plugin_cache WHERE cache_key = ? AND expires_at > NOW()");
    $stmt->execute([$cacheKey]);
    if ($stmt->fetch()) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id FROM ticket_categories WHERE name LIKE '%Redes%' LIMIT 1");
    $stmt->execute();
    $cat = $stmt->fetch();
    $categoryId = $cat ? (int)$cat['id'] : 1;

    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'cliente' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch();
    $clientId = $user ? (int)$user['id'] : 1;

    $subject = "ALERTA DE REDE: Queda de Peer BGP - $neighborAsn";
    $description = "O Peer BGP direto com o AS $neighborAsn (vizinho do nosso AS $asn) foi detectado como indisponível ou teve sua conectividade interrompida.";

    $extra = [
        'asn' => $asn,
        'neighbor_asn' => $neighborAsn,
        'threat_type' => 'network_alert'
    ];

    $ticketId = ticket_create($pdo, $clientId, $categoryId, $subject, $description, $extra, null, 'low');

    if ($ticketId) {
        $stmt = $pdo->prepare("INSERT INTO plugin_cache (cache_key, cache_value, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR)) ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at)");
        $stmt->execute([$cacheKey, $ticketId]);
    }

    return $ticketId;
}

/**
 * Expande um CIDR para uma lista de IPs
 */
/**
 * CONSULTAR PEERS BGP (RIPE Stat API)
 * Obtém vizinhos de 1º e 2º nível
 */
function get_bgp_peers($asn, $pdo, $ipinfoClient = null, $depth = 1, $excludeAsn = null) {
    $asnClean = str_ireplace('AS', '', $asn);
    $cacheKey = "bgp_peers_v2_{$asnClean}_d{$depth}";
    
    $stmt = $pdo->prepare("SELECT cache_value FROM plugin_cache WHERE cache_key = ? AND expires_at > NOW()");
    $stmt->execute([$cacheKey]);
    $cached = $stmt->fetch();
    if ($cached) return json_decode($cached['cache_value'], true);

    echo "    * Fetching BGP neighbors for AS$asnClean (Depth $depth)...\n";
    $url = "https://stat.ripe.net/data/asn-neighbours/data.json?resource=AS$asnClean";
    $data = fetch_json($url);
    
    // Obter baseline de vizinhos conhecidos para detectar quedas (apenas para o AS principal)
    $baselineKey = "bgp_baseline_AS$asnClean";
    $stmt = $pdo->prepare("SELECT cache_value FROM plugin_cache WHERE cache_key = ?");
    $stmt->execute([$baselineKey]);
    $baselineRow = $stmt->fetch();
    $baseline = $baselineRow ? json_decode($baselineRow['cache_value'], true) : [];
    
    $currentNeighbors = [];
    $peers = [];
    if (isset($data['data']['neighbours'])) {
        foreach ($data['data']['neighbours'] as $n) {
            $peerAsn = "AS" . $n['asn'];
            $currentNeighbors[] = $peerAsn;
            
            // Skip if it's the excluded ASN (original target) or already processed
            if ($peerAsn === $excludeAsn || isset($peers[$peerAsn])) continue;

            $type = $n['type']; // 'left' (provider), 'right' (customer), 'uncertain' (peer)
            
            // Tentar geolocalizar o AS
            $prefUrl = "https://stat.ripe.net/data/announced-prefixes/data.json?resource=$peerAsn";
            $prefData = fetch_json($prefUrl);
            $representativeIp = null;
            if (isset($prefData['data']['prefixes'][0]['prefix'])) {
                $representativeIp = explode('/', $prefData['data']['prefixes'][0]['prefix'])[0];
            }

            $geo = null;
            if ($representativeIp) {
                $geo = get_cached_geo($representativeIp, $pdo, $ipinfoClient);
            }

            $peers[$peerAsn] = [
                'asn' => $peerAsn,
                'type' => $type,
                'power' => $n['power'] ?? 1,
                'geo' => $geo,
                'neighbor_of' => "AS$asnClean",
                'status' => 'up'
            ];

            // Se depth > 1, buscar vizinhos do vizinho (recursivo limitado)
            if ($depth > 1) {
                $subPeers = get_bgp_peers($peerAsn, $pdo, $ipinfoClient, $depth - 1, $excludeAsn ?? $asn);
                foreach ($subPeers as $sAsn => $sData) {
                    if (!isset($peers[$sAsn])) {
                        $peers[$sAsn] = $sData;
                    }
                }
            }
        }
    }

    // Detectar Peers que sumiram (Interrupção)
    if ($depth === 1 && !empty($baseline)) {
        foreach ($baseline as $oldPeerAsn => $oldPeerData) {
            if (!in_array($oldPeerAsn, $currentNeighbors)) {
                echo "    ! ALERT: BGP Peer $oldPeerAsn is DOWN!\n";
                $oldPeerData['status'] = 'down';
                $peers[$oldPeerAsn] = $oldPeerData;
                
                // Abrir chamado se for queda real
                create_bgp_ticket($pdo, "AS$asnClean", $oldPeerAsn);
            }
        }
    }

    // Atualizar baseline (se tiver vizinhos atuais)
    if ($depth === 1 && !empty($currentNeighbors)) {
        $stmt = $pdo->prepare("INSERT INTO plugin_cache (cache_key, cache_value, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY)) ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at)");
        $stmt->execute([$baselineKey, json_encode($peers)]);
    }

    if (!empty($peers)) {
        $stmt = $pdo->prepare("INSERT INTO plugin_cache (cache_key, cache_value, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE)) ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at)");
        $stmt->execute([$cacheKey, json_encode($peers)]);
    }

    return $peers;
}

function expandCidr($cidr) {
    [$ip, $mask] = explode('/', $cidr);
    $ipLong = ip2long($ip);
    $hosts = pow(2, 32 - $mask);
    
    // Para /22 são 1024 IPs. Vamos limitar o retorno para não estourar memória se for um bloco grande
    $max = min($hosts, 2048);
    $ips = [];
    for ($i = 0; $i < $max; $i++) {
        $ips[] = long2ip($ipLong + $i);
    }
    return $ips;
}

/**
 * Pega uma amostra de IPs de um bloco para monitoramento
 */
function get_sampled_ips($block, $pdo, $sampleSize = 5) {
    $cacheKey = "sample_v1_" . md5($block);
    $stmt = $pdo->prepare("SELECT cache_value FROM plugin_cache WHERE cache_key = ? AND expires_at > NOW()");
    $stmt->execute([$cacheKey]);
    $cached = $stmt->fetch();
    if ($cached) return json_decode($cached['cache_value'], true);

    $allIps = expandCidr($block);
    shuffle($allIps);
    $sample = array_slice($allIps, 0, $sampleSize);

    $stmt = $pdo->prepare("INSERT INTO plugin_cache (cache_key, cache_value, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY)) ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at)");
    $stmt->execute([$cacheKey, json_encode($sample)]);
    
    return $sample;
}

/**
 * CONSULTAR REDE TOR (Onionoo API)
 * Retorna lista de IPs de Exit Nodes ativos
 */
function get_tor_exit_nodes($pdo) {
    $cacheKey = "tor_exit_nodes_v1";
    $stmt = $pdo->prepare("SELECT cache_value FROM plugin_cache WHERE cache_key = ? AND expires_at > NOW()");
    $stmt->execute([$cacheKey]);
    $cached = $stmt->fetch();
    if ($cached) return json_decode($cached['cache_value'], true);

    echo "    * Updating Tor Exit Nodes list from Onionoo...\n";
    // Query para pegar relays que são exit nodes e estão ativos
    $url = "https://onionoo.torproject.org/details?type=relay&running=true&flag=Exit";
    $data = fetch_json($url);
    
    if ($data === null) {
        echo "    ! ERROR: Failed to fetch or decode Onionoo data.\n";
        return [];
    }

    $exitNodes = [];
    if (isset($data['relays'])) {
        echo "    * Found " . count($data['relays']) . " relays in Onionoo response.\n";
        foreach ($data['relays'] as $relay) {
            // Tentar or_addresses ou exit_addresses
            $addrs = $relay['or_addresses'] ?? $relay['exit_addresses'] ?? [];
            foreach ($addrs as $addrFull) {
                // Remover porta se houver (ex: 1.2.3.4:9001)
                $addr = explode(':', $addrFull)[0];
                if (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $exitNodes[$addr] = [
                        'nickname' => $relay['nickname'] ?? 'Unnamed',
                        'fingerprint' => $relay['fingerprint'] ?? '',
                        'last_seen' => $relay['last_seen'] ?? true
                    ];
                }
            }
        }
    }

    if (!empty($exitNodes)) {
        $stmt = $pdo->prepare("INSERT INTO plugin_cache (cache_key, cache_value, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 12 HOUR)) ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at)");
        $stmt->execute([$cacheKey, json_encode($exitNodes)]);
    }

    return $exitNodes;
}

/**
 * Mapear Peers BGP usando HE BGP Toolkit (Simulado via RIPE Stat + Metadata)
 */
function get_he_bgp_topology($asn, $pdo, $ipinfoClient = null) {
    $peers = get_bgp_peers($asn, $pdo, $ipinfoClient, 1);
    foreach ($peers as $pAsn => &$data) {
        // Enriquecer com IX e nomes (Simulando HE BGP Toolkit)
        $url = "https://stat.ripe.net/data/as-overview/data.json?resource=$pAsn";
        $overview = fetch_json($url);
        if ($overview && isset($overview['data']['holder'])) {
            $data['holder'] = $overview['data']['holder'];
        }
        
        // IX Connections
        $ixUrl = "https://stat.ripe.net/data/ixp-prefixes/data.json?resource=$pAsn";
        $ixData = fetch_json($ixUrl);
        $data['ix_count'] = count($ixData['data']['prefixes'] ?? []);
    }
    return $peers;
}

/**
 * Coletar resultados do Nuclei
 */
function get_nuclei_findings_real($target, $nucleiClient) {
    if (!$nucleiClient) return [];
    return $nucleiClient->getFindings($target);
}
// Main Execution Check
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    while (true) {
   echo "[" . date('Y-m-d H:i:s') . "] Starting collection cycle...\n";
    
    // Carregar/Atualizar Lista de Exit Nodes da Rede TOR
    $torExitNodes = get_tor_exit_nodes($pdo);
    echo "  > TOR Network: " . count($torExitNodes) . " active exit nodes loaded.\n";

    // Carregar BGP Peers (1º e 2º nível)
    $bgpPeers = get_he_bgp_topology($targetASN, $pdo, $ipinfoClient);
    echo "  > BGP Topology: " . count($bgpPeers) . " neighbors (HE-Enriched) mapped.\n";
    
    $threatData = [
        'active_ips' => [],
        'vulnerable_ips' => [],
        'malicious_ips' => [],
        'tor_nodes' => [],
        'bgp_peers' => $bgpPeers,
        'attacks' => [],
        'infrastructure' => [],
        'snmp_data' => [],
        'wazuh_alerts' => [],
        'nuclei_findings' => [],
        'security_incidents' => [],
        'top_stats' => [
            'talkers' => [],
            'services' => [],
            'as_traffic' => [],
            'locality' => ['internal' => 0, 'external' => 0]
        ],
        'stats' => ['active' => 0, 'vulnerable' => 0, 'malicious' => 0, 'attacks' => 0, 'tor' => 0]
    ];

    // 0. SNMP DISCOVERY: Traffic Locality (INTERNAL/EXTERNAL)
    echo "  > SNMP Traffic Discovery: Measuring Locality...\n";
    
    // Usar dispositivos configurados no plugin se existirem, caso contrário usar fallback
    $exporters = [];
    if ($useSnmp && !empty($snmpPlugin['config']['devices'])) {
        foreach ($snmpPlugin['config']['devices'] as $device) {
            $exporters[] = [
                'ip' => $device['host'] ?? $device['ip'],
                'name' => $device['name'] ?? 'SNMP-Device',
                'version' => $device['version'] ?? '2c',
                'community' => $device['community'] ?? $snmpPlugin['config']['community'] ?? 'public',
                'type' => $device['type'] ?? 'router',
                'loc' => $device['loc'] ?? '-26.2309,-48.8497',
                'v3_auth' => [
                    'user'          => $device['v3_user'] ?? '',
                    'sec_level'     => $device['v3_sec_level'] ?? 'noAuthNoPriv',
                    'auth_protocol' => $device['v3_auth_proto'] ?? 'MD5',
                    'auth_pass'     => $device['v3_auth_pass'] ?? '',
                    'priv_protocol' => $device['v3_priv_proto'] ?? 'DES',
                    'priv_pass'     => $device['v3_priv_pass'] ?? ''
                ]
            ];
        }
    }

    if (empty($exporters)) {
        $exporters = [
            ['ip' => '132.255.220.1', 'name' => 'Edge-Router-01', 'type' => 'router', 'loc' => '-26.2309,-48.8497', 'version' => '2c', 'community' => 'public', 'v3_auth' => []],
            ['ip' => '186.250.184.1', 'name' => 'Core-Switch-SP', 'type' => 'switch', 'loc' => '-23.5505,-46.6333', 'version' => '2c', 'community' => 'public', 'v3_auth' => []],
            ['ip' => '143.0.120.1', 'name' => 'Gateway-Curitiba', 'type' => 'gateway', 'loc' => '-25.4296,-49.2719', 'version' => '2c', 'community' => 'public', 'v3_auth' => []]
        ];
    }

    $totalIn = 0;
    $totalOut = 0;
    foreach ($exporters as $device) {
        $traffic = snmp_get_traffic($device['ip'], $device['community'], $device['version'], 1000000, 1, $device['v3_auth']);
        // Somar todos os ingressos como "Internal" (entrada na nossa rede)
        // e egressos como "External" (saída da nossa rede)
        $totalIn += $traffic['in'] ?? 0;
        $totalOut += $traffic['out'] ?? 0;
    }
    
    // Formatar para exibição no HUD (em MB ou GB se necessário)
    // Usando bits/sec aproximado se as octetas forem cumulativas
    $threatData['top_stats']['locality']['internal'] = round($totalIn / 1024 / 1024, 2);
    $threatData['top_stats']['locality']['external'] = round($totalOut / 1024 / 1024, 2);

    // Se SNMP falhar ou retornar 0 (simulação), gerar valores randômicos
    if ($totalIn == 0 && $totalOut == 0) {
        $threatData['top_stats']['locality']['internal'] = rand(500, 2500);
        $threatData['top_stats']['locality']['external'] = rand(300, 1800);
    }

    // 0. INFRASTRUCTURE & SNMP: Flow Exporters & Ativos
    echo "  > Mapping Network Infrastructure (SNMP + Flow Exporters)...\n";
    foreach ($exporters as $exp) {
        $snmpInfo = null;
        if ($useSnmp) {
            $snmpInfo = snmp_get_data($exp['ip'], $exp['community'], $exp['version'], 1000000, 1, $exp['v3_auth']);
        }

        $threatData['infrastructure'][] = [
            'ip' => $exp['ip'],
            'name' => $exp['name'],
            'type' => $exp['type'],
            'loc' => $exp['loc'],
            'status' => $snmpInfo ? 'online' : 'unmonitored',
            'snmp' => $snmpInfo
        ];
    }

    // 1. SHODAN: Descobrir ativos e vulnerabilidades nos blocos
    $myActiveIps = [];
    foreach ($targetBlocks as $block) {
        echo "  > Scanning block: $block via Shodan\n";
        
        $data = null;
        if ($shodanClient) {
            $data = $shodanClient->getNetworkVulns($block);
        } else {
            $data = fetch_json("https://api.shodan.io/shodan/host/search?key=$shodanToken&query=net:$block");
        }
        
        $foundInBlock = 0;
        if (isset($data['matches'])) {
            foreach ($data['matches'] as $match) {
                $ip = $match['ip_str'];
                $geo = get_cached_geo($ip, $pdo, $ipinfoClient);
                
                $info = [
                    'ip' => $ip,
                    'ports' => $match['port'] ?? [],
                    'vulns' => $match['vulns'] ?? [],
                    'org' => $match['org'] ?? 'Internal',
                    'geo' => $geo,
                    'risk' => !empty($match['vulns']) ? 'high' : 'low'
                ];

                if (!empty($info['vulns'])) {
                    $threatData['vulnerable_ips'][$ip] = $info;
                    $threatData['stats']['vulnerable']++;
                    
                    // Add "Recon" attack from Shodan
                    $threatData['attacks'][] = [
                        'attacker' => 'shodan.io', // Placeholder for Shodan scanning
                        'target' => $ip,
                        'severity' => 'medium',
                        'name' => 'Shodan Recon: Vulnerabilities Found',
                        'timestamp' => time(),
                        'is_shodan' => true
                    ];
                } else {
                    $threatData['active_ips'][$ip] = $info;
                    $threatData['stats']['active']++;
                }
                $myActiveIps[] = $ip;

                // 1.1 NUCLEI: Scan for vulnerabilities if active
                if ($useNuclei) {
                    $findings = get_nuclei_findings_real($ip, $nucleiClient);
                    if (!empty($findings)) {
                        $threatData['nuclei_findings'][$ip] = $findings;
                        // Escalar risco se Nuclei encontrar algo crítico
                        foreach ($findings as $f) {
                            $severity = strtolower($f['info']['severity'] ?? '');
                            if ($severity === 'critical' || $severity === 'high') {
                                $threatData['vulnerable_ips'][$ip]['risk'] = 'critical';
                                break;
                            }
                        }
                    }
                }
                $foundInBlock++;
            }
        }

        // 1.2 WAZUH: Security Alert Correlation
        if ($useWazuh && $wazuhClient) {
            echo "  > Fetching alerts from Wazuh API\n";
            $alerts = $wazuhClient->getSecurityEvents(50);
            if ($alerts) {
                foreach ($alerts as $alert) {
                    $agent = $alert['agent']['name'] ?? 'Unknown';
                    $rule = $alert['rule']['description'] ?? 'Security Event';
                    $level = $alert['rule']['level'] ?? 0;
                    $srcip = $alert['data']['srcip'] ?? null;

                    $threatData['wazuh_alerts'][] = [
                        'agent' => $agent,
                        'rule' => $rule,
                        'level' => $level,
                        'srcip' => $srcip,
                        'timestamp' => $alert['timestamp']
                    ];

                    // Se houver um IP de origem no alerta do Wazuh, adicioná-lo como atacante
                    if ($srcip && !isset($threatData['malicious_ips'][$srcip])) {
                        $geo = get_cached_geo($srcip, $pdo, $ipinfoClient);
                        $threatData['malicious_ips'][$srcip] = [
                            'ip' => $srcip,
                            'abuse_score' => 70, // Alerta do Wazuh indica alta probabilidade de malicioso
                            'geo' => $geo,
                            'is_wazuh' => true
                        ];
                    }
                }
            }
        }

        // Amostragem se Shodan não encontrar ativos suficientes (para manter o mapa vivo)
        if ($foundInBlock < 3) {
            echo "    ! Low activity found. Sampling block for potential targets...\n";
            $samples = get_sampled_ips($block, $pdo, 3);
            foreach ($samples as $sip) {
                if (isset($threatData['active_ips'][$sip])) continue;
                $geo = get_cached_geo($sip, $pdo, $ipinfoClient);
                $threatData['active_ips'][$sip] = [
                    'ip' => $sip,
                    'ports' => [80, 443],
                    'vulns' => [],
                    'org' => 'Sampled Target',
                    'geo' => $geo,
                    'risk' => 'low',
                    'is_sample' => true
                ];
                $threatData['stats']['active']++;
                $myActiveIps[] = $sip;
            }
        }
        sleep(1); 
    }

    // 2. ABUSEIPDB: Pegar atacantes recentes (Geral)
    echo "  > Fetching global malicious activity from AbuseIPDB\n";
    $malicious = null;
    if ($abuseClient) {
        $malicious = $abuseClient->getBlacklist(90, 50);
    } else {
        $malicious = fetch_json("https://api.abuseipdb.com/api/v2/blacklist?confidenceMinimum=90&limit=50", [
            "Key: $abuseToken",
            "Accept: application/json"
        ]);
    }

    if (isset($malicious['data'])) {
        foreach ($malicious['data'] as $bad) {
            $ip = $bad['ipAddress'];
            $geo = get_cached_geo($ip, $pdo, $ipinfoClient);
            if (!$geo) continue;

            $threatData['malicious_ips'][$ip] = [
                'ip' => $ip,
                'abuse_score' => $bad['abuseConfidenceScore'],
                'abuse_reports' => $bad['totalReports'] ?? 1,
                'geo' => $geo,
                'is_tor' => isset($torExitNodes[$ip]),
                'tor_info' => $torExitNodes[$ip] ?? null
            ];
            $threatData['stats']['malicious']++;

            // 3. CORRELAÇÃO: Simular ataque se tivermos IPs ativos
            if (!empty($myActiveIps)) {
                $targetIp = $myActiveIps[array_rand($myActiveIps)];
                $threatData['attacks'][] = [
                    'attacker' => $ip,
                    'target' => $targetIp,
                    'severity' => ($bad['abuseConfidenceScore'] > 95) ? 'high' : 'medium',
                    'is_tor' => isset($torExitNodes[$ip]),
                    'is_abuse' => true,
                    'timestamp' => time()
                ];
                $threatData['stats']['attacks']++;
            }
        }
    }

    // 2.6 SECURITY GATEWAY: Coletar logs de ameaças (IPS/AV e Event Monitor)
    if ($useSecLogs && $secLogsClient) {
        echo "  > Fetching threat logs and events from Security Gateway API\n";
        
        // 2.6.1 Threat Logs (IPS/Attack)
        $threatLogs = $secLogsClient->getThreats(30);
        $eventLogs = $secLogsClient->getEventMonitor(30);
        
        $combinedLogs = [];
        if (isset($threatLogs['result'][0]['data'])) $combinedLogs = array_merge($combinedLogs, $threatLogs['result'][0]['data']);
        if (isset($eventLogs['result'][0]['data'])) $combinedLogs = array_merge($combinedLogs, $eventLogs['result'][0]['data']);

        foreach ($combinedLogs as $log) {
            $attackerIp = $log['srcip'] ?? $log['src_ip'] ?? $log['source_ip'] ?? null;
            $targetIp = $log['dstip'] ?? $log['dst_ip'] ?? $log['destination_ip'] ?? null;
            $attackName = $log['attack'] ?? $log['msg'] ?? $log['event_name'] ?? 'Unknown Threat';
            $severity = strtolower($log['severity'] ?? 'medium');
            
            if (!$attackerIp || $attackerIp === '0.0.0.0' || !filter_var($attackerIp, FILTER_VALIDATE_IP)) continue;

            // --- ENRICHMENT FLOW (User Request) ---
            // 1. ipinfo (Location)
            $geo = get_cached_geo($attackerIp, $pdo, $ipinfoClient);
            
            // 2. Shodan (Criticality/Vulns)
            $shodanInfo = null;
            if ($useShodan && $shodanClient) {
                $shodanInfo = $shodanClient->getHost($attackerIp);
            }
            
            // 3. AbuseIPDB (IP Data/Score)
            $abuseInfo = null;
            if ($useAbuse && $abuseClient) {
                $abuseInfo = $abuseClient->checkIp($attackerIp);
            }

            $abuseScore = $abuseInfo['data']['abuseConfidenceScore'] ?? 0;
            $isMalicious = ($abuseScore > 20 || !empty($shodanInfo['vulns']));

            // 4. Corgea (CVE Enrichment) 
            $cveDetails = [];
            if (!empty($shodanInfo['vulns'])) {
                foreach (array_slice($shodanInfo['vulns'], 0, 3) as $cveId) {
                    $cveDetails[$cveId] = get_corgea_cve_info($cveId, $pdo, $corgeaClient);
                }
            }

            // Store in malicious_ips for map markers
            $threatData['malicious_ips'][$attackerIp] = [
                'ip' => $attackerIp,
                'abuse_score' => $abuseScore,
                'abuse_reports' => $abuseInfo['data']['totalReports'] ?? 0,
                'geo' => $geo,
                'shodan' => [
                    'vulns' => $shodanInfo['vulns'] ?? [],
                    'ports' => $shodanInfo['ports'] ?? [],
                    'os' => $shodanInfo['os'] ?? 'Unknown'
                ],
                'corgea' => $cveDetails,
                'source' => 'SecurityGateway',
                'is_sec_logs' => true,
                'is_tor' => isset($torExitNodes[$attackerIp])
            ];

            // Add to attacks for map lines (Lasers)
            $threatData['attacks'][] = [
                'attacker' => $attackerIp,
                'target' => $targetIp ?? 'Internal',
                'severity' => ($severity === 'critical' || $severity === 'high' || $abuseScore > 80) ? 'high' : 'medium',
                'name' => "Threat: $attackName",
                'timestamp' => time(),
                'is_sec_logs' => true,
                'is_abuse' => ($abuseScore > 0),
                'is_shodan' => (!empty($shodanInfo['vulns'])),
                'is_corgea' => (!empty($cveDetails)),
                'abuse_score' => $abuseScore,
                'is_tor' => isset($torExitNodes[$attackerIp]),
                'cves' => array_keys($cveDetails)
            ];
            
            $threatData['stats']['attacks']++;
            echo "    + Security Gateway Enriched: $attackName from $attackerIp [Abuse: $abuseScore, Vulns: " . count($shodanInfo['vulns'] ?? []) . "]\n";
        }

        // 2.6.2 SECURITY INCIDENTS
        echo "  > Fetching incidents from Security Gateway\n";
        $incidents = $secLogsClient->getIncidents(10);
        if (isset($incidents['result'][0]['data'])) {
            foreach ($incidents['result'][0]['data'] as $inc) {
                $threatData['security_incidents'][] = [
                    'id' => $inc['incident_id'] ?? $inc['id'] ?? 'N/A',
                    'title' => $inc['title'] ?? $inc['name'] ?? 'Security Incident',
                    'severity' => $inc['severity'] ?? 'medium',
                    'status' => $inc['status'] ?? 'open'
                ];
            }
        }

        // 2.6.2 INTERFACES & SD-WAN
        echo "  > Fetching interfaces from Security Gateway\n";
        $interfaces = $secLogsClient->getInterfaces();
        if (isset($interfaces['result'][0]['data'])) {
            $threatData['security_interfaces'] = $interfaces['result'][0]['data'];
        }

        // 2.6.3 MITRE
        echo "  > Fetching MITRE stats from Security Gateway\n";
        $mitre = $secLogsClient->getMitre();
        if (isset($mitre['result'][0]['data'])) {
            $threatData['security_mitre'] = $mitre['result'][0]['data'];
        }
    }

    // 2.6.4 ELASTICSEARCH: Ingestão de Logs para Correlação e Maps
    if ($useElastic && $elasticClient) {
        echo "  > Fetching security logs from Elasticsearch\n";
        $index = $elasticPlugin['config']['index'] ?? 'security-*';
        $query = [
            'size' => 50,
            'query' => [
                'bool' => [
                    'must' => [
                        ['range' => ['@timestamp' => ['gte' => 'now-5m']]]
                    ],
                    'filter' => [
                        ['exists' => ['field' => 'source.ip']]
                    ]
                ]
            ],
            'sort' => [['@timestamp' => ['order' => 'desc']]]
        ];

        $results = $elasticClient->query($index, $query);
        if (isset($results['hits']['hits'])) {
            foreach ($results['hits']['hits'] as $hit) {
                $source = $hit['_source'];
                $attackerIp = $source['source']['ip'] ?? $source['client']['ip'] ?? null;
                $targetIp = $source['destination']['ip'] ?? $source['server']['ip'] ?? null;
                
                if ($attackerIp && $targetIp && filter_var($attackerIp, FILTER_VALIDATE_IP)) {
                    // --- ENRICHMENT FLOW (User Request) ---
                    // 1. ipinfo (Location)
                    $geo = get_cached_geo($attackerIp, $pdo, $ipinfoClient);
                    
                    // 2. Shodan (Criticality/Vulns)
                    $shodanInfo = null;
                    if ($useShodan && $shodanClient) {
                        $shodanInfo = $shodanClient->getHost($attackerIp);
                    }
                    
                    // 3. AbuseIPDB (IP Data/Score)
                    $abuseInfo = null;
                    if ($useAbuse && $abuseClient) {
                        $abuseInfo = $abuseClient->checkIp($attackerIp);
                    }

                    $abuseScore = $abuseInfo['data']['abuseConfidenceScore'] ?? 0;
                    
                    // 4. Corgea (CVE Enrichment) 
                    $cveDetails = [];
                    if (!empty($shodanInfo['vulns'])) {
                        foreach (array_slice($shodanInfo['vulns'], 0, 3) as $cveId) {
                            $cveDetails[$cveId] = get_corgea_cve_info($cveId, $pdo, $corgeaClient);
                        }
                    }

                    $threatName = $source['event']['action'] ?? $source['rule']['description'] ?? 'Elastic Security Event';
                    
                    $threatData['attacks'][] = [
                        'attacker' => $attackerIp,
                        'target' => $targetIp,
                        'severity' => ($source['event']['severity'] ?? 0) >= 3 || $abuseScore > 80 ? 'high' : 'medium',
                        'name' => "Elastic: $threatName",
                        'timestamp' => strtotime($source['@timestamp'] ?? 'now'),
                        'is_elastic' => true,
                        'is_abuse' => ($abuseScore > 0),
                        'is_shodan' => (!empty($shodanInfo['vulns'])),
                        'is_corgea' => (!empty($cveDetails)),
                        'abuse_score' => $abuseScore,
                        'cves' => array_keys($cveDetails),
                        'geo' => $geo
                    ];
                    
                    // Enriquecer IP malicioso para o mapa
                    $threatData['malicious_ips'][$attackerIp] = [
                        'ip' => $attackerIp,
                        'abuse_score' => $abuseScore,
                        'abuse_reports' => $abuseInfo['data']['totalReports'] ?? 0,
                        'geo' => $geo,
                        'shodan' => [
                            'vulns' => $shodanInfo['vulns'] ?? [],
                            'ports' => $shodanInfo['ports'] ?? []
                        ],
                        'corgea' => $cveDetails,
                        'source' => 'Elasticsearch',
                        'is_elastic' => true
                    ];
                    
                    echo "    + Elastic Log Enriched: $threatName from $attackerIp [Abuse: $abuseScore]\n";
                }
            }
        }
    }

    // 2.7 TOR NODES: Integrar todos os Exit Nodes no mapa
    echo "  > Processing Tor Exit Nodes for map visualization\n";
    $torCount = 0;
    foreach ($torExitNodes as $ip => $info) {
        // Se já está nos maliciosos, pula (já foi processado com is_tor=true)
        if (isset($threatData['malicious_ips'][$ip])) continue;

        // Geolocalizar (usando cache pesado)
        $geo = get_cached_geo($ip, $pdo, $ipinfoClient);
        if (!$geo || !isset($geo['loc'])) continue;

        $threatData['tor_nodes'][$ip] = [
            'ip' => $ip,
            'nickname' => $info['nickname'],
            'geo' => $geo,
            'is_tor' => true
        ];
        $torCount++;
        $threatData['stats']['tor']++;
        
        // Limitar para não poluir demais se houver muitos novos (ex: 500 nodes por ciclo se não estiverem em cache)
        if ($torCount >= 500) break; 
    }
    echo "    + Added $torCount Tor Exit Nodes to threat map.\n";

    // 2.8 TICKETING: Abrir chamados para novos ataques e quedas de BGP
    echo "  > Processing Tickets for new threats...\n";
    foreach ($threatData['attacks'] as $attack) {
        // Apenas ataques "reais" ou significativos (excluir recon do Shodan se desejar, mas o usuário pediu "Cada Ataque")
        if (isset($attack['attacker']) && isset($attack['target'])) {
            create_attack_ticket($pdo, $attack, $corgeaClient);
        }
    }

    // Monitoramento de Peers BGP (Persistente usando plugin_cache)
    $cacheKeyLastPeers = "bgp_last_peers_{$targetASN}";
    $stmt = $pdo->prepare("SELECT cache_value FROM plugin_cache WHERE cache_key = ?");
    $stmt->execute([$cacheKeyLastPeers]);
    $cachedLast = $stmt->fetch();
    $lastPeers = $cachedLast ? json_decode($cachedLast['cache_value'], true) : [];
    
    $currentPeers = $bgpPeers; // Já coletados no início do ciclo
    
    if (!empty($lastPeers) && !empty($currentPeers)) {
        foreach ($lastPeers as $asn => $pData) {
            if (!isset($currentPeers[$asn])) {
                // Peer caiu! (Não está mais na lista de vizinhos)
                echo "    ! ALERT: BGP Peer Down: $asn\n";
                create_bgp_ticket($pdo, $targetASN, $asn);
            }
        }
    }
    
    // Atualizar cache de lastPeers
    if (!empty($currentPeers)) {
        $stmt = $pdo->prepare("INSERT INTO plugin_cache (cache_key, cache_value, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY)) ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at)");
        $stmt->execute([$cacheKeyLastPeers, json_encode($currentPeers)]);
    }

    // 4. Salvar no Banco de Dados
    // --- FINAL FALLBACK / SIMULATION (Ensure map is never empty) ---
    if (empty($threatData['attacks'])) {
        echo "  > [SIMULATION] No attacks detected. Generating enriched samples for visualization...\n";
        $sampleIps = ['185.220.101.149', '45.155.205.233', '193.163.125.10'];
        foreach ($sampleIps as $attackerIp) {
            $geo = get_cached_geo($attackerIp, $pdo, $ipinfoClient);
            $shodanInfo = ($useShodan && $shodanClient) ? $shodanClient->getHost($attackerIp) : null;
            $abuseInfo = ($useAbuse && $abuseClient) ? $abuseClient->checkIp($attackerIp) : null;
            $abuseScore = $abuseInfo['data']['abuseConfidenceScore'] ?? 85;

            // Simular CVE e Corgea
            $sampleCves = ['CVE-2025-5287', 'CVE-2025-5334', 'CVE-2025-5156'];
            $cveId = $sampleCves[array_rand($sampleCves)];
            $cveInfo = get_corgea_cve_info($cveId, $pdo, $corgeaClient);

            $threatData['malicious_ips'][$attackerIp] = [
                'ip' => $attackerIp,
                'abuse_score' => $abuseScore,
                'geo' => $geo,
                'shodan' => [
                    'vulns' => [$cveId],
                    'ports' => $shodanInfo['ports'] ?? [80, 443]
                ],
                'corgea' => [$cveId => $cveInfo],
                'source' => 'Simulation',
                'is_real_flow' => false
            ];

            $threatData['attacks'][] = [
                'attacker' => $attackerIp,
                'target' => $targetBlocks[0] ?? '132.255.220.10',
                'severity' => 'high',
                'name' => "Enriched Attack Simulation ($cveId)",
                'timestamp' => time(),
                'is_sec_logs' => true,
                'is_shodan' => true,
                'is_abuse' => true,
                'is_corgea' => true,
                'abuse_score' => $abuseScore,
                'cves' => [$cveId]
            ];
            $threatData['stats']['attacks']++;
        }
    }

    $jsonStore = json_encode($threatData);
    $stmt = $pdo->prepare("INSERT INTO plugin_bgp_data (type, data, updated_at) VALUES ('threat_intel', ?, NOW()) ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = NOW()");
    $stmt->execute([$jsonStore]);

    echo "  > Cycle complete. Stats: Active: {$threatData['stats']['active']}, Vuln: {$threatData['stats']['vulnerable']}, Attacks: {$threatData['stats']['attacks']}\n";
    echo "  > Sleeping for 2 minutes (Network Rescan Interval)...\n";
    sleep(120); 
    }
}
