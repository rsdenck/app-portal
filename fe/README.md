# Frontend e Servidor Web (Nginx)
Configura��es de servidor para ambiente de produ��o.

## Instala��o e Configura��o
1. Copie o conte�do de 
ginx/sites-available/ para /etc/nginx/sites-available/.
2. Crie o link simb�lico para sites-enabled.
3. Certifique-se de que os caminhos no arquivo .conf apontam para o diret�rio raiz correto da aplica��o.

## Principais Arquivos
- 
ginx/sites-available/portal.domain.com.conf: Arquivo de configura��o principal com regras de rewrite.
- 
ginx/snippets/portal_security_headers.conf: Headers de seguran�a recomendados para produ��o.
