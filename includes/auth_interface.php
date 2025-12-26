<?php

declare(strict_types=1);

interface AuthProviderInterface
{
    /**
     * Autentica um usuário e retorna os dados do perfil ou null em caso de falha.
     * 
     * @param string $username
     * @param string $password
     * @return array|null
     */
    public function authenticate(string $username, string $password): ?array;

    /**
     * Retorna o nome identificador do provedor (ex: 'ldap', 'local').
     * 
     * @return string
     */
    public function getProviderName(): string;
}
