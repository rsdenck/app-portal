<?php

/**
 * Shodan API Client
 */
function shodan_get_client($config) {
    return new ShodanClient($config['password'] ?? '');
}

class ShodanClient {
    private $apiKey;
    private $baseUrl = 'https://api.shodan.io';

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Search Shodan
     */
    public function search($query, $page = 1) {
        $url = "{$this->baseUrl}/shodan/host/search?key={$this->apiKey}&query=" . urlencode($query) . "&page={$page}";
        return $this->request($url);
    }

    /**
     * Get host information
     */
    public function getHost($ip) {
        $url = "{$this->baseUrl}/shodan/host/{$ip}?key={$this->apiKey}";
        return $this->request($url);
    }

    /**
     * Get account info
     */
    public function getAccountInfo() {
        $url = "{$this->baseUrl}/account/profile?key={$this->apiKey}";
        return $this->request($url);
    }

    /**
     * Get vulnerability information for a network
     */
    public function getNetworkVulns($cidr) {
        return $this->search("net:{$cidr}");
    }

    private function request($url) {
        if (empty($this->apiKey)) return null;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            return null;
        }

        return json_decode($response, true);
    }
}
