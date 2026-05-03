<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/database.php';

const RESERVATION_ACTIVE_STATUSES = ['pending', 'confirmed'];
const RESERVATION_SEATS_PER_ROW = 10;
const RESERVATION_STANDARD_TICKET_PRICE = 7900;
const RESERVATION_MAX_TICKETS = 10;

function reservation_showtime_find_active(int $showtimeId): ?array
{
    return db_fetch_one(
        'SELECT
            s.id,
            s.movie_id,
            s.room_id,
            s.starts_at,
            s.ends_at,
            s.format_label,
            s.language_label,
            m.title AS movie_title,
            m.classification,
            r.name AS room_name,
            r.location AS room_location,
            r.capacity AS room_capacity
         FROM showtimes s
         INNER JOIN movies m ON m.id = s.movie_id
         INNER JOIN rooms r ON r.id = s.room_id
         WHERE s.id = :id
           AND s.is_active = :showtime_active
           AND m.is_active = :movie_active
           AND r.is_active = :room_active
         LIMIT 1',
        [
            'id' => $showtimeId,
            'showtime_active' => 1,
            'movie_active' => 1,
            'room_active' => 1,
        ]
    );
}

function reservation_occupied_seats_for_showtime(int $showtimeId): array
{
    $rows = db_fetch_all(
        'SELECT rs.seat_row, rs.seat_number
         FROM reservation_seats rs
         INNER JOIN reservations r ON r.id = rs.reservation_id
         WHERE rs.showtime_id = :showtime_id
           AND r.status IN (:status_pending, :status_confirmed)
         ORDER BY rs.seat_row ASC, rs.seat_number ASC',
        [
            'showtime_id' => $showtimeId,
            'status_pending' => 'pending',
            'status_confirmed' => 'confirmed',
        ]
    );

    $occupied = [];

    foreach ($rows as $row) {
        $seatRow = (string) ($row['seat_row'] ?? '');
        $seatNumber = (int) ($row['seat_number'] ?? 0);

        if ($seatRow !== '' && $seatNumber > 0) {
            $occupied[reservation_seat_key($seatRow, $seatNumber)] = true;
        }
    }

    return $occupied;
}

function reservation_find_confirmation(int $reservationId, int $userId): ?array
{
    $reservation = db_fetch_one(
        'SELECT
            r.id,
            r.user_id,
            r.showtime_id,
            r.status,
            r.total_amount,
            r.created_at
         FROM reservations r
         WHERE r.id = :id
           AND r.user_id = :user_id
         LIMIT 1',
        [
            'id' => $reservationId,
            'user_id' => $userId,
        ]
    );

    if ($reservation === null) {
        return null;
    }

    $reservation['seats'] = db_fetch_all(
        'SELECT seat_row, seat_number, seat_type
         FROM reservation_seats
         WHERE reservation_id = :reservation_id
         ORDER BY seat_row ASC, seat_number ASC',
        ['reservation_id' => $reservationId]
    );

    return $reservation;
}

function reservation_user_all(int $userId): array
{
    return db_fetch_all(
        "SELECT
            r.id,
            r.user_id,
            r.showtime_id,
            r.status,
            r.total_amount,
            r.created_at,
            r.cancelled_at,
            m.title AS movie_title,
            rm.name AS room_name,
            rm.location AS room_location,
            s.starts_at,
            s.ends_at,
            s.format_label,
            s.language_label,
            COUNT(rs.id) AS seat_count,
            GROUP_CONCAT(CONCAT(rs.seat_row, '-', rs.seat_number) ORDER BY rs.seat_row ASC, rs.seat_number ASC SEPARATOR ', ') AS seat_labels
         FROM reservations r
         INNER JOIN showtimes s ON s.id = r.showtime_id
         INNER JOIN movies m ON m.id = s.movie_id
         INNER JOIN rooms rm ON rm.id = s.room_id
         LEFT JOIN reservation_seats rs ON rs.reservation_id = r.id
         WHERE r.user_id = :user_id
         GROUP BY
            r.id,
            r.user_id,
            r.showtime_id,
            r.status,
            r.total_amount,
            r.created_at,
            r.cancelled_at,
            m.title,
            rm.name,
            rm.location,
            s.starts_at,
            s.ends_at,
            s.format_label,
            s.language_label
         ORDER BY r.created_at DESC, r.id DESC",
        ['user_id' => $userId]
    );
}

function reservation_find_for_user(int $reservationId, int $userId): ?array
{
    $reservation = db_fetch_one(
        "SELECT
            r.id,
            r.user_id,
            r.showtime_id,
            r.status,
            r.total_amount,
            r.created_at,
            r.cancelled_at,
            m.title AS movie_title,
            rm.name AS room_name,
            rm.location AS room_location,
            s.starts_at,
            s.ends_at,
            s.format_label,
            s.language_label
         FROM reservations r
         INNER JOIN showtimes s ON s.id = r.showtime_id
         INNER JOIN movies m ON m.id = s.movie_id
         INNER JOIN rooms rm ON rm.id = s.room_id
         WHERE r.id = :id
           AND r.user_id = :user_id
         LIMIT 1",
        [
            'id' => $reservationId,
            'user_id' => $userId,
        ]
    );

    if ($reservation === null) {
        return null;
    }

    $reservation['seats'] = db_fetch_all(
        'SELECT seat_row, seat_number, seat_type
         FROM reservation_seats
         WHERE reservation_id = :reservation_id
         ORDER BY seat_row ASC, seat_number ASC',
        ['reservation_id' => $reservationId]
    );

    return $reservation;
}

function reservation_cancel_for_user(int $reservationId, int $userId): array
{
    if ($reservationId <= 0 || $userId <= 0) {
        return [
            'ok' => false,
            'message' => 'Selecciona una reserva valida para cancelar.',
        ];
    }

    $pdo = db();

    try {
        $pdo->beginTransaction();

        $reservationStatement = $pdo->prepare(
            'SELECT id, status
             FROM reservations
             WHERE id = :id
               AND user_id = :user_id
             LIMIT 1
             FOR UPDATE'
        );
        $reservationStatement->execute([
            'id' => $reservationId,
            'user_id' => $userId,
        ]);
        $reservation = $reservationStatement->fetch();

        if ($reservation === false) {
            $pdo->rollBack();

            return [
                'ok' => false,
                'message' => 'La reserva no existe o no pertenece a tu cuenta.',
            ];
        }

        if (!in_array((string) $reservation['status'], RESERVATION_ACTIVE_STATUSES, true)) {
            $pdo->rollBack();

            return [
                'ok' => false,
                'message' => 'La reserva ya fue cancelada o no esta activa.',
            ];
        }

        $updateStatement = $pdo->prepare(
            'UPDATE reservations
             SET status = :cancelled_status,
                 cancelled_at = NOW()
             WHERE id = :id
               AND user_id = :user_id
               AND status IN (:status_pending, :status_confirmed)'
        );
        $updateStatement->execute([
            'cancelled_status' => 'cancelled',
            'id' => $reservationId,
            'user_id' => $userId,
            'status_pending' => 'pending',
            'status_confirmed' => 'confirmed',
        ]);

        if ($updateStatement->rowCount() !== 1) {
            $pdo->rollBack();

            return [
                'ok' => false,
                'message' => 'No se pudo cancelar la reserva porque ya no esta activa.',
            ];
        }

        $pdo->commit();

        return [
            'ok' => true,
            'message' => 'Reserva cancelada correctamente.',
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($exception->getMessage());

        return [
            'ok' => false,
            'message' => 'No se pudo cancelar la reserva en este momento. Intenta nuevamente.',
        ];
    }
}

function reservation_create_with_seats(int $userId, array $showtime, array $selectedSeats, int $ticketCount): array
{
    $pdo = db();
    $showtimeId = (int) $showtime['id'];
    $seatMap = reservation_generate_seat_map((int) $showtime['room_capacity']);
    $totalAmount = reservation_total_amount($ticketCount);

    try {
        $pdo->beginTransaction();

        $occupiedSeats = reservation_occupied_seats_for_showtime($showtimeId);
        $conflicts = reservation_selected_occupied_seats($selectedSeats, $occupiedSeats);

        if ($conflicts !== []) {
            $pdo->rollBack();

            return [
                'ok' => false,
                'errors' => ['Una o mas butacas seleccionadas ya no estan disponibles. Elige otras butacas.'],
                'conflicts' => $conflicts,
            ];
        }

        $reservationStatement = $pdo->prepare(
            'INSERT INTO reservations (user_id, showtime_id, status, total_amount)
             VALUES (:user_id, :showtime_id, :status, :total_amount)'
        );
        $reservationStatement->execute([
            'user_id' => $userId,
            'showtime_id' => $showtimeId,
            'status' => 'confirmed',
            'total_amount' => number_format($totalAmount, 2, '.', ''),
        ]);

        $reservationId = (int) $pdo->lastInsertId();
        $seatStatement = $pdo->prepare(
            'INSERT INTO reservation_seats (reservation_id, showtime_id, seat_row, seat_number, seat_type)
             VALUES (:reservation_id, :showtime_id, :seat_row, :seat_number, :seat_type)'
        );

        foreach ($selectedSeats as $seat) {
            $seatKey = reservation_seat_key($seat['row'], (int) $seat['number']);
            $seatType = $seatMap['lookup'][$seatKey]['type'] ?? 'standard';

            $seatStatement->execute([
                'reservation_id' => $reservationId,
                'showtime_id' => $showtimeId,
                'seat_row' => $seat['row'],
                'seat_number' => (int) $seat['number'],
                'seat_type' => $seatType,
            ]);
        }

        $pdo->commit();

        return [
            'ok' => true,
            'reservation_id' => $reservationId,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($exception->getMessage());

        return [
            'ok' => false,
            'errors' => ['No se pudo crear la reserva en este momento. Intenta nuevamente.'],
            'conflicts' => [],
        ];
    }
}

function reservation_generate_seat_map(int $capacity): array
{
    $rows = [];
    $lookup = [];
    $capacity = max(0, $capacity);

    for ($index = 0; $index < $capacity; $index++) {
        $rowIndex = intdiv($index, RESERVATION_SEATS_PER_ROW);
        $seatNumber = ($index % RESERVATION_SEATS_PER_ROW) + 1;
        $rowLabel = reservation_row_label($rowIndex);
        $seatType = $rowIndex === 0 && $seatNumber === min(RESERVATION_SEATS_PER_ROW, $capacity)
            ? 'accessibility'
            : 'standard';
        $seat = [
            'row' => $rowLabel,
            'number' => $seatNumber,
            'key' => reservation_seat_key($rowLabel, $seatNumber),
            'type' => $seatType,
        ];

        $rows[$rowLabel][$seatNumber] = $seat;
        $lookup[$seat['key']] = $seat;
    }

    return [
        'rows' => $rows,
        'lookup' => $lookup,
        'columns' => RESERVATION_SEATS_PER_ROW,
    ];
}

function reservation_row_label(int $index): string
{
    $label = '';
    $index++;

    while ($index > 0) {
        $index--;
        $label = chr(65 + ($index % 26)) . $label;
        $index = intdiv($index, 26);
    }

    return $label;
}

function reservation_seat_key(string $row, int $number): string
{
    return mb_strtoupper(trim($row)) . '-' . $number;
}

function reservation_parse_selected_seats(mixed $seatValues): array
{
    if (!is_array($seatValues)) {
        return [];
    }

    $seats = [];

    foreach ($seatValues as $seatValue) {
        if (!is_scalar($seatValue)) {
            continue;
        }

        $seatValue = trim((string) $seatValue);

        if (preg_match('/^([A-Za-z]{1,3})-(\d{1,3})$/', $seatValue, $matches) !== 1) {
            continue;
        }

        $row = mb_strtoupper($matches[1]);
        $number = (int) $matches[2];

        if ($number <= 0) {
            continue;
        }

        $seats[reservation_seat_key($row, $number)] = [
            'row' => $row,
            'number' => $number,
        ];
    }

    $seats = array_values($seats);
    usort(
        $seats,
        static function (array $left, array $right): int {
            $rowCompare = strcmp((string) $left['row'], (string) $right['row']);

            if ($rowCompare !== 0) {
                return $rowCompare;
            }

            return (int) $left['number'] <=> (int) $right['number'];
        }
    );

    return $seats;
}

function reservation_selected_keys(array $selectedSeats): array
{
    $keys = [];

    foreach ($selectedSeats as $seat) {
        $keys[] = reservation_seat_key((string) $seat['row'], (int) $seat['number']);
    }

    return $keys;
}

function reservation_selected_occupied_seats(array $selectedSeats, array $occupiedSeats): array
{
    $conflicts = [];

    foreach (reservation_selected_keys($selectedSeats) as $seatKey) {
        if (isset($occupiedSeats[$seatKey])) {
            $conflicts[] = $seatKey;
        }
    }

    return $conflicts;
}

function reservation_total_amount(int $ticketCount): float
{
    return (float) ($ticketCount * RESERVATION_STANDARD_TICKET_PRICE);
}

function reservation_format_money(float $amount): string
{
    return '$' . number_format($amount, 0, ',', '.');
}

function reservation_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmada',
        'cancelled' => 'Cancelada',
        default => 'Sin estado',
    };
}

function reservation_status_css_class(string $status): string
{
    return in_array($status, ['pending', 'confirmed', 'cancelled'], true) ? $status : 'unknown';
}

function reservation_can_cancel(array $reservation): bool
{
    return in_array((string) ($reservation['status'] ?? ''), RESERVATION_ACTIVE_STATUSES, true);
}

function reservation_datetime_label(mixed $value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable $exception) {
        return '';
    }

    return $date->format('d/m/Y H:i') . ' HRS';
}
