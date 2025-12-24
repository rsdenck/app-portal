<?php

declare(strict_types=1);

namespace Portal\DTO;

readonly class ProjectDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $status,
        public ?int $ticketId = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            name: (string)$data['name'],
            status: (string)$data['status'],
            ticketId: isset($data['ticket_id']) ? (int)$data['ticket_id'] : null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'ticket_id' => $this->ticketId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
