# Portal do Atendente (Admin)
Diret�rio contendo todos os arquivos do portal administrativo.

## Instala��o e Configura��o
1. Certifique-se de que o PHP 8.x est� instalado.
2. Os arquivos dependem do diret�rio ../includes/ para bootstrap e conex�o com o banco.
3. As rotas s�o gerenciadas via Nginx (veja diret�rio e/).

## Principais Arquivos
- tendente_gestao.php: Dashboard principal.
- tendente_fila.php: Fila de chamados de entrada.
- tendente_login.php: Tela de login administrativo.
- 	k_*.php: Gerenciamento de entidades (clientes, categorias, etc).
