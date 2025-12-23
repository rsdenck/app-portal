<?php
require_once __DIR__ . '/../includes/bootstrap.php';

echo "=== IPFLOW API VALIDATION TEST ===\n";

// 1. Carregar configurações do banco de dados
$ipflowPlugin = plugin_get_by_name($pdo, 'ipflow');
$bgpPlugin = plugin_get_by_name($pdo, 'bgpview');

if (!$ipflowPlugin || !$ipflowPlugin['is_active']) {
    die("ERRO: Plugin IPflow não está ativo no painel.\n");
}

$clientId = $ipflowPlugin['config']['client_id'] ?? '';
$clientSecret = $ipflowPlugin['config']['client_secret'] ?? '';
$baseUrl = $ipflowPlugin['config']['url'] ?? 'https://api.ipflow.com';
$targetASN = $bgpPlugin['config']['my_asn'] ?? "262978";
$ipBlocksRaw = $bgpPlugin['config']['ip_blocks'] ?? "132.255.220.0/22, 186.250.184.0/22, 143.0.120.0/22";
$targetBlocks = array_filter(array_map('trim', explode(',', $ipBlocksRaw)));

echo "> Client ID: " . substr($clientId, 0, 10) . "...\n";
echo "> Base URL: $baseUrl\n";
echo "> Target ASN: $targetASN\n";
echo "> Target Blocks: " . implode(', ', $targetBlocks) . "\n\n";

function test_fetch_json($url, $headers = [], $postData = null) {
    echo "  [HTTP] Requesting: $url\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        $fields = is_array($postData) ? http_build_query($postData) : $postData;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        // echo "  [HTTP] Post Data: $fields\n";
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "  [HTTP] Response Code: $httpCode\n";
    if ($error) echo "  [HTTP] CURL Error: $error\n";
    
    return [
        'code' => $httpCode,
        'body' => json_decode($res, true),
        'raw' => $res
    ];
}

// ETAPA 1: Obter Token
echo "--- STEP 1: AUTHENTICATION ---\n";
// Tentando endpoint v1.0 como sugerido pela documentação comum da IPflow
$authRes = test_fetch_json("$baseUrl/v1.0/oauth/token", 
    ['Content-Type: application/x-www-form-urlencoded'], 
    [
        'grant_type'    => 'client_credentials',
        'client_id'     => $clientId,
        'client_secret' => $clientSecret
    ]
);

if (!isset($authRes['body']['access_token'])) {
    echo "AVISO: Falha no endpoint v1.0. Tentando endpoint sem versão...\n";
    $authRes = test_fetch_json("$baseUrl/oauth/token", 
        ['Content-Type: application/x-www-form-urlencoded'], 
        [
            'grant_type'    => 'client_credentials',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret
        ]
    );
}

if (!isset($authRes['body']['access_token'])) {
    echo "AVISO: Falha no endpoint sem versão. Tentando endpoint v1...\n";
    $authRes = test_fetch_json("$baseUrl/v1/oauth/token", 
        ['Content-Type: application/x-www-form-urlencoded'], 
        [
            'grant_type'    => 'client_credentials',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret
        ]
    );
}

if (!isset($authRes['body']['access_token'])) {
    echo "ERRO: Falha na autenticação.\n";
    echo "Resposta Raw: " . $authRes['raw'] . "\n";
    exit;
}

$token = $authRes['body']['access_token'];
echo "SUCCESS: Token obtido com sucesso!\n\n";

// ETAPA 2: Consultar Fluxos
echo "--- STEP 2: FETCHING FLOWS FOR ASN $targetASN ---\n";
$asnInt = (int)str_ireplace('AS', '', $targetASN);
$payload = json_encode([
    'asn'       => $asnInt,
    'cidrs'     => $targetBlocks,
    'direction' => 'inbound',
    'limit'     => 10
]);

$flowRes = test_fetch_json("$baseUrl/v1/flows/search", [
    "Authorization: Bearer {$token}",
    "Content-Type: application/json"
], $payload);

if ($flowRes['code'] === 200 && isset($flowRes['body']['data'])) {
    $count = count($flowRes['body']['data']);
    echo "SUCCESS: Foram encontrados $count fluxos ativos.\n";
    
    foreach ($flowRes['body']['data'] as $idx => $flow) {
        echo "  [$idx] " . ($flow['src_ip'] ?? 'UNK') . " -> " . ($flow['dst_ip'] ?? 'UNK') . " (Port: " . ($flow['dst_port'] ?? 'N/A') . ")\n";
    }
} else {
    echo "AVISO: Nenhum fluxo retornado ou erro na consulta.\n";
    echo "Resposta Raw: " . $flowRes['raw'] . "\n";
}

echo "\n=== TEST FINISHED ===\n";
