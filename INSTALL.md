# Manual de Instalação e Deploy - Portal

Este documento descreve o processo de instalação, configuração e sustentação do Portal em ambientes de produção Linux (RHEL e Debian/Ubuntu).

## 1. Requisitos do Sistema

### 1.1 Hardware Mínimo
- CPU: 2 Cores
- RAM: 4 GB
- Disco: 20 GB (SSD Recomendado)

### 1.2 Dependências de Software
- **OS**: RHEL 8/9, Debian 11/12, Ubuntu 20.04/22.04
- **Web Server**: Nginx
- **Database**: MariaDB 10.5+ ou MySQL 8.0+
- **PHP**: 8.1+ com as seguintes extensões:
  - `php-fpm`
  - `php-mysqlnd` / `php-mysql`
  - `php-xml`
  - `php-curl`
  - `php-mbstring`
  - `php-gd`
  - `php-json`

---

## 2. Métodos de Instalação

### 2.1 Instalação via Script Automatizado (Recomendado)
O script `install.sh` automatiza a instalação de dependências, configuração de banco de dados, Nginx e serviços systemd.

```bash
git clone <repositorio-portal> /tmp/portal
cd /tmp/portal
chmod +x scripts/install/install.sh
sudo ./scripts/install/install.sh
```

### 2.2 Instalação via Pacotes (.deb / .rpm)
Os pacotes pré-compilados podem ser instalados utilizando os gerenciadores nativos:

**Debian/Ubuntu:**
```bash
sudo apt install ./portal-app_1.0.0_all.deb
```

**RHEL/CentOS/Rocky:**
```bash
sudo dnf install ./portal-app-1.0.0-1.el8.noarch.rpm
```

---

## 3. Serviços de Sustentação (Systemd)

O sistema utiliza coletores em background gerenciados pelo systemd para manter os dados atualizados.

| Serviço | Timer | Descrição |
|---------|-------|-----------|
| `portal-vcenter.timer` | 5 min | Coleta de inventário vCenter |
| `portal-nsx.timer` | 10 min | Coleta de dados NSX |
| `portal-bgp.timer` | 15 min | Monitoramento de sessões BGP |
| `portal-threat.timer` | Diário | Atualização de Threat Intelligence |
| `portal-alerts.timer` | 1 min | Verificação de alertas críticos vCenter |

### Comandos Úteis:
- **Verificar status:** `systemctl status portal-*`
- **Ver logs:** `journalctl -u portal-collector@vcenter_collector`
- **Logs em arquivo:** `/var/log/portal/*.log`

---

## 4. Configuração de Segurança em Produção

1. **Firewall**: Garanta que apenas as portas 80 (HTTP) e 443 (HTTPS) estejam abertas.
2. **SSL/TLS**: Recomenda-se o uso de Let's Encrypt ou certificados corporativos no Nginx.
3. **Permissões**: O diretório `/var/www/portal` deve ser de propriedade do usuário do web server (`www-data` ou `nginx`).
4. **Secrets**: Altere a senha do banco de dados gerada no arquivo `config/config.php` se necessário.

---

## 5. Troubleshooting

- **PHP Errors**: Verifique `/var/log/php-fpm/www-error.log` ou `/var/log/apache2/error.log`.
- **Nginx Errors**: Verifique `/var/log/nginx/error.log`.
- **Database Connection**: Verifique as credenciais em `config/config.php`.
- **Permissões de Upload**: Certifique-se que `uploads/` tem permissão de escrita para o usuário do PHP.
