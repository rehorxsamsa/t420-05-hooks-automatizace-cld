<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Task;

/**
 * Kontrakt repository vrstvy. Umožňuje podstrčit fake v testech.
 */
interface TaskRepositoryInterface
{
    /** @return list<Task> */
    public function all(): array;

    public function find(int $id): ?Task;

    public function create(string $title): Task;

    public function toggle(int $id): void;

    public function delete(int $id): void;
}
