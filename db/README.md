# Banco de Dados (MySQL)
Diretório contendo os scripts de criação e população do banco de dados.

## Instalação e Configuração
1. Utilize o arquivo database.sql para criar o schema inicial.
2. Recomendado: MySQL 5.7.8+ ou MariaDB 10.2.7+ (suporte a JSON).
3. Execute os scripts check_*.php para validar a integridade dos dados após a migração.

## Principais Arquivos
- database.sql: Schema completo (DML/DDL).
- check_schema.php: Validador de tabelas e colunas.

## Acesso Inicial
Após importar o banco de dados, utilize as seguintes credenciais para o primeiro acesso administrativo:
- **URL**: /app/atendente_login.php
- **Usuário**: dmin@portal.com
- **Senha**: dmin123 (Recomenda-se alterar após o primeiro acesso).
