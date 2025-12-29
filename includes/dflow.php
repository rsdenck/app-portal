<?php
declare(strict_types=1);

/**
 * Ensures DFlow specific tables exist.
 * 
 * @param PDO $pdo
 * @return void
 */
function dflow_ensure_tables(PDO $pdo): void
{
    // Devices table
    $sqlDevices = "CREATE TABLE IF NOT EXISTS plugin_dflow_devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL UNIQUE,
        hostname VARCHAR(255),
        description TEXT,
        vendor VARCHAR(100),
        model VARCHAR(100),
        uptime BIGINT,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlDevices);

    // Interfaces table
    $sqlInterfaces = "CREATE TABLE IF NOT EXISTS plugin_dflow_interfaces (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_ip VARCHAR(45),
        if_index INT,
        name VARCHAR(100) NOT NULL,
        description VARCHAR(255),
        mac_address VARCHAR(17),
        vlan INT DEFAULT 0,
        speed BIGINT,
        status ENUM('up', 'down', 'unknown') DEFAULT 'unknown',
        in_bytes BIGINT DEFAULT 0,
        out_bytes BIGINT DEFAULT 0,
        in_packets BIGINT DEFAULT 0,
        out_packets BIGINT DEFAULT 0,
        ip_address VARCHAR(45),
        asn INT,
        bgp_status ENUM('up', 'down', 'disabled') DEFAULT 'disabled',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY idx_dev_if (device_ip, if_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlInterfaces);

    // Blocked IPs table
    $sqlBlocked = "CREATE TABLE IF NOT EXISTS plugin_dflow_blocked_ips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL UNIQUE,
        reason TEXT,
        expires_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlBlocked);

    // Flows table (Historical data) - Aligned with dflow_ingestor
    $sqlFlows = "CREATE TABLE IF NOT EXISTS plugin_dflow_flows (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        src_ip VARCHAR(45) NOT NULL,
        dst_ip VARCHAR(45) NOT NULL,
        src_mac VARCHAR(17),
        dst_mac VARCHAR(17),
        vlan INT DEFAULT 0,
        src_port INT,
        dst_port INT,
        proto VARCHAR(10),
        protocol VARCHAR(10),
        bytes BIGINT DEFAULT 0,
        pkts INT DEFAULT 0,
        packets INT DEFAULT 0,
        bps INT DEFAULT 0,
        is_encrypted TINYINT(1) DEFAULT 0,
        app_proto VARCHAR(50),
        l7_proto VARCHAR(50),
        sni VARCHAR(255),
        ja3_hash VARCHAR(32),
        anomaly_type VARCHAR(100),
        cve_id VARCHAR(50),
        threat_score INT DEFAULT 0,
        direction ENUM('in', 'out') DEFAULT 'in',
        tcp_flags INT DEFAULT 0,
        rtt_ms FLOAT,
        eth_type VARCHAR(10),
        pcp INT DEFAULT 0,
        ts TIMESTAMP NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_timestamp (timestamp),
        INDEX idx_ts (ts),
        INDEX idx_src_ip (src_ip),
        INDEX idx_dst_ip (dst_ip),
        INDEX idx_src_mac (src_mac),
        INDEX idx_vlan (vlan)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlFlows);

    // Hosts table (Inventory)
    $sqlHosts = "CREATE TABLE IF NOT EXISTS plugin_dflow_hosts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL UNIQUE,
        mac_address VARCHAR(17),
        hostname VARCHAR(255),
        vlan INT DEFAULT 0,
        threat_score INT DEFAULT 0,
        first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        bytes_sent BIGINT DEFAULT 0,
        bytes_received BIGINT DEFAULT 0,
        throughput_in INT DEFAULT 0,
        throughput_out INT DEFAULT 0,
        INDEX idx_mac (mac_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlHosts);
    
    // Topology table (for Force-directed graph)
    $sqlTopology = "CREATE TABLE IF NOT EXISTS plugin_dflow_topology (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_node VARCHAR(100) NOT NULL,
        target_node VARCHAR(100) NOT NULL,
        weight INT DEFAULT 1,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY idx_edge (source_node, target_node)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlTopology);

    // BGP Prefixes table for correlation
    $sqlBgp = "CREATE TABLE IF NOT EXISTS plugin_dflow_bgp_prefixes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prefix VARCHAR(50) NOT NULL,
        asn INT NOT NULL,
        as_name VARCHAR(255),
        source ENUM('routeviews', 'ripe', 'local') DEFAULT 'local',
        last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_prefix (prefix),
        UNIQUE KEY idx_prefix_asn (prefix, asn)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlBgp);

    // Threat Intel table
    $sqlThreat = "CREATE TABLE IF NOT EXISTS plugin_dflow_threat_intel (
        id INT AUTO_INCREMENT PRIMARY KEY,
        indicator VARCHAR(255) NOT NULL,
        type ENUM('ip', 'domain', 'ja3') DEFAULT 'ip',
        category VARCHAR(100),
        threat_score INT DEFAULT 0,
        source VARCHAR(100),
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_indicator (indicator)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlThreat);

    // MITRE ATT&CK Mapping
    $sqlMitre = "CREATE TABLE IF NOT EXISTS plugin_dflow_mitre_mapping (
        id INT AUTO_INCREMENT PRIMARY KEY,
        technique_id VARCHAR(20) NOT NULL UNIQUE,
        technique_name VARCHAR(255),
        tactic VARCHAR(100),
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlMitre);

    // L7 Protocols table
    $sqlL7 = "CREATE TABLE IF NOT EXISTS plugin_dflow_l7_protocols (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        description VARCHAR(255),
        is_suspicious TINYINT(1) DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlL7);

    // System Metrics Table (Observability)
    $sqlMetrics = "CREATE TABLE IF NOT EXISTS plugin_dflow_system_metrics (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        thread_id INT,
        processed_packets BIGINT,
        processed_bytes BIGINT,
        dropped_packets BIGINT,
        active_sessions INT,
        total_flows BIGINT,
        hash_collisions BIGINT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_timestamp (timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlMetrics);

    // Alerts table for DFlow and Network notifications
    $sqlAlerts = "CREATE TABLE IF NOT EXISTS plugin_dflow_alerts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(100) NOT NULL,
        severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
        subject VARCHAR(255) NOT NULL,
        description TEXT,
        target_ip VARCHAR(45),
        source_ip VARCHAR(45),
        ticket_id BIGINT UNSIGNED NULL,
        status ENUM('active', 'resolved', 'muted') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL,
        INDEX idx_status (status),
        INDEX idx_created (created_at),
        INDEX idx_ticket (ticket_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlAlerts);

    // Deep Analysis table (Packet-level insights)
    $sqlDeep = "CREATE TABLE IF NOT EXISTS plugin_dflow_deep_analysis (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        flow_id BIGINT,
        ip_address VARCHAR(45),
        protocol VARCHAR(50),
        analysis_type VARCHAR(100), -- e.g., 'HTTP', 'TLS', 'DNS', 'Security'
        detail_key VARCHAR(100),    -- e.g., 'user_agent', 'cert_issuer', 'query_name'
        detail_value TEXT,
        severity ENUM('info', 'low', 'medium', 'high', 'critical') DEFAULT 'info',
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_flow (flow_id),
        INDEX idx_ip (ip_address),
        INDEX idx_type (analysis_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlDeep);

    // Baseline table for VLAN analysis
    $sqlBaselines = "CREATE TABLE IF NOT EXISTS plugin_dflow_baselines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vlan_id INT NOT NULL,
        hour_of_day TINYINT NOT NULL,
        avg_bytes BIGINT UNSIGNED DEFAULT 0,
        stddev_bytes BIGINT UNSIGNED DEFAULT 0,
        avg_packets BIGINT UNSIGNED DEFAULT 0,
        stddev_packets BIGINT UNSIGNED DEFAULT 0,
        sample_count INT UNSIGNED DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY vlan_hour (vlan_id, hour_of_day)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlBaselines);
}
