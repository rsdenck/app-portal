# Portal do Atendente (Admin)
Diretório contendo todos os arquivos do portal administrativo.

## Instalação e Configuração
1. Certifique-se de que o PHP 8.x está instalado.
2. Os arquivos dependem do diretório ../includes/ para bootstrap e conexão com o banco.
3. As rotas são gerenciadas via Nginx (veja diretório e/).

## Principais Arquivos
- tendente_gestao.php: Dashboard principal.
- tendente_fila.php: Fila de chamados de entrada.
- tendente_login.php: Tela de login administrativo.
- 	k_*.php: Gerenciamento de entidades (clientes, categorias, etc).
