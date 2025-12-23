SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role ENUM('cliente','atendente') NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS client_profiles (
  user_id BIGINT UNSIGNED NOT NULL,
  company_name VARCHAR(190) NOT NULL DEFAULT '',
  document VARCHAR(60) NOT NULL DEFAULT '',
  phone VARCHAR(60) NOT NULL DEFAULT '',
  PRIMARY KEY (user_id),
  CONSTRAINT fk_client_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attendant_profiles (
  user_id BIGINT UNSIGNED NOT NULL,
  department VARCHAR(120) NOT NULL DEFAULT '',
  category_id BIGINT UNSIGNED NULL DEFAULT NULL,
  category_id_2 BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_attendant_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_attendant_profiles_category FOREIGN KEY (category_id) REFERENCES ticket_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_attendant_profiles_category2 FOREIGN KEY (category_id_2) REFERENCES ticket_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ticket_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  schema_json JSON NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ticket_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ticket_statuses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(60) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ticket_statuses_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tickets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_user_id BIGINT UNSIGNED NOT NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  status_id BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(190) NOT NULL,
  description TEXT NOT NULL,
  extra_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  closed_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_tickets_client (client_user_id),
  KEY idx_tickets_assigned (assigned_user_id),
  KEY idx_tickets_status (status_id),
  KEY idx_tickets_category (category_id),
  CONSTRAINT fk_tickets_client FOREIGN KEY (client_user_id) REFERENCES users(id),
  CONSTRAINT fk_tickets_assigned FOREIGN KEY (assigned_user_id) REFERENCES users(id),
  CONSTRAINT fk_tickets_category FOREIGN KEY (category_id) REFERENCES ticket_categories(id),
  CONSTRAINT fk_tickets_status FOREIGN KEY (status_id) REFERENCES ticket_statuses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ticket_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(80) NOT NULL,
  payload_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ticket_history_ticket (ticket_id),
  CONSTRAINT fk_ticket_history_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_history_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS boletos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_user_id BIGINT UNSIGNED NOT NULL,
  reference VARCHAR(120) NOT NULL,
  file_relative_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_boletos_client (client_user_id),
  CONSTRAINT fk_boletos_client FOREIGN KEY (client_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS zabbix_hostgroups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_user_id BIGINT UNSIGNED NOT NULL,
  hostgroupid VARCHAR(32) NOT NULL,
  name VARCHAR(190) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_zbx_hostgroups_client (client_user_id),
  CONSTRAINT fk_zbx_hostgroups_client FOREIGN KEY (client_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS zabbix_settings (
  id TINYINT UNSIGNED NOT NULL,
  url VARCHAR(255) NOT NULL DEFAULT '',
  username VARCHAR(190) NOT NULL DEFAULT '',
  password VARCHAR(255) NOT NULL DEFAULT '',
  ignore_ssl TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO zabbix_settings (id, url, username, password, ignore_ssl) VALUES
  (1,'','', '',0);

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  tenant_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  context_json JSON NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_tenant (tenant_id),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO ticket_statuses (id, name, slug) VALUES
  (1,'Aberto','aberto'),
  (2,'Fechado','fechado'),
  (3,'Agendado','agendado'),
  (4,'Contestado','contestado'),
  (5,'Encerrado','encerrado'),
  (6,'Aguardando cotação','aguardando_cotacao');

INSERT IGNORE INTO ticket_categories (id, name, slug, schema_json) VALUES
  (1,'Virtualização','virtualizacao', JSON_ARRAY(
    JSON_OBJECT('name','ip','label','IP','type','text','required',true),
    JSON_OBJECT('name','hostname','label','Hostname','type','text','required',true),
    JSON_OBJECT('name','dns','label','DNS','type','text','required',false),
    JSON_OBJECT('name','acesso','label','Acesso','type','text','required',true),
    JSON_OBJECT('name','protocolo','label','SSH ou RDP','type','select','required',true,'options',JSON_ARRAY('ssh','rdp')),
    JSON_OBJECT('name','detalhes','label','Detalhes','type','textarea','required',true)
  )),
  (2,'Redes','redes', JSON_ARRAY(
    JSON_OBJECT('name','origem','label','Origem','type','text','required',true),
    JSON_OBJECT('name','destino','label','Destino','type','text','required',true),
    JSON_OBJECT('name','porta','label','Porta','type','text','required',true),
    JSON_OBJECT('name','protocolo','label','Protocolo','type','select','required',true,'options',JSON_ARRAY('tcp','udp')),
    JSON_OBJECT('name','justificativa','label','Justificativa','type','textarea','required',true)
  )),
  (3,'Email','email', JSON_ARRAY(
    JSON_OBJECT('name','conta','label','Conta / Usuário','type','text','required',true),
    JSON_OBJECT('name','acao','label','Ação','type','select','required',true,'options',JSON_ARRAY('criar','liberar','aumentar_cota','outros')),
    JSON_OBJECT('name','detalhes','label','Detalhes','type','textarea','required',true)
  )),
  (4,'Backup','backup', JSON_ARRAY(
    JSON_OBJECT('name','produto','label','Veeam ou Acronis','type','select','required',true,'options',JSON_ARRAY('veeam','acronis')),
    JSON_OBJECT('name','job','label','Job','type','text','required',false),
    JSON_OBJECT('name','erro','label','Erro','type','textarea','required',false),
    JSON_OBJECT('name','acao','label','Ação','type','select','required',true,'options',JSON_ARRAY('criar','ajustar','cota','investigar')),
    JSON_OBJECT('name','detalhes','label','Detalhes','type','textarea','required',true)
  )),
  (5,'Projetos','projetos', JSON_ARRAY(
    JSON_OBJECT('name','tipo','label','Tipo','type','select','required',true,'options',JSON_ARRAY('novo_ambiente','novo_cliente','novo_projeto','outros')),
    JSON_OBJECT('name','escopo','label','Escopo','type','textarea','required',true),
    JSON_OBJECT('name','prazo','label','Prazo desejado','type','text','required',false)
  )),
  (6,'Financeiro','financeiro', JSON_ARRAY(
    JSON_OBJECT('name','tipo','label','Tipo','type','select','required',true,'options',JSON_ARRAY('boleto','cobranca','outros')),
    JSON_OBJECT('name','referencia','label','Referência','type','text','required',true),
    JSON_OBJECT('name','detalhes','label','Detalhes','type','textarea','required',true)
  ));

CREATE TABLE IF NOT EXISTS asset_types (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_user_id BIGINT UNSIGNED NOT NULL,
  type_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  manufacturer VARCHAR(120) DEFAULT '',
  model VARCHAR(120) DEFAULT '',
  serial_number VARCHAR(120) DEFAULT '',
  purchase_date DATE DEFAULT NULL,
  warranty_expiry DATE DEFAULT NULL,
  notes TEXT,
  cpu VARCHAR(120) DEFAULT '',
  ram VARCHAR(60) DEFAULT '',
  storage VARCHAR(120) DEFAULT '',
  os_name VARCHAR(120) DEFAULT '',
  os_version VARCHAR(120) DEFAULT '',
  location VARCHAR(190) DEFAULT '',
  responsible_person VARCHAR(120) DEFAULT '',
  ip_address VARCHAR(45) DEFAULT '',
  subnet_mask VARCHAR(45) DEFAULT '',
  gateway VARCHAR(45) DEFAULT '',
  dns_servers VARCHAR(190) DEFAULT '',
  mac_address VARCHAR(45) DEFAULT '',
  switch_port VARCHAR(120) DEFAULT '',
  vlan VARCHAR(60) DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_assets_client (client_user_id),
  KEY idx_assets_type (type_id),
  CONSTRAINT fk_assets_client FOREIGN KEY (client_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_assets_type FOREIGN KEY (type_id) REFERENCES asset_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO asset_types (id, name) VALUES
  (1, 'Computador'),
  (2, 'Servidor'),
  (3, 'Switch'),
  (4, 'Roteador'),
  (5, 'Impressora'),
  (6, 'Outro');
