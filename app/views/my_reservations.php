<?php
declare(strict_types=1);

$userName = (string) ($user['name'] ?? 'Usuario');
$firstName = trim(explode(' ', $userName)[0] ?? $userName);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis reservas - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen reservations-screen">
    <header class="topbar cinema-topbar">
        <a class="brand cinema-brand" href="index.php?page=cartelera" aria-label="ES Cine cartelera">
            <span class="brand-person" aria-hidden="true"></span>
            <span class="brand-film"><span>ES</span> <em>Cine</em></span>
        </a>

        <nav class="topnav cinema-nav" aria-label="Navegacion principal">
            <a href="index.php?page=cartelera">Cartelera</a>
            <a class="is-active" href="index.php?page=my_reservations">Mis reservas</a>
            <a href="index.php?page=cartelera" aria-disabled="true">Confiteria</a>
            <a href="index.php?page=cartelera" aria-disabled="true">&iexcl;Hazte socio!</a>
            <?php if (($user['role'] ?? '') === 'admin'): ?>
                <a href="index.php?page=admin">Admin</a>
            <?php endif; ?>
        </nav>

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
    </header>

    <main class="reservations-shell">
        <?php if ($messages !== []): ?>
            <div class="page-messages cartelera-messages" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="reservations-heading">
            <a class="movie-back" href="index.php?page=cartelera" aria-label="Volver a cartelera">
                <span aria-hidden="true"></span>
            </a>
            <div>
                <h1>Mis reservas</h1>
                <p><?= e($user['name'] ?? 'Usuario') ?></p>
            </div>
        </div>

        <?php if ($reservationLoadError): ?>
            <section class="cartelera-state movie-detail-state">
                <h2>No se pudieron cargar tus reservas</h2>
                <p>Intenta nuevamente mas tarde.</p>
            </section>
        <?php elseif ($reservations === []): ?>
            <section class="reservations-empty">
                <h2>No tienes reservas</h2>
                <a class="movie-state-link" href="index.php?page=cartelera">Ver cartelera</a>
            </section>
        <?php else: ?>
            <section class="reservation-list" aria-label="Reservas del usuario">
                <?php foreach ($reservations as $reservation): ?>
                    <?php
                    $status = (string) ($reservation['status'] ?? '');
                    $statusClass = reservation_status_css_class($status);
                    $statusLabel = reservation_status_label($status);
                    $showtimeRange = trim(
                        reservation_datetime_label($reservation['starts_at'] ?? '') . ' - ' . reservation_datetime_label($reservation['ends_at'] ?? ''),
                        ' -'
                    );
                    $formatLabel = trim(
                        (string) ($reservation['format_label'] ?? '') . ' - ' . (string) ($reservation['language_label'] ?? ''),
                        ' -'
                    );
                    $roomLabel = trim(
                        (string) ($reservation['room_name'] ?? '') . ' - ' . (string) ($reservation['room_location'] ?? ''),
                        ' -'
                    );
                    $seatLabels = trim((string) ($reservation['seat_labels'] ?? ''));
                    $createdAtLabel = reservation_datetime_label($reservation['created_at'] ?? '');
                    $cancelledAtLabel = reservation_datetime_label($reservation['cancelled_at'] ?? '');
                    ?>
                    <article class="reservation-card">
                        <div class="reservation-card-main">
                            <header class="reservation-card-header">
                                <div>
                                    <span class="reservation-id">Reserva #<?= e($reservation['id'] ?? '') ?></span>
                                    <h2><?= e($reservation['movie_title'] ?? 'Pelicula') ?></h2>
                                </div>
                                <span class="reservation-status-badge status-<?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                            </header>

                            <dl class="reservation-details">
                                <div>
                                    <dt>Funcion</dt>
                                    <dd><?= e($showtimeRange !== '' ? $showtimeRange : 'Sin horario') ?></dd>
                                </div>
                                <div>
                                    <dt>Sala</dt>
                                    <dd><?= e($roomLabel !== '' ? $roomLabel : 'Sin sala') ?></dd>
                                </div>
                                <div>
                                    <dt>Formato</dt>
                                    <dd><?= e($formatLabel !== '' ? $formatLabel : 'Sin formato') ?></dd>
                                </div>
                                <div>
                                    <dt>Butacas</dt>
                                    <dd><?= e($seatLabels !== '' ? $seatLabels : 'Sin butacas') ?></dd>
                                </div>
                                <div>
                                    <dt>Total</dt>
                                    <dd><?= e(reservation_format_money((float) ($reservation['total_amount'] ?? 0))) ?></dd>
                                </div>
                                <div>
                                    <dt>Creada</dt>
                                    <dd><?= e($createdAtLabel !== '' ? $createdAtLabel : 'Sin fecha') ?></dd>
                                </div>
                                <?php if ($cancelledAtLabel !== ''): ?>
                                    <div>
                                        <dt>Cancelada</dt>
                                        <dd><?= e($cancelledAtLabel) ?></dd>
                                    </div>
                                <?php endif; ?>
                            </dl>
                        </div>

                        <?php if (reservation_can_cancel($reservation)): ?>
                            <form class="reservation-actions" method="post" action="index.php?action=cancel_reservation" data-cancel-reservation>
                                <?= csrf_token_field() ?>
                                <input type="hidden" name="reservation_id" value="<?= e($reservation['id'] ?? '') ?>">
                                <button class="reservation-cancel" type="submit">Cancelar reserva</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
