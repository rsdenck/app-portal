<?php

function user_find_by_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT id, role, name, email, password_hash, theme FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function user_find_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, role, name, email, theme FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function user_create(PDO $pdo, string $role, string $name, string $email, string $password): int
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO users (role, name, email, password_hash) VALUES (?,?,?,?)');
        $stmt->execute([$role, $name, $email, $hash]);
        $id = (int)$pdo->lastInsertId();

        if ($role === 'cliente') {
            $pdo->prepare('INSERT INTO client_profiles (user_id, company_name, document, phone) VALUES (?,?,?,?)')
                ->execute([$id, '', '', '']);
        }
        if ($role === 'atendente') {
            $pdo->prepare('INSERT INTO attendant_profiles (user_id, department) VALUES (?,?)')
                ->execute([$id, '']);
        }

        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function user_update_name(PDO $pdo, int $id, string $name): void
{
    $stmt = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
    $stmt->execute([$name, $id]);
}

function user_update_password(PDO $pdo, int $id, string $password): void
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([$hash, $id]);
}

function user_update_theme(PDO $pdo, int $id, string $theme): void
{
    $stmt = $pdo->prepare('UPDATE users SET theme = ? WHERE id = ?');
    $stmt->execute([$theme, $id]);
}

function user_ensure_schema(PDO $pdo): void
{
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN theme VARCHAR(20) NOT NULL DEFAULT 'dark'");
    } catch (PDOException $e) {
        $info = $e->errorInfo;
        if (!is_array($info) || (int)($info[1] ?? 0) !== 1060) {
            throw $e;
        }
    }
}

function auth_build_session_user(array $row): array
{
    $user = [
        'id' => (int)$row['id'],
        'role' => (string)$row['role'],
        'name' => (string)$row['name'],
        'email' => (string)$row['email'],
        'theme' => (string)($row['theme'] ?? 'dark'),
    ];
    if (($user['role'] ?? null) === 'cliente') {
        $user['tenant_id'] = (int)$user['id'];
    }
    return $user;
}

function auth_login(PDO $pdo, string $email, string $password, ?string $requiredRole = null): ?array
{
    $row = user_find_by_email($pdo, $email);
    if (!$row) {
        return null;
    }
    if ($requiredRole !== null && (string)($row['role'] ?? null) !== $requiredRole) {
        return null;
    }
    if (!password_verify($password, (string)$row['password_hash'])) {
        return null;
    }
    
    // Proteção contra Session Fixation
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $user = auth_build_session_user($row);
    
    if ($user['role'] === 'atendente') {
        $stmt = $pdo->prepare('SELECT category_id, category_id_2 FROM attendant_profiles WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();
        $categories = [];
        if ($profile) {
            if ($profile['category_id']) $categories[] = (int)$profile['category_id'];
            if ($profile['category_id_2']) $categories[] = (int)$profile['category_id_2'];
        }
        $user['categories'] = $categories;
    }

    $_SESSION['user'] = $user;
    audit_log($pdo, $user, 'login', []);
    return $user;
}

function attendant_list(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT u.id, u.name, u.email, ap.department, ap.category_id, ap.category_id_2
         FROM users u
         LEFT JOIN attendant_profiles ap ON ap.user_id = u.id
         WHERE u.role = 'atendente'
         ORDER BY u.name ASC"
    );
    return $stmt->fetchAll();
}

function ticket_status_id(PDO $pdo, string $slug): int
{
    $stmt = $pdo->prepare('SELECT id FROM ticket_statuses WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $id = $stmt->fetchColumn();
    return (int)$id;
}

function ticket_categories(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name, slug, schema_json FROM ticket_categories ORDER BY name ASC');
    return $stmt->fetchAll();
}

function ticket_counts_global(PDO $pdo): array
{
    $sql = "SELECT ts.slug, COUNT(*) AS total
            FROM tickets t
            JOIN ticket_statuses ts ON ts.id = t.status_id
            GROUP BY ts.slug";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
    $result = [
        'aberto' => 0,
        'fechado' => 0,
        'agendado' => 0,
        'contestado' => 0,
        'suspenso' => 0,
        'encerrado' => 0,
        'aguardando_cotacao' => 0,
    ];
    foreach ($rows as $row) {
        $slug = (string)($row['slug'] ?? '');
        $total = (int)($row['total'] ?? 0);
        if ($slug !== '' && array_key_exists($slug, $result)) {
            $result[$slug] = $total;
        }
    }
    return $result;
}

function ticket_counts_for_client(PDO $pdo, int $clientUserId): array
{
    $sql = "SELECT ts.slug, COUNT(*) AS total
            FROM tickets t
            JOIN ticket_statuses ts ON ts.id = t.status_id
            WHERE t.client_user_id = ?
            GROUP BY ts.slug";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$clientUserId]);
    $rows = $stmt->fetchAll();
    $result = [
        'aberto' => 0,
        'fechado' => 0,
        'agendado' => 0,
        'contestado' => 0,
        'suspenso' => 0,
        'encerrado' => 0,
        'aguardando_cotacao' => 0,
    ];
    foreach ($rows as $row) {
        $slug = (string)($row['slug'] ?? '');
        $total = (int)($row['total'] ?? 0);
        if ($slug !== '' && array_key_exists($slug, $result)) {
            $result[$slug] = $total;
        }
    }
    return $result;
}

function ticket_volume_last_days(PDO $pdo, int $days): array
{
    if ($days < 1) {
        $days = 1;
    }
    $dt = new DateTimeImmutable('today');
    $from = $dt->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
    $sql = "SELECT DATE(created_at) AS day, COUNT(*) AS total
            FROM tickets
            WHERE created_at >= ?
            GROUP BY DATE(created_at)
            ORDER BY day ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$from]);
    $rows = $stmt->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        $day = (string)($row['day'] ?? '');
        $count = (int)($row['total'] ?? 0);
        if ($day !== '') {
            $map[$day] = $count;
        }
    }
    $result = [];
    for ($i = 0; $i < $days; $i++) {
        $d = $dt->modify('-' . ($days - 1 - $i) . ' days')->format('Y-m-d');
        $result[] = ['day' => $d, 'total' => $map[$d] ?? 0];
    }
    return $result;
}

function ticket_volume_last_days_for_client(PDO $pdo, int $clientUserId, int $days): array
{
    if ($days < 1) {
        $days = 1;
    }
    $dt = new DateTimeImmutable('today');
    $from = $dt->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
    $sql = "SELECT DATE(created_at) AS day, COUNT(*) AS total
            FROM tickets
            WHERE created_at >= ? AND client_user_id = ?
            GROUP BY DATE(created_at)
            ORDER BY day ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$from, $clientUserId]);
    $rows = $stmt->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        $day = (string)($row['day'] ?? '');
        $count = (int)($row['total'] ?? 0);
        if ($day !== '') {
            $map[$day] = $count;
        }
    }
    $result = [];
    for ($i = 0; $i < $days; $i++) {
        $d = $dt->modify('-' . ($days - 1 - $i) . ' days')->format('Y-m-d');
        $result[] = ['day' => $d, 'total' => $map[$d] ?? 0];
    }
    return $result;
}

function ticket_category(PDO $pdo, int $categoryId): ?array
{
    $stmt = $pdo->prepare('SELECT id, name, slug, schema_json FROM ticket_categories WHERE id = ? LIMIT 1');
    $stmt->execute([$categoryId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function ticket_list_for_client(PDO $pdo, int $clientUserId): array
{
    $stmt = $pdo->prepare(
        "SELECT t.id, t.subject, t.created_at, t.first_response_at, t.closed_at, t.category_id, t.status_id, ts.name AS status_name, ts.slug AS status_slug, tc.name AS category_name, u.name AS assigned_name
         FROM tickets t
         JOIN ticket_statuses ts ON ts.id = t.status_id
         JOIN ticket_categories tc ON tc.id = t.category_id
         LEFT JOIN users u ON u.id = t.assigned_user_id
         WHERE t.client_user_id = ?
         ORDER BY t.id DESC"
    );
    $stmt->execute([$clientUserId]);
    return $stmt->fetchAll();
}

function ticket_list_inbox(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT t.id, t.subject, t.created_at, t.first_response_at, t.closed_at, t.category_id, t.status_id, t.assigned_user_id, t.client_user_id, t.priority, ts.name AS status_name, ts.slug AS status_slug, tc.name AS category_name, c.name AS client_name, a.name AS assigned_name
         FROM tickets t
         JOIN ticket_statuses ts ON ts.id = t.status_id
         JOIN ticket_categories tc ON tc.id = t.category_id
         JOIN users c ON c.id = t.client_user_id
         LEFT JOIN users a ON a.id = t.assigned_user_id
         WHERE t.first_response_at IS NULL AND ts.slug NOT IN ('encerrado', 'fechado')
         ORDER BY t.id DESC"
    );
    return $stmt->fetchAll();
}

function ticket_list_active(PDO $pdo, ?int $assignedUserId = null): array
{
    $sql = "SELECT t.id, t.subject, t.created_at, t.first_response_at, t.closed_at, t.category_id, t.status_id, t.assigned_user_id, t.client_user_id, t.priority, ts.name AS status_name, ts.slug AS status_slug, tc.name AS category_name, c.name AS client_name, a.name AS assigned_name
            FROM tickets t
            JOIN ticket_statuses ts ON ts.id = t.status_id
            JOIN ticket_categories tc ON tc.id = t.category_id
            JOIN users c ON c.id = t.client_user_id
            LEFT JOIN users a ON a.id = t.assigned_user_id
            WHERE (t.first_response_at IS NOT NULL OR ts.slug IN ('encerrado', 'fechado'))";
    
    if ($assignedUserId !== null) {
        $sql .= " AND (t.assigned_user_id = ? OR t.assigned_user_id IS NULL)";
        $stmt = $pdo->prepare($sql . " ORDER BY t.id DESC");
        $stmt->execute([$assignedUserId]);
    } else {
        $stmt = $pdo->query($sql . " ORDER BY t.id DESC");
    }
    
    return $stmt->fetchAll();
}

function ticket_comment_create(PDO $pdo, int $ticketId, int $userId, string $content): int
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO ticket_comments (ticket_id, user_id, content) VALUES (?,?,?)');
        $stmt->execute([$ticketId, $userId, $content]);
        $commentId = (int)$pdo->lastInsertId();

        // Log interaction in history
        $payload = json_encode(['comment_id' => $commentId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pdo->prepare('INSERT INTO ticket_history (ticket_id, actor_user_id, action, payload_json) VALUES (?,?,?,?)')
            ->execute([$ticketId, $userId, 'comment', $payload ?: '{}']);

        // Check if this is the first response from an attendant
        $stmtUser = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch();

        if ($user && $user['role'] === 'atendente') {
            $stmtTicket = $pdo->prepare('SELECT first_response_at, assigned_user_id FROM tickets WHERE id = ?');
            $stmtTicket->execute([$ticketId]);
            $ticketRow = $stmtTicket->fetch();

            if ($ticketRow) {
                $updates = [];
                $params = [];
                
                if ($ticketRow['first_response_at'] === null) {
                    $updates[] = 'first_response_at = NOW()';
                }
                
                if ($ticketRow['assigned_user_id'] === null) {
                    $updates[] = 'assigned_user_id = ?';
                    $params[] = $userId;
                }
                
                if ($updates) {
                    $updates[] = 'updated_at = NOW()';
                    $sql = 'UPDATE tickets SET ' . implode(', ', $updates) . ' WHERE id = ?';
                    $params[] = $ticketId;
                    $stmtUpdate = $pdo->prepare($sql);
                    $stmtUpdate->execute($params);
                }
            }
        } else {
            // If client interacts, just update updated_at
            $stmtUpdate = $pdo->prepare('UPDATE tickets SET updated_at = NOW() WHERE id = ?');
            $stmtUpdate->execute([$ticketId]);
        }

        $pdo->commit();
        return $commentId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function ticket_comments_list(PDO $pdo, int $ticketId): array
{
    $stmt = $pdo->prepare(
        "SELECT tc.*, u.name AS user_name, u.role AS user_role
         FROM ticket_comments tc
         JOIN users u ON u.id = tc.user_id
         WHERE tc.ticket_id = ?
         ORDER BY tc.created_at ASC"
    );
    $stmt->execute([$ticketId]);
    return $stmt->fetchAll();
}

function ticket_attachment_create(PDO $pdo, int $userId, string $fileName, string $filePath, string $fileType, int $fileSize, ?int $ticketId = null, ?int $commentId = null): int
{
    $stmt = $pdo->prepare('INSERT INTO ticket_attachments (ticket_id, comment_id, user_id, file_name, file_path, file_type, file_size) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([$ticketId, $commentId, $userId, $fileName, $filePath, $fileType, $fileSize]);
    return (int)$pdo->lastInsertId();
}

function ticket_attachments_list(PDO $pdo, int $ticketId): array
{
    $stmt = $pdo->prepare('SELECT * FROM ticket_attachments WHERE ticket_id = ? OR comment_id IN (SELECT id FROM ticket_comments WHERE ticket_id = ?) ORDER BY created_at ASC');
    $stmt->execute([$ticketId, $ticketId]);
    return $stmt->fetchAll();
}

function ticket_ensure_schema(PDO $pdo): void
{
    try {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN priority ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium'");
    } catch (PDOException $e) {
        $info = $e->errorInfo;
        $code = is_array($info) ? (int)($info[1] ?? 0) : 0;
        if ($code !== 1060) {
            throw $e;
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN first_response_at TIMESTAMP NULL DEFAULT NULL");
    } catch (PDOException $e) {
        $info = $e->errorInfo;
        $code = is_array($info) ? (int)($info[1] ?? 0) : 0;
        if ($code !== 1060) {
            throw $e;
        }
    }

    ticket_unread_ensure_schema($pdo);
    ticket_attachments_ensure_schema($pdo);
}

function ticket_attachments_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_comments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ticket_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_ticket_comments_ticket (ticket_id),
        CONSTRAINT fk_ticket_comments_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
        CONSTRAINT fk_ticket_comments_user FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_attachments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ticket_id BIGINT UNSIGNED NULL,
        comment_id BIGINT UNSIGNED NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        file_size INT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_ticket_attachments_ticket (ticket_id),
        KEY idx_ticket_attachments_comment (comment_id),
        CONSTRAINT fk_ticket_attachments_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
        CONSTRAINT fk_ticket_attachments_comment FOREIGN KEY (comment_id) REFERENCES ticket_comments(id) ON DELETE CASCADE,
        CONSTRAINT fk_ticket_attachments_user FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ticket_calculate_metrics(array $ticket, ?PDO $pdo = null): array
{
    $createdAtStr = (string)($ticket['created_at'] ?? '');
    $firstResponseAtStr = (string)($ticket['first_response_at'] ?? '');
    $closedAtStr = (string)($ticket['closed_at'] ?? '');

    $createdAt = !empty($createdAtStr) ? new DateTime($createdAtStr) : new DateTime();
    $firstResponseAt = !empty($firstResponseAtStr) ? new DateTime($firstResponseAtStr) : null;
    $closedAt = !empty($closedAtStr) ? new DateTime($closedAtStr) : null;
    $now = new DateTime();

    // SLI: Time to first response
    $sliSeconds = 0;
    if ($firstResponseAt) {
        $sliSeconds = $firstResponseAt->getTimestamp() - $createdAt->getTimestamp();
    } elseif ($closedAt) {
        $sliSeconds = $closedAt->getTimestamp() - $createdAt->getTimestamp();
    } else {
        $sliSeconds = $now->getTimestamp() - $createdAt->getTimestamp();
    }

    // SLO: Target (defaults based on priority if no company SLA)
    $priority = (string)($ticket['priority'] ?? 'medium');
    $sloSeconds = 28800; // Default 8h (Medium)
    if ($priority === 'critical') $sloSeconds = 3600;      // 1h
    if ($priority === 'high') $sloSeconds = 14400;         // 4h
    if ($priority === 'low') $sloSeconds = 86400;          // 24h
    
    if ($pdo && isset($ticket['client_user_id'])) {
        // Try to get company SLA
        try {
            $stmt = $pdo->prepare("
                SELECT c.* 
                FROM companies c
                JOIN client_profiles cp ON cp.company_id = c.id
                WHERE cp.user_id = ?
                LIMIT 1
            ");
            $stmt->execute([(int)$ticket['client_user_id']]);
            $company = $stmt->fetch();
            
            if ($company) {
                $col = "sla_response_{$priority}_minutes";
                if (isset($company[$col]) && (int)$company[$col] > 0) {
                    $sloSeconds = (int)$company[$col] * 60;
                }
            }
        } catch (Exception $e) {
            // Fallback to default SLO
        }
    }

    // SLA Status based on SLO
    $isWithinSla = ($sliSeconds <= $sloSeconds);
    $slaStatus = $isWithinSla ? 'Dentro do Prazo' : 'Atrasado';

    return array_merge($ticket, [
        'sli_seconds' => (int)$sliSeconds,
        'sli_formatted' => format_duration((int)$sliSeconds),
        'slo_seconds' => (int)$sloSeconds,
        'slo_formatted' => format_duration((int)$sloSeconds),
        'sla_status' => $slaStatus,
        'is_within_sla' => $isWithinSla,
        'is_responded' => $firstResponseAt !== null
    ]);
}

function format_duration(int $seconds): string
{
    if ($seconds < 60) return $seconds . 's';
    $minutes = floor($seconds / 60);
    if ($minutes < 60) return $minutes . 'm ' . ($seconds % 60) . 's';
    $hours = floor($minutes / 60);
    if ($hours < 24) return $hours . 'h ' . ($minutes % 60) . 'm';
    $days = floor($hours / 24);
    return $days . 'd ' . ($hours % 24) . 'h';
}

function format_rate($bps): string
{
    if ($bps === null || !is_numeric($bps)) return 'N/A';
    $bps = (float)$bps;
    if ($bps < 1000) return number_format($bps, 0) . ' bps';
    $kbps = $bps / 1000;
    if ($kbps < 1000) return number_format($kbps, 2) . ' Kbps';
    $mbps = $kbps / 1000;
    return number_format($mbps, 2) . ' Mbps';
}

function ticket_list_for_attendant(PDO $pdo, ?int $assignedUserId = null): array
{
    if ($assignedUserId === null) {
        $stmt = $pdo->query(
            "SELECT t.id, t.subject, t.created_at, t.first_response_at, t.closed_at, t.category_id, t.status_id, t.assigned_user_id, ts.name AS status_name, tc.name AS category_name, c.name AS client_name, a.name AS assigned_name
             FROM tickets t
             JOIN ticket_statuses ts ON ts.id = t.status_id
             JOIN ticket_categories tc ON tc.id = t.category_id
             JOIN users c ON c.id = t.client_user_id
             LEFT JOIN users a ON a.id = t.assigned_user_id
             ORDER BY t.id DESC"
        );
        return $stmt->fetchAll();
    }

    $stmt = $pdo->prepare(
        "SELECT t.id, t.subject, t.created_at, t.first_response_at, t.closed_at, t.category_id, t.status_id, t.assigned_user_id, ts.name AS status_name, tc.name AS category_name, c.name AS client_name, a.name AS assigned_name
         FROM tickets t
         JOIN ticket_statuses ts ON ts.id = t.status_id
         JOIN ticket_categories tc ON tc.id = t.category_id
         JOIN users c ON c.id = t.client_user_id
         LEFT JOIN users a ON a.id = t.assigned_user_id
         WHERE t.assigned_user_id = ?
         ORDER BY t.id DESC"
    );
    $stmt->execute([$assignedUserId]);
    return $stmt->fetchAll();
}

function ticket_find(PDO $pdo, int $ticketId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT t.*, ts.slug AS status_slug, ts.name AS status_name, tc.name AS category_name, c.name AS client_name, a.name AS assigned_name
         FROM tickets t
         JOIN ticket_statuses ts ON ts.id = t.status_id
         JOIN ticket_categories tc ON tc.id = t.category_id
         JOIN users c ON c.id = t.client_user_id
         LEFT JOIN users a ON a.id = t.assigned_user_id
         WHERE t.id = ? LIMIT 1"
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function ticket_create(PDO $pdo, int $clientUserId, int $categoryId, string $subject, string $description, array $extra, ?int $assignedUserId = null, string $priority = 'medium'): int
{
    $statusId = ticket_status_id($pdo, 'aberto');
    $extraJson = json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($extraJson)) {
        $extraJson = '{}';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO tickets (client_user_id, assigned_user_id, category_id, status_id, subject, description, extra_json, priority) VALUES (?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([$clientUserId, $assignedUserId, $categoryId, $statusId, $subject, $description, $extraJson, $priority]);
    $ticketId = (int)$pdo->lastInsertId();

    // Project Integration
    $stmtCat = $pdo->prepare('SELECT slug FROM ticket_categories WHERE id = ?');
    $stmtCat->execute([$categoryId]);
    $cat = $stmtCat->fetch();
    if ($cat && $cat['slug'] === 'projetos') {
        if (function_exists('project_create')) {
            project_create($pdo, $subject, $ticketId);
        }
    }

    return $ticketId;
}

function ticket_assign(PDO $pdo, int $ticketId, ?int $assignedUserId, int $actorUserId): void
{
    $stmt = $pdo->prepare('UPDATE tickets SET assigned_user_id = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$assignedUserId, $ticketId]);

    $payload = json_encode(['assigned_user_id' => $assignedUserId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo->prepare('INSERT INTO ticket_history (ticket_id, actor_user_id, action, payload_json) VALUES (?,?,?,?)')
        ->execute([$ticketId, $actorUserId, 'assign', $payload ?: '{}']);
}

function ticket_close(PDO $pdo, int $ticketId, int $actorUserId): void
{
    $statusId = ticket_status_id($pdo, 'encerrado');
    $stmt = $pdo->prepare('UPDATE tickets SET status_id = ?, closed_at = NOW(), updated_at = NOW() WHERE id = ?');
    $stmt->execute([$statusId, $ticketId]);

    $payload = json_encode(['status' => 'encerrado'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo->prepare('INSERT INTO ticket_history (ticket_id, actor_user_id, action, payload_json) VALUES (?,?,?,?)')
        ->execute([$ticketId, $actorUserId, 'close', $payload ?: '{}']);
}

function ticket_update_status(PDO $pdo, int $ticketId, int $statusId, int $actorUserId): void
{
    $stmt = $pdo->prepare('UPDATE tickets SET status_id = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$statusId, $ticketId]);

    $payload = json_encode(['status_id' => $statusId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo->prepare('INSERT INTO ticket_history (ticket_id, actor_user_id, action, payload_json) VALUES (?,?,?,?)')
        ->execute([$ticketId, $actorUserId, 'update_status', $payload ?: '{}']);
}

function ticket_statuses(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name, slug FROM ticket_statuses ORDER BY name ASC');
    return $stmt->fetchAll();
}

function ticket_history(PDO $pdo, int $ticketId): array
{
    $stmt = $pdo->prepare(
        "SELECT th.*, u.name AS actor_name
         FROM ticket_history th
         JOIN users u ON u.id = th.actor_user_id
         WHERE th.ticket_id = ?
         ORDER BY th.created_at DESC"
    );
    $stmt->execute([$ticketId]);
    return $stmt->fetchAll();
}

function ticket_sla_stats_for_attendants(PDO $pdo): array
{
    $sql = "SELECT
              t.assigned_user_id AS attendant_id,
              u.name AS attendant_name,
              COUNT(*) AS total_tickets,
              SUM(CASE WHEN t.closed_at IS NOT NULL THEN 1 ELSE 0 END) AS closed_tickets,
              AVG(CASE WHEN t.first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_response_at) END) AS avg_first_response_minutes,
              AVG(CASE WHEN t.closed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.closed_at) END) AS avg_resolution_minutes
            FROM tickets t
            LEFT JOIN users u ON u.id = t.assigned_user_id
            GROUP BY t.assigned_user_id, u.name
            ORDER BY total_tickets DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function ticket_sla_for_client(PDO $pdo, int $clientUserId): array
{
    $sql = "SELECT
              COUNT(*) AS total_tickets,
              SUM(CASE WHEN t.closed_at IS NOT NULL THEN 1 ELSE 0 END) AS closed_tickets,
              AVG(CASE WHEN t.closed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.closed_at) END) AS avg_resolution_minutes
            FROM tickets t
            WHERE t.client_user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$clientUserId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return [
            'total_tickets' => 0,
            'closed_tickets' => 0,
            'avg_resolution_minutes' => null,
        ];
    }
    $total = (int)($row['total_tickets'] ?? 0);
    $closed = (int)($row['closed_tickets'] ?? 0);
    $avg = $row['avg_resolution_minutes'];
    $avgMinutes = $avg !== null ? (float)$avg : null;
    return [
        'total_tickets' => $total,
        'closed_tickets' => $closed,
        'avg_resolution_minutes' => $avgMinutes,
    ];
}

function ticket_unread_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ticket_reads (
            user_id INT NOT NULL,
            ticket_id INT NOT NULL,
            last_read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, ticket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Ensure index for performance
    try {
        $pdo->exec("CREATE INDEX idx_ticket_comments_created_user ON ticket_comments(ticket_id, user_id, created_at)");
    } catch (PDOException $e) {
        // Index might already exist
    }
}

function ticket_mark_as_read(PDO $pdo, int $ticketId, int $userId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO ticket_reads (user_id, ticket_id, last_read_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE last_read_at = NOW()
    ");
    $stmt->execute([$userId, $ticketId]);
}

function ticket_has_unread(PDO $pdo, int $ticketId, int $userId): bool
{
    // A ticket has unread if:
    // 1. There are comments by OTHER users created after my last_read_at
    // 2. The ticket itself was created by ANOTHER user after my last_read_at
    
    // Check comments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM ticket_comments tc
        LEFT JOIN ticket_reads tr ON tr.ticket_id = tc.ticket_id AND tr.user_id = ?
        WHERE tc.ticket_id = ? 
          AND tc.user_id != ?
          AND (tr.last_read_at IS NULL OR tc.created_at > tr.last_read_at)
    ");
    $stmt->execute([$userId, $ticketId, $userId]);
    if ((int)$stmt->fetchColumn() > 0) return true;

    // Check ticket itself (if I'm not the creator)
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM tickets t
        LEFT JOIN ticket_reads tr ON tr.ticket_id = t.id AND tr.user_id = ?
        WHERE t.id = ?
          AND t.client_user_id != ?
          AND (tr.last_read_at IS NULL OR t.created_at > tr.last_read_at)
    ");
    $stmt->execute([$userId, $ticketId, $userId]);
    return (int)$stmt->fetchColumn() > 0;
}

function ticket_unread_count_global(PDO $pdo, int $userId): int
{
    // Count active tickets where there's at least one unread comment by others
    // OR the ticket itself is unread and was created by others
    
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT t.id)
        FROM tickets t
        JOIN ticket_statuses ts ON ts.id = t.status_id
        LEFT JOIN ticket_reads tr ON tr.ticket_id = t.id AND tr.user_id = ?
        WHERE ts.slug NOT IN ('encerrado', 'fechado')
          AND (
            -- Unread comments by others
            EXISTS (
                SELECT 1 FROM ticket_comments tc 
                WHERE tc.ticket_id = t.id 
                  AND tc.user_id != ? 
                  AND (tr.last_read_at IS NULL OR tc.created_at > tr.last_read_at)
            )
            OR
            -- Ticket itself unread (and not created by current user)
            (t.client_user_id != ? AND (tr.last_read_at IS NULL OR t.created_at > tr.last_read_at))
          )
    ");
    $stmt->execute([$userId, $userId, $userId]);
    return (int)$stmt->fetchColumn();
}

function ticket_unread_list_global(PDO $pdo, int $userId): array
{
    // Fetch unread tickets with categories
    $stmt = $pdo->prepare("
        SELECT DISTINCT t.id, t.subject, t.created_at, tc.name AS category_name
        FROM tickets t
        JOIN ticket_statuses ts ON ts.id = t.status_id
        JOIN ticket_categories tc ON tc.id = t.category_id
        LEFT JOIN ticket_reads tr ON tr.ticket_id = t.id AND tr.user_id = ?
        WHERE ts.slug NOT IN ('encerrado', 'fechado')
          AND (
            -- Unread comments by others
            EXISTS (
                SELECT 1 FROM ticket_comments tc2 
                WHERE tc2.ticket_id = t.id 
                  AND tc2.user_id != ? 
                  AND (tr.last_read_at IS NULL OR tc2.created_at > tr.last_read_at)
            )
            OR
            -- Ticket itself unread (and not created by current user)
            (t.client_user_id != ? AND (tr.last_read_at IS NULL OR t.created_at > tr.last_read_at))
          )
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId, $userId, $userId]);
    return $stmt->fetchAll();
}

function boleto_list_for_client(PDO $pdo, int $clientUserId): array
{
    $stmt = $pdo->prepare('SELECT id, reference, file_relative_path, created_at FROM boletos WHERE client_user_id = ? ORDER BY id DESC');
    $stmt->execute([$clientUserId]);
    return $stmt->fetchAll();
}

function boleto_find_for_client(PDO $pdo, int $boletoId, int $clientUserId): ?array
{
    $stmt = $pdo->prepare('SELECT id, reference, file_relative_path FROM boletos WHERE id = ? AND client_user_id = ? LIMIT 1');
    $stmt->execute([$boletoId, $clientUserId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function zbx_hostgroups_for_client(PDO $pdo, int $clientUserId): array
{
    $stmt = $pdo->prepare('SELECT id, hostgroupid, name FROM zabbix_hostgroups WHERE client_user_id = ? ORDER BY name ASC');
    $stmt->execute([$clientUserId]);
    return $stmt->fetchAll();
}

function zbx_hostgroup_add(PDO $pdo, int $clientUserId, string $hostgroupid, string $name): void
{
    $stmt = $pdo->prepare('INSERT INTO zabbix_hostgroups (client_user_id, hostgroupid, name) VALUES (?,?,?)');
    $stmt->execute([$clientUserId, $hostgroupid, $name]);
}

function zbx_hostgroup_find_for_client(PDO $pdo, int $clientUserId, string $hostgroupid): ?array
{
    $stmt = $pdo->prepare('SELECT id, hostgroupid, name FROM zabbix_hostgroups WHERE client_user_id = ? AND hostgroupid = ? LIMIT 1');
    $stmt->execute([$clientUserId, $hostgroupid]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function zbx_hostgroups_with_clients(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT z.client_user_id, z.hostgroupid, z.name, u.name AS client_name
         FROM zabbix_hostgroups z
         JOIN users u ON u.id = z.client_user_id
         ORDER BY u.name ASC, z.name ASC"
    );
    return $stmt->fetchAll();
}

function zbx_settings_ensure_table(PDO $pdo): void
{
    $sqlTable = "CREATE TABLE IF NOT EXISTS zabbix_settings (
  id TINYINT UNSIGNED NOT NULL,
  url VARCHAR(255) NOT NULL DEFAULT '',
  username VARCHAR(190) NOT NULL DEFAULT '',
  password VARCHAR(255) NOT NULL DEFAULT '',
  ignore_ssl TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlTable);
    try {
        $pdo->exec("ALTER TABLE zabbix_settings ADD COLUMN ignore_ssl TINYINT(1) NOT NULL DEFAULT 0");
    } catch (PDOException $e) {
        $info = $e->errorInfo;
        if (!is_array($info) || (int)($info[1] ?? 0) !== 1060) {
            throw $e;
        }
    }
    $pdo->exec("INSERT IGNORE INTO zabbix_settings (id, url, username, password, ignore_ssl) VALUES (1,'','', '',0)");
}

function attendant_profiles_ensure_category_column(PDO $pdo): void
{
    try {
        $pdo->exec("ALTER TABLE attendant_profiles ADD COLUMN category_id BIGINT UNSIGNED NULL DEFAULT NULL");
    } catch (PDOException $e) {
        $info = $e->errorInfo;
        $code = is_array($info) ? (int)($info[1] ?? 0) : 0;
        if ($code !== 1060) {
            throw $e;
        }
    }
    try {
        $pdo->exec("ALTER TABLE attendant_profiles ADD CONSTRAINT fk_attendant_profiles_category FOREIGN KEY (category_id) REFERENCES ticket_categories(id) ON DELETE SET NULL");
    } catch (PDOException $e) {
        $info = $e->errorInfo;
        $code = is_array($info) ? (int)($info[1] ?? 0) : 0;
        if ($code !== 1061 && $code !== 1826) {
            throw $e;
        }
    }

    try {
        $pdo->exec("ALTER TABLE attendant_profiles ADD COLUMN category_id_2 BIGINT UNSIGNED NULL DEFAULT NULL");
    } catch (PDOException $e) {
        $info = $e->errorInfo;
        $code = is_array($info) ? (int)($info[1] ?? 0) : 0;
        if ($code !== 1060) {
            throw $e;
        }
    }
    try {
        $pdo->exec("ALTER TABLE attendant_profiles ADD CONSTRAINT fk_attendant_profiles_category2 FOREIGN KEY (category_id_2) REFERENCES ticket_categories(id) ON DELETE SET NULL");
    } catch (PDOException $e) {
        $info = $e->errorInfo;
        $code = is_array($info) ? (int)($info[1] ?? 0) : 0;
        if ($code !== 1061 && $code !== 1826) {
            throw $e;
        }
    }
}

function company_ensure_schema(PDO $pdo): void
{
    $sql = "CREATE TABLE IF NOT EXISTS companies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  trade_name VARCHAR(190) NOT NULL DEFAULT '',
  document VARCHAR(60) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  phone VARCHAR(60) NOT NULL DEFAULT '',
  address_street VARCHAR(190) NOT NULL DEFAULT '',
  address_number VARCHAR(30) NOT NULL DEFAULT '',
  address_complement VARCHAR(120) NOT NULL DEFAULT '',
  address_district VARCHAR(120) NOT NULL DEFAULT '',
  address_city VARCHAR(120) NOT NULL DEFAULT '',
  address_state VARCHAR(10) NOT NULL DEFAULT '',
  address_zip VARCHAR(20) NOT NULL DEFAULT '',
  contact_name VARCHAR(120) NOT NULL DEFAULT '',
  contact_phone VARCHAR(60) NOT NULL DEFAULT '',
  contact_email VARCHAR(190) NOT NULL DEFAULT '',
  sla_response_critical_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  sla_resolution_critical_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  sla_response_high_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  sla_resolution_high_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  sla_response_medium_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  sla_resolution_medium_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  sla_response_low_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  sla_resolution_low_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  sla_availability_target_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  sla_notes TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    $slaColumns = [
        'sla_response_critical_minutes INT UNSIGNED NOT NULL DEFAULT 0',
        'sla_resolution_critical_minutes INT UNSIGNED NOT NULL DEFAULT 0',
        'sla_response_high_minutes INT UNSIGNED NOT NULL DEFAULT 0',
        'sla_resolution_high_minutes INT UNSIGNED NOT NULL DEFAULT 0',
        'sla_response_medium_minutes INT UNSIGNED NOT NULL DEFAULT 0',
        'sla_resolution_medium_minutes INT UNSIGNED NOT NULL DEFAULT 0',
        'sla_response_low_minutes INT UNSIGNED NOT NULL DEFAULT 0',
        'sla_resolution_low_minutes INT UNSIGNED NOT NULL DEFAULT 0',
        'sla_availability_target_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00',
    ];
    foreach ($slaColumns as $definition) {
        try {
            $pdo->exec("ALTER TABLE companies ADD COLUMN $definition");
        } catch (PDOException $e) {
            $info = $e->errorInfo;
            $code = is_array($info) ? (int)($info[1] ?? 0) : 0;
            if ($code !== 1060) {
                throw $e;
            }
        }
    }
    try {
        $pdo->exec("ALTER TABLE client_profiles ADD COLUMN company_id BIGINT UNSIGNED NULL DEFAULT NULL");
    } catch (PDOException $e) {
        $info = $e->errorInfo;
        $code = is_array($info) ? (int)($info[1] ?? 0) : 0;
        if ($code !== 1060) {
            throw $e;
        }
    }
    try {
        $pdo->exec("ALTER TABLE client_profiles ADD CONSTRAINT fk_client_profiles_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL");
    } catch (PDOException $e) {
        $info = $e->errorInfo;
        $code = is_array($info) ? (int)($info[1] ?? 0) : 0;
        if ($code !== 1061 && $code !== 1826) {
            throw $e;
        }
    }
}

function company_list(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, name, trade_name, document, email, phone, address_city, address_state
         FROM companies
         ORDER BY name ASC"
    );
    return $stmt->fetchAll();
}

function company_find(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, name, trade_name, document, email, phone, address_street, address_number, address_complement, address_district, address_city, address_state, address_zip, contact_name, contact_phone, contact_email, sla_response_critical_minutes, sla_resolution_critical_minutes, sla_response_high_minutes, sla_resolution_high_minutes, sla_response_medium_minutes, sla_resolution_medium_minutes, sla_response_low_minutes, sla_resolution_low_minutes, sla_availability_target_percent, sla_notes
         FROM companies
         WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function zbx_settings_get(PDO $pdo): array
{
    try {
        $stmt = $pdo->prepare('SELECT url, username, password, ignore_ssl FROM zabbix_settings WHERE id = 1 LIMIT 1');
        $stmt->execute();
    } catch (PDOException $e) {
        $code = (string)$e->getCode();
        if ($code !== '42S02' && $code !== '42S22') {
            throw $e;
        }
        zbx_settings_ensure_table($pdo);
        $stmt = $pdo->prepare('SELECT url, username, password, ignore_ssl FROM zabbix_settings WHERE id = 1 LIMIT 1');
        $stmt->execute();
    }

    $row = $stmt->fetch();
    if (!is_array($row)) {
        return ['url' => '', 'username' => '', 'password' => '', 'ignore_ssl' => 0];
    }
    return [
        'url' => (string)$row['url'],
        'username' => (string)$row['username'],
        'password' => (string)$row['password'],
        'ignore_ssl' => (int)($row['ignore_ssl'] ?? 0),
    ];
}

function zbx_settings_save(PDO $pdo, string $url, string $username, string $password, bool $ignoreSsl): void
{
    $url = trim($url);
    $url = preg_replace('/^[`\\""\\(]+/', '', $url);
    $url = preg_replace('/[`\\")]+$/', '', $url);
    $url = preg_replace('~^(https?://[^/]+)//+~i', '$1/', $url);

    $ignore = $ignoreSsl ? 1 : 0;

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO zabbix_settings (id, url, username, password, ignore_ssl) VALUES (1,?,?,?,?)
             ON DUPLICATE KEY UPDATE url = VALUES(url), username = VALUES(username), password = VALUES(password), ignore_ssl = VALUES(ignore_ssl)'
        );
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S02') {
            throw $e;
        }
        zbx_settings_ensure_table($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO zabbix_settings (id, url, username, password, ignore_ssl) VALUES (1,?,?,?,?)
             ON DUPLICATE KEY UPDATE url = VALUES(url), username = VALUES(username), password = VALUES(password), ignore_ssl = VALUES(ignore_ssl)'
        );
    }
    $stmt->execute([$url, $username, $password, $ignore]);
}

function docs_ensure_table(PDO $pdo): void
{
    // Categorias de Documentação (Hierárquicas)
    $sqlCat = "CREATE TABLE IF NOT EXISTS doc_categories (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        parent_id BIGINT UNSIGNED NULL,
        name VARCHAR(100) NOT NULL,
        PRIMARY KEY (id),
        CONSTRAINT fk_doc_categories_parent FOREIGN KEY (parent_id) REFERENCES doc_categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlCat);

    // Inserir categorias padrão se não existirem
    $stmtCheck = $pdo->query("SELECT COUNT(*) FROM doc_categories");
    if ($stmtCheck->fetchColumn() == 0) {
        $cats = [
            'Windows' => ['Diskpart', 'AD', 'GPO', 'Update'],
            'Linux' => ['LVM', 'Docker', 'Nginx', 'Apache', 'SSH'],
            'Zabbix' => ['Templates', 'Auto Discovery', 'Agents'],
            'Backup' => ['Veeam', 'Proxmox', 'Cloud'],
            'Redes' => ['VLAN', 'VPN', 'Firewall', 'BGP'],
            'Geral' => []
        ];
        foreach ($cats as $parent => $subs) {
            $stmt = $pdo->prepare("INSERT INTO doc_categories (name) VALUES (?)");
            $stmt->execute([$parent]);
            $parentId = $pdo->lastInsertId();
            foreach ($subs as $sub) {
                $stmt = $pdo->prepare("INSERT INTO doc_categories (parent_id, name) VALUES (?, ?)");
                $stmt->execute([$parentId, $sub]);
            }
        }
    }

    $sql = "CREATE TABLE IF NOT EXISTS docs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  category VARCHAR(60) NOT NULL DEFAULT '',
  category_id BIGINT UNSIGNED NULL,
  content TEXT NOT NULL,
  commands TEXT NULL,
  author_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_docs_category FOREIGN KEY (category_id) REFERENCES doc_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);

    // Add new columns if they don't exist
    try {
        $pdo->exec("ALTER TABLE docs ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER category");
        $pdo->exec("ALTER TABLE docs ADD CONSTRAINT fk_docs_category FOREIGN KEY (category_id) REFERENCES doc_categories(id) ON DELETE SET NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE docs ADD COLUMN author_id BIGINT UNSIGNED NULL AFTER commands");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE docs ADD COLUMN last_updated_by BIGINT UNSIGNED NULL AFTER author_id");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE docs ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    } catch (PDOException $e) {}

    // Attachments for docs
    $sqlAttachments = "CREATE TABLE IF NOT EXISTS doc_attachments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        doc_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        file_size INT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_doc_id (doc_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlAttachments);
}

function doc_categories_list(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("
            SELECT c1.id, c1.name as subcategory, c2.name as category 
            FROM doc_categories c1
            INNER JOIN doc_categories c2 ON c1.parent_id = c2.id
            ORDER BY c2.name, c1.name
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        docs_ensure_table($pdo);
        return [];
    }
}

function docs_list(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('
            SELECT d.*, u.name AS author_name, u2.name AS updater_name,
                   c1.name as subcategory, c2.name as category
            FROM docs d 
            LEFT JOIN users u ON u.id = d.author_id 
            LEFT JOIN users u2 ON u2.id = d.last_updated_by
            LEFT JOIN doc_categories c1 ON d.category_id = c1.id
            LEFT JOIN doc_categories c2 ON c1.parent_id = c2.id
            ORDER BY d.created_at DESC
        ');
    } catch (PDOException $e) {
        if (!in_array($e->getCode(), ['42S02', '42S22', '42703'])) {
            throw $e;
        }
        docs_ensure_table($pdo);
        $stmt = $pdo->query('
            SELECT d.*, u.name AS author_name, u2.name AS updater_name,
                   c1.name as subcategory, c2.name as category
            FROM docs d 
            LEFT JOIN users u ON u.id = d.author_id 
            LEFT JOIN users u2 ON u2.id = d.last_updated_by
            LEFT JOIN doc_categories c1 ON d.category_id = c1.id
            LEFT JOIN doc_categories c2 ON c1.parent_id = c2.id
            ORDER BY d.created_at DESC
        ');
    }
    return $stmt->fetchAll();
}

function doc_find(PDO $pdo, int $id): ?array
{
    try {
        $stmt = $pdo->prepare('
            SELECT d.*, u.name AS author_name, u2.name AS updater_name,
                   c1.name as subcategory, c2.name as category
            FROM docs d 
            LEFT JOIN users u ON u.id = d.author_id 
            LEFT JOIN users u2 ON u2.id = d.last_updated_by
            LEFT JOIN doc_categories c1 ON d.category_id = c1.id
            LEFT JOIN doc_categories c2 ON c1.parent_id = c2.id
            WHERE d.id = ? 
            LIMIT 1
        ');
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        if (!in_array($e->getCode(), ['42S02', '42S22', '42703'])) {
            throw $e;
        }
        docs_ensure_table($pdo);
        $stmt = $pdo->prepare('
            SELECT d.*, u.name AS author_name, u2.name AS updater_name,
                   c1.name as subcategory, c2.name as category
            FROM docs d 
            LEFT JOIN users u ON u.id = d.author_id 
            LEFT JOIN users u2 ON u2.id = d.last_updated_by
            LEFT JOIN doc_categories c1 ON d.category_id = c1.id
            LEFT JOIN doc_categories c2 ON c1.parent_id = c2.id
            WHERE d.id = ? 
            LIMIT 1
        ');
        $stmt->execute([$id]);
    }
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function doc_create(PDO $pdo, string $title, ?int $categoryId, string $content, ?string $commands = null, ?int $authorId = null): int
{
    try {
        $stmt = $pdo->prepare('INSERT INTO docs (title, category_id, content, commands, author_id) VALUES (?,?,?,?,?)');
        $stmt->execute([$title, $categoryId, $content, $commands, $authorId]);
    } catch (PDOException $e) {
        if (!in_array($e->getCode(), ['42S02', '42S22', '42703'])) {
            throw $e;
        }
        docs_ensure_table($pdo);
        $stmt = $pdo->prepare('INSERT INTO docs (title, category_id, content, commands, author_id) VALUES (?,?,?,?,?)');
        $stmt->execute([$title, $categoryId, $content, $commands, $authorId]);
    }
    return (int)$pdo->lastInsertId();
}

function doc_update(PDO $pdo, int $id, string $title, ?int $categoryId, string $content, ?string $commands = null, ?int $userId = null): void
{
    try {
        $stmt = $pdo->prepare('UPDATE docs SET title = ?, category_id = ?, content = ?, commands = ?, last_updated_by = ? WHERE id = ?');
        $stmt->execute([$title, $categoryId, $content, $commands, $userId, $id]);
    } catch (PDOException $e) {
        if (!in_array($e->getCode(), ['42S02', '42S22', '42703'])) {
            throw $e;
        }
        docs_ensure_table($pdo);
        $stmt = $pdo->prepare('UPDATE docs SET title = ?, category_id = ?, content = ?, commands = ?, last_updated_by = ? WHERE id = ?');
        $stmt->execute([$title, $categoryId, $content, $commands, $userId, $id]);
    }
}

function doc_attachment_create(PDO $pdo, int $docId, int $userId, string $fileName, string $filePath, string $fileType, int $fileSize): int
{
    $stmt = $pdo->prepare('INSERT INTO doc_attachments (doc_id, user_id, file_name, file_path, file_type, file_size) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$docId, $userId, $fileName, $filePath, $fileType, $fileSize]);
    return (int)$pdo->lastInsertId();
}

function doc_attachments_list(PDO $pdo, int $docId): array
{
    $stmt = $pdo->prepare('SELECT * FROM doc_attachments WHERE doc_id = ? ORDER BY created_at ASC');
    $stmt->execute([$docId]);
    return $stmt->fetchAll();
}

function doc_delete(PDO $pdo, int $id): void
{
    $pdo->prepare('DELETE FROM doc_attachments WHERE doc_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM docs WHERE id = ?')->execute([$id]);
}

function audit_ensure_table(PDO $pdo): void
{
    $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
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
  KEY idx_audit_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
}

function audit_log(PDO $pdo, ?array $user, string $action, array $context = []): void
{
    try {
        audit_ensure_table($pdo);
    } catch (PDOException $e) {
        return;
    }
    $userId = null;
    $tenantId = null;
    if (is_array($user)) {
        if (isset($user['id'])) {
            $userId = (int)$user['id'];
        }
        if (isset($user['tenant_id'])) {
            $tenantId = (int)$user['tenant_id'];
        }
    }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
    $ctx = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($ctx)) {
        $ctx = '{}';
    }
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, tenant_id, action, context_json, ip, user_agent) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$userId, $tenantId, $action, $ctx, $ip, $ua]);
}

