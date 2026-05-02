<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso denegado - Reserva Salas Cine</title>
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
        <section class="status-card protected-card">
            <?php foreach ($messages as $message): ?>
                <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
            <?php endforeach; ?>
            <a class="inline-action" href="index.php?page=dashboard">Volver al panel</a>
        </section>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
