<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/assets.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/../models/Movie.php';
require_once __DIR__ . '/../models/Reservation.php';
require_once __DIR__ . '/../models/User.php';

const AUTH_MIN_PASSWORD_LENGTH = 8;

function auth_mode_from_page(?string $page): string
{
    return $page === 'register' ? 'register' : 'login';
}

function render_auth_page(string $mode, array $errors = [], array $old = []): void
{
    $mode = auth_mode_from_page($mode);
    $messages = flash_get();

    require __DIR__ . '/../views/auth.php';
}

function render_dashboard(): void
{
    auth_require_login();
    $user = current_user();
    $messages = flash_get();
    $movies = [];
    $movieLoadError = false;

    try {
        $movies = movie_active_all();
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $movieLoadError = true;
    }

    require __DIR__ . '/../views/dashboard.php';
}

function render_movie_detail(): void
{
    auth_require_login();

    $user = current_user();
    $messages = flash_get();
    $movie = null;
    $showtimeDays = [];
    $movieLoadError = false;
    $movieNotFound = false;
    $movieId = movie_id_from_request($_GET['id'] ?? null);

    if ($movieId === null) {
        http_response_code(404);
        $movieNotFound = true;

        require __DIR__ . '/../views/movie_detail.php';
        return;
    }

    try {
        $movie = movie_find_active_by_id($movieId);

        if ($movie === null) {
            http_response_code(404);
            $movieNotFound = true;
        } else {
            $showtimeDays = movie_showtimes_by_day(movie_active_showtimes($movieId));
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        $movieLoadError = true;
    }

    require __DIR__ . '/../views/movie_detail.php';
}

function render_seat_selection(): void
{
    auth_require_login();

    $showtimeId = positive_int_from_request($_GET['showtime_id'] ?? null);
    $ticketCount = reservation_ticket_count_from_request($_GET['tickets'] ?? null);
    $reservationId = positive_int_from_request($_GET['reservation_id'] ?? null);
    $errors = [];

    if ($ticketCount === null) {
        $ticketCount = 1;
        $errors[] = 'Selecciona al menos una entrada valida.';
    }

    render_seat_selection_view($showtimeId, $ticketCount, [], $errors, $reservationId);
}

function handle_reservation_create(): void
{
    auth_require_login();

    $user = current_user();
    $showtimeId = positive_int_from_request($_POST['showtime_id'] ?? null);
    $ticketCount = reservation_ticket_count_from_request($_POST['ticket_count'] ?? null);
    $selectedSeats = reservation_parse_selected_seats($_POST['seats'] ?? []);
    $errors = [];
    $showtime = null;

    if ($showtimeId === null) {
        $errors[] = 'Selecciona una funcion valida.';
    }

    if ($ticketCount === null) {
        $ticketCount = 1;
        $errors[] = 'Selecciona al menos una entrada valida.';
    }

    if ($selectedSeats === []) {
        $errors[] = 'Selecciona las butacas para tu reserva.';
    }

    try {
        if ($showtimeId !== null) {
            $showtime = reservation_showtime_find_active($showtimeId);
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $errors[] = 'No se pudo validar la funcion en este momento.';
    }

    if ($showtimeId !== null && $showtime === null) {
        $errors[] = 'La funcion seleccionada no existe o no esta activa.';
    }

    if ($showtime !== null) {
        $seatMap = reservation_generate_seat_map((int) $showtime['room_capacity']);

        if ($ticketCount > count($seatMap['lookup'])) {
            $errors[] = 'La cantidad de entradas supera la capacidad de la sala.';
        }

        if ($selectedSeats !== [] && count($selectedSeats) !== $ticketCount) {
            $errors[] = 'Debes seleccionar exactamente ' . $ticketCount . ' butaca(s).';
        }

        foreach ($selectedSeats as $seat) {
            $seatKey = reservation_seat_key((string) $seat['row'], (int) $seat['number']);

            if (!isset($seatMap['lookup'][$seatKey])) {
                $errors[] = 'Una o mas butacas seleccionadas no existen en esta sala.';
                break;
            }
        }

        try {
            $occupiedSeats = reservation_occupied_seats_for_showtime((int) $showtime['id']);
            $conflicts = reservation_selected_occupied_seats($selectedSeats, $occupiedSeats);

            if ($conflicts !== []) {
                $errors[] = 'Una o mas butacas seleccionadas ya estan ocupadas.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar la disponibilidad de butacas.';
        }
    }

    if ($errors !== []) {
        render_seat_selection_view($showtimeId, $ticketCount, $selectedSeats, $errors);
        return;
    }

    $result = reservation_create_with_seats((int) ($user['id'] ?? 0), $showtime, $selectedSeats, $ticketCount);

    if (($result['ok'] ?? false) !== true) {
        render_seat_selection_view($showtimeId, $ticketCount, $selectedSeats, $result['errors'] ?? ['No se pudo crear la reserva.']);
        return;
    }

    flash_set('success', 'Reserva creada correctamente.');
    redirect_to(
        'index.php?page=seats&showtime_id=' . (int) $showtimeId
        . '&tickets=' . (int) $ticketCount
        . '&reservation_id=' . (int) $result['reservation_id']
    );
}

function render_seat_selection_view(?int $showtimeId, int $ticketCount, array $selectedSeats = [], array $errors = [], ?int $reservationId = null): void
{
    $user = current_user();
    $messages = flash_get();
    $showtime = null;
    $seatMap = ['rows' => [], 'lookup' => [], 'columns' => RESERVATION_SEATS_PER_ROW];
    $occupiedSeats = [];
    $selectedSeatKeys = array_fill_keys(reservation_selected_keys($selectedSeats), true);
    $reservationConfirmation = null;
    $showtimeLoadError = false;
    $showtimeNotFound = false;
    $showtimeLabels = [
        'date' => '',
        'time' => '',
        'datetime' => '',
    ];

    if ($showtimeId === null) {
        http_response_code(404);
        $showtimeNotFound = true;
    } else {
        try {
            $showtime = reservation_showtime_find_active($showtimeId);

            if ($showtime === null) {
                http_response_code(404);
                $showtimeNotFound = true;
            } else {
                $seatMap = reservation_generate_seat_map((int) $showtime['room_capacity']);
                $occupiedSeats = reservation_occupied_seats_for_showtime((int) $showtime['id']);
                $showtimeLabels = reservation_showtime_labels($showtime);

                if ($reservationId !== null) {
                    $reservationConfirmation = reservation_find_confirmation($reservationId, (int) ($user['id'] ?? 0));

                    if (
                        $reservationConfirmation !== null
                        && (int) ($reservationConfirmation['showtime_id'] ?? 0) !== (int) $showtime['id']
                    ) {
                        $reservationConfirmation = null;
                    }
                }
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            http_response_code(500);
            $showtimeLoadError = true;
        }
    }

    require __DIR__ . '/../views/seat_selection.php';
}

function movie_id_from_request(mixed $value): ?int
{
    return positive_int_from_request($value);
}

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

function movie_showtimes_by_day(array $showtimes): array
{
    $days = [];
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    foreach ($showtimes as $showtime) {
        try {
            $startsAt = new DateTimeImmutable((string) ($showtime['starts_at'] ?? ''));
        } catch (Throwable $exception) {
            continue;
        }

        $dateKey = $startsAt->format('Y-m-d');

        if (!isset($days[$dateKey])) {
            $days[$dateKey] = [
                'date_key' => $dateKey,
                'day_label' => $dateKey === $today ? 'Hoy' : movie_spanish_weekday($startsAt),
                'date_label' => $startsAt->format('j') . '/' . movie_spanish_month($startsAt),
                'showtimes' => [],
            ];
        }

        $days[$dateKey]['showtimes'][] = [
            'id' => (int) ($showtime['id'] ?? 0),
            'time_label' => $startsAt->format('H:i') . ' HRS',
            'format_label' => (string) ($showtime['format_label'] ?? ''),
            'language_label' => (string) ($showtime['language_label'] ?? ''),
            'room_name' => (string) ($showtime['room_name'] ?? ''),
        ];
    }

    return array_values($days);
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

function render_admin_panel(): void
{
    if (!auth_require_admin()) {
        $user = current_user();
        $messages = [
            [
                'type' => 'error',
                'message' => 'Acceso denegado. Esta vista requiere rol administrador.',
            ],
        ];

        require __DIR__ . '/../views/admin_denied.php';
        return;
    }

    $user = current_user();
    $messages = flash_get();

    require __DIR__ . '/../views/admin.php';
}

function handle_login(): void
{
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $errors = [];

    if ($email === '') {
        $errors[] = 'Ingresa tu correo electronico.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Ingresa un correo electronico valido.';
    }

    if ($password === '') {
        $errors[] = 'Ingresa tu contrasena.';
    }

    if ($errors !== []) {
        render_auth_page('login', $errors, ['email' => $email]);
        return;
    }

    try {
        $user = user_find_by_email($email);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        render_auth_page('login', ['No se pudo iniciar sesion en este momento.'], ['email' => $email]);
        return;
    }

    if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
        render_auth_page('login', ['Correo o contrasena incorrectos.'], ['email' => $email]);
        return;
    }

    auth_login(user_public_payload($user));
    flash_set('success', 'Sesion iniciada correctamente.');
    redirect_to('index.php?page=dashboard');
}

function handle_register(): void
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $errors = [];

    if ($name === '') {
        $errors[] = 'Ingresa tu nombre.';
    }

    if ($email === '') {
        $errors[] = 'Ingresa tu correo electronico.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Ingresa un correo electronico valido.';
    }

    if ($password === '') {
        $errors[] = 'Ingresa una contrasena.';
    } elseif (mb_strlen($password) < AUTH_MIN_PASSWORD_LENGTH) {
        $errors[] = 'La contrasena debe tener al menos ' . AUTH_MIN_PASSWORD_LENGTH . ' caracteres.';
    }

    if ($errors === []) {
        try {
            if (user_email_exists($email)) {
                $errors[] = 'Ya existe una cuenta con ese correo.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar el correo en este momento.';
        }
    }

    if ($errors !== []) {
        render_auth_page('register', $errors, ['name' => $name, 'email' => $email]);
        return;
    }

    try {
        $userId = user_create($name, $email, $password);
        $user = user_find_by_id($userId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        render_auth_page('register', ['No se pudo crear la cuenta en este momento.'], ['name' => $name, 'email' => $email]);
        return;
    }

    if ($user === null) {
        render_auth_page('register', ['No se pudo iniciar la sesion nueva.'], ['name' => $name, 'email' => $email]);
        return;
    }

    auth_login($user);
    flash_set('success', 'Cuenta creada correctamente.');
    redirect_to('index.php?page=dashboard');
}

function handle_logout(): void
{
    auth_logout();
    flash_set('success', 'Sesion cerrada correctamente.');
    redirect_to('index.php?page=login');
}
