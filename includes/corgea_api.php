<?php

/**
 * Corgea API Client
 */
function corgea_get_client($token) {
    return new CorgeaClient($token);
}

class CorgeaClient {
    private $token;
    private $baseUrl = 'https://www.corgea.app/api/v1';

    public function __construct($token) {
        $this->token = $token;
    }

    /**
     * Verify the API token
     */
    public function verify() {
        return $this->request('/verify');
    }

    /**
     * Get security issues
     */
    public function getIssues($params = []) {
        $queryString = !empty($params) ? '?' . http_build_query($params) : '';
        return $this->request('/issues' . $queryString);
    }

    /**
     * Get specific issue details
     */
    public function getIssueDetails($issueId) {
        return $this->request("/issues/{$issueId}");
    }

    /**
     * Search for a CVE in Corgea (Custom helper)
     * Since there isn't a direct CVE endpoint in the docs, 
     * we'll try to filter issues by CVE if the API supports it,
     * or use it as a keyword search.
     */
    public function searchCve($cveId) {
        return $this->getIssues(['q' => $cveId]);
    }

    private function request($endpoint, $method = 'GET', $data = null) {
        if (empty($this->token)) return null;

        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);
        
        $headers = [
            "CORGEA-TOKEN: {$this->token}",
            "Accept: application/json"
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                $headers[] = "Content-Type: application/json";
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            return [
                'status' => 'error',
                'code' => $httpCode,
                'message' => "HTTP error $httpCode",
                'raw' => $response
            ];
        }

        return json_decode($response, true);
    }
}

