<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/assets.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/../models/Admin.php';
require_once __DIR__ . '/../models/ConcessionProduct.php';
require_once __DIR__ . '/../models/Movie.php';
require_once __DIR__ . '/../models/Reservation.php';
require_once __DIR__ . '/../models/User.php';

const AUTH_MIN_PASSWORD_LENGTH = 8;
const CONCESSION_PRODUCTS_SETUP_MESSAGE = 'La tabla de productos de confitería no está instalada. Ejecuta database/upgrade_concession_products.sql o reimporta schema.sql y seed.sql en el entorno local.';
const CONCESSIONS_CART_SESSION_KEY = 'concessions_cart';
const CONCESSIONS_LAST_CHECKOUT_SESSION_KEY = 'last_concessions_checkout';
const CONCESSIONS_CART_MAX_QUANTITY = 10;
const CHECKOUT_ALLOWED_TYPES = ['reservation', 'concessions', 'membership'];
const CHECKOUT_MEMBERSHIP_PLAN_LABEL = 'Socio Cine Demo';
const CHECKOUT_MEMBERSHIP_DEMO_TOTAL = 0.0;

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

function checkout_type_from_request(mixed $value): ?string
{
    if (!is_scalar($value)) {
        return null;
    }

    $type = strtolower(trim((string) $value));

    return in_array($type, CHECKOUT_ALLOWED_TYPES, true) ? $type : null;
}

function checkout_url(string $type, array $params = []): string
{
    $query = array_merge(['page' => 'checkout', 'type' => $type], $params);

    return 'index.php?' . http_build_query($query);
}

function concession_cart_redirect(): void
{
    redirect_to('index.php?page=confiteria');
}

function concession_cart_quantity_from_request(mixed $value): ?int
{
    $quantity = positive_int_from_request($value);

    if ($quantity === null || $quantity > CONCESSIONS_CART_MAX_QUANTITY) {
        return null;
    }

    return $quantity;
}

function concession_cart_get(): array
{
    app_session_start();

    $storedCart = $_SESSION[CONCESSIONS_CART_SESSION_KEY] ?? [];
    $cart = [];
    $changed = !is_array($storedCart);

    if (is_array($storedCart)) {
        foreach ($storedCart as $productId => $quantity) {
            $normalizedProductId = positive_int_from_request($productId);
            $normalizedQuantity = positive_int_from_request($quantity);

            if ($normalizedProductId === null || $normalizedQuantity === null) {
                $changed = true;
                continue;
            }

            if ($normalizedQuantity > CONCESSIONS_CART_MAX_QUANTITY) {
                $normalizedQuantity = CONCESSIONS_CART_MAX_QUANTITY;
                $changed = true;
            }

            $cart[$normalizedProductId] = $normalizedQuantity;
        }
    }

    if ($changed) {
        concession_cart_save($cart);
    }

    return $cart;
}

function concession_cart_save(array $cart): void
{
    app_session_start();

    ksort($cart);
    $_SESSION[CONCESSIONS_CART_SESSION_KEY] = $cart;
}

function concession_cart_summary_from_products(array $products): array
{
    $productsById = [];

    foreach ($products as $product) {
        $productId = (int) ($product['id'] ?? 0);

        if ($productId > 0) {
            $productsById[$productId] = $product;
        }
    }

    $cart = concession_cart_get();
    $items = [];
    $total = 0.0;
    $pruned = false;

    foreach ($cart as $productId => $quantity) {
        if (!isset($productsById[$productId])) {
            unset($cart[$productId]);
            $pruned = true;
            continue;
        }

        $product = $productsById[$productId];
        $unitPrice = (float) ($product['price_amount'] ?? 0);
        $subtotal = $unitPrice * $quantity;
        $total += $subtotal;

        $items[] = [
            'product_id' => $productId,
            'name' => (string) ($product['name'] ?? ''),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_price_label' => reservation_format_money($unitPrice) . ' demo',
            'subtotal' => $subtotal,
            'subtotal_label' => reservation_format_money($subtotal) . ' demo',
        ];
    }

    if ($pruned) {
        concession_cart_save($cart);
    }

    return [
        'items' => $items,
        'total' => $total,
        'total_label' => reservation_format_money($total) . ' demo',
        'is_empty' => $items === [],
        'pruned' => $pruned,
    ];
}

function concession_checkout_last_receipt(): ?array
{
    app_session_start();

    $receipt = $_SESSION[CONCESSIONS_LAST_CHECKOUT_SESSION_KEY] ?? null;

    return is_array($receipt) ? $receipt : null;
}

function concession_checkout_save_receipt(array $cartSummary): array
{
    app_session_start();

    $items = [];

    foreach (($cartSummary['items'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $items[] = [
            'name' => (string) ($item['name'] ?? ''),
            'quantity' => (int) ($item['quantity'] ?? 0),
            'unit_price_label' => (string) ($item['unit_price_label'] ?? ''),
            'subtotal_label' => (string) ($item['subtotal_label'] ?? ''),
        ];
    }

    $receipt = [
        'code' => 'CONF-' . date('Ymd-His') . '-' . random_int(100, 999),
        'created_at' => date('Y-m-d H:i:s'),
        'items' => $items,
        'total_label' => (string) ($cartSummary['total_label'] ?? reservation_format_money(0) . ' demo'),
    ];

    $_SESSION[CONCESSIONS_LAST_CHECKOUT_SESSION_KEY] = $receipt;

    return $receipt;
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

function render_checkout_page(): void
{
    auth_require_login();

    $user = current_user();
    $type = checkout_type_from_request($_GET['type'] ?? null);

    if ($type === null) {
        render_not_found_page(
            'Checkout no encontrado',
            'El tipo de checkout solicitado no existe o no esta disponible.',
            flash_get()
        );
        return;
    }

    $checkout = [
        'type' => $type,
        'active_nav' => 'cartelera',
        'title' => 'Checkout simulado',
        'eyebrow' => 'Pago simulado academico',
        'heading' => 'Checkout simulado',
        'lead' => 'Flujo academico sin pago real, sin pasarela y sin solicitud de datos bancarios.',
        'summary_title' => 'Resumen',
        'total_label' => reservation_format_money(0) . ' demo',
        'can_confirm' => false,
        'return_url' => 'index.php?page=cartelera',
        'confirm_fields' => ['type' => $type],
    ];

    if ($type === 'reservation') {
        $reservationId = positive_int_from_request($_GET['reservation_id'] ?? null);

        if ($reservationId === null) {
            render_not_found_page(
                'Reserva no encontrada',
                'Selecciona una reserva pendiente valida para abrir el checkout.',
                flash_get()
            );
            return;
        }

        try {
            $reservation = reservation_find_for_user($reservationId, (int) ($user['id'] ?? 0));
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            render_error_page(
                'No se pudo cargar el checkout',
                'Intenta nuevamente mas tarde.',
                500,
                flash_get()
            );
            return;
        }

        if ($reservation === null) {
            render_not_found_page(
                'Reserva no encontrada',
                'La reserva solicitada no existe o no pertenece a tu cuenta.',
                flash_get()
            );
            return;
        }

        if ((string) ($reservation['status'] ?? '') !== 'pending') {
            render_error_page(
                'Checkout no disponible',
                'Solo las reservas pendientes pueden confirmarse con checkout simulado.',
                409,
                flash_get()
            );
            return;
        }

        $checkout = array_replace($checkout, [
            'active_nav' => 'my_reservations',
            'title' => 'Checkout reserva',
            'heading' => 'Confirma tu reserva',
            'lead' => 'Revisa las entradas antes de confirmar el pago simulado academico.',
            'summary_title' => 'Reserva pendiente',
            'total_label' => reservation_format_money((float) ($reservation['total_amount'] ?? 0)),
            'can_confirm' => true,
            'return_url' => 'index.php?page=my_reservations',
            'confirm_fields' => [
                'type' => 'reservation',
                'reservation_id' => (string) $reservationId,
            ],
            'reservation' => $reservation,
            'showtime_labels' => reservation_showtime_labels($reservation),
        ]);
    } elseif ($type === 'concessions') {
        $cartSummary = [
            'items' => [],
            'total' => 0.0,
            'total_label' => reservation_format_money(0) . ' demo',
            'is_empty' => true,
            'pruned' => false,
        ];
        $cartLoadError = false;
        $catalogSetupRequired = false;

        try {
            if (!concession_products_table_exists()) {
                $catalogSetupRequired = true;
            } else {
                $cartSummary = concession_cart_summary_from_products(concession_products_active_all());

                if (($cartSummary['pruned'] ?? false) === true) {
                    flash_set('info', 'Se quitaron del carrito productos que ya no estan activos.');
                }
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $cartLoadError = true;
        }

        $cartItems = is_array($cartSummary['items'] ?? null) ? $cartSummary['items'] : [];

        $checkout = array_replace($checkout, [
            'active_nav' => 'confiteria',
            'title' => 'Checkout confiteria',
            'heading' => 'Confiteria demo',
            'lead' => 'Confirma el carrito de sesion con pago simulado. No se crea una orden en base de datos.',
            'summary_title' => 'Carrito demo',
            'total_label' => (string) ($cartSummary['total_label'] ?? reservation_format_money(0) . ' demo'),
            'can_confirm' => $cartItems !== [] && !$cartLoadError && !$catalogSetupRequired,
            'return_url' => 'index.php?page=confiteria',
            'cart_summary' => $cartSummary,
            'cart_load_error' => $cartLoadError,
            'catalog_setup_required' => $catalogSetupRequired,
            'last_receipt' => concession_checkout_last_receipt(),
        ]);
    } elseif ($type === 'membership') {
        $memberDemoActive = is_member_demo_active();

        $checkout = array_replace($checkout, [
            'active_nav' => 'socios',
            'title' => 'Checkout socios',
            'heading' => 'Hazte socio demo',
            'lead' => 'Activa una membresia demo en esta sesion con pago simulado academico.',
            'summary_title' => CHECKOUT_MEMBERSHIP_PLAN_LABEL,
            'total_label' => reservation_format_money(CHECKOUT_MEMBERSHIP_DEMO_TOTAL) . ' demo',
            'can_confirm' => !$memberDemoActive,
            'return_url' => 'index.php?page=socios',
            'member_demo_active' => $memberDemoActive,
            'membership_plan' => [
                'name' => CHECKOUT_MEMBERSHIP_PLAN_LABEL,
                'total_label' => reservation_format_money(CHECKOUT_MEMBERSHIP_DEMO_TOTAL) . ' demo',
                'benefits' => [
                    'Estado de socio visible durante la sesion actual.',
                    'Beneficios academicos simulados sin descuentos reales.',
                    'Sin persistencia en base de datos y sin cambios en usuarios.',
                ],
            ],
        ]);
    }

    $messages = flash_get();

    require __DIR__ . '/../views/checkout.php';
}

function handle_checkout_confirm(): void
{
    auth_require_login();
    csrf_require_valid_post();

    $user = current_user();
    $type = checkout_type_from_request($_POST['type'] ?? null);

    if ($type === null) {
        render_not_found_page(
            'Checkout no encontrado',
            'El tipo de checkout enviado no existe o no esta disponible.'
        );
        return;
    }

    if ($type === 'reservation') {
        $reservationId = positive_int_from_request($_POST['reservation_id'] ?? null);

        if ($reservationId === null) {
            flash_set('error', 'Selecciona una reserva pendiente valida.');
            redirect_to('index.php?page=my_reservations');
        }

        $result = reservation_confirm_pending_for_user($reservationId, (int) ($user['id'] ?? 0));
        $isOk = ($result['ok'] ?? false) === true;

        flash_set(
            $isOk ? 'success' : 'error',
            (string) ($result['message'] ?? 'No se pudo confirmar la reserva.')
        );

        if ($isOk) {
            redirect_to('index.php?page=ticket&reservation_id=' . (int) $reservationId);
        }

        redirect_to(checkout_url('reservation', ['reservation_id' => (int) $reservationId]));
    }

    if ($type === 'concessions') {
        $cartSummary = [
            'items' => [],
            'total' => 0.0,
            'total_label' => reservation_format_money(0) . ' demo',
            'is_empty' => true,
            'pruned' => false,
        ];

        try {
            if (concession_products_table_exists()) {
                $cartSummary = concession_cart_summary_from_products(concession_products_active_all());
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            flash_set('error', 'No se pudo validar el carrito en este momento.');
            redirect_to(checkout_url('concessions'));
        }

        if (($cartSummary['items'] ?? []) === []) {
            flash_set('error', 'Agrega productos al carrito antes de confirmar el checkout.');
            redirect_to(checkout_url('concessions'));
        }

        concession_checkout_save_receipt($cartSummary);
        concession_cart_save([]);
        flash_set('success', 'Checkout de confiteria confirmado con pago simulado.');
        redirect_to(checkout_url('concessions', ['result' => 'success']));
    }

    if ($type === 'membership') {
        if (is_member_demo_active()) {
            flash_set('info', 'La membresia demo ya esta activa.');
            redirect_to('index.php?page=socios');
        }

        set_member_demo_active(true);
        flash_set('success', 'Membresia demo activada con pago simulado.');
        redirect_to('index.php?page=socios');
    }
}

function render_coming_soon_page(string $page): void
{
    auth_require_login();

    $pages = [
        'confiteria' => [
            'activeNav' => 'confiteria',
            'title' => 'Confiteria',
            'eyebrow' => 'Confiteria demo',
            'headline' => 'Confiteria',
            'lead' => 'Catálogo demo de combos para acompañar tu función con carrito simple en sesión.',
            'support' => 'La compra real no está disponible: el checkout es simulado y no crea pedidos.',
            'accent' => 'Carrito demo',
            'accentCopy' => 'El carrito guarda solo IDs y cantidades en sesión. Los precios se recalculan desde productos activos.',
            'catalog' => [],
            'showCatalog' => true,
            'catalogLoadError' => false,
            'catalogSetupRequired' => false,
            'items' => [
                ['icon' => '🍿', 'label' => 'Productos activos', 'copy' => 'El catálogo visible se lee desde concession_products.'],
                ['icon' => '🛒', 'label' => 'Carrito en sesión', 'copy' => 'Solo guarda product_id y cantidad por item.'],
                ['icon' => '💳', 'label' => 'Pago simulado', 'copy' => 'No existe pasarela, tarjeta ni pedido persistido.'],
                ['icon' => '🧾', 'label' => 'Sin pedidos', 'copy' => 'No se crean compras, stock ni ordenes de confiteria.'],
            ],
            'notes' => [
                'Compra real no disponible.',
                'No hay pago real.',
                'Checkout simulado sin pasarela.',
                'Sin stock, pedidos ni persistencia del carrito en base de datos.',
            ],
        ],
        'socios' => [
            'activeNav' => 'socios',
            'title' => 'Socios',
            'eyebrow' => 'MEMBRESÍA DEMO',
            'headline' => 'HAZTE SOCIO DEMO',
            'panelKicker' => 'Demo en sesión',
            'panelHeadline' => 'Activa tu membresía demo',
            'lead' => 'Activa una membresía demo para probar el estado de socio durante esta sesión.',
            'support' => '',
            'accent' => 'Estado de sesión',
            'accentCopy' => 'La membresía demo vive solo en tu sesión actual.',
            'featureIcon' => 'DEMO',
            'items' => [
                [
                    'icon' => '🏷️',
                    'label' => 'Beneficios simulados',
                    'copy' => 'Ventajas de socio simuladas sin aplicar descuentos reales.',
                    'status' => 'Simulado',
                ],
                [
                    'icon' => '🎟️',
                    'label' => 'Estado en sesión',
                    'copy' => 'El estado de socio demo se conserva mientras mantengas la sesión activa.',
                    'status' => 'Sesión',
                ],
                [
                    'icon' => '⭐',
                    'label' => 'Puntos ficticios',
                    'copy' => 'Referencia académica de beneficios, sin acumulación real.',
                    'status' => 'Simulado',
                ],
                [
                    'icon' => '🎂',
                    'label' => 'Sin pago real',
                    'copy' => 'No se solicita tarjeta, pasarela ni comprobante de pago.',
                    'status' => 'Demo',
                ],
                [
                    'icon' => '🍿',
                    'label' => 'Sin descuentos activos',
                    'copy' => 'La membresía no cambia precios de entradas, reservas ni confitería.',
                    'status' => 'Sin precios',
                ],
                [
                    'icon' => '🎁',
                    'label' => 'Proyecto académico',
                    'copy' => 'Beneficios simulados para mostrar el flujo de socio demo.',
                    'status' => 'Académico',
                ],
            ],
            'heroActions' => [
                ['type' => 'link', 'label' => 'Conoce beneficios', 'href' => '#socios-beneficios', 'class' => 'movie-state-link-secondary'],
            ],
            'benefitsLayout' => true,
            'benefitsSectionId' => 'socios-beneficios',
            'notes' => [
                'Membresía demo sin pago real.',
                'Beneficios simulados para el proyecto académico.',
                'No hay pagos, cupones, descuentos activos ni persistencia en base de datos.',
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
    $memberDemoActive = is_member_demo_active();
    $comingSoon = $pages[$page];

    if ($page === 'socios') {
        $comingSoon['lead'] = $memberDemoActive
            ? 'Tu membresía demo está activa en esta sesión.'
            : 'Activa una membresía demo para probar el estado de socio durante esta sesión.';
        $comingSoon['notes'] = [];
        $comingSoon['panelHeadline'] = $memberDemoActive
            ? 'Membresía demo activa'
            : 'Activa tu membresía demo';
        $comingSoon['memberDemo'] = [
            'isActive' => $memberDemoActive,
            'stateActiveLabel' => 'Socio Cine Demo activo',
            'stateInactiveLabel' => 'Sin membresía demo',
            'stateActiveCopy' => 'Ya puedes ver tu estado de socio demo mientras mantengas la sesión iniciada.',
            'stateInactiveCopy' => 'Activa la membresía demo para habilitar el estado de socio en esta sesión.',
            'stateNotes' => [
                $memberDemoActive
                    ? 'Estado demo activo solo en esta sesión. No aplica descuentos reales.'
                    : 'Demo académica sin pago real ni persistencia en base de datos.',
            ],
            'activateAction' => checkout_url('membership'),
            'activateMethod' => 'get',
            'deactivateAction' => 'index.php?action=member_demo_deactivate',
            'activateLabel' => 'IR A CHECKOUT DEMO',
            'deactivateLabel' => 'DESACTIVAR MEMBRESÍA DEMO',
        ];
    }

    $cartSummary = [
        'items' => [],
        'total' => 0.0,
        'total_label' => reservation_format_money(0) . ' demo',
        'is_empty' => true,
        'pruned' => false,
    ];

    if ($page === 'confiteria') {
        try {
            if (!concession_products_table_exists()) {
                $comingSoon['catalogSetupRequired'] = true;
            } else {
                $activeProducts = concession_products_active_all();
                $comingSoon['catalog'] = array_map(
                    static fn (array $product): array => [
                        'id' => (int) ($product['id'] ?? 0),
                        'icon' => (string) ($product['icon'] ?? ''),
                        'label' => (string) ($product['badge'] ?? ''),
                        'name' => (string) ($product['name'] ?? ''),
                        'description' => (string) ($product['description'] ?? ''),
                        'price_amount' => (float) ($product['price_amount'] ?? 0),
                        'price' => reservation_format_money((float) ($product['price_amount'] ?? 0)) . ' demo',
                        'button' => 'Agregar',
                    ],
                    $activeProducts
                );
                $cartSummary = concession_cart_summary_from_products($activeProducts);

                if (($cartSummary['pruned'] ?? false) === true) {
                    flash_set('info', 'Se quitaron del carrito productos que ya no están activos.');
                }
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $comingSoon['catalog'] = [];
            $comingSoon['catalogLoadError'] = true;
        }
    }

    $messages = flash_get();

    require __DIR__ . '/../views/coming_soon.php';
}

function handle_member_demo_activate(): void
{
    auth_require_login();
    csrf_require_valid_post();

    if (is_member_demo_active() === true) {
        flash_set('info', 'La membresía demo ya está activa.');
        redirect_to('index.php?page=socios');
    }

    flash_set('info', 'Confirma la membresía demo desde el checkout simulado.');
    redirect_to(checkout_url('membership'));
}

function handle_member_demo_deactivate(): void
{
    auth_require_login();
    csrf_require_valid_post();

    if (is_member_demo_active() === false) {
        flash_set('info', 'La membresía demo ya está desactivada.');
        redirect_to('index.php?page=socios');
    }

    set_member_demo_active(false);
    flash_set('success', 'Membresía demo desactivada correctamente.');
    redirect_to('index.php?page=socios');
}

function handle_concession_add(): void
{
    auth_require_login();
    csrf_require_valid_post();

    $productId = positive_int_from_request($_POST['product_id'] ?? null);
    $quantity = concession_cart_quantity_from_request($_POST['quantity'] ?? 1);

    if ($productId === null || $quantity === null) {
        flash_set('error', 'Selecciona un producto y cantidad validos.');
        concession_cart_redirect();
    }

    try {
        $product = concession_product_find_active_by_id((int) $productId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo validar el producto en este momento.');
        concession_cart_redirect();
    }

    if ($product === null) {
        flash_set('error', 'El producto seleccionado no existe o no esta activo.');
        concession_cart_redirect();
    }

    $cart = concession_cart_get();
    $currentQuantity = (int) ($cart[(int) $productId] ?? 0);
    $newQuantity = $currentQuantity + (int) $quantity;

    if ($newQuantity > CONCESSIONS_CART_MAX_QUANTITY) {
        $newQuantity = CONCESSIONS_CART_MAX_QUANTITY;
        flash_set('info', 'La cantidad maxima por producto es ' . CONCESSIONS_CART_MAX_QUANTITY . '.');
    } else {
        flash_set('success', 'Producto agregado al carrito demo.');
    }

    $cart[(int) $productId] = $newQuantity;
    concession_cart_save($cart);
    concession_cart_redirect();
}

function handle_concession_update(): void
{
    auth_require_login();
    csrf_require_valid_post();

    $productId = positive_int_from_request($_POST['product_id'] ?? null);
    $quantity = concession_cart_quantity_from_request($_POST['quantity'] ?? null);

    if ($productId === null || $quantity === null) {
        flash_set('error', 'Selecciona un producto y cantidad validos.');
        concession_cart_redirect();
    }

    $cart = concession_cart_get();

    try {
        $product = concession_product_find_active_by_id((int) $productId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo validar el producto en este momento.');
        concession_cart_redirect();
    }

    if ($product === null) {
        unset($cart[(int) $productId]);
        concession_cart_save($cart);
        flash_set('error', 'El producto ya no esta activo y fue quitado del carrito.');
        concession_cart_redirect();
    }

    $cart[(int) $productId] = (int) $quantity;
    concession_cart_save($cart);
    flash_set('success', 'Cantidad actualizada.');
    concession_cart_redirect();
}

function handle_concession_remove(): void
{
    auth_require_login();
    csrf_require_valid_post();

    $productId = positive_int_from_request($_POST['product_id'] ?? null);

    if ($productId === null) {
        flash_set('error', 'Selecciona un producto valido para quitar.');
        concession_cart_redirect();
    }

    $cart = concession_cart_get();
    unset($cart[(int) $productId]);
    concession_cart_save($cart);
    flash_set('success', 'Producto quitado del carrito demo.');
    concession_cart_redirect();
}

function handle_concession_clear(): void
{
    auth_require_login();
    csrf_require_valid_post();
    concession_cart_save([]);
    flash_set('success', 'Carrito demo vaciado.');
    concession_cart_redirect();
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
    $adminSection = admin_section_from_request($_GET['admin_section'] ?? null);
    $adminMode = admin_mode_from_request($_GET['admin_mode'] ?? null);

    if (!in_array($adminSection, ['rooms', 'movies', 'showtimes', 'concessions'], true)) {
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
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $adminModeError = 'No se pudo cargar el registro seleccionado.';
            $adminEditItem = null;
        }
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
    $allowedSections = ['summary', 'rooms', 'movies', 'showtimes', 'concessions', 'reservations'];

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

    if (in_array($section, ['rooms', 'movies', 'showtimes', 'concessions'], true) && $mode !== 'list') {
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
