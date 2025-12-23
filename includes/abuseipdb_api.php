<?php

/**
 * AbuseIPDB API Client
 */
function abuseipdb_get_client($config) {
    return new AbuseIPDBClient($config['password'] ?? '');
}

class AbuseIPDBClient {
    private $apiKey;
    private $baseUrl = 'https://api.abuseipdb.com/api/v2';

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Check a single IP for reports
     */
    public function checkIp($ip, $maxAgeInDays = 90) {
        $url = "{$this->baseUrl}/check?ipAddress={$ip}&maxAgeInDays={$maxAgeInDays}&verbose=true";
        return $this->request($url);
    }

    /**
     * Get the blacklist
     */
    public function getBlacklist($confidenceMinimum = 90, $limit = 100) {
        $url = "{$this->baseUrl}/blacklist?confidenceMinimum={$confidenceMinimum}&limit={$limit}";
        return $this->request($url);
    }

    /**
     * Report an IP
     */
    public function reportIp($ip, $categories, $comment = '') {
        $url = "{$this->baseUrl}/report";
        $data = [
            'ipAddress' => $ip,
            'categories' => implode(',', $categories),
            'comment' => $comment
        ];
        return $this->request($url, 'POST', $data);
    }

    private function request($url, $method = 'GET', $data = null) {
        if (empty($this->apiKey)) return null;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Key: {$this->apiKey}",
            "Accept: application/json"
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            return null;
        }

        return json_decode($response, true);
    }
}
