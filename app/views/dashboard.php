<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen">
    <header class="topbar">
        <a class="brand" href="index.php?page=dashboard">ES Cine</a>
        <nav class="topnav" aria-label="Navegacion principal">
            <?php if (($user['role'] ?? '') === 'admin'): ?>
                <a href="index.php?page=admin">Admin</a>
            <?php endif; ?>
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
            <p class="eyebrow">Sesion activa</p>
            <h1><?= e($user['name'] ?? 'Usuario') ?></h1>
            <p><?= e($user['email'] ?? '') ?></p>
        </section>

        <section class="status-grid" aria-label="Estado del usuario">
            <article class="status-card">
                <h2>Rol</h2>
                <p class="role-badge"><?= e($user['role'] ?? 'user') ?></p>
            </article>

            <article class="status-card">
                <h2>Cuenta</h2>
                <p class="status-ok">Autenticada</p>
            </article>

            <article class="status-card">
                <h2>Acceso admin</h2>
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                    <a class="inline-action" href="index.php?page=admin">Abrir vista protegida</a>
                <?php else: ?>
                    <p class="muted">Restringido</p>
                <?php endif; ?>
            </article>
        </section>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
