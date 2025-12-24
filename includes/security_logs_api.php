<?php

/**
 * Security Gateway API Client
 */

function security_logs_get_client(array $config) {
    return new class($config) {
        private $url;
        private $username;
        private $password;
        private $session;

        public function __construct($config) {
            $url = rtrim($config['url'] ?? '', '/');
            if ($url && !str_starts_with($url, 'http')) {
                $url = 'https://' . $url;
            }
            $this->url = $url;
            $this->username = $config['username'] ?? '';
            $this->password = $config['password'] ?? '';
        }

        private function login() {
            $payload = [
                'method' => 'exec',
                'params' => [[
                    'url' => '/sys/login/user',
                    'data' => [
                        'user' => $this->username,
                        'passwd' => $this->password
                    ]
                ]]
            ];
            
            $ch = curl_init($this->url . '/jsonrpc');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $res = curl_exec($ch);
            $data = json_decode($res, true);
            
            if (PHP_VERSION_ID < 80000) {
                curl_close($ch);
            }

            if (isset($data['session'])) {
                $this->session = $data['session'];
                return true;
            }
            return false;
        }

        public function request($url, $data = [], $cacheTtl = 600) {
            global $pdo;

            $cacheKey = '';
            if (isset($pdo)) {
                $cacheKey = 'security_logs_api_' . md5($this->url . $url . $this->username);
                $cached = plugin_cache_get($pdo, $cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }

            if (!$this->session && !$this->login()) return null;

            $payload = [
                'method' => 'get',
                'session' => $this->session,
                'params' => [['url' => $url]]
            ];

            $ch = curl_init($this->url . '/jsonrpc');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            
            $res = curl_exec($ch);
            $decoded = json_decode($res, true);

            if (PHP_VERSION_ID < 80000) {
                curl_close($ch);
            }

            if ($decoded && $cacheKey && isset($pdo)) {
                plugin_cache_set($pdo, $cacheKey, $decoded, $cacheTtl);
            }

            return $decoded;
        }

        public function getLogs($limit = 10) {
            return $this->request('/log/current/traffic', ['limit' => $limit]);
        }

        public function getThreats($limit = 50) {
            return $this->request('/log/current/attack', ['limit' => $limit]);
        }

        public function getIncidents($limit = 50) {
            return $this->request('/event/incident', ['limit' => $limit]);
        }

        public function getEventMonitor($limit = 50) {
            return $this->request('/event/monitor', ['limit' => $limit]);
        }

        public function getMitre() {
            return $this->request('/log/mitre/stats');
        }

        public function getInterfaces() {
            return $this->request('/sys/network/interface');
        }

        public function getStats() {
            return $this->request('/sys/status');
        }
    };
}

function security_logs_get_aggregated_stats(PDO $pdo) {
    $plugin = plugin_get_by_name($pdo, 'fortianalyzer');
    if (!$plugin || !$plugin['is_active']) return null;

    $client = security_logs_get_client($plugin['config']);
    $stats = $client->getStats();
    
    return [
        'status' => $stats['result'][0]['data'] ?? [],
        'active' => true
    ];
}

