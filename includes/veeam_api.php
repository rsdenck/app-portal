<?php

/**
 * Veeam Backup & Replication & VCSP API Integration (Multi-Server)
 */

function veeam_api_request(array $server, string $endpoint, string $method = 'GET', $body = null, int $cacheTtl = 300)
{
    global $pdo;

    $url = rtrim($server['url'] ?? '', '/');
    if (!$url) return null;

    $type = $server['type'] ?? 'vbr';

    // Auto-fix URL for VBR if /api is missing
    if ($type === 'vbr' && !str_contains($url, '/api')) {
        $url .= '/api';
    }

    $cacheKey = 'veeam_api_' . md5($url . $endpoint . $method . json_encode($body));
    if ($method === 'GET' && $cacheTtl > 0) {
        $cached = plugin_cache_get($pdo, $cacheKey);
        if ($cached !== null) return $cached;
    }

    $token = ($type === 'vcsp') ? veeam_get_vcsp_token($server) : veeam_get_session_token($server);
    if (!$token) {
        return null;
    }
    
    $fullUrl = $url . $endpoint;
    $ch = curl_init($fullUrl);
    $headers = [
        'Accept: application/json'
    ];

    if ($type === 'vcsp') {
        $headers[] = 'Authorization: Bearer ' . $token;
    } else {
        $headers[] = 'X-RestSvcSessionId: ' . $token;
    }

    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 20, // Increased timeout for large environments
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 401) {
        plugin_cache_delete($pdo, ($type === 'vcsp' ? 'veeam_vcsp_token_' : 'veeam_token_') . md5($url));
        return veeam_api_request($server, $endpoint, $method, $body, $cacheTtl);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        // Log error for debugging if needed
        return null;
    }

    $data = json_decode($response, true);
    if ($method === 'GET' && $cacheTtl > 0) {
        plugin_cache_set($pdo, $cacheKey, $data, $cacheTtl);
    }

    return $data;
}

function veeam_get_vcsp_token(array $server)
{
    global $pdo;
    $url = rtrim($server['url'] ?? '', '/');
    // For VCSP, the /token endpoint is usually NOT under /api
    $loginUrl = $url . '/token';
    $cacheKey = 'veeam_vcsp_token_' . md5($url);

    $cachedToken = plugin_cache_get($pdo, $cacheKey);
    if ($cachedToken) return $cachedToken;

    $user = $server['username'] ?? '';
    $pass = $server['password'] ?? '';

    $ch = curl_init($loginUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'password',
            'username' => $user,
            'password' => $pass
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($response, true);
        if (!empty($data['access_token'])) {
            $token = $data['access_token'];
            $expires = (int)($data['expires_in'] ?? 3600) - 60;
            plugin_cache_set($pdo, $cacheKey, $token, $expires);
            return $token;
        }
    }

    return null;
}

function veeam_get_session_token(array $server)
{
    global $pdo;
    $url = rtrim($server['url'] ?? '', '/');
    
    // VBR Login is usually at /api/sessionMngr or /sessionMngr
    // We try to find where it is
    $loginUrl = $url . '/sessionMngr/';
    if (!str_contains($url, '/api')) {
        $loginUrl = $url . '/api/sessionMngr/';
    }

    $cacheKey = 'veeam_token_' . md5($url);
    $cachedToken = plugin_cache_get($pdo, $cacheKey);
    if ($cachedToken) return $cachedToken;

    $user = $server['username'] ?? '';
    $pass = $server['password'] ?? '';

    $ch = curl_init($loginUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode("$user:$pass"),
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        if (preg_match('/X-RestSvcSessionId:\s*([^\r\n]+)/i', $headers, $matches)) {
            $token = trim($matches[1]);
            plugin_cache_set($pdo, $cacheKey, $token, 3500);
            return $token;
        }
    }

    return null;
}

function veeam_get_consolidated_stats(array $plugin)
{
    $servers = $plugin['config']['servers'] ?? [];
    $totalStats = [
        'TotalJobCount' => 0,
        'SuccessfulJobCount' => 0,
        'FailedJobCount' => 0,
        'RunningJobCount' => 0,
        'WarningJobCount' => 0,
        'ServerCount' => count($servers),
        'Errors' => []
    ];

    foreach ($servers as $srv) {
        try {
            $type = $srv['type'] ?? 'vbr';
            
            if ($type === 'vcsp') {
                // VCSP Dashboard stats
                $data = veeam_api_request($srv, '/infrastructure/backupServers/statistics');
                if ($data) {
                    // VCSP API v3 returns a different structure
                    // This is a simplification, actual VCSP API might need more specific parsing
                    $totalStats['TotalJobCount'] += ($data['totalJobs'] ?? 0);
                    $totalStats['SuccessfulJobCount'] += ($data['successfulJobs'] ?? 0);
                    $totalStats['FailedJobCount'] += ($data['failedJobs'] ?? 0);
                    $totalStats['WarningJobCount'] += ($data['warningJobs'] ?? 0);
                    $totalStats['RunningJobCount'] += ($data['runningJobs'] ?? 0);
                } else {
                    $totalStats['Errors'][] = "Falha (VCSP): " . ($srv['label'] ?: $srv['url']);
                }
            } else {
                // VBR (Enterprise Manager) stats
                $stats = veeam_api_request($srv, '/reports/summary/job_statistics');
                if (isset($stats['JobStatistics'])) {
                    $s = $stats['JobStatistics'];
                    $totalStats['TotalJobCount'] += (int)($s['TotalJobCount'] ?? 0);
                    $totalStats['SuccessfulJobCount'] += (int)($s['SuccessfulJobCount'] ?? 0);
                    $totalStats['FailedJobCount'] += (int)($s['FailedJobCount'] ?? 0);
                    $totalStats['RunningJobCount'] += (int)($s['RunningJobCount'] ?? 0);
                    $totalStats['WarningJobCount'] += (int)($s['WarningJobCount'] ?? 0);
                } else {
                    $totalStats['Errors'][] = "Falha (VBR): " . ($srv['label'] ?: $srv['url']);
                }
            }
        } catch (Exception $e) {
            $totalStats['Errors'][] = $srv['label'] . ": " . $e->getMessage();
        }
    }

    return $totalStats;
}

function veeam_get_all_repositories(array $plugin)
{
    $servers = $plugin['config']['servers'] ?? [];
    $allRepos = [];

    foreach ($servers as $srv) {
        $type = $srv['type'] ?? 'vbr';
        $endpoint = ($type === 'vcsp') ? '/infrastructure/backupServers/repositories' : '/repositories';
        
        $data = veeam_api_request($srv, $endpoint);
        
        if ($type === 'vcsp' && isset($data['data'])) {
            foreach ($data['data'] as $repo) {
                $allRepos[] = [
                    'ServerLabel' => $srv['label'] ?: $srv['url'],
                    'Name' => $repo['name'] ?? 'N/A',
                    'Capacity' => ($repo['capacityBytes'] ?? 0),
                    'FreeSpace' => ($repo['freeSpaceBytes'] ?? 0),
                ];
            }
        } elseif (isset($data['Repositories'])) {
            foreach ($data['Repositories'] as $repo) {
                $allRepos[] = [
                    'ServerLabel' => $srv['label'] ?: $srv['url'],
                    'Name' => $repo['Name'] ?? 'N/A',
                    'Capacity' => ($repo['Capacity'] ?? 0),
                    'FreeSpace' => ($repo['FreeSpace'] ?? 0),
                ];
            }
        }
    }
    return $allRepos;
}

function veeam_get_all_jobs(array $plugin, int $limit = 0)
{
    $servers = $plugin['config']['servers'] ?? [];
    $allJobs = [];

    foreach ($servers as $srv) {
        $type = $srv['type'] ?? 'vbr';
        
        if ($type === 'vcsp') {
            // VCSP API v3 often supports pagination/limit
            $endpoint = '/backup/jobs';
            if ($limit > 0) {
                $endpoint .= '?limit=' . $limit;
            }
            $data = veeam_api_request($srv, $endpoint);
            if (isset($data['data'])) {
                foreach ($data['data'] as $job) {
                    $allJobs[] = [
                        'ServerLabel' => $srv['label'] ?: $srv['url'],
                        'Name' => $job['name'] ?? 'N/A',
                        'LastExecutionStatus' => $job['status'] ?? 'Unknown',
                        'LastRun' => $job['lastRunTime'] ?? null,
                        'NextRun' => $job['nextRunTime'] ?? null,
                    ];
                    if ($limit > 0 && count($allJobs) >= $limit) break 2;
                }
            }
        } else {
            // VBR (Enterprise Manager) /jobs
            $data = veeam_api_request($srv, '/jobs');
            if (isset($data['Jobs'])) {
                foreach ($data['Jobs'] as $job) {
                    $allJobs[] = [
                        'ServerLabel' => $srv['label'] ?: $srv['url'],
                        'Name' => $job['Name'] ?? 'N/A',
                        'LastExecutionStatus' => $job['LastExecutionStatus'] ?? 'Unknown',
                        'LastRun' => $job['LastRun'] ?? null,
                        'NextRun' => $job['NextRun'] ?? null,
                    ];
                    if ($limit > 0 && count($allJobs) >= $limit) break 2;
                }
            }
        }
    }
    return $allJobs;
}

