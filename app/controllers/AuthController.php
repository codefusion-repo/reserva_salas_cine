<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/assets.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/../models/Admin.php';
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

function render_error_page(string $heading, string $copy, int $statusCode = 404, array $messages = []): void
{
    http_response_code($statusCode);

    $user = current_user();
    $statusLabel = (string) $statusCode;

    require __DIR__ . '/../views/error.php';
}

function render_not_found_page(string $heading, string $copy, array $messages = []): void
{
    render_error_page($heading, $copy, 404, $messages);
}

function movie_filter_value_from_request(mixed $value, int $maxLength = 80): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

    if ($normalized === null) {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($normalized, 0, $maxLength, 'UTF-8');
    }

    return substr($normalized, 0, $maxLength);
}

function movie_active_filters_from_request(array $request): array
{
    return [
        'q' => movie_filter_value_from_request($request['q'] ?? ''),
        'genre' => movie_filter_value_from_request($request['genre'] ?? ''),
        'classification' => movie_filter_value_from_request($request['classification'] ?? ''),
    ];
}

function render_dashboard(): void
{
    auth_require_login();
    $user = current_user();
    $messages = flash_get();
    $movieFilters = movie_active_filters_from_request($_GET);
    $movieFilterOptions = [
        'genres' => [],
        'classifications' => [],
    ];
    $hasActiveMovieFilters = $movieFilters['q'] !== ''
        || $movieFilters['genre'] !== ''
        || $movieFilters['classification'] !== '';
    $movies = [];
    $upcomingMovies = [];
    $upcomingSource = 'inactive_movies';
    $movieLoadError = false;
    $upcomingLoadError = false;

    try {
        $movieFilterOptions = movie_filter_options();
        $movies = movie_active_all($movieFilters);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $movieLoadError = true;
    }

    try {
        $upcomingMovies = movie_upcoming_all();
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $upcomingLoadError = true;
    }

    if (!$upcomingLoadError && $upcomingMovies === []) {
        $upcomingMovies = movie_upcoming_demo_all();
        $upcomingSource = 'demo';
    }

    require __DIR__ . '/../views/dashboard.php';
}

function render_movie_detail(): void
{
    auth_require_login();

    $movie = null;
    $showtimeDays = [];
    $movieLoadError = false;
    $movieNotFound = false;
    $movieId = movie_id_from_request($_GET['id'] ?? null);

    if ($movieId === null) {
        render_not_found_page(
            'Pelicula no encontrada',
            'La pelicula solicitada no existe o no esta activa.'
        );
        return;
    }

    try {
        $movie = movie_find_active_by_id($movieId);

        if ($movie === null) {
            render_not_found_page(
                'Pelicula no encontrada',
                'La pelicula solicitada no existe o no esta activa.'
            );
            return;
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        $user = current_user();
        $messages = flash_get();
        http_response_code(500);
        $movieLoadError = true;

        require __DIR__ . '/../views/movie_detail.php';
        return;
    }

    $user = current_user();
    $messages = flash_get();

    try {
        $showtimeDays = movie_showtimes_by_day(movie_active_showtimes($movieId));
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

    if ($showtimeId === null) {
        render_not_found_page(
            'Funcion no encontrada',
            'La funcion solicitada no existe o no esta activa.'
        );
        return;
    }

    if ($ticketCount === null) {
        $ticketCount = 1;
        $errors[] = 'Selecciona al menos una entrada valida.';
    }

    try {
        if (reservation_showtime_find_active($showtimeId) === null) {
            render_not_found_page(
                'Funcion no encontrada',
                'La funcion solicitada no existe o no esta activa.'
            );
            return;
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        render_seat_selection_view($showtimeId, $ticketCount, [], $errors, $reservationId);
        return;
    }

    render_seat_selection_view($showtimeId, $ticketCount, [], $errors, $reservationId);
}

function handle_reservation_create(): void
{
    auth_require_login();
    csrf_require_valid_post();

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
        $availableSeats = reservation_showtime_available_seats($showtime);

        if (reservation_showtime_is_sold_out($showtime)) {
            $errors[] = 'La funcion seleccionada esta agotada. Elige otro horario.';
        } elseif ($ticketCount > $availableSeats) {
            $errors[] = 'No hay suficientes butacas disponibles para esta funcion.';
        }

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

function render_my_reservations(): void
{
    auth_require_login();

    $user = current_user();
    $messages = flash_get();
    $reservations = [];
    $reservationLoadError = false;

    try {
        $reservations = reservation_user_all((int) ($user['id'] ?? 0));
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        $reservationLoadError = true;
    }

    require __DIR__ . '/../views/my_reservations.php';
}

function render_reservation_ticket(): void
{
    auth_require_login();

    $user = current_user();
    $reservationId = positive_int_from_request($_GET['reservation_id'] ?? null);

    if ($reservationId === null) {
        render_not_found_page(
            'Ticket no encontrado',
            'La reserva solicitada no existe o no pertenece a tu cuenta.'
        );
        return;
    }

    try {
        $reservation = reservation_find_for_user($reservationId, (int) ($user['id'] ?? 0));
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        render_error_page(
            'No se pudo cargar el ticket',
            'Intenta nuevamente mas tarde.',
            500
        );
        return;
    }

    if ($reservation === null) {
        render_not_found_page(
            'Ticket no encontrado',
            'La reserva solicitada no existe o no pertenece a tu cuenta.'
        );
        return;
    }

    $messages = flash_get();

    require __DIR__ . '/../views/ticket.php';
}

function render_coming_soon_page(string $page): void
{
    auth_require_login();

    $pages = [
        'confiteria' => [
            'activeNav' => 'confiteria',
            'title' => 'Confiteria',
            'eyebrow' => 'Proximamente',
            'headline' => 'Confiteria',
            'lead' => 'Muy pronto podras ver combos, cabritas, bebidas y dulces antes de tu funcion.',
            'support' => 'Esta seccion es un adelanto visual. No hay catalogo funcional, carrito ni compra activa.',
            'accent' => 'Carrito futuro',
            'accentCopy' => 'Los productos se muestran solo como teaser de una etapa posterior.',
            'items' => [
                ['icon' => '🍿', 'label' => 'Cabritas', 'copy' => 'Combos para salas y funciones futuras.'],
                ['icon' => '🥤', 'label' => 'Bebidas', 'copy' => 'Opciones de bebestibles en preparacion.'],
                ['icon' => '🍬', 'label' => 'Dulces', 'copy' => 'Snacks y chocolates como contenido demo.'],
                ['icon' => '🛒', 'label' => 'Carrito', 'copy' => 'La compra de confiteria queda fuera de esta iteracion.'],
            ],
            'notes' => [
                'No se puede agregar productos al carrito.',
                'No se procesa ninguna compra de confiteria.',
            ],
        ],
        'socios' => [
            'activeNav' => 'socios',
            'title' => 'Socios',
            'eyebrow' => 'Proximamente',
            'headline' => 'Hazte socio',
            'lead' => 'Beneficios demo para socios llegaran en una etapa futura del sitio.',
            'support' => 'Esta pantalla es solo placeholder visual. No registra membresias ni activa beneficios reales.',
            'accent' => 'Solo en cines',
            'accentCopy' => 'Descuentos demo, puntos ficticios, preventas y cumpleanos quedan planificados.',
            'items' => [
                ['icon' => '⭐', 'label' => 'Descuentos demo', 'copy' => 'Promociones de referencia para una version posterior.'],
                ['icon' => '🎟️', 'label' => 'Preventa', 'copy' => 'Acceso anticipado aun no disponible.'],
                ['icon' => '🎂', 'label' => 'Cumpleanos', 'copy' => 'Beneficios especiales pendientes.'],
                ['icon' => '🏷️', 'label' => 'Cupones', 'copy' => 'Los cupones no estan implementados.'],
            ],
            'notes' => [
                'No existe membresia real ni demo funcional.',
                'No se generan puntos, cupones ni beneficios activos.',
            ],
        ],
        'pago' => [
            'activeNav' => 'pago',
            'title' => 'Pago',
            'eyebrow' => 'Proximamente',
            'headline' => 'Pago simulado',
            'lead' => 'El flujo de pago queda reservado para una iteracion posterior.',
            'support' => 'Esta ruta solo muestra una pagina conceptual. No hay checkout funcional ni confirmacion de pago.',
            'accent' => 'Sin pago real',
            'accentCopy' => 'No existe pasarela, no se solicitan datos de tarjeta y no se almacena informacion bancaria.',
            'items' => [
                ['icon' => '💳', 'label' => 'Sin tarjeta', 'copy' => 'No hay campos para numero, CVV ni vencimiento.'],
                ['icon' => '🧾', 'label' => 'Resumen futuro', 'copy' => 'El comprobante de pago simulado sera otro alcance.'],
                ['icon' => '🚧', 'label' => 'Checkout pendiente', 'copy' => 'El flujo pending a confirmed no se implementa aqui.'],
                ['icon' => '🔒', 'label' => 'Sin pasarela', 'copy' => 'No se conecta ninguna API ni proveedor externo.'],
            ],
            'notes' => [
                'No hay pago real ni pasarela de pago.',
                'No se solicitan ni almacenan datos de tarjeta.',
                'No se modifica el flujo actual de reservas.',
            ],
        ],
    ];

    if (!isset($pages[$page])) {
        render_not_found_page(
            'Pagina no encontrada',
            'La ruta solicitada no existe o ya no esta disponible.'
        );
        return;
    }

    $user = current_user();
    $messages = flash_get();
    $comingSoon = $pages[$page];

    require __DIR__ . '/../views/coming_soon.php';
}

function handle_reservation_cancel(): void
{
    auth_require_login();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        flash_set('error', 'La cancelacion debe realizarse desde el formulario.');
        redirect_to('index.php?page=my_reservations');
    }

    csrf_require_valid_post();

    $user = current_user();
    $reservationId = positive_int_from_request($_POST['reservation_id'] ?? null);

    if ($reservationId === null) {
        flash_set('error', 'Selecciona una reserva valida para cancelar.');
        redirect_to('index.php?page=my_reservations');
    }

    $result = reservation_cancel_for_user($reservationId, (int) ($user['id'] ?? 0));
    flash_set(
        ($result['ok'] ?? false) === true ? 'success' : 'error',
        (string) ($result['message'] ?? 'No se pudo cancelar la reserva.')
    );

    redirect_to('index.php?page=my_reservations');
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
    $showtimeSoldOut = false;
    $availableSeats = 0;

    if ($showtimeId === null) {
        render_not_found_page(
            'Funcion no encontrada',
            'La funcion solicitada no existe o no esta activa.',
            $messages
        );
        return;
    } else {
        try {
            $showtime = reservation_showtime_find_active($showtimeId);

            if ($showtime === null) {
                render_not_found_page(
                    'Funcion no encontrada',
                    'La funcion solicitada no existe o no esta activa.',
                    $messages
                );
                return;
            } else {
                $seatMap = reservation_generate_seat_map((int) $showtime['room_capacity']);
                $occupiedSeats = reservation_occupied_seats_for_showtime((int) $showtime['id']);
                $showtimeLabels = reservation_showtime_labels($showtime);
                $availableSeats = reservation_showtime_available_seats($showtime);
                $showtimeSoldOut = reservation_showtime_is_sold_out($showtime);

                if ($reservationId !== null) {
                    $reservationConfirmation = reservation_find_confirmation($reservationId, (int) ($user['id'] ?? 0));

                    if (
                        $reservationConfirmation !== null
                        && (int) ($reservationConfirmation['showtime_id'] ?? 0) !== (int) $showtime['id']
                    ) {
                        $reservationConfirmation = null;
                    }
                }

                $hasSoldOutError = false;

                foreach ($errors as $error) {
                    if (strpos((string) $error, 'agotada') !== false) {
                        $hasSoldOutError = true;
                        break;
                    }
                }

                if ($showtimeSoldOut && $reservationConfirmation === null && !$hasSoldOutError) {
                    $errors[] = 'La funcion esta agotada. Elige otro horario para continuar.';
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
            'available_seats' => max(0, (int) ($showtime['available_seats'] ?? 0)),
            'is_sold_out' => max(0, (int) ($showtime['available_seats'] ?? 0)) <= 0,
            'availability_label' => movie_showtime_availability_label((int) ($showtime['available_seats'] ?? 0)),
            'availability_state' => movie_showtime_availability_state((int) ($showtime['available_seats'] ?? 0)),
        ];
    }

    return array_values($days);
}

function movie_showtime_availability_label(int $availableSeats): string
{
    $availableSeats = max(0, $availableSeats);

    if ($availableSeats === 0) {
        return 'Agotada';
    }

    if ($availableSeats <= 5) {
        return 'Ultimas butacas: ' . $availableSeats . ' disponibles';
    }

    return $availableSeats . ' disponibles';
}

function movie_showtime_availability_state(int $availableSeats): string
{
    $availableSeats = max(0, $availableSeats);

    if ($availableSeats === 0) {
        return 'none';
    }

    if ($availableSeats <= 5) {
        return 'low';
    }

    return 'available';
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
    $rooms = [];
    $activeRooms = [];
    $movies = [];
    $activeMovies = [];
    $showtimes = [];
    $adminSummary = [];
    $adminLoadError = false;

    try {
        $adminSummary = admin_summary_stats();
        $rooms = admin_rooms_all();
        $activeRooms = admin_rooms_active_all();
        $movies = admin_movies_all();
        $activeMovies = admin_movies_active_all();
        $showtimes = admin_showtimes_all();
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        $adminLoadError = true;
    }

    require __DIR__ . '/../views/admin.php';
}

function handle_room_create(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    [$payload, $errors] = admin_room_payload_from_post();

    if ($errors === []) {
        try {
            if (admin_room_name_exists($payload['name'])) {
                $errors[] = 'Ya existe una sala con ese nombre.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar el nombre de la sala.';
        }
    }

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to('index.php?page=admin#admin-rooms');
    }

    try {
        admin_room_create($payload['name'], $payload['location'], $payload['capacity']);
        flash_set('success', 'Sala creada correctamente.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo crear la sala. Revisa que el nombre no este duplicado.');
    }

    redirect_to('index.php?page=admin#admin-rooms');
}

function handle_room_update(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    $roomId = positive_int_from_request($_POST['room_id'] ?? null);
    [$payload, $errors] = admin_room_payload_from_post();

    if ($roomId === null) {
        $errors[] = 'Selecciona una sala valida para editar.';
    }

    if ($errors === [] && $roomId !== null) {
        try {
            if (admin_room_find_by_id((int) $roomId) === null) {
                $errors[] = 'La sala seleccionada no existe.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar la sala seleccionada.';
        }
    }

    if ($errors === []) {
        try {
            if (admin_room_name_exists($payload['name'], (int) $roomId)) {
                $errors[] = 'Ya existe otra sala con ese nombre.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar el nombre de la sala.';
        }
    }

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to('index.php?page=admin#admin-rooms');
    }

    try {
        admin_room_update((int) $roomId, $payload['name'], $payload['location'], $payload['capacity']);
        flash_set('success', 'Sala actualizada correctamente.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo actualizar la sala. Revisa que el nombre no este duplicado.');
    }

    redirect_to('index.php?page=admin#admin-rooms');
}

function handle_room_deactivate(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    $roomId = positive_int_from_request($_POST['room_id'] ?? null);

    if ($roomId === null) {
        flash_set('error', 'Selecciona una sala valida para desactivar.');
        redirect_to('index.php?page=admin#admin-rooms');
    }

    try {
        if (admin_room_find_by_id((int) $roomId) === null) {
            flash_set('error', 'La sala seleccionada no existe.');
        } else {
            admin_room_deactivate((int) $roomId);
            flash_set('success', 'Sala desactivada correctamente.');
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo desactivar la sala en este momento.');
    }

    redirect_to('index.php?page=admin#admin-rooms');
}

function handle_movie_create(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    [$payload, $errors] = admin_movie_payload_from_post();

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to('index.php?page=admin#admin-movies');
    }

    try {
        admin_movie_create(
            $payload['title'],
            $payload['synopsis'],
            $payload['genre'],
            $payload['release_year'],
            $payload['classification'],
            $payload['poster_path'],
            $payload['is_active']
        );
        flash_set('success', 'Pelicula creada correctamente.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo crear la pelicula en este momento.');
    }

    redirect_to('index.php?page=admin#admin-movies');
}

function handle_movie_update(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    $movieId = positive_int_from_request($_POST['movie_id'] ?? null);
    [$payload, $errors] = admin_movie_payload_from_post();

    if ($movieId === null) {
        $errors[] = 'Selecciona una pelicula valida para editar.';
    }

    if ($errors === [] && $movieId !== null) {
        try {
            if (admin_movie_find_by_id((int) $movieId) === null) {
                $errors[] = 'La pelicula seleccionada no existe.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar la pelicula seleccionada.';
        }
    }

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to('index.php?page=admin#admin-movies');
    }

    try {
        admin_movie_update(
            (int) $movieId,
            $payload['title'],
            $payload['synopsis'],
            $payload['genre'],
            $payload['release_year'],
            $payload['classification'],
            $payload['poster_path'],
            $payload['is_active']
        );
        flash_set('success', 'Pelicula actualizada correctamente.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo actualizar la pelicula en este momento.');
    }

    redirect_to('index.php?page=admin#admin-movies');
}

function handle_movie_set_active(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    $movieId = positive_int_from_request($_POST['movie_id'] ?? null);
    $targetStatus = admin_bool_from_post($_POST['target_status'] ?? 0);

    if ($movieId === null) {
        flash_set('error', 'Selecciona una pelicula valida para cambiar su estado.');
        redirect_to('index.php?page=admin#admin-movies');
    }

    try {
        if (admin_movie_find_by_id((int) $movieId) === null) {
            flash_set('error', 'La pelicula seleccionada no existe.');
        } else {
            admin_movie_set_active((int) $movieId, $targetStatus);
            flash_set(
                'success',
                $targetStatus ? 'Pelicula activada correctamente.' : 'Pelicula desactivada correctamente.'
            );
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo cambiar el estado de la pelicula.');
    }

    redirect_to('index.php?page=admin#admin-movies');
}

function handle_showtime_create(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    [$payload, $errors] = admin_showtime_payload_from_post(null);

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to('index.php?page=admin#admin-showtimes');
    }

    try {
        admin_showtime_create(
            $payload['movie_id'],
            $payload['room_id'],
            $payload['starts_at'],
            $payload['ends_at'],
            $payload['format_label'],
            $payload['language_label']
        );
        flash_set('success', 'Funcion creada correctamente.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo crear la funcion en este momento.');
    }

    redirect_to('index.php?page=admin#admin-showtimes');
}

function handle_showtime_update(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    $showtimeId = positive_int_from_request($_POST['showtime_id'] ?? null);
    $errors = [];

    if ($showtimeId === null) {
        $errors[] = 'Selecciona una funcion valida para editar.';
    } else {
        try {
            if (admin_showtime_find_by_id((int) $showtimeId) === null) {
                $errors[] = 'La funcion seleccionada no existe.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar la funcion seleccionada.';
        }
    }

    [$payload, $payloadErrors] = admin_showtime_payload_from_post($showtimeId);
    $errors = array_merge($errors, $payloadErrors);

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to('index.php?page=admin#admin-showtimes');
    }

    try {
        admin_showtime_update(
            (int) $showtimeId,
            $payload['movie_id'],
            $payload['room_id'],
            $payload['starts_at'],
            $payload['ends_at'],
            $payload['format_label'],
            $payload['language_label'],
            $payload['is_active']
        );
        flash_set('success', 'Funcion actualizada correctamente.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo actualizar la funcion en este momento.');
    }

    redirect_to('index.php?page=admin#admin-showtimes');
}

function handle_showtime_deactivate(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    $showtimeId = positive_int_from_request($_POST['showtime_id'] ?? null);
    $targetStatus = admin_bool_from_post($_POST['target_status'] ?? 0);
    $errors = [];

    if ($showtimeId === null) {
        $errors[] = 'Selecciona una funcion valida para cambiar su estado.';
    }

    $showtime = null;

    if ($showtimeId !== null) {
        try {
            $showtime = admin_showtime_find_by_id((int) $showtimeId);

            if ($showtime === null) {
                $errors[] = 'La funcion seleccionada no existe.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar la funcion seleccionada.';
        }
    }

    if ($targetStatus && $showtime !== null) {
        if ((int) ($showtime['movie_is_active'] ?? 0) !== 1) {
            $errors[] = 'No se puede activar una funcion con pelicula inactiva.';
        }

        if ((int) ($showtime['room_is_active'] ?? 0) !== 1) {
            $errors[] = 'No se puede activar una funcion con sala inactiva.';
        }

        try {
            if (
                admin_showtime_has_overlap(
                    (int) $showtime['room_id'],
                    (string) $showtime['starts_at'],
                    (string) $showtime['ends_at'],
                    (int) $showtime['id']
                )
            ) {
                $errors[] = 'La funcion se traslapa con otra funcion activa en la misma sala.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar el traslape de horarios.';
        }
    }

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to('index.php?page=admin#admin-showtimes');
    }

    try {
        if ($targetStatus) {
            admin_showtime_set_active((int) $showtimeId, true);
            flash_set('success', 'Funcion activada correctamente.');
        } else {
            admin_showtime_deactivate((int) $showtimeId);
            flash_set('success', 'Funcion desactivada correctamente.');
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo cambiar el estado de la funcion.');
    }

    redirect_to('index.php?page=admin#admin-showtimes');
}

function admin_room_payload_from_post(): array
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $capacity = positive_int_from_request($_POST['capacity'] ?? null);
    $errors = [];

    if ($name === '') {
        $errors[] = 'El nombre de la sala es obligatorio.';
    }

    if ($location === '') {
        $errors[] = 'La ubicacion de la sala es obligatoria.';
    }

    if ($capacity === null) {
        $errors[] = 'La capacidad debe ser un numero entero positivo.';
    }

    return [
        [
            'name' => $name,
            'location' => $location,
            'capacity' => $capacity ?? 0,
        ],
        $errors,
    ];
}

function admin_movie_payload_from_post(): array
{
    $title = admin_trimmed_text_from_post($_POST['title'] ?? '');
    $synopsis = admin_trimmed_text_from_post($_POST['synopsis'] ?? '');
    $genre = admin_trimmed_text_from_post($_POST['genre'] ?? '');
    $releaseYear = admin_movie_release_year_from_post($_POST['release_year'] ?? null);
    $classification = admin_trimmed_text_from_post($_POST['classification'] ?? '');
    [$posterPath, $posterPathError] = admin_movie_poster_path_from_post($_POST['poster_path'] ?? '');
    $isActive = admin_bool_from_post($_POST['is_active'] ?? 1);
    $errors = [];

    if ($title === '') {
        $errors[] = 'El titulo de la pelicula es obligatorio.';
    } elseif (admin_text_length($title) > 180) {
        $errors[] = 'El titulo no puede superar 180 caracteres.';
    }

    if ($synopsis === '') {
        $errors[] = 'La sinopsis de la pelicula es obligatoria.';
    }

    if ($genre === '') {
        $errors[] = 'El genero de la pelicula es obligatorio.';
    } elseif (admin_text_length($genre) > 80) {
        $errors[] = 'El genero no puede superar 80 caracteres.';
    }

    if ($releaseYear === null) {
        $errors[] = 'El ano de estreno debe ser un numero entre 1888 y ' . admin_movie_release_year_max() . '.';
    }

    if ($classification === '') {
        $errors[] = 'La clasificacion de la pelicula es obligatoria.';
    } elseif (admin_text_length($classification) > 20) {
        $errors[] = 'La clasificacion no puede superar 20 caracteres.';
    }

    if ($posterPathError !== null) {
        $errors[] = $posterPathError;
    }

    return [
        [
            'title' => $title,
            'synopsis' => $synopsis,
            'genre' => $genre,
            'release_year' => $releaseYear ?? 0,
            'classification' => $classification,
            'poster_path' => $posterPath,
            'is_active' => $isActive,
        ],
        $errors,
    ];
}

function admin_showtime_payload_from_post(?int $excludeShowtimeId): array
{
    $movieId = positive_int_from_request($_POST['movie_id'] ?? null);
    $roomId = positive_int_from_request($_POST['room_id'] ?? null);
    $startsAt = admin_datetime_from_post($_POST['starts_at'] ?? null);
    $endsAt = admin_datetime_from_post($_POST['ends_at'] ?? null);
    $formatLabel = admin_trimmed_label($_POST['format_label'] ?? '', '2D');
    $languageLabel = admin_trimmed_label($_POST['language_label'] ?? '', 'Subtitulada');
    $isActive = admin_bool_from_post($_POST['is_active'] ?? 1);
    $errors = [];

    if ($movieId === null) {
        $errors[] = 'Selecciona una pelicula activa.';
    }

    if ($roomId === null) {
        $errors[] = 'Selecciona una sala activa.';
    }

    if ($startsAt === null) {
        $errors[] = 'Ingresa una fecha y hora de inicio valida.';
    }

    if ($endsAt === null) {
        $errors[] = 'Ingresa una fecha y hora de termino valida.';
    }

    if ($movieId !== null) {
        try {
            $movieCanBeUsed = admin_movie_active_find_by_id($movieId) !== null;

            if (!$movieCanBeUsed && $excludeShowtimeId !== null) {
                $showtime = admin_showtime_find_by_id($excludeShowtimeId);
                $movieCanBeUsed = $showtime !== null && (int) ($showtime['movie_id'] ?? 0) === $movieId;
            }

            if (!$movieCanBeUsed) {
                $errors[] = 'La pelicula seleccionada no existe o no esta activa.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar la pelicula seleccionada.';
        }
    }

    if ($roomId !== null) {
        try {
            if (admin_room_active_find_by_id($roomId) === null) {
                $errors[] = 'La sala seleccionada no existe o no esta activa.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar la sala seleccionada.';
        }
    }

    if ($startsAt !== null && $endsAt !== null) {
        if (new DateTimeImmutable($endsAt) <= new DateTimeImmutable($startsAt)) {
            $errors[] = 'La hora de termino debe ser posterior a la hora de inicio.';
        }
    }

    if ($errors === [] && $isActive && $roomId !== null && $startsAt !== null && $endsAt !== null) {
        try {
            if (admin_showtime_has_overlap($roomId, $startsAt, $endsAt, $excludeShowtimeId)) {
                $errors[] = 'La funcion se traslapa con otra funcion activa en la misma sala.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar el traslape de horarios.';
        }
    }

    return [
        [
            'movie_id' => $movieId ?? 0,
            'room_id' => $roomId ?? 0,
            'starts_at' => $startsAt ?? '',
            'ends_at' => $endsAt ?? '',
            'format_label' => $formatLabel,
            'language_label' => $languageLabel,
            'is_active' => $isActive,
        ],
        $errors,
    ];
}

function admin_trimmed_text_from_post(mixed $value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    return trim((string) $value);
}

function admin_text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

function admin_movie_release_year_from_post(mixed $value): ?int
{
    if (!is_scalar($value)) {
        return null;
    }

    $value = trim((string) $value);

    if ($value === '' || ctype_digit($value) === false) {
        return null;
    }

    $year = (int) $value;

    if ($year < 1888 || $year > admin_movie_release_year_max()) {
        return null;
    }

    return $year;
}

function admin_movie_release_year_max(): int
{
    return (int) date('Y') + 10;
}

function admin_movie_poster_path_from_post(mixed $value): array
{
    if (!is_scalar($value)) {
        return [null, 'La ruta del poster no es valida.'];
    }

    $path = trim((string) $value);

    if ($path === '') {
        return [null, null];
    }

    if (admin_text_length($path) > 255) {
        return [null, 'La ruta del poster no puede superar 255 caracteres.'];
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
        return [null, 'La ruta del poster no puede contener caracteres de control.'];
    }

    $normalizedPath = str_replace('\\', '/', $path);

    if (str_starts_with($normalizedPath, '/')) {
        return [null, 'La ruta del poster debe ser relativa al directorio public.'];
    }

    if (
        preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $normalizedPath) === 1
        || preg_match('#^[a-z][a-z0-9+.-]*:#i', $normalizedPath) === 1
    ) {
        return [null, 'La ruta del poster no puede ser una URL ni una ruta absoluta.'];
    }

    $segments = explode('/', $normalizedPath);

    if (in_array('..', $segments, true) || in_array('.', $segments, true) || in_array('', $segments, true)) {
        return [null, 'La ruta del poster no puede contener traversal ni segmentos vacios.'];
    }

    return [$normalizedPath, null];
}

function admin_datetime_from_post(mixed $value): ?string
{
    if (!is_scalar($value)) {
        return null;
    }

    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];

    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date instanceof DateTimeImmutable && ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0))) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    return null;
}

function admin_trimmed_label(mixed $value, string $fallback): string
{
    if (!is_scalar($value)) {
        return $fallback;
    }

    $value = trim((string) $value);

    if ($value === '') {
        return $fallback;
    }

    return mb_substr($value, 0, 40);
}

function admin_bool_from_post(mixed $value): bool
{
    return is_scalar($value) && (string) $value === '1';
}

function admin_flash_errors(array $errors): void
{
    foreach ($errors as $error) {
        flash_set('error', (string) $error);
    }
}

function handle_login(): void
{
    csrf_require_valid_post();

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
    csrf_require_valid_post();

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
