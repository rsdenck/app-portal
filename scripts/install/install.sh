#!/bin/bash
# Portal Installation Script
# Supports: RHEL 8+, Debian 11+, Ubuntu 20.04+
# Author: Portal Dev Team

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}=== Portal System Installation ===${NC}"

# 1. Detect OS
if [ -f /etc/redhat-release ]; then
    OS="RHEL"
    PKG_MGR="dnf"
elif [ -f /etc/debian_version ]; then
    OS="DEBIAN"
    PKG_MGR="apt-get"
else
    echo -e "${RED}Unsupported OS.${NC}"
    exit 1
fi

echo -e "Detected OS: ${GREEN}$OS${NC}"

# 2. Install Dependencies
echo -e "${BLUE}Installing dependencies...${NC}"
if [ "$OS" == "RHEL" ]; then
    sudo dnf install -y epel-release
    sudo dnf module enable -y php:8.1
    sudo dnf install -y nginx mariadb-server php-fpm php-mysqlnd php-xml php-curl php-mbstring php-gd php-json unzip
elif [ "$OS" == "DEBIAN" ]; then
    sudo apt-get update
    sudo apt-get install -y nginx mariadb-server php-fpm php-mysql php-xml php-curl php-mbstring php-gd unzip
fi

# 3. Configure MySQL/MariaDB
echo -e "${BLUE}Configuring Database...${NC}"
sudo systemctl enable --now mariadb
# Check if DB exists
DB_NAME="portal"
DB_USER="portal_user"
DB_PASS=$(openssl rand -base64 12)

sudo mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
sudo mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# 4. App Directory Setup
INSTALL_DIR="/var/www/portal"
echo -e "${BLUE}Setting up application at $INSTALL_DIR...${NC}"
sudo mkdir -p $INSTALL_DIR
sudo cp -r . $INSTALL_DIR/
sudo chown -R www-data:www-data $INSTALL_DIR
sudo chmod -R 755 $INSTALL_DIR
sudo chmod -R 775 $INSTALL_DIR/uploads

# 5. Configure App
echo -e "${BLUE}Configuring application...${NC}"
cat <<EOF | sudo tee $INSTALL_DIR/config/config.php
<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => '$DB_NAME',
        'user' => '$DB_USER',
        'pass' => '$DB_PASS',
        'port' => 3306,
    ],
    'app' => [
        'name' => 'Portal',
        'session_name' => 'portal_session',
    ]
];
EOF

# 6. Systemd Services
echo -e "${BLUE}Installing systemd services...${NC}"
sudo mkdir -p /var/log/portal
sudo chown www-data:www-data /var/log/portal

PHP_BIN=$(which php)
sed -i "s|{{INSTALL_DIR}}|$INSTALL_DIR|g" deployment/systemd/portal-collector@.service
sed -i "s|{{PHP_BIN}}|$PHP_BIN|g" deployment/systemd/portal-collector@.service

sudo cp deployment/systemd/*.service /etc/systemd/system/
sudo cp deployment/systemd/*.timer /etc/systemd/system/

sudo systemctl daemon-reload
sudo systemctl enable --now portal-vcenter.timer portal-nsx.timer portal-bgp.timer portal-threat.timer portal-alerts.timer

# 7. Nginx Config
echo -e "${BLUE}Configuring Nginx...${NC}"
NGINX_CONF="/etc/nginx/conf.d/portal.conf"
if [ "$OS" == "DEBIAN" ]; then
    NGINX_CONF="/etc/nginx/sites-available/portal"
fi

cat <<EOF | sudo tee $NGINX_CONF
server {
    listen 80;
    server_name _;
    root $INSTALL_DIR;
    index index.php;

    client_max_body_size 64M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php-fpm.sock; # Adjust for RHEL if needed
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }

    location ~ /\. {
        deny all;
    }
}
EOF

if [ "$OS" == "DEBIAN" ]; then
    sudo ln -sf $NGINX_CONF /etc/nginx/sites-enabled/
    sudo rm -f /etc/nginx/sites-enabled/default
fi

# Adjust PHP-FPM socket path for RHEL
if [ "$OS" == "RHEL" ]; then
    sed -i 's|unix:/run/php/php-fpm.sock|unix:/run/php-fpm/www.sock|g' $NGINX_CONF
fi

sudo nginx -t && sudo systemctl restart nginx php-fpm

echo -e "${GREEN}Installation Complete!${NC}"
echo -e "Database Password: ${BLUE}$DB_PASS${NC}"
echo -e "Access the portal via: http://$(hostname -I | awk '{print $1}')"
