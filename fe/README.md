# Frontend e Servidor Web (Nginx)
Configurações de servidor para ambiente de produção.

## Instalação e Configuração
1. Copie o conteúdo de 
ginx/sites-available/ para /etc/nginx/sites-available/.
2. Crie o link simbólico para sites-enabled.
3. Certifique-se de que os caminhos no arquivo .conf apontam para o diretório raiz correto da aplicação.

## Principais Arquivos
- 
ginx/sites-available/portal.domain.com.conf: Arquivo de configuração principal com regras de rewrite.
- 
ginx/snippets/portal_security_headers.conf: Headers de segurança recomendados para produção.
