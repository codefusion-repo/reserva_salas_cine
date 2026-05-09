<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Reservation.php';

function positive_int_from_request(mixed $value): ?int
{
    if (!is_scalar($value)) {
        return null;
    }

    $value = trim((string) $value);

    if ($value === '' || ctype_digit($value) === false) {
        return null;
    }

    $number = (int) $value;

    return $number > 0 ? $number : null;
}

function reservation_ticket_count_from_request(mixed $value): ?int
{
    $ticketCount = positive_int_from_request($value);

    if ($ticketCount === null || $ticketCount > RESERVATION_MAX_TICKETS) {
        return null;
    }

    return $ticketCount;
}

function reservation_showtime_labels(array $showtime): array
{
    try {
        $startsAt = new DateTimeImmutable((string) ($showtime['starts_at'] ?? ''));
    } catch (Throwable $exception) {
        return [
            'date' => '',
            'time' => '',
            'datetime' => '',
        ];
    }

    return [
        'date' => $startsAt->format('d/m/Y'),
        'time' => $startsAt->format('H:i') . ' HRS',
        'datetime' => movie_spanish_weekday($startsAt) . ' ' . $startsAt->format('j') . ' de ' . movie_spanish_month($startsAt) . ', ' . $startsAt->format('H:i') . ' HRS',
    ];
}


function movie_spanish_weekday(DateTimeImmutable $date): string
{
    $days = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miercoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sabado',
        7 => 'Domingo',
    ];

    return $days[(int) $date->format('N')] ?? '';
}

function movie_spanish_month(DateTimeImmutable $date): string
{
    $months = [
        1 => 'Ene',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dic',
    ];

    return $months[(int) $date->format('n')] ?? '';
}
