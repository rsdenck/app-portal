<?php
// Increase memory limit for large Zabbix reports
ini_set('memory_limit', '512M');


function zbx_rpc(array $config, string $method, array $params = [], ?string $auth = null, int $cacheTtl = 300)
{
    global $pdo;

    // Cacheable methods for Zabbix
    $cacheable = ['host.get', 'item.get', 'trigger.get', 'problem.get', 'history.get', 'trend.get', 'event.get'];
    $cacheKey = '';
    if (in_array($method, $cacheable) && isset($pdo)) {
        $cacheKey = 'zbx_api_' . md5($method . json_encode($params) . (string)$auth);
        $cached = plugin_cache_get($pdo, $cacheKey);
        if ($cached !== null) {
            return $cached;
        }
    }

    $payload = [
        'jsonrpc' => '2.0',
        'method' => $method,
        'params' => $params,
        'id' => random_int(1, 1000000),
    ];
    if ($auth !== null && $auth !== '') {
        $payload['auth'] = $auth;
    }

    $url = (string)($config['zabbix']['url'] ?? '');
    if ($url === '') {
        throw new RuntimeException('Zabbix URL not configured');
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('URL da API do Zabbix inválida: ' . $url);
    }

    $ignoreSsl = true; // Force ignore SSL for self-signed certificates as requested
    
    $raw = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json-rpc'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => (int)($config['zabbix']['timeout_seconds'] ?? 15),
        ]);

        if ($ignoreSsl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            // Explicitly handle self-signed certificates in chain
            curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, false);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            throw new RuntimeException('Zabbix request failed: ' . $err);
        }

        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('Zabbix HTTP error: ' . $httpCode);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json-rpc\r\n",
                'content' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'timeout' => (int)($config['zabbix']['timeout_seconds'] ?? 15),
            ],
            'ssl' => [
                'verify_peer' => !$ignoreSsl,
                'verify_peer_name' => !$ignoreSsl,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            $err = error_get_last();
            $msg = is_array($err) && isset($err['message']) ? (string)$err['message'] : 'unknown error';
            throw new RuntimeException('Zabbix request failed (stream): ' . $msg);
        }
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid Zabbix response');
    }
    if (isset($decoded['error'])) {
        $msg = is_array($decoded['error']) ? json_encode($decoded['error'], JSON_UNESCAPED_SLASHES) : (string)$decoded['error'];
        throw new RuntimeException('Zabbix API error: ' . $msg);
    }

    $result = $decoded['result'] ?? [];

    if ($cacheKey && isset($pdo) && !empty($result)) {
        plugin_cache_set($pdo, $cacheKey, $result, $cacheTtl);
    }

    return $result;
}

function zbx_auth(array $config): string
{
    $token = (string)($config['zabbix']['token'] ?? '');
    if ($token !== '') {
        return $token;
    }
    $user = (string)($config['zabbix']['user'] ?? '');
    $pass = (string)($config['zabbix']['pass'] ?? '');
    if ($user === '' || $pass === '') {
        throw new RuntimeException('Zabbix credentials not configured');
    }
    $result = zbx_rpc($config, 'user.login', ['username' => $user, 'password' => $pass], null);
    if (!is_string($result) || $result === '') {
        throw new RuntimeException('Zabbix auth failed');
    }
    return $result;
}

/**
 * Fetch trend data for multiple item IDs
 */
function zbx_get_trends(array $config, string $auth, array $itemIds, int $timeFrom, int $timeTill): array
{
    if (empty($itemIds)) return [];
    
    // Chunk requests to avoid Zabbix API limits or timeouts (and memory issues)
    $chunks = array_chunk($itemIds, 100);
    $allTrends = [];
    
    foreach ($chunks as $chunk) {
        $result = zbx_rpc($config, 'trend.get', [
            'itemids' => $chunk,
            'time_from' => $timeFrom,
            'time_till' => $timeTill,
            'output' => ['itemid', 'clock', 'num', 'value_min', 'value_avg', 'value_max'],
            'sortfield' => 'clock',
            'sortorder' => 'ASC'
        ], $auth);
        
        if (is_array($result)) {
            $allTrends = array_merge($allTrends, $result);
        }
    }
    
    return $allTrends;
}

/**
 * Fetch active triggers/problems
 */
function zbx_get_active_alerts(array $config, string $auth, array $hostIds = []): array
{
    $params = [
        'output' => ['triggerid', 'description', 'priority', 'lastchange'],
        'filter' => ['value' => 1, 'status' => 0], // active and enabled
        'sortfield' => 'priority',
        'sortorder' => 'DESC',
        'expandDescription' => true,
        'selectHosts' => ['hostid', 'name'],
        'monitored' => true,
        'skipDependent' => true
    ];
    if (!empty($hostIds)) {
        $params['hostids'] = $hostIds;
    }
    // Limit active alerts to prevent memory exhaustion
    $params['limit'] = 2000;
    
    return zbx_rpc($config, 'trigger.get', $params, $auth);
}

/**
 * Fetch alert counts for a trend (last 7 days by default)
 */
function zbx_get_alerts_trend(array $config, string $auth, array $hostIds = [], int $days = 7): array
{
    $timeFrom = time() - ($days * 86400);
    $params = [
        'output' => ['eventid', 'clock'],
        'time_from' => $timeFrom,
        'sortfield' => 'eventid',
        'sortorder' => 'ASC'
    ];
    if (!empty($hostIds)) {
        $params['hostids'] = $hostIds;
    }
    // We use problem.get for current/recent problems (limit output to save memory)
    // If there are too many problems, it will crash memory.
    // Let's add limit to params
    $params['limit'] = 5000;
    
    $problems = zbx_rpc($config, 'problem.get', $params, $auth);
    
    $trend = array_fill(0, $days, 0);
    $now = time();
    foreach ($problems as $p) {
        $dayIdx = (int)floor(($now - (int)$p['clock']) / 86400);
        if ($dayIdx >= 0 && $dayIdx < $days) {
            $trend[($days - 1) - $dayIdx]++;
        }
    }
    return $trend;
}

function zbx_config_from_db(PDO $pdo, array $config): array
{
    $row = [];
    if (function_exists('zbx_settings_get')) {
        $row = zbx_settings_get($pdo);
    } else {
        try {
            $stmt = $pdo->prepare('SELECT url, username, password, ignore_ssl FROM zabbix_settings WHERE id = 1 LIMIT 1');
            $stmt->execute();
            $fetch = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($fetch)) {
                $row = $fetch;
            }
        } catch (Throwable $e) {
            $row = [];
        }
    }

    if (is_array($row) && $row) {
        $url = trim((string)($row['url'] ?? ''));
        $user = trim((string)($row['username'] ?? ''));
        $pass = (string)($row['password'] ?? '');
        if ($url !== '') {
            $config['zabbix']['url'] = $url;
        }
        if ($user !== '') {
            $config['zabbix']['user'] = $user;
        }
        if ($pass !== '') {
            $config['zabbix']['pass'] = $pass;
        }
        $config['zabbix']['ignore_ssl'] = (int)($row['ignore_ssl'] ?? 0) === 1;
    }

    return $config;
}
