<?php

/**
 * SNMP Integration for Network Infrastructure
 * Supports SNMP v1, v2c, and v3
 */

// Initialize MIBDIRS to include DFLOW vendor MIBs
function snmp_init_mibs() {
    $mibsPath = realpath(__DIR__ . '/../mibs');
    if ($mibsPath) {
        // Build MIBDIRS string (recursive search for subfolders)
        $dirs = [$mibsPath];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($mibsPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            if ($file->isDir()) {
                $dirs[] = $file->getRealPath();
            }
        }
        
        $separator = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? ';' : ':';
        $currentMibDirs = getenv('MIBDIRS') ?: '';
        $newMibDirs = implode($separator, $dirs);
        
        if ($currentMibDirs) {
            $newMibDirs .= $separator . $currentMibDirs;
        }
        
        putenv("MIBDIRS=$newMibDirs");
        
        // Attempt to load ALL available MIBs
        if (function_exists('snmp_read_mib')) {
            @snmp_read_mib('ALL');
        }
    }
}

// Auto-initialize MIBs on load
snmp_init_mibs();

/**
 * Get basic SNMP data from a device
 */
function snmp_get_data($host, $community, $version = '2c', $timeout = 1000000, $retries = 1, $v3_auth = []) {
    $version = (string)$version;
    
    $data = [
        'hostname' => null,
        'description' => null,
        'uptime' => null,
        'interfaces' => []
    ];

    $oids = [
        'hostname' => "1.3.6.1.2.1.1.5.0",
        'description' => "1.3.6.1.2.1.1.1.0",
        'uptime' => "1.3.6.1.2.1.1.3.0"
    ];

    foreach ($oids as $key => $oid) {
        $val = null;
        if ($version === '3') {
            if (function_exists('snmp3_get')) {
                $val = @snmp3_get(
                    $host, 
                    $v3_auth['user'] ?? '', 
                    $v3_auth['sec_level'] ?? 'noAuthNoPriv',
                    $v3_auth['auth_protocol'] ?? 'MD5',
                    $v3_auth['auth_pass'] ?? '',
                    $v3_auth['priv_protocol'] ?? 'DES',
                    $v3_auth['priv_pass'] ?? '',
                    $oid,
                    $timeout,
                    $retries
                );
            }
        } elseif ($version === '1') {
            if (function_exists('snmpget')) {
                $val = @snmpget($host, $community, $oid, $timeout, $retries);
            }
        } else { // Default to v2c
            if (function_exists('snmp2_get')) {
                $val = @snmp2_get($host, $community, $oid, $timeout, $retries);
            }
        }

        if ($val !== false && $val !== null) {
            $data[$key] = preg_replace('/^.*: "?|"?$/', '', $val);
        }
    }

    return $data;
}

/**
 * Get traffic stats from a device
 */
function snmp_get_traffic($host, $community, $version = '2c', $timeout = 1000000, $retries = 1, $v3_auth = []) {
    $version = (string)$version;
    $oids = [
        'in' => "1.3.6.1.2.1.2.2.1.10",
        'out' => "1.3.6.1.2.1.2.2.1.16"
    ];

    $results = ['in' => 0, 'out' => 0];

    foreach ($oids as $key => $oid) {
        $walk = null;
        if ($version === '3') {
            if (function_exists('snmp3_real_walk')) {
                $walk = @snmp3_real_walk(
                    $host, 
                    $v3_auth['user'] ?? '', 
                    $v3_auth['sec_level'] ?? 'noAuthNoPriv',
                    $v3_auth['auth_protocol'] ?? 'MD5',
                    $v3_auth['auth_pass'] ?? '',
                    $v3_auth['priv_protocol'] ?? 'DES',
                    $v3_auth['priv_pass'] ?? '',
                    $oid,
                    $timeout,
                    $retries
                );
            }
        } elseif ($version === '1') {
            if (function_exists('snmprealwalk')) {
                $walk = @snmprealwalk($host, $community, $oid, $timeout, $retries);
            }
        } else { // Default to v2c
            if (function_exists('snmp2_real_walk')) {
                $walk = @snmp2_real_walk($host, $community, $oid, $timeout, $retries);
            }
        }

        if ($walk) {
            $total = 0;
            foreach ($walk as $val) {
                $total += (float)preg_replace('/^.*: /', '', $val);
            }
            $results[$key] = $total;
        }
    }

    return $results;
}

/**
 * Factory for SNMP Client
 */
function snmp_get_client($config) {
    return new class($config) {
        private $config;
        public function __construct($config) { $this->config = $config; }
        
        public function getDevices() {
            $devices = $this->config['devices'] ?? [];
            $results = [];
            foreach ($devices as $device) {
                $host = $device['host'] ?? '';
                $version = $device['version'] ?? '2c';
                $community = $device['community'] ?? 'public';
                
                $v3_auth = [
                    'user'          => $device['v3_user'] ?? '',
                    'sec_level'     => $device['v3_sec_level'] ?? 'noAuthNoPriv',
                    'auth_protocol' => $device['v3_auth_proto'] ?? 'MD5',
                    'auth_pass'     => $device['v3_auth_pass'] ?? '',
                    'priv_protocol' => $device['v3_priv_proto'] ?? 'DES',
                    'priv_pass'     => $device['v3_priv_pass'] ?? ''
                ];

                if ($host) {
                    $results[] = [
                        'ip' => $host,
                        'name' => $device['name'] ?? $host,
                        'version' => $version,
                        'data' => snmp_get_data($host, $community, $version, 1000000, 1, $v3_auth)
                    ];
                }
            }
            return $results;
        }
    };
}
