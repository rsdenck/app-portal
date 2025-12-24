<?php

declare(strict_types=1);

namespace Portal\Service;

use Portal\Repository\ProjectRepository;
use Portal\DTO\ProjectDTO;

class ProjectService
{
    public function __construct(private ProjectRepository $repository) {}

    public function getProject(int $id): ?ProjectDTO
    {
        return $this->repository->findById($id);
    }

    public function listProjects(string $status = 'active'): array
    {
        return $this->repository->findAll($status);
    }

    public function createProject(string $name, ?int $ticketId = null): int
    {
        return $this->repository->create($name, $ticketId);
    }

    public function archiveProject(int $id): void
    {
        $this->repository->updateStatus($id, 'archived');
    }

    public function unarchiveProject(int $id): void
    {
        $this->repository->updateStatus($id, 'active');
    }

    public function deleteProject(int $id): void
    {
        $this->repository->delete($id);
    }

    public function getFullStructure(int $projectId): array
    {
        $groups = $this->repository->getGroups($projectId);
        if (empty($groups)) return [];

        $groupIds = array_map(fn($g) => (int)$g['id'], $groups);
        $allItems = $this->repository->getItemsInGroups($groupIds);

        $itemsByGroup = [];
        foreach ($allItems as $item) {
            $itemsByGroup[(int)$item['group_id']][] = $item;
        }

        foreach ($groups as &$group) {
            $group['items'] = $itemsByGroup[(int)$group['id']] ?? [];
        }

        return $groups;
    }
}
