<?php
require_once __DIR__ . '/../includes/bootstrap.php';

echo "=== IPFLOW API COMPREHENSIVE TEST ===\n";

$clientId = "D2skCl7ixtUnPML9SMFYBQqnLwNzHy14v8psmR0oubSjqptG";
$clientSecret = "zbb7bGqneInxR6sVrNOLyCTvDIk02xMQDgoSnWRmbHBOCcME6qp7lSYki0WZj32OvbrClQqzGfIEQBNAucdI31PWbXuyLftSHiDLqn4suEcOn81DtMkLdcAtGVvq3DPi";
$personId = "8ABBD3DF0DA5EE52AB965E7F11439FB9";

$baseUrls = [
    "https://api.ipflow.com",
    "https://gtw.apiflow.com.br",
    "https://www.apiflow.com.br",
    "https://www.apiflow.com.br/gateway/$personId"
];

$authEndpoints = [
    "/oauth/token",
    "/v1/oauth/token",
    "/v1.0/oauth/token",
    "/token"
];

function test_request($url, $headers = [], $postData = null) {
    echo "  [TRYING] $url\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        $fields = is_array($postData) ? http_build_query($postData) : $postData;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "  [RESULT] HTTP $httpCode\n";
    return [
        'code' => $httpCode,
        'body' => json_decode($res, true),
        'raw' => $res
    ];
}

foreach ($baseUrls as $baseUrl) {
    echo "\n--- Testing Base: $baseUrl ---\n";
    foreach ($authEndpoints as $endpoint) {
        $url = $baseUrl . $endpoint;
        $res = test_request($url, ['Content-Type: application/x-www-form-urlencoded'], [
            'grant_type'    => 'client_credentials',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret
        ]);
        
        if ($res['code'] == 200 && isset($res['body']['access_token'])) {
            echo "!!! SUCCESS !!! Found valid endpoint: $url\n";
            echo "Token: " . substr($res['body']['access_token'], 0, 15) . "...\n";
            exit(0);
        }
        
        // Try with JSON as well, just in case
        $res = test_request($url, ['Content-Type: application/json'], json_encode([
            'grant_type'    => 'client_credentials',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret
        ]));
        
        if ($res['code'] == 200 && isset($res['body']['access_token'])) {
            echo "!!! SUCCESS (JSON) !!! Found valid endpoint: $url\n";
            echo "Token: " . substr($res['body']['access_token'], 0, 15) . "...\n";
            exit(0);
        }
    }
}

echo "\n--- No endpoint found ---\n";
