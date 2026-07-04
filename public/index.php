<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use App\Controller\TaskController;
use App\Core\Router;

$controller = new TaskController();

$router = new Router();
$router->add('GET', '/', static fn (): mixed => $controller->index());
$router->add('POST', '/tasks', static fn (): mixed => $controller->store());
$router->add('POST', '/tasks/{id}/toggle', static fn (?int $id): mixed => $controller->toggle((int) $id));
$router->add('POST', '/tasks/{id}/delete', static fn (?int $id): mixed => $controller->destroy((int) $id));

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
