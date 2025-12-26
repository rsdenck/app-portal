<?php

declare(strict_types=1);

require_once __DIR__ . '/DTO/ProjectDTO.php';
require_once __DIR__ . '/Repository/ProjectRepository.php';
require_once __DIR__ . '/Service/ProjectService.php';

use Portal\Repository\ProjectRepository;
use Portal\Service\ProjectService;

function _get_project_service(PDO $pdo): ProjectService
{
    static $service = null;
    if ($service === null) {
        $repo = new ProjectRepository($pdo);
        $service = new ProjectService($repo);
    }
    return $service;
}

function projects_ensure_schema(PDO $pdo): void
{
    // Projects Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id BIGINT UNSIGNED NULL,
        name VARCHAR(255) NOT NULL,
        status ENUM('active', 'archived') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_projects_ticket (ticket_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Project Groups Table (Planning, Execution, etc.)
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(20) DEFAULT '#579bfc',
        position INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_project_groups_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Project Items Table (The "Monday" rows)
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        owner_user_id BIGINT UNSIGNED NULL,
        status VARCHAR(50) DEFAULT 'Aguardando',
        baseline INT DEFAULT 0,
        expenses DECIMAL(15, 2) DEFAULT 0.00,
        position INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_project_items_group FOREIGN KEY (group_id) REFERENCES project_groups(id) ON DELETE CASCADE,
        CONSTRAINT fk_project_items_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure Projetos category exists
    $stmtProj = $pdo->prepare("SELECT id FROM ticket_categories WHERE slug = 'projetos'");
    $stmtProj->execute();
    if (!$stmtProj->fetch()) {
        $pdo->prepare("INSERT INTO ticket_categories (name, slug, schema_json) VALUES ('Projetos', 'projetos', '[]')")->execute();
    }
}

function project_create(PDO $pdo, string $name, ?int $ticketId = null): int
{
    $projectId = _get_project_service($pdo)->createProject($name, $ticketId);

    // Create default groups (Mantido aqui para compatibilidade direta de fluxo)
    project_group_create($pdo, $projectId, 'Planejamento', '#579bfc', 1);
    project_group_create($pdo, $projectId, 'Execução', '#a25ddc', 2);

    return $projectId;
}

function project_get_all(PDO $pdo, string $status = 'active'): array
{
    $projects = _get_project_service($pdo)->listProjects($status);
    return array_map(fn($p) => $p->toArray(), $projects);
}

function project_get_by_id(PDO $pdo, int $id): ?array
{
    $project = _get_project_service($pdo)->getProject($id);
    return $project ? $project->toArray() : null;
}

function project_group_create(PDO $pdo, int $projectId, string $name, string $color = '#579bfc', int $position = 0): int
{
    $stmt = $pdo->prepare("INSERT INTO project_groups (project_id, name, color, position) VALUES (?, ?, ?, ?)");
    $stmt->execute([$projectId, $name, $color, $position]);
    return (int)$pdo->lastInsertId();
}

function project_get_structure(PDO $pdo, int $projectId): array
{
    return _get_project_service($pdo)->getFullStructure($projectId);
}

function project_item_create(PDO $pdo, int $groupId, string $name): int
{
    $stmt = $pdo->prepare("INSERT INTO project_items (group_id, name) VALUES (?, ?)");
    $stmt->execute([$groupId, $name]);
    return (int)$pdo->lastInsertId();
}

function project_item_update(PDO $pdo, int $itemId, array $data): void
{
    $fields = [];
    $params = [];
    foreach ($data as $key => $value) {
        if (in_array($key, ['name', 'owner_user_id', 'status', 'baseline', 'expenses', 'position'])) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }
    }
    if (!$fields) return;

    $params[] = $itemId;
    $stmt = $pdo->prepare("UPDATE project_items SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->execute($params);
}

function project_item_delete(PDO $pdo, int $itemId): void
{
    $stmt = $pdo->prepare("DELETE FROM project_items WHERE id = ?");
    $stmt->execute([$itemId]);
}

function project_archive(PDO $pdo, int $id): void
{
    _get_project_service($pdo)->archiveProject($id);
}

function project_unarchive(PDO $pdo, int $id): void
{
    _get_project_service($pdo)->unarchiveProject($id);
}

function project_delete(PDO $pdo, int $id): void
{
    _get_project_service($pdo)->deleteProject($id);
}

function project_group_delete(PDO $pdo, int $groupId): void
{
    $stmt = $pdo->prepare("DELETE FROM project_groups WHERE id = ?");
    $stmt->execute([$groupId]);
}

