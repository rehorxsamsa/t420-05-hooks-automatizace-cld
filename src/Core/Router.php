<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimalistický router. Mapuje (HTTP metoda + cesta) na callable.
 * Podpora jednoduchého parametru {id} v cestě.
 */
final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $regex = '#^' . preg_replace('#\{id\}#', '(?P<id>\d+)', $route['pattern']) . '$#';
            if (preg_match($regex, $path, $matches)) {
                $id = isset($matches['id']) ? (int) $matches['id'] : null;
                ($route['handler'])($id);
                return;
            }
        }

        http_response_code(404);
        echo '404 — stránka nenalezena';
    }
}
