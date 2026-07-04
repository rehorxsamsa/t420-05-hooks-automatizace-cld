<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Model\Task;
use PDO;

/**
 * Repository vrstva — jediné místo, kde se sahá na databázi.
 */
final class TaskRepository implements TaskRepositoryInterface
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * @return list<Task>
     */
    public function all(): array
    {
        $rows = $this->pdo
            ->query('SELECT * FROM tasks ORDER BY id DESC')
            ->fetchAll();

        return array_map(static fn (array $row): Task => Task::fromRow($row), $rows);
    }

    public function find(int $id): ?Task
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? Task::fromRow($row) : null;
    }

    public function create(string $title): Task
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tasks (title, done, created_at) VALUES (:title, 0, :created_at)'
        );
        $stmt->execute([
            'title' => $title,
            'created_at' => date('c'),
        ]);

        return $this->find((int) $this->pdo->lastInsertId())
            ?? throw new \RuntimeException('Úkol se nepodařilo vytvořit');
    }

    public function toggle(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE tasks SET done = 1 - done WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
