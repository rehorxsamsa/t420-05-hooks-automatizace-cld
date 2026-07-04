<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Task;
use App\Repository\TaskRepository;
use App\Repository\TaskRepositoryInterface;

/**
 * Service vrstva — business logika. Controller nikdy nesahá na repository přímo.
 */
final class TaskService
{
    private readonly TaskRepositoryInterface $repository;

    public function __construct(?TaskRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new TaskRepository();
    }

    /**
     * @return list<Task>
     */
    public function list(): array
    {
        return $this->repository->all();
    }

    public function add(string $title): Task
    {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Název úkolu nesmí být prázdný');
        }

        return $this->repository->create($title);
    }

    public function toggle(int $id): void
    {
        $this->repository->toggle($id);
    }

    public function remove(int $id): void
    {
        $this->repository->delete($id);
    }

    /**
     * Spočítá kolik úkolů je hotových. (V dílu 2 na téhle metodě uděláme /review a testy.)
     */
    public function progress(): int
    {
        $tasks = $this->repository->all();
        if (count($tasks) === 0) {
            return 0;
        }

        $done = 0;
        foreach ($tasks as $task) {
            if ($task->done) {
                $done++;
            }
        }

        return (int) round($done / count($tasks) * 100);
    }
}
