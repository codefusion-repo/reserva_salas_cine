<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/security.php';
require_once __DIR__ . '/../app/helpers/database.php';

$config = db_config();
$dbStatus = 'Configuracion cargada';
$dbDetail = 'La conexion PDO se probara automaticamente cuando la base de datos exista.';
$movies = [];

try {
    $movies = db_fetch_all(
        'SELECT title, genre, classification
         FROM movies
         WHERE is_active = :is_active
         ORDER BY title
         LIMIT 5',
        ['is_active' => 1]
    );
    $dbStatus = 'Conexion PDO OK';
    $dbDetail = 'La aplicacion puede leer datos desde MySQL/MariaDB.';
} catch (Throwable $exception) {
    $dbStatus = 'Config OK, base de datos pendiente';
    $dbDetail = 'Importa database/schema.sql y database/seed.sql para completar la prueba local.';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <main class="page-shell">
        <section class="hero">
            <p class="eyebrow">Bootstrap PHP/MySQL</p>
            <h1>Reserva Salas Cine</h1>
            <p class="lead">
                Proyecto academico vanilla PHP para gestionar cartelera, funciones, butacas y reservas.
            </p>
        </section>

        <section class="status-grid" aria-label="Estado del bootstrap">
            <article class="status-card">
                <h2>Aplicacion</h2>
                <p class="status-ok">PHP cargado correctamente</p>
                <p>Entrada publica: <strong>public/index.php</strong></p>
            </article>

            <article class="status-card">
                <h2>Base de datos</h2>
                <p class="status-ok"><?= e($dbStatus) ?></p>
                <p><?= e($dbDetail) ?></p>
            </article>

            <article class="status-card">
                <h2>Configuracion</h2>
                <dl>
                    <div>
                        <dt>Host</dt>
                        <dd><?= e($config['host']) ?></dd>
                    </div>
                    <div>
                        <dt>Database</dt>
                        <dd><?= e($config['database']) ?></dd>
                    </div>
                    <div>
                        <dt>User</dt>
                        <dd><?= e($config['username']) ?></dd>
                    </div>
                    <div>
                        <dt>Password</dt>
                        <dd><?= $config['password'] === '' ? 'vacio por defecto' : 'configurado localmente' ?></dd>
                    </div>
                </dl>
            </article>
        </section>

        <section class="movie-preview">
            <div class="section-heading">
                <h2>Cartelera demo</h2>
                <p>Datos visibles cuando el seed local ya fue importado.</p>
            </div>

            <?php if ($movies !== []): ?>
                <div class="movie-grid">
                    <?php foreach ($movies as $movie): ?>
                        <article class="movie-card">
                            <h3><?= e($movie['title']) ?></h3>
                            <p><?= e($movie['genre']) ?></p>
                            <span><?= e($movie['classification']) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Aun no hay peliculas demo disponibles para mostrar.</p>
            <?php endif; ?>
        </section>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
