<?php
declare(strict_types=1);

$activeRoomCount = 0;
$activeShowtimeCount = 0;

foreach ($rooms as $room) {
    if ((int) ($room['is_active'] ?? 0) === 1) {
        $activeRoomCount++;
    }
}

foreach ($showtimes as $showtime) {
    if ((int) ($showtime['is_active'] ?? 0) === 1) {
        $activeShowtimeCount++;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen admin-screen">
    <?php
    $activeNav = 'admin';
    require __DIR__ . '/partials/header.php';
    ?>

    <main class="admin-shell">
        <?php if ($messages !== []): ?>
            <div class="page-messages cartelera-messages" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="admin-heading">
            <p class="eyebrow">Rol administrador</p>
            <h1>Administracion</h1>
            <p><?= e($user['name'] ?? 'Administrador') ?></p>
        </section>

        <?php if ($adminLoadError): ?>
            <section class="cartelera-state movie-detail-state">
                <h2>No se pudo cargar el panel</h2>
                <p>Intenta nuevamente mas tarde.</p>
            </section>
        <?php else: ?>
            <section class="admin-summary" aria-label="Resumen administrativo">
                <article>
                    <span><?= e(count($rooms)) ?></span>
                    <p>Salas registradas</p>
                    <strong><?= e($activeRoomCount) ?> activas</strong>
                </article>
                <article>
                    <span><?= e(count($showtimes)) ?></span>
                    <p>Funciones registradas</p>
                    <strong><?= e($activeShowtimeCount) ?> activas</strong>
                </article>
                <article>
                    <span><?= e(count($activeMovies)) ?></span>
                    <p>Peliculas activas</p>
                    <strong>Cartelera disponible</strong>
                </article>
            </section>

            <section id="admin-rooms" class="admin-section" aria-labelledby="admin-rooms-title">
                <div class="admin-section-heading">
                    <div>
                        <p class="eyebrow">Salas</p>
                        <h2 id="admin-rooms-title">Gestion de salas</h2>
                    </div>
                </div>

                <form class="admin-form admin-room-create" method="post" action="index.php?action=create_room">
                    <?= csrf_token_field() ?>
                    <label>
                        <span>Nombre</span>
                        <input type="text" name="name" maxlength="100" required>
                    </label>
                    <label>
                        <span>Ubicacion</span>
                        <input type="text" name="location" maxlength="120" required>
                    </label>
                    <label>
                        <span>Capacidad</span>
                        <input type="number" name="capacity" min="1" step="1" required>
                    </label>
                    <button type="submit">Crear sala</button>
                </form>

                <?php if ($rooms === []): ?>
                    <div class="admin-empty">
                        <h3>Sin salas</h3>
                    </div>
                <?php else: ?>
                    <div class="admin-list admin-room-list" role="list">
                        <div class="admin-list-head admin-room-row" aria-hidden="true">
                            <span>Nombre</span>
                            <span>Ubicacion</span>
                            <span>Capacidad</span>
                            <span>Estado</span>
                            <span>Acciones</span>
                        </div>

                        <?php foreach ($rooms as $room): ?>
                            <?php $roomActive = (int) ($room['is_active'] ?? 0) === 1; ?>
                            <form class="admin-row admin-room-row" method="post" action="index.php?action=update_room" role="listitem">
                                <?= csrf_token_field() ?>
                                <input type="hidden" name="room_id" value="<?= e($room['id'] ?? '') ?>">
                                <label>
                                    <span class="sr-only">Nombre</span>
                                    <input type="text" name="name" value="<?= e($room['name'] ?? '') ?>" maxlength="100" required>
                                </label>
                                <label>
                                    <span class="sr-only">Ubicacion</span>
                                    <input type="text" name="location" value="<?= e($room['location'] ?? '') ?>" maxlength="120" required>
                                </label>
                                <label>
                                    <span class="sr-only">Capacidad</span>
                                    <input type="number" name="capacity" value="<?= e($room['capacity'] ?? '') ?>" min="1" step="1" required>
                                </label>
                                <span class="admin-status-badge status-<?= e(admin_status_css_class($room['is_active'] ?? 0)) ?>">
                                    <?= e(admin_status_label($room['is_active'] ?? 0)) ?>
                                </span>
                                <span class="admin-actions">
                                    <button type="submit">Guardar</button>
                                    <button
                                        class="admin-danger"
                                        type="submit"
                                        formaction="index.php?action=deactivate_room"
                                        data-confirm-action="Desactivar esta sala?"
                                        <?= $roomActive ? '' : 'disabled' ?>
                                    >
                                        Desactivar
                                    </button>
                                </span>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section id="admin-showtimes" class="admin-section" aria-labelledby="admin-showtimes-title">
                <div class="admin-section-heading">
                    <div>
                        <p class="eyebrow">Funciones</p>
                        <h2 id="admin-showtimes-title">Gestion de funciones</h2>
                    </div>
                </div>

                <?php if ($activeMovies === [] || $activeRooms === []): ?>
                    <div class="admin-empty">
                        <h3>Faltan peliculas o salas activas</h3>
                    </div>
                <?php else: ?>
                    <form class="admin-form admin-showtime-create" method="post" action="index.php?action=create_showtime">
                        <?= csrf_token_field() ?>
                        <label>
                            <span>Pelicula</span>
                            <select name="movie_id" required>
                                <option value="">Seleccionar</option>
                                <?php foreach ($activeMovies as $movie): ?>
                                    <option value="<?= e($movie['id'] ?? '') ?>"><?= e($movie['title'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Sala</span>
                            <select name="room_id" required>
                                <option value="">Seleccionar</option>
                                <?php foreach ($activeRooms as $room): ?>
                                    <option value="<?= e($room['id'] ?? '') ?>">
                                        <?= e(trim(($room['name'] ?? '') . ' - ' . ($room['location'] ?? ''), ' -')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Inicio</span>
                            <input type="datetime-local" name="starts_at" required>
                        </label>
                        <label>
                            <span>Termino</span>
                            <input type="datetime-local" name="ends_at" required>
                        </label>
                        <label>
                            <span>Formato</span>
                            <input type="text" name="format_label" value="2D" maxlength="40" required>
                        </label>
                        <label>
                            <span>Idioma</span>
                            <input type="text" name="language_label" value="Subtitulada" maxlength="40" required>
                        </label>
                        <button type="submit">Crear funcion</button>
                    </form>
                <?php endif; ?>

                <?php if ($showtimes === []): ?>
                    <div class="admin-empty">
                        <h3>Sin funciones</h3>
                    </div>
                <?php else: ?>
                    <div class="admin-list admin-showtime-list" role="list">
                        <div class="admin-list-head admin-showtime-row" aria-hidden="true">
                            <span>Pelicula</span>
                            <span>Sala</span>
                            <span>Inicio</span>
                            <span>Termino</span>
                            <span>Formato</span>
                            <span>Idioma</span>
                            <span>Estado</span>
                            <span>Acciones</span>
                        </div>

                        <?php foreach ($showtimes as $showtime): ?>
                            <?php
                            $showtimeActive = (int) ($showtime['is_active'] ?? 0) === 1;
                            $targetStatus = $showtimeActive ? '0' : '1';
                            $targetLabel = $showtimeActive ? 'Desactivar' : 'Activar';
                            ?>
                            <form class="admin-row admin-showtime-row" method="post" action="index.php?action=update_showtime" role="listitem">
                                <?= csrf_token_field() ?>
                                <input type="hidden" name="showtime_id" value="<?= e($showtime['id'] ?? '') ?>">
                                <input type="hidden" name="is_active" value="<?= $showtimeActive ? '1' : '0' ?>">
                                <label>
                                    <span class="sr-only">Pelicula</span>
                                    <select name="movie_id" required>
                                        <?php foreach ($activeMovies as $movie): ?>
                                            <option
                                                value="<?= e($movie['id'] ?? '') ?>"
                                                <?= (int) ($movie['id'] ?? 0) === (int) ($showtime['movie_id'] ?? 0) ? 'selected' : '' ?>
                                            >
                                                <?= e($movie['title'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    <span class="sr-only">Sala</span>
                                    <select name="room_id" required>
                                        <?php foreach ($activeRooms as $room): ?>
                                            <option
                                                value="<?= e($room['id'] ?? '') ?>"
                                                <?= (int) ($room['id'] ?? 0) === (int) ($showtime['room_id'] ?? 0) ? 'selected' : '' ?>
                                            >
                                                <?= e(trim(($room['name'] ?? '') . ' - ' . ($room['location'] ?? ''), ' -')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    <span class="sr-only">Inicio</span>
                                    <input type="datetime-local" name="starts_at" value="<?= e(admin_datetime_input_value($showtime['starts_at'] ?? '')) ?>" required>
                                </label>
                                <label>
                                    <span class="sr-only">Termino</span>
                                    <input type="datetime-local" name="ends_at" value="<?= e(admin_datetime_input_value($showtime['ends_at'] ?? '')) ?>" required>
                                </label>
                                <label>
                                    <span class="sr-only">Formato</span>
                                    <input type="text" name="format_label" value="<?= e($showtime['format_label'] ?? '') ?>" maxlength="40" required>
                                </label>
                                <label>
                                    <span class="sr-only">Idioma</span>
                                    <input type="text" name="language_label" value="<?= e($showtime['language_label'] ?? '') ?>" maxlength="40" required>
                                </label>
                                <span class="admin-status-badge status-<?= e(admin_status_css_class($showtime['is_active'] ?? 0)) ?>">
                                    <?= e(admin_status_label($showtime['is_active'] ?? 0)) ?>
                                </span>
                                <span class="admin-actions">
                                    <button type="submit">Guardar</button>
                                    <button
                                        class="<?= $showtimeActive ? 'admin-danger' : 'admin-secondary' ?>"
                                        type="submit"
                                        formaction="index.php?action=deactivate_showtime"
                                        name="target_status"
                                        value="<?= e($targetStatus) ?>"
                                        data-confirm-action="<?= e($targetLabel) ?> esta funcion?"
                                    >
                                        <?= e($targetLabel) ?>
                                    </button>
                                </span>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
