#!/bin/bash

# DNS Author Manager - Installer Script
# Tested on Debian 12 / Ubuntu 22.04
# Run as root

set -e

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

log() {
    echo -e "${GREEN}[DNS-INSTALLER]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

# Check Root
if [ "$EUID" -ne 0 ]; then
  error "Please run as root"
fi

# Configuration Variables
DB_ROOT_PASS=$(openssl rand -base64 12)
DB_PDNS_USER="pdns"
DB_PDNS_PASS=$(openssl rand -base64 12)
DB_NAME="powerdns"

PDNS_API_KEY=$(openssl rand -hex 16)
ADMIN_PASS="admin123" # Default password for web login

WEB_DIR="/var/www/html"
BACKUP_DIR="/root/dns_manager_backup_$(date +%s)"

# 1. Pre-installation Checklist
log "Starting Pre-installation Checklist..."

check_step() {
    if [ $? -eq 0 ]; then
        echo -e "  [${GREEN}✓${NC}] $1"
    else
        echo -e "  [${RED}✗${NC}] $1"
        error "Check failed: $1"
    fi
}

# Check OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    if [[ "$ID" == "debian" || "$ID" == "ubuntu" ]]; then
        true
    else
        false
    fi
fi
check_step "OS Check ($PRETTY_NAME detected)"

# Check Root
[ "$EUID" -eq 0 ]
check_step "Root Privileges"

# Check Internet (simple ping)
curl -s --connect-timeout 3 www.google.com > /dev/null
check_step "Internet Connectivity"

echo -e "\n${GREEN}Checklist passed. Starting installation...${NC}\n"

# 2. Update and Install Dependencies
log "Updating system and installing dependencies..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
check_step "System Repositories Updated"

PACKAGES="apache2 php php-mysql php-curl php-json mariadb-server pdns-server pdns-backend-mysql jq curl unzip"
log "Installing packages: $PACKAGES"
apt-get install -y $PACKAGES > /dev/null
check_step "Required Packages Installed"

# 2. Database Setup
log "Configuring Database..."

# Secure MariaDB (Automated)
mysql -e "UPDATE mysql.global_priv SET priv=json_set(priv, '$.plugin', 'mysql_native_password', '$.authentication_string', PASSWORD('$DB_ROOT_PASS')) WHERE User='root';" 2>/dev/null || true
mysql -e "FLUSH PRIVILEGES;"

# Create PDNS Database and User
mysql -u root -p"$DB_ROOT_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;"
mysql -u root -p"$DB_ROOT_PASS" -e "CREATE USER IF NOT EXISTS '$DB_PDNS_USER'@'localhost';"
mysql -u root -p"$DB_ROOT_PASS" -e "ALTER USER '$DB_PDNS_USER'@'localhost' IDENTIFIED BY '$DB_PDNS_PASS';"
mysql -u root -p"$DB_ROOT_PASS" -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_PDNS_USER'@'localhost';"
mysql -u root -p"$DB_ROOT_PASS" -e "FLUSH PRIVILEGES;"

# Import PowerDNS Schema
# Schema source: https://doc.powerdns.com/authoritative/backends/generic-mysql.html
log "Importing PowerDNS Schema..."
mysql --force -u root -p"$DB_ROOT_PASS" "$DB_NAME" <<EOF
CREATE TABLE IF NOT EXISTS domains (
  id                    INT AUTO_INCREMENT,
  name                  VARCHAR(255) NOT NULL,
  master                VARCHAR(128) DEFAULT NULL,
  last_check            INT DEFAULT NULL,
  type                  VARCHAR(6) NOT NULL,
  notified_serial       INT DEFAULT NULL,
  account               VARCHAR(40) DEFAULT NULL,
  options               TEXT DEFAULT NULL,
  catalog               VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id)
) Engine=InnoDB;

CREATE UNIQUE INDEX name_index ON domains(name);

CREATE TABLE IF NOT EXISTS records (
  id                    BIGINT AUTO_INCREMENT,
  domain_id             INT DEFAULT NULL,
  name                  VARCHAR(255) DEFAULT NULL,
  type                  VARCHAR(10) DEFAULT NULL,
  content               TEXT DEFAULT NULL,
  ttl                   INT DEFAULT NULL,
  prio                  INT DEFAULT NULL,
  disabled              TINYINT(1) DEFAULT 0,
  ordername             VARCHAR(255) BINARY DEFAULT NULL,
  auth                  TINYINT(1) DEFAULT 1,
  PRIMARY KEY (id)
) Engine=InnoDB;

CREATE INDEX nametype_index ON records(name,type);
CREATE INDEX domain_id ON records(domain_id);
CREATE INDEX recordorder ON records (domain_id, ordername);

CREATE TABLE IF NOT EXISTS supermasters (
  ip                    VARCHAR(64) NOT NULL,
  nameserver            VARCHAR(255) NOT NULL,
  account               VARCHAR(40) NOT NULL,
  PRIMARY KEY (ip, nameserver)
) Engine=InnoDB;

CREATE TABLE IF NOT EXISTS comments (
  id                    INT AUTO_INCREMENT,
  domain_id             INT NOT NULL,
  name                  VARCHAR(255) NOT NULL,
  type                  VARCHAR(10) NOT NULL,
  modified_at           INT NOT NULL,
  account               VARCHAR(40) DEFAULT NULL,
  comment               TEXT NOT NULL,
  PRIMARY KEY (id)
) Engine=InnoDB;

CREATE INDEX comments_name_type_idx ON comments (name, type);
CREATE INDEX comments_order_idx ON comments (domain_id, modified_at);

CREATE TABLE IF NOT EXISTS domainmetadata (
  id                    INT AUTO_INCREMENT,
  domain_id             INT NOT NULL,
  kind                  VARCHAR(32),
  content               TEXT,
  PRIMARY KEY (id)
) Engine=InnoDB;

CREATE INDEX domainmetadata_idx ON domainmetadata (domain_id, kind);

CREATE TABLE IF NOT EXISTS cryptokeys (
  id                    INT AUTO_INCREMENT,
  domain_id             INT NOT NULL,
  flags                 INT NOT NULL,
  active                TINYINT(1),
  content               TEXT,
  PRIMARY KEY (id)
) Engine=InnoDB;

CREATE INDEX domainidindex ON cryptokeys(domain_id);

CREATE TABLE IF NOT EXISTS tsigkeys (
  id                    INT AUTO_INCREMENT,
  name                  VARCHAR(255),
  algorithm             VARCHAR(50),
  secret                VARCHAR(255),
  PRIMARY KEY (id)
) Engine=InnoDB;

CREATE UNIQUE INDEX namealgoindex ON tsigkeys(name, algorithm);
EOF

# Import Application Tables
log "Importing Application Schema..."
mysql --force -u root -p"$DB_ROOT_PASS" "$DB_NAME" <<EOF
CREATE TABLE IF NOT EXISTS cluster_servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    host VARCHAR(255) NOT NULL,
    port INT DEFAULT 8081,
    api_key VARCHAR(255) NOT NULL,
    role ENUM('sync', 'standalone') DEFAULT 'sync',
    type ENUM('native', 'master', 'slave') DEFAULT 'native',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(255) NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS app_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(255) NOT NULL,
    description TEXT,
    key_string VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
EOF

# 3. Configure PowerDNS
log "Configuring PowerDNS..."
if [ -f "/etc/powerdns/pdns.conf" ]; then
    cp /etc/powerdns/pdns.conf "/etc/powerdns/pdns.conf.bak.$(date +%s)"
fi

cat > /etc/powerdns/pdns.conf <<EOF
launch=gmysql
gmysql-host=127.0.0.1
gmysql-user=$DB_PDNS_USER
gmysql-password=$DB_PDNS_PASS
gmysql-dbname=$DB_NAME

api=yes
api-key=$PDNS_API_KEY
webserver=yes
webserver-address=0.0.0.0
webserver-port=8081
webserver-allow-from=0.0.0.0/0
EOF

systemctl restart pdns

# 4. Configure Web Application
log "Setting up Web Application..."

# Make backup of existing web dir if not empty
if [ "$(ls -A $WEB_DIR)" ]; then
    log "Backing up existing web directory to $BACKUP_DIR..."
    mkdir -p "$BACKUP_DIR"
    cp -r $WEB_DIR/* "$BACKUP_DIR/" 2>/dev/null || true
fi

# Copy files (assuming script is run from source directory)
# If running standalone, you might need to git clone or unzip here.
# For this script we assume the files are in the current directory or a subdirectory
cp -r ./* $WEB_DIR/ 2>/dev/null || true 
rm -f $WEB_DIR/index.html 2>/dev/null || true # Remove default apache page
rm -f $WEB_DIR/install.sh 2>/dev/null || true # Don't leave installer in webroot

# Generate db.php
cat > $WEB_DIR/db.php <<EOF
<?php
\$host = '127.0.0.1';
\$db   = '$DB_NAME';
\$user = '$DB_PDNS_USER';
\$pass = '$DB_PDNS_PASS';
\$charset = 'utf8mb4';

\$dsn = "mysql:host=\$host;dbname=\$db;charset=\$charset";
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    \$pdo = new PDO(\$dsn, \$user, \$pass, \$options);
} catch (\PDOException \$e) {
    throw new \PDOException(\$e->getMessage(), (int)\$e->getCode());
}
EOF

# Seed Admin User
ADMIN_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_DEFAULT);")
mysql -u root -p"$DB_ROOT_PASS" "$DB_NAME" -e "INSERT IGNORE INTO app_users (username, password_hash) VALUES ('admin', '$ADMIN_HASH');"

# Seed Local Server
# Needs encrypted key
# We need to replicate the encryptData logic or just insert it directly if we knew the secret.
# But APP_SECRET is in config.php.
# Let's generate a new APP_SECRET for this install to be secure.

NEW_APP_SECRET=$(openssl rand -hex 32)
sed -i "s/define('APP_SECRET', '.*');/define('APP_SECRET', '$NEW_APP_SECRET');/" $WEB_DIR/config.php

# Now use PHP to encrypt the key using the new secret
ENCRYPTED_KEY=$(php -r "
    define('APP_SECRET', '$NEW_APP_SECRET');
    function encryptData(\$data) {
        \$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        \$encrypted = openssl_encrypt(\$data, 'aes-256-cbc', APP_SECRET, 0, \$iv);
        return base64_encode(\$encrypted . '::' . \$iv);
    }
    echo encryptData('$PDNS_API_KEY');
")

mysql -u root -p"$DB_ROOT_PASS" "$DB_NAME" -e "INSERT INTO cluster_servers (name, host, port, api_key, role, type) VALUES ('Local Node', '127.0.0.1', 8081, '$ENCRYPTED_KEY', 'sync', 'native');"

# Permissions
chown -R www-data:www-data $WEB_DIR
chmod -R 755 $WEB_DIR

# 5. Finalize
log "Installation Complete!"
echo "--------------------------------------------------------"
echo "Web URL:       http://$(hostname -I | awk '{print $1}')/"
echo "Admin User:    admin"
echo "Admin Pass:    $ADMIN_PASS"
echo "--------------------------------------------------------"
echo "PowerDNS API:  http://$(hostname -I | awk '{print $1}'):8081/"
echo "API Key:       $PDNS_API_KEY"
echo "--------------------------------------------------------"
echo "Database Pass: $DB_ROOT_PASS"
echo "--------------------------------------------------------"
