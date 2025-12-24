<?php

/**
 * IPinfo API Client
 * Supports single and batch IP lookups
 */
function ipinfo_get_client($config) {
    return new IpinfoClient($config['password'] ?? '');
}

class IpinfoClient {
    private $token;
    private $baseUrl = 'https://ipinfo.io';

    public function __construct($token = '') {
        $this->token = $token;
    }

    /**
     * Get details for a single IP
     */
    public function getDetails($ip) {
        $url = "{$this->baseUrl}/{$ip}" . ($this->token ? "?token={$this->token}" : "");
        return $this->request($url);
    }

    /**
     * Get details for multiple IPs in a single batch request
     * IPinfo supports up to 1000 IPs per batch request
     */
    public function getBatchDetails($ips) {
        if (empty($this->token)) {
            // Batch requires a token
            $results = [];
            foreach ($ips as $ip) {
                $results[$ip] = $this->getDetails($ip);
            }
            return $results;
        }

        $url = "{$this->baseUrl}/batch?token={$this->token}";
        $payload = json_encode($ips);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            return null;
        }

        return json_decode($response, true);
    }

    private function request($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'CyberThreatMap/1.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            return null;
        }

        return json_decode($response, true);
    }
}

