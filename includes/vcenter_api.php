<?php

/**
 * VMware vCenter API Integration (Multi-Server)
 * Supports both REST (/api) and SOAP (/sdk)
 */

/**
 * Sends a SOAP request to vCenter /sdk
 */
function vcenter_soap_request(array $server, string $method, string $paramsXml, bool $useSession = true)
{
    global $pdo;
    $url = rtrim($server['url'] ?? '', '/');
    if (!$url) return null;

    $sdkUrl = $url . '/sdk';
    
    $cookie = '';
    if ($useSession) {
        $cookie = vcenter_get_soap_cookie($server);
        if (!$cookie) return null;
    }

    $soapAction = 'urn:vim25/' . $method;
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:vim25">
   <soapenv:Header/>
   <soapenv:Body>
      <' . $method . ' xmlns="urn:vim25">
         ' . $paramsXml . '
      </' . $method . '>
   </soapenv:Body>
</soapenv:Envelope>';

    $ch = curl_init($sdkUrl);
    $headers = [
        'Content-Type: text/xml; charset=utf-8',
        'SOAPAction: "' . $soapAction . '"',
    ];

    if ($cookie) {
        $headers[] = 'Cookie: ' . $cookie;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADER => true,
    ]);

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    // curl_close($ch); // Deprecated in PHP 8.5

    if ($curlError) {
        throw new Exception("Erro de conexão SOAP (CURL): " . $curlError);
    }

    if ($httpCode >= 400) {
        if (preg_match('/<faultstring>(.*)<\/faultstring>/', $body, $matches)) {
            throw new Exception("vCenter SOAP Fault: " . $matches[1]);
        }
        throw new Exception("Erro na API SOAP do vCenter (HTTP $httpCode).");
    }

    return $body;
}

/**
 * Authenticates and returns the vmware_soap_session cookie
 */
function vcenter_get_soap_cookie(array $server)
{
    global $pdo;
    $url = rtrim($server['url'] ?? '', '/');
    $cacheKey = 'vcenter_soap_cookie_' . md5($url);

    $cachedCookie = plugin_cache_get($pdo, $cacheKey);
    if ($cachedCookie) return $cachedCookie;

    $user = $server['username'] ?? '';
    $pass = $server['password'] ?? '';

    $loginXml = '<_this type="SessionManager">SessionManager</_this>
                 <userName>' . htmlspecialchars($user) . '</userName>
                 <password>' . htmlspecialchars($pass) . '</password>';

    $sdkUrl = $url . '/sdk';
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:vim25">
   <soapenv:Header/>
   <soapenv:Body>
      <Login xmlns="urn:vim25">
         ' . $loginXml . '
      </Login>
   </soapenv:Body>
</soapenv:Envelope>';

    $ch = curl_init($sdkUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "urn:vim25/Login"',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HEADER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    // curl_close($ch); // Deprecated in PHP 8.5

    if ($httpCode === 200 && preg_match('/vmware_soap_session="?([^;"]+)"?/', $response, $matches)) {
        $cookie = 'vmware_soap_session="' . $matches[1] . '"';
        plugin_cache_set($pdo, $cacheKey, $cookie, 1800);
        return $cookie;
    }

    if ($curlError) {
        throw new Exception("Erro de conexão SOAP Login (CURL): " . $curlError);
    }
    
    if (preg_match('/<faultstring>(.*)<\/faultstring>/', $response, $matches)) {
        throw new Exception("Falha na autenticação SOAP: " . $matches[1]);
    }

    throw new Exception("Falha ao obter sessão SOAP do vCenter (HTTP $httpCode).");
}

/**
 * Gets basic stats and data using SOAP
 */
function vcenter_get_data_soap(array $server)
{
    $scXml = vcenter_soap_request($server, 'RetrieveServiceContent', '<_this type="ServiceInstance">ServiceInstance</_this>');
    
    preg_match('/<rootFolder type="Folder">([^<]+)<\/rootFolder>/', $scXml, $matches);
    $rootFolder = $matches[1] ?? null;
    
    if (!$rootFolder) throw new Exception("Não foi possível encontrar o rootFolder via SOAP.");

    $data = [
        'vms' => [],
        'hosts' => [],
        'clusters' => [],
        'datacenters' => [],
        'datastores' => [],
        'stats' => [
            'total_vms' => 0,
            'running_vms' => 0,
            'total_hosts' => 0,
            'total_datacenters' => 0,
            'total_clusters' => 0,
            'total_datastores' => 0,
        ]
    ];

    // Traversal Spec for comprehensive recursive search
    $traversalSpec = '
        <selectSet xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="TraversalSpec">
            <name>visitFolders</name>
            <type>Folder</type>
            <path>childEntity</path>
            <skip>false</skip>
            <selectSet><name>visitFolders</name></selectSet>
            <selectSet><name>visitDatacenterVM</name></selectSet>
            <selectSet><name>visitDatacenterHost</name></selectSet>
            <selectSet><name>visitDatacenterDatastore</name></selectSet>
            <selectSet><name>visitComputeResource</name></selectSet>
            <selectSet><name>visitClusterComputeResource</name></selectSet>
        </selectSet>
        <selectSet xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="TraversalSpec">
            <name>visitDatacenterVM</name>
            <type>Datacenter</type>
            <path>vmFolder</path>
            <skip>false</skip>
            <selectSet><name>visitFolders</name></selectSet>
        </selectSet>
        <selectSet xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="TraversalSpec">
            <name>visitDatacenterHost</name>
            <type>Datacenter</type>
            <path>hostFolder</path>
            <skip>false</skip>
            <selectSet><name>visitFolders</name></selectSet>
        </selectSet>
        <selectSet xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="TraversalSpec">
            <name>visitDatacenterDatastore</name>
            <type>Datacenter</type>
            <path>datastoreFolder</path>
            <skip>false</skip>
            <selectSet><name>visitFolders</name></selectSet>
        </selectSet>
        <selectSet xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="TraversalSpec">
            <name>visitDatacenter</name>
            <type>Datacenter</type>
            <path>networkFolder</path>
            <skip>false</skip>
            <selectSet><name>visitFolders</name></selectSet>
        </selectSet>
        <selectSet xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="TraversalSpec">
            <name>visitComputeResource</name>
            <type>ComputeResource</type>
            <path>host</path>
            <skip>false</skip>
        </selectSet>
        <selectSet xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="TraversalSpec">
            <name>visitClusterComputeResource</name>
            <type>ClusterComputeResource</type>
            <path>host</path>
            <skip>false</skip>
        </selectSet>';

    // Fetch VMs
    $vmXml = vcenter_soap_request($server, 'RetrievePropertiesEx', '
        <_this type="PropertyCollector">propertyCollector</_this>
        <specSet>
            <propSet>
                <type>VirtualMachine</type>
                <all>false</all>
                <pathSet>name</pathSet>
                <pathSet>runtime.powerState</pathSet>
                <pathSet>config.hardware.numCPU</pathSet>
                <pathSet>config.hardware.memoryMB</pathSet>
            </propSet>
            <objectSet>
                <obj type="Folder">' . $rootFolder . '</obj>
                <skip>false</skip>
                ' . $traversalSpec . '
            </objectSet>
        </specSet>
        <options/>');

    preg_match_all('/<objects([^>]*)>(.*?)<\/objects>/s', $vmXml, $vmObjects);
    foreach ($vmObjects[2] as $vmObj) {
        $vm = [];
        if (preg_match('/<name>name<\/name><val[^>]*>([^<]+)<\/val>/', $vmObj, $m)) $vm['name'] = $m[1];
        if (preg_match('/<name>runtime\.powerState<\/name><val[^>]*>([^<]+)<\/val>/', $vmObj, $m)) $vm['power_state'] = ($m[1] === 'poweredOn' ? 'POWERED_ON' : 'POWERED_OFF');
        if (preg_match('/<name>config\.hardware\.numCPU<\/name><val[^>]*>([^<]+)<\/val>/', $vmObj, $m)) $vm['cpu_count'] = (int)$m[1];
        if (preg_match('/<name>config\.hardware\.memoryMB<\/name><val[^>]*>([^<]+)<\/val>/', $vmObj, $m)) $vm['memory_size_MiB'] = (int)$m[1];
        
        if (isset($vm['name'])) {
            $data['vms'][] = $vm;
            $data['stats']['total_vms']++;
            if (($vm['power_state'] ?? '') === 'POWERED_ON') $data['stats']['running_vms']++;
        }
    }

    // Fetch Hosts
    $hostXml = vcenter_soap_request($server, 'RetrievePropertiesEx', '
        <_this type="PropertyCollector">propertyCollector</_this>
        <specSet>
            <propSet>
                <type>HostSystem</type>
                <all>false</all>
                <pathSet>name</pathSet>
                <pathSet>runtime.connectionState</pathSet>
                <pathSet>runtime.powerState</pathSet>
            </propSet>
            <objectSet>
                <obj type="Folder">' . $rootFolder . '</obj>
                <skip>false</skip>
                ' . $traversalSpec . '
            </objectSet>
        </specSet>
        <options/>');

    preg_match_all('/<objects([^>]*)>(.*?)<\/objects>/s', $hostXml, $hostObjects);
    foreach ($hostObjects[2] as $hostObj) {
        $host = [];
        if (preg_match('/<name>name<\/name><val[^>]*>([^<]+)<\/val>/', $hostObj, $m)) $host['name'] = $m[1];
        if (preg_match('/<name>runtime\.connectionState<\/name><val[^>]*>([^<]+)<\/val>/', $hostObj, $m)) $host['connection_state'] = strtoupper($m[1]);
        if (preg_match('/<name>runtime\.powerState<\/name><val[^>]*>([^<]+)<\/val>/', $hostObj, $m)) $host['power_state'] = strtoupper($m[1]);
        
        if (isset($host['name'])) {
            $data['hosts'][] = $host;
            $data['stats']['total_hosts']++;
        }
    }

    // Fetch Datastores
    $dsXml = vcenter_soap_request($server, 'RetrievePropertiesEx', '
        <_this type="PropertyCollector">propertyCollector</_this>
        <specSet>
            <propSet>
                <type>Datastore</type>
                <all>false</all>
                <pathSet>name</pathSet>
                <pathSet>summary.capacity</pathSet>
                <pathSet>summary.freeSpace</pathSet>
            </propSet>
            <objectSet>
                <obj type="Folder">' . $rootFolder . '</obj>
                <skip>false</skip>
                ' . $traversalSpec . '
            </objectSet>
        </specSet>
        <options/>');

    preg_match_all('/<objects([^>]*)>(.*?)<\/objects>/s', $dsXml, $dsObjects);
    foreach ($dsObjects[2] as $dsObj) {
        $ds = [];
        if (preg_match('/<name>name<\/name><val[^>]*>([^<]+)<\/val>/', $dsObj, $m)) $ds['name'] = $m[1];
        if (preg_match('/<name>summary\.capacity<\/name><val[^>]*>([^<]+)<\/val>/', $dsObj, $m)) $ds['capacity'] = (float)$m[1];
        if (preg_match('/<name>summary\.freeSpace<\/name><val[^>]*>([^<]+)<\/val>/', $dsObj, $m)) $ds['free_space'] = (float)$m[1];
        
        if (isset($ds['name'])) {
            $data['datastores'][] = $ds;
            $data['stats']['total_datastores']++;
        }
    }

    // Fetch Clusters
    $clusterXml = vcenter_soap_request($server, 'RetrievePropertiesEx', '
        <_this type="PropertyCollector">propertyCollector</_this>
        <specSet>
            <propSet>
                <type>ClusterComputeResource</type>
                <all>false</all>
                <pathSet>name</pathSet>
            </propSet>
            <objectSet>
                <obj type="Folder">' . $rootFolder . '</obj>
                <skip>false</skip>
                ' . $traversalSpec . '
            </objectSet>
        </specSet>
        <options/>');

    preg_match_all('/<objects([^>]*)>(.*?)<\/objects>/s', $clusterXml, $clusterObjects);
    foreach ($clusterObjects[2] as $cObj) {
        if (preg_match('/<name>name<\/name><val[^>]*>([^<]+)<\/val>/', $cObj, $m)) {
            $data['clusters'][] = ['name' => $m[1]];
            $data['stats']['total_clusters']++;
        }
    }

    // Fetch Datacenters
    $dcXml = vcenter_soap_request($server, 'RetrievePropertiesEx', '
        <_this type="PropertyCollector">propertyCollector</_this>
        <specSet>
            <propSet>
                <type>Datacenter</type>
                <all>false</all>
                <pathSet>name</pathSet>
            </propSet>
            <objectSet>
                <obj type="Folder">' . $rootFolder . '</obj>
                <skip>false</skip>
                ' . $traversalSpec . '
            </objectSet>
        </specSet>
        <options/>');

    preg_match_all('/<objects([^>]*)>(.*?)<\/objects>/s', $dcXml, $dcObjects);
    foreach ($dcObjects[2] as $dcObj) {
        if (preg_match('/<name>name<\/name><val[^>]*>([^<]+)<\/val>/', $dcObj, $m)) {
            $data['datacenters'][] = ['name' => $m[1]];
            $data['stats']['total_datacenters']++;
        }
    }

    return $data;
}

function vcenter_api_request(array $server, string $endpoint, string $method = 'GET', $body = null, int $cacheTtl = 300)
{
    global $pdo;
    $url = rtrim($server['url'] ?? '', '/');
    if (!$url) return null;

    $cacheKey = 'vcenter_api_' . md5($url . $endpoint . $method . json_encode($body));
    if ($method === 'GET' && $cacheTtl > 0) {
        $cached = plugin_cache_get($pdo, $cacheKey);
        if ($cached !== null) return $cached;
    }

    $token = vcenter_get_session_token($server);
    if (!$token) return null;
    
    $fullUrl = $url . '/api' . $endpoint;
    $ch = curl_init($fullUrl);
    $headers = ['Accept: application/json', 'vmware-api-session-id: ' . $token];

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
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close($ch); // Deprecated in PHP 8.5

    if ($httpCode === 401) {
        plugin_cache_delete($pdo, 'vcenter_token_' . md5($url));
        return vcenter_api_request($server, $endpoint, $method, $body, $cacheTtl);
    }

    $data = json_decode($response, true);
    if ($method === 'GET' && $cacheTtl > 0) {
        plugin_cache_set($pdo, $cacheKey, $data, $cacheTtl);
    }
    return $data;
}

function vcenter_get_session_token(array $server)
{
    global $pdo;
    $url = rtrim($server['url'] ?? '', '/');
    $loginUrl = $url . '/api/session';
    $cacheKey = 'vcenter_token_' . md5($url);

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
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close($ch); // Deprecated in PHP 8.5

    if ($httpCode >= 200 && $httpCode < 300) {
        $token = json_decode($response, true);
        if (is_string($token)) {
            plugin_cache_set($pdo, $cacheKey, $token, 3000);
            return $token;
        }
    }
    return null;
}

function vcenter_get_local_data(PDO $pdo): ?array
{
    $stmt = $pdo->prepare("SELECT data_content, updated_at FROM plugin_vcenter_data WHERE data_type = 'aggregated_full'");
    $stmt->execute();
    $row = $stmt->fetch();
    if ($row) {
        $data = json_decode($row['data_content'], true);
        if ($data) {
            $data['last_update'] = $row['updated_at'];
            return $data;
        }
    }
    return null;
}

function vcenter_get_local_data_status(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT updated_at FROM plugin_vcenter_data WHERE data_type = 'aggregated_full'");
        $row = $stmt->fetch();
        if (!$row) return ['status' => 'DOWN', 'message' => 'Nenhum dado coletado ainda.'];
        $lastUpdate = strtotime($row['updated_at']);
        $diff = time() - $lastUpdate;
        if ($diff < 600) return ['status' => 'UP', 'last_update' => $row['updated_at']];
        if ($diff < 1800) return ['status' => 'WARNING', 'last_update' => $row['updated_at']];
        return ['status' => 'DOWN', 'last_update' => $row['updated_at']];
    } catch (Exception $e) {
        return ['status' => 'ERROR', 'message' => $e->getMessage()];
    }
}
