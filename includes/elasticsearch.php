<?php

function elastic_get_client(array $config)
{
    return new class($config) {
        private $url;
        private $user;
        private $pass;

        public function __construct($config) {
            $this->url = rtrim($config['url'] ?? '', '/');
            $this->user = $config['username'] ?? '';
            $this->pass = $config['password'] ?? '';
        }

        public function query(string $index, array $body) {
            $ch = curl_init("{$this->url}/{$index}/_search");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            if ($this->user && $this->pass) {
                curl_setopt($ch, CURLOPT_USERPWD, "{$this->user}:{$this->pass}");
            }

            $res = curl_exec($ch);
            return json_decode($res, true);
        }

        public function health() {
            $ch = curl_init("{$this->url}/_cluster/health");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if ($this->user && $this->pass) {
                curl_setopt($ch, CURLOPT_USERPWD, "{$this->user}:{$this->pass}");
            }
            $res = curl_exec($ch);
            return json_decode($res, true);
        }
    };
}
