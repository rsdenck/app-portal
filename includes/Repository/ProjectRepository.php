<?php

declare(strict_types=1);

namespace Portal\Repository;

use PDO;
use Portal\DTO\ProjectDTO;

class ProjectRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?ProjectDTO
    {
        $stmt = $this->pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? ProjectDTO::fromArray($row) : null;
    }

    public function findAll(string $status = 'active'): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM projects WHERE status = ? ORDER BY created_at DESC");
        $stmt->execute([$status]);
        $rows = $stmt->fetchAll();
        return array_map(fn($row) => ProjectDTO::fromArray($row), $rows);
    }

    public function create(string $name, ?int $ticketId = null): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO projects (name, ticket_id) VALUES (?, ?)");
        $stmt->execute([$name, $ticketId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE projects SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function getGroups(int $projectId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM project_groups WHERE project_id = ? ORDER BY position ASC, id ASC");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function getItemsInGroups(array $groupIds): array
    {
        if (empty($groupIds)) return [];
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT i.*, u.name as owner_name, u.email as owner_email 
            FROM project_items i 
            LEFT JOIN users u ON u.id = i.owner_user_id 
            WHERE i.group_id IN ($placeholders)
            ORDER BY i.position ASC, i.id ASC
        ");
        $stmt->execute($groupIds);
        return $stmt->fetchAll();
    }
}
