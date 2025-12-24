<?php

/**
 * Netflow API Client
 * Simplified to use only API KEY for authentication as per user request.
 */

declare(strict_types=1);

class NetflowClient {
    private string $url;
    private string $username;
    private string $password;

    public function __construct(string $url, string $username = '', string $password = '') {
        $this->url = rtrim($url, '/');
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Perform a request to the Netflow API
     */
    public function request(string $endpoint, string $method = 'GET', array $data = null) {
        $ch = curl_init($this->url . $endpoint);
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        if (!empty($this->username) && !empty($this->password)) {
            // Basic Auth if both provided
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
        } elseif (!empty($this->password)) {
            // Bearer Token if only password/key provided
            $headers[] = 'Authorization: Bearer ' . $this->password;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            if (str_contains($contentType, 'application/json')) {
                return json_decode($response, true);
            }
            return $response; // Return raw if not JSON
        }

        return [
            'error' => true,
            'status' => $httpCode,
            'message' => 'API request failed'
        ];
    }

    /**
     * Test the connection
     */
    public function testConnection() {
        return $this->request('/health') ?: $this->request('/status') ?: $this->request('/api/v1/status');
    }

    /**
     * Get flow summary
     */
    public function getFlowSummary() {
        return $this->request('/flows/summary');
    }
}

/**
 * Helper to get Netflow client
 */
function netflow_get_client(array $config): NetflowClient {
    return new NetflowClient(
        $config['url'] ?? '', 
        $config['username'] ?? '', 
        $config['password'] ?? ''
    );
}

