<?php
declare(strict_types=1);

$status = (string) ($reservation['status'] ?? '');
$statusClass = reservation_status_css_class($status);
$statusLabel = reservation_status_label($status);
$seatLabels = [];

foreach ($reservation['seats'] ?? [] as $seat) {
    $seatLabels[] = reservation_seat_key((string) ($seat['seat_row'] ?? ''), (int) ($seat['seat_number'] ?? 0));
}

$seatSummary = $seatLabels !== [] ? implode(', ', $seatLabels) : 'Sin butacas';
$roomLabel = trim(
    (string) ($reservation['room_name'] ?? '') . ' - ' . (string) ($reservation['room_location'] ?? ''),
    ' -'
);
$formatLabel = trim(
    (string) ($reservation['format_label'] ?? '') . ' - ' . (string) ($reservation['language_label'] ?? ''),
    ' -'
);
$showtimeLabels = reservation_showtime_labels($reservation);
$createdAtLabel = reservation_datetime_label($reservation['created_at'] ?? '');
$cancelledAtLabel = reservation_datetime_label($reservation['cancelled_at'] ?? '');
$reservationCode = reservation_visual_code((int) ($reservation['id'] ?? 0));
$accountLabel = trim((string) ($user['name'] ?? ''));

if ($accountLabel === '') {
    $accountLabel = (string) ($user['email'] ?? 'Usuario');
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket <?= e($reservationCode) ?> - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen ticket-screen">
    <?php
    $activeNav = 'my_reservations';
    require __DIR__ . '/partials/header.php';
    ?>

    <main class="ticket-shell">
        <?php if ($messages !== []): ?>
            <div class="page-messages cartelera-messages no-print" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="ticket-heading no-print">
            <a class="movie-back" href="index.php?page=my_reservations" aria-label="Volver a Mis reservas">
                <span aria-hidden="true"></span>
            </a>
            <div>
                <h1>Ticket reserva</h1>
                <p><?= e($reservationCode) ?></p>
            </div>
        </div>

        <section class="ticket-panel" aria-labelledby="ticket-title">
            <div class="ticket-brand-row">
                <div class="ticket-brand">
                    <span class="ticket-brand-mark" aria-hidden="true"></span>
                    <div>
                        <span>ES Cine</span>
                        <strong>Ticket de reserva</strong>
                    </div>
                </div>
                <div class="ticket-code">
                    <span>Reserva</span>
                    <strong><?= e($reservationCode) ?></strong>
                </div>
            </div>

            <div class="ticket-main">
                <div>
                    <p class="eyebrow">Pelicula</p>
                    <h2 id="ticket-title"><?= e($reservation['movie_title'] ?? 'Pelicula') ?></h2>
                </div>
                <span class="reservation-status-badge status-<?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
            </div>

            <dl class="ticket-details">
                <div>
                    <dt>Codigo de reserva</dt>
                    <dd><?= e($reservationCode) ?></dd>
                </div>
                <div>
                    <dt>Sala</dt>
                    <dd><?= e($roomLabel !== '' ? $roomLabel : 'Sin sala') ?></dd>
                </div>
                <div>
                    <dt>Fecha</dt>
                    <dd><?= e($showtimeLabels['date'] !== '' ? $showtimeLabels['date'] : 'Sin fecha') ?></dd>
                </div>
                <div>
                    <dt>Hora</dt>
                    <dd><?= e($showtimeLabels['time'] !== '' ? $showtimeLabels['time'] : 'Sin hora') ?></dd>
                </div>
                <div>
                    <dt>Formato / idioma</dt>
                    <dd><?= e($formatLabel !== '' ? $formatLabel : 'Sin formato') ?></dd>
                </div>
                <div>
                    <dt>Butacas</dt>
                    <dd><?= e($seatSummary) ?></dd>
                </div>
                <div>
                    <dt>Total</dt>
                    <dd><?= e(reservation_format_money((float) ($reservation['total_amount'] ?? 0))) ?></dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd><?= e($statusLabel) ?></dd>
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
                <div>
                    <dt>Usuario</dt>
                    <dd><?= e($accountLabel) ?></dd>
                </div>
            </dl>
        </section>

        <div class="ticket-actions no-print" aria-label="Acciones de ticket">
            <button class="ticket-action ticket-action-primary" type="button" data-print-ticket>Imprimir ticket</button>
            <a class="ticket-action ticket-action-secondary" href="index.php?page=my_reservations">Volver a Mis reservas</a>
            <a class="ticket-action ticket-action-secondary" href="index.php?page=cartelera">Volver a cartelera</a>
        </div>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
