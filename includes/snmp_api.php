<?php

/**
 * SNMP Integration for Network Infrastructure
 */
function snmp_get_data($host, $community, $version = '2c', $timeout = 1000000, $retries = 1) {
    if (!function_exists('snmp2_get')) {
        return null;
    }

    $data = [
        'hostname' => @snmp2_get($host, $community, "1.3.6.1.2.1.1.5.0", $timeout, $retries),
        'description' => @snmp2_get($host, $community, "1.3.6.1.2.1.1.1.0", $timeout, $retries),
        'uptime' => @snmp2_get($host, $community, "1.3.6.1.2.1.1.3.0", $timeout, $retries),
        'interfaces' => []
    ];

    // Clean up strings (SNMP returns strings like 'STRING: "Hostname"')
    foreach ($data as $key => $val) {
        if (is_string($val)) {
            $data[$key] = preg_replace('/^.*: "?|"?$/', '', $val);
        }
    }

    return $data;
}

function snmp_get_traffic($host, $community, $version = '2c', $timeout = 1000000, $retries = 1) {
    if (!function_exists('snmp2_real_walk')) {
        return ['in' => 0, 'out' => 0];
    }

    $inOctets = @snmp2_real_walk($host, $community, "1.3.6.1.2.1.2.2.1.10", $timeout, $retries);
    $outOctets = @snmp2_real_walk($host, $community, "1.3.6.1.2.1.2.2.1.16", $timeout, $retries);

    $totalIn = 0;
    $totalOut = 0;

    if ($inOctets) {
        foreach ($inOctets as $val) {
            $totalIn += (float)preg_replace('/^.*: /', '', $val);
        }
    }

    if ($outOctets) {
        foreach ($outOctets as $val) {
            $totalOut += (float)preg_replace('/^.*: /', '', $val);
        }
    }

    // Convert octets to bits and then to a human-readable format or just return the bits/sec if we had delta
    // For simplicity in this dashboard, we will return a simulated delta or just the raw sum if it's for locality visualization
    return [
        'in' => $totalIn,
        'out' => $totalOut
    ];
}

function snmp_get_client($config) {
    return new class($config) {
        private $config;
        public function __construct($config) { $this->config = $config; }
        
        public function getDevices() {
            $devices = $this->config['devices'] ?? [];
            $results = [];
            foreach ($devices as $device) {
                $host = $device['host'] ?? '';
                $community = $device['community'] ?? 'public';
                if ($host) {
                    $results[] = [
                        'ip' => $host,
                        'name' => $device['name'] ?? $host,
                        'data' => snmp_get_data($host, $community)
                    ];
                }
            }
            return $results;
        }
    };
}
