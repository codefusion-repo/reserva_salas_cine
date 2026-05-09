<?php
declare(strict_types=1);

require_once __DIR__ . '/ErrorController.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/checkout_view.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/reservation_view.php';
require_once __DIR__ . '/../models/Reservation.php';

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

    flash_set('success', 'Reserva pendiente creada. Confirma el pago simulado para completarla.');
    redirect_to(checkout_url('reservation', ['reservation_id' => (int) $result['reservation_id']]));
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
