<?php
declare(strict_types=1);

$hasShowtime = is_array($showtime);
$pageMessages = $messages;
$movieTitle = $hasShowtime ? (string) ($showtime['movie_title'] ?? 'Pelicula') : 'Seleccion de butacas';
$ticketTotal = reservation_total_amount($ticketCount);
$ticketTotalLabel = reservation_format_money($ticketTotal);
$initialSelectedSeatKeys = array_filter(
    array_keys($selectedSeatKeys ?? []),
    static function (string $seatKey) use ($seatMap, $occupiedSeats): bool {
        return isset($seatMap['lookup'][$seatKey]) && !isset($occupiedSeats[$seatKey]);
    }
);
$initialSelectedSeatLabels = array_map(
    static fn (string $seatKey): string => str_replace('-', '', $seatKey),
    $initialSelectedSeatKeys
);
$initialSelectedSeatCount = count($initialSelectedSeatLabels);
$initialRemainingSeatCount = max(0, $ticketCount - $initialSelectedSeatCount);
$initialExtraSeatCount = max(0, $initialSelectedSeatCount - $ticketCount);
$initialSeatListLabel = $initialSelectedSeatLabels !== [] ? implode(', ', $initialSelectedSeatLabels) : 'Sin butacas seleccionadas';
$initialRemainingLabel = match (true) {
    $initialExtraSeatCount > 0 => $initialExtraSeatCount === 1 ? 'Quita 1 butaca' : 'Quita ' . $initialExtraSeatCount . ' butacas',
    $initialRemainingSeatCount === 0 => 'No faltan butacas',
    $initialRemainingSeatCount === 1 => 'Falta 1 butaca',
    default => 'Faltan ' . $initialRemainingSeatCount . ' butacas',
};
$initialButtonStateLabel = $initialSelectedSeatCount === $ticketCount
    ? 'Boton activo'
    : 'Boton deshabilitado: ' . strtolower($initialRemainingLabel);
$initialSubmitMessage = 'Selecciona ' . $ticketCount . ' butaca' . ($ticketCount === 1 ? '' : 's') . ' para reservar. ' . $initialRemainingLabel . '.';
$confirmedSeatLabels = [];
$hasReservationConfirmation = $hasShowtime && is_array($reservationConfirmation ?? null);
$confirmationStatusClass = 'unknown';
$confirmationStatusLabel = 'Sin estado';
$confirmationSeatSummary = 'Sin butacas';
$confirmationMovieLabel = '';
$confirmationRoomLabel = '';
$confirmationTotalLabel = $ticketTotalLabel;
$pageTitle = $hasReservationConfirmation ? 'Reserva confirmada' : $movieTitle . ' - Seleccion de butacas';

foreach ($errors as $error) {
    $pageMessages[] = [
        'type' => 'error',
        'message' => (string) $error,
    ];
}

if ($hasReservationConfirmation) {
    foreach ($reservationConfirmation['seats'] ?? [] as $seat) {
        $confirmedSeatLabels[] = reservation_seat_key((string) ($seat['seat_row'] ?? ''), (int) ($seat['seat_number'] ?? 0));
    }

    $confirmationStatus = (string) ($reservationConfirmation['status'] ?? '');
    $confirmationStatusClass = reservation_status_css_class($confirmationStatus);
    $confirmationStatusLabel = reservation_status_label($confirmationStatus);
    $confirmationSeatSummary = $confirmedSeatLabels !== [] ? implode(', ', $confirmedSeatLabels) : 'Sin butacas';
    $confirmationFormatLabel = trim(
        (string) ($showtime['format_label'] ?? '') . ' - ' . (string) ($showtime['language_label'] ?? ''),
        ' -'
    );
    $confirmationMovieLabel = trim(
        (string) ($showtime['movie_title'] ?? '') . ($confirmationFormatLabel !== '' ? ' - ' . $confirmationFormatLabel : '')
    );
    $confirmationRoomLabel = trim(
        (string) ($showtime['room_name'] ?? '') . ' - ' . (string) ($showtime['room_location'] ?? ''),
        ' -'
    );
    $confirmationTotalLabel = reservation_format_money((float) ($reservationConfirmation['total_amount'] ?? $ticketTotal));
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen seat-selection-screen">
    <?php
    $activeNav = 'cartelera';
    require __DIR__ . '/partials/header.php';
    ?>

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
        <?php elseif ($hasReservationConfirmation): ?>
            <section class="reservation-confirmation-panel" aria-labelledby="reservation-confirmation-title" aria-live="polite">
                <div class="reservation-confirmation-head">
                    <span class="reservation-confirmation-mark" aria-hidden="true"></span>
                    <div class="reservation-confirmation-copy">
                        <p class="eyebrow">Reserva creada</p>
                        <h1 id="reservation-confirmation-title">Reserva confirmada</h1>
                        <p>Guardamos tu reserva. Puedes revisarla desde tu perfil de reservas.</p>
                    </div>
                    <div class="reservation-confirmation-meta">
                        <span class="reservation-confirmation-id">Reserva #<?= e($reservationConfirmation['id'] ?? '') ?></span>
                        <span class="reservation-status-badge status-<?= e($confirmationStatusClass) ?>"><?= e($confirmationStatusLabel) ?></span>
                    </div>
                </div>

                <dl class="reservation-confirmation-details">
                    <div>
                        <dt>Pelicula</dt>
                        <dd><?= e($confirmationMovieLabel !== '' ? $confirmationMovieLabel : 'Sin pelicula') ?></dd>
                    </div>
                    <div>
                        <dt>Funcion</dt>
                        <dd><?= e($showtimeLabels['datetime'] !== '' ? $showtimeLabels['datetime'] : 'Sin horario') ?></dd>
                    </div>
                    <div>
                        <dt>Sala</dt>
                        <dd><?= e($confirmationRoomLabel !== '' ? $confirmationRoomLabel : 'Sin sala') ?></dd>
                    </div>
                    <div>
                        <dt>Butacas</dt>
                        <dd><?= e($confirmationSeatSummary) ?></dd>
                    </div>
                    <div>
                        <dt>Total</dt>
                        <dd><?= e($confirmationTotalLabel) ?></dd>
                    </div>
                    <div>
                        <dt>Estado</dt>
                        <dd><?= e($confirmationStatusLabel) ?></dd>
                    </div>
                </dl>

                <div class="reservation-confirmation-actions" aria-label="Acciones de reserva">
                    <a class="reservation-confirmation-action reservation-confirmation-action-primary" href="index.php?page=ticket&amp;reservation_id=<?= e($reservationConfirmation['id'] ?? '') ?>">Ver ticket</a>
                    <a class="reservation-confirmation-action reservation-confirmation-action-primary" href="index.php?page=my_reservations">Ver mis reservas</a>
                    <a class="reservation-confirmation-action reservation-confirmation-action-secondary" href="index.php?page=cartelera">Volver a cartelera</a>
                </div>
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
                        data-ticket-total="<?= e($ticketTotalLabel) ?>"
                    >
                        <?= csrf_token_field() ?>
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
                                    <h2>Resumen</h2>

                                    <dl class="seat-selection-metrics">
                                        <div>
                                            <dt>Entradas</dt>
                                            <dd><span data-seat-ticket-count><?= e($ticketCount) ?></span> seleccionadas</dd>
                                        </div>
                                        <div>
                                            <dt>Butacas</dt>
                                            <dd><span data-seat-selected-count><?= e($initialSelectedSeatCount) ?></span> / <?= e($ticketCount) ?> seleccionadas</dd>
                                        </div>
                                        <div>
                                            <dt>Faltan</dt>
                                            <dd data-seat-remaining><?= e($initialRemainingLabel) ?></dd>
                                        </div>
                                        <div>
                                            <dt>Total</dt>
                                            <dd data-seat-current-total><?= e($ticketTotalLabel) ?></dd>
                                        </div>
                                    </dl>

                                    <div class="seat-selected-list-box">
                                        <span>Seleccionadas</span>
                                        <strong data-seat-selected-list><?= e($initialSeatListLabel) ?></strong>
                                    </div>

                                    <p class="seat-submit-state" data-seat-submit-state><?= e($initialButtonStateLabel) ?></p>
                                    <p class="seat-submit-message" data-seat-submit-message hidden><?= e($initialSubmitMessage) ?></p>
                                </div>

                                <div class="seat-submit-wrap" data-seat-submit-guard>
                                    <button class="seat-submit" type="submit" data-seat-submit>
                                        Reservar entradas
                                    </button>
                                </div>
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
