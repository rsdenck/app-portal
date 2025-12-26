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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);

    // Cache table for ASN and GeoIP
    $sqlCache = "CREATE TABLE IF NOT EXISTS plugin_cache (
        cache_key VARCHAR(255) PRIMARY KEY,
        cache_value JSON NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlCache);

    // Persist table for NSX collected data
    $sqlNsx = "CREATE TABLE IF NOT EXISTS plugin_nsx_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data_type VARCHAR(50) NOT NULL UNIQUE,
        data_content JSON NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlNsx);

    // Persist table for vCenter collected data
    $sqlVcenter = "CREATE TABLE IF NOT EXISTS plugin_vcenter_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data_type VARCHAR(50) NOT NULL UNIQUE,
        data_content JSON NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlVcenter);

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

    // Seed initial plugins if not exists
    $initialPlugins = [
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
        if (!isset($groupedMenus[$category])) {
            $groupedMenus[$category] = [
                'label' => $category,
                'icon' => $p['icon'] ?: 'box',
                'plugins' => []
            ];
        }

        // Standardize URLs and labels
        $url = "/app/plugin_" . $p['name'] . ".php";
        
        // Custom logic for plugins with submenus or different entry points
        $sub = [];
        if ($p['name'] === 'veeam') {
            $sub = [
                ['label' => 'Dashboard', 'url' => '/app/plugin_veeam.php'],
                ['label' => 'Jobs', 'url' => '/app/plugin_veeam_jobs.php'],
                ['label' => 'Repositórios', 'url' => '/app/plugin_veeam_repos.php']
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

    return $groupedMenus;
}

