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
        snmp_community VARCHAR(100) DEFAULT 'public',
        snmp_version VARCHAR(10) DEFAULT '2c',
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

    // Flows table (Historical data)
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
        vendor VARCHAR(100),
        os_info VARCHAR(255),
        vlan INT DEFAULT 0,
        threat_score INT DEFAULT 0,
        first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        bytes_sent BIGINT DEFAULT 0,
        bytes_received BIGINT DEFAULT 0,
        throughput_in INT DEFAULT 0,
        throughput_out INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        INDEX idx_mac (mac_address),
        INDEX idx_ip (ip_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlHosts);

    // IP Blocks for Scanning
    $sqlBlocks = "CREATE TABLE IF NOT EXISTS plugin_dflow_ip_blocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cidr VARCHAR(50) NOT NULL UNIQUE,
        description VARCHAR(255),
        is_active TINYINT(1) DEFAULT 1,
        last_scan TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlBlocks);

    // IP Scanning Results
    $sqlScanning = "CREATE TABLE IF NOT EXISTS plugin_dflow_ip_scanning (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL UNIQUE,
        block_id INT,
        status ENUM('active', 'inactive', 'unknown') DEFAULT 'unknown',
        open_ports VARCHAR(255),
        last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (block_id) REFERENCES plugin_dflow_ip_blocks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlScanning);

    // VLANs table
    $sqlVlans = "CREATE TABLE IF NOT EXISTS plugin_dflow_vlans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_ip VARCHAR(45) NOT NULL,
        vlan_id INT NOT NULL,
        vlan_name VARCHAR(100),
        vlan_status VARCHAR(20) DEFAULT 'active',
        vlan_type VARCHAR(50) DEFAULT 'static',
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY idx_dev_vlan (device_ip, vlan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlVlans);

    // Topology table (for Force-directed graph)
    $sqlTopology = "CREATE TABLE IF NOT EXISTS plugin_dflow_topology (
        id INT AUTO_INCREMENT PRIMARY KEY,
        local_device_ip VARCHAR(45),
        local_port_index INT,
        remote_device_name VARCHAR(100),
        remote_port_id VARCHAR(100),
        protocol VARCHAR(20),
        source_node VARCHAR(100),
        target_node VARCHAR(100),
        weight INT DEFAULT 1,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY idx_topo (local_device_ip, local_port_index, remote_device_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlTopology);

    // Anomaly Detection table
    $sqlAnomalies = "CREATE TABLE IF NOT EXISTS plugin_dflow_anomalies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(100),
        severity ENUM('low', 'medium', 'high', 'critical'),
        source_ip VARCHAR(45),
        target_ip VARCHAR(45),
        description TEXT,
        mitre_tactic VARCHAR(100),
        mitre_technique VARCHAR(100),
        status ENUM('open', 'investigating', 'closed') DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlAnomalies);
}
