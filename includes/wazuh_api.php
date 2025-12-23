<?php

/**
 * Wazuh API Client for Security Event Correlation
 */
class WazuhClient {
    private $url;
    private $user;
    private $password;
    private $token;

    public function __construct($config) {
        $this->url = rtrim($config['url'] ?? '', '/');
        $this->user = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
    }

    private function login() {
        if ($this->token) return $this->token;

        $ch = curl_init("{$this->url}/security/user/authenticate");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->user}:{$this->password}");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $data = json_decode($response, true);
        curl_close($ch);

        if (isset($data['data']['token'])) {
            $this->token = $data['data']['token'];
            return $this->token;
        }
        return null;
    }

    public function getSecurityEvents($limit = 100) {
        $token = $this->login();
        if (!$token) return null;

        $ch = curl_init("{$this->url}/alerts?limit=$limit&sort=-timestamp");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $data = json_decode($response, true);
        curl_close($ch);

        return $data['data']['affected_items'] ?? [];
    }
}

function wazuh_get_client($config) {
    return new WazuhClient($config);
}
