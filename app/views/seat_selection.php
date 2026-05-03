<?php
declare(strict_types=1);

$userName = (string) ($user['name'] ?? 'Usuario');
$firstName = trim(explode(' ', $userName)[0] ?? $userName);
$hasShowtime = is_array($showtime);
$pageMessages = $messages;
$movieTitle = $hasShowtime ? (string) ($showtime['movie_title'] ?? 'Pelicula') : 'Seleccion de butacas';
$ticketTotal = reservation_total_amount($ticketCount);
$confirmedSeatLabels = [];

foreach ($errors as $error) {
    $pageMessages[] = [
        'type' => 'error',
        'message' => (string) $error,
    ];
}

if (is_array($reservationConfirmation)) {
    foreach ($reservationConfirmation['seats'] ?? [] as $seat) {
        $confirmedSeatLabels[] = reservation_seat_key((string) ($seat['seat_row'] ?? ''), (int) ($seat['seat_number'] ?? 0));
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($movieTitle) ?> - Seleccion de butacas</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen seat-selection-screen">
    <header class="topbar cinema-topbar">
        <a class="brand cinema-brand" href="index.php?page=cartelera" aria-label="ES Cine cartelera">
            <span class="brand-person" aria-hidden="true"></span>
            <span class="brand-film"><span>ES</span> <em>Cine</em></span>
        </a>

        <nav class="topnav cinema-nav" aria-label="Navegacion principal">
            <a class="is-active" href="index.php?page=cartelera">Cartelera</a>
            <a href="index.php?page=cartelera" aria-disabled="true">Confiteria</a>
            <a href="index.php?page=cartelera" aria-disabled="true">¡Hazte socio!</a>
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
                <a href="index.php?action=logout">Cerrar sesion</a>
            </div>
        </div>
    </header>

    <main class="seat-selection-shell">
        <?php if ($pageMessages !== []): ?>
            <div class="page-messages cartelera-messages" aria-live="polite">
                <?php foreach ($pageMessages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($showtimeLoadError): ?>
            <section class="cartelera-state movie-detail-state">
                <h1>No se pudo cargar la funcion</h1>
                <p>Intenta nuevamente mas tarde.</p>
                <a class="movie-state-link" href="index.php?page=cartelera">Volver a cartelera</a>
            </section>
        <?php elseif ($showtimeNotFound || !$hasShowtime): ?>
            <section class="cartelera-state movie-detail-state">
                <h1>Funcion no encontrada</h1>
                <p>La funcion solicitada no existe o no esta activa.</p>
                <a class="movie-state-link" href="index.php?page=cartelera">Volver a cartelera</a>
            </section>
        <?php else: ?>
            <div class="seat-title-row">
                <a class="movie-back" href="index.php?page=movie&amp;id=<?= e($showtime['movie_id'] ?? '') ?>" aria-label="Volver al detalle de pelicula">
                    <span aria-hidden="true"></span>
                </a>
                <h1>Selecciona tu butaca</h1>
            </div>

            <div class="seat-selection-layout">
                <aside class="seat-summary-panel" aria-label="Resumen de reserva">
                    <div class="seat-summary-group">
                        <h2>Pelicula</h2>
                        <p><?= e($showtime['movie_title'] ?? '') ?> - <?= e($showtime['format_label'] ?? '') ?> - <?= e($showtime['language_label'] ?? '') ?></p>
                    </div>

                    <div class="seat-summary-group">
                        <h2>Horario</h2>
                        <p><?= e($showtimeLabels['datetime']) ?></p>
                    </div>

                    <div class="seat-summary-group">
                        <h2>Sala</h2>
                        <p><?= e($showtime['room_name'] ?? '') ?></p>
                        <span><?= e($showtime['room_location'] ?? '') ?></span>
                    </div>

                    <div class="seat-summary-group">
                        <h2>Entrada</h2>
                        <p>(<?= e($ticketCount) ?>) Butaca tradicional</p>
                        <strong><?= e(reservation_format_money($ticketTotal)) ?></strong>
                        <small>*Cargo por servicio incluido</small>
                    </div>

                    <?php if (is_array($reservationConfirmation)): ?>
                        <div class="seat-confirmation" aria-live="polite">
                            <h2>Reserva creada</h2>
                            <p>ID #<?= e($reservationConfirmation['id'] ?? '') ?></p>
                            <span><?= e(implode(', ', $confirmedSeatLabels)) ?></span>
                        </div>
                    <?php endif; ?>
                </aside>

                <section class="seat-map-panel" aria-labelledby="seat-map-title">
                    <div class="seat-map-heading">
                        <div class="cinema-screen" aria-hidden="true">Pantalla</div>
                        <div class="seat-timer" aria-label="Tiempo de seleccion">
                            <span aria-hidden="true"></span>
                            <strong>05:00</strong>
                        </div>
                    </div>

                    <form
                        class="seat-form"
                        method="post"
                        action="index.php?action=create_reservation"
                        data-seat-form
                        data-ticket-count="<?= e($ticketCount) ?>"
                    >
                        <input type="hidden" name="showtime_id" value="<?= e($showtime['id'] ?? '') ?>">
                        <input type="hidden" name="ticket_count" value="<?= e($ticketCount) ?>">

                        <div class="seat-board-layout">
                            <div class="seat-grid-wrap">
                                <h2 id="seat-map-title" class="sr-only">Butacas disponibles</h2>
                                <div class="seat-grid" style="--seat-columns: <?= e($seatMap['columns']) ?>">
                                    <?php foreach ($seatMap['rows'] as $rowLabel => $rowSeats): ?>
                                        <div class="seat-row">
                                            <span class="seat-row-label"><?= e($rowLabel) ?></span>
                                            <div class="seat-row-seats">
                                                <?php for ($seatNumber = 1; $seatNumber <= $seatMap['columns']; $seatNumber++): ?>
                                                    <?php $seat = $rowSeats[$seatNumber] ?? null; ?>
                                                    <?php if ($seat === null): ?>
                                                        <span class="seat-cell seat-cell-empty" aria-hidden="true"></span>
                                                    <?php else: ?>
                                                        <?php
                                                        $seatKey = (string) $seat['key'];
                                                        $isOccupied = isset($occupiedSeats[$seatKey]);
                                                        $isSelected = isset($selectedSeatKeys[$seatKey]) && !$isOccupied;
                                                        $seatClasses = [
                                                            'seat-cell',
                                                            $isOccupied ? 'is-occupied' : 'is-available',
                                                            $isSelected ? 'is-selected' : '',
                                                            $seat['type'] === 'accessibility' ? 'is-accessibility' : '',
                                                        ];
                                                        ?>
                                                        <label class="<?= e(trim(implode(' ', $seatClasses))) ?>">
                                                            <input
                                                                type="checkbox"
                                                                name="seats[]"
                                                                value="<?= e($seatKey) ?>"
                                                                data-seat-checkbox
                                                                <?= $isOccupied ? 'disabled' : '' ?>
                                                                <?= $isSelected ? 'checked' : '' ?>
                                                            >
                                                            <span><?= $seat['type'] === 'accessibility' ? '♿' : e($seat['number']) ?></span>
                                                        </label>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="seat-row-label"><?= e($rowLabel) ?></span>
                                        </div>
                                    <?php endforeach; ?>

                                    <div class="seat-number-row" aria-hidden="true">
                                        <span></span>
                                        <div>
                                            <?php for ($seatNumber = 1; $seatNumber <= $seatMap['columns']; $seatNumber++): ?>
                                                <span><?= e($seatNumber) ?></span>
                                            <?php endfor; ?>
                                        </div>
                                        <span></span>
                                    </div>
                                </div>
                            </div>

                            <div class="seat-side-panel">
                                <ul class="seat-legend" aria-label="Estados de butacas">
                                    <li><span class="legend-seat legend-available"></span> Disponible</li>
                                    <li><span class="legend-seat legend-occupied"></span> No disponible</li>
                                    <li><span class="legend-seat legend-selected"></span> Seleccionado</li>
                                    <li><span class="legend-seat legend-accessibility">♿</span> Silla de ruedas</li>
                                </ul>

                                <div class="seat-selected-summary" aria-live="polite">
                                    <strong><span data-seat-selected-count>0</span> / <?= e($ticketCount) ?></strong>
                                    <span data-seat-selected-list>Sin butacas</span>
                                </div>

                                <button class="seat-submit" type="submit" data-seat-submit>
                                    Reservar entradas
                                </button>
                            </div>
                        </div>
                    </form>
                </section>
            </div>
        <?php endif; ?>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
