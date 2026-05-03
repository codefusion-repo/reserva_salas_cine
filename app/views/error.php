<?php
declare(strict_types=1);

$isAuthenticated = is_array($user);
$userName = $isAuthenticated ? (string) ($user['name'] ?? 'Usuario') : '';
$firstName = trim(explode(' ', $userName)[0] ?? $userName);
$isAdmin = $isAuthenticated && ($user['role'] ?? '') === 'admin';
$pageHeading = trim($heading) !== '' ? $heading : 'Pagina no encontrada';
$pageCopy = trim($copy) !== '' ? $copy : 'No encontramos lo que estabas buscando.';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageHeading) ?> - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen error-screen">
    <header class="topbar cinema-topbar">
        <a class="brand cinema-brand" href="index.php?page=cartelera" aria-label="ES Cine cartelera">
            <span class="brand-person" aria-hidden="true"></span>
            <span class="brand-film"><span>ES</span> <em>Cine</em></span>
        </a>

        <nav class="topnav cinema-nav" aria-label="Navegacion principal">
            <a href="index.php?page=cartelera">Cartelera</a>
            <?php if ($isAuthenticated): ?>
                <a href="index.php?page=my_reservations">Mis reservas</a>
            <?php else: ?>
                <a href="index.php?page=login">Iniciar sesion</a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
                <a href="index.php?page=admin">Admin</a>
            <?php endif; ?>
        </nav>

        <?php if ($isAuthenticated): ?>
            <div class="user-menu">
                <button class="user-pill" type="button" aria-haspopup="true">
                    <span class="user-avatar" aria-hidden="true"></span>
                    <span>Hola, <?= e($firstName !== '' ? $firstName : 'Usuario') ?>!</span>
                    <span class="user-caret" aria-hidden="true"></span>
                </button>
                <div class="user-dropdown">
                    <span><?= e($user['email'] ?? '') ?></span>
                    <a href="index.php?page=my_reservations">Mis reservas</a>
                    <a href="index.php?action=logout">Cerrar sesion</a>
                </div>
            </div>
        <?php endif; ?>
    </header>

    <main class="error-shell">
        <?php if ($messages !== []): ?>
            <div class="page-messages cartelera-messages" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="error-panel" aria-labelledby="error-title">
            <div class="error-mark" aria-hidden="true">
                <span><?= e($statusLabel) ?></span>
            </div>

            <div class="error-copy">
                <p class="eyebrow">Error <?= e($statusLabel) ?></p>
                <h1 id="error-title"><?= e($pageHeading) ?></h1>
                <p><?= e($pageCopy) ?></p>

                <div class="error-actions">
                    <a class="error-action error-action-primary" href="index.php?page=cartelera">Volver a cartelera</a>
                    <?php if ($isAuthenticated): ?>
                        <a class="error-action error-action-secondary" href="index.php?page=my_reservations">Mis reservas</a>
                    <?php else: ?>
                        <a class="error-action error-action-secondary" href="index.php?page=login">Iniciar sesion</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
