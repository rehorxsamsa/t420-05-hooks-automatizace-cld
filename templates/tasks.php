<?php

/**
 * @var list<App\Model\Task> $tasks
 * @var int $progress
 */
declare(strict_types=1);

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Task Library</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
        h1 { margin-bottom: .25rem; }
        .bar { height: 10px; background: #8883; border-radius: 6px; overflow: hidden; margin: .5rem 0 1.5rem; }
        .bar > span { display: block; height: 100%; background: #3b82f6; }
        form.add { display: flex; gap: .5rem; margin-bottom: 1.5rem; }
        form.add input[type=text] { flex: 1; padding: .5rem; }
        ul { list-style: none; padding: 0; }
        li { display: flex; align-items: center; gap: .75rem; padding: .5rem 0; border-bottom: 1px solid #8883; }
        li .title { flex: 1; }
        li.done .title { text-decoration: line-through; opacity: .6; }
        button { cursor: pointer; padding: .35rem .6rem; }
    </style>
</head>
<body>
    <h1>📚 Task Library</h1>
    <p>Hotovo: <strong><?= $progress ?> %</strong></p>
    <div class="bar"><span style="width: <?= $progress ?>%"></span></div>

    <form class="add" method="post" action="/tasks">
        <input type="text" name="title" placeholder="Nový úkol…" autofocus>
        <button type="submit">Přidat</button>
    </form>

    <ul>
        <?php foreach ($tasks as $task): ?>
            <li class="<?= $task->done ? 'done' : '' ?>">
                <span class="title"><?= $e($task->title) ?></span>
                <form method="post" action="/tasks/<?= $task->id ?>/toggle">
                    <button type="submit"><?= $task->done ? '↩︎ Zpět' : '✓ Hotovo' ?></button>
                </form>
                <form method="post" action="/tasks/<?= $task->id ?>/delete">
                    <button type="submit">🗑</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>
