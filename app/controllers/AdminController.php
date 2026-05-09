<?php
declare(strict_types=1);

require_once __DIR__ . '/ErrorController.php';
require_once __DIR__ . '/MovieController.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/checkout_view.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/payment_view.php';
require_once __DIR__ . '/../helpers/reservation_view.php';
require_once __DIR__ . '/../models/Admin.php';
require_once __DIR__ . '/../models/ConcessionProduct.php';
require_once __DIR__ . '/../models/Coupon.php';
require_once __DIR__ . '/../models/Movie.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Reservation.php';

const CONCESSION_PRODUCTS_SETUP_MESSAGE = 'La tabla de productos de confitería no está instalada. Ejecuta database/upgrade_concession_products.sql o reimporta schema.sql y seed.sql en el entorno local.';
const COUPONS_SETUP_MESSAGE = 'La tabla de cupones demo no esta instalada. Reimporta database/schema.sql y database/seed.sql en el entorno local.';

function render_admin_panel(): void
{
    if (!auth_require_admin()) {
        render_admin_denied_page();
        return;
    }

    $user = current_user();
    $messages = flash_get();
    $adminSection = admin_section_from_request($_GET['admin_section'] ?? null);
    $adminMode = admin_mode_from_request($_GET['admin_mode'] ?? null);

    if (!in_array($adminSection, ['rooms', 'movies', 'showtimes', 'concessions', 'coupons'], true)) {
        $adminMode = 'list';
    }

    $adminSections = [
        [
            'key' => 'summary',
            'label' => 'Resumen',
            'url' => admin_section_url('summary'),
        ],
        [
            'key' => 'rooms',
            'label' => 'Salas',
            'url' => admin_section_url('rooms'),
        ],
        [
            'key' => 'movies',
            'label' => 'Peliculas',
            'url' => admin_section_url('movies'),
        ],
        [
            'key' => 'showtimes',
            'label' => 'Funciones',
            'url' => admin_section_url('showtimes'),
        ],
        [
            'key' => 'concessions',
            'label' => 'Confiteria',
            'url' => admin_section_url('concessions'),
        ],
        [
            'key' => 'coupons',
            'label' => 'Cupones',
            'url' => admin_section_url('coupons'),
        ],
        [
            'key' => 'payments',
            'label' => 'Pagos',
            'url' => 'index.php?page=admin_payments',
        ],
        [
            'key' => 'reservations',
            'label' => 'Reservas',
            'url' => admin_section_url('reservations'),
        ],
    ];
    $rooms = [];
    $activeRooms = [];
    $movies = [];
    $activeMovies = [];
    $showtimes = [];
    $concessionProducts = [];
    $concessionProductsTableReady = false;
    $coupons = [];
    $couponsTableReady = false;
    $adminShowtimeFilters = admin_showtime_filters_from_request($_GET);
    $adminReservationFilters = admin_reservation_filters_from_request($_GET);
    $adminReservations = [];
    $adminSummary = [];
    $adminEditItem = null;
    $adminModeError = '';
    $adminLoadError = false;
    $adminReservationLoadError = false;

    try {
        $adminSummary = admin_summary_stats();
        $rooms = admin_rooms_all();
        $activeRooms = admin_rooms_active_all();
        $movies = admin_movies_all();
        $activeMovies = admin_movies_active_all();
        $showtimes = admin_showtimes_all($adminShowtimeFilters);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        $adminLoadError = true;
    }

    if (!$adminLoadError) {
        $concessionProductsTableReady = concession_products_table_exists();

        if ($concessionProductsTableReady) {
            try {
                $concessionProducts = concession_products_all();
            } catch (Throwable $exception) {
                error_log($exception->getMessage());
                $concessionProducts = [];
            }
        }

        $couponsTableReady = coupons_table_exists();

        if ($couponsTableReady) {
            try {
                $coupons = coupons_all();
            } catch (Throwable $exception) {
                error_log($exception->getMessage());
                $coupons = [];
            }
        }
    }

    if (!$adminLoadError) {
        try {
            $adminReservations = admin_reservations_all($adminReservationFilters);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $adminReservationLoadError = true;
        }
    }

    if (!$adminLoadError && $adminMode === 'edit') {
        try {
            if ($adminSection === 'rooms') {
                $roomId = positive_int_from_request($_GET['room_id'] ?? null);

                if ($roomId === null) {
                    $adminModeError = 'Selecciona una sala valida para editar.';
                } else {
                    $adminEditItem = admin_room_find_by_id($roomId);
                    $adminModeError = $adminEditItem === null ? 'La sala seleccionada no existe.' : '';
                }
            } elseif ($adminSection === 'movies') {
                $movieId = positive_int_from_request($_GET['movie_id'] ?? null);

                if ($movieId === null) {
                    $adminModeError = 'Selecciona una pelicula valida para editar.';
                } else {
                    $adminEditItem = admin_movie_find_by_id($movieId);
                    $adminModeError = $adminEditItem === null ? 'La pelicula seleccionada no existe.' : '';
                }
            } elseif ($adminSection === 'showtimes') {
                $showtimeId = positive_int_from_request($_GET['showtime_id'] ?? null);

                if ($showtimeId === null) {
                    $adminModeError = 'Selecciona una funcion valida para editar.';
                } else {
                    $adminEditItem = admin_showtime_find_by_id($showtimeId);
                    $adminModeError = $adminEditItem === null ? 'La funcion seleccionada no existe.' : '';
                }
            } elseif ($adminSection === 'concessions') {
                $productId = positive_int_from_request($_GET['product_id'] ?? null);

                if (!$concessionProductsTableReady) {
                    $adminModeError = 'La tabla de productos de confiteria no esta instalada.';
                    $adminEditItem = null;
                } elseif ($productId === null) {
                    $adminModeError = 'Selecciona un producto valido para editar.';
                } else {
                    $adminEditItem = concession_product_find_by_id($productId);
                    $adminModeError = $adminEditItem === null ? 'El producto seleccionado no existe.' : '';
                }
            } elseif ($adminSection === 'coupons') {
                $couponId = positive_int_from_request($_GET['coupon_id'] ?? null);

                if (!$couponsTableReady) {
                    $adminModeError = 'La tabla de cupones demo no esta instalada.';
                    $adminEditItem = null;
                } elseif ($couponId === null) {
                    $adminModeError = 'Selecciona un cupon valido para editar.';
                } else {
                    $adminEditItem = coupon_find_by_id($couponId);
                    $adminModeError = $adminEditItem === null ? 'El cupon seleccionado no existe.' : '';
                }
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $adminModeError = 'No se pudo cargar el registro seleccionado.';
            $adminEditItem = null;
        }
    }

    require __DIR__ . '/../views/admin.php';
}

function render_admin_payments(): void
{
    if (!auth_require_admin()) {
        render_admin_denied_page();
        return;
    }

    $user = current_user();
    $messages = flash_get();
    $adminPaymentFilters = admin_payment_filters_from_request($_GET);
    $adminPayments = [];
    $adminPaymentSummary = [
        'count' => 0,
        'total_amount' => 0.0,
        'latest_date' => '',
    ];
    $adminPaymentLoadError = false;

    try {
        $adminPayments = payment_admin_all($adminPaymentFilters);
        $adminPaymentSummary['count'] = count($adminPayments);

        foreach ($adminPayments as $payment) {
            $adminPaymentSummary['total_amount'] += (float) ($payment['total_amount'] ?? 0);

            if ($adminPaymentSummary['latest_date'] === '') {
                $adminPaymentSummary['latest_date'] = (string) (($payment['paid_at'] ?? '') ?: ($payment['created_at'] ?? ''));
            }
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        $adminPaymentLoadError = true;
    }

    require __DIR__ . '/../views/admin_payments.php';
}

function render_admin_payment_detail(): void
{
    if (!auth_require_admin()) {
        render_admin_denied_page();
        return;
    }

    $paymentId = positive_int_from_request($_GET['payment_id'] ?? null);

    if ($paymentId === null) {
        render_not_found_page(
            'Pago no encontrado',
            'Selecciona un pago valido para revisar.'
        );
        return;
    }

    try {
        $payment = payment_find_for_admin($paymentId);

        if ($payment === null) {
            render_not_found_page(
                'Pago no encontrado',
                'El pago solicitado no existe.'
            );
            return;
        }

        $paymentItems = payment_items_for_payment($paymentId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        render_error_page(
            'No se pudo cargar el pago',
            'Intenta nuevamente mas tarde.',
            500
        );
        return;
    }

    $user = current_user();
    $messages = flash_get();

    require __DIR__ . '/../views/admin_payment_detail.php';
}

function render_admin_invoice(): void
{
    if (!auth_require_admin()) {
        render_admin_denied_page();
        return;
    }

    $paymentId = positive_int_from_request($_GET['payment_id'] ?? null);

    if ($paymentId === null) {
        render_not_found_page(
            'Comprobante no encontrado',
            'Selecciona un pago valido para abrir el comprobante.'
        );
        return;
    }

    try {
        $payment = payment_find_for_admin($paymentId);

        if ($payment === null) {
            render_not_found_page(
                'Comprobante no encontrado',
                'El pago solicitado no existe.'
            );
            return;
        }

        $paymentItems = payment_items_for_payment($paymentId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        render_error_page(
            'No se pudo cargar el comprobante',
            'Intenta nuevamente mas tarde.',
            500
        );
        return;
    }

    $user = current_user();
    $messages = flash_get();
    $invoiceUser = admin_payment_invoice_user($payment);
    $invoiceActiveNav = 'admin';
    $invoiceBackUrl = 'index.php?page=admin_payment_detail&payment_id=' . (int) $paymentId;
    $invoiceDownloadUrl = 'index.php?action=admin_invoice_download&payment_id=' . (int) $paymentId;
    $invoiceBackLabel = 'Volver al detalle admin';
    $invoiceHeadingEyebrow = 'Comprobante admin';

    require __DIR__ . '/../views/invoice.php';
}

function handle_admin_invoice_download(): void
{
    if (!auth_require_admin()) {
        render_admin_denied_page();
        return;
    }

    $paymentId = positive_int_from_request($_GET['payment_id'] ?? null);

    if ($paymentId === null) {
        render_not_found_page(
            'Comprobante no encontrado',
            'Selecciona un pago valido para descargar el comprobante.'
        );
        return;
    }

    try {
        $payment = payment_find_for_admin($paymentId);

        if ($payment === null) {
            render_not_found_page(
                'Comprobante no encontrado',
                'El pago solicitado no existe.'
            );
            return;
        }

        $paymentItems = payment_items_for_payment($paymentId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        render_error_page(
            'No se pudo descargar el comprobante',
            'Intenta nuevamente mas tarde.',
            500
        );
        return;
    }

    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . payment_invoice_filename($payment) . '"');
    header('X-Content-Type-Options: nosniff');

    echo payment_invoice_text($payment, $paymentItems, admin_payment_invoice_user($payment));
}

function admin_payment_invoice_user(array $payment): array
{
    return [
        'name' => (string) ($payment['user_name'] ?? ''),
        'email' => (string) ($payment['user_email'] ?? ''),
    ];
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
        redirect_to(admin_section_url('rooms'));
    }

    try {
        admin_room_create($payload['name'], $payload['location'], $payload['capacity']);
        flash_set('success', 'Sala creada correctamente.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo crear la sala. Revisa que el nombre no este duplicado.');
    }

    redirect_to(admin_section_url('rooms'));
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
        redirect_to(admin_section_url('rooms'));
    }

    try {
        admin_room_update((int) $roomId, $payload['name'], $payload['location'], $payload['capacity']);
        flash_set('success', 'Sala actualizada correctamente.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo actualizar la sala. Revisa que el nombre no este duplicado.');
    }

    redirect_to(admin_section_url('rooms'));
}

function handle_room_deactivate(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    $roomId = positive_int_from_request($_POST['room_id'] ?? null);

    if ($roomId === null) {
        flash_set('error', 'Selecciona una sala valida para desactivar.');
        redirect_to(admin_section_url('rooms'));
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

    redirect_to(admin_section_url('rooms'));
}

function handle_room_set_active(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    $roomId = positive_int_from_request($_POST['room_id'] ?? null);
    $targetStatus = admin_target_status_from_post($_POST['target_status'] ?? null);
    $errors = [];

    if ($roomId === null) {
        $errors[] = 'Selecciona una sala valida para cambiar su estado.';
    }

    if ($targetStatus === null) {
        $errors[] = 'Selecciona un estado valido para la sala.';
    }

    if ($roomId !== null) {
        try {
            if (admin_room_find_by_id((int) $roomId) === null) {
                $errors[] = 'La sala seleccionada no existe.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar la sala seleccionada.';
        }
    }

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to(admin_section_url('rooms'));
    }

    try {
        admin_room_set_active((int) $roomId, $targetStatus);
        flash_set(
            'success',
            $targetStatus ? 'Sala activada correctamente.' : 'Sala desactivada correctamente.'
        );
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo actualizar el estado de la sala en este momento.');
    }

    redirect_to(admin_section_url('rooms'));
}

function handle_room_delete(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    $roomId = positive_int_from_request($_POST['room_id'] ?? null);

    if ($roomId === null) {
        flash_set('error', 'Selecciona una sala valida para eliminar.');
        redirect_to(admin_section_url('rooms'));
    }

    try {
        if (admin_room_find_by_id((int) $roomId) === null) {
            flash_set('error', 'La sala seleccionada no existe.');
        } elseif (admin_room_has_showtimes((int) $roomId)) {
            flash_set('error', 'No se puede eliminar esta sala porque tiene funciones asociadas. Puedes desactivarla.');
        } else {
            admin_room_delete((int) $roomId);
            flash_set('success', 'Sala eliminada correctamente.');
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo eliminar la sala en este momento.');
    }

    redirect_to(admin_section_url('rooms'));
}

function handle_movie_create(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    [$payload, $errors] = admin_movie_payload_from_post();

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to(admin_section_url('movies'));
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

    redirect_to(admin_section_url('movies'));
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
        redirect_to(admin_section_url('movies'));
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

    redirect_to(admin_section_url('movies'));
}

function handle_movie_set_active(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    $movieId = positive_int_from_request($_POST['movie_id'] ?? null);
    $targetStatus = admin_bool_from_post($_POST['target_status'] ?? 0);

    if ($movieId === null) {
        flash_set('error', 'Selecciona una pelicula valida para cambiar su estado.');
        redirect_to(admin_section_url('movies'));
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

    redirect_to(admin_section_url('movies'));
}

function handle_movie_delete(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    $movieId = positive_int_from_request($_POST['movie_id'] ?? null);

    if ($movieId === null) {
        flash_set('error', 'Selecciona una pelicula valida para eliminar.');
        redirect_to(admin_section_url('movies'));
    }

    try {
        if (admin_movie_find_by_id((int) $movieId) === null) {
            flash_set('error', 'La pelicula seleccionada no existe.');
        } elseif (admin_movie_has_showtimes((int) $movieId)) {
            flash_set('error', 'No se puede eliminar esta película porque tiene funciones asociadas. Puedes desactivarla.');
        } else {
            admin_movie_delete((int) $movieId);
            flash_set('success', 'Película eliminada correctamente.');
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo eliminar la pelicula en este momento.');
    }

    redirect_to(admin_section_url('movies'));
}

function handle_showtime_create(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    [$payload, $errors] = admin_showtime_payload_from_post(null);

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to(admin_section_url('showtimes'));
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

    redirect_to(admin_section_url('showtimes'));
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
        redirect_to(admin_section_url('showtimes'));
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

    redirect_to(admin_section_url('showtimes'));
}

function handle_showtime_set_active(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    $showtimeId = positive_int_from_request($_POST['showtime_id'] ?? null);
    $targetStatus = admin_target_status_from_post($_POST['target_status'] ?? null);
    $errors = [];

    if ($showtimeId === null) {
        $errors[] = 'Selecciona una funcion valida para cambiar su estado.';
    }

    if ($targetStatus === null) {
        $errors[] = 'Selecciona un estado valido para la funcion.';
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

    if ($targetStatus === true && $showtime !== null) {
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
        redirect_to(admin_section_url('showtimes'));
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
        flash_set('error', 'No se pudo actualizar el estado de la funcion en este momento.');
    }

    redirect_to(admin_section_url('showtimes'));
}

function handle_showtime_deactivate(): void
{
    $_POST['target_status'] = '0';
    handle_showtime_set_active();
}

function handle_showtime_delete(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    $showtimeId = positive_int_from_request($_POST['showtime_id'] ?? null);

    if ($showtimeId === null) {
        flash_set('error', 'Selecciona una funcion valida para eliminar.');
        redirect_to(admin_section_url('showtimes'));
    }

    try {
        if (admin_showtime_find_by_id((int) $showtimeId) === null) {
            flash_set('error', 'La funcion seleccionada no existe.');
        } elseif (admin_showtime_has_reservations((int) $showtimeId)) {
            flash_set('error', 'No se puede eliminar esta función porque tiene reservas asociadas. Puedes desactivarla.');
        } else {
            admin_showtime_delete((int) $showtimeId);
            flash_set('success', 'Función eliminada correctamente.');
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo eliminar la funcion en este momento.');
    }

    redirect_to(admin_section_url('showtimes'));
}

function handle_concession_product_create(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    if (!concession_products_table_exists()) {
        flash_set('error', CONCESSION_PRODUCTS_SETUP_MESSAGE);
        redirect_to(admin_section_url('concessions'));
    }

    [$payload, $errors] = admin_concession_product_payload_from_post();

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to(admin_section_url('concessions'));
    }

    try {
        concession_product_create(
            $payload['name'],
            $payload['description'],
            $payload['price_amount'],
            $payload['icon'],
            $payload['badge'],
            $payload['is_active'],
            $payload['sort_order']
        );
        flash_set('success', 'Producto de confiteria creado correctamente.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo crear el producto de confiteria en este momento.');
    }

    redirect_to(admin_section_url('concessions'));
}

function handle_concession_product_update(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    if (!concession_products_table_exists()) {
        flash_set('error', CONCESSION_PRODUCTS_SETUP_MESSAGE);
        redirect_to(admin_section_url('concessions'));
    }

    $productId = positive_int_from_request($_POST['product_id'] ?? null);
    [$payload, $errors] = admin_concession_product_payload_from_post();

    if ($productId === null) {
        $errors[] = 'Selecciona un producto valido para editar.';
    }

    if ($errors === [] && $productId !== null) {
        try {
            if (concession_product_find_by_id((int) $productId) === null) {
                $errors[] = 'El producto seleccionado no existe.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar el producto seleccionado.';
        }
    }

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to(admin_section_url('concessions'));
    }

    try {
        concession_product_update(
            (int) $productId,
            $payload['name'],
            $payload['description'],
            $payload['price_amount'],
            $payload['icon'],
            $payload['badge'],
            $payload['is_active'],
            $payload['sort_order']
        );
        flash_set('success', 'Producto de confiteria actualizado correctamente.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo actualizar el producto de confiteria en este momento.');
    }

    redirect_to(admin_section_url('concessions'));
}

function handle_concession_product_set_active(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    if (!concession_products_table_exists()) {
        flash_set('error', CONCESSION_PRODUCTS_SETUP_MESSAGE);
        redirect_to(admin_section_url('concessions'));
    }

    $productId = positive_int_from_request($_POST['product_id'] ?? null);
    $targetStatus = admin_target_status_from_post($_POST['target_status'] ?? null);
    $errors = [];

    if ($productId === null) {
        $errors[] = 'Selecciona un producto valido para cambiar su estado.';
    }

    if ($targetStatus === null) {
        $errors[] = 'Selecciona un estado valido para el producto.';
    }

    if ($productId !== null) {
        try {
            if (concession_product_find_by_id((int) $productId) === null) {
                $errors[] = 'El producto seleccionado no existe.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar el producto seleccionado.';
        }
    }

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to(admin_section_url('concessions'));
    }

    try {
        concession_product_set_active((int) $productId, $targetStatus);
        flash_set(
            'success',
            $targetStatus ? 'Producto de confiteria activado correctamente.' : 'Producto de confiteria desactivado correctamente.'
        );
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo actualizar el estado del producto en este momento.');
    }

    redirect_to(admin_section_url('concessions'));
}

function handle_concession_product_delete(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    if (!concession_products_table_exists()) {
        flash_set('error', CONCESSION_PRODUCTS_SETUP_MESSAGE);
        redirect_to(admin_section_url('concessions'));
    }

    $productId = positive_int_from_request($_POST['product_id'] ?? null);

    if ($productId === null) {
        flash_set('error', 'Selecciona un producto valido para eliminar.');
        redirect_to(admin_section_url('concessions'));
    }

    try {
        if (concession_product_find_by_id((int) $productId) === null) {
            flash_set('error', 'El producto seleccionado no existe.');
        } else {
            concession_product_delete((int) $productId);
            flash_set('success', 'Producto de confiteria eliminado correctamente.');
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo eliminar el producto de confiteria en este momento.');
    }

    redirect_to(admin_section_url('concessions'));
}

function handle_admin_coupon_create(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    if (!coupons_table_exists()) {
        flash_set('error', COUPONS_SETUP_MESSAGE);
        redirect_to(admin_section_url('coupons'));
    }

    [$payload, $errors] = admin_coupon_payload_from_post();

    if ($errors === []) {
        try {
            if (coupon_code_exists($payload['code'])) {
                $errors[] = 'Ya existe un cupon con ese codigo.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar el codigo del cupon.';
        }
    }

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to(admin_section_url('coupons', 'create'));
    }

    try {
        coupon_create(
            $payload['code'],
            $payload['description'],
            $payload['checkout_type'],
            $payload['discount_type'],
            $payload['discount_value'],
            $payload['is_active'],
            $payload['starts_at'],
            $payload['ends_at']
        );
        flash_set('success', 'Cupon demo creado correctamente.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo crear el cupon demo. Revisa que el codigo no este duplicado.');
    }

    redirect_to(admin_section_url('coupons'));
}

function handle_admin_coupon_update(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    if (!coupons_table_exists()) {
        flash_set('error', COUPONS_SETUP_MESSAGE);
        redirect_to(admin_section_url('coupons'));
    }

    $couponId = positive_int_from_request($_POST['coupon_id'] ?? null);
    [$payload, $errors] = admin_coupon_payload_from_post();

    if ($couponId === null) {
        $errors[] = 'Selecciona un cupon valido para editar.';
    }

    if ($errors === [] && $couponId !== null) {
        try {
            if (coupon_find_by_id((int) $couponId) === null) {
                $errors[] = 'El cupon seleccionado no existe.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar el cupon seleccionado.';
        }
    }

    if ($errors === [] && $couponId !== null) {
        try {
            if (coupon_code_exists($payload['code'], (int) $couponId)) {
                $errors[] = 'Ya existe otro cupon con ese codigo.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar el codigo del cupon.';
        }
    }

    if ($errors !== []) {
        admin_flash_errors($errors);
        $redirectParams = $couponId !== null ? ['coupon_id' => (int) $couponId] : [];
        redirect_to(admin_section_url('coupons', 'edit', $redirectParams));
    }

    try {
        coupon_update(
            (int) $couponId,
            $payload['code'],
            $payload['description'],
            $payload['checkout_type'],
            $payload['discount_type'],
            $payload['discount_value'],
            $payload['is_active'],
            $payload['starts_at'],
            $payload['ends_at']
        );
        flash_set('success', 'Cupon demo actualizado correctamente.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo actualizar el cupon demo en este momento.');
    }

    redirect_to(admin_section_url('coupons'));
}

function handle_admin_coupon_set_active(): void
{
    auth_require_admin_action();
    csrf_require_valid_post();

    if (!coupons_table_exists()) {
        flash_set('error', COUPONS_SETUP_MESSAGE);
        redirect_to(admin_section_url('coupons'));
    }

    $couponId = positive_int_from_request($_POST['coupon_id'] ?? null);
    $targetStatus = admin_target_status_from_post($_POST['target_status'] ?? null);
    $errors = [];

    if ($couponId === null) {
        $errors[] = 'Selecciona un cupon valido para cambiar su estado.';
    }

    if ($targetStatus === null) {
        $errors[] = 'Selecciona un estado valido para el cupon.';
    }

    if ($couponId !== null) {
        try {
            if (coupon_find_by_id((int) $couponId) === null) {
                $errors[] = 'El cupon seleccionado no existe.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar el cupon seleccionado.';
        }
    }

    if ($errors !== []) {
        admin_flash_errors($errors);
        redirect_to(admin_section_url('coupons'));
    }

    try {
        coupon_set_active((int) $couponId, $targetStatus);
        flash_set(
            'success',
            $targetStatus ? 'Cupon demo activado correctamente.' : 'Cupon demo desactivado correctamente.'
        );
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo actualizar el estado del cupon en este momento.');
    }

    redirect_to(admin_section_url('coupons'));
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

function admin_reservation_filters_from_request(array $request): array
{
    $status = '';
    $statusValue = '';

    if (is_scalar($request['status'] ?? null)) {
        $statusValue = strtolower(trim((string) $request['status']));
    }

    if (in_array($statusValue, ['pending', 'confirmed', 'cancelled'], true)) {
        $status = $statusValue;
    }

    return [
        'status' => $status,
        'q' => movie_filter_value_from_request($request['q'] ?? '', 80),
    ];
}

function admin_payment_filters_from_request(array $request): array
{
    $checkoutType = '';
    $checkoutTypeValue = '';

    if (is_scalar($request['checkout_type'] ?? null)) {
        $checkoutTypeValue = strtolower(trim((string) $request['checkout_type']));
    }

    if (in_array($checkoutTypeValue, PAYMENT_ALLOWED_CHECKOUT_TYPES, true)) {
        $checkoutType = $checkoutTypeValue;
    }

    $status = '';
    $statusValue = '';

    if (is_scalar($request['status'] ?? null)) {
        $statusValue = strtolower(trim((string) $request['status']));
    }

    if ($statusValue === PAYMENT_STATUS_SIMULATED_PAID) {
        $status = $statusValue;
    }

    return [
        'checkout_type' => $checkoutType,
        'status' => $status,
        'q' => movie_filter_value_from_request($request['q'] ?? '', 100),
        'date_from' => admin_date_filter_from_request($request['date_from'] ?? null),
        'date_to' => admin_date_filter_from_request($request['date_to'] ?? null),
    ];
}

function admin_showtime_filters_from_request(array $request): array
{
    $status = '';
    $statusValue = '';

    if (is_scalar($request['showtime_status'] ?? null)) {
        $statusValue = strtolower(trim((string) $request['showtime_status']));
    }

    if (in_array($statusValue, ['active', 'inactive'], true)) {
        $status = $statusValue;
    }

    return [
        'room_id' => positive_int_from_request($request['showtime_room_id'] ?? null),
        'date_from' => admin_date_filter_from_request($request['showtime_date_from'] ?? null),
        'date_to' => admin_date_filter_from_request($request['showtime_date_to'] ?? null),
        'status' => $status,
        'q' => movie_filter_value_from_request($request['showtime_q'] ?? '', 80),
    ];
}

function admin_date_filter_from_request(mixed $value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $date = trim((string) $value);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return '';
    }

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();

    if (
        $parsed === false
        || (
            is_array($errors)
            && ((int) ($errors['warning_count'] ?? 0) > 0 || (int) ($errors['error_count'] ?? 0) > 0)
        )
    ) {
        return '';
    }

    return $parsed->format('Y-m-d');
}

function admin_section_from_request(mixed $value): string
{
    if (!is_scalar($value)) {
        return 'summary';
    }

    $section = strtolower(trim((string) $value));
    $allowedSections = ['summary', 'rooms', 'movies', 'showtimes', 'concessions', 'coupons', 'reservations'];

    return in_array($section, $allowedSections, true) ? $section : 'summary';
}

function admin_mode_from_request(mixed $value): string
{
    if (!is_scalar($value)) {
        return 'list';
    }

    $mode = strtolower(trim((string) $value));
    $allowedModes = ['list', 'create', 'edit'];

    return in_array($mode, $allowedModes, true) ? $mode : 'list';
}

function admin_section_url(string $section, string $mode = 'list', array $params = []): string
{
    $section = admin_section_from_request($section);
    $mode = admin_mode_from_request($mode);
    $query = [
        'page' => 'admin',
        'admin_section' => $section,
    ];

    if (in_array($section, ['rooms', 'movies', 'showtimes', 'concessions', 'coupons'], true) && $mode !== 'list') {
        $query['admin_mode'] = $mode;
    }

    foreach ($params as $key => $value) {
        if (is_string($key) && is_scalar($value)) {
            $query[$key] = (string) $value;
        }
    }

    return 'index.php?' . http_build_query($query) . '#admin-' . $section;
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

function admin_concession_product_payload_from_post(): array
{
    $name = admin_trimmed_text_from_post($_POST['name'] ?? '');
    $description = admin_trimmed_text_from_post($_POST['description'] ?? '');
    $priceAmount = admin_price_amount_from_post($_POST['price_amount'] ?? null);
    $icon = admin_optional_text_from_post($_POST['icon'] ?? '', 20);
    $badge = admin_optional_text_from_post($_POST['badge'] ?? '', 40);
    $sortOrder = admin_sort_order_from_post($_POST['sort_order'] ?? null);
    $isActive = admin_bool_from_post($_POST['is_active'] ?? 1);
    $errors = [];

    if ($name === '') {
        $errors[] = 'El nombre del producto es obligatorio.';
    } elseif (admin_text_length($name) > 120) {
        $errors[] = 'El nombre del producto no puede superar 120 caracteres.';
    }

    if ($description === '') {
        $errors[] = 'La descripcion del producto es obligatoria.';
    } elseif (admin_text_length($description) > 255) {
        $errors[] = 'La descripcion del producto no puede superar 255 caracteres.';
    }

    if ($priceAmount === null) {
        $errors[] = 'El precio debe ser numerico y mayor que 0.';
    }

    if ($icon === null) {
        $errors[] = 'El icono no puede superar 20 caracteres ni contener caracteres de control.';
    }

    if ($badge === null) {
        $errors[] = 'La etiqueta no puede superar 40 caracteres ni contener caracteres de control.';
    }

    return [
        [
            'name' => $name,
            'description' => $description,
            'price_amount' => $priceAmount ?? 0.0,
            'icon' => $icon,
            'badge' => $badge,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ],
        $errors,
    ];
}

function admin_coupon_payload_from_post(): array
{
    $code = checkout_coupon_code_from_value($_POST['code'] ?? '');
    $description = admin_trimmed_text_from_post($_POST['description'] ?? '');
    $checkoutType = admin_coupon_choice_from_post($_POST['checkout_type'] ?? '', COUPON_ALLOWED_CHECKOUT_TYPES);
    $discountType = admin_coupon_choice_from_post($_POST['discount_type'] ?? '', COUPON_ALLOWED_DISCOUNT_TYPES);
    $discountValue = admin_coupon_discount_value_from_post($_POST['discount_value'] ?? null);
    $isActive = admin_bool_from_post($_POST['is_active'] ?? 1);
    [$startsAt, $startsAtError] = admin_optional_datetime_from_post($_POST['starts_at'] ?? null, 'inicio');
    [$endsAt, $endsAtError] = admin_optional_datetime_from_post($_POST['ends_at'] ?? null, 'termino');
    $errors = [];

    if ($code === '') {
        $errors[] = 'El codigo del cupon es obligatorio.';
    } elseif (!preg_match('/^[A-Z0-9_-]{3,24}$/', $code)) {
        $errors[] = 'El codigo debe tener 3 a 24 caracteres, sin espacios, usando letras, numeros, guion o guion bajo.';
    }

    if ($description === '') {
        $errors[] = 'La descripcion del cupon es obligatoria.';
    } elseif (admin_text_length($description) > 255 || preg_match('/[\x00-\x1F\x7F]/', $description) === 1) {
        $errors[] = 'La descripcion no puede superar 255 caracteres ni contener caracteres de control.';
    }

    if ($checkoutType === null) {
        $errors[] = 'Selecciona un tipo de checkout valido para el cupon.';
    }

    if ($discountType === null) {
        $errors[] = 'Selecciona un tipo de descuento valido.';
    }

    if ($discountValue === null) {
        $errors[] = 'El descuento debe ser numerico y mayor que 0.';
    } elseif ($discountType === 'percent' && ($discountValue < 1.0 || $discountValue > 100.0)) {
        $errors[] = 'El descuento porcentual debe estar entre 1 y 100.';
    }

    if ($startsAtError !== null) {
        $errors[] = $startsAtError;
    }

    if ($endsAtError !== null) {
        $errors[] = $endsAtError;
    }

    if ($startsAt !== null && $endsAt !== null && new DateTimeImmutable($endsAt) <= new DateTimeImmutable($startsAt)) {
        $errors[] = 'La fecha de termino debe ser posterior a la fecha de inicio.';
    }

    return [
        [
            'code' => $code,
            'description' => $description,
            'checkout_type' => $checkoutType ?? 'reservation',
            'discount_type' => $discountType ?? 'percent',
            'discount_value' => $discountValue ?? 0.0,
            'is_active' => $isActive,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ],
        $errors,
    ];
}

function admin_coupon_choice_from_post(mixed $value, array $allowedValues): ?string
{
    if (!is_scalar($value)) {
        return null;
    }

    $choice = strtolower(trim((string) $value));

    return in_array($choice, $allowedValues, true) ? $choice : null;
}

function admin_coupon_discount_value_from_post(mixed $value): ?float
{
    if (!is_scalar($value)) {
        return null;
    }

    $normalized = str_replace(',', '.', trim((string) $value));

    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $normalized)) {
        return null;
    }

    $amount = (float) $normalized;

    return $amount > 0 ? $amount : null;
}

function admin_optional_datetime_from_post(mixed $value, string $fieldLabel): array
{
    if (!is_scalar($value)) {
        return [null, null];
    }

    $rawValue = trim((string) $value);

    if ($rawValue === '') {
        return [null, null];
    }

    $date = admin_datetime_from_post($rawValue);

    if ($date === null) {
        return [null, 'Ingresa una fecha y hora de ' . $fieldLabel . ' valida.'];
    }

    return [$date, null];
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

function admin_optional_text_from_post(mixed $value, int $maxLength): ?string
{
    if (!is_scalar($value)) {
        return null;
    }

    $text = trim((string) $value);

    if ($text === '') {
        return '';
    }

    if (admin_text_length($text) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $text) === 1) {
        return null;
    }

    return $text;
}

function admin_price_amount_from_post(mixed $value): ?float
{
    if (!is_scalar($value)) {
        return null;
    }

    $normalized = str_replace(',', '.', trim((string) $value));

    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $normalized)) {
        return null;
    }

    $amount = (float) $normalized;

    return $amount > 0 ? $amount : null;
}

function admin_sort_order_from_post(mixed $value): int
{
    if (!is_scalar($value)) {
        return 0;
    }

    $normalized = trim((string) $value);

    if ($normalized === '' || !preg_match('/^-?\d+$/', $normalized)) {
        return 0;
    }

    return min(9999, max(0, (int) $normalized));
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

function admin_target_status_from_post(mixed $value): ?bool
{
    if (!is_scalar($value)) {
        return null;
    }

    $value = (string) $value;

    if ($value === '1') {
        return true;
    }

    if ($value === '0') {
        return false;
    }

    return null;
}

function admin_flash_errors(array $errors): void
{
    foreach ($errors as $error) {
        flash_set('error', (string) $error);
    }
}
