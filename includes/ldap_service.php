<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_interface.php';

class LdapAuthProvider implements AuthProviderInterface
{
    private ?array $config;
    private $connection = null;

    public function __construct(?array $config = null)
    {
        // As configurações devem vir de variáveis de ambiente ou config file seguro
        $this->config = $config ?? [
            'host' => getenv('LDAP_HOST'),
            'port' => (int)getenv('LDAP_PORT'),
            'use_tls' => true,
            'bind_dn' => getenv('LDAP_BIND_DN'),
            'bind_pw' => getenv('LDAP_BIND_PW'),
            'base_dn' => getenv('LDAP_BASE_DN'),
        ];
    }

    public function getProviderName(): string
    {
        return 'ldap';
    }

    public function authenticate(string $username, string $password): ?array
    {
        if (empty($username) || empty($password)) {
            return null;
        }

        try {
            $this->connect();
            
            // 1. Bind Técnico (Service Account)
            if (!@ldap_bind($this->connection, $this->config['bind_dn'], $this->config['bind_pw'])) {
                error_log("LDAP: Falha no bind técnico.");
                return null;
            }

            // 2. Busca do usuário (Proteção contra LDAP Injection via escape)
            $safeUser = $this->ldapEscape($username);
            $filter = "(sAMAccountName=$safeUser)";
            $search = ldap_search($this->connection, $this->config['base_dn'], $filter);
            
            if (!$search) return null;
            
            $entries = ldap_get_entries($this->connection, $search);
            if ($entries['count'] === 0) return null;

            $userDn = $entries[0]['dn'];

            // 3. Bind do Usuário Final (Validação de Senha)
            if (!@ldap_bind($this->connection, $userDn, $password)) {
                return null; // Falha na senha
            }

            // 4. Retorno de dados (Sem expor DN ou tokens sensíveis)
            return [
                'id' => $username,
                'name' => $entries[0]['displayname'][0] ?? $username,
                'email' => $entries[0]['mail'][0] ?? '',
                'role' => $this->mapGroupsToRole($entries[0]['memberof'] ?? []),
                'source' => 'ldap'
            ];

        } catch (Throwable $e) {
            error_log("LDAP Error: " . $e->getMessage());
            return null; // Fail closed
        } finally {
            $this->disconnect();
        }
    }

    private function connect(): void
    {
        $uri = "ldaps://{$this->config['host']}:{$this->config['port']}";
        $this->connection = ldap_connect($uri);
        
        ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($this->connection, LDAP_OPT_NETWORK_TIMEOUT, 5);

        // Forçar TLS se não for LDAPS nativo
        if ($this->config['use_tls'] && !str_starts_with($uri, 'ldaps://')) {
            if (!ldap_start_tls($this->connection)) {
                throw new Exception("Falha ao iniciar TLS no LDAP.");
            }
        }
    }

    private function disconnect(): void
    {
        if ($this->connection) {
            ldap_unbind($this->connection);
            $this->connection = null;
        }
    }

    private function ldapEscape(string $subject): string
    {
        return str_replace(['\\', '*', '(', ')', "\0"], ['\\5c', '\\2a', '\\28', '\\29', '\\00'], $subject);
    }

    private function mapGroupsToRole(array $groups): string
    {
        // Lógica de mapeamento de grupos AD para roles do sistema
        return 'cliente'; 
    }
}
