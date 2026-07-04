<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Doménová entita úkolu.
 */
final class Task
{
    public function __construct(
        public readonly ?int $id,
        public string $title,
        public bool $done,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param array{id: int, title: string, done: int, created_at: string} $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            title: (string) $row['title'],
            done: (bool) $row['done'],
            createdAt: (string) $row['created_at'],
        );
    }
}
