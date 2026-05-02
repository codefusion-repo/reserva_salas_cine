<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen">
    <header class="topbar">
        <a class="brand" href="index.php?page=dashboard">ES Cine</a>
        <nav class="topnav" aria-label="Navegacion principal">
            <a href="index.php?page=dashboard">Panel</a>
            <a href="index.php?action=logout">Cerrar sesion</a>
        </nav>
    </header>

    <main class="app-shell">
        <?php if ($messages !== []): ?>
            <div class="page-messages" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="panel-hero">
            <p class="eyebrow">Rol administrador</p>
            <h1>Panel administrador</h1>
            <p><?= e($user['name'] ?? 'Administrador') ?></p>
        </section>

        <section class="status-card protected-card">
            <h2>Vista protegida</h2>
            <p>Acceso permitido para <?= e($user['email'] ?? '') ?>.</p>
        </section>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
