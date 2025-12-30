<?php

declare(strict_types=1);

/**
 * Ensures plugin tables exist.
 * 
 * @param PDO $pdo
 * @return void
 */
function plugins_ensure_table(PDO $pdo): void
{
    $sql = "CREATE TABLE IF NOT EXISTS plugins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        label VARCHAR(100) NOT NULL,
        category VARCHAR(50) NOT NULL,
        description TEXT,
        is_active TINYINT(1) DEFAULT 0,
        config JSON,
        icon VARCHAR(255),
        required_category_slug VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);

    // Cache table for ASN and GeoIP
    $sqlCache = "CREATE TABLE IF NOT EXISTS plugin_cache (
        cache_key VARCHAR(255) PRIMARY KEY,
        cache_value JSON NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlCache);

    // Persist table for NSX collected data
    $sqlNsx = "CREATE TABLE IF NOT EXISTS plugin_nsx_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data_type VARCHAR(50) NOT NULL UNIQUE,
        data_content JSON NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlNsx);

    // Persist table for vCenter collected data
    $sqlVcenter = "CREATE TABLE IF NOT EXISTS plugin_vcenter_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data_type VARCHAR(50) NOT NULL UNIQUE,
        data_content JSON NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlVcenter);

    // Persist table for generic Virtualization data
    $sqlVirt = "CREATE TABLE IF NOT EXISTS plugin_virt_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plugin_name VARCHAR(50) NOT NULL,
        data_type VARCHAR(50) NOT NULL,
        data_content JSON NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `plugin_data_type` (`plugin_name`, `data_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlVirt);

    $sqlDflowInterfaces = "CREATE TABLE IF NOT EXISTS plugin_dflow_interfaces (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_ip VARCHAR(45) NOT NULL,
        if_index INT NOT NULL,
        name VARCHAR(100),
        description TEXT,
        mac_address VARCHAR(17),
        vlan INT DEFAULT 0,
        speed BIGINT,
        status VARCHAR(20),
        in_bytes BIGINT DEFAULT 0,
        out_bytes BIGINT DEFAULT 0,
        in_packets BIGINT DEFAULT 0,
        out_packets BIGINT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_dev_if` (`device_ip`, `if_index`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlDflowInterfaces);

    $sqlDflowHosts = "CREATE TABLE IF NOT EXISTS plugin_dflow_hosts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        hostname VARCHAR(255),
        mac_address VARCHAR(17),
        vlan INT,
        asn INT,
        country_code CHAR(2),
        throughput_bps BIGINT DEFAULT 0,
        total_bytes BIGINT DEFAULT 0,
        active_flows INT DEFAULT 0,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_ip_address` (`ip_address`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlDflowHosts);

    $sqlDflowFlows = "CREATE TABLE IF NOT EXISTS plugin_dflow_flows (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        src_ip VARCHAR(45) NOT NULL,
        src_port INT UNSIGNED,
        dst_ip VARCHAR(45) NOT NULL,
        dst_port INT UNSIGNED,
        proto VARCHAR(20),
        app_proto VARCHAR(100),
        bytes BIGINT UNSIGNED DEFAULT 0,
        pkts BIGINT UNSIGNED DEFAULT 0,
        vlan INT DEFAULT 0,
        ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        tcp_flags TINYINT UNSIGNED DEFAULT 0,
        rtt_ms FLOAT DEFAULT NULL,
        eth_type VARCHAR(10) DEFAULT NULL,
        pcp TINYINT UNSIGNED DEFAULT 0,
        sni VARCHAR(255) DEFAULT NULL,
        ja3 CHAR(32) DEFAULT NULL,
        anomaly TEXT DEFAULT NULL,
        cve VARCHAR(50) DEFAULT NULL,
        src_mac VARCHAR(17) DEFAULT NULL,
        dst_mac VARCHAR(17) DEFAULT NULL,
        as_src INT UNSIGNED,
        as_dst INT UNSIGNED,
        application VARCHAR(100),
        input_if TEXT,
        output_if TEXT,
        KEY `idx_src_ip` (`src_ip`),
        KEY `idx_dst_ip` (`dst_ip`),
        KEY `idx_app_proto` (`app_proto`),
        KEY `idx_vlan` (`vlan`),
        KEY `idx_ts` (`ts`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlDflowFlows);

    $sqlDflowTopology = "CREATE TABLE IF NOT EXISTS plugin_dflow_topology (
        id INT AUTO_INCREMENT PRIMARY KEY,
        local_device_ip VARCHAR(45) NOT NULL,
        local_port_index INT NOT NULL,
        remote_device_name VARCHAR(255),
        remote_port_id VARCHAR(100),
        remote_chassis_id VARCHAR(100),
        remote_system_desc TEXT,
        protocol ENUM('LLDP', 'CDP') NOT NULL,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY neighbor (local_device_ip, local_port_index, remote_chassis_id, remote_port_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlDflowTopology);

    $sqlDflowVlans = "CREATE TABLE IF NOT EXISTS plugin_dflow_vlans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_ip VARCHAR(45) NOT NULL,
        vlan_id INT NOT NULL,
        vlan_name VARCHAR(100),
        vlan_status VARCHAR(20),
        vlan_type VARCHAR(50),
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY device_vlan (device_ip, vlan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlDflowVlans);

    $sqlDflowDevices = "CREATE TABLE IF NOT EXISTS plugin_dflow_devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        hostname VARCHAR(255),
        description TEXT,
        vendor VARCHAR(100),
        model VARCHAR(100),
        os_version VARCHAR(100),
        uptime BIGINT UNSIGNED,
        snmp_community VARCHAR(255) DEFAULT 'public',
        snmp_version VARCHAR(10) DEFAULT '2c',
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY device_ip (ip_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlDflowDevices);

    $sqlDflowBaselines = "CREATE TABLE IF NOT EXISTS plugin_dflow_baselines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vlan_id INT NOT NULL,
        hour_of_day INT NOT NULL,
        avg_bytes BIGINT DEFAULT 0,
        stddev_bytes BIGINT DEFAULT 0,
        avg_packets BIGINT DEFAULT 0,
        stddev_packets BIGINT DEFAULT 0,
        sample_count INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY vlan_hour (vlan_id, hour_of_day)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlDflowBaselines);

    // --- NOVO MODELO BGP/ASN (Entidades de 1ª Classe) ---
    
    // Master ASN Table
    $sqlAsns = "CREATE TABLE IF NOT EXISTS plugin_dflow_asns (
        asn_id INT AUTO_INCREMENT PRIMARY KEY,
        asn_number INT NOT NULL UNIQUE,
        organization VARCHAR(255),
        country CHAR(2),
        rir VARCHAR(20),
        first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_asn (asn_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlAsns);

    // BGP Snapshots (Audit Log of Internet Routing State)
    $sqlBgpSnapshots = "CREATE TABLE IF NOT EXISTS plugin_dflow_bgp_snapshots (
        snapshot_id INT AUTO_INCREMENT PRIMARY KEY,
        collected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        source ENUM('ripe', 'routeviews', 'bgpq4', 'local') NOT NULL,
        file_path VARCHAR(255),
        status ENUM('pending', 'processing', 'completed', 'error') DEFAULT 'pending',
        INDEX idx_collected (collected_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlBgpSnapshots);

    // Temporal Prefix Mapping (The Core of Correlation)
    $sqlAsnPrefixes = "CREATE TABLE IF NOT EXISTS plugin_dflow_asn_prefixes (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        prefix VARCHAR(50) NOT NULL,
        asn_id INT NOT NULL,
        snapshot_id INT,
        valid_from TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        valid_to TIMESTAMP NULL,
        source ENUM('ripe', 'routeviews', 'bgpq4', 'local') DEFAULT 'local',
        INDEX idx_prefix_temporal (prefix, valid_from, valid_to),
        INDEX idx_asn_id (asn_id),
        FOREIGN KEY (asn_id) REFERENCES plugin_dflow_asns(asn_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlAsnPrefixes);

    // Flow to ASN Mapping (Historical Correlation for Forensics)
    $pdo->exec("DROP TABLE IF EXISTS plugin_dflow_flow_asn_map");
    $sqlFlowAsnMap = "CREATE TABLE IF NOT EXISTS plugin_dflow_flow_asn_map (
        flow_id BIGINT UNSIGNED PRIMARY KEY,
        src_asn_id INT,
        dst_asn_id INT,
        snapshot_id INT,
        INDEX idx_src_asn (src_asn_id),
        INDEX idx_dst_asn (dst_asn_id),
        FOREIGN KEY (flow_id) REFERENCES plugin_dflow_flows(id) ON DELETE CASCADE,
        FOREIGN KEY (src_asn_id) REFERENCES plugin_dflow_asns(asn_id),
        FOREIGN KEY (dst_asn_id) REFERENCES plugin_dflow_asns(asn_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlFlowAsnMap);

    $sqlDflowAlerts = "CREATE TABLE IF NOT EXISTS plugin_dflow_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL,
        severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
        subject VARCHAR(255) NOT NULL,
        description TEXT,
        source_ip VARCHAR(45),
        target_ip VARCHAR(45),
        vlan INT DEFAULT 0,
        status ENUM('active', 'resolved', 'muted') DEFAULT 'active',
        ticket_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL,
        KEY `idx_status` (`status`),
        KEY `idx_vlan` (`vlan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlDflowAlerts);

    $sqlDflowSensors = "CREATE TABLE IF NOT EXISTS plugin_dflow_sensors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        ip_address VARCHAR(45),
        status ENUM('online', 'offline', 'error') DEFAULT 'offline',
        cpu_usage FLOAT DEFAULT 0,
        mem_usage FLOAT DEFAULT 0,
        pps BIGINT DEFAULT 0,
        bps BIGINT DEFAULT 0,
        packet_drops BIGINT DEFAULT 0,
        active_flows INT DEFAULT 0,
        last_heartbeat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        version VARCHAR(20),
        UNIQUE KEY idx_sensor_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlDflowSensors);

    $sqlDflowMetrics = "CREATE TABLE IF NOT EXISTS plugin_dflow_system_metrics (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        sensor_id INT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        thread_id INT DEFAULT 0,
        processed_packets BIGINT DEFAULT 0,
        processed_bytes BIGINT DEFAULT 0,
        dropped_packets BIGINT DEFAULT 0,
        active_sessions INT DEFAULT 0,
        total_flows BIGINT DEFAULT 0,
        hash_collisions INT DEFAULT 0,
        cpu_load FLOAT DEFAULT 0,
        mem_used BIGINT DEFAULT 0,
        INDEX idx_sensor_ts (sensor_id, timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlDflowMetrics);

    // --- WATCHER DE SEGURANÇA (Anomalias e Eventos) ---

    $sqlDflowSecurityEvents = "CREATE TABLE IF NOT EXISTS plugin_dflow_security_events (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(50) NOT NULL,
        severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
        src_ip VARCHAR(45),
        dst_ip VARCHAR(45),
        src_asn INT,
        dst_asn INT,
        protocol_l4 VARCHAR(10),
        protocol_l7 VARCHAR(50),
        detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        evidence JSON,
        mitre_techniques JSON,
        INDEX idx_event_type (event_type),
        INDEX idx_severity (severity),
        INDEX idx_src_ip (src_ip),
        INDEX idx_dst_ip (dst_ip),
        INDEX idx_detected (detected_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlDflowSecurityEvents);

    $sqlDflowBaselinesDim = "CREATE TABLE IF NOT EXISTS plugin_dflow_baselines_dim (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entity_type ENUM('host', 'vlan', 'interface', 'asn', 'protocol') NOT NULL,
        entity_value VARCHAR(100) NOT NULL,
        hour_of_day INT NOT NULL,
        day_of_week INT NOT NULL,
        avg_bytes BIGINT DEFAULT 0,
        stddev_bytes BIGINT DEFAULT 0,
        avg_packets BIGINT DEFAULT 0,
        stddev_packets BIGINT DEFAULT 0,
        sample_count INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY entity_temporal (entity_type, entity_value, hour_of_day, day_of_week)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlDflowBaselinesDim);

    // SNMP Data Tables
    $sqlSnmpDevices = "CREATE TABLE IF NOT EXISTS plugin_snmp_devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL UNIQUE,
        community VARCHAR(100),
        version ENUM('v1', 'v2c', 'v3') DEFAULT 'v2c',
        hostname VARCHAR(255),
        sys_desc TEXT,
        last_discovery TIMESTAMP NULL,
        status VARCHAR(20) DEFAULT 'unknown',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlSnmpDevices);

    $sqlSnmpInterfaces = "CREATE TABLE IF NOT EXISTS plugin_snmp_interfaces (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id INT NOT NULL,
        if_index INT NOT NULL,
        if_name VARCHAR(100),
        if_desc TEXT,
        if_type INT,
        if_speed BIGINT,
        if_phys_address VARCHAR(50),
        if_admin_status INT,
        if_oper_status INT,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_device_if` (`device_id`, `if_index`),
        CONSTRAINT fk_snmp_if_device FOREIGN KEY (device_id) REFERENCES plugin_snmp_devices(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlSnmpInterfaces);

    // Ensure column exists for older installations
    try {
        $pdo->exec("ALTER TABLE plugins ADD COLUMN required_category_slug VARCHAR(100) DEFAULT NULL AFTER icon");
    } catch (PDOException $e) {}

    // Ensure Segurança category exists
    $stmtCat = $pdo->prepare("SELECT id FROM ticket_categories WHERE slug = 'seguranca'");
    $stmtCat->execute();
    if (!$stmtCat->fetch()) {
        $pdo->prepare("INSERT INTO ticket_categories (name, slug, schema_json) VALUES ('Segurança', 'seguranca', '[]')")->execute();
    }

    // Ensure Redes category exists
    $stmtCatRedes = $pdo->prepare("SELECT id FROM ticket_categories WHERE slug = 'redes'");
    $stmtCatRedes->execute();
    if (!$stmtCatRedes->fetch()) {
        $pdo->prepare("INSERT INTO ticket_categories (name, slug, schema_json) VALUES ('Redes', 'redes', '[]')")->execute();
    }

    // Ensure Virtualização category exists
    $stmtCatVirt = $pdo->prepare("SELECT id FROM ticket_categories WHERE slug = 'virtualizacao'");
    $stmtCatVirt->execute();
    if (!$stmtCatVirt->fetch()) {
        $pdo->prepare("INSERT INTO ticket_categories (name, slug, schema_json) VALUES ('Virtualização', 'virtualizacao', '[]')")->execute();
    }

    // Seed initial plugins if not exists
    $initialPlugins = [
        [
            'name' => 'dflow',
            'label' => 'DFlow (Native)',
            'category' => 'Redes',
            'description' => 'Flow Analyser nativo com análise de pacotes em baixo nível, detecção de anomalias e mapa topológico vivo.',
            'icon' => 'activity',
            'required_category_slug' => 'redes'
        ],
        [
            'name' => 'zabbix',
            'label' => 'Zabbix',
            'category' => 'Monitoramento',
            'description' => 'Monitoramento de infraestrutura e servidores.',
            'icon' => 'activity',
            'required_category_slug' => null
        ],
        [
            'name' => 'vcenter',
            'label' => 'VMware vCenter',
            'category' => 'Virtualização',
            'description' => 'Gestão de ambientes virtualizados VMware.',
            'icon' => 'vm',
            'required_category_slug' => 'virtualizacao'
        ],
        [
            'name' => 'nsx',
            'label' => 'VMware NSX Manager',
            'category' => 'Virtualização',
            'description' => 'Gestão de redes e segurança definida por software (SDN).',
            'icon' => 'share-2',
            'required_category_slug' => 'virtualizacao'
        ],
        [
            'name' => 'veeam',
            'label' => 'Backups',
            'category' => 'Backup',
            'description' => 'Consolidação de VCSP e VBR para gestão de backups.',
            'icon' => 'shield',
            'required_category_slug' => 'backup'
        ],
        [
            'name' => 'acronis',
            'label' => 'ACRONIS API',
            'category' => 'Backup',
            'description' => 'Integração com ACRONIS Cyber Cloud.',
            'icon' => 'cloud',
            'required_category_slug' => 'backup'
        ],
        [
            'name' => 'zimbra',
            'label' => 'Zimbra Admin Console',
            'category' => 'Email',
            'description' => 'Gestão e monitoramento de servidores Zimbra.',
            'icon' => 'mail',
            'required_category_slug' => 'email'
        ],
        [
            'name' => 'whm',
            'label' => 'WHM API',
            'category' => 'Hospedagem',
            'description' => 'Gestão de contas e servidores cPanel/WHM.',
            'icon' => 'server',
            'required_category_slug' => 'hospedagem'
        ],
        [
            'name' => 'wazuh',
            'label' => 'Wazuh API',
            'category' => 'Segurança',
            'description' => 'Monitoramento de segurança e conformidade.',
            'icon' => 'shield',
            'required_category_slug' => 'seguranca'
        ],
        [
            'name' => 'security_gateway',
            'label' => 'Security Gateway',
            'category' => 'Segurança',
            'description' => 'Coleta de logs e eventos de segurança do gateway principal.',
            'icon' => 'shield',
            'required_category_slug' => 'seguranca'
        ],
        [
            'name' => 'abuseipdb',
            'label' => 'AbuseIPDB API',
            'category' => 'Segurança',
            'description' => 'Verificação de reputação de IPs e reporte de abusos.',
            'icon' => 'shield-off',
            'required_category_slug' => 'seguranca'
        ],
        [
            'name' => 'shodan',
            'label' => 'Shodan API',
            'category' => 'Segurança',
            'description' => 'Motor de busca para dispositivos conectados à Internet.',
            'icon' => 'search',
            'required_category_slug' => 'seguranca'
        ],
        [
            'name' => 'nuclei',
            'label' => 'Nuclei CLI',
            'category' => 'Segurança',
            'description' => 'Scanner de vulnerabilidades rápido baseado em templates.',
            'icon' => 'zap',
            'required_category_slug' => 'seguranca'
        ],
        [
            'name' => 'ipinfo',
            'label' => 'IPINFO API',
            'category' => 'Inteligência',
            'description' => 'GeoIP e GEOMAP 3D para análise de tráfego.',
            'icon' => 'globe',
            'required_category_slug' => null
        ],
        [
            'name' => 'bgpview',
            'label' => 'Network (BGP)',
            'category' => 'Redes',
            'description' => 'Gestão de ASN, prefixos e análise de IX (Internet Exchange).',
            'icon' => 'share-2',
            'required_category_slug' => 'redes'
        ],
        [
            'name' => 'snmp',
            'label' => 'SNMP Integration',
            'category' => 'Redes',
            'description' => 'Monitoramento de ativos de rede via protocolo SNMP.',
            'icon' => 'cpu',
            'required_category_slug' => 'redes'
        ],
        [
            'name' => 'proxmox',
            'label' => 'Proxmox VE API',
            'category' => 'Virtualização',
            'description' => 'Gestão de ambientes Proxmox VE.',
            'icon' => 'server',
            'required_category_slug' => 'virtualizacao'
        ],
        [
            'name' => 'cloudstack',
            'label' => 'Apache Cloudstack',
            'category' => 'Virtualização',
            'description' => 'Gestão de nuvem privada Apache Cloudstack.',
            'icon' => 'cloud',
            'required_category_slug' => 'virtualizacao'
        ],
        [
            'name' => 'nutanix',
            'label' => 'Nutanix Prism API',
            'category' => 'Virtualização',
            'description' => 'Gestão de infraestrutura hiperconvergente Nutanix.',
            'icon' => 'box',
            'required_category_slug' => 'virtualizacao'
        ],
        [
            'name' => 'hyperv',
            'label' => 'Hyper-V SCVMM',
            'category' => 'Virtualização',
            'description' => 'Gestão de ambientes Microsoft Hyper-V via SCVMM.',
            'icon' => 'monitor',
            'required_category_slug' => 'virtualizacao'
        ],
        [
            'name' => 'xen',
            'label' => 'XEN API (Citrix Hypervisor)',
            'category' => 'Virtualização',
            'description' => 'Gestão de ambientes XEN/Citrix Hypervisor.',
            'icon' => 'activity',
            'required_category_slug' => 'virtualizacao'
        ],
        [
            'name' => 'kvm',
            'label' => 'KVM API (Libvirt)',
            'category' => 'Virtualização',
            'description' => 'Gestão de ambientes KVM nativos via Libvirt API.',
            'icon' => 'cpu',
            'required_category_slug' => 'virtualizacao'
        ],
        [
            'name' => 'deepflow',
            'label' => 'Deepflow API',
            'category' => 'Redes',
            'description' => 'Observabilidade de rede e aplicação com eBPF.',
            'icon' => 'activity',
            'required_category_slug' => 'redes'
        ],
        [
            'name' => 'netflow',
            'label' => 'Netflow API',
            'category' => 'Redes',
            'description' => 'Análise de tráfego de rede.',
            'icon' => 'bar-chart',
            'required_category_slug' => 'redes'
        ],
        [
            'name' => 'guacamole',
            'label' => 'Apache Guacamole',
            'category' => 'Acesso Remoto',
            'description' => 'Acesso a servidores via RDP, SSH e VNC de dentro do painel.',
            'icon' => 'monitor',
            'required_category_slug' => null
        ],
        [
            'name' => 'cloudflare',
            'label' => 'Cloudflare Radar',
            'category' => 'Segurança',
            'description' => 'Monitoramento de ameaças e eventos BGP via Cloudflare Radar.',
            'icon' => 'cloud',
            'required_category_slug' => 'seguranca'
        ],
        [
            'name' => 'elasticsearch',
            'label' => 'Elasticsearch API',
            'category' => 'Monitoramento',
            'description' => 'Integração com Elasticsearch para busca e análise de logs.',
            'icon' => 'monitor',
            'required_category_slug' => null
        ]
    ];

    foreach ($initialPlugins as $p) {
        $stmt = $pdo->prepare("INSERT INTO plugins (name, label, category, description, icon, required_category_slug) 
                               VALUES (?, ?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE 
                               label = VALUES(label), 
                               category = VALUES(category), 
                               description = VALUES(description), 
                               icon = VALUES(icon),
                               required_category_slug = VALUES(required_category_slug)");
        $stmt->execute([$p['name'], $p['label'], $p['category'], $p['description'], $p['icon'], $p['required_category_slug']]);
    }
}

function plugins_get_all(PDO $pdo): array
{
    plugins_ensure_table($pdo);
    $stmt = $pdo->query("SELECT * FROM plugins ORDER BY category ASC, label ASC");
    return $stmt->fetchAll();
}

function plugins_get_active(PDO $pdo): array
{
    plugins_ensure_table($pdo);
    $stmt = $pdo->query("SELECT * FROM plugins WHERE is_active = 1");
    return $stmt->fetchAll();
}

function plugin_get_by_name(PDO $pdo, string $name): ?array
{
    plugins_ensure_table($pdo);
    $stmt = $pdo->prepare("SELECT * FROM plugins WHERE name = ?");
    $stmt->execute([$name]);
    $plugin = $stmt->fetch();
    if ($plugin && $plugin['config']) {
        $plugin['config'] = json_decode($plugin['config'], true);
    }
    return $plugin ?: null;
}

function plugin_update_status(PDO $pdo, string $name, bool $isActive): void
{
    $stmt = $pdo->prepare("UPDATE plugins SET is_active = ? WHERE name = ?");
    $stmt->execute([$isActive ? 1 : 0, $name]);
}

function plugin_update_config(PDO $pdo, string $name, array $config): void
{
    $stmt = $pdo->prepare("UPDATE plugins SET config = ? WHERE name = ?");
    $stmt->execute([json_encode($config), $name]);
}

/**
 * Check if a plugin is properly configured.
 * 
 * @param array $plugin
 * @return bool
 */
function plugin_is_configured(array $plugin): bool
{
    $config = $plugin['config'] ?? [];
    if (is_string($config)) {
        $config = json_decode($config, true) ?: [];
    }

    $name = $plugin['name'] ?? '';

    switch ($name) {
        case 'zabbix':
        case 'vcenter':
        case 'nsx':
        case 'veeam':
        case 'guacamole':
        case 'proxmox':
        case 'cloudstack':
        case 'nutanix':
        case 'hyperv':
        case 'xen':
        case 'kvm':
            return !empty($config['url']) && !empty($config['username']) && !empty($config['password']);
        
        case 'abuseipdb':
        case 'shodan':
        case 'ipinfo':
            return !empty($config['password']); // These use password field for API Key
            
        case 'bgpview':
            return !empty($config['my_asn']);

        case 'snmp':
            return !empty($config['devices']) && is_array($config['devices']);

        case 'netflow':
        case 'deepflow':
        case 'elasticsearch':
            return !empty($config['url']);

        case 'dflow':
        case 'nuclei':
        case 'cloudflare':
            // These might have optional or different config requirements, 
            // but for now let's assume if they are active they are "configured" 
            // or require at least one field.
            return true;

        default:
            return true;
    }
}

/**
 * Memcached singleton helper
 */
function get_memcached(): ?Memcached
{
    static $memcached = null;
    if ($memcached !== null) return $memcached;
    
    if (class_exists('Memcached')) {
        $m = new Memcached();
        $m->addServer('localhost', 11211);
        // Check connection
        $stats = $m->getStats();
        if ($stats && isset($stats['localhost:11211'])) {
            $memcached = $m;
            return $memcached;
        }
    }
    return null;
}

/**
 * Cache agressivo para evitar reconsultas de API
 */
function plugin_cache_get(PDO $pdo, string $key)
{
    global $_CACHE_STATS;
    if (!isset($_CACHE_STATS)) $_CACHE_STATS = ['hits' => 0, 'misses' => 0];

    // Try Memcached first
    $m = get_memcached();
    if ($m) {
        $val = $m->get($key);
        if ($val !== false) {
            $_CACHE_STATS['hits']++;
            return $val;
        }
    }

    // Fallback to MySQL
    $stmt = $pdo->prepare("SELECT cache_value FROM plugin_cache WHERE cache_key = ? AND expires_at > NOW()");
    $stmt->execute([$key]);
    $res = $stmt->fetch();
    
    if ($res) {
        $decoded = json_decode($res['cache_value'], true);
        if ($decoded !== null) {
            $_CACHE_STATS['hits']++;
            // Sync to Memcached for next time
            if ($m) {
                $m->set($key, $decoded, 300); // 5 min default
            }
            return $decoded;
        }
    }

    $_CACHE_STATS['misses']++;
    return null;
}

function plugin_cache_set(PDO $pdo, string $key, $value, int $ttlSeconds = 86400 * 7): void
{
    // Set in Memcached
    $m = get_memcached();
    if ($m) {
        $m->set($key, $value, $ttlSeconds);
    }

    // Persist in MySQL
    $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $stmt = $pdo->prepare("INSERT INTO plugin_cache (cache_key, cache_value, expires_at) 
                           VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at)");
    $stmt->execute([$key, json_encode($value), $expiresAt]);
}

function plugin_cache_delete(PDO $pdo, string $key): void
{
    $m = get_memcached();
    if ($m) {
        $m->delete($key);
    }
    $stmt = $pdo->prepare("DELETE FROM plugin_cache WHERE cache_key = ?");
    $stmt->execute([$key]);
}

/**
 * Get menus for active plugins, grouped by category and filtered by RBCA.
 * 
 * @param PDO $pdo
 * @param array|null $user
 * @param array $activePlugins
 * @return array
 */
function plugin_get_menus(PDO $pdo, ?array $user, array $activePlugins): array
{
    if (!$user) {
        return [];
    }

    $userCategorySlug = null;
    if ($user['role'] === 'atendente') {
        $stmt = $pdo->prepare("SELECT tc.slug 
                               FROM attendant_profiles ap 
                               JOIN ticket_categories tc ON tc.id = ap.category_id 
                               WHERE ap.user_id = ?");
        $stmt->execute([$user['id']]);
        $res = $stmt->fetch();
        $userCategorySlug = $res ? (string)$res['slug'] : null;
    }

    $groupedMenus = [];
    foreach ($activePlugins as $p) {
        // MUST be active AND configured to show in sidebar
        if (!plugin_is_configured($p)) {
            continue;
        }

        // RBCA: Check if plugin requires a specific category access
        if ($p['required_category_slug'] !== null) {
            // Admin role bypasses category restriction for visibility in settings, 
            // but for sidebar we follow the category if it's an attendant.
            if ($user['role'] === 'atendente' && $userCategorySlug !== $p['required_category_slug']) {
                continue;
            }
        }

        $category = (string)$p['category'];
        
        // RBCA: Restricted categories for Clients
        if ($user['role'] === 'cliente' && in_array($category, ['Redes', 'Segurança', 'Inteligência'])) {
            continue;
        }

        // Merge Networking, Intelligence and Security for Attendants
        if ($user['role'] === 'atendente' && in_array($category, ['Inteligência', 'Segurança'])) {
            $category = 'Redes';
        }

        if (!isset($groupedMenus[$category])) {
            $groupedMenus[$category] = [
                'label' => $category,
                'icon' => ($category === 'Redes') ? 'share-2' : ($p['icon'] ?: 'box'),
                'plugins' => []
            ];
        }

        // Standardize URLs and labels
        $url = "/app/plugin_" . $p['name'] . ".php";
        
        // DFlow Hub Logic
        if ($user['role'] === 'atendente' && in_array($p['name'], ['dflow', 'bgpview', 'snmp', 'ipinfo', 'netflow', 'deepflow'])) {
            $url = "/app/plugin_dflow_maps.php"; // All network plugins point to the Hub
            
            // NTOPNG Style: Remove from grouping to avoid duplicate sidebar entries
            // We only want ONE entry for "Redes" that points to the Hub
            $category = 'Redes';
            if (!isset($groupedMenus[$category])) {
                $groupedMenus[$category] = [
                    'label' => 'Redes',
                    'icon' => 'share-2',
                    'plugins' => [[
                        'name' => 'dflow_hub',
                        'label' => 'Central de Redes',
                        'url' => '/app/plugin_dflow_maps.php',
                        'icon' => 'share-2',
                        'sub' => []
                    ]]
                ];
            }
            continue; 
        }
        
        // Custom logic for plugins with submenus or different entry points
        $sub = [];
        if ($p['name'] === 'veeam') {
            $sub = [
                ['label' => 'Dashboard', 'url' => '/app/plugin_veeam.php'],
                ['label' => 'Jobs', 'url' => '/app/plugin_veeam_jobs.php'],
                ['label' => 'Repositórios', 'url' => '/app/plugin_veeam_repos.php']
            ];
        } elseif ($p['name'] === 'snmp') {
            $sub = [
                ['label' => 'Dashboard', 'url' => '/app/plugin_snmp.php'],
                ['label' => 'Devices', 'url' => '/app/plugin_snmp_devices.php'],
                ['label' => 'Interfaces', 'url' => '/app/plugin_snmp_interfaces.php'],
                ['label' => 'Discovery', 'url' => '/app/plugin_snmp_discovery.php']
            ];
        } elseif (in_array($p['name'], ['dflow', 'deepflow', 'netflow'])) {
            $sub = [
                ['label' => 'Dashboard', 'url' => "/app/plugin_{$p['name']}.php"],
                ['label' => 'Alertas', 'url' => "/app/plugin_{$p['name']}_alerts.php"],
                ['label' => 'Hosts', 'url' => "/app/plugin_{$p['name']}_hosts.php"],
                ['label' => 'Flows', 'url' => "/app/plugin_{$p['name']}_flows.php"],
                ['label' => 'Maps', 'url' => "/app/plugin_{$p['name']}_maps.php"],
                ['label' => 'Interfaces', 'url' => "/app/plugin_{$p['name']}_interfaces.php"]
            ];
        }

        $groupedMenus[$category]['plugins'][] = [
            'name' => (string)$p['name'],
            'label' => (string)$p['label'],
            'url' => $url,
            'icon' => (string)$p['icon'],
            'sub' => $sub
        ];
    }

    // Sort categories by priority
    $priority = [
        'Redes' => 1,
        'Segurança' => 2,
        'Virtualização' => 3,
        'Monitoramento' => 4,
        'Backup' => 5,
        'Acesso Remoto' => 6,
        'Hospedagem' => 7,
        'Email' => 8,
        'Inteligência' => 9
    ];

    uksort($groupedMenus, function($a, $b) use ($priority) {
        $pA = $priority[$a] ?? 99;
        $pB = $priority[$b] ?? 99;
        if ($pA !== $pB) return $pA - $pB;
        return strcmp($a, $b);
    });

    // Deduplicate Hub links in Redes for attendants
    if ($user['role'] === 'atendente' && isset($groupedMenus['Redes'])) {
        $groupedMenus['Redes']['plugins'] = [[
            'name' => 'dflow_hub',
            'label' => 'Central de Redes (Hub)',
            'url' => '/app/plugin_dflow_maps.php',
            'icon' => 'share-2',
            'sub' => []
        ]];
    }

    return $groupedMenus;
}

