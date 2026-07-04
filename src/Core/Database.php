<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Jednoduchý správce PDO připojení k SQLite.
 * Při prvním spuštění vytvoří schéma a naseeduje pár úkolů.
 */
final class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $dbFile = dirname(__DIR__, 2) . '/data/tasks.sqlite';

        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        self::migrate($pdo);

        self::$instance = $pdo;

        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                done INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )'
        );

        $count = (int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare(
                'INSERT INTO tasks (title, done, created_at) VALUES (:title, :done, :created_at)'
            );
            $seed = [
                ['Naučit se Claude Code', 0],
                ['Postavit Docker projekt', 1],
                ['Napsat testy', 0],
            ];
            foreach ($seed as [$title, $done]) {
                $stmt->execute([
                    'title' => $title,
                    'done' => $done,
                    'created_at' => date('c'),
                ]);
            }
        }
    }
}
